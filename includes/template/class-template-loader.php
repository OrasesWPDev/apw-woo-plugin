<?php
/**
 * Template Loader for APW WooCommerce Plugin
 *
 * Handles the loading, processing, and overriding of templates for WooCommerce pages.
 * Works with APW_Woo_Template_Resolver for template resolution and APW_Woo_Page_Detector
 * for page type detection.
 *
 * @package APW_Woo_Plugin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Loader
 *
 * Handles the loading of custom templates for WooCommerce pages,
 * including product, category, and shop pages.
 */
class APW_Woo_Template_Loader
{
    /**
     * Template path constants
     */
    private const TEMPLATE_DIRECTORY = 'templates/';
    private const WOOCOMMERCE_DIRECTORY = 'woocommerce/';
    private const PARTIALS_DIRECTORY = 'partials/';
    private const SHOP_TEMPLATE = 'woocommerce/partials/shop-categories-display.php';
    private const CATEGORY_TEMPLATE = 'woocommerce/partials/category-products-display.php';
    private const PRODUCT_TEMPLATE = 'woocommerce/single-product.php';
    private const CART_TEMPLATE = 'woocommerce/cart/cart.php';
    private const CHECKOUT_TEMPLATE = 'woocommerce/checkout/form-checkout.php';
    private const MY_ACCOUNT_TEMPLATE = 'woocommerce/myaccount/my-account.php';

    /**
     * Store the original product ID to prevent template issues
     *
     * @var int
     */
    private static $original_product_id = 0;

    /**
     * Store the original product object
     *
     * @var WC_Product
     */
    private static $original_product = null;

    /**
     * Set the original product for restoration
     *
     * @param int $product_id The product ID
     * @param WC_Product $product The product object
     */
    public static function set_original_product($product_id, $product)
    {
        // **OPTIMIZATION: Added validation to ensure we only store valid products**
        if ($product_id > 0 && is_a($product, 'WC_Product')) {
            self::$original_product_id = $product_id;
            self::$original_product = $product;

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Original product set: {$product->get_name()} (ID: {$product_id})");
            }
        } else if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Invalid product provided to set_original_product()", 'warning');
        }
    }

    /**
     * Hook priority constants
     */
    private const TEMPLATE_FILTER_PRIORITY = 10;
    private const TEMPLATE_FILTER_ARGS = 3;

    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Template directory path
     *
     * @var string
     */
    private $template_path;

    /**
     * Tracks if hooks are registered to prevent duplicate registration
     *
     * @var bool
     */
    private $hooks_registered = false;

    /**
     * Caches removed hook information to avoid redundant operations
     *
     * @var array
     */
    private static $hook_removal_cache = [];

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->template_path = APW_WOO_PLUGIN_DIR . self::TEMPLATE_DIRECTORY;
        $this->init_hooks();

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Template loader initialized');
        }
    }

    /**
     * Get instance
     *
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // **OPTIMIZATION: Check if hooks are already registered to prevent duplicates**
        if ($this->hooks_registered) {
            return;
        }

        // Add our plugin template directory to WooCommerce template paths
        add_filter(
            'woocommerce_locate_template',
            [$this, 'locate_template'],
            self::TEMPLATE_FILTER_PRIORITY,
            self::TEMPLATE_FILTER_ARGS
        );

        // Modify shop templates
        add_filter(
            'wc_get_template_part',
            [$this, 'get_template_part'],
            self::TEMPLATE_FILTER_PRIORITY,
            self::TEMPLATE_FILTER_ARGS
        );

        // Register template include filter for better template control
        $this->register_template_include_filter();

        // Keep the legacy method for backward compatibility during transition
        // Can be removed in a future version once we confirm the new method works
        add_action('woocommerce_before_main_content', [$this, 'maybe_load_custom_template']);

        // Remove default Flatsome/WooCommerce elements
        $this->remove_default_woocommerce_elements();

        // Remove default WC notices and hook our custom notice display
        $this->apw_woo_remove_default_notices();
        add_action('apw_woo_before_page_content', [$this, 'apw_woo_output_custom_notices'], 10);


        // Debug product permalinks
        if (APW_WOO_DEBUG_MODE) {
            add_filter('post_type_link', [$this, 'debug_product_permalinks'], 99, 2);
        }

        // **OPTIMIZATION: Add cleanup action to free memory at shutdown**
        add_action('shutdown', [$this, 'cleanup_resources']);

        $this->hooks_registered = true;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Template loader hooks initialized with template_include filter');
        }
    }

    /**
     * Cleanup resources at the end of the request
     */
    public function cleanup_resources()
    {
        // **OPTIMIZATION: Clear static caches to prevent memory leaks in persistent environments**
        self::$hook_removal_cache = [];

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Template loader resources cleaned up');
        }
    }

    /**
     * Remove default WooCommerce and Flatsome elements that we don't want
     */
    private function remove_default_woocommerce_elements()
    {
        // **OPTIMIZATION: Skip if already performed this operation (prevents duplicate work)**
        static $elements_removed = false;

        if ($elements_removed) {
            return;
        }

        // Remove Flatsome page title (which includes breadcrumbs)
        remove_action('flatsome_after_header', 'flatsome_pages_title', 12);

        // Additional Flatsome-specific removals for shop title
        if (function_exists('flatsome_remove_shop_header')) {
            flatsome_remove_shop_header();
        } else {
            // Manual removal if function doesn't exist
            remove_action('flatsome_after_header', 'woocommerce_breadcrumb', 20);
            remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
            remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);
            remove_action('woocommerce_archive_description', 'woocommerce_product_archive_description', 10);
        }

        // Remove shop tools (ordering, result count)
        remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
        remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);

        // Target Flatsome's shop-page-title container directly
        add_action('wp_head', function () {
            if (is_woocommerce()) {
                echo '<style>.shop-page-title { display: none !important; }</style>';
            }
        });

        // Additional safety: remove at higher priority
        add_action('init', function () {
            remove_action('flatsome_after_header', 'flatsome_pages_title', 12);
            remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
            remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
            remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
        }, 20);

        $elements_removed = true;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Removed default WooCommerce and Flatsome UI elements');
        }
    }

    /**
     * Debug product permalinks
     *
     * @param string $permalink The current permalink
     * @param object $post The current post
     * @return string The unchanged permalink
     */
    public function debug_product_permalinks($permalink, $post)
    {
        // **OPTIMIZATION: Added input validation to prevent issues with unexpected inputs**
        if (is_object($post) && property_exists($post, 'post_type') && $post->post_type === 'product') {
            apw_woo_log("Product permalink for {$post->post_name} (ID: {$post->ID}): {$permalink}");
        }
        return $permalink;
    }

    /**
     * Debug permalink generation
     *
     * @param string $product_slug The current product slug
     * @param string $requested_url The requested URL
     */
    private function debug_permalink($product_slug, $requested_url)
    {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Permalink Debug - Product Slug: {$product_slug}, Requested URL: {$requested_url}");
        }
    }

    /**
     * Removes default WooCommerce notice printing functions from standard hooks.
     *
     * This prevents notices from appearing in their default locations, allowing
     * us to place them consistently with apw_woo_output_custom_notices.
     *
     * @since 1.2.5 (Your new version)
     */
    public function apw_woo_remove_default_notices()
    {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Attempting to remove default WooCommerce notice hooks.');
        }

        // Remove the main notice printing function from common hooks
        // Note: wc_print_notices handles success, error, and info notices.
        remove_action('woocommerce_before_single_product', 'wc_print_notices', 10);
        remove_action('woocommerce_before_shop_loop', 'wc_print_notices', 10); // Often used on archives
        remove_action('woocommerce_before_cart', 'wc_print_notices', 10);
        remove_action('woocommerce_before_checkout_form', 'wc_print_notices', 10);
        // Add others if necessary, e.g., account pages might use different hooks or direct calls

        // Also remove the older 'woocommerce_output_all_notices' if themes/plugins might still use it
        remove_action('woocommerce_before_single_product', 'woocommerce_output_all_notices', 10);
        remove_action('woocommerce_before_shop_loop', 'woocommerce_output_all_notices', 10);
        remove_action('woocommerce_before_cart', 'woocommerce_output_all_notices', 10);
        remove_action('woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10);


        if (APW_WOO_DEBUG_MODE) {
            // Check if removals were successful (basic check, might not be 100% reliable)
            $hooks_to_check = [
                'woocommerce_before_single_product' => 'wc_print_notices',
                'woocommerce_before_cart' => 'wc_print_notices',
                'woocommerce_before_checkout_form' => 'wc_print_notices',
            ];
            $all_removed = true;
            foreach ($hooks_to_check as $hook => $function) {
                if (has_action($hook, $function) === 10) { // Check specific priority
                    apw_woo_log("Failed to remove {$function} from {$hook} at priority 10.", 'warning');
                    $all_removed = false;
                }
            }
            if ($all_removed) {
                apw_woo_log('Successfully removed default notice hooks (or they were not present).');
            } else {
                apw_woo_log('One or more default notice hooks might still be present.', 'warning');
            }
        }
    }

    /**
     * Outputs WooCommerce notices within a custom container.
     *
     * This function should be hooked where notices are desired. It calls
     * wc_print_notices() to render any queued notices (error, success, info).
     *
     * @since 1.2.5 (Your new version)
     */
    public function apw_woo_output_custom_notices()
    {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Executing apw_woo_output_custom_notices function.');
        }
        echo '<div class="apw-woo-notices-container">';
        // This function prints all queued notices (success, error, info)
        wc_print_notices();
        echo '</div>';
    }

    /**
     * Locate a template and return the path for inclusion.
     *
     * @param string $template Original template path.
     * @param string $template_name Template name.
     * @param string $template_path Template path.
     * @return string
     */
    public function locate_template($template, $template_name, $template_path)
    {
        // **OPTIMIZATION: Added input validation**
        if (empty($template_name)) {
            return $template;
        }

        // Look for template in our plugin
        $custom_template = $this->find_template_in_plugin($template_name);

        // Return our plugin template if it exists, otherwise return the original template
        if ($custom_template) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Using custom template: {$custom_template}");
            }
            return $custom_template;
        }

        return $template;
    }

    /**
     * Get template part (for templates in loops)
     *
     * @param string $template Original template path.
     * @param string $slug Template slug.
     * @param string $name Template name.
     * @return string
     */
    public function get_template_part($template, $slug, $name)
    {
        // **OPTIMIZATION: Added input validation**
        if (empty($slug) || empty($name)) {
            return $template;
        }

        // Create the template part filename
        $template_name = $slug . '-' . $name . '.php';

        // Look for template in our plugin
        $custom_template = $this->find_template_in_plugin($template_name);

        // Return our plugin template if it exists, otherwise return the original template
        if ($custom_template) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Using custom template part: {$custom_template}");
            }
            return $custom_template;
        }

        return $template;
    }

    /**
     * Find a template in plugin directories with caching for better performance
     *
     * @param string $template_name Template name.
     * @return string|false Path to template file or false if not found.
     */
    private function find_template_in_plugin($template_name)
    {
        // **OPTIMIZATION: Ensure template name is valid and contains .php extension**
        if (empty($template_name) || !preg_match('/\.php$/', $template_name)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Invalid template name: {$template_name}", 'warning');
            }
            return false;
        }

        // Check template cache first
        static $template_cache = [];

        if (isset($template_cache[$template_name])) {
            return $template_cache[$template_name];
        }

        // Also check WordPress object cache for persistent caching
        $cache_key = 'apw_template_' . md5($template_name);
        $cached_path = wp_cache_get($cache_key, 'apw-woo-plugin');

        if (false !== $cached_path) {
            // Store in static cache for this request
            $template_cache[$template_name] = $cached_path;
            return $cached_path;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Looking for template: {$template_name}");
        }

        // Define possible locations to check (in order of preference)
        $locations = [
            $this->template_path . self::WOOCOMMERCE_DIRECTORY . $template_name,
            $this->template_path . $template_name
        ];

        // Allow extending the template search locations via filter
        $locations = apply_filters('apw_woo_template_locations', $locations, $template_name);

        // Find the first existing template
        $found_template = false;

        foreach ($locations as $location) {
            if ($this->template_exists($location)) {
                $found_template = $location;
                break;
            }
        }

        // **OPTIMIZATION: Improve cache efficiency - use longer cache time for found templates,
        // shorter for not-found to allow for template creation**
        $template_cache[$template_name] = $found_template;

        $cache_time = $found_template ? HOUR_IN_SECONDS : MINUTE_IN_SECONDS * 5;
        wp_cache_set(
            $cache_key,
            $found_template,
            'apw-woo-plugin',
            $cache_time
        );

        return $found_template;
    }

    /**
     * Check if a template file exists with safeguards
     *
     * @param string $template_path Full path to template.
     * @return bool Whether the template exists
     */
    private function template_exists($template_path)
    {
        // **OPTIMIZATION: Added early return for empty paths**
        if (empty($template_path)) {
            return false;
        }

        // Sanitize and validate the path to prevent directory traversal
        $template_path = wp_normalize_path($template_path);
        $plugin_dir = wp_normalize_path(APW_WOO_PLUGIN_DIR);

        // Ensure the template path is within the plugin directory
        if (strpos($template_path, $plugin_dir) !== 0) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Security warning: Attempted to access template outside plugin directory: {$template_path}", 'warning');
            }
            return false;
        }

        // **OPTIMIZATION: Use is_readable() to ensure the file can be accessed**
        $exists = file_exists($template_path) && is_readable($template_path);

        if ($exists && APW_WOO_DEBUG_MODE) {
            apw_woo_log("Template found: {$template_path}");
        }

        return $exists;
    }

    /**
     * Load custom template based on the current view
     * Handles custom URL structures for products, categories, and shop
     */
    public function maybe_load_custom_template()
    {
        global $post, $wp;

        // Only affect WooCommerce pages
        if (!is_woocommerce()) {
            return;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("maybe_load_custom_template - Current post: " . ($post ? $post->post_name : 'No post'));
        }

        // Detect single product pages using multiple methods
        $is_single_product = $this->detect_product_page($wp);

        // **OPTIMIZATION: Use try-catch for better error handling during template loading**
        try {
            // Load appropriate template based on page type
            if ($is_single_product && $post) {
                // Force WooCommerce to use the correct product
                $GLOBALS['product'] = wc_get_product($post);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Loading product template for: " . $post->post_name . " (ID: " . $post->ID . ")");
                }

                $this->load_template_and_remove_defaults(self::PRODUCT_TEMPLATE);
            } elseif ($this->is_main_shop_page()) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Loading shop page template");
                }

                $this->load_template_and_remove_defaults(self::SHOP_TEMPLATE);
            } elseif (is_product_category()) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Loading category template");
                }

                $this->load_template_and_remove_defaults(self::CATEGORY_TEMPLATE);
            }
        } catch (Exception $e) {
            // **OPTIMIZATION: Added proper error handling for template loading failures**
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Error loading custom template: " . $e->getMessage(), 'error');
            }

            // Let WordPress continue with default template handling
        }
    }

    /**
     * Detect if current page is a product page using multiple methods
     *
     * @param object $wp The WordPress environment object
     * @return bool True if page is a product page
     */
    private function detect_product_page($wp)
    {
        // Use the dedicated page detector class
        return APW_Woo_Page_Detector::is_product_page($wp);
    }

    /**
     * Check if we're on the main shop page
     *
     * @return bool
     */
    private function is_main_shop_page()
    {
        return APW_Woo_Page_Detector::is_main_shop_page();
    }

    /**
     * Load template and remove default WooCommerce content
     *
     * Loads a template file from the specified path, removing default
     * WooCommerce content hooks while preserving specified ones.
     * Uses output buffering to capture and return the template output.
     *
     * @param string $template_relative_path Relative path to template from template directory
     * @param array $preserve_hooks Optional array of hooks to preserve
     * @param array $template_vars Optional variables to extract into template scope
     * @return string|bool Template content if successful, false otherwise
     */
    public function load_template_and_remove_defaults($template_relative_path, $preserve_hooks = array(), $template_vars = array())
    {
        // **OPTIMIZATION: Added more robust security check for directory traversal attempts**
        if (strpos($template_relative_path, '..') !== false) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Security warning: Attempted path traversal in template path: {$template_relative_path}", 'error');
            }
            return false;
        }

        // Prevent directory traversal attacks by sanitizing the path
        $template_relative_path = ltrim(preg_replace('#\.\./#', '', $template_relative_path), '/');

        // Build full path and verify it exists
        $template_path = $this->template_path . $template_relative_path;

        if (!$this->template_exists($template_path)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Template not found: {$template_path}");
            }
            return false;
        }

        // Performance tracking if enabled
        $start_time = microtime(true);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Loading template with buffering: ' . $template_path);
        }

        try {
            // Remove default WooCommerce content hooks, but preserve specified ones
            $this->remove_default_woocommerce_content($preserve_hooks);

            // **OPTIMIZATION: Check output buffer level before starting to prevent nesting issues**
            $initial_ob_level = ob_get_level();

            // Start output buffering
            ob_start();

            // **OPTIMIZATION: Better error handling with try-finally to ensure buffer is cleared**
            try {
                // Extract variables to template scope if provided
                if (!empty($template_vars) && is_array($template_vars)) {
                    extract($template_vars, EXTR_SKIP);
                }

                // Register restoration hooks before including template
                $this->register_product_restoration_hooks();

                // Include the template with limited scope
                include($template_path);

                // Get the buffered content
                $content = ob_get_clean();

            } catch (Exception $inner_e) {
                // Ensure buffer is cleaned up on error
                while (ob_get_level() > $initial_ob_level) {
                    ob_end_clean();
                }
                throw $inner_e; // Re-throw to be caught by outer try-catch
            }

            // Validate template structure if in debug mode
            if (APW_WOO_DEBUG_MODE) {
                $this->validate_template_structure($content, $template_path);

                // Log performance data
                $execution_time = microtime(true) - $start_time;
                $execution_ms = round($execution_time * 1000, 2);
                apw_woo_log("Template loading performance: {$template_relative_path} loaded in {$execution_ms}ms");
            }

            // Apply filters to allow modifying the template output
            $content = apply_filters('apw_woo_template_output', $content, $template_relative_path);

            return $content;

        } catch (Exception $e) {
            // Clean up output buffer if an error occurred
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Template loading error: " . $e->getMessage(), 'error');
            }

            return false;
        }
    }

    /**
     * Validate template structure to catch common template issues
     *
     * @param string $content The template content
     * @param string $template_path The template path for reference in logs
     * @return bool True if validation passes, false if issues found
     */
    private function validate_template_structure($content, $template_path)
    {
        $validation_passed = true;
        $template_name = basename($template_path);
        $issues = [];

        // **OPTIMIZATION: More comprehensive template validation**

        // Check for multiple get_header calls
        $header_count = substr_count($content, 'get_header');
        if ($header_count > 1) {
            $issues[] = "Contains multiple get_header() calls ({$header_count})";
            $validation_passed = false;
        }

        // Check for multiple get_footer calls
        $footer_count = substr_count($content, 'get_footer');
        if ($footer_count > 1) {
            $issues[] = "Contains multiple get_footer() calls ({$footer_count})";
            $validation_passed = false;
        }

        // Check for direct HTML output outside of PHP (potential leaks)
        if (preg_match('/^\s*<(?!php|!DOCTYPE|!--|!$)/im', $content)) {
            $issues[] = "Contains direct HTML output outside of PHP tags";
            $validation_passed = false;
        }

        // Check for common WooCommerce hooks that should be preserved
        $required_hooks = [
            'woocommerce_before_single_product',
            'woocommerce_after_single_product',
            'woocommerce_before_main_content',
            'woocommerce_after_main_content'
        ];

        foreach ($required_hooks as $hook) {
            $found = (strpos($content, "do_action('{$hook}'") !== false ||
                strpos($content, "do_action(\"{$hook}\"") !== false);

            // Only log a warning for product template if it's a product-related hook
            if (!$found && strpos($template_name, 'product') !== false && strpos($hook, 'product') !== false) {
                $issues[] = "Missing required hook: {$hook}";
                $validation_passed = false;
            }
        }

        // **OPTIMIZATION: Check for potential PHP errors in templates**
        $php_error_patterns = [
            '/(undefined variable|undefined index|undefined offset|undefined constant)/i',
            '/(trying to access array offset on value of type null|trying to access array offset on bool)/i',
            '/call to undefined function/i'
        ];

        foreach ($php_error_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $issues[] = "May contain PHP error patterns";
                $validation_passed = false;
                break;
            }
        }

        // Log individual issues
        if (!empty($issues)) {
            foreach ($issues as $issue) {
                apw_woo_log("TEMPLATE ERROR: {$template_name} {$issue}");
            }
        }

        // Log overall validation status
        if ($validation_passed) {
            apw_woo_log("TEMPLATE VALIDATED: {$template_name} structure looks good");
        } else {
            apw_woo_log("TEMPLATE ISSUES: {$template_name} has " . count($issues) . " structural issues that should be addressed");
        }

        return $validation_passed;
    }

    /**
     * Remove default WooCommerce loop content while preserving specified hooks
     *
     * @param array $preserve_hooks Optional array of hooks to preserve in format ['hook_name' => ['callback' => 'function_name', 'priority' => 10]]
     */
    public function remove_default_woocommerce_content($preserve_hooks = array())
    {
        // **OPTIMIZATION: Add caching so we don't repeatedly calculate the same hooks to remove**
        $cache_key = md5(serialize($preserve_hooks));

        if (isset(self::$hook_removal_cache[$cache_key])) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Using cached hook removal information');
            }
            return;
        }

        // Store all hooks to remove in an array for easier maintenance
        $hooks_to_remove = [
            // Before shop loop hooks
            ['woocommerce_before_shop_loop', 'woocommerce_output_all_notices', 10],
            ['woocommerce_before_shop_loop', 'woocommerce_result_count', 20],
            ['woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30],
            // Shop loop hooks
            ['woocommerce_shop_loop', 'woocommerce_product_loop_start', 10],
            ['woocommerce_shop_loop', 'woocommerce_product_loop_end', 10],
            // After shop loop hooks
            ['woocommerce_after_shop_loop', 'woocommerce_pagination', 10],
            // No products found hook
            ['woocommerce_no_products_found', 'wc_no_products_found', 10],
            // Additional hooks that might interfere with extensions
            ['woocommerce_before_single_product', 'woocommerce_output_all_notices', 10],

            // **OPTIMIZATION: Additional hooks that could cause conflicts**
            ['woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10],
            ['woocommerce_archive_description', 'woocommerce_product_archive_description', 10]
        ];

        // **OPTIMIZATION: Allow extending the hooks to remove via filter**
        $hooks_to_remove = apply_filters('apw_woo_hooks_to_remove', $hooks_to_remove);

        // Process hooks to remove
        foreach ($hooks_to_remove as $hook) {
            list($hook_name, $callback, $priority) = $hook;

            // Check if this hook should be preserved
            $preserve = false;
            if (isset($preserve_hooks[$hook_name])) {
                foreach ($preserve_hooks[$hook_name] as $preserved_callback) {
                    if (isset($preserved_callback['callback']) && isset($preserved_callback['priority']) &&
                        $preserved_callback['callback'] === $callback &&
                        $preserved_callback['priority'] === $priority) {
                        $preserve = true;
                        break;
                    }
                }
            }

            // Only remove if not preserved
            if (!$preserve) {
                remove_action($hook_name, $callback, $priority);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Removed hook: {$hook_name}, callback: {$callback}, priority: {$priority}");
                }
            } else {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Preserved hook: {$hook_name}, callback: {$callback}, priority: {$priority}");
                }
            }
        }

        // Cache the fact that we've processed this combination of hooks
        self::$hook_removal_cache[$cache_key] = true;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Finished processing WooCommerce content hooks');
        }
    }

    /**
     * Register a filter to intercept WordPress template inclusion
     * This allows us to override templates at the WordPress level
     */
    public function register_template_include_filter()
    {
        // **OPTIMIZATION: Added check to prevent registering the filter multiple times**
        static $filter_registered = false;

        if ($filter_registered) {
            return;
        }

        // Create template resolver instance
        $template_resolver = new APW_Woo_Template_Resolver($this);

        // Register filter using the resolver
        add_filter('template_include', function ($template) use ($template_resolver) {
            return $template_resolver->resolve_template($template);
        }, 99);

        $filter_registered = true;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Registered template_include filter with resolver');
        }
    }

    /**
     * Restore the original product during template rendering
     *
     * This critical method prevents WooCommerce hooks or related product displays from
     * causing the wrong product to be displayed, especially during FAQ rendering and
     * when displaying related products.
     *
     * The method checks if the global product has changed and restores it to the
     * original product when necessary to maintain consistent URLs and product data.
     */
    public function restore_original_product()
    {
        global $post, $product;

        // Only proceed if we have stored an original product
        if (self::$original_product_id <= 0 || !self::$original_product instanceof WC_Product) {
            return;
        }

        // **OPTIMIZATION: Added more robust product validation**

        // Check if product needs to be restored:
        // 1. The current product is not a valid product object, or
        // 2. The current product ID doesn't match our original product ID
        $needs_restore = !is_a($product, 'WC_Product') || $product->get_id() != self::$original_product_id;

        // Apply filter to allow other code to control product restoration
        $needs_restore = apply_filters('apw_woo_needs_product_restoration', $needs_restore, $product, self::$original_product);

        if ($needs_restore) {
            if (APW_WOO_DEBUG_MODE) {
                $current_id = is_a($product, 'WC_Product') ? $product->get_id() : 'none';
                apw_woo_log("PRODUCT FIX: Restoring original product ID: " . self::$original_product_id .
                    " (was: " . $current_id . ")");
            }

            // **OPTIMIZATION: Ensure we're restoring a valid post**
            $original_post = get_post(self::$original_product_id);

            if ($original_post && $original_post->post_type === 'product') {
                // Restore the original post
                $post = $original_post;
                setup_postdata($post);

                // Restore the original product object
                $product = self::$original_product;

                // Notify other code that product has been restored
                do_action('apw_woo_product_restored', self::$original_product_id, self::$original_product);
            } else if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PRODUCT FIX ERROR: Failed to get valid post for product ID {$self::$original_product_id}", 'error');
            }
        }
    }

    /**
     * Register hooks for product restoration
     *
     * This ensures the original product is maintained throughout template rendering,
     * even when related products or other WooCommerce elements might change it.
     */
    public function register_product_restoration_hooks()
    {
        // **OPTIMIZATION: Added static flag to prevent registering hooks multiple times**
        static $hooks_registered = false;

        if ($hooks_registered) {
            return;
        }

        // Critical WooCommerce template hooks
        $woo_hooks = array(
            'woocommerce_before_single_product',
            'woocommerce_before_template_part',
            'woocommerce_after_template_part',
            'woocommerce_before_shop_loop_item',
            'woocommerce_after_shop_loop_item',
            'woocommerce_before_single_product_summary',
            'woocommerce_after_single_product_summary',
            'woocommerce_product_meta_start',
            'woocommerce_product_meta_end',
            // Additional hooks for better coverage
            'woocommerce_before_shop_loop',
            'woocommerce_after_shop_loop',
            'woocommerce_before_add_to_cart_form',
            'woocommerce_after_add_to_cart_form',
            'woocommerce_related_products',
            'woocommerce_upsell_display',

            // **OPTIMIZATION: More hooks for comprehensive product restoration**
            'woocommerce_single_product_summary',
            'woocommerce_after_add_to_cart_button',
            'woocommerce_before_add_to_cart_quantity',
            'woocommerce_after_add_to_cart_quantity',
            'woocommerce_product_thumbnails',
            'woocommerce_product_tabs'
        );

        // Custom hooks where product might change
        $custom_hooks = array(
            'apw_woo_before_product_faqs',
            'apw_woo_after_product_faqs',
            'apw_woo_before_faq_section',
            'apw_woo_after_faq_section'
        );

        // Combine all hooks
        $hooks = array_merge($woo_hooks, $custom_hooks);

        // Allow other code to add additional hooks for product restoration
        $hooks = apply_filters('apw_woo_product_restoration_hooks', $hooks);

        // Register all hooks
        foreach ($hooks as $hook) {
            add_action($hook, array($this, 'restore_original_product'), 5); // Priority 5 to run early
        }

        $hooks_registered = true;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Registered product restoration hooks for " . count($hooks) . " hooks");
        }
    }

    /**
     * Fix Yoast SEO breadcrumbs to use the correct product
     *
     * @param array $links The breadcrumb links
     * @return array Modified breadcrumb links
     */
    public function fix_yoast_breadcrumbs($links)
    {
        // **OPTIMIZATION: Better validation before modifying links**
        if (!is_array($links) || empty($links)) {
            return $links;
        }

        if (self::$original_product_id > 0 && self::$original_product instanceof WC_Product) {
            // Find and replace the last item in the breadcrumb trail
            $last_index = count($links) - 1;

            if (isset($links[$last_index])) {
                $links[$last_index]['text'] = self::$original_product->get_name();
                $links[$last_index]['url'] = get_permalink(self::$original_product_id);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("BREADCRUMB FIX: Replaced product in breadcrumbs with: " . self::$original_product->get_name());
                }
            }
        }

        return $links;
    }

    /**
     * Fix Yoast SEO title to use the correct product
     *
     * @param string $title The page title
     * @return string Modified page title
     */
    public function fix_yoast_title($title)
    {
        // **OPTIMIZATION: Better validation and more robust regex pattern**
        if (empty($title) || !is_string($title)) {
            return $title;
        }

        if (self::$original_product_id > 0 && self::$original_product instanceof WC_Product) {
            // **OPTIMIZATION: Improved regex to be more specific and avoid unintended replacements**
            $product_name = preg_quote(self::$original_product->get_name(), '/');
            $new_title = $title;

            // Try pattern specific to our known products first
            if (strpos($title, 'Wireless Router') !== false) {
                $new_title = preg_replace('/.*?Wireless Router/', self::$original_product->get_name(), $title);
            } // More generic approach as fallback
            else if (preg_match('/^.*?[\s\-–—]\s/', $title)) {
                // This will replace the content before the first separator (-, —, –, or similar)
                $new_title = preg_replace('/^.*?[\s\-–—]\s/', self::$original_product->get_name() . ' - ', $title);
            }

            if ($new_title !== $title && APW_WOO_DEBUG_MODE) {
                apw_woo_log("TITLE FIX: Changed page title from \"{$title}\" to \"{$new_title}\"");
            }

            return $new_title;
        }

        return $title;
    }

    /**
     * Fix document title as a fallback
     *
     * @param string $title The document title
     * @return string Modified document title
     */
    public function fix_document_title($title)
    {
        // **OPTIMIZATION: Better validation**
        if (!empty($title) || self::$original_product_id <= 0 || !self::$original_product instanceof WC_Product) {
            return $title;
        }

        $site_name = get_bloginfo('name');
        $product_name = self::$original_product->get_name();

        $title = $product_name . ' - ' . $site_name;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("DOCUMENT TITLE FIX: Set document title to \"{$title}\"");
        }

        return $title;
    }
}