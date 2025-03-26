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
        add_action('wp_head', function() {
            if (is_woocommerce()) {
                echo '<style>.shop-page-title { display: none !important; }</style>';
            }
        });
        // Additional safety: remove at higher priority
        add_action('init', function() {
            remove_action('flatsome_after_header', 'flatsome_pages_title', 12);
            remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
            remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
            remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
        }, 20);
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
            apw_woo_log("PRODUCT DETECTION: Starting product page detection");
            apw_woo_log("PRODUCT DETECTION: Request URL: " . print_r($wp->request, true));
            if ($post) {
                apw_woo_log("PRODUCT DETECTION: Current post: " . $post->post_name . " (ID: " . $post->ID . ", Type: " . get_post_type($post) . ")");
            } else {
                apw_woo_log("PRODUCT DETECTION: No current post object found");
            }
        }
        // Method 1: Standard WooCommerce function
        if (is_product()) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PRODUCT DETECTION: Method 1 SUCCESS - Detected as product via is_product()");
            }
            return true;
        } else if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PRODUCT DETECTION: Method 1 FAILED - is_product() returned false");
        }
        // Method 2: WordPress singular check
        if (get_post_type() === 'product' && is_singular('product')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PRODUCT DETECTION: Method 2 SUCCESS - Detected as product via get_post_type and is_singular");
            }
            return true;
        } else if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PRODUCT DETECTION: Method 2 FAILED - get_post_type: " . get_post_type() . ", is_singular('product'): " . (is_singular('product') ? 'true' : 'false'));
        }
        // Method 3: Custom URL structure detection
        if ($wp->request) {
            $url_parts = explode('/', trim($wp->request, '/'));
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PRODUCT DETECTION: Method 3 - URL parts: " . print_r($url_parts, true));
            }
            // Check if URL starts with 'products'
            if (count($url_parts) >= 2 && $url_parts[0] === 'products') {
                // Get the last part of the URL as the product slug
                $product_slug = end($url_parts);
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("PRODUCT DETECTION: Method 3 - Trying to find product with slug: " . $product_slug);
                }
                // Try to find this product
                $args = array(
                    'name'        => $product_slug,
                    'post_type'   => 'product',
                    'post_status' => 'publish',
                    'numberposts' => 1
                );
                $products = get_posts($args);
                if (!empty($products)) {
                    $product_post = $products[0];
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("PRODUCT DETECTION: Method 3 SUCCESS - Found product by slug: " . $product_slug . " (ID: " . $product_post->ID . ")");
                    }
                    // Make sure WP knows we're on this product
                    $post = $product_post;
                    setup_postdata($post);
                    // Override the main query
                    global $wp_query;
                    $wp_query->is_single = true;
                    $wp_query->is_singular = true;
                    $wp_query->is_product = true;
                    $wp_query->is_post_type_archive = false;
                    $wp_query->is_archive = false;
                    $wp_query->queried_object = $post;
                    $wp_query->queried_object_id = $post->ID;
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("PRODUCT DETECTION: Method 3 - Override main query settings to force product page");
                    }
                    return true;
                } else {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("PRODUCT DETECTION: Method 3 FAILED - No product found with slug: " . $product_slug);
                        // Check if this is potentially a category
                        $term = get_term_by('slug', $product_slug, 'product_cat');
                        if ($term) {
                            apw_woo_log("PRODUCT DETECTION: Note - Found a category with this slug instead: " . $term->name);
                        }
                    }
                }
            } else if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PRODUCT DETECTION: Method 3 FAILED - URL does not match expected product URL pattern");
            }
        } else if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PRODUCT DETECTION: Method 3 FAILED - No request URL found");
        }
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PRODUCT DETECTION: All methods failed - not a product page");
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
        global $wp, $post, $product;

        // First check for custom product permalink structure
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("TEMPLATE OVERRIDE: Checking URL: " . $wp->request);
            apw_woo_log("TEMPLATE OVERRIDE: is_shop(): " . (is_shop() ? 'true' : 'false'));
            apw_woo_log("TEMPLATE OVERRIDE: is_product_category(): " . (is_product_category() ? 'true' : 'false'));
            apw_woo_log("TEMPLATE OVERRIDE: is_product(): " . (is_product() ? 'true' : 'false'));
            apw_woo_log("TEMPLATE OVERRIDE: is_cart(): " . (is_cart() ? 'true' : 'false'));
            apw_woo_log("TEMPLATE OVERRIDE: is_checkout(): " . (is_checkout() ? 'true' : 'false'));
            apw_woo_log("TEMPLATE OVERRIDE: is_account_page(): " . (is_account_page() ? 'true' : 'false'));
        }

        // Special handling for /products/%product_cat%/ permalink structure
        $url_parts = explode('/', trim($wp->request, '/'));
        if (count($url_parts) >= 3 && $url_parts[0] === 'products') {
            // Get the product slug (last part of URL)
            $product_slug = end($url_parts);

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Checking for product with slug: " . $product_slug);
            }

            // Try to find a product with this slug
            $args = array(
                'name'        => $product_slug,
                'post_type'   => 'product',
                'post_status' => 'publish',
                'numberposts' => 1
            );
            $products = get_posts($args);

            if (!empty($products)) {
                $product_post = $products[0];

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Found product '" . $product_post->post_title . "' (ID: " . $product_post->ID . ") at URL: " . $wp->request);
                }

                // Store the original product ID and object to ensure we can restore it
                self::$original_product_id = $product_post->ID;
                self::$original_product = wc_get_product($product_post->ID);

                if (APW_WOO_DEBUG_MODE && self::$original_product) {
                    apw_woo_log("PRODUCT DEBUG: Stored original product: " . self::$original_product->get_name() . " (ID: " . self::$original_product_id . ")");
                }

                // Setup the product post data
                $post = $product_post;
                setup_postdata($post);

                // Create a WC_Product object for use in template
                $GLOBALS['product'] = wc_get_product($product_post->ID);

                // Force WordPress to treat this as a product page
                global $wp_query;
                $wp_query->is_single = true;
                $wp_query->is_singular = true;
                $wp_query->is_product = true;
                $wp_query->is_archive = false;
                $wp_query->is_post_type_archive = false;
                $wp_query->is_tax = false;
                $wp_query->is_category = false;
                $wp_query->queried_object = $post;
                $wp_query->queried_object_id = $post->ID;

                // Add hooks to restore the original product during template rendering
                add_action('woocommerce_before_single_product', array($this, 'restore_original_product'));
                add_action('woocommerce_before_template_part', array($this, 'restore_original_product'));
                add_action('woocommerce_after_template_part', array($this, 'restore_original_product'));
                add_action('woocommerce_before_shop_loop_item', array($this, 'restore_original_product'));
                add_action('woocommerce_before_single_product_summary', array($this, 'restore_original_product'));
                add_action('woocommerce_after_single_product_summary', array($this, 'restore_original_product'));

                // Add hooks to fix Yoast SEO breadcrumbs and page title
                add_filter('wpseo_breadcrumb_links', array($this, 'fix_yoast_breadcrumbs'), 5);
                add_filter('wpseo_title', array($this, 'fix_yoast_title'), 5);
                add_filter('pre_get_document_title', array($this, 'fix_document_title'), 5);

                // Load the single product template
                $custom_template = $this->template_path . self::PRODUCT_TEMPLATE;
                if (file_exists($custom_template)) {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("TEMPLATE OVERRIDE: Loading single product template: " . $custom_template);
                    }
                    return $custom_template;
                } else {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("TEMPLATE OVERRIDE ERROR: Could not find product template at: " . $custom_template);
                    }
                }
            } else if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: No product found with slug: " . $product_slug);
            }
        }

        // Handle category pages - check if we're on a product category page
        if (is_product_category()) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Detected product category page");
            }

            $custom_template = $this->template_path . self::CATEGORY_TEMPLATE;
            if (file_exists($custom_template)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Loading category template: " . $custom_template);
                }
                return $custom_template;
            }
        }

        // Handle shop page
        if (is_shop() && !is_search()) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Detected main shop page");
            }

            $custom_template = $this->template_path . self::SHOP_TEMPLATE;
            if (file_exists($custom_template)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Loading shop template: " . $custom_template);
                }
                return $custom_template;
            }
        }

        // Handle cart page
        if (is_cart()) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Detected cart page");
            }

            $custom_template = $this->template_path . self::CART_TEMPLATE;
            if (file_exists($custom_template)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Loading cart template: " . $custom_template);
                }
                return $custom_template;
            }
        }

        // Handle checkout page
        if (is_checkout()) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Detected checkout page");
            }

            $custom_template = $this->template_path . self::CHECKOUT_TEMPLATE;
            if (file_exists($custom_template)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Loading checkout template: " . $custom_template);
                }
                return $custom_template;
            }
        }

        // Handle account page
        if (is_account_page()) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Detected account page");
            }

            $custom_template = $this->template_path . self::MY_ACCOUNT_TEMPLATE;
            if (file_exists($custom_template)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Loading account template: " . $custom_template);
                }
                return $custom_template;
            }
        }

        // If we've made it here, no custom template was found
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('No custom template found, using default: ' . $template);
        }
        return $template;
    }

    /**
     * Restore the original product during template rendering
     * This prevents WooCommerce hooks or related product displays from
     * causing the wrong product to be displayed
     */
    public function restore_original_product() {
        global $post, $product;

        // Only proceed if we have stored an original product and either:
        // 1. The current product is not a valid product object, or
        // 2. The current product ID doesn't match our original product ID
        if (self::$original_product_id > 0 && self::$original_product instanceof WC_Product &&
            (!is_a($product, 'WC_Product') || $product->get_id() != self::$original_product_id)) {

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PRODUCT FIX: Restoring original product ID: " . self::$original_product_id .
                    " (was: " . ($product ? $product->get_id() : 'none') . ")");
            }

            // Restore the original product
            $post = get_post(self::$original_product_id);
            setup_postdata($post);
            $product = self::$original_product;
        }
    }

    /**
     * Fix Yoast SEO breadcrumbs to use the correct product
     *
     * @param array $links The breadcrumb links
     * @return array Modified breadcrumb links
     */
    public function fix_yoast_breadcrumbs($links) {
        if (self::$original_product_id && self::$original_product) {
            // Find and replace the last item in the breadcrumb trail
            if (!empty($links) && is_array($links)) {
                $last_index = count($links) - 1;
                if (isset($links[$last_index])) {
                    $links[$last_index]['text'] = self::$original_product->get_name();
                    $links[$last_index]['url'] = get_permalink(self::$original_product_id);

                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("BREADCRUMB FIX: Replaced product in breadcrumbs with: " . self::$original_product->get_name());
                    }
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
    public function fix_yoast_title($title) {
        if (self::$original_product_id && self::$original_product) {
            // Check if the title contains incorrect product info
            $new_title = preg_replace('/.*?Wireless Router/', self::$original_product->get_name(), $title);

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
    public function fix_document_title($title) {
        if (self::$original_product_id && self::$original_product && empty($title)) {
            $title = self::$original_product->get_name() . ' - ' . get_bloginfo('name');

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("DOCUMENT TITLE FIX: Set document title to \"{$title}\"");
            }
        }
        return $title;
    }
}