# Phase 4: Testing Framework Implementation Specification

## Overview
Establish a comprehensive testing framework with unit tests, integration tests, and end-to-end tests to ensure code quality, functionality, and regression prevention throughout the APW WooCommerce Plugin refactoring.

## Testing Objectives

### 4.1 Testing Goals
- **Code Coverage**: Achieve >80% test coverage across all components
- **Regression Prevention**: Catch breaking changes before deployment
- **Quality Assurance**: Ensure all functionality works as expected
- **Continuous Integration**: Automated testing on every commit
- **Performance Validation**: Monitor performance metrics during testing

## Testing Architecture

### 4.2 Testing Stack

#### 4.2.1 Core Testing Tools
```json
{
  "testing_framework": {
    "unit_tests": "PHPUnit 9.x",
    "integration_tests": "WordPress Test Suite",
    "e2e_tests": "Playwright/Codeception",
    "api_tests": "REST API Test Suite",
    "performance_tests": "Apache Bench + Custom Metrics"
  },
  "mock_framework": "Mockery",
  "fixtures": "Custom WordPress Fixtures",
  "database": "SQLite for fast testing",
  "ci_cd": "GitHub Actions"
}
```

#### 4.2.2 Test Directory Structure
```
tests/
├── Unit/                          # Unit tests
│   ├── Services/
│   │   ├── PaymentGatewayServiceTest.php
│   │   ├── PricingServiceTest.php
│   │   ├── CartServiceTest.php
│   │   └── CustomerServiceTest.php
│   ├── Common/
│   │   ├── ValidatorsTest.php
│   │   └── UtilitiesTest.php
│   └── Core/
│       ├── ServiceContainerTest.php
│       └── ConfigurationTest.php
├── Integration/                    # Integration tests
│   ├── WooCommerce/
│   │   ├── HookManagerTest.php
│   │   ├── CheckoutIntegrationTest.php
│   │   └── CartIntegrationTest.php
│   ├── ACF/
│   │   └── FieldManagerTest.php
│   └── Database/
│       └── QueryOptimizationTest.php
├── EndToEnd/                      # E2E tests
│   ├── Checkout/
│   │   ├── PaymentProcessingTest.php
│   │   └── SurchargeCalculationTest.php
│   ├── Admin/
│   │   ├── ReferralExportTest.php
│   │   └── CustomerManagementTest.php
│   └── Frontend/
│       └── CartFunctionalityTest.php
├── Performance/                   # Performance tests
│   ├── LoadTestSuite.php
│   ├── DatabasePerformanceTest.php
│   └── CacheEfficiencyTest.php
├── fixtures/                     # Test data
│   ├── products.json
│   ├── customers.json
│   └── orders.json
└── bootstrap.php                 # Test bootstrap
```

## Unit Testing Implementation

### 4.3 Core Business Logic Testing

#### 4.3.1 Payment Gateway Service Tests
```php
<?php
namespace APW\WooPlugin\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use APW\WooPlugin\Services\Payment\PaymentGatewayService;
use APW\WooPlugin\Core\ConfigInterface;
use APW\WooPlugin\Services\LoggerServiceInterface;

class PaymentGatewayServiceTest extends TestCase {
    private PaymentGatewayService $service;
    private m\MockInterface $config;
    private m\MockInterface $logger;

    protected function setUp(): void {
        $this->config = m::mock(ConfigInterface::class);
        $this->logger = m::mock(LoggerServiceInterface::class);
        
        $this->config->shouldReceive('get')
            ->with('surcharge_rate', 0.03)
            ->andReturn(0.03);
            
        $this->service = new PaymentGatewayService($this->config, $this->logger);
    }

    protected function tearDown(): void {
        m::close();
    }

    public function testCalculateSurchargeForSupportedGateway(): void {
        $amount = 100.00;
        $gateway_id = 'intuit_qbms_credit_card';
        
        $this->logger->shouldReceive('info')
            ->once()
            ->with(m::pattern('/Calculated surcharge: 3 for gateway: intuit_qbms_credit_card/'));
        
        $surcharge = $this->service->calculateSurcharge($amount, $gateway_id);
        
        $this->assertEquals(3.00, $surcharge);
    }

    public function testCalculateSurchargeForUnsupportedGateway(): void {
        $amount = 100.00;
        $gateway_id = 'unsupported_gateway';
        
        $surcharge = $this->service->calculateSurcharge($amount, $gateway_id);
        
        $this->assertEquals(0.0, $surcharge);
    }

    public function testSurchargeRounding(): void {
        $amount = 100.33;
        $gateway_id = 'intuit_qbms_credit_card';
        
        $this->logger->shouldReceive('info')->once();
        
        $surcharge = $this->service->calculateSurcharge($amount, $gateway_id);
        
        // 100.33 * 0.03 = 3.0099, should round to 3.01
        $this->assertEquals(3.01, $surcharge);
    }

    /**
     * @dataProvider surchargeCalculationDataProvider
     */
    public function testSurchargeCalculationVariousAmounts(float $amount, float $expected): void {
        $gateway_id = 'intuit_qbms_credit_card';
        
        $this->logger->shouldReceive('info')->once();
        
        $surcharge = $this->service->calculateSurcharge($amount, $gateway_id);
        
        $this->assertEquals($expected, $surcharge);
    }

    public function surchargeCalculationDataProvider(): array {
        return [
            'Zero amount' => [0.00, 0.00],
            'Small amount' => [10.00, 0.30],
            'Large amount' => [1000.00, 30.00],
            'Decimal amount' => [123.45, 3.70],
            'Edge case' => [0.01, 0.00], // Rounds to 0
        ];
    }

    public function testIsGatewaySupported(): void {
        $this->assertTrue($this->service->isGatewaySupported('intuit_qbms_credit_card'));
        $this->assertFalse($this->service->isGatewaySupported('unsupported_gateway'));
    }

    public function testGetSupportedGateways(): void {
        $gateways = $this->service->getSupportedGateways();
        
        $this->assertIsArray($gateways);
        $this->assertArrayHasKey('intuit_qbms_credit_card', $gateways);
    }
}
```

#### 4.3.2 Pricing Service Tests
```php
<?php
namespace APW\WooPlugin\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use APW\WooPlugin\Services\Pricing\PricingService;
use APW\WooPlugin\Models\Product;
use APW\WooPlugin\Models\Customer;

class PricingServiceTest extends TestCase {
    private PricingService $service;
    private m\MockInterface $product_model;
    private m\MockInterface $customer_model;

    protected function setUp(): void {
        $this->product_model = m::mock(Product::class);
        $this->customer_model = m::mock(Customer::class);
        
        $this->service = new PricingService($this->product_model, $this->customer_model);
    }

    protected function tearDown(): void {
        m::close();
    }

    public function testCalculateDynamicPriceWithNoRules(): void {
        $product_id = 123;
        $quantity = 5;
        $base_price = 10.00;

        $mock_product = m::mock(\WC_Product::class);
        $mock_product->shouldReceive('get_regular_price')->andReturn($base_price);

        $this->product_model->shouldReceive('find')
            ->with($product_id)
            ->andReturn($mock_product);

        // Mock empty pricing rules
        $this->service->shouldReceive('getProductPricingRules')
            ->with($product_id)
            ->andReturn([]);

        $final_price = $this->service->calculateDynamicPrice($product_id, $quantity);

        $this->assertEquals($base_price, $final_price);
    }

    public function testApplyVIPDiscountForQualifyingCustomer(): void {
        $customer_id = 456;
        $subtotal = 500.00;

        $this->customer_model->shouldReceive('isVIPCustomer')
            ->with($customer_id)
            ->andReturn(true);

        // Mock current user
        $this->mockCurrentUser($customer_id);

        $discount = $this->service->applyVIPDiscount($subtotal);

        // 10% discount for $500+ orders
        $this->assertEquals(50.00, $discount);
    }

    public function testApplyVIPDiscountForNonVIPCustomer(): void {
        $customer_id = 456;
        $subtotal = 500.00;

        $this->customer_model->shouldReceive('isVIPCustomer')
            ->with($customer_id)
            ->andReturn(false);

        $this->mockCurrentUser($customer_id);

        $discount = $this->service->applyVIPDiscount($subtotal);

        $this->assertEquals(0.0, $discount);
    }

    /**
     * @dataProvider vipDiscountThresholdProvider
     */
    public function testVIPDiscountThresholds(float $subtotal, float $expected_discount): void {
        $customer_id = 456;

        $this->customer_model->shouldReceive('isVIPCustomer')
            ->with($customer_id)
            ->andReturn(true);

        $this->mockCurrentUser($customer_id);

        $discount = $this->service->applyVIPDiscount($subtotal);

        $this->assertEquals($expected_discount, $discount);
    }

    public function vipDiscountThresholdProvider(): array {
        return [
            'Below minimum threshold' => [50.00, 0.00],
            'Minimum $100 threshold' => [100.00, 5.00],   // 5% discount
            'Mid $300 threshold' => [300.00, 24.00],       // 8% discount
            'High $500 threshold' => [500.00, 50.00],      // 10% discount
            'Above high threshold' => [1000.00, 100.00],   // 10% discount
        ];
    }

    public function testGetProductPricingRulesWithCaching(): void {
        $product_id = 123;
        $rules = [
            ['type' => 'bulk', 'min_quantity' => 10, 'discount_rate' => 0.1]
        ];

        // First call should hit the model
        $this->product_model->shouldReceive('getPricingRules')
            ->with($product_id)
            ->once()
            ->andReturn($rules);

        // First call
        $result1 = $this->service->getProductPricingRules($product_id);
        
        // Second call should use cache (no additional model call)
        $result2 = $this->service->getProductPricingRules($product_id);

        $this->assertEquals($rules, $result1);
        $this->assertEquals($rules, $result2);
    }

    private function mockCurrentUser(int $user_id): void {
        // Mock WordPress function
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                return 456;
            }
        }
    }
}
```

#### 4.3.3 Input Validator Tests
```php
<?php
namespace APW\WooPlugin\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use APW\WooPlugin\Common\Validators\InputValidator;
use APW\WooPlugin\Common\Validators\ValidationException;

class InputValidatorTest extends TestCase {
    private InputValidator $validator;

    protected function setUp(): void {
        $this->validator = new InputValidator();
    }

    public function testValidateRequiredField(): void {
        $data = ['name' => 'John Doe', 'email' => ''];
        $rules = [
            'name' => ['required' => true],
            'email' => ['required' => true]
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('email', $result->errors);
        $this->assertEquals('John Doe', $result->data['name']);
    }

    public function testEmailValidation(): void {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => ['email' => true]];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('email', $result->errors);
    }

    public function testPhoneNumberSanitization(): void {
        $data = ['phone' => '(555) 123-4567 ext. 123'];
        $rules = ['phone' => ['sanitizer' => 'phone']];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result->isValid());
        $this->assertEquals('(555) 123-4567 123', $result->data['phone']);
    }

    public function testCustomValidationRule(): void {
        $data = ['quantity' => 5];
        $rules = [
            'quantity' => [
                'required' => true,
                'numeric' => true,
                'min_value' => 1,
                'max_value' => 10
            ]
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result->isValid());
        $this->assertEquals(5, $result->data['quantity']);
    }

    /**
     * @dataProvider maliciousInputProvider
     */
    public function testSecuritySanitization(string $input, string $sanitizer, string $expected): void {
        $data = ['input' => $input];
        $rules = ['input' => ['sanitizer' => $sanitizer]];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result->isValid());
        $this->assertEquals($expected, $result->data['input']);
    }

    public function maliciousInputProvider(): array {
        return [
            'XSS attempt' => [
                '<script>alert("xss")</script>',
                'text',
                ''
            ],
            'HTML in text field' => [
                '<b>Bold text</b>',
                'text',
                'Bold text'
            ],
            'SQL injection attempt' => [
                "'; DROP TABLE users; --",
                'text',
                "'; DROP TABLE users; --"
            ],
            'Safe HTML' => [
                '<p>Safe paragraph</p>',
                'html',
                '<p>Safe paragraph</p>'
            ]
        ];
    }
}
```

## Integration Testing Implementation

### 4.4 WooCommerce Integration Tests

#### 4.4.1 Checkout Integration Test
```php
<?php
namespace APW\WooPlugin\Tests\Integration\WooCommerce;

use APW\WooPlugin\Tests\Integration\BaseIntegrationTest;

class CheckoutIntegrationTest extends BaseIntegrationTest {
    public function setUp(): void {
        parent::setUp();
        
        // Set up WooCommerce environment
        $this->setupWooCommerce();
        $this->createTestProducts();
        $this->createTestCustomer();
    }

    public function testSurchargeAppliedOnIntuitPayment(): void {
        // Add product to cart
        $product_id = $this->createProduct(['price' => 100.00]);
        WC()->cart->add_to_cart($product_id, 1);

        // Set payment method to Intuit
        WC()->session->set('chosen_payment_method', 'intuit_qbms_credit_card');

        // Trigger cart calculation
        WC()->cart->calculate_totals();

        // Check that surcharge was applied
        $fees = WC()->cart->get_fees();
        $surcharge_fee = null;
        
        foreach ($fees as $fee) {
            if (strpos($fee->name, 'Surcharge') !== false) {
                $surcharge_fee = $fee;
                break;
            }
        }

        $this->assertNotNull($surcharge_fee, 'Surcharge fee should be applied');
        $this->assertEquals(3.00, $surcharge_fee->amount);
    }

    public function testVIPDiscountWithSurchargeCalculation(): void {
        // Create VIP customer
        $customer_id = $this->createVIPCustomer();
        wp_set_current_user($customer_id);

        // Add expensive product to trigger VIP discount
        $product_id = $this->createProduct(['price' => 500.00]);
        WC()->cart->add_to_cart($product_id, 1);

        // Set payment method
        WC()->session->set('chosen_payment_method', 'intuit_qbms_credit_card');

        // Calculate totals
        WC()->cart->calculate_totals();

        $fees = WC()->cart->get_fees();
        
        // Should have both VIP discount (negative) and surcharge (positive)
        $vip_discount = null;
        $surcharge = null;
        
        foreach ($fees as $fee) {
            if (strpos($fee->name, 'VIP') !== false) {
                $vip_discount = $fee;
            } elseif (strpos($fee->name, 'Surcharge') !== false) {
                $surcharge = $fee;
            }
        }

        $this->assertNotNull($vip_discount, 'VIP discount should be applied');
        $this->assertNotNull($surcharge, 'Surcharge should be applied');
        
        // VIP discount: $500 * 10% = -$50
        $this->assertEquals(-50.00, $vip_discount->amount);
        
        // Surcharge: ($500 - $50) * 3% = $13.50
        $this->assertEquals(13.50, $surcharge->amount);
    }

    public function testCustomCheckoutFieldValidation(): void {
        $_POST = [
            'billing_first_name' => 'John',
            'billing_last_name' => 'Doe',
            'billing_email' => 'john@example.com',
            'billing_company' => '', // Required field left empty
            'billing_phone' => '555-1234',
            'apw_additional_emails' => 'invalid-email'
        ];

        $checkout = WC()->checkout();
        $checkout->process_checkout();

        $notices = wc_get_notices('error');
        
        $this->assertNotEmpty($notices);
        
        // Check for company validation error
        $has_company_error = false;
        $has_email_error = false;
        
        foreach ($notices as $notice) {
            if (strpos($notice['notice'], 'Company') !== false) {
                $has_company_error = true;
            }
            if (strpos($notice['notice'], 'email') !== false) {
                $has_email_error = true;
            }
        }

        $this->assertTrue($has_company_error, 'Should validate required company field');
        $this->assertTrue($has_email_error, 'Should validate additional email format');
    }

    public function testRecurringBillingFieldDisplay(): void {
        // Create product with recurring tag
        $product_id = $this->createProduct(['tags' => ['recurring']]);
        WC()->cart->add_to_cart($product_id, 1);

        // Start output buffering to capture checkout form
        ob_start();
        do_action('woocommerce_checkout_before_customer_details');
        do_action('woocommerce_checkout_billing');
        $checkout_html = ob_get_clean();

        $this->assertStringContainsString('Preferred Monthly Billing Method', $checkout_html);
        $this->assertStringContainsString('billing_recurring_method', $checkout_html);
    }

    private function createProduct(array $attributes = []): int {
        $product = new \WC_Product_Simple();
        $product->set_name($attributes['name'] ?? 'Test Product');
        $product->set_regular_price($attributes['price'] ?? 10.00);
        $product->set_status('publish');
        
        if (isset($attributes['tags'])) {
            wp_set_object_terms($product->get_id(), $attributes['tags'], 'product_tag');
        }
        
        return $product->save();
    }

    private function createVIPCustomer(): int {
        $customer_id = wp_create_user('vip_user', 'password', 'vip@example.com');
        update_user_meta($customer_id, 'is_vip_customer', true);
        return $customer_id;
    }
}
```

#### 4.4.2 ACF Integration Test
```php
<?php
namespace APW\WooPlugin\Tests\Integration\ACF;

use APW\WooPlugin\Tests\Integration\BaseIntegrationTest;
use APW\WooPlugin\Integrations\ACF\FieldManager;
use APW\WooPlugin\Integrations\ACF\DataMapper;

class FieldManagerTest extends BaseIntegrationTest {
    private FieldManager $field_manager;
    private DataMapper $data_mapper;

    public function setUp(): void {
        parent::setUp();
        
        // Mock ACF functions if not available
        $this->mockACFFunctions();
        
        $this->field_manager = new FieldManager();
        $this->data_mapper = new DataMapper();
    }

    public function testFAQFieldGroupRegistration(): void {
        $this->field_manager->register();

        // Verify field group was registered with ACF
        $field_groups = acf_get_local_field_groups();
        
        $faq_group = null;
        foreach ($field_groups as $group) {
            if ($group['key'] === 'group_apw_faq') {
                $faq_group = $group;
                break;
            }
        }

        $this->assertNotNull($faq_group, 'FAQ field group should be registered');
        $this->assertEquals('APW FAQ Settings', $faq_group['title']);
    }

    public function testFAQDataRetrieval(): void {
        $product_id = $this->createTestProduct();
        
        // Mock FAQ data
        $faq_data = [
            [
                'question' => 'What is this product?',
                'answer' => 'This is a test product.'
            ],
            [
                'question' => 'How do I use it?',
                'answer' => 'Follow the instructions.'
            ]
        ];

        // Simulate ACF data storage
        update_field('faqs', $faq_data, $product_id);

        // Test data retrieval
        $retrieved_faqs = $this->data_mapper->getFAQs($product_id);

        $this->assertCount(2, $retrieved_faqs);
        $this->assertEquals('What is this product?', $retrieved_faqs[0]['question']);
        $this->assertEquals('This is a test product.', $retrieved_faqs[0]['answer']);
    }

    public function testVIPCustomerFieldFunctionality(): void {
        $customer_id = wp_create_user('test_customer', 'password', 'test@example.com');

        // Set VIP status
        update_field('is_vip_customer', true, "user_{$customer_id}");
        update_field('vip_discount_rate', 15.5, "user_{$customer_id}");

        // Test data retrieval
        $is_vip = $this->data_mapper->isVIPCustomer($customer_id);
        $discount_rate = $this->data_mapper->getVIPDiscountRate($customer_id);

        $this->assertTrue($is_vip);
        $this->assertEquals(15.5, $discount_rate);
    }

    public function testFAQFieldValidation(): void {
        $product_id = $this->createTestProduct();
        
        // Test with invalid FAQ data (missing required fields)
        $invalid_faq_data = [
            [
                'question' => 'Valid question',
                // Missing answer
            ],
            [
                // Missing question
                'answer' => 'Valid answer'
            ]
        ];

        update_field('faqs', $invalid_faq_data, $product_id);
        
        $retrieved_faqs = $this->data_mapper->getFAQs($product_id);

        // Should return sanitized data, filtering out invalid entries
        foreach ($retrieved_faqs as $faq) {
            $this->assertArrayHasKey('question', $faq);
            $this->assertArrayHasKey('answer', $faq);
            $this->assertNotEmpty($faq['question']);
            $this->assertNotEmpty($faq['answer']);
        }
    }

    private function mockACFFunctions(): void {
        if (!function_exists('acf_add_local_field_group')) {
            function acf_add_local_field_group($field_group) {
                global $acf_field_groups;
                if (!isset($acf_field_groups)) {
                    $acf_field_groups = [];
                }
                $acf_field_groups[$field_group['key']] = $field_group;
            }
        }

        if (!function_exists('acf_get_local_field_groups')) {
            function acf_get_local_field_groups() {
                global $acf_field_groups;
                return $acf_field_groups ?? [];
            }
        }

        if (!function_exists('get_field')) {
            function get_field($selector, $post_id = false) {
                return get_post_meta($post_id, $selector, true);
            }
        }

        if (!function_exists('update_field')) {
            function update_field($selector, $value, $post_id = false) {
                return update_post_meta($post_id, $selector, $value);
            }
        }
    }
}
```

## End-to-End Testing Implementation

### 4.5 Critical User Flow Testing

#### 4.5.1 Complete Checkout Flow Test
```php
<?php
namespace APW\WooPlugin\Tests\EndToEnd\Checkout;

use APW\WooPlugin\Tests\EndToEnd\BaseE2ETest;

class PaymentProcessingTest extends BaseE2ETest {
    public function testCompleteCheckoutWithSurcharge(): void {
        // Step 1: Navigate to shop and add product to cart
        $this->browser->visit('/shop/');
        $this->browser->click('[data-product-id="123"] .add-to-cart-btn');
        
        // Verify cart indicator updates
        $this->browser->waitFor('.cart-quantity-indicator');
        $cart_quantity = $this->browser->text('.cart-quantity-indicator');
        $this->assertEquals('1', $cart_quantity);

        // Step 2: Go to cart and verify totals
        $this->browser->visit('/cart/');
        $this->browser->assertSee('$100.00'); // Base product price

        // Step 3: Proceed to checkout
        $this->browser->click('.checkout-button');
        $this->browser->waitForPath('/checkout/');

        // Step 4: Fill out billing information
        $this->fillBillingInformation([
            'billing_first_name' => 'John',
            'billing_last_name' => 'Doe',
            'billing_email' => 'john.doe@example.com',
            'billing_company' => 'Test Company',
            'billing_phone' => '(555) 123-4567',
            'billing_address_1' => '123 Test Street',
            'billing_city' => 'Test City',
            'billing_state' => 'NY',
            'billing_postcode' => '12345'
        ]);

        // Step 5: Select Intuit payment method
        $this->browser->click('[value="intuit_qbms_credit_card"]');
        
        // Wait for surcharge to be calculated and displayed
        $this->browser->waitFor('.fee-line:contains("Credit Card Surcharge")');
        
        // Verify surcharge amount
        $surcharge_text = $this->browser->text('.fee-line:contains("Credit Card Surcharge") .amount');
        $this->assertStringContains('$3.00', $surcharge_text);

        // Step 6: Fill credit card information
        $this->fillCreditCardInfo([
            'card_number' => '4111111111111111',
            'expiry_month' => '12',
            'expiry_year' => '2025',
            'cvv' => '123'
        ]);

        // Step 7: Place order
        $this->browser->click('#place_order');
        
        // Wait for order completion
        $this->browser->waitForPath('/checkout/order-received/*');
        
        // Verify order success
        $this->browser->assertSee('Order received');
        $this->browser->assertSee('Thank you');

        // Step 8: Verify order in database
        $orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        $latest_order = $orders[0];
        
        $this->assertEquals(103.00, $latest_order->get_total()); // $100 + $3 surcharge
        
        // Verify surcharge fee in order
        $fees = $latest_order->get_fees();
        $surcharge_fee = null;
        
        foreach ($fees as $fee) {
            if (strpos($fee->get_name(), 'Surcharge') !== false) {
                $surcharge_fee = $fee;
                break;
            }
        }
        
        $this->assertNotNull($surcharge_fee);
        $this->assertEquals(3.00, $surcharge_fee->get_total());
    }

    public function testVIPCustomerCheckoutWithDiscount(): void {
        // Login as VIP customer
        $this->loginAsVIPCustomer();

        // Add high-value product to trigger VIP discount
        $product_id = $this->createProduct(['price' => 500.00]);
        $this->addProductToCart($product_id);

        // Go to checkout
        $this->browser->visit('/checkout/');

        // Verify VIP discount is applied
        $this->browser->waitFor('.fee-line:contains("VIP Discount")');
        $discount_text = $this->browser->text('.fee-line:contains("VIP Discount") .amount');
        $this->assertStringContains('-$50.00', $discount_text);

        // Select payment method and verify surcharge calculation
        $this->browser->click('[value="intuit_qbms_credit_card"]');
        $this->browser->waitFor('.fee-line:contains("Credit Card Surcharge")');
        
        // Surcharge should be calculated on discounted amount
        // ($500 - $50) * 3% = $13.50
        $surcharge_text = $this->browser->text('.fee-line:contains("Credit Card Surcharge") .amount');
        $this->assertStringContains('$13.50', $surcharge_text);

        // Verify final total
        $total_text = $this->browser->text('.order-total .amount');
        $this->assertStringContains('$463.50', $total_text); // $500 - $50 + $13.50
    }

    public function testDynamicPricingIntegration(): void {
        // Add product with bulk pricing rules
        $product_id = $this->createProductWithBulkPricing();
        
        // Add quantity that triggers bulk discount
        $this->browser->visit("/product/{$product_id}/");
        $this->browser->type('[name="quantity"]', '10');
        $this->browser->click('.single_add_to_cart_button');

        // Verify bulk pricing message is displayed
        $this->browser->waitFor('.bulk-pricing-message');
        $this->browser->assertSee('Bulk discount applied');

        // Go to cart and verify discounted price
        $this->browser->visit('/cart/');
        
        // Should show discounted line total
        $line_total = $this->browser->text('.cart-item .line-total');
        $this->assertStringContains('$90.00', $line_total); // 10 * $10 with 10% bulk discount
    }

    private function fillBillingInformation(array $data): void {
        foreach ($data as $field => $value) {
            $this->browser->type("[name=\"{$field}\"]", $value);
        }
    }

    private function fillCreditCardInfo(array $data): void {
        // Fill credit card form in iframe if present
        $this->browser->withinFrame('[name="intuit-card-iframe"]', function($frame) use ($data) {
            $frame->type('[name="card_number"]', $data['card_number']);
            $frame->select('[name="expiry_month"]', $data['expiry_month']);
            $frame->select('[name="expiry_year"]', $data['expiry_year']);
            $frame->type('[name="cvv"]', $data['cvv']);
        });
    }

    private function loginAsVIPCustomer(): void {
        $customer = $this->createVIPCustomer();
        $this->browser->loginAs($customer);
    }

    private function addProductToCart(int $product_id): void {
        $this->browser->visit("/product/{$product_id}/");
        $this->browser->click('.single_add_to_cart_button');
        $this->browser->waitFor('.woocommerce-message');
    }
}
```

## Performance Testing Implementation

### 4.6 Load and Performance Tests

#### 4.6.1 Database Performance Test
```php
<?php
namespace APW\WooPlugin\Tests\Performance;

use APW\WooPlugin\Tests\BaseTestCase;
use APW\WooPlugin\Services\Performance\PerformanceMonitor;

class DatabasePerformanceTest extends BaseTestCase {
    private PerformanceMonitor $monitor;

    protected function setUp(): void {
        parent::setUp();
        
        $logger = $this->createMock(LoggerServiceInterface::class);
        $this->monitor = new PerformanceMonitor($logger);
    }

    public function testCustomerReferralQueryPerformance(): void {
        // Create test data
        $this->createTestCustomers(1000);
        $this->createTestReferrals(200);

        $this->monitor->startTimer('referral_query');
        
        $customer_service = $this->getContainer()->make('APW\WooPlugin\Services\Customer\CustomerServiceInterface');
        $referrals = $customer_service->getReferralCustomers();
        
        $metrics = $this->monitor->endTimer('referral_query');

        // Performance assertions
        $this->assertLessThan(1.0, $metrics['duration'], 'Referral query should complete in under 1 second');
        $this->assertLessThan(5 * 1024 * 1024, $metrics['memory_used'], 'Memory usage should be under 5MB');
        
        // Result assertions
        $this->assertCount(200, $referrals);
    }

    public function testPricingRulesLookupPerformance(): void {
        // Create products with pricing rules
        $product_ids = $this->createProductsWithPricingRules(100);

        $this->monitor->startTimer('pricing_lookup');
        
        $pricing_service = $this->getContainer()->make('APW\WooPlugin\Services\Pricing\PricingServiceInterface');
        
        foreach ($product_ids as $product_id) {
            $pricing_service->getProductPricingRules($product_id);
        }
        
        $metrics = $this->monitor->endTimer('pricing_lookup');

        // Should complete in reasonable time with caching
        $this->assertLessThan(2.0, $metrics['duration'], 'Pricing lookup for 100 products should complete in under 2 seconds');
    }

    public function testCartCalculationPerformance(): void {
        // Add multiple products to cart
        $this->addProductsToCart(20);

        $this->monitor->startTimer('cart_calculation');
        
        // Trigger cart calculation multiple times
        for ($i = 0; $i < 10; $i++) {
            WC()->cart->calculate_totals();
        }
        
        $metrics = $this->monitor->endTimer('cart_calculation');

        $this->assertLessThan(0.5, $metrics['duration'], 'Cart calculation should be fast even with multiple products');
    }

    public function testDatabaseQueryCount(): void {
        global $wpdb;
        
        $query_count_before = $wpdb->num_queries;
        
        // Perform operations that should be optimized
        $customer_service = $this->getContainer()->make('APW\WooPlugin\Services\Customer\CustomerServiceInterface');
        $customer_service->getReferralCustomers();
        
        $query_count_after = $wpdb->num_queries;
        $queries_executed = $query_count_after - $query_count_before;

        // Should not execute excessive queries
        $this->assertLessThan(5, $queries_executed, 'Should execute minimal database queries');
    }

    public function testCacheEfficiency(): void {
        $cache_service = $this->getContainer()->make('APW\WooPlugin\Services\Cache\CacheServiceInterface');
        
        $product_id = $this->createTestProduct();
        
        // First call should miss cache
        $this->monitor->startTimer('cache_miss');
        $pricing_service = $this->getContainer()->make('APW\WooPlugin\Services\Pricing\PricingServiceInterface');
        $rules1 = $pricing_service->getProductPricingRules($product_id);
        $miss_metrics = $this->monitor->endTimer('cache_miss');

        // Second call should hit cache
        $this->monitor->startTimer('cache_hit');
        $rules2 = $pricing_service->getProductPricingRules($product_id);
        $hit_metrics = $this->monitor->endTimer('cache_hit');

        // Cache hit should be significantly faster
        $this->assertLessThan($miss_metrics['duration'] / 2, $hit_metrics['duration']);
        $this->assertEquals($rules1, $rules2);
    }

    private function createTestCustomers(int $count): array {
        $customer_ids = [];
        
        for ($i = 0; $i < $count; $i++) {
            $customer_id = wp_create_user("customer_{$i}", 'password', "customer_{$i}@example.com");
            update_user_meta($customer_id, 'first_name', "Customer {$i}");
            update_user_meta($customer_id, 'company_name', "Company {$i}");
            $customer_ids[] = $customer_id;
        }
        
        return $customer_ids;
    }

    private function createTestReferrals(int $count): void {
        $customers = $this->createTestCustomers($count);
        
        foreach ($customers as $index => $customer_id) {
            if ($index % 5 === 0) { // Every 5th customer has a referral
                update_user_meta($customer_id, 'referred_by', 'Referrer Name');
            }
        }
    }
}
```

## Continuous Integration Setup

### 4.7 GitHub Actions Workflow

#### 4.7.1 Complete CI/CD Pipeline
```yaml
# .github/workflows/test.yml
name: Test Suite

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  unit-tests:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: [7.4, 8.0, 8.1]
        wordpress-version: [5.9, 6.0, 6.1]

    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, mysql, mysqli, pdo_mysql
        coverage: xdebug

    - name: Install Composer dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Setup WordPress test environment
      run: |
        bash bin/install-wp-tests.sh wordpress_test root root localhost ${{ matrix.wordpress-version }}

    - name: Run PHP CodeSniffer
      run: vendor/bin/phpcs --standard=WordPress --ignore=vendor/,node_modules/ ./

    - name: Run PHPUnit tests
      run: |
        vendor/bin/phpunit --coverage-clover=coverage.xml
        
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml

  integration-tests:
    runs-on: ubuntu-latest
    needs: unit-tests

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.0

    - name: Install WooCommerce
      run: |
        wget https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip
        unzip woocommerce.latest-stable.zip

    - name: Install ACF Pro (mock)
      run: |
        # Install ACF Pro mock for testing
        mkdir -p advanced-custom-fields-pro
        echo "<?php // ACF Pro Mock" > advanced-custom-fields-pro/acf.php

    - name: Run integration tests
      run: vendor/bin/phpunit --testsuite=Integration

  e2e-tests:
    runs-on: ubuntu-latest
    needs: integration-tests

    steps:
    - uses: actions/checkout@v3

    - name: Setup WordPress with Docker
      run: |
        docker-compose up -d
        sleep 30

    - name: Install Playwright
      run: |
        npm install @playwright/test
        npx playwright install

    - name: Run E2E tests
      run: |
        npx playwright test

    - name: Upload test results
      uses: actions/upload-artifact@v3
      if: failure()
      with:
        name: e2e-test-results
        path: test-results/

  performance-tests:
    runs-on: ubuntu-latest
    needs: integration-tests

    steps:
    - uses: actions/checkout@v3

    - name: Setup performance test environment
      run: |
        # Setup optimized environment
        echo "opcache.enable=1" >> /etc/php/8.0/cli/php.ini
        echo "opcache.enable_cli=1" >> /etc/php/8.0/cli/php.ini

    - name: Run performance benchmarks
      run: |
        vendor/bin/phpunit --testsuite=Performance
        
    - name: Generate performance report
      run: |
        php bin/generate-performance-report.php

  security-scan:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3

    - name: Run security scan
      run: |
        # Install security scanner
        composer require --dev enlightn/security-checker
        vendor/bin/security-checker security:check

    - name: PHPCS Security scan
      run: |
        vendor/bin/phpcs --standard=WordPress-Extra --ignore=vendor/,node_modules/ ./
```

## Success Criteria

### 4.8 Testing Metrics and Quality Gates

#### 4.8.1 Coverage Requirements
- [ ] **Unit Test Coverage**: >80% line coverage
- [ ] **Integration Test Coverage**: All critical paths tested
- [ ] **E2E Test Coverage**: All user workflows tested
- [ ] **Performance Benchmarks**: All benchmarks pass
- [ ] **Security Tests**: No vulnerabilities detected

#### 4.8.2 Quality Gates
- [ ] All tests pass in CI/CD pipeline
- [ ] Performance tests meet requirements
- [ ] Security scans pass
- [ ] Code quality metrics meet standards
- [ ] Test execution time <10 minutes

#### 4.8.3 Test Maintenance
- [ ] Tests are maintained with code changes
- [ ] Test data is properly isolated
- [ ] Test environments are reproducible
- [ ] Test documentation is current

## Next Steps
Upon completion:
1. Proceed to Phase 4: Documentation Implementation
2. Establish test maintenance procedures
3. Create test execution monitoring
4. Implement test-driven development practices