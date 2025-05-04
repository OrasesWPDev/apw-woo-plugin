<?php
/**
 * RMA Form Functions for APW WooCommerce Plugin
 *
 * Handles display, validation, cart integration, and order storage
 * for products tagged with "rma".
 *
 * @package APW_Woo_Plugin
 * @since   1.15.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * APW_Woo_RMA_Form Class
 *
 * Manages the display, validation, and saving of RMA form fields
 * for products tagged as 'rma'.
 */
class APW_Woo_RMA_Form
{
    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Product tag slug that triggers the RMA form display.
     *
     * @var string
     */
    private const RMA_TAG_SLUG = 'rma';

    /**
     * Meta key prefix for storing RMA form data.
     *
     * @var string
     */
    private const META_KEY_PREFIX = '_apw_woo_rma_';

    /**
     * Get instance.
     *
     * @return self
     */
    public static function get_instance()
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to prevent direct instantiation.
     */
    private function __construct()
    {
        if ( function_exists( 'apw_woo_log' ) ) {
            apw_woo_log( 'RMA Form class constructed' );
        }
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     * Hooks for display, validation, cart, and order integration are
     * added in subsequent implementation steps.
     */
    private function init_hooks()
    {
        // Step 2: Display form & CSS
        add_action('wp_head', [$this, 'add_rma_product_css']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_rma_form']);
        // Load ACF front-end form functions early, before any HTML output
        add_action('get_header', [$this, 'acf_form_head'], 0);
        
        // Step 3: Validation
        add_action('woocommerce_add_to_cart_validation', [$this, 'validate_rma_form'], 10, 3);
        
        // Step 4: Cart & Order Integration
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_rma_data_to_cart_item'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_rma_data_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_rma_data_to_order_item'], 10, 4);
        add_action('woocommerce_admin_order_item_headers', [$this, 'add_rma_column_header']);
        add_action('woocommerce_admin_order_item_values', [$this, 'add_rma_column_value'], 10, 3);
    }

    /**
     * Check if the current product has the RMA tag.
     *
     * @param int|WC_Product $product Product ID or product object
     * @return bool True if product has RMA tag
     */
    public function has_rma_tag( $product )
    {
        $product_id = 0;

        if ( is_numeric( $product ) ) {
            $product_id = (int) $product;
        } elseif ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) {
            $product_id = $product->get_id();
        }

        if ( ! $product_id ) {
            return false;
        }

        return has_term( self::RMA_TAG_SLUG, 'product_tag', $product_id );
    }

    /**
     * Inject scoped CSS for the RMA form on products tagged "rma"
     */
    public function add_rma_product_css()
    {
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$this->has_rma_tag($product)) {
            return;
        }
        
        ?>
        <style type="text/css">
        /* RMA form container */
        .apw-woo-rma-form {
            margin-bottom: 30px;
            padding: 20px;
            background-color: rgba(215, 224, 226, 0.3);
            border-radius: 8px;
            border-left: 4px solid #178093;
        }
        
        /* RMA Form Title */
        .apw-woo-rma-form h3 {
            font-family: var(--apw-font-family);
            font-weight: var(--apw-font-bold);
            font-size: 1.5rem;
            color: var(--apw-woo-text-color);
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        /* Form Row */
        .apw-woo-rma-form .form-row {
            margin-bottom: 20px;
        }
        
        /* Form Labels */
        .apw-woo-rma-form label {
            display: block;
            font-family: var(--apw-font-family);
            font-weight: var(--apw-font-bold);
            font-size: var(--apw-woo-content-font-size);
            color: var(--apw-woo-text-color);
            margin-bottom: 8px;
        }
        
        /* Required Field Indicator */
        .apw-woo-rma-form .required {
            color: #C60307;
            font-weight: var(--apw-font-bold);
            margin-left: 4px;
        }
        
        /* Form Inputs */
        .apw-woo-rma-form select,
        .apw-woo-rma-form input[type="text"],
        .apw-woo-rma-form textarea {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid var(--apw-woo-dropdown-border);
            border-radius: 20px;
            background-color: var(--apw-woo-dropdown-bg);
            font-family: var(--apw-font-family);
            font-size: var(--apw-woo-content-font-size);
            color: var(--apw-woo-text-color);
            box-shadow: none;
        }
        
        /* Textarea Specific Styles */
        .apw-woo-rma-form textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Select Dropdown Styling */
        .apw-woo-rma-form select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="6" viewBox="0 0 12 6"><path fill="%230D252C" d="M0 0l6 6 6-6z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
            padding-right: 40px;
        }
        
        /* Focus States */
        .apw-woo-rma-form select:focus,
        .apw-woo-rma-form input[type="text"]:focus,
        .apw-woo-rma-form textarea:focus {
            outline: none;
            border-color: #178093;
            box-shadow: 0 0 0 1px #178093;
        }
        
        /* Error State */
        .apw-woo-rma-form select.error,
        .apw-woo-rma-form input[type="text"].error,
        .apw-woo-rma-form textarea.error {
            border-color: #C60307;
        }
        
        /* Conditional Fields */
        .apw-woo-rma-form .conditional-field {
            display: none;
        }
        </style>
        <?php
    }
    /**
     * Ensure ACF front-end form functions are loaded before rendering the form
     *
     * acf_form_head() must be called before any output
     */
    public function acf_form_head()
    {
        if ( function_exists( 'acf_form_head' ) && is_product() && $this->has_rma_tag( get_queried_object() ) ) {
            acf_form_head();
        }
    }

    /**
     * Output the RMA form before the Add to Cart button
     */
    public function display_rma_form() {
        if ( ! is_product() || ! $this->has_rma_tag( get_queried_object() ) ) {
            return;
        }
        echo '<div class="apw-woo-rma-form">';
        echo '<h3>' . esc_html__( 'Return Merchandise Authorization', 'apw-woo-plugin' ) . '</h3>';
        if ( function_exists( 'acf_form' ) ) {
            acf_form( array(
                'id'                 => 'apw-rma-acf-form',
                'post_id'            => 'new',
                'field_groups'       => array( 'group_6814c1fe4e6f5' ),
                'html_submit_button' => '<button type="submit" class="single_add_to_cart_button button alt">%s</button>',
                'return'             => get_permalink() . '?rma_submitted=1',
                'submit_value'       => __( 'Submit RMA', 'apw-woo-plugin' ),
                'honeypot'           => true,
                'uploader'           => 'basic',
                'updated_message'    => false,
            ) );
        } else {
            echo '<p>' . esc_html__( 'RMA form unavailable. ACF not active.', 'apw-woo-plugin' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Validate the RMA form data when adding to cart
     *
     * @param bool $passed Whether validation has passed so far
     * @param int $product_id Product ID being added to the cart
     * @param int $quantity Quantity of the product being added
     * @return bool Whether validation has passed
     */
    public function validate_rma_form($passed, $product_id, $quantity)
    {
        // Only validate if this product has the RMA tag
        if (!$this->has_rma_tag($product_id)) {
            return $passed;
        }
        
        // Check if we have RMA form data
        if (!isset($_POST['apw_rma_data']) || !is_array($_POST['apw_rma_data'])) {
            wc_add_notice(__('Please complete the RMA form before adding this product to cart.', 'apw-woo-plugin'), 'error');
            return false;
        }
        
        // Verify nonce
        if (!isset($_POST['apw_rma_form_nonce']) || !wp_verify_nonce($_POST['apw_rma_form_nonce'], 'apw_rma_form')) {
            wc_add_notice(__('Security check failed. Please refresh the page and try again.', 'apw-woo-plugin'), 'error');
            return false;
        }
        
        $rma_data = $_POST['apw_rma_data'];
        $errors = [];
        
        // Validate required fields
        if (empty($rma_data['reason'])) {
            $errors[] = __('Please provide a reason for return.', 'apw-woo-plugin');
        }
        
        if (empty($rma_data['purchase_date'])) {
            $errors[] = __('Please provide the original purchase date.', 'apw-woo-plugin');
        } else {
            // Validate date format (MM/DD/YYYY)
            if (!preg_match('/^(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])\/\d{4}$/', $rma_data['purchase_date'])) {
                $errors[] = __('Please enter the purchase date in MM/DD/YYYY format.', 'apw-woo-plugin');
            }
        }
        
        if (empty($rma_data['condition'])) {
            $errors[] = __('Please select the product condition.', 'apw-woo-plugin');
        } elseif ($rma_data['condition'] === 'damaged' && empty($rma_data['damage_details'])) {
            $errors[] = __('Please provide details about the product damage.', 'apw-woo-plugin');
        }
        
        // If we have errors, add them as notices and fail validation
        if (!empty($errors)) {
            foreach ($errors as $error) {
                wc_add_notice($error, 'error');
            }
            return false;
        }
        
        // Store the validated data in session for later use
        WC()->session->set('apw_rma_form_data', $rma_data);
        
        // If we got here, validation passed
        return $passed;
    }
    
    /**
     * Add RMA form data to cart item
     *
     * @param array $cart_item_data Cart item data
     * @param int $product_id Product ID
     * @param int $variation_id Variation ID
     * @return array Modified cart item data
     */
    public function add_rma_data_to_cart_item($cart_item_data, $product_id, $variation_id)
    {
        // Only add RMA data if this product has the RMA tag
        if (!$this->has_rma_tag($product_id)) {
            return $cart_item_data;
        }
        
        // Get RMA data from session
        $rma_data = WC()->session->get('apw_rma_form_data', array());
        
        if (!empty($rma_data)) {
            // Add RMA data to cart item
            $cart_item_data[self::META_KEY_PREFIX . 'data'] = $rma_data;
            
            // Clear session data to prevent it from being added to other products
            WC()->session->set('apw_rma_form_data', array());
        }
        
        return $cart_item_data;
    }
    
    /**
     * Display RMA data in cart
     *
     * @param array $item_data Existing item data
     * @param array $cart_item Cart item
     * @return array Modified item data
     */
    public function display_rma_data_in_cart($item_data, $cart_item)
    {
        if (isset($cart_item[self::META_KEY_PREFIX . 'data']) && is_array($cart_item[self::META_KEY_PREFIX . 'data'])) {
            $rma_data = $cart_item[self::META_KEY_PREFIX . 'data'];
            
            // Add RMA reason to cart display
            $item_data[] = array(
                'key'   => __('Return Reason', 'apw-woo-plugin'),
                'value' => wc_clean($rma_data['reason']),
                'display' => '',
            );
            
            // Add purchase date to cart display
            $item_data[] = array(
                'key'   => __('Purchase Date', 'apw-woo-plugin'),
                'value' => wc_clean($rma_data['purchase_date']),
                'display' => '',
            );
            
            // Add condition to cart display
            $condition_labels = array(
                'new' => __('New/Unused', 'apw-woo-plugin'),
                'like_new' => __('Like New', 'apw-woo-plugin'),
                'used' => __('Used', 'apw-woo-plugin'),
                'damaged' => __('Damaged', 'apw-woo-plugin'),
            );
            
            $condition = isset($condition_labels[$rma_data['condition']]) 
                ? $condition_labels[$rma_data['condition']] 
                : $rma_data['condition'];
                
            $item_data[] = array(
                'key'   => __('Condition', 'apw-woo-plugin'),
                'value' => $condition,
                'display' => '',
            );
            
            // Add damage details if applicable
            if ($rma_data['condition'] === 'damaged' && !empty($rma_data['damage_details'])) {
                $item_data[] = array(
                    'key'   => __('Damage Details', 'apw-woo-plugin'),
                    'value' => wc_clean($rma_data['damage_details']),
                    'display' => '',
                );
            }
            
            // Add serial number if provided
            if (!empty($rma_data['serial'])) {
                $item_data[] = array(
                    'key'   => __('Serial Number', 'apw-woo-plugin'),
                    'value' => wc_clean($rma_data['serial']),
                    'display' => '',
                );
            }
        }
        
        return $item_data;
    }
    
    /**
     * Add RMA data to order line item
     *
     * @param WC_Order_Item_Product $item Order item
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param WC_Order $order Order object
     */
    public function add_rma_data_to_order_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values[self::META_KEY_PREFIX . 'data'])) {
            $rma_data = $values[self::META_KEY_PREFIX . 'data'];
            
            // Store RMA data as order item meta
            $item->add_meta_data(self::META_KEY_PREFIX . 'data', $rma_data);
            
            // Also store individual fields for easier access
            foreach ($rma_data as $key => $value) {
                $item->add_meta_data(self::META_KEY_PREFIX . $key, $value, true);
            }
        }
    }
    
    /**
     * Add RMA column header in admin order items table
     */
    public function add_rma_column_header()
    {
        echo '<th class="apw-rma-info">' . esc_html__('RMA Info', 'apw-woo-plugin') . '</th>';
    }
    
    /**
     * Add RMA column value in admin order items table
     *
     * @param WC_Order_Item_Product $item Order item
     * @param WC_Order $order Order object
     * @param int $item_id Order item ID
     */
    public function add_rma_column_value($item, $order, $item_id)
    {
        echo '<td class="apw-rma-info">';
        
        $rma_data = $item->get_meta(self::META_KEY_PREFIX . 'data');
        
        if (!empty($rma_data) && is_array($rma_data)) {
            echo '<a href="#" class="button show-rma-details" data-item-id="' . esc_attr($item_id) . '">';
            esc_html_e('View RMA Details', 'apw-woo-plugin');
            echo '</a>';
            
            // Hidden div with RMA details that will be shown in a modal
            echo '<div id="rma-details-' . esc_attr($item_id) . '" class="rma-details-popup" style="display:none;">';
            
            echo '<h4>' . esc_html__('RMA Information', 'apw-woo-plugin') . '</h4>';
            
            echo '<p><strong>' . esc_html__('Reason for Return:', 'apw-woo-plugin') . '</strong><br>';
            echo esc_html($rma_data['reason']) . '</p>';
            
            echo '<p><strong>' . esc_html__('Purchase Date:', 'apw-woo-plugin') . '</strong><br>';
            echo esc_html($rma_data['purchase_date']) . '</p>';
            
            // Condition with proper label
            $condition_labels = array(
                'new' => __('New/Unused', 'apw-woo-plugin'),
                'like_new' => __('Like New', 'apw-woo-plugin'),
                'used' => __('Used', 'apw-woo-plugin'),
                'damaged' => __('Damaged', 'apw-woo-plugin'),
            );
            
            $condition = isset($condition_labels[$rma_data['condition']]) 
                ? $condition_labels[$rma_data['condition']] 
                : $rma_data['condition'];
                
            echo '<p><strong>' . esc_html__('Condition:', 'apw-woo-plugin') . '</strong><br>';
            echo esc_html($condition) . '</p>';
            
            // Damage details if applicable
            if ($rma_data['condition'] === 'damaged' && !empty($rma_data['damage_details'])) {
                echo '<p><strong>' . esc_html__('Damage Details:', 'apw-woo-plugin') . '</strong><br>';
                echo esc_html($rma_data['damage_details']) . '</p>';
            }
            
            // Serial number if provided
            if (!empty($rma_data['serial'])) {
                echo '<p><strong>' . esc_html__('Serial Number:', 'apw-woo-plugin') . '</strong><br>';
                echo esc_html($rma_data['serial']) . '</p>';
            }
            
            echo '</div>';
            
            // Add JavaScript to handle the modal display
            wc_enqueue_js("
                jQuery('.show-rma-details').on('click', function(e) {
                    e.preventDefault();
                    var itemId = jQuery(this).data('item-id');
                    jQuery('#rma-details-' + itemId).dialog({
                        title: '" . esc_js(__('RMA Details', 'apw-woo-plugin')) . "',
                        width: 400,
                        modal: true,
                        resizable: false,
                        closeOnEscape: true,
                        create: function() {
                            // Style the dialog
                            jQuery('.ui-dialog-titlebar').css('background', '#0073aa');
                            jQuery('.ui-dialog-titlebar').css('color', '#fff');
                            jQuery('.ui-dialog-titlebar-close').css('color', '#fff');
                        }
                    });
                });
            ");
        } else {
            echo '<span class="na">&ndash;</span>';
        }
        
        echo '</td>';
    }
}

/**
 * Initialize the RMA Form class.
 * Called during plugin initialization.
 */
function apw_woo_initialize_rma_form()
{
    APW_Woo_RMA_Form::get_instance();
}
