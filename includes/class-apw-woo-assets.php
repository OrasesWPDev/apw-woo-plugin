<?php
/**
 * Asset Management for APW WooCommerce Plugin
 *
 * @package APW_Woo_Plugin
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Assets Class
 *
 * Handles all CSS and JavaScript asset registration, enqueueing,
 * and cache busting functionality.
 *
 * @since 1.0.0
 */
class APW_Woo_Assets
{

    /**
     * Initialize the asset system
     *
     * @return void
     * @since 1.0.0
     */
    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    /**
     * Register and enqueue CSS/JS assets with cache busting
     *
     * Dynamically loads all CSS and JS files found in the assets directories,
     * with automatic cache busting based on file modification times.
     *
     * @return void
     * @since 1.0.0
     */
    public static function register_assets()
    {
        // Only load on WooCommerce-related pages for better performance
        if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
            return;
        }

        // Determine the current page type for targeted asset loading
        $current_page_type = self::get_current_page_type();

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Loading assets on {$current_page_type} page");
        }

        // Setup paths
        $assets_url = APW_WOO_PLUGIN_URL . 'assets/';
        $assets_dir = APW_WOO_PLUGIN_DIR . 'assets/';

        // Auto-load all CSS files
        self::auto_enqueue_styles($assets_dir, $assets_url, $current_page_type);

        // Auto-load all JS files
        self::auto_enqueue_scripts($assets_dir, $assets_url, $current_page_type);

        // Add page-specific data for JavaScript
        $page_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'page_type' => $current_page_type,
            'nonce' => wp_create_nonce('apw_woo_nonce'),
            'plugin_url' => APW_WOO_PLUGIN_URL
        );

        // Allow extensions to modify JS data
        $page_data = apply_filters('apw_woo_js_data', $page_data, $current_page_type);

        // Only localize if the script exists and has been enqueued
        if (wp_script_is('apw-woo-scripts', 'enqueued')) {
            wp_localize_script('apw-woo-scripts', 'apwWooData', $page_data);
        }

        // Load Intuit tokenization libraries on checkout
        if ( 'checkout' === $current_page_type ) {
            // GenCert for the QuickBooks gateway
            wp_enqueue_script(
                'wfqbc-gencert',
                'https://jstest.intuit.com/js/GenCert.js',
                array(),
                null,
                true
            );
            // sbjs library
            wp_enqueue_script(
                'wfqbc-sbjs',
                'https://sbjs.intuitcdn.net/v1/sb.js',
                array(),
                null,
                true
            );
        }

        // Pass our page_data to the Intuit integration script
        if ( wp_script_is('apw-woo-intuit-integration-scripts', 'enqueued') ) {
            wp_localize_script(
                'apw-woo-intuit-integration-scripts',
                'apwWooData',
                $page_data
            );
        }

        // Pass our page_data to the Dynamic Pricing script
        if ( wp_script_is('apw-woo-dynamic-pricing-scripts', 'enqueued') ) {
            wp_localize_script(
                'apw-woo-dynamic-pricing-scripts',
                'apwWooDynamicPricing',
                $page_data
            );
        }

    }

    /**
     * Get the current WooCommerce page type
     *
     * Helper function to determine the current WooCommerce page context.
     *
     * @return string The current page type identifier
     * @since 1.0.0
     */
    public static function get_current_page_type()
    {
        if (is_shop()) {
            return 'shop';
        } elseif (is_product()) {
            return 'product';
        } elseif (is_product_category()) {
            return 'category';
        } elseif (is_cart()) {
            return 'cart';
        } elseif (is_checkout()) {
            return 'checkout';
        } elseif (is_account_page()) {
            return 'account';
        }

        return 'generic';
    }

    /**
     * Auto-discover and enqueue all stylesheet files with cache busting
     *
     * @param string $assets_dir The local directory path to assets
     * @param string $assets_url The URL path to assets
     * @param string $current_page_type The current page type
     * @return void
     * @since 1.0.0
     */
    public static function auto_enqueue_styles($assets_dir, $assets_url, $current_page_type)
    {
        $css_dir = $assets_dir . 'css/';
        $css_url = $assets_url . 'css/';

        // Skip if CSS directory doesn't exist
        if (!file_exists($css_dir) || !is_dir($css_dir)) {
            return;
        }

        // Get all CSS files
        $css_files = glob($css_dir . '*.css');
        if (empty($css_files)) {
            return;
        }

        // Track which files we've loaded
        $loaded_common = false;

        // First pass - Load the common file first if it exists
        foreach ($css_files as $file) {
            $filename = basename($file);

            // Load common file first
            if ($filename === 'apw-woo-public.css') {
                $css_ver = filemtime($file);
                $handle = 'apw-woo-styles';

                wp_register_style(
                    $handle,
                    $css_url . $filename,
                    array(),
                    $css_ver
                );
                wp_enqueue_style($handle);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Enqueued common CSS with version: ' . $css_ver);
                }

                $loaded_common = true;
                break;
            }
        }

        // Set default dependencies
        $deps = $loaded_common ? array('apw-woo-styles') : array();

        // Second pass - Load page-specific files next
        $page_specific_loaded = false;
        foreach ($css_files as $file) {
            $filename = basename($file);

            // Skip common file (already loaded)
            if ($filename === 'apw-woo-public.css') {
                continue;
            }

            // Load page specific file next
            if ($filename === "apw-woo-{$current_page_type}.css") {
                $css_ver = filemtime($file);
                $handle = "apw-woo-{$current_page_type}-styles";

                wp_register_style(
                    $handle,
                    $css_url . $filename,
                    $deps,
                    $css_ver
                );
                wp_enqueue_style($handle);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Enqueued {$current_page_type}-specific CSS with version: " . $css_ver);
                }

                $page_specific_loaded = true;
                break;
            }
        }

        // Third pass - Load FAQ styles and then all other CSS files
        $faq_style_loaded = false;
        $faq_style_path = $css_dir . 'faq-styles.css';
        if (file_exists($faq_style_path)) {
            $css_ver = filemtime($faq_style_path);
            $handle = 'apw-woo-faq-styles';

            wp_register_style(
                $handle,
                $css_url . 'faq-styles.css',
                $deps, // Depends on common styles if loaded
                $css_ver
            );
            wp_enqueue_style($handle);
            $faq_style_loaded = true;

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Enqueued FAQ CSS (faq-styles.css) with version: " . $css_ver);
            }
        } elseif (APW_WOO_DEBUG_MODE) {
            apw_woo_log("FAQ CSS file not found: faq-styles.css", 'warning');
        }

        // Load any remaining CSS files (excluding common, page-specific, and faq)
        foreach ($css_files as $file) {
            $filename = basename($file);

            // Skip already loaded files (common, page-specific, faq)
            if ($filename === 'apw-woo-public.css' ||
                $filename === "apw-woo-{$current_page_type}.css" ||
                $filename === 'faq-styles.css') {
                continue;
            }

            // Get a clean handle from the filename
            $handle_base = str_replace(
                array('apw-woo-', '.min.css', '.css'),
                array('', '', ''),
                $filename
            );

            // Add prefix to ensure uniqueness and prevent conflicts
            $handle = 'apw-woo-' . sanitize_title($handle_base) . '-styles';

            $css_ver = filemtime($file);

            wp_register_style(
                $handle,
                $css_url . $filename,
                $deps, // Depends on common styles if loaded
                $css_ver
            );
            wp_enqueue_style($handle);

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Enqueued additional CSS ({$filename}) with version: " . $css_ver);
            }
        }
    }

    /**
     * Auto-discover and enqueue all JavaScript files with cache busting
     *
     * @param string $assets_dir The local directory path to assets
     * @param string $assets_url The URL path to assets
     * @param string $current_page_type The current page type
     * @return void
     * @since 1.0.0
     */
    public static function auto_enqueue_scripts($assets_dir, $assets_url, $current_page_type)
    {
        $js_dir = $assets_dir . 'js/';
        $js_url = $assets_url . 'js/';

        // Skip if JS directory doesn't exist
        if (!file_exists($js_dir) || !is_dir($js_dir)) {
            return;
        }

        // Get all JS files
        $js_files = glob($js_dir . '*.js');
        if (empty($js_files)) {
            return;
        }

        // Track which files we've loaded
        $loaded_common = false;

        // First pass - Load the common file first
        foreach ($js_files as $file) {
            $filename = basename($file);

            // Load common file first
            if ($filename === 'apw-woo-public.js') {
                $js_ver = filemtime($file);
                $handle = 'apw-woo-scripts';

                wp_register_script(
                    $handle,
                    $js_url . $filename,
                    array('jquery'),
                    $js_ver,
                    true // Load in footer
                );
                wp_enqueue_script($handle);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Enqueued common JS with version: ' . $js_ver);
                }

                $loaded_common = true;
                break;
            }
        }

        // Set default dependencies
        $deps = $loaded_common ? array('jquery', 'apw-woo-scripts') : array('jquery');

        // Second pass - Load page-specific file next
        $page_specific_loaded = false;
        foreach ($js_files as $file) {
            $filename = basename($file);

            // Skip common file (already loaded)
            if ($filename === 'apw-woo-public.js') {
                continue;
            }

            // Load page specific file next
            if ($filename === "apw-woo-{$current_page_type}.js") {
                $js_ver = filemtime($file);
                $handle = "apw-woo-{$current_page_type}-scripts";

                wp_register_script(
                    $handle,
                    $js_url . $filename,
                    $deps,
                    $js_ver,
                    true // Load in footer
                );
                wp_enqueue_script($handle);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Enqueued {$current_page_type}-specific JS with version: " . $js_ver);
                }

                $page_specific_loaded = true;
                break;
            }
        }

        // Third pass - Load all other JS files
        foreach ($js_files as $file) {
            $filename = basename($file);

            // Skip already loaded files
            if ($filename === 'apw-woo-public.js' || $filename === "apw-woo-{$current_page_type}.js") {
                continue;
            }

            // Get a clean handle from the filename
            $handle_base = str_replace(
                array('apw-woo-', '.min.js', '.js'),
                array('', '', ''),
                $filename
            );

            // Add prefix to ensure uniqueness and prevent conflicts
            $handle = 'apw-woo-' . sanitize_title($handle_base) . '-scripts';

            $js_ver = filemtime($file);

            wp_register_script(
                $handle,
                $js_url . $filename,
                $deps,
                $js_ver,
                true // Load in footer
            );
            wp_enqueue_script($handle);

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Enqueued additional JS ({$filename}) with version: " . $js_ver);
            }
        }
    }
}
