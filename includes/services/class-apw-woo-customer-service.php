<?php
/**
 * Customer Service
 *
 * Consolidates customer management functionality including registration fields,
 * VIP status management, referral tracking, and data export.
 *
 * @package APW_Woo_Plugin
 * @since 1.24.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer Service Class
 *
 * Handles customer registration, VIP discount management, and referral tracking
 */
class APW_Woo_Customer_Service {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * VIP status cache for request lifecycle
     */
    private $vip_cache = [];
    
    /**
     * User meta fields managed by this service
     */
    private $user_meta_fields = [
        'apw_first_name',
        'apw_last_name', 
        'apw_company',
        'apw_phone',
        'apw_referred_by'
    ];
    
    /**
     * Export directory within WordPress uploads
     */
    private $export_dir = 'apw-referral-exports';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->init_hooks();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Customer Service initialized');
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // VIP discount system - priority 10 to run BEFORE payment surcharge (priority 20)
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_vip_discount'], 10);
        
        // Registration field hooks
        add_action('woocommerce_register_form', [$this, 'add_registration_fields']);
        add_filter('woocommerce_registration_errors', [$this, 'validate_registration_fields'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'save_registration_fields']);
        add_action('woocommerce_checkout_update_customer', [$this, 'sync_registration_to_billing']);
        
        // Admin user profile hooks
        add_action('show_user_profile', [$this, 'add_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_user_profile_fields']);
        add_action('personal_options_update', [$this, 'save_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);
        
        // Admin user list customization
        add_filter('manage_users_columns', [$this, 'add_user_columns']);
        add_action('manage_users_custom_column', [$this, 'show_user_column_content'], 10, 3);
        add_filter('manage_users_sortable_columns', [$this, 'make_user_columns_sortable']);
        add_action('pre_get_users', [$this, 'handle_user_column_sorting']);
        
        // Referral export system
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('bulk_actions-users', [$this, 'add_bulk_action']);
        add_filter('handle_bulk_actions-users', [$this, 'handle_bulk_action'], 10, 3);
        add_action('manage_users_extra_tablenav', [$this, 'add_export_button']);
        add_action('wp_ajax_apw_export_referrals', [$this, 'handle_ajax_export']);
        add_action('restrict_manage_users', [$this, 'add_user_filters']);
        add_filter('pre_get_users', [$this, 'filter_users_by_referral']);
        add_action('admin_init', [$this, 'setup_export_directory']);
        
        // Asset enqueuing
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    // =================================================================
    // VIP DISCOUNT SYSTEM (PRIORITY FUNCTIONALITY)
    // =================================================================
    
    /**
     * Apply VIP discount to cart based on customer tier
     * This runs at priority 10, BEFORE payment surcharge calculation at priority 20
     */
    public function apply_vip_discount() {
        // Standard validations
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        if (!is_checkout()) {
            return;
        }
        
        $customer_id = get_current_user_id();
        if (!$customer_id) {
            return;
        }
        
        // Check VIP status with caching
        if (!$this->is_vip_customer($customer_id)) {
            return;
        }
        
        $cart_subtotal = WC()->cart->get_subtotal();
        $discount_tier = $this->get_vip_discount_tier($customer_id);
        
        if (!$discount_tier || $cart_subtotal < $discount_tier['minimum']) {
            return;
        }
        
        $discount_amount = $cart_subtotal * $discount_tier['discount'];
        
        WC()->cart->add_fee(
            sprintf(__('VIP %s Discount (%d%%)', 'apw-woo-plugin'), 
                ucfirst($discount_tier['tier']), 
                $discount_tier['discount'] * 100
            ),
            -$discount_amount,
            true // Taxable
        );
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CUSTOMER SERVICE: Applied VIP discount - {$discount_tier['tier']} tier, {$discount_tier['discount']}%, $" . number_format($discount_amount, 2));
        }
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
        
        // Use request-level cache to avoid repeated lookups
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
        
        // Tier thresholds matching existing system
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
     * Calculate VIP status based on spending
     */
    private function calculate_vip_status($customer_id) {
        $total_spent = $this->get_customer_total_spent($customer_id);
        return $total_spent >= 100; // Minimum VIP threshold
    }
    
    /**
     * Get customer total spent amount
     */
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
    
    // =================================================================
    // REGISTRATION FIELDS SYSTEM
    // =================================================================
    
    /**
     * Add custom fields to WooCommerce registration form
     */
    public function add_registration_fields() {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('CUSTOMER SERVICE: Adding registration fields to WooCommerce form');
        }
        
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide apw-registration-field">
            <label for="apw_first_name"><?php esc_html_e('First Name', 'apw-woo-plugin'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="apw_first_name" id="apw_first_name" value="<?php echo esc_attr(isset($_POST['apw_first_name']) ? sanitize_text_field($_POST['apw_first_name']) : ''); ?>" required />
        </p>
        
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide apw-registration-field">
            <label for="apw_last_name"><?php esc_html_e('Last Name', 'apw-woo-plugin'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="apw_last_name" id="apw_last_name" value="<?php echo esc_attr(isset($_POST['apw_last_name']) ? sanitize_text_field($_POST['apw_last_name']) : ''); ?>" required />
        </p>
        
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide apw-registration-field">
            <label for="apw_company"><?php esc_html_e('Company Name', 'apw-woo-plugin'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="apw_company" id="apw_company" value="<?php echo esc_attr(isset($_POST['apw_company']) ? sanitize_text_field($_POST['apw_company']) : ''); ?>" required />
        </p>
        
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide apw-registration-field">
            <label for="apw_phone"><?php esc_html_e('Phone Number', 'apw-woo-plugin'); ?> <span class="required">*</span></label>
            <input type="tel" class="woocommerce-Input woocommerce-Input--tel input-text" name="apw_phone" id="apw_phone" value="<?php echo esc_attr(isset($_POST['apw_phone']) ? sanitize_text_field($_POST['apw_phone']) : ''); ?>" required />
        </p>
        
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide apw-registration-field">
            <label for="apw_referred_by"><?php esc_html_e('Referred By', 'apw-woo-plugin'); ?></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="apw_referred_by" id="apw_referred_by" value="<?php echo esc_attr(isset($_POST['apw_referred_by']) ? sanitize_text_field($_POST['apw_referred_by']) : ''); ?>" />
            <small class="description"><?php esc_html_e('Optional: Who referred you to us?', 'apw-woo-plugin'); ?></small>
        </p>
        <?php
    }
    
    /**
     * Validate registration fields
     */
    public function validate_registration_fields($errors, $username, $email) {
        $required_fields = [
            'apw_first_name' => __('First Name is required.', 'apw-woo-plugin'),
            'apw_last_name' => __('Last Name is required.', 'apw-woo-plugin'),
            'apw_company' => __('Company Name is required.', 'apw-woo-plugin'),
            'apw_phone' => __('Phone Number is required.', 'apw-woo-plugin')
        ];
        
        foreach ($required_fields as $field => $error_message) {
            if (empty($_POST[$field])) {
                $errors->add($field . '_error', $error_message);
            }
        }
        
        // Phone validation
        if (!empty($_POST['apw_phone']) && !$this->is_valid_phone(sanitize_text_field($_POST['apw_phone']))) {
            $errors->add('apw_phone_format_error', __('Please enter a valid phone number.', 'apw-woo-plugin'));
        }
        
        if (APW_WOO_DEBUG_MODE) {
            $error_count = count($errors->get_error_codes());
            apw_woo_log("CUSTOMER SERVICE: Registration validation completed with {$error_count} errors");
        }
        
        return $errors;
    }
    
    /**
     * Save registration fields when customer is created
     */
    public function save_registration_fields($customer_id) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CUSTOMER SERVICE: Saving registration fields for customer {$customer_id}");
        }
        
        $meta_data = [];
        foreach ($this->user_meta_fields as $field) {
            if (!empty($_POST[$field])) {
                $meta_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }
        
        $this->update_customer_meta($customer_id, $meta_data);
    }
    
    /**
     * Sync registration data to WooCommerce billing fields during checkout
     */
    public function sync_registration_to_billing($customer) {
        $user_id = $customer->get_id();
        
        if (!$user_id) {
            return;
        }
        
        // Only sync if billing fields are empty (first checkout)
        $sync_fields = [
            'first_name' => 'apw_first_name',
            'last_name' => 'apw_last_name',
            'company' => 'apw_company',
            'phone' => 'apw_phone'
        ];
        
        foreach ($sync_fields as $billing_field => $meta_field) {
            $getter = "get_billing_{$billing_field}";
            $setter = "set_billing_{$billing_field}";
            
            if (empty($customer->$getter())) {
                $meta_value = get_user_meta($user_id, $meta_field, true);
                if ($meta_value) {
                    $customer->$setter($meta_value);
                }
            }
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CUSTOMER SERVICE: Synced registration data to billing fields for user {$user_id}");
        }
    }
    
    /**
     * Validate phone number format
     */
    private function is_valid_phone($phone) {
        $digits_only = preg_replace('/\D/', '', $phone);
        return strlen($digits_only) >= 7 && strlen($digits_only) <= 15;
    }
    
    /**
     * Update customer meta data
     */
    private function update_customer_meta($customer_id, $meta_data) {
        foreach ($meta_data as $key => $value) {
            update_user_meta($customer_id, $key, $value);
        }
    }
    
    // =================================================================
    // ADMIN USER MANAGEMENT
    // =================================================================
    
    /**
     * Add custom fields to user profile in admin
     */
    public function add_user_profile_fields($user) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        ?>
        <h2><?php esc_html_e('APW Registration Information', 'apw-woo-plugin'); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="apw_first_name"><?php esc_html_e('First Name', 'apw-woo-plugin'); ?></label></th>
                <td><input type="text" name="apw_first_name" id="apw_first_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_first_name', true)); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="apw_last_name"><?php esc_html_e('Last Name', 'apw-woo-plugin'); ?></label></th>
                <td><input type="text" name="apw_last_name" id="apw_last_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_last_name', true)); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="apw_company"><?php esc_html_e('Company Name', 'apw-woo-plugin'); ?></label></th>
                <td><input type="text" name="apw_company" id="apw_company" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_company', true)); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="apw_phone"><?php esc_html_e('Phone Number', 'apw-woo-plugin'); ?></label></th>
                <td><input type="tel" name="apw_phone" id="apw_phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_phone', true)); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="apw_referred_by"><?php esc_html_e('Referred By', 'apw-woo-plugin'); ?></label></th>
                <td>
                    <input type="text" name="apw_referred_by" id="apw_referred_by" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_referred_by', true)); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Who referred this user to the site?', 'apw-woo-plugin'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save user profile fields in admin
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        foreach ($this->user_meta_fields as $field) {
            if (isset($_POST[$field])) {
                update_user_meta($user_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CUSTOMER SERVICE: Updated user profile fields for user {$user_id}");
        }
    }
    
    /**
     * Add custom columns to user list in admin
     */
    public function add_user_columns($columns) {
        $columns['apw_company'] = __('Company', 'apw-woo-plugin');
        $columns['apw_phone'] = __('Phone', 'apw-woo-plugin');
        $columns['apw_referred_by'] = __('Referred By', 'apw-woo-plugin');
        
        return $columns;
    }
    
    /**
     * Show content for custom user columns
     */
    public function show_user_column_content($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'apw_company':
                return esc_html(get_user_meta($user_id, 'apw_company', true));
            case 'apw_phone':
                return esc_html(get_user_meta($user_id, 'apw_phone', true));
            case 'apw_referred_by':
                $referred_by = get_user_meta($user_id, 'apw_referred_by', true);
                return $referred_by ? esc_html($referred_by) : '—';
        }
        
        return $value;
    }
    
    /**
     * Make custom user columns sortable
     */
    public function make_user_columns_sortable($columns) {
        $columns['apw_company'] = 'apw_company';
        $columns['apw_phone'] = 'apw_phone';
        $columns['apw_referred_by'] = 'apw_referred_by';
        
        return $columns;
    }
    
    /**
     * Handle sorting for custom user columns
     */
    public function handle_user_column_sorting($query) {
        if (!is_admin()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        if (in_array($orderby, ['apw_company', 'apw_phone', 'apw_referred_by'])) {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
        }
    }
    
    // =================================================================
    // REFERRAL EXPORT SYSTEM (CONSOLIDATED FROM SEPARATE CLASS)
    // =================================================================
    
    /**
     * Add admin menu for referral exports
     */
    public function add_admin_menu() {
        add_submenu_page(
            'users.php',
            __('Referral Export', 'apw-woo-plugin'),
            __('Referral Export', 'apw-woo-plugin'),
            'manage_woocommerce',
            'apw-referral-export',
            [$this, 'render_export_page']
        );
    }
    
    /**
     * Render the export page
     */
    public function render_export_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'apw-woo-plugin'));
        }

        // Handle form submission
        if (isset($_POST['export_referrals']) && wp_verify_nonce($_POST['apw_export_nonce'], 'apw_export_referrals')) {
            $this->process_export_request();
        }

        $referred_users_count = $this->get_referred_users_count();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Referral Export', 'apw-woo-plugin'); ?></h1>
            
            <div class="apw-export-stats">
                <div class="apw-stat-box">
                    <h3><?php echo esc_html($referred_users_count); ?></h3>
                    <p><?php esc_html_e('Users with Referrals', 'apw-woo-plugin'); ?></p>
                </div>
            </div>

            <div class="apw-export-options">
                <h2><?php esc_html_e('Export Options', 'apw-woo-plugin'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('apw_export_referrals', 'apw_export_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="export_type"><?php esc_html_e('Export Type', 'apw-woo-plugin'); ?></label>
                            </th>
                            <td>
                                <select name="export_type" id="export_type">
                                    <option value="all"><?php esc_html_e('All Users with Referrals', 'apw-woo-plugin'); ?></option>
                                    <option value="by_referrer"><?php esc_html_e('Filter by Specific Referrer', 'apw-woo-plugin'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr class="filter-option" id="referrer-filter" style="display: none;">
                            <th scope="row">
                                <label for="referrer_name"><?php esc_html_e('Referrer Name', 'apw-woo-plugin'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="referrer_name" id="referrer_name" class="regular-text" />
                                <p class="description"><?php esc_html_e('Enter the name of the referrer to filter by', 'apw-woo-plugin'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Export to CSV', 'apw-woo-plugin'), 'primary', 'export_referrals'); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get referral customers with optional filtering
     */
    public function get_referral_customers($referrer_name = '') {
        global $wpdb;
        
        $query = "
            SELECT u.ID, u.user_login, u.user_email, u.user_registered,
                   MAX(CASE WHEN um.meta_key = 'apw_first_name' THEN um.meta_value END) as first_name,
                   MAX(CASE WHEN um.meta_key = 'apw_last_name' THEN um.meta_value END) as last_name,
                   MAX(CASE WHEN um.meta_key = 'apw_company' THEN um.meta_value END) as company_name,
                   MAX(CASE WHEN um.meta_key = 'apw_phone' THEN um.meta_value END) as phone_number,
                   MAX(CASE WHEN um.meta_key = 'apw_referred_by' THEN um.meta_value END) as referred_by
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE u.ID IN (
                SELECT user_id FROM {$wpdb->usermeta} 
                WHERE meta_key = 'apw_referred_by' AND meta_value != ''
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
    
    /**
     * Escape CSV field for proper formatting
     */
    private function escape_csv_field($field) {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
    
    /**
     * Process export request from form
     */
    private function process_export_request() {
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');
        $referrer_name = '';
        
        if ($export_type === 'by_referrer' && !empty($_POST['referrer_name'])) {
            $referrer_name = sanitize_text_field($_POST['referrer_name']);
        }
        
        $customers = $this->get_referral_customers($referrer_name);
        
        if (empty($customers)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No users found matching your criteria.', 'apw-woo-plugin') . '</p></div>';
            return;
        }

        $csv_data = $this->export_customers_csv($customers);
        $filename = 'referral-customers-' . date('Y-m-d') . '.csv';
        
        // Output CSV file
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv_data));
        
        echo $csv_data;
        wp_die();
    }
    
    /**
     * Get count of users with referrals
     */
    private function get_referred_users_count() {
        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'apw_referred_by',
                    'value' => '',
                    'compare' => '!='
                ]
            ],
            'count_total' => true,
            'fields' => 'ID'
        ]);
        
        return count($users);
    }
    
    /**
     * Add bulk action to users list
     */
    public function add_bulk_action($bulk_actions) {
        $bulk_actions['apw_export_selected'] = __('Export Selected (Referrals Only)', 'apw-woo-plugin');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk action
     */
    public function handle_bulk_action($redirect_url, $action, $user_ids) {
        if ($action !== 'apw_export_selected' || !current_user_can('manage_woocommerce')) {
            return $redirect_url;
        }

        // Filter users to only those with referrals
        $referred_users = [];
        foreach ($user_ids as $user_id) {
            $referred_by = get_user_meta($user_id, 'apw_referred_by', true);
            if (!empty($referred_by)) {
                $referred_users[] = $user_id;
            }
        }

        if (empty($referred_users)) {
            return add_query_arg('apw_export_message', 'no_referrals', $redirect_url);
        }

        // Generate export - simplified version for bulk action
        $users_data = [];
        foreach ($referred_users as $user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $users_data[] = (object)[
                    'ID' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'first_name' => get_user_meta($user_id, 'apw_first_name', true),
                    'last_name' => get_user_meta($user_id, 'apw_last_name', true),
                    'company_name' => get_user_meta($user_id, 'apw_company', true),
                    'phone_number' => get_user_meta($user_id, 'apw_phone', true),
                    'referred_by' => get_user_meta($user_id, 'apw_referred_by', true),
                    'user_registered' => $user->user_registered
                ];
            }
        }
        
        if (!empty($users_data)) {
            $csv_data = $this->export_customers_csv($users_data);
            $filename = 'referral-export-selected-' . date('Y-m-d') . '.csv';
            
            // Output CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $csv_data;
            wp_die();
        }

        return add_query_arg('apw_export_message', 'error', $redirect_url);
    }
    
    /**
     * Add export button to users list
     */
    public function add_export_button($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            echo '<a href="' . esc_url(admin_url('users.php?page=apw-referral-export')) . '" class="button">';
            esc_html_e('Export All Referrals', 'apw-woo-plugin');
            echo '</a>';
            echo '</div>';
        }
    }
    
    /**
     * Add user filters
     */
    public function add_user_filters() {
        $current_filter = isset($_GET['apw_referral_filter']) ? sanitize_text_field($_GET['apw_referral_filter']) : '';
        
        echo '<select name="apw_referral_filter">';
        echo '<option value="">' . esc_html__('All Users', 'apw-woo-plugin') . '</option>';
        echo '<option value="with_referrals"' . selected($current_filter, 'with_referrals', false) . '>' . esc_html__('Users with Referrals', 'apw-woo-plugin') . '</option>';
        echo '<option value="no_referrals"' . selected($current_filter, 'no_referrals', false) . '>' . esc_html__('Users without Referrals', 'apw-woo-plugin') . '</option>';
        echo '</select>';
    }
    
    /**
     * Filter users by referral status
     */
    public function filter_users_by_referral($query) {
        if (!is_admin() || !isset($_GET['apw_referral_filter'])) {
            return;
        }

        $filter = sanitize_text_field($_GET['apw_referral_filter']);
        
        if ($filter === 'with_referrals') {
            $query->set('meta_query', [
                [
                    'key' => 'apw_referred_by',
                    'value' => '',
                    'compare' => '!='
                ]
            ]);
        } elseif ($filter === 'no_referrals') {
            $query->set('meta_query', [
                'relation' => 'OR',
                [
                    'key' => 'apw_referred_by',
                    'value' => '',
                    'compare' => '='
                ],
                [
                    'key' => 'apw_referred_by',
                    'compare' => 'NOT EXISTS'
                ]
            ]);
        }
    }
    
    /**
     * Handle AJAX export request
     */
    public function handle_ajax_export() {
        check_ajax_referer('apw_export_referrals', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions', 'apw-woo-plugin'));
        }

        $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');
        $referrer_name = '';
        
        if ($export_type === 'by_referrer' && !empty($_POST['referrer_name'])) {
            $referrer_name = sanitize_text_field($_POST['referrer_name']);
        }
        
        $customers = $this->get_referral_customers($referrer_name);
        
        if (empty($customers)) {
            wp_send_json_error(__('No users found with referrals', 'apw-woo-plugin'));
        }

        $csv_data = $this->export_customers_csv($customers);
        
        wp_send_json_success([
            'csv_data' => $csv_data,
            'filename' => 'referral-export-' . date('Y-m-d') . '.csv',
            'count' => count($customers)
        ]);
    }
    
    /**
     * Setup export directory with security
     */
    public function setup_export_directory() {
        $upload_dir = wp_upload_dir();
        $export_path = $upload_dir['basedir'] . '/' . $this->export_dir;
        
        if (!file_exists($export_path)) {
            wp_mkdir_p($export_path);
            
            // Create .htaccess file to prevent direct access
            $htaccess_file = $export_path . '/.htaccess';
            $htaccess_content = "Options -Indexes\nRequire all denied";
            
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, $htaccess_content);
            }

            // Create index.php to prevent directory listing
            $index_file = $export_path . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
        }
    }
    
    // =================================================================
    // ASSET MANAGEMENT
    // =================================================================
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_account_page()) {
            wp_enqueue_style(
                'apw-registration-fields',
                APW_WOO_PLUGIN_URL . 'assets/css/apw-registration-fields.css',
                [],
                APW_WOO_VERSION
            );
            
            wp_enqueue_script(
                'apw-registration-validation',
                APW_WOO_PLUGIN_URL . 'assets/js/apw-registration-validation.js',
                ['jquery'],
                APW_WOO_VERSION,
                true
            );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'users_page_apw-referral-export') {
            wp_enqueue_script(
                'apw-referral-export',
                APW_WOO_PLUGIN_URL . 'assets/js/apw-referral-export.js',
                ['jquery'],
                APW_WOO_VERSION,
                true
            );
            
            wp_localize_script('apw-referral-export', 'apwReferralExport', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('apw_export_referrals')
            ]);
        }
    }
}