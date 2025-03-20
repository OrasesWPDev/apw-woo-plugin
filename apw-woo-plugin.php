<?php
/**
 * Plugin Name: APW WooCommerce Plugin
 * Plugin URI: https://github.com/OrasesWPDev/apw-woo-plugin
 * Description: Custom WooCommerce enhancements for displaying products across shop, category, and product pages.
 * Version: 1.0.0
 * Author: Orases
 * Author URI: https://orases.com
 * Text Domain: apw-woo-plugin
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Add HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// Define plugin constants
define( 'APW_WOO_VERSION', '1.0.0' );
define( 'APW_WOO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APW_WOO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APW_WOO_PLUGIN_FILE', __FILE__ );
define( 'APW_WOO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Debug mode - set to true for debugging
define( 'APW_WOO_DEBUG_MODE', true );

/**
 * Check if WooCommerce is active
 */
function apw_woo_is_woocommerce_active() {
    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
    );
}

/**
 * Check if ACF Pro is active
 */
function apw_woo_is_acf_pro_active() {
    // First, check if the ACF Pro plugin is active
    if (in_array('advanced-custom-fields-pro/acf.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return true;
    }

    // If not found in the standard location, check for other possible ACF Pro paths
    $possible_acf_paths = [
        'advanced-custom-fields-pro/acf.php',
        'acf-pro/acf.php',
        'acf/acf.php'
    ];

    foreach ($possible_acf_paths as $path) {
        if (in_array($path, apply_filters('active_plugins', get_option('active_plugins')))) {
            return true;
        }
    }

    // Also check if ACF function exists as a final check
    return function_exists('acf_register_block_type');
}

/**
 * Create log directory and log file if they don't exist
 */
function apw_woo_setup_logs() {
    if ( APW_WOO_DEBUG_MODE ) {
        $log_dir = APW_WOO_PLUGIN_DIR . 'logs';

        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );

            // Create .htaccess file to protect logs directory
            $htaccess_content = "# Deny access to all files in this directory
<Files \"*\">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</Files>";
            file_put_contents( $log_dir . '/.htaccess', $htaccess_content );

            // Create index.php to prevent directory listing
            file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
        }
    }
}

/**
 * Log messages when debug mode is enabled
 */
function apw_woo_log( $message ) {
    if ( APW_WOO_DEBUG_MODE ) {
        $log_file = APW_WOO_PLUGIN_DIR . 'logs/debug-' . date( 'Y-m-d' ) . '.log';

        if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true );
        }

        $timestamp = date( '[Y-m-d H:i:s]' );
        $formatted_message = $timestamp . ' ' . $message . PHP_EOL;

        error_log( $formatted_message, 3, $log_file );
    }
}

/**
 * Auto-include all PHP files in the includes directory
 */
function apw_woo_autoload_files() {
    $includes_dir = APW_WOO_PLUGIN_DIR . 'includes';

    if ( ! file_exists( $includes_dir ) ) {
        wp_mkdir_p( $includes_dir );
        apw_woo_log( 'Created includes directory.' );
    }

    apw_woo_log( 'Starting to autoload files.' );

    // Get all php files from includes directory
    $includes_files = glob( $includes_dir . '/*.php' );

    // Load all files in the includes directory
    foreach ( $includes_files as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
            apw_woo_log( 'Loaded file: ' . basename( $file ) );
        }
    }

    // Autoload subdirectories if they exist
    $subdirs = array( 'admin', 'frontend', 'templates' );

    foreach ( $subdirs as $subdir ) {
        $subdir_path = $includes_dir . '/' . $subdir;

        if ( file_exists( $subdir_path ) ) {
            $subdir_files = glob( $subdir_path . '/*.php' );

            foreach ( $subdir_files as $file ) {
                if ( file_exists( $file ) ) {
                    require_once $file;
                    apw_woo_log( 'Loaded file: ' . $subdir . '/' . basename( $file ) );
                }
            }
        }
    }

    apw_woo_log( 'Finished autoloading files.' );
}

/**
 * Register and enqueue CSS/JS assets with cache busting
 */
function apw_woo_register_assets() {
    // Define asset paths
    $css_file = APW_WOO_PLUGIN_DIR . 'assets/css/apw-woo-public.css';
    $js_file = APW_WOO_PLUGIN_DIR . 'assets/js/apw-woo-public.js';

    // CSS with cache busting
    if ( file_exists( $css_file ) ) {
        $css_ver = filemtime( $css_file );
        wp_register_style(
            'apw-woo-styles',
            APW_WOO_PLUGIN_URL . 'assets/css/apw-woo-public.css',
            array(),
            $css_ver
        );
        wp_enqueue_style( 'apw-woo-styles' );
        apw_woo_log( 'Enqueued CSS with version: ' . $css_ver );
    }

    // JS with cache busting
    if ( file_exists( $js_file ) ) {
        $js_ver = filemtime( $js_file );
        wp_register_script(
            'apw-woo-scripts',
            APW_WOO_PLUGIN_URL . 'assets/js/apw-woo-public.js',
            array( 'jquery' ),
            $js_ver,
            true
        );
        wp_enqueue_script( 'apw-woo-scripts' );
        apw_woo_log( 'Enqueued JS with version: ' . $js_ver );
    }
}

/**
 * Initialize the plugin
 */
function apw_woo_init() {
    // Setup logs first
    apw_woo_setup_logs();
    apw_woo_log( 'Plugin initialization started.' );

    // Check if WooCommerce is active
    if ( ! apw_woo_is_woocommerce_active() ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e( 'APW WooCommerce Plugin requires WooCommerce to be installed and activated.', 'apw-woo-plugin' ); ?></p>
            </div>
            <?php
        });
        apw_woo_log( 'WooCommerce not active - plugin initialization stopped.' );
        return;
    }

    // Check if ACF Pro is active
    if ( ! apw_woo_is_acf_pro_active() ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e( 'APW WooCommerce Plugin requires Advanced Custom Fields PRO to be installed and activated.', 'apw-woo-plugin' ); ?></p>
            </div>
            <?php
        });
        apw_woo_log( 'ACF Pro not active - plugin initialization stopped.' );
        return;
    }

    // Autoload files
    apw_woo_autoload_files();

    // Initialize main plugin class if it exists
    if ( class_exists( 'APW_Woo_Plugin' ) ) {
        $plugin = APW_Woo_Plugin::get_instance();
        $plugin->init();
        apw_woo_log( 'Main plugin class initialized.' );
    } else {
        apw_woo_log( 'Main plugin class not found.' );
    }

    // Register assets
    add_action( 'wp_enqueue_scripts', 'apw_woo_register_assets' );
}

/**
 * Plugin activation hook
 */
function apw_woo_activate() {
    // Setup logs directory
    apw_woo_setup_logs();
    apw_woo_log( 'Plugin activated.' );

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'apw_woo_activate' );

/**
 * Plugin deactivation hook
 */
function apw_woo_deactivate() {
    if ( APW_WOO_DEBUG_MODE ) {
        apw_woo_log( 'Plugin deactivated.' );
    }

    // Clean up if needed
    // (Add any cleanup code here)

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'apw_woo_deactivate' );

// Initialize the plugin
add_action( 'plugins_loaded', 'apw_woo_init' );

/**
 * Simple test function to verify WooCommerce template filters are working
 */
function apw_woo_test_template_override($template, $template_name, $template_path) {
    // Log when this filter runs
    apw_woo_log('TEST: woocommerce_locate_template filter triggered for ' . $template_name);
    error_log('APW WOO TEST: woocommerce_locate_template filter triggered for ' . $template_name);

    // Return the original template
    return $template;
}
add_filter('woocommerce_locate_template', 'apw_woo_test_template_override', 999, 3);