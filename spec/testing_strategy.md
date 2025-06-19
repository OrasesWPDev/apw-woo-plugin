# Testing Strategy Specification

## Overview
This specification defines a practical testing approach for the APW WooCommerce Plugin refactor, focusing on critical functionality verification, regression prevention, and performance validation using WordPress and WooCommerce testing patterns.

## Testing Philosophy

### Pragmatic WordPress Testing
- **WordPress-Native Tools**: Use WordPress test suite patterns
- **Critical Path Focus**: Test revenue-impacting functionality first
- **Manual + Automated**: Combine manual testing with automated checks
- **Real Environment**: Test in actual WordPress/WooCommerce environments
- **Regression Prevention**: Ensure existing functionality continues working

### Testing Priorities
1. **Critical**: Payment processing, VIP discounts, cart calculations
2. **High**: Customer registration, product display, template rendering
3. **Medium**: Admin functionality, export features, integrations
4. **Low**: Debug output, logging, optimization features

## Phase 1: Critical Payment Processing Tests

### Payment Surcharge Calculation Tests

#### Test Scenario 1: Basic Surcharge Calculation
```php
/**
 * Test basic credit card surcharge calculation
 */
function test_basic_surcharge_calculation() {
    // Setup
    $cart_subtotal = 100.00;
    $shipping_total = 10.00;
    $expected_surcharge = ($cart_subtotal + $shipping_total) * 0.03; // $3.30
    
    // Execute
    WC()->session->set('chosen_payment_method', 'intuit_payments_credit_card');
    WC()->cart->add_fee('Test Product', 100.00);
    WC()->cart->calculate_totals();
    
    // Verify
    $fees = WC()->cart->get_fees();
    $surcharge_fee = null;
    
    foreach ($fees as $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            $surcharge_fee = $fee;
            break;
        }
    }
    
    assert($surcharge_fee !== null, 'Surcharge fee should be added');
    assert(abs($surcharge_fee->amount - $expected_surcharge) < 0.01, 'Surcharge amount should be $3.30');
}
```

#### Test Scenario 2: VIP Discount Integration (Critical Bug Fix)
```php
/**
 * Test credit card surcharge with VIP discount applied
 * This is the CRITICAL test case that was failing before refactor
 */
function test_surcharge_with_vip_discount() {
    // Setup: Product #80, quantity 5, VIP customer
    $product_id = 80;
    $quantity = 5;
    $product_price = 100.00; // Assume $100 per item
    $subtotal = $product_price * $quantity; // $500
    $shipping = 0.00;
    $vip_discount = $subtotal * 0.10; // 10% VIP discount = $50
    
    // Expected surcharge: (subtotal + shipping - VIP discount) * 3%
    // ($500 + $0 - $50) * 0.03 = $13.50
    $expected_surcharge = 13.50;
    
    // Execute
    // 1. Add product to cart
    WC()->cart->add_to_cart($product_id, $quantity);
    
    // 2. Apply VIP discount (simulating VIP customer)
    WC()->cart->add_fee('VIP Discount (10%)', -$vip_discount, false);
    
    // 3. Set payment method to credit card
    WC()->session->set('chosen_payment_method', 'intuit_payments_credit_card');
    
    // 4. Calculate totals (this should apply surcharge correctly)
    WC()->cart->calculate_totals();
    
    // Verify
    $fees = WC()->cart->get_fees();
    $surcharge_amount = 0;
    $vip_discount_found = false;
    
    foreach ($fees as $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            $surcharge_amount = $fee->amount;
        }
        if (strpos($fee->name, 'VIP Discount') !== false) {
            $vip_discount_found = true;
        }
    }
    
    assert($vip_discount_found, 'VIP discount should be applied');
    assert(abs($surcharge_amount - $expected_surcharge) < 0.01, 
           "Surcharge should be $13.50, got $" . number_format($surcharge_amount, 2));
    
    // This test should now PASS after the refactor fixes the timing issue
}
```

#### Test Scenario 3: Payment Method Switching
```php
/**
 * Test surcharge removal when switching payment methods
 */
function test_payment_method_switching() {
    // Setup with credit card (surcharge should apply)
    WC()->cart->add_to_cart(1, 1); // Add any product
    WC()->session->set('chosen_payment_method', 'intuit_payments_credit_card');
    WC()->cart->calculate_totals();
    
    // Verify surcharge exists
    $fees_with_surcharge = WC()->cart->get_fees();
    $surcharge_exists = false;
    foreach ($fees_with_surcharge as $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            $surcharge_exists = true;
            break;
        }
    }
    assert($surcharge_exists, 'Surcharge should exist with credit card payment');
    
    // Switch to different payment method
    WC()->session->set('chosen_payment_method', 'bacs'); // Bank transfer
    WC()->cart->calculate_totals();
    
    // Verify surcharge removed
    $fees_without_surcharge = WC()->cart->get_fees();
    $surcharge_exists_after = false;
    foreach ($fees_without_surcharge as $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            $surcharge_exists_after = true;
            break;
        }
    }
    assert(!$surcharge_exists_after, 'Surcharge should be removed with non-credit-card payment');
}
```

### Manual Payment Testing Checklist

#### Pre-Refactor Testing (Establish Baseline)
- [ ] **Product #80, Qty 5**: Document current surcharge amount (should be incorrect)
- [ ] **Payment method switch**: Verify current behavior
- [ ] **VIP discount timing**: Document current issues
- [ ] **Cart updates**: Test quantity changes with surcharge

#### Post-Refactor Testing (Verify Fixes)
- [ ] **Product #80, Qty 5**: Surcharge should be $15.64, not $17.14
- [ ] **Multiple scenarios**: Test various product/quantity combinations
- [ ] **Edge cases**: Empty cart, zero quantities, invalid products
- [ ] **Performance**: No infinite loops or excessive calculations

## Phase 2: Service Integration Tests

### Customer Service Tests
```php
/**
 * Test VIP customer identification
 */
function test_vip_customer_identification() {
    // Create test customer
    $customer_id = wp_create_user('test_vip', 'password', 'test@example.com');
    
    // Set customer total spent to VIP threshold
    update_user_meta($customer_id, '_money_spent', 150.00); // Above $100 VIP threshold
    
    // Test VIP status
    $customer_service = APW_Woo_Customer_Service::get_instance();
    $is_vip = $customer_service->is_vip_customer($customer_id);
    
    assert($is_vip, 'Customer with $150 spent should be VIP');
    
    // Test VIP discount tier
    $discount_tier = $customer_service->get_vip_discount_tier($customer_id);
    assert($discount_tier['tier'] === 'silver', 'Customer should be in silver tier');
    assert($discount_tier['discount'] === 0.05, 'Discount should be 5%');
    
    // Cleanup
    wp_delete_user($customer_id);
}

/**
 * Test customer registration validation
 */
function test_customer_registration_validation() {
    $customer_service = APW_Woo_Customer_Service::get_instance();
    
    // Test valid data
    $valid_data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'company_name' => 'Test Company',
        'phone_number' => '555-123-4567'
    ];
    
    $errors = $customer_service->validate_registration_data($valid_data);
    assert(empty($errors), 'Valid data should pass validation');
    
    // Test invalid data
    $invalid_data = [
        'first_name' => '',  // Missing required field
        'email' => 'invalid-email',  // Invalid email format
        'phone_number' => 'abc123'   // Invalid phone format
    ];
    
    $errors = $customer_service->validate_registration_data($invalid_data);
    assert(!empty($errors), 'Invalid data should fail validation');
    assert(isset($errors['first_name']), 'Should have first_name error');
    assert(isset($errors['email']), 'Should have email error');
}
```

### Product Service Tests
```php
/**
 * Test dynamic pricing calculation
 */
function test_dynamic_pricing_calculation() {
    // Create test product with pricing rules
    $product_id = wp_insert_post([
        'post_title' => 'Test Product',
        'post_type' => 'product',
        'post_status' => 'publish'
    ]);
    
    // Set base price
    update_post_meta($product_id, '_regular_price', 100.00);
    update_post_meta($product_id, '_price', 100.00);
    
    // Set quantity-based pricing rule (5+ items = 10% discount)
    $pricing_rules = [
        [
            'quantity_min' => 5,
            'quantity_max' => 999,
            'discount_type' => 'percentage',
            'discount_amount' => 10
        ]
    ];
    update_post_meta($product_id, '_pricing_rules', $pricing_rules);
    
    // Test pricing calculation
    $product_service = APW_Woo_Product_Service::get_instance();
    $price_for_1 = $product_service->calculate_dynamic_price($product_id, 1);
    $price_for_5 = $product_service->calculate_dynamic_price($product_id, 5);
    
    assert($price_for_1 == 100.00, 'Price for 1 item should be $100');
    assert($price_for_5 == 90.00, 'Price for 5 items should be $90 (10% discount)');
    
    // Cleanup
    wp_delete_post($product_id, true);
}
```

## Phase 3: Template and Integration Tests

### Template Rendering Tests
```php
/**
 * Test template loading and rendering
 */
function test_template_loading() {
    // Test template hierarchy
    $template_name = 'partials/faq-display.php';
    $test_args = ['faqs' => [['question' => 'Test?', 'answer' => 'Test!']]];
    
    // Capture output
    ob_start();
    apw_woo_load_template($template_name, $test_args);
    $output = ob_get_clean();
    
    assert(!empty($output), 'Template should produce output');
    assert(strpos($output, 'Test?') !== false, 'Output should contain FAQ question');
    assert(strpos($output, 'Test!') !== false, 'Output should contain FAQ answer');
}

/**
 * Test shortcode preservation
 */
function test_shortcode_preservation() {
    // Test that shortcodes still work after refactor
    $shortcode_output = do_shortcode('[block id="third-level-woo-page-header"]');
    
    // This test ensures existing shortcodes continue to function
    // Exact assertion depends on what the shortcode should return
    assert($shortcode_output !== '[block id="third-level-woo-page-header"]', 
           'Shortcode should be processed, not returned as-is');
}
```

### Cart Integration Tests
```php
/**
 * Test cart quantity indicator updates
 */
function test_cart_quantity_indicators() {
    // Start with empty cart
    WC()->cart->empty_cart();
    $cart_service = APW_Woo_Cart_Service::get_instance();
    
    $initial_count = $cart_service->get_cart_quantity();
    assert($initial_count === 0, 'Initial cart should be empty');
    
    // Add product to cart
    $product_id = wp_insert_post(['post_title' => 'Test', 'post_type' => 'product']);
    WC()->cart->add_to_cart($product_id, 3);
    
    $updated_count = $cart_service->get_cart_quantity();
    assert($updated_count === 3, 'Cart should contain 3 items');
    
    // Cleanup
    WC()->cart->empty_cart();
    wp_delete_post($product_id, true);
}
```

## WordPress/WooCommerce Test Environment Setup

### Local Development Testing
```bash
# Install WordPress test suite
bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Install WooCommerce testing framework
composer require --dev woocommerce/woocommerce-sniffs
composer require --dev wp-phpunit/wp-phpunit
```

### Test Configuration
```php
// tests/bootstrap.php
<?php
// Include WordPress test functions
require_once dirname(__FILE__) . '/includes/functions.php';

// Activate our plugin
function _manually_load_plugin() {
    require dirname(__FILE__) . '/../apw-woo-plugin.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Include the WordPress testing environment
require dirname(__FILE__) . '/includes/bootstrap.php';

// Activate WooCommerce for testing
tests_add_filter('init', function() {
    if (!class_exists('WooCommerce')) {
        activate_plugin('woocommerce/woocommerce.php');
    }
});
```

## Manual Testing Procedures

### Pre-Deployment Testing Checklist

#### Payment Processing (Critical)
- [ ] **Test Case 1**: Product #80, Quantity 5, VIP customer → Surcharge should be $15.64
- [ ] **Test Case 2**: Regular customer, $100 order → Surcharge should be $3.00
- [ ] **Test Case 3**: Switch from credit card to bank transfer → Surcharge removed
- [ ] **Test Case 4**: Update cart quantities → Surcharge recalculates correctly
- [ ] **Test Case 5**: Multiple VIP discount tiers → Correct discounts applied

#### Customer Functionality
- [ ] **Registration**: All required fields validated
- [ ] **VIP Status**: Correct tier assignment based on spending
- [ ] **Referral Export**: CSV export works correctly
- [ ] **Account Management**: Customer data updates properly

#### Template Rendering
- [ ] **Product Pages**: Templates load correctly with shortcodes
- [ ] **FAQ Sections**: Display properly from ACF data
- [ ] **Cart Indicators**: Update in real-time
- [ ] **Admin Pages**: Settings and export forms work

#### Performance Testing
- [ ] **Page Load Times**: No degradation from refactor
- [ ] **Memory Usage**: Monitor for memory leaks
- [ ] **Database Queries**: No unnecessary query increases
- [ ] **Cache Performance**: Verify caching effectiveness

### Browser Testing Matrix
- **Chrome** (latest): Primary testing browser
- **Firefox** (latest): Secondary testing
- **Safari** (latest): Mobile compatibility
- **Edge** (latest): Windows compatibility

### Device Testing
- **Desktop**: 1920x1080, 1366x768
- **Tablet**: iPad (768x1024), Android tablet
- **Mobile**: iPhone (375x667), Android phone (360x640)

## Automated Testing Implementation

### Unit Test Structure
```php
// tests/test-payment-service.php
class Test_Payment_Service extends WP_UnitTestCase {
    
    private $payment_service;
    
    public function setUp() {
        parent::setUp();
        $this->payment_service = APW_Woo_Payment_Service::get_instance();
    }
    
    public function test_surcharge_calculation() {
        $surcharge = $this->payment_service->calculate_surcharge(100.00);
        $this->assertEquals(3.00, $surcharge, 'Surcharge should be 3% of amount');
    }
    
    public function test_vip_discount_integration() {
        // Test the critical bug fix scenario
        WC()->cart->add_fee('VIP Discount', -50.00);
        $surcharge = $this->payment_service->calculate_surcharge_with_discounts(500.00);
        $this->assertEquals(13.50, $surcharge, 'Surcharge should account for VIP discount');
    }
}
```

### Continuous Integration
```yaml
# .github/workflows/test.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: vendor/bin/phpunit
```

## Performance Benchmarking

### Before/After Comparison
```php
/**
 * Performance testing helper
 */
function apw_woo_benchmark_function($callback, $iterations = 100) {
    $start_time = microtime(true);
    $start_memory = memory_get_usage();
    
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    
    $end_time = microtime(true);
    $end_memory = memory_get_usage();
    
    return [
        'time' => ($end_time - $start_time) / $iterations,
        'memory' => ($end_memory - $start_memory) / $iterations
    ];
}

// Usage
$old_performance = apw_woo_benchmark_function(function() {
    // Old payment calculation code
});

$new_performance = apw_woo_benchmark_function(function() {
    // New payment service code
});

// Compare results
assert($new_performance['time'] <= $old_performance['time'], 'New code should be faster');
assert($new_performance['memory'] <= $old_performance['memory'], 'New code should use less memory');
```

## Success Criteria

### Critical Functionality (Must Pass)
- [ ] All payment processing tests pass
- [ ] VIP discount integration works correctly
- [ ] No regression in existing features
- [ ] Performance maintains or improves

### Code Quality (Should Pass)
- [ ] All unit tests pass
- [ ] Manual testing checklist completed
- [ ] Browser compatibility verified
- [ ] Security validation completed

### Performance (Target Goals)
- [ ] Page load times within 10% of baseline
- [ ] Memory usage reduced by 15%
- [ ] Database queries optimized
- [ ] Cache hit rates improved

This testing strategy ensures the refactored plugin maintains reliability while delivering the expected improvements in code quality and performance.