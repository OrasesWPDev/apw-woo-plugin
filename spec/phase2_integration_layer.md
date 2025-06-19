# Phase 2: Integration Layer Implementation Specification

## Overview
Centralize and streamline all third-party plugin integrations into a clean, maintainable architecture that reduces coupling and improves extensibility.

## Integration Architecture Principles

### 2.1 Design Goals
- **Single Point of Integration**: One class per external system
- **Adapter Pattern**: Consistent interface regardless of external API changes
- **Loose Coupling**: Minimal dependencies between integrations
- **Event-Driven**: Use WordPress hooks for communication
- **Graceful Degradation**: Function when plugins are disabled

## Integration Layer Structure

### 2.2 Directory Organization
```
src/APW/WooPlugin/Integrations/
├── WooCommerce/
│   ├── HookManager.php
│   ├── ProductAdapter.php
│   ├── CartAdapter.php
│   ├── OrderAdapter.php
│   └── CheckoutAdapter.php
├── ACF/
│   ├── FieldManager.php
│   ├── FieldGroupBuilder.php
│   └── DataMapper.php
├── ThirdParty/
│   ├── DynamicPricingAdapter.php
│   ├── ProductAddonsAdapter.php
│   └── IntuitAdapter.php
└── Base/
    ├── IntegrationInterface.php
    ├── AbstractIntegration.php
    └── AdapterInterface.php
```

## WooCommerce Integration Centralization

### 2.3 Hook Manager Implementation

#### 2.3.1 Current Issues
- **Scattered Hook Registration**: Hooks registered across 10+ files
- **Duplicate Priorities**: Conflicting hook priorities causing issues
- **No Central Management**: Difficult to track and debug hook interactions
- **Complex Dependencies**: Hooks depending on other hooks firing first

#### 2.3.2 Hook Manager Interface
```php
<?php
namespace APW\WooPlugin\Integrations\WooCommerce;

interface HookManagerInterface {
    public function registerHooks(): void;
    public function deregisterHooks(): void;
    public function getRegisteredHooks(): array;
    public function hasHook(string $hook, callable $callback): bool;
}
```

#### 2.3.3 Centralized Hook Manager
```php
<?php
namespace APW\WooPlugin\Integrations\WooCommerce;

use APW\WooPlugin\Core\ServiceContainer;

class HookManager implements HookManagerInterface {
    private ServiceContainer $container;
    private array $registered_hooks = [];
    private array $hook_priorities = [
        // Cart calculation order is critical
        'woocommerce_before_calculate_totals' => [
            'dynamic_pricing' => 5,
            'vip_discounts' => 8,
        ],
        'woocommerce_cart_calculate_fees' => [
            'payment_surcharges' => 15,
            'additional_fees' => 20,
        ],
        // Checkout field priorities
        'woocommerce_checkout_fields' => [
            'required_fields' => 10,
            'additional_fields' => 15,
        ],
        // Registration hooks
        'woocommerce_register_form' => [
            'custom_fields' => 10,
        ],
        'woocommerce_registration_errors' => [
            'field_validation' => 10,
        ]
    ];

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    public function registerHooks(): void {
        // Cart and pricing hooks
        $this->registerCartHooks();
        
        // Checkout and payment hooks
        $this->registerCheckoutHooks();
        
        // Product and catalog hooks
        $this->registerProductHooks();
        
        // Customer and account hooks
        $this->registerCustomerHooks();
        
        // Admin hooks
        $this->registerAdminHooks();
    }

    private function registerCartHooks(): void {
        // Dynamic pricing - must run early
        add_action(
            'woocommerce_before_calculate_totals',
            [$this, 'handle_dynamic_pricing'],
            $this->getPriority('woocommerce_before_calculate_totals', 'dynamic_pricing')
        );

        // VIP discounts - after pricing, before fees
        add_action(
            'woocommerce_before_calculate_totals',
            [$this, 'handle_vip_discounts'],
            $this->getPriority('woocommerce_before_calculate_totals', 'vip_discounts')
        );

        // Payment surcharges - after all discounts
        add_action(
            'woocommerce_cart_calculate_fees',
            [$this, 'handle_payment_surcharges'],
            $this->getPriority('woocommerce_cart_calculate_fees', 'payment_surcharges')
        );

        // Cart quantity indicators
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'add_cart_quantity_fragment']);

        $this->logHookRegistration('Cart hooks registered');
    }

    private function registerCheckoutHooks(): void {
        // Custom checkout fields
        add_filter(
            'woocommerce_checkout_fields',
            [$this, 'modify_checkout_fields'],
            $this->getPriority('woocommerce_checkout_fields', 'required_fields')
        );

        // Additional checkout fields
        add_filter(
            'woocommerce_checkout_fields',
            [$this, 'add_additional_checkout_fields'],
            $this->getPriority('woocommerce_checkout_fields', 'additional_fields')
        );

        // Checkout validation
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_fields']);

        // Save custom data
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_checkout_data'], 10, 2);

        $this->logHookRegistration('Checkout hooks registered');
    }

    private function registerCustomerHooks(): void {
        // Registration form fields
        add_action(
            'woocommerce_register_form',
            [$this, 'add_registration_fields'],
            $this->getPriority('woocommerce_register_form', 'custom_fields')
        );

        // Registration validation
        add_filter(
            'woocommerce_registration_errors',
            [$this, 'validate_registration_fields'],
            $this->getPriority('woocommerce_registration_errors', 'field_validation'),
            2
        );

        // Save registration data
        add_action('woocommerce_created_customer', [$this, 'save_registration_data']);

        $this->logHookRegistration('Customer hooks registered');
    }

    // Hook Handlers (delegate to services)
    public function handle_dynamic_pricing($cart): void {
        $pricing_service = $this->container->make('APW\WooPlugin\Services\Pricing\PricingServiceInterface');
        $pricing_service->applyDynamicPricing($cart);
    }

    public function handle_vip_discounts($cart): void {
        $pricing_service = $this->container->make('APW\WooPlugin\Services\Pricing\PricingServiceInterface');
        $pricing_service->applyVIPDiscounts($cart);
    }

    public function handle_payment_surcharges(): void {
        $payment_service = $this->container->make('APW\WooPlugin\Services\Payment\PaymentGatewayServiceInterface');
        $chosen_method = WC()->session->get('chosen_payment_method');
        
        if ($chosen_method) {
            $payment_service->applySurcharge($chosen_method);
        }
    }

    public function modify_checkout_fields($fields): array {
        $checkout_adapter = $this->container->make('APW\WooPlugin\Integrations\WooCommerce\CheckoutAdapter');
        return $checkout_adapter->modifyFields($fields);
    }

    private function getPriority(string $hook, string $handler): int {
        return $this->hook_priorities[$hook][$handler] ?? 10;
    }

    private function logHookRegistration(string $message): void {
        if ($this->container->make('APW\WooPlugin\Core\ConfigInterface')->get('debug_mode')) {
            $logger = $this->container->make('APW\WooPlugin\Services\LoggerServiceInterface');
            $logger->info($message);
        }
    }
}
```

### 2.4 WooCommerce Adapters

#### 2.4.1 Product Adapter
```php
<?php
namespace APW\WooPlugin\Integrations\WooCommerce;

class ProductAdapter {
    public function getProductPricingData(int $product_id): array {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return [];
        }

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'regular_price' => (float) $product->get_regular_price(),
            'sale_price' => (float) $product->get_sale_price(),
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
            'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
            'tags' => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names'])
        ];
    }

    public function getProductAddons(int $product_id): array {
        if (!class_exists('WC_Product_Addons_Helper')) {
            return [];
        }

        return \WC_Product_Addons_Helper::get_product_addons($product_id) ?: [];
    }

    public function hasProductAddons(int $product_id): bool {
        return !empty($this->getProductAddons($product_id));
    }
}
```

#### 2.4.2 Cart Adapter
```php
<?php
namespace APW\WooPlugin\Integrations\WooCommerce;

class CartAdapter {
    public function getCartTotalForCalculation(): float {
        $cart = WC()->cart;
        
        if (!$cart) {
            return 0.0;
        }

        // Start with subtotal + shipping
        $total = $cart->get_subtotal() + $cart->get_shipping_total();

        // Apply any discount fees (negative amounts)
        foreach ($cart->get_fees() as $fee) {
            if ($fee->amount < 0) {
                $total += $fee->amount;
            }
        }

        return max(0, $total);
    }

    public function addCartFragment(string $selector, callable $content_generator): void {
        add_filter('woocommerce_add_to_cart_fragments', function($fragments) use ($selector, $content_generator) {
            $fragments[$selector] = $content_generator();
            return $fragments;
        });
    }

    public function removeFeesByName(string $fee_name_pattern): void {
        $cart = WC()->cart;
        
        if (!$cart) {
            return;
        }

        foreach ($cart->get_fees() as $fee_key => $fee) {
            if (strpos($fee->name, $fee_name_pattern) !== false) {
                unset($cart->fees[$fee_key]);
            }
        }
    }
}
```

#### 2.4.3 Checkout Adapter
```php
<?php
namespace APW\WooPlugin\Integrations\WooCommerce;

class CheckoutAdapter {
    private array $custom_fields = [
        'billing_company' => [
            'required' => true,
            'priority' => 30
        ],
        'billing_phone' => [
            'required' => true,
            'priority' => 100
        ],
        'apw_additional_emails' => [
            'label' => 'Additional Email Recipients',
            'placeholder' => 'email1@example.com, email2@example.com',
            'required' => false,
            'type' => 'text',
            'class' => ['form-row-wide'],
            'priority' => 110
        ]
    ];

    public function modifyFields(array $fields): array {
        foreach ($this->custom_fields as $field_key => $field_config) {
            if (isset($fields['billing'][$field_key])) {
                // Modify existing field
                $fields['billing'][$field_key] = array_merge(
                    $fields['billing'][$field_key],
                    $field_config
                );
            } else {
                // Add new field
                $fields['billing'][$field_key] = $field_config;
            }
        }

        return $fields;
    }

    public function validateCustomFields(): array {
        $errors = [];

        foreach ($this->custom_fields as $field_key => $config) {
            if ($config['required'] ?? false) {
                $value = sanitize_text_field($_POST[$field_key] ?? '');
                
                if (empty($value)) {
                    $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $field_key));
                    $errors[$field_key] = sprintf('%s is required.', $label);
                }
            }
        }

        return $errors;
    }
}
```

## ACF Integration Centralization

### 2.5 ACF Field Manager

#### 2.5.1 Current Issues
- **Scattered Field Definitions**: ACF fields defined across multiple files
- **No Central Schema**: Difficult to understand all custom fields
- **Manual Field Management**: Field groups created manually

#### 2.5.2 Field Manager Implementation
```php
<?php
namespace APW\WooPlugin\Integrations\ACF;

class FieldManager {
    private array $field_groups = [];

    public function __construct() {
        $this->defineFieldGroups();
    }

    public function register(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        foreach ($this->field_groups as $group) {
            acf_add_local_field_group($group);
        }
    }

    private function defineFieldGroups(): void {
        $this->field_groups = [
            // FAQ Field Group
            [
                'key' => 'group_apw_faq',
                'title' => 'APW FAQ Settings',
                'fields' => [
                    [
                        'key' => 'field_apw_faqs',
                        'label' => 'FAQs',
                        'name' => 'faqs',
                        'type' => 'repeater',
                        'sub_fields' => [
                            [
                                'key' => 'field_apw_faq_question',
                                'label' => 'Question',
                                'name' => 'question',
                                'type' => 'text',
                                'required' => 1,
                            ],
                            [
                                'key' => 'field_apw_faq_answer',
                                'label' => 'Answer',
                                'name' => 'answer',
                                'type' => 'wysiwyg',
                                'required' => 1,
                                'toolbar' => 'basic',
                            ],
                        ],
                        'min' => 0,
                        'layout' => 'block',
                        'button_label' => 'Add FAQ',
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'product',
                        ],
                    ],
                    [
                        [
                            'param' => 'taxonomy',
                            'operator' => '==',
                            'value' => 'product_cat',
                        ],
                    ],
                    [
                        [
                            'param' => 'page_template',
                            'operator' => '==',
                            'value' => 'page-shop.php',
                        ],
                    ],
                ],
            ],
            // Customer VIP Settings
            [
                'key' => 'group_apw_customer_vip',
                'title' => 'APW Customer VIP Settings',
                'fields' => [
                    [
                        'key' => 'field_apw_is_vip',
                        'label' => 'VIP Customer',
                        'name' => 'is_vip_customer',
                        'type' => 'true_false',
                        'default_value' => 0,
                    ],
                    [
                        'key' => 'field_apw_vip_discount_rate',
                        'label' => 'Custom VIP Discount Rate',
                        'name' => 'vip_discount_rate',
                        'type' => 'number',
                        'min' => 0,
                        'max' => 100,
                        'step' => 0.01,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_apw_is_vip',
                                    'operator' => '==',
                                    'value' => '1',
                                ],
                            ],
                        ],
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'user_form',
                            'operator' => '==',
                            'value' => 'edit',
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

#### 2.5.3 Data Mapper
```php
<?php
namespace APW\WooPlugin\Integrations\ACF;

class DataMapper {
    public function getFAQs($object_id, string $object_type = 'post'): array {
        if (!function_exists('get_field')) {
            return [];
        }

        $faqs = get_field('faqs', $object_id);
        
        if (!is_array($faqs)) {
            return [];
        }

        return array_map(function($faq) {
            return [
                'question' => wp_kses_post($faq['question'] ?? ''),
                'answer' => wp_kses_post($faq['answer'] ?? '')
            ];
        }, $faqs);
    }

    public function isVIPCustomer(int $user_id): bool {
        if (!function_exists('get_field')) {
            return false;
        }

        return (bool) get_field('is_vip_customer', "user_{$user_id}");
    }

    public function getVIPDiscountRate(int $user_id): float {
        if (!function_exists('get_field')) {
            return 0.0;
        }

        $rate = get_field('vip_discount_rate', "user_{$user_id}");
        
        return is_numeric($rate) ? (float) $rate : 0.0;
    }
}
```

## Third-Party Plugin Adapters

### 2.6 Dynamic Pricing Adapter

#### 2.6.1 Current Issues
- **File**: `includes/apw-woo-dynamic-pricing-functions.php`
- **Problems**: Direct plugin API calls scattered throughout code

#### 2.6.2 Adapter Implementation
```php
<?php
namespace APW\WooPlugin\Integrations\ThirdParty;

use APW\WooPlugin\Integrations\Base\AbstractIntegration;

class DynamicPricingAdapter extends AbstractIntegration {
    protected string $plugin_class = 'WC_Dynamic_Pricing';
    protected string $plugin_name = 'WooCommerce Dynamic Pricing';

    public function isActive(): bool {
        return class_exists($this->plugin_class);
    }

    public function getProductRules(int $product_id): array {
        if (!$this->isActive()) {
            return [];
        }

        // Get product-specific rules
        $product_rules = get_post_meta($product_id, '_pricing_rules', true);
        
        if (!empty($product_rules) && is_array($product_rules)) {
            return $this->formatRules($product_rules);
        }

        // Get category rules
        $category_rules = $this->getCategoryRules($product_id);
        
        if (!empty($category_rules)) {
            return $this->formatRules($category_rules);
        }

        // Get global rules
        return $this->getGlobalRules();
    }

    public function calculateDiscountedPrice(int $product_id, int $quantity): float {
        if (!$this->isActive()) {
            return 0.0;
        }

        $product = wc_get_product($product_id);
        
        if (!$product) {
            return 0.0;
        }

        $base_price = (float) $product->get_regular_price();
        $rules = $this->getProductRules($product_id);

        return $this->applyBulkDiscountRules($base_price, $quantity, $rules);
    }

    private function formatRules(array $raw_rules): array {
        $formatted = [];

        foreach ($raw_rules as $rule_set) {
            if (!isset($rule_set['rules']) || !is_array($rule_set['rules'])) {
                continue;
            }

            foreach ($rule_set['rules'] as $rule) {
                $formatted[] = [
                    'type' => $rule_set['mode'] ?? 'bulk',
                    'min_quantity' => (int) ($rule['from'] ?? 1),
                    'max_quantity' => (int) ($rule['to'] ?? 999999),
                    'discount_type' => $rule['type'] ?? 'percentage',
                    'discount_amount' => (float) ($rule['amount'] ?? 0),
                ];
            }
        }

        return $formatted;
    }

    private function applyBulkDiscountRules(float $base_price, int $quantity, array $rules): float {
        $applicable_rule = null;

        foreach ($rules as $rule) {
            if ($quantity >= $rule['min_quantity'] && $quantity <= $rule['max_quantity']) {
                $applicable_rule = $rule;
                break;
            }
        }

        if (!$applicable_rule) {
            return $base_price;
        }

        switch ($applicable_rule['discount_type']) {
            case 'percentage':
                return $base_price * (1 - $applicable_rule['discount_amount'] / 100);
            case 'fixed':
                return max(0, $base_price - $applicable_rule['discount_amount']);
            default:
                return $base_price;
        }
    }
}
```

### 2.7 Product Add-ons Adapter
```php
<?php
namespace APW\WooPlugin\Integrations\ThirdParty;

use APW\WooPlugin\Integrations\Base\AbstractIntegration;

class ProductAddonsAdapter extends AbstractIntegration {
    protected string $plugin_class = 'WC_Product_Addons';
    protected string $plugin_name = 'WooCommerce Product Add-ons';

    public function getProductAddons(int $product_id): array {
        if (!$this->isActive() || !function_exists('get_product_addons')) {
            return [];
        }

        $addons = get_product_addons($product_id);
        
        return is_array($addons) ? $this->formatAddons($addons) : [];
    }

    public function calculateAddonPrices(int $product_id, array $addon_data): float {
        if (!$this->isActive()) {
            return 0.0;
        }

        $addons = $this->getProductAddons($product_id);
        $total_addon_price = 0.0;

        foreach ($addon_data as $addon_id => $selection) {
            $addon = $this->findAddonById($addons, $addon_id);
            
            if ($addon) {
                $total_addon_price += $this->calculateSingleAddonPrice($addon, $selection);
            }
        }

        return $total_addon_price;
    }

    private function formatAddons(array $raw_addons): array {
        return array_map(function($addon) {
            return [
                'id' => $addon['id'] ?? '',
                'name' => $addon['name'] ?? '',
                'type' => $addon['type'] ?? 'text',
                'required' => (bool) ($addon['required'] ?? false),
                'options' => $this->formatAddonOptions($addon['options'] ?? []),
                'price_type' => $addon['price_type'] ?? 'flat_fee'
            ];
        }, $raw_addons);
    }

    private function formatAddonOptions(array $options): array {
        return array_map(function($option) {
            return [
                'label' => $option['label'] ?? '',
                'price' => (float) ($option['price'] ?? 0),
                'image' => $option['image'] ?? ''
            ];
        }, $options);
    }
}
```

## Integration Bootstrap

### 2.8 Integration Manager
```php
<?php
namespace APW\WooPlugin\Core;

class IntegrationManager {
    private ServiceContainer $container;
    private array $integrations = [];

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    public function bootstrap(): void {
        $this->registerIntegrations();
        $this->initializeIntegrations();
    }

    private function registerIntegrations(): void {
        $this->integrations = [
            'woocommerce' => 'APW\WooPlugin\Integrations\WooCommerce\HookManager',
            'acf' => 'APW\WooPlugin\Integrations\ACF\FieldManager',
            'dynamic_pricing' => 'APW\WooPlugin\Integrations\ThirdParty\DynamicPricingAdapter',
            'product_addons' => 'APW\WooPlugin\Integrations\ThirdParty\ProductAddonsAdapter',
        ];
    }

    private function initializeIntegrations(): void {
        foreach ($this->integrations as $name => $class) {
            try {
                $integration = $this->container->make($class);
                
                if (method_exists($integration, 'register')) {
                    $integration->register();
                }
                
                if (method_exists($integration, 'isActive') && !$integration->isActive()) {
                    $this->logMissingIntegration($name);
                }
            } catch (\Exception $e) {
                $this->logIntegrationError($name, $e);
            }
        }
    }

    private function logMissingIntegration(string $name): void {
        $logger = $this->container->make('APW\WooPlugin\Services\LoggerServiceInterface');
        $logger->warning("Integration '{$name}' plugin not active - functionality disabled");
    }
}
```

## Success Criteria

### 2.9 Implementation Checklist
- [ ] WooCommerce hook management centralized
- [ ] ACF field definitions consolidated
- [ ] Third-party plugin adapters implemented
- [ ] Integration manager functional
- [ ] All hooks properly prioritized
- [ ] Graceful degradation when plugins missing
- [ ] Clear adapter interfaces
- [ ] Comprehensive integration testing

### 2.10 Quality Gates
- **Hook Conflicts**: Zero priority conflicts
- **Plugin Dependencies**: Graceful handling of missing plugins
- **Performance**: No performance degradation from centralization
- **Maintainability**: Clear integration boundaries

## Next Steps
Upon completion:
1. Proceed to Phase 3: Code Cleanup
2. Begin removal of scattered hook registrations
3. Implement integration testing suite
4. Update documentation for new integration patterns