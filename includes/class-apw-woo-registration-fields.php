<?php
/**
 * APW WooCommerce Registration Fields Class
 *
 * Handles custom registration fields for WooCommerce registration form.
 * Adds First Name, Last Name, Company Name, Phone Number, and Referred By fields.
 *
 * @package APW_Woo_Plugin
 * @since 1.18.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW WooCommerce Registration Fields Class
 */
class APW_Woo_Registration_Fields {
    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

    /**
     * User meta fields managed by this class
     *
     * @var array
     */
    private $user_meta_fields = [
        'apw_first_name',
        'apw_last_name', 
        'apw_company',
        'apw_phone',
        'apw_referred_by'
    ];

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('APW Registration Fields class constructed');
        }
    }

    /**
     * Get instance
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add fields to registration form
        add_action('woocommerce_register_form', array($this, 'add_registration_fields'));
        
        // Validate registration fields
        add_filter('woocommerce_registration_errors', array($this, 'validate_registration_fields'), 10, 3);
        
        // Save registration fields
        add_action('woocommerce_created_customer', array($this, 'save_registration_fields'));
        
        // Sync to WooCommerce customer data during first checkout
        add_action('woocommerce_checkout_update_customer', array($this, 'sync_registration_to_billing'));
        
        // Add fields to user profile in admin
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
        
        // Customize admin user columns
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_action('manage_users_custom_column', array($this, 'show_user_column_content'), 10, 3);
        
        // Make user columns sortable
        add_filter('manage_users_sortable_columns', array($this, 'make_user_columns_sortable'));
        add_action('pre_get_users', array($this, 'handle_user_column_sorting'));
        
        // Enqueue frontend styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Add custom fields to WooCommerce registration form
     */
    public function add_registration_fields() {
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('Adding registration fields to WooCommerce form');
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
     *
     * @param WP_Error $errors Error object
     * @param string $username Username
     * @param string $email Email
     * @return WP_Error
     */
    public function validate_registration_fields($errors, $username, $email) {
        // First Name validation
        if (empty($_POST['apw_first_name'])) {
            $errors->add('apw_first_name_error', __('First Name is required.', 'apw-woo-plugin'));
        }
        
        // Last Name validation  
        if (empty($_POST['apw_last_name'])) {
            $errors->add('apw_last_name_error', __('Last Name is required.', 'apw-woo-plugin'));
        }
        
        // Company validation
        if (empty($_POST['apw_company'])) {
            $errors->add('apw_company_error', __('Company Name is required.', 'apw-woo-plugin'));
        }
        
        // Phone validation
        if (empty($_POST['apw_phone'])) {
            $errors->add('apw_phone_error', __('Phone Number is required.', 'apw-woo-plugin'));
        } elseif (!$this->validate_phone_number(sanitize_text_field($_POST['apw_phone']))) {
            $errors->add('apw_phone_format_error', __('Please enter a valid phone number.', 'apw-woo-plugin'));
        }
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            $error_count = count($errors->get_error_codes());
            apw_woo_log("Registration validation completed with {$error_count} errors");
        }
        
        return $errors;
    }

    /**
     * Save registration fields when customer is created
     *
     * @param int $customer_id Customer ID
     */
    public function save_registration_fields($customer_id) {
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log("Saving registration fields for customer {$customer_id}");
        }
        
        // Save custom fields
        if (!empty($_POST['apw_first_name'])) {
            update_user_meta($customer_id, 'apw_first_name', sanitize_text_field($_POST['apw_first_name']));
        }
        
        if (!empty($_POST['apw_last_name'])) {
            update_user_meta($customer_id, 'apw_last_name', sanitize_text_field($_POST['apw_last_name']));
        }
        
        if (!empty($_POST['apw_company'])) {
            update_user_meta($customer_id, 'apw_company', sanitize_text_field($_POST['apw_company']));
        }
        
        if (!empty($_POST['apw_phone'])) {
            update_user_meta($customer_id, 'apw_phone', sanitize_text_field($_POST['apw_phone']));
        }
        
        if (!empty($_POST['apw_referred_by'])) {
            update_user_meta($customer_id, 'apw_referred_by', sanitize_text_field($_POST['apw_referred_by']));
        }
    }

    /**
     * Sync registration data to WooCommerce billing fields during checkout
     *
     * @param WC_Customer $customer Customer object
     */
    public function sync_registration_to_billing($customer) {
        $user_id = $customer->get_id();
        
        if (!$user_id) {
            return;
        }
        
        // Only sync if billing fields are empty (first checkout)
        if (empty($customer->get_billing_first_name())) {
            $first_name = get_user_meta($user_id, 'apw_first_name', true);
            if ($first_name) {
                $customer->set_billing_first_name($first_name);
            }
        }
        
        if (empty($customer->get_billing_last_name())) {
            $last_name = get_user_meta($user_id, 'apw_last_name', true);
            if ($last_name) {
                $customer->set_billing_last_name($last_name);
            }
        }
        
        if (empty($customer->get_billing_company())) {
            $company = get_user_meta($user_id, 'apw_company', true);
            if ($company) {
                $customer->set_billing_company($company);
            }
        }
        
        if (empty($customer->get_billing_phone())) {
            $phone = get_user_meta($user_id, 'apw_phone', true);
            if ($phone) {
                $customer->set_billing_phone($phone);
            }
        }
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log("Synced registration data to billing fields for user {$user_id}");
        }
    }

    /**
     * Add custom fields to user profile in admin
     *
     * @param WP_User $user User object
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
                <td>
                    <input type="text" name="apw_first_name" id="apw_first_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_first_name', true)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="apw_last_name"><?php esc_html_e('Last Name', 'apw-woo-plugin'); ?></label></th>
                <td>
                    <input type="text" name="apw_last_name" id="apw_last_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_last_name', true)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="apw_company"><?php esc_html_e('Company Name', 'apw-woo-plugin'); ?></label></th>
                <td>
                    <input type="text" name="apw_company" id="apw_company" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_company', true)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="apw_phone"><?php esc_html_e('Phone Number', 'apw-woo-plugin'); ?></label></th>
                <td>
                    <input type="tel" name="apw_phone" id="apw_phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'apw_phone', true)); ?>" class="regular-text" />
                </td>
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
     *
     * @param int $user_id User ID
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
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log("Updated user profile fields for user {$user_id}");
        }
    }

    /**
     * Add custom columns to user list in admin
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_user_columns($columns) {
        $columns['apw_company'] = __('Company', 'apw-woo-plugin');
        $columns['apw_phone'] = __('Phone', 'apw-woo-plugin');
        $columns['apw_referred_by'] = __('Referred By', 'apw-woo-plugin');
        
        return $columns;
    }

    /**
     * Show content for custom user columns
     *
     * @param string $value Column value
     * @param string $column_name Column name
     * @param int $user_id User ID
     * @return string
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
     *
     * @param array $columns Sortable columns
     * @return array Modified columns
     */
    public function make_user_columns_sortable($columns) {
        $columns['apw_company'] = 'apw_company';
        $columns['apw_phone'] = 'apw_phone';
        $columns['apw_referred_by'] = 'apw_referred_by';
        
        return $columns;
    }

    /**
     * Handle sorting for custom user columns
     *
     * @param WP_User_Query $query User query
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

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_account_page()) {
            wp_enqueue_style(
                'apw-registration-fields',
                APW_WOO_PLUGIN_URL . 'assets/css/apw-registration-fields.css',
                array(),
                APW_WOO_VERSION
            );
            
            wp_enqueue_script(
                'apw-registration-validation',
                APW_WOO_PLUGIN_URL . 'assets/js/apw-registration-validation.js',
                array('jquery'),
                APW_WOO_VERSION,
                true
            );
        }
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number
     * @return bool
     */
    private function validate_phone_number($phone) {
        // Remove all non-digit characters
        $digits_only = preg_replace('/\D/', '', $phone);
        
        // Check if it's a valid length (7-15 digits, covering most international formats)
        return strlen($digits_only) >= 7 && strlen($digits_only) <= 15;
    }

    /**
     * Get user meta fields managed by this class
     *
     * @return array
     */
    public function get_user_meta_fields() {
        return $this->user_meta_fields;
    }
}