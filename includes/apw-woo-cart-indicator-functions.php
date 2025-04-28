<?php
/**
 * Cart Quantity Indicator Functions for APW WooCommerce Plugin
 *
 * Adds functionality to display the cart quantity as a CSS pseudo-element
 * for elements with the class 'cart-quantity-indicator'.
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue cart indicator styles and scripts
 */
function apw_woo_enqueue_cart_indicator_assets() {
    // Enqueue the CSS file
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
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('Cart indicator CSS file not found at: ' . $css_path);
        }
    }
    
    // We're using the existing public.js file for the JavaScript functionality
    // Make sure it's loaded with the cart fragments as a dependency
    wp_enqueue_script('wc-cart-fragments');
}
add_action('wp_enqueue_scripts', 'apw_woo_enqueue_cart_indicator_assets');

/**
 * Add initial cart count as a data attribute to the body
 * This helps JavaScript find the count even before fragments are loaded
 */
function apw_woo_add_cart_count_to_body() {
    if (function_exists('WC') && isset(WC()->cart)) {
        $cart_count = WC()->cart->get_cart_contents_count();
        echo '<script type="text/javascript">
            document.body.setAttribute("data-cart-count", "' . esc_js($cart_count) . '");
        </script>';
    }
}
add_action('wp_footer', 'apw_woo_add_cart_count_to_body', 10);

/**
 * Add AJAX action to get the current cart count
 */
function apw_woo_get_cart_count() {
    $count = 0;
    if (function_exists('WC') && isset(WC()->cart)) {
        $count = WC()->cart->get_cart_contents_count();
    }
    wp_send_json_success(array('count' => $count));
}
add_action('wp_ajax_apw_woo_get_cart_count', 'apw_woo_get_cart_count');
add_action('wp_ajax_nopriv_apw_woo_get_cart_count', 'apw_woo_get_cart_count');
