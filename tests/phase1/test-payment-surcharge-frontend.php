<?php
/**
 * Frontend HTML test for payment surcharge bug fix
 * Tests Product #80 scenario with Frederick, MD shipping
 * 
 * @group phase1
 * @group payment
 */

class Test_Payment_Surcharge_Frontend extends WP_UnitTestCase {
    
    private $product_id;
    private $customer_id;
    
    public function setUp(): void {
        parent::setUp();
        
        // Check if WooCommerce is available
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce not available for testing');
        }
        
        // Initialize WooCommerce
        WC()->init();
        
        // Create test product #80
        $this->product_id = $this->create_test_product_80();
        
        // Create VIP customer
        $this->customer_id = $this->create_vip_customer();
        
        // Clear cart
        WC()->cart->empty_cart();
        
        // Set up session for testing
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
    }
    
    public function tearDown(): void {
        // Clean up
        if ($this->product_id) {
            wp_delete_post($this->product_id, true);
        }
        if ($this->customer_id) {
            wp_delete_user($this->customer_id);
        }
        
        WC()->cart->empty_cart();
        parent::tearDown();
    }
    
    /**
     * Create test product #80 for testing
     */
    private function create_test_product_80() {
        $product = new WC_Product_Simple();
        $product->set_name('Test Product #80');
        $product->set_regular_price(109.00); // $545 / 5 = $109 per item
        $product->set_manage_stock(true);
        $product->set_stock_quantity(100);
        $product->save();
        
        return $product->get_id();
    }
    
    /**
     * Create VIP customer for testing
     */
    private function create_vip_customer() {
        $customer_id = wp_create_user('vip_test_user', 'password123', 'vip@test.com');
        
        // Set customer spending above VIP threshold
        update_user_meta($customer_id, '_money_spent', 300.00);
        
        return $customer_id;
    }
    
    /**
     * Simulate adding VIP discount to cart
     */
    private function apply_vip_discount($amount = 50.00) {
        WC()->cart->add_fee('VIP Discount (10%)', -$amount, false);
    }
    
    /**
     * Simulate shipping to Frederick, MD
     */
    private function set_frederick_shipping() {
        // Set customer shipping address
        WC()->customer->set_shipping_address('2519 Mill Race Road');
        WC()->customer->set_shipping_city('Frederick');
        WC()->customer->set_shipping_state('MD');
        WC()->customer->set_shipping_postcode('21701');
        WC()->customer->set_shipping_country('US');
        
        // Add shipping fee manually for testing (normally calculated by shipping method)
        WC()->cart->add_fee('Ground Shipping', 26.26, true);
    }
    
    /**
     * Test the critical Product #80 scenario with frontend output
     */
    public function test_product_80_surcharge_frontend_display() {
        echo "\n=== FRONTEND TEST: Product #80 Scenario ===\n";
        
        // Step 1: Add Product #80, quantity 5 to cart
        $added = WC()->cart->add_to_cart($this->product_id, 5);
        $this->assertTrue($added !== false, 'Should be able to add product to cart');
        
        // Step 2: Apply VIP discount
        $this->apply_vip_discount(50.00);
        
        // Step 3: Set Frederick, MD shipping
        $this->set_frederick_shipping();
        
        // Step 4: Set payment method to credit card
        WC()->session->set('chosen_payment_method', 'intuit_payments_credit_card');
        
        // Step 5: Calculate cart totals (this triggers surcharge calculation)
        WC()->cart->calculate_totals();
        
        // Step 6: Get cart totals for verification
        $cart_totals = WC()->cart->get_totals();
        $subtotal = $cart_totals['subtotal'];
        $shipping_total = $cart_totals['shipping_total'];
        
        echo "Cart Contents:\n";
        echo "- Product #80 x 5: $" . number_format($subtotal, 2) . "\n";
        echo "- Ground Shipping to Frederick, MD: $" . number_format($shipping_total, 2) . "\n";
        
        // Step 7: Check fees (VIP discount and surcharge)
        $fees = WC()->cart->get_fees();
        $vip_discount_amount = 0;
        $surcharge_amount = 0;
        $surcharge_fee_name = '';
        
        foreach ($fees as $fee) {
            echo "- Fee: " . $fee->name . " = $" . number_format($fee->amount, 2) . "\n";
            
            if (strpos($fee->name, 'VIP Discount') !== false) {
                $vip_discount_amount = abs($fee->amount);
            }
            if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
                $surcharge_amount = $fee->amount;
                $surcharge_fee_name = $fee->name;
            }
        }
        
        // Step 8: Verify calculations
        echo "\nCalculation Verification:\n";
        echo "- Subtotal: $" . number_format($subtotal, 2) . "\n";
        echo "- Shipping: $" . number_format($shipping_total, 2) . "\n";
        echo "- VIP Discount: $" . number_format($vip_discount_amount, 2) . "\n";
        
        $expected_base = $subtotal + $shipping_total - $vip_discount_amount;
        $expected_surcharge = $expected_base * 0.03;
        
        echo "- Expected Base: $" . number_format($expected_base, 2) . "\n";
        echo "- Expected Surcharge: $" . number_format($expected_surcharge, 2) . "\n";
        echo "- Actual Surcharge: $" . number_format($surcharge_amount, 2) . "\n";
        
        // Step 9: Assertions
        $this->assertEquals(545.00, $subtotal, 'Subtotal should be $545.00 (5 × $109)', 0.01);
        $this->assertEquals(26.26, $shipping_total, 'Shipping should be $26.26', 0.01);
        $this->assertEquals(50.00, $vip_discount_amount, 'VIP discount should be $50.00', 0.01);
        $this->assertEquals(15.64, $surcharge_amount, 'Surcharge should be $15.64, not $17.14', 0.01);
        
        // Step 10: Simulate frontend HTML output
        echo "\n=== FRONTEND HTML OUTPUT ===\n";
        $this->simulate_checkout_totals_html($cart_totals, $fees);
        
        // Final verification
        if (abs($surcharge_amount - 15.64) < 0.01) {
            echo "\n✅ SUCCESS: Bug is FIXED! Surcharge shows $15.64 (not $17.14)\n";
        } else {
            echo "\n❌ FAILURE: Bug still exists. Surcharge shows $" . number_format($surcharge_amount, 2) . " instead of $15.64\n";
        }
    }
    
    /**
     * Simulate how the surcharge appears in checkout HTML
     */
    private function simulate_checkout_totals_html($cart_totals, $fees) {
        echo "<table class=\"shop_table woocommerce-checkout-review-order-table\">\n";
        echo "  <tbody>\n";
        echo "    <tr class=\"cart-subtotal\">\n";
        echo "      <th>Subtotal</th>\n";
        echo "      <td><span class=\"woocommerce-Price-amount\">$" . number_format($cart_totals['subtotal'], 2) . "</span></td>\n";
        echo "    </tr>\n";
        
        // Display fees
        foreach ($fees as $fee) {
            $fee_class = $fee->amount < 0 ? 'discount-fee' : 'additional-fee';
            echo "    <tr class=\"fee-{$fee_class}\">\n";
            echo "      <th>" . esc_html($fee->name) . "</th>\n";
            echo "      <td><span class=\"woocommerce-Price-amount\">$" . number_format($fee->amount, 2) . "</span></td>\n";
            echo "    </tr>\n";
        }
        
        echo "    <tr class=\"shipping\">\n";
        echo "      <th>Shipping</th>\n";
        echo "      <td><span class=\"woocommerce-Price-amount\">$" . number_format($cart_totals['shipping_total'], 2) . "</span></td>\n";
        echo "    </tr>\n";
        
        echo "    <tr class=\"order-total\">\n";
        echo "      <th>Total</th>\n";
        echo "      <td><strong><span class=\"woocommerce-Price-amount\">$" . number_format($cart_totals['total'], 2) . "</span></strong></td>\n";
        echo "    </tr>\n";
        echo "  </tbody>\n";
        echo "</table>\n";
    }
    
    /**
     * Test payment method switching removes surcharge
     */
    public function test_payment_method_switching_removes_surcharge() {
        echo "\n=== FRONTEND TEST: Payment Method Switching ===\n";
        
        // Set up cart with credit card surcharge
        WC()->cart->add_to_cart($this->product_id, 5);
        $this->apply_vip_discount(50.00);
        $this->set_frederick_shipping();
        WC()->session->set('chosen_payment_method', 'intuit_payments_credit_card');
        WC()->cart->calculate_totals();
        
        // Verify surcharge exists
        $fees_with_cc = WC()->cart->get_fees();
        $surcharge_exists = false;
        foreach ($fees_with_cc as $fee) {
            if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
                $surcharge_exists = true;
                echo "✓ Surcharge exists with credit card: $" . number_format($fee->amount, 2) . "\n";
                break;
            }
        }
        $this->assertTrue($surcharge_exists, 'Surcharge should exist with credit card payment');
        
        // Switch to bank transfer
        WC()->session->set('chosen_payment_method', 'bacs');
        WC()->cart->calculate_totals();
        
        // Verify surcharge is removed
        $fees_without_cc = WC()->cart->get_fees();
        $surcharge_exists_after = false;
        foreach ($fees_without_cc as $fee) {
            if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
                $surcharge_exists_after = true;
                break;
            }
        }
        $this->assertFalse($surcharge_exists_after, 'Surcharge should be removed with non-credit-card payment');
        
        echo "✓ Surcharge correctly removed when switching to bank transfer\n";
    }
}
?>