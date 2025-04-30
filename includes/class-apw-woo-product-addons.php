<?php
/**
 * Product Add-ons integration for APW Woo Plugin
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle Product Add-ons integration
 */
class APW_Woo_Product_Addons {
    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

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
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Remove Product Add-ons from default location
        add_action('init', array($this, 'remove_default_addons_location')); // RE-ENABLED

        // Add Product Add-ons at our custom location
        add_action('woocommerce_single_product_summary', array($this, 'display_product_addons'), 45); // RE-ENABLED

        // Register hooks for visualization if in debug mode
        if (APW_WOO_DEBUG_MODE && current_user_can('manage_options')) {
            $this->register_visualization_hooks();
        }
    }

    /**
     * Register Product Add-ons hooks for visualization
     */
    public function register_visualization_hooks() {
        // Check if visualization function exists before using it
        if (!function_exists('apw_woo_hook_visualizer')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Hook visualizer function not found - visualization skipped');
            }
            return;
        }

        // Add these hooks to the visualization system
        $addon_hooks = array(
            'woocommerce_product_addons_start',
            'woocommerce_product_addons_option',
            'woocommerce_product_addons_end',
            'woocommerce_product_addons_option_price',
            'apw_woo_before_product_addons',
            'apw_woo_after_product_addons'
        );

        // Register our own visualization directly
        foreach ($addon_hooks as $hook) {
            add_action($hook, apw_woo_hook_visualizer($hook), 999);
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Registered Product Add-ons hooks for visualization');
        }
    }

    /**
     * Remove Product Add-ons from default location
     */
    public function remove_default_addons_location() {
        // Check if Product Add-ons plugin is active
        if (!class_exists('WC_Product_Addons')) {
            return;
        }

        // Remove from default location
        remove_action('woocommerce_before_add_to_cart_button', array('WC_Product_Addons_Display', 'display'), 10);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Removed Product Add-ons from default location');
        }
    }

    /**
     * Display product add-ons between product meta and sharing
     */
    public function display_product_addons() {
        // Check if Product Add-ons plugin is active
        if (!class_exists('WC_Product_Addons')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Product Add-ons plugin not active');
            }
            return;
        }

        global $product;
        // Verify we have a valid product
        if (!is_a($product, 'WC_Product')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Invalid product object when displaying product add-ons');
            }
            return;
        }

        // Custom hook before add-ons (for visualization)
        do_action('apw_woo_before_product_addons', $product);

        echo '<div class="apw-woo-product-addons">';
        echo '<h3 class="apw-woo-product-addons-title">' . esc_html__('Product Options', 'apw-woo-plugin') . '</h3>';

        // Custom action to mark the start of add-ons
        do_action('woocommerce_product_addons_start', $product);

        // Call the Product Add-ons display function directly
        // Ensure the class and method exist before calling
        if (class_exists('WC_Product_Addons_Display') && method_exists('WC_Product_Addons_Display', 'display')) {
             WC_Product_Addons_Display::display();
             if (APW_WOO_DEBUG_MODE) {
                 apw_woo_log('Called WC_Product_Addons_Display::display() directly.');
             }
        } else {
             if (APW_WOO_DEBUG_MODE) {
                 apw_woo_log('WC_Product_Addons_Display::display() not found.', 'error');
             }
        }

        // Custom action to mark the end of add-ons
        do_action('woocommerce_product_addons_end', $product);

        echo '</div>'; // .apw-woo-product-addons

        // Custom hook after add-ons (for visualization)
        do_action('apw_woo_after_product_addons', $product);
    }
}
