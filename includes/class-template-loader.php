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
 *
 * Handles the loading of custom templates for WooCommerce pages,
 * including product, category, and shop pages.
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
    private const PRODUCT_TEMPLATE = 'woocommerce/single-product.php';
    private const CART_TEMPLATE = 'woocommerce/cart/cart.php';
    private const CHECKOUT_TEMPLATE = 'woocommerce/checkout/form-checkout.php';
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

        // Register template include filter for better template control
        $this->register_template_include_filter();

        // Keep the legacy method for backward compatibility during transition
        // Can be removed in a future version once we confirm the new method works
        add_action('woocommerce_before_main_content', [$this, 'maybe_load_custom_template']);

        // Remove default Flatsome/WooCommerce elements
        $this->remove_default_woocommerce_elements();

        // Debug product permalinks
        if (APW_WOO_DEBUG_MODE) {
            add_filter('post_type_link', [$this, 'debug_product_permalinks'], 99, 2);
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Template loader hooks initialized with template_include filter');
        }
    }

    /**
     * Remove default WooCommerce and Flatsome elements that we don't want
     */
    private function remove_default_woocommerce_elements() {
        // Remove Flatsome page title (which includes breadcrumbs)
        remove_action('flatsome_after_header', 'flatsome_pages_title', 12);

        // Remove WooCommerce breadcrumbs
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);

        // Remove result count
        remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);

        // Remove ordering dropdown
        remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);

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
    public function debug_product_permalinks($permalink, $post) {
        if ($post->post_type === 'product') {
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
    private function debug_permalink($product_slug, $requested_url) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Permalink Debug - Product Slug: {$product_slug}, Requested URL: {$requested_url}");
        }
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
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Using custom template part: {$custom_template}");
            }
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
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Looking for template: {$template_name}");
        }
        // Check each location
        foreach ($locations as $location) {
            if ($this->template_exists($location)) {
                return $location;
            }
        }
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
        if ($exists && APW_WOO_DEBUG_MODE) {
            apw_woo_log("Template found: {$template_path}");
        }
        return $exists;
    }
    /**
     * Load custom template based on the current view
     * Handles custom URL structures for products, categories, and shop
     */
    public function maybe_load_custom_template() {
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
    }
    /**
     * Detect if current page is a product page using multiple methods
     *
     * @param object $wp The WordPress environment object
     * @return bool True if page is a product page
     */
    private function detect_product_page($wp) {
        global $post;

        // Debug the current request
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Detect product page - Request: " . print_r($wp->request, true));
            if ($post) {
                apw_woo_log("Current post: " . $post->post_name . " (ID: " . $post->ID . ")");
            }
        }

        // Method 1: Standard WooCommerce function
        if (is_product()) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Detected as product via is_product()");
            }
            return true;
        }

        // Method 2: WordPress singular check
        if (get_post_type() === 'product' && is_singular('product')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Detected as product via get_post_type and is_singular");
            }
            return true;
        }

        // Method 3: Custom URL structure detection
        if ($post && get_post_type() === 'product') {
            $url_parts = explode('/', trim($wp->request, '/'));
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("URL parts: " . print_r($url_parts, true));
            }

            if (count($url_parts) >= 2 && $url_parts[0] === 'products') {
                // Get the actual product slug from the URL
                $product_slug = end($url_parts);
                $this->debug_permalink($product_slug, $wp->request);

                // Let's manually check if this product exists
                $product_by_slug = get_page_by_path($product_slug, OBJECT, 'product');

                if ($product_by_slug) {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("Found product by slug: " . $product_slug);
                    }

                    // Make sure WP knows we're on this product
                    $post = $product_by_slug;
                    setup_postdata($post);

                    return true;
                }
            }
        }

        return false;
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
     * Load template and remove default WooCommerce content
     *
     * @param string $template_relative_path Relative path to template from template directory
     * @param array $preserve_hooks Optional array of hooks to preserve
     * @return string|bool Template content if successful, false otherwise
     */
    public function load_template_and_remove_defaults($template_relative_path, $preserve_hooks = array()) {
        $template_path = $this->template_path . $template_relative_path;

        if (file_exists($template_path)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Loading template with buffering: ' . $template_path);
            }

            // Remove default WooCommerce content hooks, but preserve specified ones
            $this->remove_default_woocommerce_content($preserve_hooks);

            // Start output buffering
            ob_start();
            include($template_path);
            $content = ob_get_clean();

            // Validate template structure if in debug mode
            if (APW_WOO_DEBUG_MODE) {
                $this->validate_template_structure($content, $template_path);
            }

            return $content;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Template not found: {$template_path}");
        }

        return false;
    }

    /**
     * Validate template structure to catch common template issues
     *
     * @param string $content The template content
     * @param string $template_path The template path for reference in logs
     * @return bool True if validation passes, false if issues found
     */
    private function validate_template_structure($content, $template_path) {
        $validation_passed = true;
        $template_name = basename($template_path);

        // Check for multiple get_header calls
        $header_count = substr_count($content, 'get_header');
        if ($header_count > 1) {
            apw_woo_log("TEMPLATE ERROR: {$template_name} contains multiple get_header() calls ({$header_count})");
            $validation_passed = false;
        }

        // Check for multiple get_footer calls
        $footer_count = substr_count($content, 'get_footer');
        if ($footer_count > 1) {
            apw_woo_log("TEMPLATE ERROR: {$template_name} contains multiple get_footer() calls ({$footer_count})");
            $validation_passed = false;
        }

        // Check for direct HTML output outside of PHP (potential leaks)
        if (preg_match('/^\s*<(?!php|!DOCTYPE|!--|!$)/im', $content)) {
            apw_woo_log("TEMPLATE WARNING: {$template_name} might contain direct HTML output outside of PHP tags");
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
            if (!strpos($content, "do_action('{$hook}'") &&
                !strpos($content, "do_action(\"{$hook}\"")) {
                // Only log a warning for product template if it's a product-related hook
                if (strpos($template_name, 'product') !== false && strpos($hook, 'product') !== false) {
                    apw_woo_log("TEMPLATE WARNING: {$template_name} might be missing required hook: {$hook}");
                    $validation_passed = false;
                }
            }
        }

        // Log overall validation status
        if ($validation_passed) {
            apw_woo_log("TEMPLATE VALIDATED: {$template_name} structure looks good");
        } else {
            apw_woo_log("TEMPLATE ISSUES: {$template_name} has structural issues that should be addressed");
        }

        return $validation_passed;
    }


    /**
     * Remove default WooCommerce loop content while preserving specified hooks
     *
     * @param array $preserve_hooks Optional array of hooks to preserve in format ['hook_name' => ['callback' => 'function_name', 'priority' => 10]]
     */
    public function remove_default_woocommerce_content($preserve_hooks = array()) {
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
            ['woocommerce_before_single_product', 'woocommerce_output_all_notices', 10]
        ];

        // Process hooks to remove
        foreach ($hooks_to_remove as $hook) {
            list($hook_name, $callback, $priority) = $hook;

            // Check if this hook should be preserved
            $preserve = false;
            if (isset($preserve_hooks[$hook_name])) {
                foreach ($preserve_hooks[$hook_name] as $preserved_callback) {
                    if ($preserved_callback['callback'] === $callback &&
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

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Finished processing WooCommerce content hooks');
        }
    }

    /**
     * Register a filter to intercept WordPress template inclusion
     * This allows us to override templates at the WordPress level
     */
    public function register_template_include_filter() {
        add_filter('template_include', array($this, 'maybe_override_template'), 99);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Registered template_include filter');
        }
    }

    /**
     * Override template based on current page type
     *
     * @param string $template The current template path
     * @return string Modified template path
     */
    public function maybe_override_template($template) {
        // Only affect WooCommerce templates
        if (!is_woocommerce() && !is_cart() && !is_checkout()) {
            return $template;
        }

        // Log the override attempt
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Attempting to override template: ' . $template);
        }

        // Get custom template based on page type
        $custom_template = false;

        if (is_shop() && !is_search()) {
            // Main shop page
            $custom_template = $this->template_path . self::SHOP_TEMPLATE;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Shop page detected - trying template: ' . $custom_template);
            }
        } elseif (is_product_category()) {
            // Product category page
            $custom_template = $this->template_path . self::CATEGORY_TEMPLATE;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Category page detected - trying template: ' . $custom_template);
            }
        } elseif (is_product()) {
            // Single product page
            $custom_template = $this->template_path . self::PRODUCT_TEMPLATE;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Product page detected - trying template: ' . $custom_template);
            }
        } elseif (is_cart()) {
            // Cart page
            $custom_template = $this->template_path . self::CART_TEMPLATE;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cart page detected - trying template: ' . $custom_template);
            }
        } elseif (is_checkout()) {
            // Checkout page
            $custom_template = $this->template_path . self::CHECKOUT_TEMPLATE;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Checkout page detected - trying template: ' . $custom_template);
            }
        }

        // Return custom template if it exists, otherwise return default
        if ($custom_template && file_exists($custom_template)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Using custom template: ' . $custom_template);
            }
            return $custom_template;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('No custom template found, using default: ' . $template);
        }
        return $template;
    }
}
