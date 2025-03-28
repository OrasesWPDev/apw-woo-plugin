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
if (!defined('ABSPATH')) {
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

//--------------------------------------------------------------
// Define plugin constants
//--------------------------------------------------------------
define('APW_WOO_VERSION', '1.0.0');
define('APW_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APW_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('APW_WOO_PLUGIN_FILE', __FILE__);
define('APW_WOO_PLUGIN_BASENAME', plugin_basename(__FILE__));
// Debug mode - set to true for debugging
define('APW_WOO_DEBUG_MODE', true);

//--------------------------------------------------------------
// Dependency Check Helper Functions
//--------------------------------------------------------------

/**
 * Check if WooCommerce is active
 */
function apw_woo_is_woocommerce_active() {
    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters('active_plugins', get_option('active_plugins'))
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

//--------------------------------------------------------------
// FAQ Helper Functions
//--------------------------------------------------------------

/**
 * Get the FAQ page ID for a specific context
 *
 * @param string $context The context for which to retrieve the FAQ page ID (shop, category, etc.)
 * @return int The FAQ page ID
 */
function apw_woo_get_faq_page_id($context = 'shop') {
    // Default fallback values
    $default_ids = array(
        'shop' => 0, // Default to 0 (no FAQs) instead of hardcoded 66
        'category' => 0,
        'product' => 0
    );

    // Get value from options if set
    $option_name = 'apw_woo_faq_' . $context . '_page_id';
    $page_id = get_option($option_name, $default_ids[$context]);

    // Allow filtering
    $page_id = apply_filters('apw_woo_' . $context . '_faq_page_id', $page_id);

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("FAQ Page ID for {$context}: {$page_id}");
    }

    return absint($page_id);
}

/**
 * Test ACF FAQ field retrieval across all contexts
 *
 * This function helps verify that FAQs are working correctly with template changes
 * and buffering approach.
 *
 * @param mixed $object The object to test (product, category, or page ID)
 * @param string $type The type of object ('product', 'category', or 'page')
 * @return array|false The retrieved FAQs or false on failure
 */
function apw_woo_test_faq_retrieval($object, $type = '') {
    if (empty($type) && is_numeric($object)) {
        $type = 'page';
    } elseif (empty($type) && is_a($object, 'WC_Product')) {
        $type = 'product';
    } elseif (empty($type) && is_a($object, 'WP_Term')) {
        $type = 'category';
    }

    if (!function_exists('get_field')) {
        apw_woo_log("FAQ TEST ERROR: ACF's get_field function does not exist");
        return false;
    }

    $result = array(
        'type' => $type,
        'has_acf' => function_exists('get_field'),
        'object_valid' => false,
        'faqs_retrieved' => false,
        'faqs_count' => 0,
        'buffer_level' => apw_woo_get_ob_level(),
        'error' => ''
    );

    switch ($type) {
        case 'product':
            if (!is_a($object, 'WC_Product')) {
                $result['error'] = 'Invalid product object';
                break;
            }
            $result['object_valid'] = true;
            $result['object_id'] = $object->get_id();
            $result['object_name'] = $object->get_name();
            $faqs = get_field('faqs', $object->get_id());
            break;

        case 'category':
            if (!is_a($object, 'WP_Term')) {
                $result['error'] = 'Invalid category object';
                break;
            }
            $result['object_valid'] = true;
            $result['object_id'] = $object->term_id;
            $result['object_name'] = $object->name;
            $faqs = get_field('faqs', $object);
            break;

        case 'page':
            if (!is_numeric($object)) {
                $result['error'] = 'Invalid page ID';
                break;
            }
            $result['object_valid'] = true;
            $result['object_id'] = $object;
            $result['object_name'] = get_the_title($object);
            $faqs = get_field('faqs', $object);
            break;

        default:
            $result['error'] = 'Unknown object type: ' . $type;
            apw_woo_log("FAQ TEST ERROR: " . $result['error']);
            return false;
    }

    if (!empty($faqs)) {
        $result['faqs_retrieved'] = true;
        $result['faqs_count'] = count($faqs);

        // Validate FAQ structure
        $valid_structure = apw_woo_validate_faq_structure($faqs);
        $result['valid_structure'] = $valid_structure['valid'];
        $result['structure_errors'] = $valid_structure['errors'];

        // Log detailed sample of first FAQ
        if (!empty($faqs[0])) {
            $result['faqs_sample'] = array(
                'has_question' => isset($faqs[0]['question']),
                'has_answer' => isset($faqs[0]['answer']),
                'question_length' => isset($faqs[0]['question']) ? strlen($faqs[0]['question']) : 0,
                'answer_length' => isset($faqs[0]['answer']) ? strlen($faqs[0]['answer']) : 0,
            );
        }
    }

    // Log the test results
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("FAQ TEST for {$type}: " .
            ($result['faqs_retrieved'] ? "SUCCESS - Found {$result['faqs_count']} FAQs" : "FAILED - No FAQs found") .
            " for {$result['object_name']} (ID: {$result['object_id']})");
        apw_woo_log("Output buffer level during FAQ test: {$result['buffer_level']}");

        if (!empty($result['structure_errors'])) {
            apw_woo_log("FAQ structure errors: " . implode(', ', $result['structure_errors']));
        }
    }

    return $result['faqs_retrieved'] ? $faqs : false;
}

/**
 * Validate FAQ structure against expected format
 *
 * @param array $faqs Array of FAQs to validate
 * @return array Result with 'valid' boolean and 'errors' array
 */
function apw_woo_validate_faq_structure($faqs) {
    $result = array(
        'valid' => true,
        'errors' => array()
    );

    if (!is_array($faqs)) {
        $result['valid'] = false;
        $result['errors'][] = 'FAQs is not an array';
        return $result;
    }

    foreach ($faqs as $index => $faq) {
        // Check if each FAQ is an array
        if (!is_array($faq)) {
            $result['valid'] = false;
            $result['errors'][] = "FAQ #{$index} is not an array";
            continue;
        }

        // Check for required fields
        if (!isset($faq['question']) || empty($faq['question'])) {
            $result['valid'] = false;
            $result['errors'][] = "FAQ #{$index} is missing question field";
        }

        if (!isset($faq['answer']) || empty($faq['answer'])) {
            $result['valid'] = false;
            $result['errors'][] = "FAQ #{$index} is missing answer field";
        }
    }

    return $result;
}

/**
 * Check if output buffering is active and return level
 *
 * @return int Current output buffering level
 */
function apw_woo_get_ob_level() {
    return ob_get_level();
}

//--------------------------------------------------------------
// Logging and Debug Functionality
//--------------------------------------------------------------

/**
 * Create log directory and log file if they don't exist
 */
function apw_woo_setup_logs() {
    if (APW_WOO_DEBUG_MODE) {
        $log_dir = APW_WOO_PLUGIN_DIR . 'logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
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
            file_put_contents($log_dir . '/.htaccess', $htaccess_content);
            // Create index.php to prevent directory listing
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden.');
        }
    }
}

/**
 * Log messages when debug mode is enabled
 */
function apw_woo_log($message) {
    if (APW_WOO_DEBUG_MODE) {
        // Set timezone to EST (New York)
        $timezone = new DateTimeZone('America/New_York');
        $date = new DateTime('now', $timezone);
        // Format the date for file name and timestamp
        $log_file = APW_WOO_PLUGIN_DIR . 'logs/debug-' . $date->format('Y-m-d') . '.log';
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $timestamp = $date->format('[Y-m-d H:i:s T]'); // T will show timezone abbreviation
        $formatted_message = $timestamp . ' ' . $message . PHP_EOL;
        error_log($formatted_message, 3, $log_file);
    }
}

//--------------------------------------------------------------
// File and Asset Management Functions
//--------------------------------------------------------------

/**
 * Auto-include all PHP files in the includes directory
 */
function apw_woo_autoload_files() {
    $includes_dir = APW_WOO_PLUGIN_DIR . 'includes';
    if (!file_exists($includes_dir)) {
        wp_mkdir_p($includes_dir);
        apw_woo_log('Created includes directory.');
    }
    apw_woo_log('Starting to autoload files.');
    // Get all php files from includes directory
    $includes_files = glob($includes_dir . '/*.php');
    // Load all files in the includes directory
    foreach ($includes_files as $file) {
        if (file_exists($file)) {
            require_once $file;
            apw_woo_log('Loaded file: ' . basename($file));
        }
    }
    // Autoload subdirectories if they exist
    $subdirs = array('admin', 'frontend', 'templates');
    foreach ($subdirs as $subdir) {
        $subdir_path = $includes_dir . '/' . $subdir;
        if (file_exists($subdir_path)) {
            $subdir_files = glob($subdir_path . '/*.php');
            foreach ($subdir_files as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    apw_woo_log('Loaded file: ' . $subdir . '/' . basename($file));
                }
            }
        }
    }
    apw_woo_log('Finished autoloading files.');
}

/**
 * Register and enqueue CSS/JS assets with cache busting
 * Only loads assets on WooCommerce pages to optimize performance
 */
function apw_woo_register_assets() {
    // Only load on WooCommerce pages
    if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
        return;
    }

    // Get current page type for conditional loading
    $current_page_type = 'generic';
    if (is_shop()) {
        $current_page_type = 'shop';
    } elseif (is_product()) {
        $current_page_type = 'product';
    } elseif (is_product_category()) {
        $current_page_type = 'category';
    } elseif (is_cart()) {
        $current_page_type = 'cart';
    } elseif (is_checkout()) {
        $current_page_type = 'checkout';
    } elseif (is_account_page()) {
        $current_page_type = 'account';
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Loading APW WooCommerce Plugin assets on {$current_page_type} page");
    }

    // Define asset paths - common assets
    $css_file = APW_WOO_PLUGIN_DIR . 'assets/css/apw-woo-public.css';
    $js_file = APW_WOO_PLUGIN_DIR . 'assets/js/apw-woo-public.js';

    // Define page-specific asset paths for future use
    $page_specific_css = APW_WOO_PLUGIN_DIR . "assets/css/apw-woo-{$current_page_type}.css";
    $page_specific_js = APW_WOO_PLUGIN_DIR . "assets/js/apw-woo-{$current_page_type}.js";

    // CSS with cache busting - common CSS
    if (file_exists($css_file)) {
        $css_ver = filemtime($css_file);
        wp_register_style(
            'apw-woo-styles',
            APW_WOO_PLUGIN_URL . 'assets/css/apw-woo-public.css',
            array(),
            $css_ver
        );
        wp_enqueue_style('apw-woo-styles');
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Enqueued common CSS with version: ' . $css_ver);
        }
    }

    // Page-specific CSS if it exists
    if (file_exists($page_specific_css)) {
        $css_ver = filemtime($page_specific_css);
        wp_register_style(
            "apw-woo-{$current_page_type}-styles",
            APW_WOO_PLUGIN_URL . "assets/css/apw-woo-{$current_page_type}.css",
            array('apw-woo-styles'), // Depend on common styles
            $css_ver
        );
        wp_enqueue_style("apw-woo-{$current_page_type}-styles");
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Enqueued {$current_page_type}-specific CSS with version: " . $css_ver);
        }
    }

    // JS with cache busting - common JS
    if (file_exists($js_file)) {
        $js_ver = filemtime($js_file);
        wp_register_script(
            'apw-woo-scripts',
            APW_WOO_PLUGIN_URL . 'assets/js/apw-woo-public.js',
            array('jquery'),
            $js_ver,
            true
        );
        wp_enqueue_script('apw-woo-scripts');
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Enqueued common JS with version: ' . $js_ver);
        }
    }

    // Page-specific JS if it exists
    if (file_exists($page_specific_js)) {
        $js_ver = filemtime($page_specific_js);
        wp_register_script(
            "apw-woo-{$current_page_type}-scripts",
            APW_WOO_PLUGIN_URL . "assets/js/apw-woo-{$current_page_type}.js",
            array('jquery', 'apw-woo-scripts'), // Depend on jQuery and common scripts
            $js_ver,
            true
        );
        wp_enqueue_script("apw-woo-{$current_page_type}-scripts");
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Enqueued {$current_page_type}-specific JS with version: " . $js_ver);
        }
    }

    // Add page-specific data for JS if needed
    $page_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'page_type' => $current_page_type,
        'nonce' => wp_create_nonce('apw_woo_nonce')
    );

    // Allow other parts of the plugin to modify JS data
    $page_data = apply_filters('apw_woo_js_data', $page_data, $current_page_type);

    // Localize script with data
    wp_localize_script('apw-woo-scripts', 'apwWooData', $page_data);
}

//--------------------------------------------------------------
// Plugin Initialization and Core Functions
//--------------------------------------------------------------

/**
 * Define ACF field structure for FAQs
 */
function apw_woo_define_faq_field_structure() {
    // Only execute on admin pages that might use this information
    if (!is_admin()) {
        return;
    }

    // Documentation constants for ACF field structure
    define('APW_WOO_FAQ_FIELD_GROUP', 'faqs');
    define('APW_WOO_FAQ_QUESTION_FIELD', 'question');
    define('APW_WOO_FAQ_ANSWER_FIELD', 'answer');

    // Document expected FAQ structure for site administrators
    add_action('admin_notices', 'apw_woo_display_faq_field_notice');
}

/**
 * Display admin notice about FAQ field structure
 */
function apw_woo_display_faq_field_notice() {
    // Only show this notice on ACF field group edit pages
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'acf-field-group') {
        return;
    }

    ?>
    <div class="notice notice-info is-dismissible">
        <p><strong><?php _e('APW WooCommerce Plugin FAQ Field Structure', 'apw-woo-plugin'); ?></strong></p>
        <p><?php _e('This plugin expects FAQs to be configured as a repeater field named "faqs" with the following sub-fields:', 'apw-woo-plugin'); ?></p>
        <ul style="margin-left: 20px; list-style-type: disc;">
            <li><?php _e('<strong>question</strong> - Text field for the FAQ question', 'apw-woo-plugin'); ?></li>
            <li><?php _e('<strong>answer</strong> - Wysiwyg/textarea field for the FAQ answer', 'apw-woo-plugin'); ?></li>
        </ul>
        <p><?php _e('This field should be added to product, category, and page content types.', 'apw-woo-plugin'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function apw_woo_init() {
    // Setup logs first
    apw_woo_setup_logs();
    apw_woo_log('Plugin initialization started.');

    // Check if WooCommerce is active
    if (!apw_woo_is_woocommerce_active()) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('APW WooCommerce Plugin requires WooCommerce to be installed and activated.', 'apw-woo-plugin'); ?></p>
            </div>
            <?php
        });
        apw_woo_log('WooCommerce not active - plugin initialization stopped.');
        return;
    }

    // Check if ACF Pro is active
    if (!apw_woo_is_acf_pro_active()) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('APW WooCommerce Plugin requires Advanced Custom Fields PRO to be installed and activated.', 'apw-woo-plugin'); ?></p>
            </div>
            <?php
        });
        apw_woo_log('ACF Pro not active - plugin initialization stopped.');
        return;
    }

    // Define FAQ field structure
    apw_woo_define_faq_field_structure();

    // Autoload files
    apw_woo_autoload_files();

    // Initialize main plugin class if it exists
    if (class_exists('APW_Woo_Plugin')) {
        $plugin = APW_Woo_Plugin::get_instance();
        $plugin->init();
        apw_woo_log('Main plugin class initialized.');
    } else {
        apw_woo_log('Main plugin class not found.');
    }

    // Initialize Product Add-ons integration
    require_once APW_WOO_PLUGIN_DIR . 'includes/apw-woo-product-addons-functions.php';

    // Check if Product Add-ons plugin is active
    if (function_exists('apw_woo_is_product_addons_active') && apw_woo_is_product_addons_active()) {
        require_once APW_WOO_PLUGIN_DIR . 'includes/class-apw-woo-product-addons.php';
        $product_addons = APW_Woo_Product_Addons::get_instance();
        apw_woo_log('Product Add-ons integration initialized.');
    } else if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Product Add-ons plugin not active - integration skipped.');
    }

    // Register assets
    add_action('wp_enqueue_scripts', 'apw_woo_register_assets');
}

//--------------------------------------------------------------
// Plugin Activation/Deactivation
//--------------------------------------------------------------

/**
 * Plugin activation hook
 */
function apw_woo_activate() {
    // Setup logs directory
    apw_woo_setup_logs();
    apw_woo_log('Plugin activated.');
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'apw_woo_activate');

/**
 * Plugin deactivation hook
 */
function apw_woo_deactivate() {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Plugin deactivated.');
    }
    // Clean up if needed
    // (Add any cleanup code here)
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'apw_woo_deactivate');

//--------------------------------------------------------------
// Testing & Debugging Utilities
//--------------------------------------------------------------

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

// Initialize the plugin
add_action('plugins_loaded', 'apw_woo_init');