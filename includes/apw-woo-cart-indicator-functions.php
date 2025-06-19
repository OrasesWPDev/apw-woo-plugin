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
function apw_woo_enqueue_cart_indicator_assets()
{
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
function apw_woo_add_cart_count_to_body()
{
    if (function_exists('WC') && isset(WC()->cart)) {
        $cart_count = WC()->cart->get_cart_contents_count();

        // Check if user is logged in
        // *** MODIFICATION START: Always output count, JS handles visibility logic ***
        // For logged-in users, always show the count (even if it's 0)
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
        // *** MODIFICATION END ***
    }
}

add_action('wp_footer', 'apw_woo_add_cart_count_to_body', 10);

/**
 * Add cart update event listener to ensure indicators update when cart changes
 */
function apw_woo_add_cart_update_listener()
{
    if (is_cart() || is_checkout()) {
        echo '<script type="text/javascript">
            if (typeof jQuery !== "undefined") {
                jQuery(function($) {
                    // Listen for cart form submissions
                    $("body").on("submit", ".woocommerce-cart-form", function() {
                        setTimeout(function() {
                            // Trigger our custom event that the cart indicators listen for
                            $(document.body).trigger("updated_cart_totals");
                        }, 500);
                    });

                    // Listen for quantity changes
                    $("body").on("change", ".woocommerce-cart-form input.qty", function() {
                        // Add a small delay to allow WooCommerce to update
                        setTimeout(function() {
                            $(document.body).trigger("updated_cart_totals");
                        }, 500);
                    });
                });
            }
        </script>';
    }
}

add_action('wp_footer', 'apw_woo_add_cart_update_listener', 20);

/**
 * Add AJAX action to get the current cart count
 */
function apw_woo_get_cart_count()
{
    $count = 0;
    $is_logged_in = is_user_logged_in(); // We still need this for the response data

    if (function_exists('WC') && isset(WC()->cart)) {
        // Get actual cart count
        $count = WC()->cart->get_cart_contents_count();

        // *** MODIFICATION START: Removed logic that forced empty string for guests ***
        // For logged-out users, return empty string to hide bubble but keep link visible
        // if (!$is_logged_in) {
        //     $count = '';
        // }
        // For logged-in users, we return the actual count (even if it's 0)
        // *** MODIFICATION END ***
    }

    wp_send_json_success(array(
        'count' => $count, // Now sends the actual count for guests too
        'is_logged_in' => $is_logged_in // Still useful for JS if needed elsewhere
    ));
}

add_action('wp_ajax_apw_woo_get_cart_count', 'apw_woo_get_cart_count');
add_action('wp_ajax_nopriv_apw_woo_get_cart_count', 'apw_woo_get_cart_count');