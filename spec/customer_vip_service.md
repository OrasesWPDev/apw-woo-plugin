# Customer VIP Service Specification

## Overview
Consolidate scattered customer management functionality into a unified service that handles registration, VIP status, referral tracking, and data export while maintaining WordPress-native patterns.

## Current State Analysis

### Scattered Files & Functionality
1. **Registration Fields**: `class-apw-woo-registration-fields.php` (200+ lines)
2. **Referral Export**: `class-apw-woo-referral-export.php` (150+ lines)  
3. **VIP Logic**: Scattered across dynamic pricing and payment functions
4. **Account Functions**: `apw-woo-account-functions.php`

### Current Issues
- **Duplicate Validation**: Customer validation logic spread across multiple files
- **Mixed Concerns**: Export logic mixed with HTML presentation
- **VIP Status Inconsistency**: VIP customer identification not centralized
- **Poor Performance**: Repeated customer lookups without caching

## Unified Customer Service Architecture

### Core Responsibilities
1. **Customer Registration** - Validation, creation, meta data management
2. **VIP Status Management** - Determine eligibility, track tier status
3. **Referral Tracking** - Link customers to referrers, export functionality
4. **Data Export** - Clean separation of data logic from presentation

### WordPress-Native Implementation

### 1. Customer Service Class
```php
class APW_Woo_Customer_Service {
    
    private static $instance = null;
    private $vip_cache = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validate customer registration data
     */
    public function validate_registration_data($data) {
        $errors = [];
        $required_fields = ['first_name', 'last_name', 'company_name', 'phone_number'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[$field] = sprintf(__('The %s field is required.', 'apw-woo-plugin'), $field);
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors['email'] = __('Please enter a valid email address.', 'apw-woo-plugin');
        }
        
        // Phone validation
        if (!empty($data['phone_number']) && !$this->is_valid_phone($data['phone_number'])) {
            $errors['phone_number'] = __('Please enter a valid phone number.', 'apw-woo-plugin');
        }
        
        return $errors;
    }
    
    /**
     * Create customer with validated data
     */
    public function create_customer($data) {
        $validation_errors = $this->validate_registration_data($data);
        
        if (!empty($validation_errors)) {
            return new WP_Error('validation_failed', 'Validation errors', $validation_errors);
        }
        
        $customer_id = wp_create_user($data['username'], $data['password'], $data['email']);
        
        if (is_wp_error($customer_id)) {
            return $customer_id;
        }
        
        // Set customer meta data
        $this->update_customer_meta($customer_id, [
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name']),
            'company_name' => sanitize_text_field($data['company_name']),
            'phone_number' => sanitize_text_field($data['phone_number']),
            'referred_by' => sanitize_text_field($data['referred_by'] ?? ''),
            'registration_date' => current_time('mysql')
        ]);
        
        do_action('apw_woo_customer_created', $customer_id, $data);
        
        return $customer_id;
    }
    
    /**
     * Check if customer is VIP based on purchase history
     */
    public function is_vip_customer($customer_id = null) {
        if (null === $customer_id) {
            $customer_id = get_current_user_id();
        }
        
        if (!$customer_id) {
            return false;
        }
        
        // Use cache to avoid repeated lookups
        if (isset($this->vip_cache[$customer_id])) {
            return $this->vip_cache[$customer_id];
        }
        
        $is_vip = $this->calculate_vip_status($customer_id);
        $this->vip_cache[$customer_id] = $is_vip;
        
        return $is_vip;
    }
    
    /**
     * Get VIP discount tier for customer
     */
    public function get_vip_discount_tier($customer_id = null) {
        if (!$this->is_vip_customer($customer_id)) {
            return null;
        }
        
        if (null === $customer_id) {
            $customer_id = get_current_user_id();
        }
        
        $total_spent = $this->get_customer_total_spent($customer_id);
        
        // Tier thresholds
        if ($total_spent >= 500) {
            return ['tier' => 'platinum', 'discount' => 0.10, 'minimum' => 500];
        } elseif ($total_spent >= 300) {
            return ['tier' => 'gold', 'discount' => 0.08, 'minimum' => 300];
        } elseif ($total_spent >= 100) {
            return ['tier' => 'silver', 'discount' => 0.05, 'minimum' => 100];
        }
        
        return null;
    }
    
    /**
     * Apply VIP discount to cart
     */
    public function apply_vip_discount() {
        if (!$this->is_vip_customer()) {
            return;
        }
        
        $cart_subtotal = WC()->cart->get_subtotal();
        $discount_tier = $this->get_vip_discount_tier();
        
        if (!$discount_tier || $cart_subtotal < $discount_tier['minimum']) {
            return;
        }
        
        $discount_amount = $cart_subtotal * $discount_tier['discount'];
        
        WC()->cart->add_fee(
            sprintf(__('VIP %s Discount (%d%%)', 'apw-woo-plugin'), ucfirst($discount_tier['tier']), $discount_tier['discount'] * 100),
            -$discount_amount,
            false // Not taxable
        );
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Applied VIP discount: {$discount_tier['tier']} tier, {$discount_tier['discount']}%, $" . number_format($discount_amount, 2));
        }
    }
    
    /**
     * Get referral customers
     */
    public function get_referral_customers($referrer_name = '') {
        global $wpdb;
        
        $query = "
            SELECT u.ID, u.user_login, u.user_email, u.user_registered,
                   MAX(CASE WHEN um.meta_key = 'first_name' THEN um.meta_value END) as first_name,
                   MAX(CASE WHEN um.meta_key = 'last_name' THEN um.meta_value END) as last_name,
                   MAX(CASE WHEN um.meta_key = 'company_name' THEN um.meta_value END) as company_name,
                   MAX(CASE WHEN um.meta_key = 'phone_number' THEN um.meta_value END) as phone_number,
                   MAX(CASE WHEN um.meta_key = 'referred_by' THEN um.meta_value END) as referred_by
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE u.ID IN (
                SELECT user_id FROM {$wpdb->usermeta} 
                WHERE meta_key = 'referred_by' AND meta_value != ''
        ";
        
        $params = [];
        if (!empty($referrer_name)) {
            $query .= " AND meta_value LIKE %s";
            $params[] = '%' . $wpdb->esc_like($referrer_name) . '%';
        }
        
        $query .= ") GROUP BY u.ID ORDER BY u.user_registered DESC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Export customer data to CSV
     */
    public function export_customers_csv($customers) {
        if (empty($customers)) {
            return '';
        }
        
        // CSV headers
        $csv_data = "User ID,Username,Email,First Name,Last Name,Company,Phone,Referred By,Registration Date\n";
        
        foreach ($customers as $customer) {
            $csv_data .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $customer->ID,
                $this->escape_csv_field($customer->user_login),
                $this->escape_csv_field($customer->user_email),
                $this->escape_csv_field($customer->first_name ?? ''),
                $this->escape_csv_field($customer->last_name ?? ''),
                $this->escape_csv_field($customer->company_name ?? ''),
                $this->escape_csv_field($customer->phone_number ?? ''),
                $this->escape_csv_field($customer->referred_by ?? ''),
                $customer->user_registered
            );
        }
        
        return $csv_data;
    }
    
    // Private helper methods
    
    private function calculate_vip_status($customer_id) {
        $total_spent = $this->get_customer_total_spent($customer_id);
        return $total_spent >= 100; // Minimum VIP threshold
    }
    
    private function get_customer_total_spent($customer_id) {
        // Use WooCommerce customer data if available
        if (function_exists('wc_get_customer')) {
            $customer = new WC_Customer($customer_id);
            return $customer->get_total_spent();
        }
        
        // Fallback calculation
        global $wpdb;
        $total = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(pm.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_order_total'
            AND p.post_author = %d
        ", $customer_id));
        
        return floatval($total ?: 0);
    }
    
    private function is_valid_phone($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    private function escape_csv_field($field) {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
    
    private function update_customer_meta($customer_id, $meta_data) {
        foreach ($meta_data as $key => $value) {
            update_user_meta($customer_id, $key, $value);
        }
    }
}
```

### 2. Hook Integration
```php
function apw_woo_init_customer_service() {
    $customer_service = APW_Woo_Customer_Service::get_instance();
    
    // VIP discount hooks
    add_action('woocommerce_cart_calculate_fees', [$customer_service, 'apply_vip_discount'], 10);
    
    // Registration hooks
    add_action('user_register', [$customer_service, 'process_custom_registration_fields']);
    add_filter('registration_errors', [$customer_service, 'validate_custom_registration_fields'], 10, 3);
    
    // Admin export functionality
    if (is_admin()) {
        add_action('wp_ajax_apw_export_referrals', 'apw_woo_handle_referral_export');
    }
}
add_action('init', 'apw_woo_init_customer_service');
```

### 3. Admin Export Handler
```php
function apw_woo_handle_referral_export() {
    // Verify permissions and nonce
    if (!current_user_can('manage_woocommerce') || !check_ajax_referer('apw_woo_export_nonce', 'nonce', false)) {
        wp_die(__('Unauthorized access', 'apw-woo-plugin'));
    }
    
    $customer_service = APW_Woo_Customer_Service::get_instance();
    $referrer_name = sanitize_text_field($_POST['referrer_name'] ?? '');
    
    $customers = $customer_service->get_referral_customers($referrer_name);
    $csv_data = $customer_service->export_customers_csv($customers);
    
    $filename = 'referral-customers-' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csv_data));
    
    echo $csv_data;
    wp_die();
}
```

## Code Consolidation Impact

### Current Files Total: ~550 lines
- `class-apw-woo-registration-fields.php`: ~200 lines
- `class-apw-woo-referral-export.php`: ~150 lines  
- VIP logic scattered: ~200 lines

### Target: ~300 lines (45% reduction)
- **Unified Customer Service**: ~250 lines
- **Hook Integration**: ~30 lines
- **Admin Handlers**: ~20 lines

### Reductions Achieved
- **Eliminate Duplicate Validation**: -50 lines
- **Consolidate VIP Logic**: -80 lines
- **Remove Mixed Concerns**: -40 lines
- **Optimize Database Queries**: -30 lines
- **Streamline Export Logic**: -50 lines

## Integration with Payment Service

### VIP Discount Timing
The customer service VIP discount application (priority 10) runs before payment surcharge calculation (priority 20), ensuring proper surcharge calculation on discounted amounts.

```php
// Customer VIP discount (priority 10)
add_action('woocommerce_cart_calculate_fees', [$customer_service, 'apply_vip_discount'], 10);

// Payment surcharge calculation (priority 20) 
add_action('woocommerce_cart_calculate_fees', 'apw_woo_apply_credit_card_surcharge', 20);
```

## Testing Strategy

### VIP Status Testing
1. **New Customer**: Verify VIP status = false
2. **Spending Thresholds**: Test $100, $300, $500 purchase totals
3. **Discount Application**: Verify correct tier discounts applied
4. **Cache Performance**: Ensure VIP status cached within request

### Registration Testing  
1. **Field Validation**: Test required field validation
2. **Data Sanitization**: Verify input sanitization
3. **Meta Storage**: Confirm custom fields saved correctly
4. **Error Handling**: Test validation error display

### Export Testing
1. **All Customers**: Export without filters
2. **Referrer Filter**: Export by specific referrer name
3. **CSV Format**: Verify proper CSV escaping
4. **Large Dataset**: Test performance with 1000+ customers

## Success Metrics

### Code Quality
- [ ] 45% reduction in customer-related code (550 → 300 lines)
- [ ] Elimination of duplicate validation logic
- [ ] Clean separation of concerns (data vs presentation)
- [ ] WordPress coding standards compliance

### Performance
- [ ] VIP status caching reduces database queries by 80%
- [ ] Consolidated customer queries improve export performance
- [ ] Single service initialization reduces hook overhead

### Functionality
- [ ] All existing registration fields continue working
- [ ] VIP discounts apply correctly with payment surcharges
- [ ] Referral export maintains current functionality
- [ ] Customer data integrity preserved during transition

This unified customer service maintains all existing functionality while significantly reducing code complexity and improving performance through proper caching and consolidated logic.