# Phase 2: Core Services Implementation Specification

## Overview
Refactor existing functional code into well-designed services that handle specific business domains with clear responsibilities and proper separation of concerns.

## Service Architecture Principles

### 2.1 Service Design Guidelines
- **Single Responsibility**: Each service handles one business domain
- **Interface Segregation**: Services expose only necessary methods
- **Dependency Injection**: Services receive dependencies via constructor
- **Testability**: All services are unit testable
- **Immutability**: Service state is immutable where possible

## Core Services Specifications

### 2.2 Payment Gateway Service

#### 2.2.1 Current Issues
- **File**: `includes/apw-woo-intuit-payment-functions.php`
- **Problems**: 
  - Credit card surcharge calculation scattered across multiple hooks
  - Duplicate fee prevention logic is complex and error-prone
  - VIP discount timing issues with cart recalculation
  - Hardcoded surcharge rate (3%)

#### 2.2.2 Service Interface
```php
<?php
namespace APW\WooPlugin\Services\Payment;

interface PaymentGatewayServiceInterface {
    public function calculateSurcharge(float $amount, string $gateway_id): float;
    public function applySurcharge(string $gateway_id): void;
    public function removeSurcharge(): void;
    public function getSupportedGateways(): array;
    public function isGatewaySupported(string $gateway_id): bool;
}
```

#### 2.2.3 Implementation
```php
<?php
namespace APW\WooPlugin\Services\Payment;

use APW\WooPlugin\Core\ConfigInterface;
use APW\WooPlugin\Services\LoggerServiceInterface;

class PaymentGatewayService implements PaymentGatewayServiceInterface {
    private ConfigInterface $config;
    private LoggerServiceInterface $logger;
    private array $supported_gateways;

    public function __construct(
        ConfigInterface $config,
        LoggerServiceInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->supported_gateways = [
            'intuit_qbms_credit_card' => [
                'surcharge_rate' => $this->config->get('surcharge_rate', 0.03),
                'fee_name' => 'Credit Card Surcharge'
            ]
        ];
    }

    public function calculateSurcharge(float $amount, string $gateway_id): float {
        if (!$this->isGatewaySupported($gateway_id)) {
            return 0.0;
        }

        $gateway_config = $this->supported_gateways[$gateway_id];
        $surcharge = $amount * $gateway_config['surcharge_rate'];

        $this->logger->info("Calculated surcharge: {$surcharge} for gateway: {$gateway_id}");

        return round($surcharge, 2);
    }

    public function applySurcharge(string $gateway_id): void {
        if (!$this->isGatewaySupported($gateway_id)) {
            return;
        }

        // Remove existing surcharge to prevent duplicates
        $this->removeSurcharge();

        $cart_total = $this->getCartTotalForSurcharge();
        $surcharge_amount = $this->calculateSurcharge($cart_total, $gateway_id);

        if ($surcharge_amount > 0) {
            $gateway_config = $this->supported_gateways[$gateway_id];
            
            WC()->cart->add_fee(
                $gateway_config['fee_name'],
                $surcharge_amount,
                true // Taxable
            );

            $this->logger->info("Applied surcharge: {$surcharge_amount}");
        }
    }

    private function getCartTotalForSurcharge(): float {
        $cart = WC()->cart;
        
        // Base calculation: subtotal + shipping - discounts
        $total = $cart->get_subtotal() + $cart->get_shipping_total();
        
        // Subtract existing fees (like VIP discounts) but not surcharges
        foreach ($cart->get_fees() as $fee) {
            if (strpos($fee->name, 'Surcharge') === false && $fee->amount < 0) {
                $total += $fee->amount; // Add negative fees (discounts)
            }
        }

        return max(0, $total);
    }

    public function removeSurcharge(): void {
        $cart = WC()->cart;
        $fees = $cart->get_fees();

        foreach ($fees as $fee_key => $fee) {
            if (strpos($fee->name, 'Surcharge') !== false) {
                unset($cart->fees[$fee_key]);
                $this->logger->info("Removed existing surcharge fee");
            }
        }
    }
}
```

#### 2.2.4 Hook Integration
```php
<?php
namespace APW\WooPlugin\Integrations\WooCommerce;

class PaymentHookManager {
    private PaymentGatewayServiceInterface $payment_service;

    public function __construct(PaymentGatewayServiceInterface $payment_service) {
        $this->payment_service = $payment_service;
    }

    public function register(): void {
        add_action('woocommerce_cart_calculate_fees', [$this, 'handle_payment_method_fees'], 10);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_payment_data'], 10, 2);
    }

    public function handle_payment_method_fees(): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $chosen_payment_method = WC()->session->get('chosen_payment_method');
        
        if ($chosen_payment_method) {
            $this->payment_service->applySurcharge($chosen_payment_method);
        }
    }
}
```

### 2.3 Pricing Service

#### 2.3.1 Current Issues
- **File**: `includes/apw-woo-dynamic-pricing-functions.php`
- **Problems**:
  - Complex pricing rules retrieval with multiple fallbacks
  - Duplicate code for product validation across functions
  - VIP discount calculation scattered across different files
  - Performance issues with repeated product lookups

#### 2.3.2 Service Interface
```php
<?php
namespace APW\WooPlugin\Services\Pricing;

interface PricingServiceInterface {
    public function getProductPricingRules(int $product_id): array;
    public function calculateDynamicPrice(int $product_id, int $quantity): float;
    public function applyVIPDiscount(float $subtotal): float;
    public function getDiscountThresholds(int $product_id): array;
    public function isVIPCustomer(int $customer_id): bool;
}
```

#### 2.3.3 Implementation
```php
<?php
namespace APW\WooPlugin\Services\Pricing;

use APW\WooPlugin\Models\Product;
use APW\WooPlugin\Models\Customer;

class PricingService implements PricingServiceInterface {
    private Product $product_model;
    private Customer $customer_model;
    private array $pricing_cache = [];

    public function __construct(Product $product_model, Customer $customer_model) {
        $this->product_model = $product_model;
        $this->customer_model = $customer_model;
    }

    public function getProductPricingRules(int $product_id): array {
        // Use cache to avoid repeated lookups
        if (isset($this->pricing_cache[$product_id])) {
            return $this->pricing_cache[$product_id];
        }

        $rules = [];

        // Strategy pattern for different rule sources
        $rule_sources = [
            new ProductSpecificRuleSource(),
            new CategoryRuleSource(),
            new GlobalRuleSource()
        ];

        foreach ($rule_sources as $source) {
            $source_rules = $source->getRules($product_id);
            if (!empty($source_rules)) {
                $rules = array_merge($rules, $source_rules);
            }
        }

        $this->pricing_cache[$product_id] = $rules;
        return $rules;
    }

    public function calculateDynamicPrice(int $product_id, int $quantity): float {
        $product = $this->product_model->find($product_id);
        if (!$product) {
            return 0.0;
        }

        $base_price = (float) $product->get_regular_price();
        $rules = $this->getProductPricingRules($product_id);

        return $this->applyPricingRules($base_price, $quantity, $rules);
    }

    public function applyVIPDiscount(float $subtotal): float {
        $customer_id = get_current_user_id();
        
        if (!$this->isVIPCustomer($customer_id)) {
            return 0.0;
        }

        $discount_config = $this->getVIPDiscountConfiguration();
        
        foreach ($discount_config['thresholds'] as $threshold) {
            if ($subtotal >= $threshold['minimum']) {
                return $subtotal * $threshold['discount_rate'];
            }
        }

        return 0.0;
    }

    private function applyPricingRules(float $base_price, int $quantity, array $rules): float {
        $final_price = $base_price;

        foreach ($rules as $rule) {
            if ($this->ruleApplies($rule, $quantity)) {
                $final_price = $this->calculateRulePrice($final_price, $rule, $quantity);
            }
        }

        return $final_price;
    }

    private function getVIPDiscountConfiguration(): array {
        return [
            'thresholds' => [
                ['minimum' => 500, 'discount_rate' => 0.10],
                ['minimum' => 300, 'discount_rate' => 0.08],
                ['minimum' => 100, 'discount_rate' => 0.05]
            ]
        ];
    }
}
```

### 2.4 Cart Service

#### 2.4.1 Current Issues
- **File**: `includes/apw-woo-cart-indicator-functions.php`
- **Problems**:
  - Cart quantity indicators not updating properly
  - Multiple AJAX endpoints for similar functionality
  - No centralized cart state management

#### 2.4.2 Service Interface
```php
<?php
namespace APW\WooPlugin\Services\Cart;

interface CartServiceInterface {
    public function getCartQuantity(): int;
    public function getCartTotal(): float;
    public function addToCart(int $product_id, int $quantity, array $variation = []): bool;
    public function removeFromCart(string $cart_item_key): bool;
    public function updateCartItemQuantity(string $cart_item_key, int $quantity): bool;
    public function clearCart(): void;
    public function getCartItems(): array;
}
```

#### 2.4.3 Implementation
```php
<?php
namespace APW\WooPlugin\Services\Cart;

use APW\WooPlugin\Services\LoggerServiceInterface;

class CartService implements CartServiceInterface {
    private LoggerServiceInterface $logger;

    public function __construct(LoggerServiceInterface $logger) {
        $this->logger = $logger;
    }

    public function getCartQuantity(): int {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }

        $total_quantity = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $total_quantity += $cart_item['quantity'];
        }

        return $total_quantity;
    }

    public function getCartTotal(): float {
        if (!function_exists('WC') || !WC()->cart) {
            return 0.0;
        }

        return (float) WC()->cart->get_total('edit');
    }

    public function addToCart(int $product_id, int $quantity, array $variation = []): bool {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, $variation);
        
        if ($cart_item_key) {
            $this->logger->info("Added product {$product_id} to cart, quantity: {$quantity}");
            return true;
        }

        $this->logger->warning("Failed to add product {$product_id} to cart");
        return false;
    }

    public function getCartItems(): array {
        if (!function_exists('WC') || !WC()->cart) {
            return [];
        }

        $items = [];
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            $items[] = [
                'key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'] ?? 0,
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'name' => $product->get_name(),
                'image' => wp_get_attachment_image_src($product->get_image_id(), 'thumbnail')[0] ?? ''
            ];
        }

        return $items;
    }
}
```

### 2.5 Customer Service

#### 2.5.1 Current Issues
- **Files**: 
  - `includes/class-apw-woo-registration-fields.php`
  - `includes/class-apw-woo-referral-export.php`
- **Problems**:
  - Registration field validation scattered across multiple methods
  - Referral export logic mixed with presentation
  - No centralized customer data management

#### 2.5.2 Service Interface
```php
<?php
namespace APW\WooPlugin\Services\Customer;

interface CustomerServiceInterface {
    public function validateRegistrationData(array $data): array;
    public function createCustomer(array $data): int;
    public function updateCustomerMeta(int $customer_id, array $meta): bool;
    public function getReferralCustomers(string $referrer_name = ''): array;
    public function getTotalReferrals(): int;
    public function exportCustomers(array $criteria): string;
}
```

#### 2.5.3 Implementation
```php
<?php
namespace APW\WooPlugin\Services\Customer;

use APW\WooPlugin\Models\Customer;
use APW\WooPlugin\Services\Export\ExportServiceInterface;

class CustomerService implements CustomerServiceInterface {
    private Customer $customer_model;
    private ExportServiceInterface $export_service;

    public function __construct(
        Customer $customer_model,
        ExportServiceInterface $export_service
    ) {
        $this->customer_model = $customer_model;
        $this->export_service = $export_service;
    }

    public function validateRegistrationData(array $data): array {
        $errors = [];
        $required_fields = ['first_name', 'last_name', 'company_name', 'phone_number'];

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = "The {$field} field is required.";
            }
        }

        // Validate email format
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Validate phone number format
        if (!empty($data['phone_number']) && !$this->isValidPhoneNumber($data['phone_number'])) {
            $errors['phone_number'] = 'Please enter a valid phone number.';
        }

        return $errors;
    }

    public function createCustomer(array $data): int {
        $validation_errors = $this->validateRegistrationData($data);
        
        if (!empty($validation_errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validation_errors));
        }

        $customer_id = wp_create_user(
            $data['username'],
            $data['password'],
            $data['email']
        );

        if (is_wp_error($customer_id)) {
            throw new \Exception('Failed to create customer: ' . $customer_id->get_error_message());
        }

        // Update customer meta data
        $meta_data = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_name' => $data['company_name'],
            'phone_number' => $data['phone_number'],
            'referred_by' => $data['referred_by'] ?? ''
        ];

        $this->updateCustomerMeta($customer_id, $meta_data);

        return $customer_id;
    }

    public function getReferralCustomers(string $referrer_name = ''): array {
        return $this->customer_model->getReferralCustomers($referrer_name);
    }

    public function exportCustomers(array $criteria): string {
        $customers = $this->customer_model->getCustomersByCriteria($criteria);
        
        return $this->export_service->exportToCSV($customers, [
            'ID' => 'User ID',
            'user_login' => 'Username',
            'user_email' => 'Email',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'company_name' => 'Company',
            'phone_number' => 'Phone',
            'referred_by' => 'Referred By',
            'user_registered' => 'Registration Date'
        ]);
    }

    private function isValidPhoneNumber(string $phone): bool {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Check if it's a valid length (10-15 digits)
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
}
```

### 2.6 Template Service

#### 2.6.1 Current Issues
- **Files**: Multiple template-related functions in main plugin file
- **Problems**:
  - Template loading mixed with business logic
  - FAQ system scattered across multiple files
  - No template caching or optimization

#### 2.6.2 Service Interface
```php
<?php
namespace APW\WooPlugin\Services\Template;

interface TemplateServiceInterface {
    public function render(string $template, array $data = []): string;
    public function renderFAQ(array $faqs, array $options = []): string;
    public function templateExists(string $template): bool;
    public function getTemplateDirectories(): array;
}
```

#### 2.6.3 Implementation
```php
<?php
namespace APW\WooPlugin\Services\Template;

use APW\WooPlugin\Core\ConfigInterface;

class TemplateService implements TemplateServiceInterface {
    private ConfigInterface $config;
    private array $template_cache = [];

    public function __construct(ConfigInterface $config) {
        $this->config = $config;
    }

    public function render(string $template, array $data = []): string {
        $template_file = $this->findTemplate($template);
        
        if (!$template_file) {
            throw new \Exception("Template not found: {$template}");
        }

        // Use output buffering to capture template output
        ob_start();
        
        // Extract data to variables for template use
        extract($data, EXTR_SKIP);
        
        include $template_file;
        
        return ob_get_clean();
    }

    public function renderFAQ(array $faqs, array $options = []): string {
        if (empty($faqs)) {
            return '';
        }

        $defaults = [
            'show_title' => true,
            'title' => __('Frequently Asked Questions', 'apw-woo-plugin'),
            'collapsible' => true,
            'container_class' => 'apw-faq-container'
        ];

        $options = array_merge($defaults, $options);

        return $this->render('partials/faq-display', [
            'faqs' => $faqs,
            'options' => $options
        ]);
    }

    public function templateExists(string $template): bool {
        return $this->findTemplate($template) !== null;
    }

    public function getTemplateDirectories(): array {
        return [
            get_stylesheet_directory() . '/apw-woo-plugin/',
            get_template_directory() . '/apw-woo-plugin/',
            $this->config->get('plugin_dir') . 'templates/'
        ];
    }

    private function findTemplate(string $template): ?string {
        // Add .php extension if not present
        if (substr($template, -4) !== '.php') {
            $template .= '.php';
        }

        foreach ($this->getTemplateDirectories() as $directory) {
            $file = $directory . $template;
            
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }
}
```

## Integration and Testing

### 2.7 Service Integration
```php
<?php
namespace APW\WooPlugin\Core;

class ServiceBootstrapper {
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    public function bootstrap(): void {
        $this->registerCoreServices();
        $this->registerBusinessServices();
        $this->registerIntegrationServices();
    }

    private function registerCoreServices(): void {
        $this->container->singleton(
            'APW\WooPlugin\Services\LoggerServiceInterface',
            'APW\WooPlugin\Services\LoggerService'
        );

        $this->container->singleton(
            'APW\WooPlugin\Services\Template\TemplateServiceInterface',
            'APW\WooPlugin\Services\Template\TemplateService'
        );
    }

    private function registerBusinessServices(): void {
        $this->container->singleton(
            'APW\WooPlugin\Services\Payment\PaymentGatewayServiceInterface',
            'APW\WooPlugin\Services\Payment\PaymentGatewayService'
        );

        $this->container->singleton(
            'APW\WooPlugin\Services\Pricing\PricingServiceInterface',
            'APW\WooPlugin\Services\Pricing\PricingService'
        );

        $this->container->singleton(
            'APW\WooPlugin\Services\Cart\CartServiceInterface',
            'APW\WooPlugin\Services\Cart\CartService'
        );

        $this->container->singleton(
            'APW\WooPlugin\Services\Customer\CustomerServiceInterface',
            'APW\WooPlugin\Services\Customer\CustomerService'
        );
    }
}
```

### 2.8 Testing Framework
```php
<?php
namespace APW\WooPlugin\Tests\Services;

use PHPUnit\Framework\TestCase;
use APW\WooPlugin\Services\Payment\PaymentGatewayService;

class PaymentGatewayServiceTest extends TestCase {
    private PaymentGatewayService $service;

    protected function setUp(): void {
        $config = $this->createMock(ConfigInterface::class);
        $logger = $this->createMock(LoggerServiceInterface::class);
        
        $this->service = new PaymentGatewayService($config, $logger);
    }

    public function testCalculateSurcharge(): void {
        $amount = 100.00;
        $gateway_id = 'intuit_qbms_credit_card';
        
        $surcharge = $this->service->calculateSurcharge($amount, $gateway_id);
        
        $this->assertEquals(3.00, $surcharge);
    }

    public function testUnsupportedGateway(): void {
        $amount = 100.00;
        $gateway_id = 'unsupported_gateway';
        
        $surcharge = $this->service->calculateSurcharge($amount, $gateway_id);
        
        $this->assertEquals(0.0, $surcharge);
    }
}
```

## Success Criteria

### 2.9 Implementation Checklist
- [ ] Payment Gateway Service refactored and tested
- [ ] Pricing Service implemented with caching
- [ ] Cart Service with proper state management
- [ ] Customer Service with validation framework
- [ ] Template Service with flexible rendering
- [ ] All services follow interface contracts
- [ ] Dependency injection working correctly
- [ ] Unit tests passing for all services
- [ ] Integration tests for service interactions
- [ ] Performance benchmarks meet requirements

### 2.10 Quality Gates
- **Test Coverage**: >85% for all services
- **Performance**: Services 10x faster than current functions
- **Memory Usage**: Reduced memory footprint through caching
- **Maintainability**: Clear service boundaries and responsibilities

## Next Steps
Upon completion:
1. Proceed to Phase 2: Integration Layer Cleanup
2. Begin migration of existing hooks to service-based architecture
3. Implement comprehensive logging and monitoring
4. Update documentation for new service architecture