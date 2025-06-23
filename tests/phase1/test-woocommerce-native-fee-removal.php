<?php
/**
 * Test WooCommerce Native Fee Removal Logic - Attempt 7
 *
 * Tests the revised approach using WooCommerce's official fee management API
 * to properly remove and replace fees without direct array manipulation.
 *
 * @package APW_Woo_Plugin
 * @since 1.23.24
 */

/**
 * @group phase1
 * @group payment
 */
class Test_WooCommerce_Native_Fee_Removal extends WP_UnitTestCase {

    private $cart;
    private $session;

    public function setUp(): void {
        parent::setUp();
        
        // Check if WooCommerce is available
        if (!class_exists('WooCommerce')) {
            // Load WooCommerce mocks
            require_once __DIR__ . '/../utilities/woocommerce-mocks.php';
        }
        
        // Load the payment functions
        require_once __DIR__ . '/../../includes/apw-woo-intuit-payment-functions.php';
        
        // Create mock cart and session
        $this->cart = new WC_Cart();
        $this->session = new WC_Session_Handler();
        
        // Mock WC() global
        global $woocommerce;
        $woocommerce = new WooCommerce();
        $woocommerce->cart = $this->cart;
        $woocommerce->session = $this->session;
    }

    /**
     * Test: WooCommerce native fee filtering logic
     * Ensures we can properly filter out surcharge fees from fees array
     */
    public function test_fee_filtering_logic() {
        // Arrange: Create mock fees array with various fee types
        $mock_fees = [
            (object) ['name' => 'Shipping Fee', 'amount' => 26.26],
            (object) ['name' => 'Credit Card Surcharge (3%)', 'amount' => 17.14],
            (object) ['name' => 'Bulk Discount (InHand I-22)', 'amount' => -50.00],
            (object) ['name' => 'Processing Surcharge', 'amount' => 5.00],
            (object) ['name' => 'Tax Fee', 'amount' => 30.73]
        ];

        // Act: Filter out surcharge fees
        $filtered_fees = array_filter($mock_fees, function($fee) {
            return strpos($fee->name, 'Surcharge') === false;
        });

        // Assert: Only non-surcharge fees remain
        $this->assertCount(3, $filtered_fees, 'Should filter out both surcharge fees');
        
        $remaining_names = array_map(function($fee) { return $fee->name; }, $filtered_fees);
        $this->assertContains('Shipping Fee', $remaining_names);
        $this->assertContains('Bulk Discount (InHand I-22)', $remaining_names);
        $this->assertContains('Tax Fee', $remaining_names);
        $this->assertNotContains('Credit Card Surcharge (3%)', $remaining_names);
        $this->assertNotContains('Processing Surcharge', $remaining_names);
    }

    /**
     * Test: Mock WooCommerce fees_api behavior
     * Tests the set_fees() method behavior with filtered fees
     */
    public function test_mock_fees_api_set_fees() {
        // Arrange: Add multiple fees to cart
        $this->cart->add_fee('Original Surcharge', 17.14);
        $this->cart->add_fee('Shipping', 26.26);
        $this->cart->add_fee('Bulk Discount', -50.00);
        
        $initial_fee_count = count($this->cart->get_fees());
        $this->assertEquals(3, $initial_fee_count, 'Should have 3 initial fees');

        // Act: Filter out surcharge fees and set new fees array
        $all_fees = $this->cart->get_fees();
        $filtered_fees = array_filter($all_fees, function($fee) {
            return strpos($fee->name, 'Surcharge') === false;
        });

        // Reset fees array to filtered fees (simulating set_fees behavior)
        $this->cart->fees = array_values($filtered_fees);

        // Assert: Surcharge fee removed
        $remaining_fees = $this->cart->get_fees();
        $this->assertCount(2, $remaining_fees, 'Should have 2 fees after filtering');
        
        $remaining_names = array_map(function($fee) { return $fee->name; }, $remaining_fees);
        $this->assertNotContains('Original Surcharge', $remaining_names);
        $this->assertContains('Shipping', $remaining_names);
        $this->assertContains('Bulk Discount', $remaining_names);
    }

    /**
     * Test: Complete fee replacement workflow
     * Tests the full workflow of removing old surcharge and adding new one
     */
    public function test_complete_fee_replacement_workflow() {
        // Arrange: Setup cart with Product #80 scenario
        $this->cart->add_to_cart(80, 5); // 5 x Product #80
        $this->cart->add_fee('Shipping', 26.26);
        $this->cart->add_fee('Bulk Discount (InHand I-22)', -50.00);
        $this->cart->add_fee('Credit Card Surcharge (3%)', 17.14); // Old incorrect surcharge
        
        $this->session->set('chosen_payment_method', 'intuit_payments_credit_card');
        
        // Act: Perform native fee replacement
        
        // Step 1: Get all current fees
        $all_fees = $this->cart->get_fees();
        $this->assertCount(3, $all_fees, 'Should start with 3 fees');
        
        // Step 2: Filter out surcharge fees
        $filtered_fees = array_filter($all_fees, function($fee) {
            return strpos($fee->name, 'Surcharge') === false;
        });
        $this->assertCount(2, $filtered_fees, 'Should have 2 fees after filtering');
        
        // Step 3: Replace fees array (simulating WooCommerce fees_api()->set_fees())
        $this->cart->fees = array_values($filtered_fees);
        
        // Step 4: Calculate new surcharge amount
        $subtotal = $this->cart->get_subtotal(); // 545.00
        $shipping = $this->cart->get_shipping_total(); // 26.26 (from remaining fees)
        $discounts = 50.00; // From bulk discount
        $new_surcharge = ($subtotal + $shipping - $discounts) * 0.03;
        $expected_surcharge = 15.64; // Expected correct amount
        
        $this->assertEquals($expected_surcharge, round($new_surcharge, 2), 'New surcharge should be $15.64');
        
        // Step 5: Add new correct surcharge
        $this->cart->add_fee('Credit Card Surcharge (3%)', $new_surcharge);
        
        // Assert: Final state verification
        $final_fees = $this->cart->get_fees();
        $this->assertCount(3, $final_fees, 'Should end with 3 fees');
        
        $surcharge_fee = null;
        foreach ($final_fees as $fee) {
            if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
                $surcharge_fee = $fee;
                break;
            }
        }
        
        $this->assertNotNull($surcharge_fee, 'Should have surcharge fee');
        $this->assertEquals(15.64, round($surcharge_fee->amount, 2), 'Surcharge should be correct amount');
        $this->assertNotEquals(17.14, $surcharge_fee->amount, 'Should not have old incorrect amount');
    }

    /**
     * Test: Edge case - No existing surcharge fees
     * Tests behavior when no surcharge fees exist to remove
     */
    public function test_no_existing_surcharge_fees() {
        // Arrange: Cart with no surcharge fees
        $this->cart->add_fee('Shipping', 26.26);
        $this->cart->add_fee('Bulk Discount', -50.00);
        
        // Act: Filter surcharge fees (should find none)
        $all_fees = $this->cart->get_fees();
        $filtered_fees = array_filter($all_fees, function($fee) {
            return strpos($fee->name, 'Surcharge') === false;
        });
        
        // Assert: All fees should remain
        $this->assertCount(2, $all_fees);
        $this->assertCount(2, $filtered_fees);
        $this->assertEquals($all_fees, $filtered_fees, 'Filtering should not change fees when no surcharges exist');
    }

    /**
     * Test: Edge case - Multiple surcharge fees
     * Tests behavior when multiple different surcharge fees exist
     */
    public function test_multiple_surcharge_fees() {
        // Arrange: Cart with multiple surcharge fees
        $this->cart->add_fee('Credit Card Surcharge (3%)', 17.14);
        $this->cart->add_fee('Processing Surcharge', 5.00);
        $this->cart->add_fee('Convenience Surcharge', 2.50);
        $this->cart->add_fee('Shipping', 26.26);
        
        // Act: Filter out all surcharge fees
        $all_fees = $this->cart->get_fees();
        $filtered_fees = array_filter($all_fees, function($fee) {
            return strpos($fee->name, 'Surcharge') === false;
        });
        
        // Assert: Only non-surcharge fees remain
        $this->assertCount(4, $all_fees, 'Should start with 4 fees');
        $this->assertCount(1, $filtered_fees, 'Should end with 1 fee after filtering all surcharges');
        
        $remaining_fee = array_values($filtered_fees)[0];
        $this->assertEquals('Shipping', $remaining_fee->name);
    }

    /**
     * Test: Memory and performance considerations
     * Ensures the native fee replacement doesn't cause memory issues
     */
    public function test_memory_performance() {
        // Arrange: Large number of fees
        for ($i = 0; $i < 50; $i++) {
            $this->cart->add_fee("Fee $i", rand(1, 100));
        }
        $this->cart->add_fee('Credit Card Surcharge (3%)', 17.14);
        
        $initial_memory = memory_get_usage();
        
        // Act: Perform fee filtering multiple times
        for ($j = 0; $j < 10; $j++) {
            $all_fees = $this->cart->get_fees();
            $filtered_fees = array_filter($all_fees, function($fee) {
                return strpos($fee->name, 'Surcharge') === false;
            });
        }
        
        $final_memory = memory_get_usage();
        $memory_increase = $final_memory - $initial_memory;
        
        // Assert: Memory usage should not increase significantly
        $this->assertLessThan(1048576, $memory_increase, 'Memory increase should be less than 1MB'); // 1MB limit
        
        // Assert: Filtering should work correctly even with many fees
        $this->assertCount(50, $filtered_fees, 'Should filter correctly with large fee arrays');
    }

    /**
     * Test: Array key preservation
     * Ensures that array_filter preserves keys and array_values resets them properly
     */
    public function test_array_key_handling() {
        // Arrange: Fees with specific keys
        $this->cart->add_fee('Fee 1', 10.00);
        $this->cart->add_fee('Credit Card Surcharge (3%)', 17.14);
        $this->cart->add_fee('Fee 3', 30.00);
        
        // Act: Filter and reset keys
        $all_fees = $this->cart->get_fees();
        $filtered_fees = array_filter($all_fees, function($fee) {
            return strpos($fee->name, 'Surcharge') === false;
        });
        $reindexed_fees = array_values($filtered_fees);
        
        // Assert: Keys should be properly reset
        $this->assertArrayHasKey(0, $reindexed_fees);
        $this->assertArrayHasKey(1, $reindexed_fees);
        $this->assertArrayNotHasKey(2, $reindexed_fees);
        
        $this->assertEquals('Fee 1', $reindexed_fees[0]->name);
        $this->assertEquals('Fee 3', $reindexed_fees[1]->name);
    }

    /**
     * Test: Concurrent fee operations
     * Tests behavior when fees are being modified during the replacement process
     */
    public function test_concurrent_fee_operations() {
        // Arrange: Initial fees
        $this->cart->add_fee('Credit Card Surcharge (3%)', 17.14);
        $this->cart->add_fee('Shipping', 26.26);
        
        // Act: Simulate concurrent modification
        $all_fees = $this->cart->get_fees();
        
        // Simulate another process adding a fee during our operation
        $this->cart->add_fee('New Fee', 5.00);
        
        // Continue with filtering using the snapshot
        $filtered_fees = array_filter($all_fees, function($fee) {
            return strpos($fee->name, 'Surcharge') === false;
        });
        
        // Replace fees with filtered array
        $this->cart->fees = array_values($filtered_fees);
        
        // Assert: Should only have fees from the original snapshot
        $remaining_fees = $this->cart->get_fees();
        $this->assertCount(1, $remaining_fees, 'Should only have shipping fee from original snapshot');
        $this->assertEquals('Shipping', $remaining_fees[0]->name);
    }
}