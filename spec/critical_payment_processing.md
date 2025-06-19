# Critical Payment Processing Issues Specification

## Overview
Address critical payment processing bugs, particularly credit card surcharge configuration and VIP discount integration issues that are causing revenue loss and customer dissatisfaction.

## Critical Issues Identified

### 1. Payment Surcharge Calculation Errors

#### 1.1 Current Problems
**File**: `includes/apw-woo-intuit-payment-functions.php`
**Issues**:
- Surcharges calculated on incorrect cart total (includes taxes/shipping inconsistently)
- VIP discounts not properly excluded from surcharge calculation
- Multiple surcharge fees added in rapid cart updates
- Surcharge not removed when payment method changes

#### 1.2 Root Cause Analysis
```php
// PROBLEMATIC CODE - Current Implementation
function apw_woo_add_intuit_surcharge() {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    $chosen_method = WC()->session->get('chosen_payment_method');
    
    if ($chosen_method === 'intuit_qbms_credit_card') {
        $cart_total = WC()->cart->get_subtotal(); // WRONG: Excludes shipping, includes discounts
        $surcharge = $cart_total * 0.03; // HARDCODED RATE
        
        // PROBLEM: Doesn't check if surcharge already exists
        WC()->cart->add_fee('Credit Card Processing Fee', $surcharge);
    }
}
```

**Issues**:
1. **Incorrect Base Amount**: Uses `get_subtotal()` instead of proper calculation base
2. **Hardcoded Rate**: No configuration flexibility
3. **No Deduplication**: Multiple surcharges can be added
4. **VIP Integration Missing**: VIP discounts not considered
5. **No Payment Method Validation**: Doesn't verify gateway is actually active

#### 1.3 Fixed Implementation
```php
<?php
namespace APW\WooPlugin\Services\Payment;

class PaymentSurchargeService {
    private ConfigInterface $config;
    private LoggerServiceInterface $logger;
    private array $applied_surcharges = [];
    
    public function __construct(ConfigInterface $config, LoggerServiceInterface $logger) {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    public function applySurcharge(string $payment_method): void {
        // Prevent duplicate applications
        if ($this->hasSurchargeBeenApplied($payment_method)) {
            return;
        }
        
        // Validate payment method and get rate
        $surcharge_rate = $this->getSurchargeRate($payment_method);
        if ($surcharge_rate <= 0) {
            return;
        }
        
        // Calculate base amount (excluding existing fees and taxes)
        $base_amount = $this->calculateSurchargeBase();
        if ($base_amount <= 0) {
            return;
        }
        
        // Apply VIP discount considerations
        $adjusted_amount = $this->applyVIPConsiderations($base_amount);
        
        // Calculate final surcharge
        $surcharge_amount = $adjusted_amount * $surcharge_rate;
        
        // Apply minimum/maximum limits
        $surcharge_amount = $this->applySurchargeLimits($surcharge_amount);
        
        if ($surcharge_amount > 0) {
            $this->addSurchargeToCart($payment_method, $surcharge_amount);
        }
    }
    
    public function removeSurcharge(string $payment_method): void {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }
        
        $surcharge_name = $this->getSurchargeName($payment_method);
        
        foreach ($cart->get_fees() as $fee_key => $fee) {
            if ($fee->name === $surcharge_name) {
                unset($cart->fees[$fee_key]);
                $this->logger->info("Removed surcharge for payment method: {$payment_method}");
                break;
            }
        }
        
        // Clear applied surcharge tracking
        unset($this->applied_surcharges[$payment_method]);
    }
    
    private function calculateSurchargeBase(): float {
        $cart = WC()->cart;
        if (!$cart) {
            return 0.0;
        }
        
        // Start with cart subtotal
        $base = $cart->get_subtotal();
        
        // Add shipping if configured to include it
        if ($this->config->get('surcharge_includes_shipping', true)) {
            $base += $cart->get_shipping_total();
        }
        
        // Subtract VIP discounts (they should not incur surcharges)
        $vip_discount = $this->getVIPDiscountAmount();
        $base -= $vip_discount;
        
        // Exclude existing surcharge fees from calculation
        foreach ($cart->get_fees() as $fee) {
            if (strpos($fee->name, 'Processing Fee') !== false) {
                continue; // Don't subtract our own surcharges
            }
            if ($fee->amount < 0) {
                // This is a discount, don't subtract it again
                continue;
            }
        }
        
        return max(0, $base);
    }
    
    private function applyVIPConsiderations(float $base_amount): float {
        $customer_id = get_current_user_id();
        
        if (!$customer_id) {
            return $base_amount;
        }
        
        // Check if customer is VIP and has surcharge exemption
        $is_vip = get_user_meta($customer_id, 'is_vip_customer', true);
        $surcharge_exempt = get_user_meta($customer_id, 'vip_surcharge_exempt', true);
        
        if ($is_vip && $surcharge_exempt) {
            $this->logger->info("VIP customer {$customer_id} exempt from surcharges");
            return 0.0;
        }
        
        // Apply VIP surcharge reduction if configured
        $vip_surcharge_reduction = $this->config->get('vip_surcharge_reduction', 0);
        if ($is_vip && $vip_surcharge_reduction > 0) {
            $reduction = min($vip_surcharge_reduction, 1.0); // Cap at 100%
            $base_amount *= (1 - $reduction);
        }
        
        return $base_amount;
    }
    
    private function getSurchargeRate(string $payment_method): float {
        $surcharge_rates = $this->config->get('payment_surcharges', []);
        
        if (!isset($surcharge_rates[$payment_method])) {
            return 0.0;
        }
        
        $rate = (float) $surcharge_rates[$payment_method];
        
        // Validate rate is reasonable (0.1% to 10%)
        if ($rate < 0.001 || $rate > 0.10) {
            $this->logger->warning("Invalid surcharge rate for {$payment_method}: {$rate}");
            return 0.0;
        }
        
        return $rate;
    }
    
    private function applySurchargeLimits(float $amount): float {
        $min_surcharge = $this->config->get('min_surcharge_amount', 0.50);
        $max_surcharge = $this->config->get('max_surcharge_amount', 50.00);
        
        if ($amount < $min_surcharge) {
            return 0.0; // Below minimum, don't apply
        }
        
        return min($amount, $max_surcharge);
    }
    
    private function addSurchargeToCart(string $payment_method, float $amount): void {
        $cart = WC()->cart;
        $surcharge_name = $this->getSurchargeName($payment_method);
        
        $cart->add_fee($surcharge_name, $amount);
        $this->applied_surcharges[$payment_method] = $amount;
        
        $this->logger->info("Applied surcharge", [
            'payment_method' => $payment_method,
            'amount' => $amount,
            'fee_name' => $surcharge_name
        ]);
    }
    
    private function getSurchargeName(string $payment_method): string {
        $names = [
            'intuit_qbms_credit_card' => 'Credit Card Processing Fee',
            'stripe' => 'Card Processing Fee',
            'paypal' => 'PayPal Processing Fee'
        ];
        
        return $names[$payment_method] ?? 'Payment Processing Fee';
    }
    
    private function getVIPDiscountAmount(): float {
        $cart = WC()->cart;
        $vip_discount = 0.0;
        
        foreach ($cart->get_fees() as $fee) {
            if (strpos($fee->name, 'VIP') !== false && $fee->amount < 0) {
                $vip_discount += abs($fee->amount);
            }
        }
        
        return $vip_discount;
    }
    
    private function hasSurchargeBeenApplied(string $payment_method): bool {
        return isset($this->applied_surcharges[$payment_method]);
    }
}
```

### 2. VIP Discount Integration Problems

#### 2.1 Current Issues
**Files**: 
- `includes/apw-woo-dynamic-pricing-functions.php`
- `apw-woo-plugin.php` (main file, lines 450-550)

**Problems**:
- VIP discounts calculated after payment surcharges
- Discount amounts not properly excluded from surcharge base
- VIP status check happens too late in cart calculation
- Manual VIP assignments not properly saved

#### 2.2 VIP Discount Fix
```php
<?php
namespace APW\WooPlugin\Services\Pricing;

class VIPDiscountService {
    private ConfigInterface $config;
    private LoggerServiceInterface $logger;
    private bool $discounts_applied = false;
    
    public function applyVIPDiscounts(): void {
        if ($this->discounts_applied) {
            return; // Prevent double application
        }
        
        $customer_id = $this->getCurrentCustomerId();
        if (!$customer_id) {
            return;
        }
        
        $vip_status = $this->getCustomerVIPStatus($customer_id);
        if (!$vip_status['is_vip']) {
            return;
        }
        
        $cart_total = $this->getCartTotalForVIPCalculation();
        $discount_rate = $this->calculateVIPDiscountRate($vip_status, $cart_total);
        
        if ($discount_rate > 0) {
            $discount_amount = $cart_total * $discount_rate;
            $this->applyDiscountToCart($discount_amount, $vip_status);
        }
        
        $this->discounts_applied = true;
    }
    
    private function getCustomerVIPStatus(int $customer_id): array {
        // Check manual VIP assignment first
        $manual_vip = get_user_meta($customer_id, 'is_vip_customer', true);
        $custom_rate = get_user_meta($customer_id, 'vip_discount_rate', true);
        
        if ($manual_vip) {
            return [
                'is_vip' => true,
                'tier' => 'manual',
                'discount_rate' => $custom_rate ? (float) $custom_rate / 100 : null,
                'source' => 'manual_assignment'
            ];
        }
        
        // Check automatic VIP qualification based on order history
        $automatic_status = $this->checkAutomaticVIPStatus($customer_id);
        
        return $automatic_status;
    }
    
    private function checkAutomaticVIPStatus(int $customer_id): array {
        global $wpdb;
        
        // Get customer order history for last 12 months
        $twelve_months_ago = date('Y-m-d H:i:s', strtotime('-12 months'));
        
        $customer_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(p.ID) as order_count,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_spent
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_customer_user'
            WHERE pm2.meta_value = %d
            AND p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
        ", $customer_id, $twelve_months_ago), ARRAY_A);
        
        if (!$customer_stats) {
            return ['is_vip' => false];
        }
        
        $total_spent = (float) $customer_stats['total_spent'];
        $order_count = (int) $customer_stats['order_count'];
        
        // VIP qualification thresholds
        $vip_thresholds = $this->config->get('vip_qualification_thresholds', [
            'gold' => ['spent' => 5000, 'orders' => 10],
            'silver' => ['spent' => 2500, 'orders' => 5],
            'bronze' => ['spent' => 1000, 'orders' => 3]
        ]);
        
        foreach ($vip_thresholds as $tier => $requirements) {
            if ($total_spent >= $requirements['spent'] && $order_count >= $requirements['orders']) {
                return [
                    'is_vip' => true,
                    'tier' => $tier,
                    'total_spent' => $total_spent,
                    'order_count' => $order_count,
                    'source' => 'automatic_qualification'
                ];
            }
        }
        
        return ['is_vip' => false];
    }
    
    private function calculateVIPDiscountRate(array $vip_status, float $cart_total): float {
        // Manual VIP with custom rate
        if (isset($vip_status['discount_rate']) && $vip_status['discount_rate'] > 0) {
            return min($vip_status['discount_rate'], 0.50); // Cap at 50%
        }
        
        // Automatic VIP based on tier and cart total
        $tier_rates = $this->config->get('vip_tier_rates', [
            'gold' => [
                1000 => 0.15,  // 15% for $1000+
                500 => 0.12,   // 12% for $500+
                0 => 0.10      // 10% for any amount
            ],
            'silver' => [
                500 => 0.10,   // 10% for $500+
                300 => 0.08,   // 8% for $300+
                0 => 0.05      // 5% for any amount
            ],
            'bronze' => [
                300 => 0.08,   // 8% for $300+
                100 => 0.05,   // 5% for $100+
                0 => 0.03      // 3% for any amount
            ]
        ]);
        
        $tier = $vip_status['tier'] ?? 'bronze';
        $rates = $tier_rates[$tier] ?? $tier_rates['bronze'];
        
        // Find applicable rate based on cart total
        krsort($rates); // Sort by threshold descending
        foreach ($rates as $threshold => $rate) {
            if ($cart_total >= $threshold) {
                return $rate;
            }
        }
        
        return 0.0;
    }
    
    private function getCartTotalForVIPCalculation(): float {
        $cart = WC()->cart;
        if (!$cart) {
            return 0.0;
        }
        
        // Use subtotal + shipping, exclude taxes and fees
        $total = $cart->get_subtotal() + $cart->get_shipping_total();
        
        return max(0, $total);
    }
    
    private function applyDiscountToCart(float $discount_amount, array $vip_status): void {
        $cart = WC()->cart;
        $discount_name = $this->getVIPDiscountName($vip_status);
        
        // Remove any existing VIP discounts first
        $this->removeExistingVIPDiscounts();
        
        // Apply new discount as negative fee
        $cart->add_fee($discount_name, -$discount_amount);
        
        $this->logger->info('VIP discount applied', [
            'discount_amount' => $discount_amount,
            'vip_status' => $vip_status,
            'discount_name' => $discount_name
        ]);
        
        // Trigger action for other systems
        do_action('apw_woo_vip_discount_applied', $discount_amount, $vip_status);
    }
    
    private function removeExistingVIPDiscounts(): void {
        $cart = WC()->cart;
        
        foreach ($cart->get_fees() as $fee_key => $fee) {
            if (strpos($fee->name, 'VIP') !== false && $fee->amount < 0) {
                unset($cart->fees[$fee_key]);
            }
        }
    }
    
    private function getVIPDiscountName(array $vip_status): string {
        $tier = $vip_status['tier'] ?? 'member';
        return "VIP " . ucfirst($tier) . " Discount";
    }
    
    private function getCurrentCustomerId(): int {
        if (is_user_logged_in()) {
            return get_current_user_id();
        }
        
        // For guest users, try to get from session if they're in checkout
        $customer_id = WC()->session->get('customer_id');
        return $customer_id ? (int) $customer_id : 0;
    }
}
```

### 3. Cart Calculation Order Fix

#### 3.1 Proper Hook Priority Implementation
```php
<?php
namespace APW\WooPlugin\Integrations\WooCommerce;

class CartCalculationManager {
    public function registerCalculationHooks(): void {
        // Priority order is critical for proper calculation
        
        // 1. FIRST: Apply dynamic pricing (priority 5)
        add_action('woocommerce_before_calculate_totals', 
            [$this, 'applyDynamicPricing'], 5);
        
        // 2. SECOND: Apply VIP discounts (priority 8)
        add_action('woocommerce_before_calculate_totals', 
            [$this, 'applyVIPDiscounts'], 8);
        
        // 3. LAST: Calculate payment surcharges AFTER all discounts (priority 15)
        add_action('woocommerce_cart_calculate_fees', 
            [$this, 'calculatePaymentSurcharges'], 15);
        
        // Handle payment method changes
        add_action('woocommerce_checkout_update_order_review', 
            [$this, 'handlePaymentMethodChange'], 10);
    }
    
    public function applyDynamicPricing($cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $pricing_service = $this->container->make('DynamicPricingServiceInterface');
        $pricing_service->applyDynamicPricing($cart);
    }
    
    public function applyVIPDiscounts($cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $vip_service = $this->container->make('VIPDiscountServiceInterface');
        $vip_service->applyVIPDiscounts();
    }
    
    public function calculatePaymentSurcharges(): void {
        $chosen_method = WC()->session->get('chosen_payment_method');
        
        if (!$chosen_method) {
            return;
        }
        
        // Remove any existing surcharges first
        $surcharge_service = $this->container->make('PaymentSurchargeServiceInterface');
        $surcharge_service->removeAllSurcharges();
        
        // Apply surcharge for current payment method
        $surcharge_service->applySurcharge($chosen_method);
    }
    
    public function handlePaymentMethodChange($post_data): void {
        parse_str($post_data, $data);
        $new_payment_method = $data['payment_method'] ?? '';
        $current_method = WC()->session->get('chosen_payment_method');
        
        if ($new_payment_method !== $current_method) {
            // Clear all surcharges when payment method changes
            $surcharge_service = $this->container->make('PaymentSurchargeServiceInterface');
            $surcharge_service->removeAllSurcharges();
            
            // The new surcharge will be applied on the next cart calculation
            WC()->session->set('chosen_payment_method', $new_payment_method);
        }
    }
}
```

### 4. Configuration Management Fix

#### 4.1 Centralized Payment Configuration
```php
<?php
namespace APW\WooPlugin\Config;

class PaymentConfiguration {
    private const OPTION_KEY = 'apw_payment_settings';
    
    public function getDefaultConfiguration(): array {
        return [
            'payment_surcharges' => [
                'intuit_qbms_credit_card' => 0.03,  // 3%
                'stripe' => 0.029,                   // 2.9%
                'paypal' => 0.035,                   // 3.5%
            ],
            'surcharge_settings' => [
                'includes_shipping' => true,
                'excludes_vip_discounts' => true,
                'min_amount' => 0.50,
                'max_amount' => 50.00,
                'min_order_total' => 10.00
            ],
            'vip_settings' => [
                'qualification_thresholds' => [
                    'gold' => ['spent' => 5000, 'orders' => 10],
                    'silver' => ['spent' => 2500, 'orders' => 5],
                    'bronze' => ['spent' => 1000, 'orders' => 3]
                ],
                'tier_rates' => [
                    'gold' => [1000 => 0.15, 500 => 0.12, 0 => 0.10],
                    'silver' => [500 => 0.10, 300 => 0.08, 0 => 0.05],
                    'bronze' => [300 => 0.08, 100 => 0.05, 0 => 0.03]
                ],
                'surcharge_exemption' => true,
                'surcharge_reduction' => 0.50  // 50% surcharge reduction for VIPs
            ]
        ];
    }
    
    public function saveConfiguration(array $config): bool {
        $validated_config = $this->validateConfiguration($config);
        
        if ($validated_config === false) {
            return false;
        }
        
        return update_option(self::OPTION_KEY, $validated_config);
    }
    
    public function getConfiguration(): array {
        $saved_config = get_option(self::OPTION_KEY, []);
        $default_config = $this->getDefaultConfiguration();
        
        return wp_parse_args($saved_config, $default_config);
    }
    
    private function validateConfiguration(array $config): array|false {
        $validated = [];
        
        // Validate surcharge rates
        if (isset($config['payment_surcharges'])) {
            foreach ($config['payment_surcharges'] as $method => $rate) {
                $rate = (float) $rate;
                if ($rate >= 0 && $rate <= 0.10) { // 0% to 10%
                    $validated['payment_surcharges'][sanitize_key($method)] = $rate;
                }
            }
        }
        
        // Validate surcharge settings
        if (isset($config['surcharge_settings'])) {
            $settings = $config['surcharge_settings'];
            $validated['surcharge_settings'] = [
                'includes_shipping' => (bool) ($settings['includes_shipping'] ?? true),
                'excludes_vip_discounts' => (bool) ($settings['excludes_vip_discounts'] ?? true),
                'min_amount' => max(0, (float) ($settings['min_amount'] ?? 0.50)),
                'max_amount' => max(1, (float) ($settings['max_amount'] ?? 50.00)),
                'min_order_total' => max(0, (float) ($settings['min_order_total'] ?? 10.00))
            ];
        }
        
        return $validated;
    }
}
```

## Testing Critical Fixes

### 5. Comprehensive Test Scenarios

#### 5.1 Payment Surcharge Tests
```php
<?php
namespace APW\WooPlugin\Tests\Critical;

class PaymentSurchargeTest extends TestCase {
    public function testSurchargeCalculationWithVIPCustomer(): void {
        // Setup VIP customer
        $customer_id = $this->createVIPCustomer(['tier' => 'gold']);
        wp_set_current_user($customer_id);
        
        // Add products to cart
        $cart = WC()->cart;
        $cart->add_to_cart($this->createProduct(['price' => 100]), 1);
        
        // Apply VIP discount first
        $vip_service = new VIPDiscountService($this->config, $this->logger);
        $vip_service->applyVIPDiscounts();
        
        // Get cart total after VIP discount
        $cart_total = $cart->get_subtotal();
        $vip_discount = $this->getVIPDiscountAmount($cart);
        $expected_surcharge_base = $cart_total - $vip_discount;
        
        // Apply surcharge
        $surcharge_service = new PaymentSurchargeService($this->config, $this->logger);
        $surcharge_service->applySurcharge('intuit_qbms_credit_card');
        
        // Verify surcharge calculated on correct amount
        $surcharge_amount = $this->getSurchargeAmount($cart);
        $expected_surcharge = $expected_surcharge_base * 0.03; // 3% rate
        
        $this->assertEquals($expected_surcharge, $surcharge_amount, 'Surcharge should exclude VIP discount');
    }
    
    public function testMultipleSurchargesPrevented(): void {
        $cart = WC()->cart;
        $cart->add_to_cart($this->createProduct(['price' => 100]), 1);
        
        $surcharge_service = new PaymentSurchargeService($this->config, $this->logger);
        
        // Apply surcharge twice
        $surcharge_service->applySurcharge('intuit_qbms_credit_card');
        $surcharge_service->applySurcharge('intuit_qbms_credit_card');
        
        // Should only have one surcharge fee
        $surcharge_fees = array_filter($cart->get_fees(), function($fee) {
            return strpos($fee->name, 'Processing Fee') !== false;
        });
        
        $this->assertCount(1, $surcharge_fees, 'Should only have one surcharge fee');
    }
    
    public function testPaymentMethodChangeClearsSurcharge(): void {
        $cart = WC()->cart;
        $cart->add_to_cart($this->createProduct(['price' => 100]), 1);
        
        $surcharge_service = new PaymentSurchargeService($this->config, $this->logger);
        
        // Apply surcharge for credit card
        $surcharge_service->applySurcharge('intuit_qbms_credit_card');
        $this->assertGreaterThan(0, $this->getSurchargeAmount($cart));
        
        // Remove surcharge when changing to different method
        $surcharge_service->removeSurcharge('intuit_qbms_credit_card');
        $this->assertEquals(0, $this->getSurchargeAmount($cart));
    }
}
```

## Emergency Deployment Plan

### 6. Hotfix Implementation Strategy

#### 6.1 Immediate Fixes (Priority 1 - Deploy within 24 hours)
1. **Fix surcharge calculation base amount**
2. **Prevent duplicate surcharge applications**
3. **Fix VIP discount exclusion from surcharges**

#### 6.2 Medium-term Fixes (Priority 2 - Deploy within 1 week)
1. **Implement proper hook priorities**
2. **Add configuration management interface**
3. **Comprehensive testing suite**

#### 6.3 Deployment Checklist
- [ ] **Backup current plugin code**
- [ ] **Test fixes on staging environment**
- [ ] **Verify VIP customer calculations**
- [ ] **Test payment method changes**
- [ ] **Validate surcharge limits and minimums**
- [ ] **Test with multiple cart scenarios**
- [ ] **Monitor error logs after deployment**

## Success Criteria

### 7. Critical Issue Resolution Metrics
- [ ] **Surcharge Accuracy**: 100% correct surcharge calculations
- [ ] **VIP Integration**: Proper VIP discount exclusion from surcharges
- [ ] **No Duplicate Fees**: Zero instances of duplicate surcharge application
- [ ] **Payment Method Changes**: Clean surcharge removal/application
- [ ] **Configuration Flexibility**: Admin can modify all rates and settings
- [ ] **Error Rate**: <0.1% payment processing errors
- [ ] **Customer Satisfaction**: Resolution of VIP customer complaints

### 8. Monitoring and Alerts
- **Real-time surcharge calculation monitoring**
- **VIP discount application tracking**
- **Payment method change event logging**
- **Error rate alerts for payment processing**
- **Revenue impact analysis and reporting**

## Next Steps
Upon completion:
1. Proceed to Critical Architecture Problems specification
2. Implement comprehensive payment processing tests
3. Create payment configuration admin interface
4. Establish payment processing monitoring and alerting