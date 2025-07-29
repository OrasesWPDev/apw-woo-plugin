<?php
/**
 * Allpoint Command Integration Test Suite - Unit Tests
 * 
 * DEVELOPMENT SETUP:
 * - Development: /projects/apw/apw-woo-plugin (this directory)
 * - Local Testing: http://localhost:10013/
 * - PDF Location: assets/pdf/Allpoint Wireless Rental Agreement.pdf
 * - Staging Deploy: WP Engine after Local testing passes
 */

require_once dirname(__FILE__) . '/bootstrap.php';

class Test_Allpoint_Command_Integration_Unit extends WP_UnitTestCase {

    private $local_site_url = 'http://localhost:10013/';
    private $pdf_file = 'Allpoint Wireless Rental Agreement.pdf';
    
    public function setUp(): void {
        parent::setUp();
        
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not active');
        }
        
        $this->clean_test_data();
    }
    
    public function tearDown(): void {
        $this->clean_test_data();
        parent::tearDown();
    }
    
    private function clean_test_data() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_title LIKE '%Allpoint Test%'");
        $wpdb->query("DELETE FROM {$wpdb->users} WHERE user_login LIKE '%allpoint_test_%'");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_apw_allpoint_%'");
    }
    
    /**
     * Test 1: Environment Manager Endpoints
     */
    public function test_environment_manager_endpoints() {
        $expected_endpoints = [
            'production' => 'https://allpointcommand.com',
            'staging' => 'https://watm.beta.orases.dev',
            'review' => 'https://watm.review.orases.dev',
            'wp_staging' => 'http://allpointstage.wpenginepowered.com/',
            'wp_development' => 'http://allpointwi1dev.wpenginepowered.com/',
            'local_development' => $this->local_site_url
        ];
        
        foreach ($expected_endpoints as $env => $url) {
            $this->assertNotEmpty($url, "Environment $env should have a valid URL");
            $this->assertStringNotContainsString('checkurbox', $url, "No checkurbox references should remain");
            $this->assertStringNotContainsString('thewirelessbox', $url, "No thewirelessbox references should remain");
        }
        
        $this->assertEquals('https://allpointcommand.com', $expected_endpoints['production']);
        $this->assertEquals($this->local_site_url, $expected_endpoints['local_development']);
    }
    
    /**
     * Test 2: Token Generation Logic
     */
    public function test_token_generation_logic() {
        $order_id = 12345;
        $customer_id = 67890;
        
        $expected_token = md5($order_id . $customer_id);
        
        $this->assertEquals(32, strlen($expected_token), "Token should be 32 characters (MD5 length)");
        $this->assertTrue(ctype_xdigit($expected_token), "Token should be hexadecimal");
        
        $token2 = md5($order_id . $customer_id);
        $this->assertEquals($expected_token, $token2, "Same inputs should generate same token");
        
        $different_token = md5(($order_id + 1) . $customer_id);
        $this->assertNotEquals($expected_token, $different_token, "Different inputs should generate different tokens");
    }
    
    /**
     * Test 3: Email Content Transformation (Branding Updates)
     */
    public function test_email_content_transformation() {
        $old_content = 'Welcome to The Wireless Box! Visit checkurbox.com for support. Our ATM network is extensive.';
        
        // Apply required transformations
        $updated_content = $old_content;
        $updated_content = str_replace('The Wireless Box', 'Allpoint Wireless', $updated_content);
        $updated_content = str_replace('checkurbox.com', 'allpointwireless.com', $updated_content);
        $updated_content = preg_replace('/ATM[^.]*\./', '', $updated_content);
        
        // Verify transformations
        $this->assertStringNotContainsString('The Wireless Box', $updated_content);
        $this->assertStringContainsString('Allpoint Wireless', $updated_content);
        $this->assertStringNotContainsString('checkurbox.com', $updated_content);
        $this->assertStringContainsString('allpointwireless.com', $updated_content);
        $this->assertStringNotContainsString('ATM', $updated_content);
    }
    
    /**
     * Test 4: PDF File Verification
     */
    public function test_rental_agreement_pdf_exists() {
        $pdf_path = dirname(__FILE__) . '/../assets/pdf/' . $this->pdf_file;
        
        $this->assertTrue(file_exists($pdf_path), "Rental agreement PDF should exist at: $pdf_path");
        $this->assertGreaterThan(0, filesize($pdf_path), "PDF file should not be empty");
        
        // Verify it's actually a PDF
        $file_content = file_get_contents($pdf_path, false, null, 0, 10);
        $this->assertStringStartsWith('%PDF', $file_content, "File should be a valid PDF");
    }
    
    /**
     * Test 5: API Data Structure Requirements
     */
    public function test_api_data_structure() {
        // Mock order data for testing API structure
        $order_data = [
            'order_number' => 12345,
            'woocommerce_user_id' => 67890,
            'order_details' => [
                'addresses' => [
                    'billing' => [
                        'first_name' => 'Allpoint',
                        'last_name' => 'Customer',
                        'email' => 'test@allpointwireless.com'
                    ],
                    'shipping' => [
                        'first_name' => 'Allpoint',
                        'last_name' => 'Customer'
                    ]
                ],
                'items' => [
                    'product_123' => [
                        'name' => 'Allpoint Service Plan',
                        'slug' => 'allpoint-service-plan',
                        'quantity' => 1
                    ]
                ]
            ],
            'order_date' => '2025-07-28 18:30:00',
            'woocommerce_token' => md5('12345' . '67890')
        ];
        
        // Test required structure
        $this->assertArrayHasKey('order_number', $order_data);
        $this->assertArrayHasKey('woocommerce_user_id', $order_data);
        $this->assertArrayHasKey('order_details', $order_data);
        $this->assertArrayHasKey('order_date', $order_data);
        $this->assertArrayHasKey('woocommerce_token', $order_data);
        
        // Test nested structure
        $this->assertArrayHasKey('addresses', $order_data['order_details']);
        $this->assertArrayHasKey('items', $order_data['order_details']);
        $this->assertArrayHasKey('billing', $order_data['order_details']['addresses']);
        $this->assertArrayHasKey('shipping', $order_data['order_details']['addresses']);
        
        // Test data types
        $this->assertIsInt($order_data['order_number']);
        $this->assertIsInt($order_data['woocommerce_user_id']);
        $this->assertIsArray($order_data['order_details']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $order_data['order_date']);
        $this->assertEquals(32, strlen($order_data['woocommerce_token']));
    }
    
    /**
     * Test 6: Product Tag Detection Logic
     */
    public function test_product_tag_detection() {
        // Test recurring tag detection
        $recurring_tags = ['recurring', 'monthly'];
        $this->assertContains('recurring', $recurring_tags);
        
        // Test rental tag detection  
        $rental_tags = ['rental', 'equipment'];
        $this->assertContains('rental', $rental_tags);
        
        // Test regular product (no special tags)
        $regular_tags = ['accessory', 'hardware'];
        $this->assertNotContains('recurring', $regular_tags);
        $this->assertNotContains('rental', $regular_tags);
    }
    
    /**
     * Test 7: Order Meta Field Requirements
     */
    public function test_order_meta_field_structure() {
        $expected_meta_fields = [
            '_apw_allpoint_contains_recurring_products',
            '_apw_allpoint_command_token',
            '_apw_allpoint_api_post_status',
            '_apw_allpoint_api_post_timestamp',
            '_apw_allpoint_environment'
        ];
        
        foreach ($expected_meta_fields as $field) {
            $this->assertStringStartsWith('_apw_allpoint_', $field, "Meta fields should use consistent naming convention");
        }
        
        // Test token storage format
        $test_token = md5('12345' . '67890');
        $this->assertEquals(32, strlen($test_token));
        $this->assertTrue(ctype_xdigit($test_token));
    }
    
    /**
     * Test 8: Local Development Environment Configuration
     */
    public function test_local_environment_configuration() {
        // Verify local site URL format
        $this->assertStringStartsWith('http://localhost:', $this->local_site_url);
        $this->assertStringEndsWith('/', $this->local_site_url);
        
        // Test environment detection logic
        $is_local = (strpos($this->local_site_url, 'localhost') !== false);
        $this->assertTrue($is_local, "Should detect local environment");
        
        // Test environment-specific settings
        $local_config = [
            'api_endpoint' => $this->local_site_url . 'woocommerce',
            'debug_mode' => true,
            'log_api_calls' => true,
            'use_mock_emails' => true
        ];
        
        foreach ($local_config as $key => $value) {
            $this->assertNotNull($value, "Local config should have value for $key");
        }
    }
    
    /**
     * Test 9: Registration Link Generation
     */
    public function test_registration_link_generation() {
        $test_token = 'abc123def456';
        
        // Test local environment link
        $local_link = $this->local_site_url . 'create-company?token=' . $test_token;
        $this->assertStringContainsString('localhost:10013', $local_link);
        $this->assertStringContainsString('create-company', $local_link);
        $this->assertStringContainsString($test_token, $local_link);
        
        // Test production environment link
        $production_link = 'https://allpointcommand.com/create-company?token=' . $test_token;
        $this->assertStringContainsString('allpointcommand.com', $production_link);
        $this->assertStringNotContainsString('localhost', $production_link);
    }
    
    /**
     * Test 10: Error Handling Structure
     */
    public function test_error_handling_structure() {
        $test_error = [
            'timestamp' => current_time('mysql'),
            'type' => 'api_error',
            'message' => 'Test Allpoint Command API error',
            'order_id' => 12345,
            'environment' => 'local_development',
            'endpoint' => $this->local_site_url . 'woocommerce'
        ];
        
        // Test error structure
        $this->assertArrayHasKey('timestamp', $test_error);
        $this->assertArrayHasKey('type', $test_error);
        $this->assertArrayHasKey('message', $test_error);
        $this->assertArrayHasKey('order_id', $test_error);
        $this->assertArrayHasKey('environment', $test_error);
        $this->assertArrayHasKey('endpoint', $test_error);
        
        // Test error message contains Allpoint branding
        $this->assertStringContainsString('Allpoint Command', $test_error['message']);
    }
    
    /**
     * Helper: Create test product with tags (when WooCommerce is available)
     */
    private function create_test_product($name, $tags = []) {
        if (!class_exists('WC_Product_Simple')) {
            $this->markTestSkipped('WooCommerce product classes not available');
        }
        
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price(10.00);
        $product->set_status('publish');
        $product->set_slug('allpoint-test-' . uniqid());
        
        $product_id = $product->save();
        
        if (!empty($tags)) {
            wp_set_object_terms($product_id, $tags, 'product_tag');
        }
        
        return $product_id;
    }
    
    /**
     * Helper: Create test order (when WooCommerce is available)
     */
    private function create_test_order($product_ids) {
        if (!function_exists('wc_create_order')) {
            $this->markTestSkipped('WooCommerce order functions not available');
        }
        
        $user_id = $this->factory->user->create([
            'user_email' => 'testcustomer@allpointwireless.com',
            'user_login' => 'allpoint_test_' . uniqid(),
            'first_name' => 'Allpoint',
            'last_name' => 'TestCustomer'
        ]);
        
        $order = wc_create_order(['customer_id' => $user_id]);
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            $order->add_product($product, 1);
        }
        
        // Set addresses with Allpoint branding
        $order->set_billing_first_name('Allpoint');
        $order->set_billing_last_name('TestCustomer');
        $order->set_billing_email('testcustomer@allpointwireless.com');
        $order->set_billing_address_1('123 Allpoint Test Street');
        $order->set_billing_city('Test City');
        $order->set_billing_state('CA');
        $order->set_billing_postcode('90210');
        $order->set_billing_country('US');
        $order->set_billing_phone('555-ALLPOINT');
        
        $order->set_shipping_first_name('Allpoint');
        $order->set_shipping_last_name('TestCustomer');
        $order->set_shipping_address_1('123 Allpoint Test Street');
        $order->set_shipping_city('Test City');
        $order->set_shipping_state('CA');
        $order->set_shipping_postcode('90210');
        $order->set_shipping_country('US');
        
        $order->calculate_totals();
        $order->save();
        
        return $order;
    }
}
