<?php
/**
 * Template Loader for APW WooCommerce Plugin
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Loader
 */
class APW_Woo_Template_Loader {
    /**
     * Template path constants
     */
    private const TEMPLATE_DIRECTORY = 'templates/';
    private const WOOCOMMERCE_DIRECTORY = 'woocommerce/';
    private const PARTIALS_DIRECTORY = 'partials/';
    private const SHOP_TEMPLATE = 'woocommerce/partials/shop-categories-display.php';
    private const CATEGORY_TEMPLATE = 'woocommerce/partials/category-products-display.php';

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
     * Constructor
     */
    private function __construct() {
        $this->template_path = APW_WOO_PLUGIN_DIR . self::TEMPLATE_DIRECTORY;

        // Initialize hooks
        $this->init_hooks();

        apw_woo_log('Template loader initialized');
    }

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
     * Initialize hooks
     */
    private function init_hooks() {
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

        // Load custom partials when needed
        add_action('woocommerce_before_main_content', [$this, 'maybe_load_custom_template']);
    }

    /**
     * Locate a template and return the path for inclusion.
     *
     * @param string $template      Original template path.
     * @param string $template_name Template name.
     * @param string $template_path Template path.
     * @return string
     */
    public function locate_template($template, $template_name, $template_path) {
        // Look for template in our plugin
        $custom_template = $this->find_template_in_plugin($template_name);

        // Debug
        apw_woo_log("TEST: woocommerce_locate_template filter triggered for {$template_name}");

        // Return our plugin template if it exists, otherwise return the original template
        if ($custom_template) {
            apw_woo_log("Using custom template: {$custom_template}");
            return $custom_template;
        }

        return $template;
    }

    /**
     * Get template part (for templates in loops)
     *
     * @param string $template Original template path.
     * @param string $slug     Template slug.
     * @param string $name     Template name.
     * @return string
     */
    public function get_template_part($template, $slug, $name) {
        // Create the template part filename
        $template_name = $slug . '-' . $name . '.php';

        // Look for template in our plugin
        $custom_template = $this->find_template_in_plugin($template_name);

        // Return our plugin template if it exists, otherwise return the original template
        if ($custom_template) {
            apw_woo_log("Using custom template part: {$custom_template}");
            return $custom_template;
        }

        return $template;
    }

    /**
     * Find a template in plugin directories
     *
     * @param string $template_name Template name.
     * @return string|false Path to template file or false if not found.
     */
    private function find_template_in_plugin($template_name) {
        // Define possible locations to check (in order of preference)
        $locations = [
            $this->template_path . self::WOOCOMMERCE_DIRECTORY . $template_name,
            $this->template_path . $template_name
        ];

        // Log the template we're looking for and the full paths being checked
        apw_woo_log("Looking for template: {$template_name}");
        apw_woo_log("Template path base: {$this->template_path}");
        apw_woo_log("Full template paths to check:");
        foreach ($locations as $index => $location) {
            apw_woo_log("  Path {$index}: {$location} (exists: " . (file_exists($location) ? 'yes' : 'no') . ")");
        }

        // Check each location
        foreach ($locations as $location) {
            if ($this->template_exists($location)) {
                return $location;
            }
        }

        apw_woo_log("No template found for: {$template_name} in any location");
        return false;
    }

    /**
     * Check if a template file exists
     *
     * @param string $template_path Full path to template.
     * @return bool
     */
    private function template_exists($template_path) {
        $exists = file_exists($template_path);

        if ($exists) {
            apw_woo_log("Template found: {$template_path}");
        }

        return $exists;
    }

    /**
     * Maybe load custom template based on the current view
     */
    public function maybe_load_custom_template() {
        // Add debug info about the current page
        apw_woo_log('Checking page type for custom template: ' .
            (is_woocommerce() ? 'Is WooCommerce page' : 'Not WooCommerce page') . ', ' .
            (is_shop() ? 'Is Shop page' : 'Not Shop page') . ', ' .
            (is_product_category() ? 'Is Category page' : 'Not Category page') . ', ' .
            (is_product() ? 'Is Product page' : 'Not Product page')
        );

        // Only affect WooCommerce pages
        if (!is_woocommerce()) {
            apw_woo_log('Not a WooCommerce page, exiting template loader');
            return;
        }

        // Different templates for different views
        if ($this->is_main_shop_page()) {
            apw_woo_log('Attempting to load shop template');
            $this->load_shop_template();
        } elseif (is_product_category()) {
            apw_woo_log('Attempting to load category template');
            $this->load_category_template();
        } elseif (is_product()) {
            apw_woo_log('Attempting to load product template');
            $this->load_product_customizations();
        } else {
            apw_woo_log('No matching template condition found');
        }
    }

    /**
     * Check if we're on the main shop page
     *
     * @return bool
     */
    private function is_main_shop_page() {
        return is_shop() && !is_search();
    }

    /**
     * Load shop page template
     */
    private function load_shop_template() {
        apw_woo_log('Loading shop categories template');

        if ($this->load_template(self::SHOP_TEMPLATE)) {
            $this->remove_default_woocommerce_content();
        }
    }

    /**
     * Load category page template
     */
    private function load_category_template() {
        apw_woo_log('Loading category products template');

        if ($this->load_template(self::CATEGORY_TEMPLATE)) {
            $this->remove_default_woocommerce_content();
        }
    }

    /**
     * Load product customizations
     */
    private function load_product_customizations() {
        apw_woo_log('Loading single product customizations');
        // No need to prevent default content here, as we'll use template overrides
    }

    /**
     * Load a template file
     *
     * @param string $template_relative_path Relative path to template from template directory.
     * @return bool True if template was loaded, false otherwise.
     */
    private function load_template($template_relative_path) {
        $template_path = $this->template_path . $template_relative_path;
        apw_woo_log('Trying to load template: ' . $template_path . ' (exists: ' . (file_exists($template_path) ? 'yes' : 'no') . ')');

        if (file_exists($template_path)) {
            apw_woo_log('Including template: ' . $template_path);
            include($template_path);
            return true;
        }

        apw_woo_log("Template not found: {$template_path}");
        return false;
    }

    /**
     * Remove default WooCommerce loop content
     */
    private function remove_default_woocommerce_content() {
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
            ['woocommerce_no_products_found', 'wc_no_products_found', 10]
        ];

        // Remove all hooks
        foreach ($hooks_to_remove as $hook) {
            list($hook_name, $callback, $priority) = $hook;
            remove_action($hook_name, $callback, $priority);
        }

        apw_woo_log('Removed default WooCommerce content hooks');
    }
}