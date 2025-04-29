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
        // Get cart count - works for both logged-in and guest users with sessions
        $cart_count = WC()->cart->get_cart_contents_count();

        // Always output the count (or 0) - JS/CSS will handle display based on value
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
    }
}
add_action('wp_footer', 'apw_woo_add_cart_count_to_body', 10);

/**
 * Check if WooCommerce functions are available
 * 
 * @return bool True if WooCommerce functions are available
 */
function apw_woo_wc_functions_available() {
    return function_exists('WC') && function_exists('wc_get_page_permalink');
}

/**
 * Add cart update event listener to ensure indicators update when cart changes
 */
function apw_woo_add_cart_update_listener() {
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
 * Add inline CSS for My Account buttons
 * This ensures our button styles take precedence over theme styles
 */
function apw_woo_add_inline_button_css() {
    if (is_account_page()) {
        // Add inline CSS with !important rules for the specific buttons
        $inline_css = "
            /* Target both button types in message containers */
            .woocommerce-account .woocommerce-MyAccount-content .message-container .woocommerce-Button,
            .woocommerce-account .woocommerce-MyAccount-content .message-container .button.wc-forward,
            .woocommerce-account .woocommerce-MyAccount-content .message-container a.wc-forward {
                background: linear-gradient(204deg, #244B5A, #178093) !important;
                background-color: #244B5A !important;
                color: #ffffff !important;
                font-family: var(--apw-font-family) !important;
                font-weight: var(--apw-font-bold) !important;
                font-size: 1.1rem !important;
                text-transform: uppercase !important;
                padding: 12px 30px !important;
                border-radius: 58px !important;
                display: block !important;
                margin-top: 1.5rem !important;
                width: fit-content !important;
                border: none !important;
                text-decoration: none !important;
                box-shadow: none !important;
            }
            
            /* Ensure text content has proper styling */
            .woocommerce-account .woocommerce-MyAccount-content .message-container,
            .woocommerce-account .woocommerce-MyAccount-content .message-container > *:not(a),
            .woocommerce-account .woocommerce-MyAccount-content .message-container::before {
                font-family: var(--apw-font-family) !important;
                font-size: var(--apw-woo-content-font-size) !important;
                color: var(--apw-woo-text-color) !important;
                line-height: 1.5 !important;
            }
            
            /* Container styling */
            .woocommerce-account .woocommerce-MyAccount-content .message-container {
                background-color: rgba(182, 198, 204, 0.1) !important;
                border-radius: 8px !important;
                border-left: 4px solid #178093 !important;
                padding: 1.5rem !important;
                margin: 2rem 0 !important;
            }
        ";
        wp_add_inline_style('apw-woo-style', $inline_css);
    }
}
add_action('wp_enqueue_scripts', 'apw_woo_add_inline_button_css', 999); // Very high priority

/**
 * Anti-loop login redirect for WooCommerce restricted pages
 * Prevents redirect loops by properly detecting login pages vs. account pages
 */
function apw_woo_anti_loop_login_redirect() {
    // Skip for logged-in users, admin, AJAX, or cron
    if (is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    
    // Get current path and query string
    $current_path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $query_string = parse_url($current_path, PHP_URL_QUERY);
    parse_str($query_string ?: '', $query_params);
    
    // CRITICAL: Don't redirect if we're already on a login form
    // Check for login-specific query parameters or the login form
    if (
        // If we already have our login notice parameter, don't redirect again
        isset($query_params['apw_login_notice']) ||
        // If WooCommerce is showing the login form
        (function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) ||
        // If we're on a specific login-related endpoint
        (isset($query_params['action']) && in_array($query_params['action'], ['login', 'register', 'lostpassword'])) ||
        // Check for WooCommerce login form in the DOM (more reliable)
        (did_action('woocommerce_before_customer_login_form') || has_action('woocommerce_before_customer_login_form'))
    ) {
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('ANTI-LOOP: Login form detected, skipping redirect');
        }
        return;
    }
    
    // Check for cart, checkout, or account pages in the URL path
    if (
        // URL path-based detection
        strpos($current_path, '/cart') !== false || 
        strpos($current_path, '/checkout') !== false || 
        strpos($current_path, '/my-account') !== false ||
        strpos($current_path, '/account') !== false ||
        // Function-based detection (more reliable but runs later)
        (function_exists('is_cart') && is_cart()) || 
        (function_exists('is_checkout') && is_checkout()) || 
        (function_exists('is_account_page') && is_account_page())
    ) {
        // Get the login URL (WooCommerce account page)
        $login_url = function_exists('wc_get_page_permalink') ? 
            wc_get_page_permalink('myaccount') : 
            wp_login_url(home_url());
        
        // Determine which page type for the notice
        $page_type = 'required';
        if (strpos($current_path, '/cart') !== false || (function_exists('is_cart') && is_cart())) {
            $page_type = 'cart';
        } elseif (strpos($current_path, '/checkout') !== false || (function_exists('is_checkout') && is_checkout())) {
            $page_type = 'checkout';
        } elseif (strpos($current_path, '/account') !== false || strpos($current_path, '/my-account') !== false || 
                 (function_exists('is_account_page') && is_account_page())) {
            $page_type = 'account';
        }
        
        // Add our notice parameter
        $login_url = add_query_arg('apw_login_notice', $page_type, $login_url);
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('ANTI-LOOP: Redirecting to login: ' . $login_url);
        }
        
        // Perform a clean redirect and exit immediately
        wp_safe_redirect($login_url);
        exit;
    }
}

// Remove all previous redirect hooks
remove_action('init', 'apw_woo_early_restricted_page_check', 1);
remove_action('template_redirect', 'apw_woo_redirect_restricted_pages', 5);
remove_action('template_redirect', 'apw_woo_force_login_for_woocommerce_pages', 1);
remove_action('wp_footer', 'apw_woo_add_js_redirect_check', 99);
remove_action('init', 'apw_woo_simple_login_redirect', 1);
remove_action('init', 'apw_woo_anti_loop_login_redirect', 1);

// Add our anti-loop hook at a later point in the WordPress lifecycle
// Using template_redirect instead of init to have access to WooCommerce conditional functions
add_action('template_redirect', 'apw_woo_anti_loop_login_redirect', 5);

/**
 * Display a notice on the login form based on which page the user was trying to access
 */
function apw_woo_display_login_notice() {
    // Check if our query parameter is set
    if (isset($_GET['apw_login_notice'])) {
        $page_type = sanitize_text_field($_GET['apw_login_notice']);
        $message = '';
        
        // Set message based on page type
        switch ($page_type) {
            case 'cart':
                $message = __('Please log in to view your cart.', 'apw-woo-plugin');
                break;
            case 'checkout':
                $message = __('Please log in to proceed to checkout.', 'apw-woo-plugin');
                break;
            case 'account':
                $message = __('Please log in to view your account details.', 'apw-woo-plugin');
                break;
            default:
                $message = __('Please log in to access this page.', 'apw-woo-plugin');
                break;
        }
        
        // Display the notice with a custom class for styling
        if (function_exists('wc_print_notice')) {
            wc_print_notice($message, 'notice apw-login-required-notice');
        } else {
            echo '<div class="woocommerce-info apw-login-required-notice">' . esc_html($message) . '</div>';
        }
    }
}
add_action('woocommerce_before_customer_login_form', 'apw_woo_display_login_notice', 10);
// Also add to notices hook for themes that might not use the standard login form
add_action('woocommerce_before_checkout_form', 'apw_woo_display_login_notice', 5);
add_action('woocommerce_before_cart', 'apw_woo_display_login_notice', 5);

// JavaScript fallback redirect has been removed to prevent redirect loops

/**
 * Add AJAX action to get the current cart count
 */
function apw_woo_get_cart_count() {
    $count = 0;
    $is_logged_in = is_user_logged_in();
    
    if (function_exists('WC') && isset(WC()->cart)) {
        // Get actual cart count
        $count = WC()->cart->get_cart_contents_count();
        
        // For logged-out users, return empty string to hide bubble but keep link visible
        if (!$is_logged_in) {
            $count = '';
        }
        // For logged-in users, we return the actual count (even if it's 0)
    }
    
    wp_send_json_success(array(
        'count' => $count,
        'is_logged_in' => $is_logged_in
    ));
}
add_action('wp_ajax_apw_woo_get_cart_count', 'apw_woo_get_cart_count');
add_action('wp_ajax_nopriv_apw_woo_get_cart_count', 'apw_woo_get_cart_count');
