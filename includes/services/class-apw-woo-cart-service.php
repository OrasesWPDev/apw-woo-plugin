<?php
/**
 * Cart Service
 *
 * Consolidates cart management functionality including cart quantity indicators,
 * checkout field customization, cart fragment management, and cart-related scripts.
 *
 * @package APW_Woo_Plugin
 * @since 1.24.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cart Service Class
 *
 * Handles cart quantity indicators, checkout fields, cart fragments, and 
 * cart-related frontend functionality.
 */
class APW_Woo_Cart_Service {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
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
            apw_woo_log('Cart Service initialized');
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Cart quantity indicator hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_indicator_assets']);
        add_action('wp_footer', [$this, 'add_cart_count_to_body'], 10);
        add_action('wp_footer', [$this, 'add_cart_update_listener'], 15);
        
        // AJAX handlers for cart count
        add_action('wp_ajax_apw_woo_get_cart_count', [$this, 'ajax_get_cart_count']);
        add_action('wp_ajax_nopriv_apw_woo_get_cart_count', [$this, 'ajax_get_cart_count']);
        
        // Checkout field customization hooks
        add_filter('woocommerce_checkout_fields', [$this, 'modify_checkout_fields'], 999);
        add_action('woocommerce_checkout_after_customer_details', [$this, 'add_additional_emails_field'], 20);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_fields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_checkout_fields']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_checkout_fields_admin']);
        
        // Email customization
        add_filter('woocommerce_email_headers', [$this, 'add_cc_to_emails'], 10, 3);
        
        // Checkout scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);
        
        // Cart fragments management
        add_action('wp_enqueue_scripts', [$this, 'ensure_cart_fragments']);
    }
    
    /**
     * Enqueue cart indicator assets
     */
    public function enqueue_cart_indicator_assets() {
        // Enqueue CSS for cart indicators
        $css_file = 'assets/css/apw-woo-cart-indicator.css';
        $css_path = APW_WOO_PLUGIN_DIR . $css_file;
        
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'apw-woo-cart-indicator',
                APW_WOO_PLUGIN_URL . $css_file,
                array(),
                filemtime($css_path)
            );
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cart Service: Cart indicator CSS file not found at: ' . $css_path);
            }
        }
        
        // Ensure cart fragments are loaded
        wp_enqueue_script('wc-cart-fragments');
    }
    
    /**
     * Add initial cart count as data attribute to body
     */
    public function add_cart_count_to_body() {
        if (function_exists('WC') && isset(WC()->cart)) {
            $cart_count = WC()->cart->get_cart_contents_count();
            
            echo '<script type="text/javascript">
                document.body.setAttribute("data-cart-count", "' . esc_js($cart_count) . '");
                // Initialize all cart indicators with the current count
                if (typeof jQuery !== "undefined") {
                    jQuery(function($) {
                        $(".cart-quantity-indicator").attr("data-cart-count", "' . esc_js($cart_count) . '");
                        // Store the WC cart count in a global variable for JS to access
                        window.apwWooCartCount = ' . esc_js($cart_count) . ';
                    });
                }
            </script>';
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cart Service: Added cart count to body: ' . $cart_count);
            }
        }
    }
    
    /**
     * Add cart update event listener
     */
    public function add_cart_update_listener() {
        if (is_cart() || is_checkout()) {
            echo '<script type="text/javascript">
                jQuery(function($) {
                    $(document.body).on("wc_fragments_refreshed updated_cart_totals", function(event) {
                        // Update cart quantity indicators when fragments refresh
                        if (typeof updateCartQuantityIndicators === "function") {
                            updateCartQuantityIndicators();
                        }
                    });
                });
            </script>';
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cart Service: Added cart update listeners for cart/checkout pages');
            }
        }
    }
    
    /**
     * AJAX handler to get current cart count
     */
    public function ajax_get_cart_count() {
        if (function_exists('WC') && isset(WC()->cart)) {
            $cart_count = WC()->cart->get_cart_contents_count();
            wp_send_json_success(array(
                'count' => $cart_count,
                'formatted_count' => number_format_i18n($cart_count)
            ));
        } else {
            wp_send_json_error('Cart not available');
        }
    }
    
    /**
     * Modify checkout fields structure and requirements
     */
    public function modify_checkout_fields($fields) {
        // Make company field required
        if (isset($fields['billing']['billing_company'])) {
            $fields['billing']['billing_company']['required'] = true;
            $fields['billing']['billing_company']['priority'] = 25;
        }
        
        // Make phone field required
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required'] = true;
        }
        
        // Modify field classes and structure
        if (isset($fields['billing']['billing_first_name'])) {
            $fields['billing']['billing_first_name']['class'] = array('form-row-first');
        }
        
        if (isset($fields['billing']['billing_last_name'])) {
            $fields['billing']['billing_last_name']['class'] = array('form-row-last');
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Cart Service: Modified checkout fields - made company and phone required');
        }
        
        return $fields;
    }
    
    /**
     * Add additional emails field to checkout
     */
    public function add_additional_emails_field() {
        $checkout = WC()->checkout();
        
        echo '<div class="apw-additional-emails-wrapper">';
        
        woocommerce_form_field(
            'additional_emails',
            array(
                'type' => 'textarea',
                'class' => array('apw-additional-emails-field', 'form-row-wide'),
                'label' => __('Additional Email Addresses (CC)', 'apw-woo-plugin'),
                'placeholder' => __('Enter additional email addresses, one per line', 'apw-woo-plugin'),
                'required' => false,
                'description' => __('These email addresses will receive a copy of the order confirmation.', 'apw-woo-plugin'),
            ),
            $checkout->get_value('additional_emails')
        );
        
        echo '</div>';
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Cart Service: Added additional emails field to checkout');
        }
    }
    
    /**
     * Validate checkout fields
     */
    public function validate_checkout_fields() {
        // Validate additional emails if provided
        if (!empty($_POST['additional_emails'])) {
            $emails = sanitize_textarea_field($_POST['additional_emails']);
            $email_lines = array_filter(array_map('trim', explode("\n", $emails)));
            
            foreach ($email_lines as $email) {
                if (!is_email($email)) {
                    wc_add_notice(
                        sprintf(__('"%s" is not a valid email address in Additional Email Addresses.', 'apw-woo-plugin'), $email),
                        'error'
                    );
                }
            }
        }
        
        // Validate conditional shipping fields if shipping is different from billing
        if (!empty($_POST['ship_to_different_address'])) {
            $required_shipping_fields = array('shipping_first_name', 'shipping_last_name');
            
            foreach ($required_shipping_fields as $field) {
                if (empty($_POST[$field])) {
                    $field_label = str_replace('shipping_', '', $field);
                    $field_label = str_replace('_', ' ', $field_label);
                    $field_label = ucwords($field_label);
                    
                    wc_add_notice(
                        sprintf(__('%s is a required shipping field.', 'apw-woo-plugin'), $field_label),
                        'error'
                    );
                }
            }
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Cart Service: Validated checkout fields');
        }
    }
    
    /**
     * Save checkout fields to order meta
     */
    public function save_checkout_fields($order_id) {
        // Save additional emails
        if (!empty($_POST['additional_emails'])) {
            $emails = sanitize_textarea_field($_POST['additional_emails']);
            update_post_meta($order_id, '_additional_emails', $emails);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cart Service: Saved additional emails for order ID: ' . $order_id);
            }
        }
    }
    
    /**
     * Display checkout fields in admin order view
     */
    public function display_checkout_fields_admin($order) {
        $additional_emails = get_post_meta($order->get_id(), '_additional_emails', true);
        
        if ($additional_emails) {
            ?>
            <div class="apw-additional-emails-admin order_data_column">
                <h4><?php esc_html_e('Additional Email Recipients', 'apw-woo-plugin'); ?></h4>
                <p>
                    <strong><?php esc_html_e('CC Recipients:', 'apw-woo-plugin'); ?></strong><br>
                    <?php echo nl2br(esc_html($additional_emails)); ?>
                </p>
            </div>
            <?php
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cart Service: Displayed additional emails in admin for order ID: ' . $order->get_id());
            }
        }
    }
    
    /**
     * Add CC recipients to order emails
     */
    public function add_cc_to_emails($headers, $email_id, $order) {
        // Only add CC to customer-facing emails
        $customer_emails = array('customer_completed_order', 'customer_invoice', 'customer_processing_order');
        
        if (!in_array($email_id, $customer_emails) || !$order) {
            return $headers;
        }
        
        $additional_emails = get_post_meta($order->get_id(), '_additional_emails', true);
        
        if ($additional_emails) {
            $email_lines = array_filter(array_map('trim', explode("\n", $additional_emails)));
            $valid_emails = array();
            
            foreach ($email_lines as $email) {
                if (is_email($email)) {
                    $valid_emails[] = $email;
                }
            }
            
            if (!empty($valid_emails)) {
                $cc_header = 'Cc: ' . implode(', ', $valid_emails) . "\r\n";
                $headers .= $cc_header;
                
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Cart Service: Added CC recipients to email: ' . implode(', ', $valid_emails));
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * Enqueue checkout-specific scripts
     */
    public function enqueue_checkout_scripts() {
        if (is_checkout()) {
            $js_file = 'assets/js/apw-woo-checkout.js';
            $js_path = APW_WOO_PLUGIN_DIR . $js_file;
            
            if (file_exists($js_path)) {
                wp_enqueue_script(
                    'apw-woo-checkout',
                    APW_WOO_PLUGIN_URL . $js_file,
                    array('jquery', 'wc-checkout'),
                    filemtime($js_path),
                    true
                );
                
                wp_localize_script(
                    'apw-woo-checkout',
                    'apwWooCheckout',
                    array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'debug_mode' => APW_WOO_DEBUG_MODE
                    )
                );
                
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Cart Service: Enqueued checkout scripts');
                }
            }
        }
    }
    
    /**
     * Ensure cart fragments are properly loaded
     */
    public function ensure_cart_fragments() {
        if (is_checkout() || is_cart()) {
            wp_enqueue_script('wc-cart-fragments');
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cart Service: Ensured cart fragments script is loaded');
            }
        }
    }
}

/**
 * Initialize Cart Service
 * 
 * @return void
 * @since 1.24.2
 */
function apw_woo_initialize_cart_service()
{
    if (class_exists('APW_Woo_Cart_Service')) {
        APW_Woo_Cart_Service::get_instance();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('PHASE 2: Cart Service initialized (quantity indicators, checkout fields, cart fragments)');
        }
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('APW_Woo_Cart_Service class not found', 'warning');
        }
    }
}