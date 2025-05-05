<?php
/**
 * Asset Management for APW WooCommerce Plugin (Revised for Clarity)
 *
 * @package APW_Woo_Plugin
 * @since 1.15.9
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class APW_Woo_Assets
{

    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_and_enqueue_assets'), 20);
    }

    public static function register_and_enqueue_assets()
    {
        // Only proceed on relevant frontend pages
        if (is_admin() || !(is_woocommerce() || is_cart() || is_checkout() || is_account_page() || is_product())) {
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log('Assets: Not a relevant frontend page. Skipping asset loading.');
            }
            return;
        }

        $current_page_type = self::get_current_page_type();
        $assets_url = APW_WOO_PLUGIN_URL . 'assets/';
        $assets_dir = APW_WOO_PLUGIN_DIR . 'assets/';

        if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log("Assets: Loading assets on '{$current_page_type}' page type.");
        }

        // --- Enqueue Stylesheets ---
        self::enqueue_styles($assets_dir, $assets_url, $current_page_type);

        // --- Enqueue Core Public Script ---
        $public_js_path = $assets_dir . 'js/apw-woo-public.js';
        if (file_exists($public_js_path)) {
            wp_enqueue_script(
                'apw-woo-scripts', // Main public script handle
                $assets_url . 'js/apw-woo-public.js',
                array('jquery'),
                filemtime($public_js_path),
                true // Load in footer
            );
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log('Assets: Enqueued apw-woo-public.js');
            }
        } elseif (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('Assets Warning: File not found: apw-woo-public.js', 'warning');
        }

        // --- Localize Common Data for Core Script ---
        $page_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'page_type' => $current_page_type,
            'nonce' => wp_create_nonce('apw_woo_nonce'),
            'plugin_url' => APW_WOO_PLUGIN_URL,
            'debug_mode' => APW_WOO_DEBUG_MODE
        );
        $page_data = apply_filters('apw_woo_js_data', $page_data, $current_page_type);

        if (wp_script_is('apw-woo-scripts', 'enqueued')) {
            wp_localize_script('apw-woo-scripts', 'apwWooData', $page_data);
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log('Assets: Localized common data for apw-woo-scripts.');
            }
        }

        // --- Conditionally Enqueue Dynamic Pricing Script ---
        if (self::is_product_page_condition()) {
            $dynamic_pricing_js_path = $assets_dir . 'js/apw-woo-dynamic-pricing.js';
            if (file_exists($dynamic_pricing_js_path)) {
                wp_enqueue_script(
                    'apw-woo-dynamic-pricing-scripts',
                    $assets_url . 'js/apw-woo-dynamic-pricing.js',
                    array('jquery'), // Dependency
                    filemtime($dynamic_pricing_js_path),
                    true // Load in footer
                );
                // Localize specific data for dynamic pricing
                wp_localize_script(
                    'apw-woo-dynamic-pricing-scripts',
                    'apwWooDynamicPricing', // Specific object name
                    array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('apw_woo_dynamic_pricing'),
                        'price_selector' => '.apw-woo-price-display, .woocommerce-Price-amount, .price .amount',
                        'is_product' => true,
                        'debug_mode' => APW_WOO_DEBUG_MODE
                    )
                );
                if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                    apw_woo_log('Assets: Enqueued apw-woo-dynamic-pricing.js for product page.');
                }
            } elseif (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log('Assets Warning: File not found: apw-woo-dynamic-pricing.js', 'warning');
            }
        }

        // --- Conditionally Enqueue Intuit Integration Script ---
        if ($current_page_type === 'checkout') {
            // First, check if the Intuit Gateway plugin is likely active and its core script is registered
            if (wp_script_is('wc-intuit-payments', 'registered') || wp_script_is('wc-intuit-payments', 'enqueued')) {
                $intuit_integration_js_path = $assets_dir . 'js/apw-woo-intuit-integration.js';
                if (file_exists($intuit_integration_js_path)) {
                    wp_enqueue_script(
                        'apw-woo-intuit-integration', // Consistent handle
                        $assets_url . 'js/apw-woo-intuit-integration.js',
                        array(
                            'jquery',
                            'wc-checkout',
                            'wc-intuit-payments' // Correct Dependency
                        ),
                        filemtime($intuit_integration_js_path),
                        true // Load in footer
                    );
                    // Localize data specifically for this script
                    wp_localize_script(
                        'apw-woo-intuit-integration',
                        'apwWooIntuitData', // Specific object name
                        array( // Only pass necessary data
                            'debug_mode' => APW_WOO_DEBUG_MODE,
                            'is_checkout' => true
                        )
                    );
                    if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                        apw_woo_log('Assets: Enqueued apw-woo-intuit-integration.js for checkout.');
                    }
                } elseif (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                    apw_woo_log('Assets Warning: File not found: apw-woo-intuit-integration.js', 'warning');
                }
            } elseif (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log('Assets Warning: Intuit core script (wc-intuit-payments) not registered or enqueued. Skipping enqueue of apw-woo-intuit-integration.js.', 'warning');
            }
        }

        // --- Conditionally Enqueue Payment Debug Script ---
        $payment_debug_path = $assets_dir . 'js/apw-woo-payment-debug.js';
        if ($current_page_type === 'checkout' && file_exists($payment_debug_path) && APW_WOO_DEBUG_MODE) {
            wp_enqueue_script(
                'apw-woo-payment-debug',
                $assets_url . 'js/apw-woo-payment-debug.js',
                array('jquery', 'apw-woo-scripts'), // Depends on common script
                filemtime($payment_debug_path),
                true
            );
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Assets: Enqueued Payment Debug script.');
            }
        }

        // --- Auto-enqueue any other JS files (if needed) ---
        self::enqueue_other_scripts($assets_dir, $assets_url, $current_page_type);

    }

    /**
     * Helper to check if the current page is a product page using detector or fallback.
     */
    private static function is_product_page_condition()
    {
        global $wp;
        if (class_exists('APW_Woo_Page_Detector') && method_exists('APW_Woo_Page_Detector', 'is_product_page')) {
            return APW_Woo_Page_Detector::is_product_page($wp);
        }
        return function_exists('is_product') && is_product();
    }


    /**
     * Get the current WooCommerce page type (simplified).
     */
    public static function get_current_page_type()
    {
        if (function_exists('is_shop') && is_shop() && !is_search()) {
            return 'shop';
        }
        if (function_exists('is_product') && is_product()) {
            return 'product';
        }
        if (function_exists('is_product_category') && is_product_category()) {
            return 'category';
        }
        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) ? 'order-received' : 'checkout';
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return 'account';
        }
        return 'generic';
    }

    /**
     * Auto-discover and enqueue all stylesheet files with cache busting.
     */
    public static function enqueue_styles($assets_dir, $assets_url, $current_page_type)
    {
        $css_dir = $assets_dir . 'css/';
        $css_url = $assets_url . 'css/';

        if (!is_dir($css_dir)) {
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log("Assets CSS directory not found: {$css_dir}", 'warning');
            }
            return;
        }

        $css_files = glob($css_dir . '*.css');
        if (empty($css_files)) {
            return;
        }

        $loaded_files = []; // Track loaded handles
        $common_handle = 'apw-woo-styles'; // Use consistent handle for main CSS
        $common_file_name = 'woocommerce-custom.css'; // Your main global CSS
        $deps = array();

        // Enqueue common file first
        $common_file_path = $css_dir . $common_file_name;
        if (file_exists($common_file_path)) {
            $css_ver = filemtime($common_file_path);
            wp_register_style($common_handle, $css_url . $common_file_name, [], $css_ver);
            wp_enqueue_style($common_handle);
            $loaded_files[] = $common_handle;
            $deps[] = $common_handle; // Other styles depend on this
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log("Assets: Enqueued common CSS ({$common_file_name})");
            }
        }

        // Enqueue page-specific file
        $page_specific_filename = "apw-woo-{$current_page_type}.css";
        $page_specific_path = $css_dir . $page_specific_filename;
        $page_specific_handle = "apw-woo-{$current_page_type}-styles";
        if (file_exists($page_specific_path)) {
            $css_ver = filemtime($page_specific_path);
            wp_register_style($page_specific_handle, $css_url . $page_specific_filename, $deps, $css_ver);
            wp_enqueue_style($page_specific_handle);
            $loaded_files[] = $page_specific_handle;
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log("Assets: Enqueued {$current_page_type}-specific CSS ({$page_specific_filename})");
            }
        }

        // Enqueue FAQ styles
        $faq_style_filename = 'faq-styles.css';
        $faq_style_path = $css_dir . $faq_style_filename;
        $faq_handle = 'apw-woo-faq-styles';
        if (file_exists($faq_style_path)) {
            $css_ver = filemtime($faq_style_path);
            wp_register_style($faq_handle, $css_url . $faq_style_filename, $deps, $css_ver);
            wp_enqueue_style($faq_handle);
            $loaded_files[] = $faq_handle;
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log("Assets: Enqueued FAQ CSS ({$faq_style_filename})");
            }
        }

        // Enqueue remaining CSS files
        foreach ($css_files as $file) {
            $filename = basename($file);
            $file_path = $css_dir . $filename;

            // Generate handle
            $handle_base = str_replace(array('apw-woo-', '.min.css', '.css'), array('', '', ''), $filename);
            $handle = 'apw-woo-' . sanitize_title($handle_base) . '-styles';

            // Skip if already loaded or doesn't exist
            if (in_array($handle, $loaded_files) || !file_exists($file_path)) {
                continue;
            }

            $css_ver = filemtime($file_path);
            wp_register_style($handle, $css_url . $filename, $deps, $css_ver);
            wp_enqueue_style($handle);
            $loaded_files[] = $handle; // Mark as loaded
            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log("Assets: Enqueued additional CSS ({$filename})");
            }
        }
    }

    /**
     * Enqueues any other JS files not handled by specific functions.
     */
    public static function enqueue_other_scripts($assets_dir, $assets_url, $current_page_type)
    {
        $js_dir = $assets_dir . 'js/';
        $js_url = $assets_url . 'js/';

        if (!is_dir($js_dir)) {
            return; // Skip if dir doesn't exist
        }

        $js_files = glob($js_dir . '*.js');
        if (empty($js_files)) {
            return;
        }

        $excluded_scripts = [
            'apw-woo-public.js',
            'apw-woo-dynamic-pricing.js',
            'apw-woo-intuit-integration.js',
            'apw-woo-payment-debug.js'
        ];

        // Base dependencies - typically includes jQuery and your main public script if loaded
        $base_deps = array('jquery');
        if (wp_script_is('apw-woo-scripts', 'enqueued')) {
            $base_deps[] = 'apw-woo-scripts';
        }

        foreach ($js_files as $file) {
            $filename = basename($file);
            $file_path = $js_dir . $filename;

            // Skip excluded and non-existent files
            if (in_array($filename, $excluded_scripts) || !file_exists($file_path)) {
                continue;
            }

            $handle_base = str_replace(array('apw-woo-', '.min.js', '.js'), array('', '', ''), $filename);
            $handle = 'apw-woo-' . sanitize_title($handle_base) . '-scripts';

            // Prevent double enqueuing
            if (wp_script_is($handle, 'enqueued') || wp_script_is($handle, 'registered')) {
                continue;
            }

            $js_ver = filemtime($file_path);
            wp_enqueue_script($handle, $js_url . $filename, $base_deps, $js_ver, true);

            if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log("Assets: Enqueued other JS file ({$filename})");
            }
        }
    }
}