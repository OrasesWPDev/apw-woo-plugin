<?php
/**
 * APW WooCommerce Plugin
 *
 * @package           APW_Woo_Plugin
 * @author            Orases
 * @copyright         2023 Orases
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       APW WooCommerce Plugin
 * Plugin URI:        https://github.com/OrasesWPDev/apw-woo-plugin
 * Description:       Custom WooCommerce enhancements for displaying products across shop, category, and product pages.
 * Version:           1.0.0
 * Requires at least: 5.3
 * Requires PHP:      7.2
 * Author:            Orases
 * Author URI:        https://orases.com
 * Text Domain:       apw-woo-plugin
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 5.0
 * WC tested up to:   8.0
 * Update URI:        https://example.com/my-plugin/
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

//--------------------------------------------------------------
// Define plugin constants
//--------------------------------------------------------------
define('APW_WOO_VERSION', '1.0.0');
define('APW_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APW_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('APW_WOO_PLUGIN_FILE', __FILE__);
define('APW_WOO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Debug mode - enabled for development
 * Controls logging and visualization features throughout the plugin.
 * Particularly useful for template and hook debugging with Flatsome theme.
 *
 * @see templates/woocommerce/single-product.php for hook visualization
 */
define('APW_WOO_DEBUG_MODE', true);

/**
 * WooCommerce HPOS compatibility declaration
 *
 * Ensures compatibility with WooCommerce High-Performance Order Storage
 * @see https://woocommerce.com/document/high-performance-order-storage/
 */
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
// Dependency Check Helper Functions
//--------------------------------------------------------------

/**
 * Check if WooCommerce is active
 *
 * @since 1.0.0
 * @return boolean True if WooCommerce is active
 */
function apw_woo_is_woocommerce_active() {
    // Fastest check - using function existence
    if (function_exists('WC')) {
        return true;
    }

    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters('active_plugins', get_option('active_plugins'))
    );
}

/**
 * Check if Advanced Custom Fields Pro is active
 *
 * @since 1.0.0
 * @return boolean True if ACF Pro is active
 */
function apw_woo_is_acf_pro_active() {
    // Fastest check - using function existence
    if (function_exists('get_field')) {
        return true;
    }

    // Get active plugins once to avoid repeated calls
    $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));

    // First, check standard ACF Pro path
    if (in_array('advanced-custom-fields-pro/acf.php', $active_plugins)) {
        return true;
    }

    // Check alternative paths
    $possible_acf_paths = [
        'acf-pro/acf.php',
        'acf/acf.php'
    ];

    foreach ($possible_acf_paths as $path) {
        if (in_array($path, $active_plugins)) {
            return true;
        }
    }

    // Final fallback check
    return function_exists('acf_register_block_type');
}

//--------------------------------------------------------------
// FAQ Helper Functions
//--------------------------------------------------------------

/**
 * Get the FAQ page ID for a specific context
 *
 * Retrieves the appropriate FAQ page ID based on the context (shop, category, product).
 * Allows for filtering and falls back to safe defaults if not set.
 *
 * @since 1.0.0
 * @param string $context The context for which to retrieve the FAQ page ID (shop, category, etc.)
 * @return int The sanitized FAQ page ID
 */
function apw_woo_get_faq_page_id($context = 'shop') {
    // Sanitize input to prevent potential issues
    $context = sanitize_key($context);

    // Valid contexts - restricting to only supported types
    $valid_contexts = array('shop', 'category', 'product');

    // If context is not valid, default to 'shop'
    if (!in_array($context, $valid_contexts)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Invalid FAQ context requested: {$context}, defaulting to 'shop'");
        }
        $context = 'shop';
    }

    // Default fallback values
    $default_ids = array(
        'shop' => 0,
        'category' => 0,
        'product' => 0
    );

    // Build option name with prefix for better organization
    $option_name = 'apw_woo_faq_' . $context . '_page_id';

    // Get value from options with fallback to default
    $page_id = get_option($option_name, $default_ids[$context]);

    // Allow filtering for extensibility
    $page_id = apply_filters('apw_woo_' . $context . '_faq_page_id', $page_id);

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("FAQ Page ID for {$context}: {$page_id}");
    }

    // Ensure the ID is a positive integer or zero
    return absint($page_id);
}

/**
 * Test ACF FAQ field retrieval across all contexts
 *
 * This function helps verify that FAQs are working correctly with template changes
 * and buffering approach. It handles object validation, FAQ retrieval, and
 * structure validation in one comprehensive test.
 *
 * @since 1.0.0
 * @param mixed  $object The object to test (product, category, or page ID)
 * @param string $type   Optional. The type of object ('product', 'category', or 'page').
 *                       If not provided, the function will attempt to determine the type.
 * @return array|false The retrieved FAQs array or false on failure
 */
function apw_woo_test_faq_retrieval($object, $type = '') {
    // Auto-detect object type if not specified
    if (empty($type)) {
        if (is_numeric($object)) {
            $type = 'page';
        } elseif (is_a($object, 'WC_Product')) {
            $type = 'product';
        } elseif (is_a($object, 'WP_Term')) {
            $type = 'category';
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("FAQ TEST ERROR: Cannot determine object type automatically");
            }
            return false;
        }
    }

    // Validate that ACF is available
    if (!function_exists('get_field')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("FAQ TEST ERROR: ACF's get_field function does not exist");
        }
        return false;
    }

    // Initialize result data structure
    $result = array(
        'type' => $type,
        'has_acf' => true, // We already checked this above
        'object_valid' => false,
        'faqs_retrieved' => false,
        'faqs_count' => 0,
        'buffer_level' => apw_woo_get_ob_level(),
        'error' => ''
    );

    // Process based on object type
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
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("FAQ TEST ERROR: " . $result['error']);
            }
            return false;
    }

    // Process and validate FAQs if found
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
    } else {
        // No FAQs found
        $faqs = false;
    }

    // Log detailed test results when in debug mode
    if (APW_WOO_DEBUG_MODE) {
        // Handle possible error in object_name or object_id
        $object_name = isset($result['object_name']) ? $result['object_name'] : 'Unknown';
        $object_id = isset($result['object_id']) ? $result['object_id'] : 'Unknown';

        apw_woo_log("FAQ TEST for {$type}: " .
            ($result['faqs_retrieved'] ? "SUCCESS - Found {$result['faqs_count']} FAQs" : "FAILED - No FAQs found") .
            " for {$object_name} (ID: {$object_id})");

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
 * Verifies that FAQs conform to the expected data structure with
 * required question and answer fields. Returns detailed information
 * about any validation failures.
 *
 * @since 1.0.0
 * @param array $faqs Array of FAQs to validate
 * @return array Associative array with 'valid' boolean and 'errors' array
 */
function apw_woo_validate_faq_structure($faqs) {
    $result = array(
        'valid' => true,
        'errors' => array()
    );

    // Check if the FAQs parameter is actually an array
    if (!is_array($faqs)) {
        $result['valid'] = false;
        $result['errors'][] = 'FAQs is not an array';
        return $result;
    }

    // If array is empty, it's technically valid but might be a warning case
    if (empty($faqs)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('FAQ validation warning: Empty FAQs array provided');
        }
        return $result; // Still valid, just empty
    }

    // Validate each FAQ item
    foreach ($faqs as $index => $faq) {
        // Validate FAQ is an array
        if (!is_array($faq)) {
            $result['valid'] = false;
            $result['errors'][] = "FAQ #{$index} is not an array";
            continue; // Skip further checks for this item
        }

        // Validate required fields exist and are not empty
        if (!isset($faq['question']) || empty($faq['question'])) {
            $result['valid'] = false;
            $result['errors'][] = "FAQ #{$index} is missing or has empty question field";
        }

        if (!isset($faq['answer']) || empty($faq['answer'])) {
            $result['valid'] = false;
            $result['errors'][] = "FAQ #{$index} is missing or has empty answer field";
        }

        // Check for unexpected keys that might indicate a structure problem
        $expected_keys = array('question', 'answer');
        $unexpected_keys = array_diff(array_keys($faq), $expected_keys);

        if (!empty($unexpected_keys) && APW_WOO_DEBUG_MODE) {
            apw_woo_log("FAQ #{$index} has unexpected fields: " . implode(', ', $unexpected_keys));
            // This doesn't invalidate the FAQ, but might indicate an issue
        }
    }

    return $result;
}

/**
 * Check if output buffering is active and return level
 *
 * Output buffering details are important for template loading and
 * debugging issues with template parts and includes.
 *
 * @since 1.0.0
 * @return int Current output buffering level
 */
function apw_woo_get_ob_level() {
    return ob_get_level();
}

//--------------------------------------------------------------
// Logging and Debug Functionality
//--------------------------------------------------------------

/**
 * Log messages when debug mode is enabled
 *
 * This is a wrapper for the APW_Woo_Logger class to maintain backward compatibility
 * with existing code that calls this function directly.
 *
 * @since 1.0.0
 * @param mixed $message The message or data to log
 * @param string $level Optional. Log level (info, warning, error). Default 'info'.
 * @return void
 */
function apw_woo_log($message, $level = 'info') {
    APW_Woo_Logger::log($message, $level);
}

/**
 * Create log directory and security files if they don't exist
 *
 * This is a wrapper for the APW_Woo_Logger class to maintain backward compatibility
 * with existing code that calls this function directly.
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_setup_logs() {
    APW_Woo_Logger::setup_logs();
}

//--------------------------------------------------------------
// File and Asset Management Functions
//--------------------------------------------------------------

/**
 * Auto-include all PHP files in the includes directory
 *
 * Recursively loads all PHP files from the includes directory and specified subdirectories.
 * Files are loaded in a predictable order: main includes first, then subdirectories.
 * Skip files that start with 'class-' to allow for proper class autoloading order.
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_autoload_files() {
    $includes_dir = APW_WOO_PLUGIN_DIR . 'includes';

    // Ensure the includes directory exists
    if (!file_exists($includes_dir)) {
        if (wp_mkdir_p($includes_dir)) {
            apw_woo_log('Created includes directory.');
        } else {
            apw_woo_log('Failed to create includes directory.', 'error');
            return;
        }
    }

    apw_woo_log('Starting to autoload files.');

    // First, load core logger class to ensure logging functions are available
    $logger_file = $includes_dir . '/class-apw-woo-logger.php';
    if (file_exists($logger_file)) {
        require_once $logger_file;
        apw_woo_log('Loaded logger class: ' . basename($logger_file));
    }

    // Next, load function files (files that don't start with 'class-')
    // This ensures functions are available for classes
    $function_files = glob($includes_dir . '/*.php');

    foreach ($function_files as $file) {
        $basename = basename($file);

        // Skip the logger class since we already loaded it
        if ($basename === 'class-apw-woo-logger.php') {
            continue;
        }

        // Skip other class files for now - will load them next
        if (strpos($basename, 'class-') === 0) {
            continue;
        }

        if (file_exists($file)) {
            require_once $file;
            apw_woo_log('Loaded function file: ' . $basename);
        }
    }

    // Next, load class files
    $class_files = glob($includes_dir . '/class-*.php');

    foreach ($class_files as $file) {
        $basename = basename($file);

        // Skip the logger class since we already loaded it
        if ($basename === 'class-apw-woo-logger.php') {
            continue;
        }

        if (file_exists($file)) {
            require_once $file;
            apw_woo_log('Loaded class file: ' . $basename);
        }
    }

    // Finally, load subdirectory files if they exist
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

//--------------------------------------------------------------
// File and Asset Management Functions
//--------------------------------------------------------------

/**
 * Register and enqueue CSS/JS assets with cache busting
 *
 * This is a wrapper for the APW_Woo_Assets class to maintain backward compatibility
 * with existing code that calls this function directly.
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_register_assets() {
    // Forward to the class method
    APW_Woo_Assets::register_assets();
}

//--------------------------------------------------------------
// Plugin Initialization and Core Functions
//--------------------------------------------------------------

/**
 * Define ACF field structure for FAQs
 *
 * Sets up the standard field names and structure used by the plugin
 * for FAQ functionality and displays guidance in the admin area.
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_define_faq_field_structure() {
    // Only execute on admin pages
    if (!is_admin()) {
        return;
    }

    // Documentation constants for ACF field structure
    if (!defined('APW_WOO_FAQ_FIELD_GROUP')) {
        define('APW_WOO_FAQ_FIELD_GROUP', 'faqs');
    }

    if (!defined('APW_WOO_FAQ_QUESTION_FIELD')) {
        define('APW_WOO_FAQ_QUESTION_FIELD', 'question');
    }

    if (!defined('APW_WOO_FAQ_ANSWER_FIELD')) {
        define('APW_WOO_FAQ_ANSWER_FIELD', 'answer');
    }

    // Document expected FAQ structure for site administrators
    // Only add this on ACF field group pages
    add_action('admin_notices', 'apw_woo_display_faq_field_notice');
}

/**
 * Display admin notice about FAQ field structure
 *
 * Shown only on ACF field group edit pages to help administrators
 * set up the correct field structure for FAQs.
 *
 * @since 1.0.0
 * @return void
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
 *
 * Central initialization function that coordinates the loading
 * and initialization of all plugin components in the correct order.
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_init() {
    // Setup logs first
    apw_woo_setup_logs();
    apw_woo_log('Plugin initialization started.');

    // Verify dependencies
    if (!apw_woo_verify_dependencies()) {
        return; // Dependency check failed, initialization aborted
    }

    // Define FAQ field structure
    apw_woo_define_faq_field_structure();

    // Autoload files
    apw_woo_autoload_files();

    // Initialize main plugin class
    apw_woo_initialize_main_class();

    // Initialize asset management system
    if (class_exists('APW_Woo_Assets')) {
        APW_Woo_Assets::init();
        apw_woo_log('Asset management system initialized.');
    } else {
        // Fallback to legacy asset registration if class doesn't exist
        add_action('wp_enqueue_scripts', 'apw_woo_register_assets');
        apw_woo_log('Using legacy asset registration method.');
    }

    // Initialize Product Add-ons integration
    apw_woo_initialize_product_addons();
}

/**
 * Verify all plugin dependencies are met
 *
 * @since 1.0.0
 * @return bool True if all dependencies are satisfied
 */
function apw_woo_verify_dependencies() {
    // Check if WooCommerce is active
    if (!apw_woo_is_woocommerce_active()) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('APW WooCommerce Plugin requires WooCommerce to be installed and activated.', 'apw-woo-plugin'); ?></p>
            </div>
            <?php
        });
        apw_woo_log('WooCommerce not active - plugin initialization stopped.', 'error');
        return false;
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
        apw_woo_log('ACF Pro not active - plugin initialization stopped.', 'error');
        return false;
    }

    return true;
}

/**
 * Initialize the main plugin class
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_initialize_main_class() {
    if (class_exists('APW_Woo_Plugin')) {
        $plugin = APW_Woo_Plugin::get_instance();
        $plugin->init();
        apw_woo_log('Main plugin class initialized.');
    } else {
        apw_woo_log('Main plugin class not found.', 'error');
    }
}

/**
 * Initialize Product Add-ons integration
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_initialize_product_addons() {
    // Load Product Add-ons functions file
    $addons_file = APW_WOO_PLUGIN_DIR . 'includes/apw-woo-product-addons-functions.php';

    if (file_exists($addons_file)) {
        require_once $addons_file;

        // Check if Product Add-ons plugin is active
        if (function_exists('apw_woo_is_product_addons_active') && apw_woo_is_product_addons_active()) {
            $addon_class_file = APW_WOO_PLUGIN_DIR . 'includes/class-apw-woo-product-addons.php';

            if (file_exists($addon_class_file)) {
                require_once $addon_class_file;
                $product_addons = APW_Woo_Product_Addons::get_instance();
                apw_woo_log('Product Add-ons integration initialized.');
            } else {
                apw_woo_log('Product Add-ons class file not found.', 'warning');
            }
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Product Add-ons plugin not active - integration skipped.');
            }
        }
    } else {
        apw_woo_log('Product Add-ons functions file not found.', 'warning');
    }
}

//--------------------------------------------------------------
// Plugin Activation/Deactivation
//--------------------------------------------------------------

/**
 * Plugin activation hook
 *
 * Runs when the plugin is activated.
 * Sets up necessary structures and flushes rewrite rules.
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_activate() {
    // Make sure log directory exists
    apw_woo_setup_logs();

    // Create necessary plugin directories
    $dirs_to_create = array(
        'logs',
        'assets/css',
        'assets/js',
        'assets/images'
    );

    foreach ($dirs_to_create as $dir) {
        $full_path = APW_WOO_PLUGIN_DIR . $dir;
        if (!file_exists($full_path)) {
            wp_mkdir_p($full_path);
        }
    }

    // Log the activation
    apw_woo_log('Plugin activated.', 'info');

    // Flush rewrite rules to ensure custom endpoints work
    flush_rewrite_rules();
}

// Register the activation hook
register_activation_hook(__FILE__, 'apw_woo_activate');

/**
 * Plugin deactivation hook
 *
 * Runs when the plugin is deactivated.
 * Performs cleanup operations and flushes rewrite rules.
 *
 * @since 1.0.0
 * @return void
 */
function apw_woo_deactivate() {
    // Log the deactivation
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Plugin deactivated.', 'info');
    }

    // Perform any necessary cleanup
    // Note: We don't remove logs or other data to prevent data loss

    // Remove transients
    delete_transient('apw_woo_template_cache');

    // Flush rewrite rules to remove any custom endpoints
    flush_rewrite_rules();
}

// Register the deactivation hook
register_deactivation_hook(__FILE__, 'apw_woo_deactivate');

//--------------------------------------------------------------
// Testing & Debugging Utilities
//--------------------------------------------------------------

/**
 * WooCommerce template filter test function
 *
 * This function is only used during development to verify
 * that template filters are correctly being applied.
 *
 * @since 1.0.0
 * @param string $template      Original template path
 * @param string $template_name Template name
 * @param string $template_path Template path
 * @return string Unmodified template path
 */
function apw_woo_test_template_override($template, $template_name, $template_path) {
    if (APW_WOO_DEBUG_MODE) {
        // Log to plugin's debug log
        apw_woo_log('Template filter test: ' . $template_name, 'debug');

        // Also log to WP debug log for cross-reference
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('APW WOO TEMPLATE TEST: ' . $template_name . ' (Path: ' . $template . ')');
        }
    }

    // Return unmodified template - this is just for testing
    return $template;
}

// Only add test filter if in debug mode
if (APW_WOO_DEBUG_MODE) {
    add_filter('woocommerce_locate_template', 'apw_woo_test_template_override', 999, 3);
}

/**
 * Optional filter for performance testing - enables timing hooks
 *
 * @param bool $value Whether to enable performance tracking
 * @return bool Filtered value
 */
function apw_woo_enable_performance_tracking($value) {
    return APW_WOO_DEBUG_MODE ? true : $value;
}

// Only in debug mode, enable performance tracking
if (APW_WOO_DEBUG_MODE) {
    add_filter('apw_woo_enable_performance_tracking', '__return_true');
}

//--------------------------------------------------------------
// Initialize the plugin
//--------------------------------------------------------------

// Hook into WordPress 'plugins_loaded' to initialize our plugin
add_action('plugins_loaded', 'apw_woo_init');