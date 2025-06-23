<?php
/**
 * Payment Service
 *
 * Consolidates payment gateway integration, credit card surcharge management,
 * and recurring billing preferences functionality.
 *
 * @package APW_Woo_Plugin
 * @since 1.24.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Service Class
 *
 * Handles Intuit/QuickBooks payment gateway integration, credit card surcharge calculation,
 * and recurring product billing preferences.
 */
class APW_Woo_Payment_Service {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Payment gateway integration status
     */
    private $integration_initialized = false;
    
    /**
     * Recurring product tag slug
     */
    private const RECURRING_TAG_SLUG = 'recurring';
    
    /**
     * Meta key for billing preferences
     */
    private const BILLING_PREFERENCE_META_KEY = '_apw_woo_preferred_billing_method';
    
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
            apw_woo_log('Payment Service initialized');
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize payment gateway integration
        add_action('woocommerce_init', [$this, 'initialize_payment_integration'], 20);
        
        // Recurring billing hooks
        add_action('woocommerce_checkout_after_customer_details', [$this, 'display_recurring_billing_field'], 10);
        add_action('woocommerce_checkout_process', [$this, 'validate_recurring_billing_field']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_recurring_billing_field'], 10, 2);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_recurring_billing_admin'], 10, 1);
    }
    
    /**
     * Initialize payment gateway integration
     */
    public function initialize_payment_integration() {
        // Prevent multiple initializations
        if ($this->integration_initialized) {
            return;
        }
        
        // Check if Intuit gateway is active
        $intuit_active = $this->is_intuit_gateway_active();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Payment Service: Intuit gateway active check: ' . ($intuit_active ? 'YES' : 'NO'));
        }
        
        if (!$intuit_active) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Payment Service: Intuit gateway not active - integration skipped');
            }
            return;
        }
        
        // Initialize Intuit payment integration
        add_action('woocommerce_checkout_before_customer_details', [$this, 'add_intuit_payment_fields'], 10);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_intuit_scripts']);
        add_filter('woocommerce_checkout_posted_data', [$this, 'preserve_intuit_fields']);
        
        // Credit card surcharge - priority 30 to run after VIP discounts (priority 10)
        add_action('woocommerce_cart_calculate_fees', [$this, 'handle_credit_card_surcharge'], 30);
        
        $this->integration_initialized = true;
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Payment Service: Intuit payment integration initialized');
        }
    }
    
    /**
     * Check if Intuit/QuickBooks payment gateway is active
     */
    public function is_intuit_gateway_active() {
        // Check if gateway classes exist
        if (class_exists('WC_Gateway_Intuit_QBMS') || class_exists('WC_Gateway_QBMS_Credit_Card')) {
            return true;
        }
        
        // Check available gateways
        if (function_exists('WC') && method_exists(WC(), 'payment_gateways')) {
            $gateways_api = WC()->payment_gateways();
            $available_gateways = $gateways_api->get_available_payment_gateways();
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Payment Service: Available gateways: ' . implode(', ', array_keys($available_gateways)));
            }
            
            return isset($available_gateways['intuit_qbms_credit_card']) || 
                   isset($available_gateways['intuit_payments_credit_card']);
        }
        
        return false;
    }
    
    /**
     * Add Intuit payment fields to checkout
     */
    public function add_intuit_payment_fields() {
        if (!is_checkout() || !$this->is_intuit_gateway_active()) {
            return;
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Payment Service: Added Intuit payment fields to checkout');
        }
    }
    
    /**
     * Enqueue Intuit payment scripts
     */
    public function enqueue_intuit_scripts() {
        if (!is_checkout() || !$this->is_intuit_gateway_active()) {
            return;
        }
        
        $js_file = 'apw-woo-intuit-integration.js';
        $js_path = APW_WOO_PLUGIN_DIR . 'assets/js/' . $js_file;
        
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'apw-woo-intuit-integration',
                APW_WOO_PLUGIN_URL . 'assets/js/' . $js_file,
                array('jquery', 'wc-checkout', 'wc-intuit-qbms-checkout'),
                filemtime($js_path),
                true
            );
            
            wp_localize_script(
                'apw-woo-intuit-integration',
                'apwWooIntuitData',
                array(
                    'debug_mode' => APW_WOO_DEBUG_MODE,
                    'is_checkout' => is_checkout()
                )
            );
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Payment Service: Enqueued Intuit integration script');
            }
        }
        
        // Ensure cart fragments update
        wp_enqueue_script('wc-cart-fragments');
    }
    
    /**
     * Preserve Intuit payment fields during checkout
     */
    public function preserve_intuit_fields($data) {
        if (isset($_POST['wc-intuit-payments-credit-card-js-token']) && !empty($_POST['wc-intuit-payments-credit-card-js-token'])) {
            $data['wc-intuit-payments-credit-card-js-token'] = sanitize_text_field($_POST['wc-intuit-payments-credit-card-js-token']);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Payment Service: Intuit token preserved (length: " . strlen($data['wc-intuit-payments-credit-card-js-token']) . ")");
            }
        }

        if (isset($_POST['wc-intuit-payments-credit-card-card-type']) && !empty($_POST['wc-intuit-payments-credit-card-card-type'])) {
            $data['wc-intuit-payments-credit-card-card-type'] = sanitize_text_field($_POST['wc-intuit-payments-credit-card-card-type']);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Payment Service: Intuit card type preserved: " . $data['wc-intuit-payments-credit-card-card-type']);
            }
        }
        
        return $data;
    }
    
    /**
     * Handle credit card surcharge calculation and application
     */
    public function handle_credit_card_surcharge() {
        // Standard validations
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!is_checkout()) {
            return;
        }

        $chosen_gateway = WC()->session->get('chosen_payment_method');
        if ($chosen_gateway !== 'intuit_payments_credit_card') {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Payment Service: No surcharge - payment method is: " . ($chosen_gateway ?: 'none'));
            }
            // Remove surcharge when payment method changes
            $this->remove_credit_card_surcharge();
            return;
        }
        
        // Apply surcharge for credit card payments
        $this->apply_credit_card_surcharge();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Payment Service: Surcharge processing completed");
        }
    }
    
    /**
     * Calculate credit card surcharge amount
     * 
     * Pure calculation function that determines surcharge based on cart totals
     * Uses the formula: (subtotal + shipping - discounts) × 3%
     */
    public function calculate_credit_card_surcharge() {
        // Only calculate if payment method is credit card
        if (!is_checkout() || WC()->session->get('chosen_payment_method') !== 'intuit_payments_credit_card') {
            return 0;
        }
        
        $cart = WC()->cart;
        $subtotal = $cart->get_subtotal();
        $shipping_total = $cart->get_shipping_total();
        
        // Calculate total discounts from negative fees (VIP discounts)
        $total_discounts = 0;
        $existing_fees = $cart->get_fees();
        
        foreach ($existing_fees as $fee) {
            // Only count negative fees that aren't surcharges themselves
            if ($fee->amount < 0 && strpos($fee->name, 'Surcharge') === false) {
                $total_discounts += abs($fee->amount);
            }
        }
        
        // Calculate surcharge base: subtotal + shipping - discounts
        $surcharge_base = $subtotal + $shipping_total - $total_discounts;
        $surcharge = max(0, $surcharge_base * 0.03); // 3%
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Payment Service: Surcharge calculation:");
            apw_woo_log("- Subtotal: $" . number_format($subtotal, 2));
            apw_woo_log("- Shipping: $" . number_format($shipping_total, 2));
            apw_woo_log("- Discounts: $" . number_format($total_discounts, 2));
            apw_woo_log("- Base: $" . number_format($surcharge_base, 2));
            apw_woo_log("- Surcharge (3%): $" . number_format($surcharge, 2));
        }
        
        return $surcharge;
    }
    
    /**
     * Remove existing credit card surcharge fees using native WooCommerce API
     */
    public function remove_credit_card_surcharge() {
        $cart = WC()->cart;
        $all_fees = $cart->get_fees();
        $removed_count = 0;
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Payment Service: Starting native fee removal process");
            apw_woo_log("Payment Service: Found " . count($all_fees) . " total fees");
        }
        
        // Filter out surcharge fees
        $filtered_fees = array_filter($all_fees, function($fee) use (&$removed_count) {
            $is_surcharge = strpos($fee->name, 'Credit Card Surcharge') !== false || 
                           strpos($fee->name, 'Surcharge') !== false;
            
            if ($is_surcharge) {
                $removed_count++;
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Payment Service: Will remove fee '$fee->name' (amount: $" . number_format($fee->amount, 2) . ")");
                }
            }
            
            return !$is_surcharge;
        });
        
        // Reset array keys and replace fees using native API
        $filtered_fees = array_values($filtered_fees);
        
        if (method_exists($cart, 'fees_api') && method_exists($cart->fees_api(), 'set_fees')) {
            // Use official fees API if available
            $cart->fees_api()->set_fees($filtered_fees);
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Payment Service: Used WooCommerce fees_api()->set_fees() method");
            }
        } else {
            // Fallback: Direct assignment
            $cart->fees = $filtered_fees;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Payment Service: Used direct fees array assignment (fallback)");
            }
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Payment Service: Removed $removed_count surcharge fees");
        }
    }
    
    /**
     * Apply credit card surcharge fee using native WooCommerce API
     */
    public function apply_credit_card_surcharge() {
        // Remove existing surcharge first
        $this->remove_credit_card_surcharge();
        
        // Calculate new surcharge
        $surcharge = $this->calculate_credit_card_surcharge();
        
        if ($surcharge > 0) {
            // Add new surcharge using WooCommerce's standard method
            WC()->cart->add_fee(__('Credit Card Surcharge (3%)', 'apw-woo-plugin'), $surcharge, true);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Payment Service: Added new surcharge: $" . number_format($surcharge, 2));
                apw_woo_log("Payment Service: Final fee count: " . count(WC()->cart->get_fees()));
            }
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Payment Service: No surcharge to apply (amount: $0.00)");
            }
        }
    }
    
    /**
     * Check if cart contains recurring products
     */
    public function cart_has_recurring_products() {
        if (!function_exists('WC') || WC()->cart === null) {
            return false;
        }

        $cart = WC()->cart;
        if ($cart->is_empty()) {
            return false;
        }

        foreach ($cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (has_term(self::RECURRING_TAG_SLUG, 'product_tag', $product_id)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Payment Service: Found recurring product ID ' . $product_id);
                }
                return true;
            }
        }

        return false;
    }
    
    /**
     * Display recurring billing preference field on checkout
     */
    public function display_recurring_billing_field() {
        if (!$this->cart_has_recurring_products()) {
            return;
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Payment Service: Displaying recurring billing field');
        }
        
        $checkout = WC()->checkout();
        if (!is_a($checkout, 'WC_Checkout')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Payment Service: Failed to get valid WC_Checkout object', 'error');
            }
            return;
        }
        
        $selected_value = $checkout->get_value(self::BILLING_PREFERENCE_META_KEY);
        
        echo '<div class="apw-woo-preferred-billing-wrapper">';
        
        woocommerce_form_field(
            self::BILLING_PREFERENCE_META_KEY,
            array(
                'type' => 'select',
                'class' => array('apw-woo-preferred-billing-field', 'form-row-wide'),
                'label' => __('Preferred monthly billing method', 'apw-woo-plugin'),
                'required' => true,
                'options' => array(
                    '' => __(' -- Select an Option -- ', 'apw-woo-plugin'),
                    'YES-NEW' => __('New Customer - must provide new monthly billing method', 'apw-woo-plugin'),
                    'NO-EXISTING' => __('Existing customer – use default monthly billing on file', 'apw-woo-plugin'),
                    'YES-EXISTING' => __('Existing customer – use new monthly billing method', 'apw-woo-plugin'),
                ),
                'default' => '',
            ),
            $selected_value
        );
        
        echo '</div>';
    }
    
    /**
     * Validate recurring billing preference field
     */
    public function validate_recurring_billing_field() {
        if (!$this->cart_has_recurring_products()) {
            return;
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Payment Service: Validating recurring billing field');
        }
        
        if (isset($_POST[self::BILLING_PREFERENCE_META_KEY]) && empty(trim($_POST[self::BILLING_PREFERENCE_META_KEY]))) {
            wc_add_notice(__('Please select your <strong>Preferred monthly billing method</strong>.', 'apw-woo-plugin'), 'error');
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Payment Service: Billing preference field validation failed - empty value');
            }
        }
    }
    
    /**
     * Save recurring billing preference to order meta
     */
    public function save_recurring_billing_field($order, $data) {
        if (isset($_POST[self::BILLING_PREFERENCE_META_KEY]) && !empty($_POST[self::BILLING_PREFERENCE_META_KEY])) {
            $selected_value = sanitize_text_field($_POST[self::BILLING_PREFERENCE_META_KEY]);
            $order->update_meta_data(self::BILLING_PREFERENCE_META_KEY, $selected_value);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Payment Service: Saved billing preference "' . $selected_value . '" to order ID ' . $order->get_id());
            }
        }
    }
    
    /**
     * Display recurring billing preference in admin order view
     */
    public function display_recurring_billing_admin($order) {
        $saved_value = $order->get_meta(self::BILLING_PREFERENCE_META_KEY);
        
        if (!$saved_value) {
            return;
        }
        
        $display_text = __('N/A', 'apw-woo-plugin');
        
        switch ($saved_value) {
            case 'YES-NEW':
                $display_text = __('New Customer - must provide new monthly billing method', 'apw-woo-plugin');
                break;
            case 'NO-EXISTING':
                $display_text = __('Existing customer – use default monthly billing on file', 'apw-woo-plugin');
                break;
            case 'YES-EXISTING':
                $display_text = __('Existing customer – use new monthly billing method', 'apw-woo-plugin');
                break;
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Payment Service: Displaying admin billing preference for Order ID ' . $order->get_id() . ': ' . $display_text);
        }
        
        ?>
        <div class="apw-preferred-billing-admin order_data_column">
            <h4><?php esc_html_e('Customer Billing Preference', 'apw-woo-plugin'); ?></h4>
            <p>
                <strong><?php esc_html_e('Preferred Monthly Billing:', 'apw-woo-plugin'); ?></strong><br>
                <?php echo esc_html($display_text); ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Initialize Payment Service
 * 
 * @return void
 * @since 1.24.0
 */
function apw_woo_initialize_payment_service()
{
    if (class_exists('APW_Woo_Payment_Service')) {
        APW_Woo_Payment_Service::get_instance();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('PHASE 2: Payment Service initialized (Intuit integration, recurring billing, surcharge calculation)');
        }
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('APW_Woo_Payment_Service class not found', 'warning');
        }
    }
}