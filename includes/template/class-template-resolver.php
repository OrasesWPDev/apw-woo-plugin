<?php
/**
 * Template Resolution for APW WooCommerce Plugin
 *
 * Handles resolution of which template to use for different WooCommerce page types.
 *
 * @package APW_Woo_Plugin
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Template_Resolver Class
 *
 * Resolves which template to use for different WooCommerce page types.
 */
class APW_Woo_Template_Resolver
{
    /**
     * Template path constants
     */
    private const TEMPLATE_DIRECTORY = 'templates/';
    private const WOOCOMMERCE_DIRECTORY = 'woocommerce/';
    private const SHOP_TEMPLATE = 'woocommerce/partials/shop-categories-display.php';
    private const CATEGORY_TEMPLATE = 'woocommerce/partials/category-products-display.php';
    private const PRODUCT_TEMPLATE = 'woocommerce/single-product.php';
    private const CART_TEMPLATE = 'woocommerce/cart/cart.php';
    private const CHECKOUT_TEMPLATE = 'woocommerce/checkout/form-checkout.php';
    private const MY_ACCOUNT_TEMPLATE = 'woocommerce/myaccount/my-account.php';

    /**
     * Template directory path
     *
     * @var string
     */
    private $template_path;

    /**
     * Template loader instance
     *
     * @var APW_Woo_Template_Loader
     */
    private $template_loader;

    /**
     * Constructor
     *
     * @param APW_Woo_Template_Loader $template_loader The template loader instance
     */
    public function __construct($template_loader)
    {
        $this->template_path = APW_WOO_PLUGIN_DIR . self::TEMPLATE_DIRECTORY;
        $this->template_loader = $template_loader;
    }

    /**
     * Find the appropriate template based on current page type
     *
     * @param string $default_template The default template path from WordPress
     * @return string The resolved template path
     */
    public function resolve_template($default_template)
    {
        // Quick exit for resource files (images, fonts, etc)
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('/\.(css|js|woff2?|ttf|svg|png|jpe?g|gif|ico)(\?.*)?$/i', $request_uri)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("SKIPPING TEMPLATE OVERRIDE: Resource file detected: {$request_uri}");
            }
            return $default_template;
        }

        global $wp, $wp_query;

        // Enhanced debugging information
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("TEMPLATE DEBUG: Current URL: {$request_uri}");
            apw_woo_log("TEMPLATE DEBUG: WP Request: " . $wp->request);
        }

        // Step 1: Check for product with custom URL structure
        $product_template = $this->maybe_get_product_template($wp);
        if ($product_template) {
            return $product_template;
        }

        // Step 2: Enhanced category detection for /products/{category}/ URL
        $url_parts = explode('/', trim($wp->request, '/'));
        if (count($url_parts) >= 2 && $url_parts[0] === 'products') {
            $category_slug = $url_parts[1];

            // Skip if this looks like a product URL with deeper nesting
            if (count($url_parts) == 2 || (count($url_parts) > 2 && !$this->find_product_for_template(end($url_parts)))) {
                // Attempt to find the category
                $category = get_term_by('slug', $category_slug, 'product_cat');

                if ($category && !is_wp_error($category)) {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("TEMPLATE OVERRIDE: Detected category: {$category->name} at URL containing /products/{$category_slug}/");
                    }

                    // Setup proper query vars for category
                    $wp_query->set('product_cat', $category_slug);
                    $wp_query->set('term', $category_slug);
                    $wp_query->set('term_id', $category->term_id);
                    $wp_query->is_tax = true;
                    $wp_query->is_archive = true;
                    $wp_query->queried_object = $category;
                    $wp_query->queried_object_id = $category->term_id;

                    // Load category template
                    $category_template = $this->template_path . self::CATEGORY_TEMPLATE;
                    if (file_exists($category_template)) {
                        if (APW_WOO_DEBUG_MODE) {
                            apw_woo_log("TEMPLATE OVERRIDE: Loading category template: {$category_template}");
                        }
                        return $category_template;
                    }
                }
            }
        }

        // Step 3: Improved shop page detection
        if (count($url_parts) === 1 && $url_parts[0] === 'products') {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Detected main shop page at /products/");
            }

            // Mark as shop page
            $wp_query->is_post_type_archive = true;
            $wp_query->is_archive = true;
            $wp_query->is_shop = true;

            // Load shop template
            $shop_template = $this->template_path . self::SHOP_TEMPLATE;
            if (file_exists($shop_template)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Loading shop template: {$shop_template}");
                }
                return $shop_template;
            }
        }

        // Step 4: Check standard WooCommerce pages
        $page_templates = [
            'category' => [
                'condition' => 'is_product_category',
                'template' => self::CATEGORY_TEMPLATE,
                'description' => 'product category'
            ],
            'shop' => [
                'condition' => [APW_Woo_Page_Detector::class, 'is_main_shop_page'],
                'template' => self::SHOP_TEMPLATE,
                'description' => 'main shop'
            ],
            'cart' => [
                'condition' => 'is_cart',
                'template' => self::CART_TEMPLATE,
                'description' => 'cart'
            ],
            'checkout' => [
                'condition' => 'is_checkout',
                'template' => self::CHECKOUT_TEMPLATE,
                'description' => 'checkout'
            ],
            'account' => [
                'condition' => 'is_account_page',
                'template' => self::MY_ACCOUNT_TEMPLATE,
                'description' => 'account'
            ]
        ];

        // Check each page type
        foreach ($page_templates as $type => $settings) {
            $condition = $settings['condition'];
            $condition_met = is_callable($condition) ? call_user_func($condition) : call_user_func($condition);

            if ($condition_met) {
                $custom_template = $this->get_template_for_page_type(
                    $settings['template'],
                    $settings['description']
                );

                if ($custom_template) {
                    return $custom_template;
                }
            }
        }

        // Step 5: Handle special case for /products/* URLs that weren't caught above
        if (!empty($url_parts) && $url_parts[0] === 'products') {
            // At this point, we've already checked for product and category pages
            // This is a fallback to ensure we at least show the shop page
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Fallback for URL with /products/ prefix: {$wp->request}");
            }

            // Try to load shop template as fallback
            $shop_template = $this->template_path . self::SHOP_TEMPLATE;
            if (file_exists($shop_template)) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("TEMPLATE OVERRIDE: Loading shop template as fallback: {$shop_template}");
                }
                return $shop_template;
            }
        }

        // If we've made it here, no custom template was found
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('No custom template found, using default: ' . $default_template);
        }

        return $default_template;
    }

    /**
     * Log debug information for template overrides
     */
    private function log_template_override_debug_info()
    {
        if (!APW_WOO_DEBUG_MODE) {
            return;
        }

        global $wp;

        apw_woo_log("TEMPLATE OVERRIDE: Checking URL: " . $wp->request);
        apw_woo_log("TEMPLATE OVERRIDE: is_shop(): " . (is_shop() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE: is_product_category(): " . (is_product_category() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE: is_product(): " . (is_product() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE: is_cart(): " . (is_cart() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE: is_checkout(): " . (is_checkout() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE: is_account_page(): " . (is_account_page() ? 'true' : 'false'));
    }

    /**
     * Check for product with custom URL structure and return its template
     *
     * @param object $wp WordPress environment object
     * @return string|false Template path or false if not a product
     */
    private function maybe_get_product_template($wp)
    {
        // Special handling for /products/%product_cat%/ permalink structure
        $url_parts = explode('/', trim($wp->request, '/'));

        // Log more detailed URL information in debug mode
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PRODUCT DETECTION: Analyzing URL parts: " . implode(', ', $url_parts));
        }

        if (count($url_parts) < 2 || $url_parts[0] !== 'products') {
            return false;
        }

        // Get the product slug (last part of URL)
        $product_slug = end($url_parts);
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("TEMPLATE OVERRIDE: Checking for product with slug: " . $product_slug);
        }

        // Try to find a product with this slug
        $product_post = $this->find_product_for_template($product_slug);

        // FIX: Clear any potentially incorrect/cached global product reference
        unset($GLOBALS['product']);
        global $wp_query;
        if (isset($wp_query->queried_object)) {
            $wp_query->queried_object = null;
            $wp_query->queried_object_id = 0;
        }

        if (!$product_post) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: No product found with slug: " . $product_slug);
            }
            return false;
        }

        // Set a global flag that we can check elsewhere
        $GLOBALS['apw_is_custom_product_url'] = true;

        // Setup product globals - we've found a product that matches the URL
        $this->setup_product_for_template($product_post, $wp->request);

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
            return false;
        }
    }

    /**
     * Find product post by slug with caching
     *
     * @param string $product_slug The product slug
     * @return WP_Post|false Product post or false if not found
     */
    private function find_product_for_template($product_slug)
    {
        static $product_cache = [];

        // Check static cache first
        if (isset($product_cache[$product_slug])) {
            return $product_cache[$product_slug];
        }

        // Use our page detector's method to find products by slug
        $product_post = APW_Woo_Page_Detector::get_product_by_slug($product_slug);

        // Store in static cache for future lookups
        $product_cache[$product_slug] = $product_post;

        return $product_post;
    }

    /**
     * Setup global variables for a product page
     *
     * @param WP_Post $product_post The product post object
     * @param string $request_url The current request URL (for logging)
     */
    private function setup_product_for_template($product_post, $request_url)
    {
        global $post;
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("TEMPLATE OVERRIDE: Found product '" . $product_post->post_title . "' (ID: " . $product_post->ID . ") at URL: " . $request_url);
        }

        // Store the original product ID and object to ensure we can restore it
        APW_Woo_Template_Loader::set_original_product(
            $product_post->ID,
            wc_get_product($product_post->ID)
        );

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PRODUCT DEBUG: Stored original product: " . $product_post->post_title . " (ID: " . $product_post->ID . ")");
        }

        // Add early filters for title to ensure they use the correct product
        add_filter('pre_get_document_title', function ($title) use ($product_post) {
            $site_name = get_bloginfo('name');
            $product_title = $product_post->post_title;
            $new_title = $product_title . ' - ' . $site_name;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("EARLY TITLE FIX: Set document title to \"" . $new_title . "\"");
            }
            return $new_title;
        }, 0);  // Priority 0 to run before other filters

        // Force the WP query object to use our product
        global $wp_query;
        $wp_query->queried_object = $product_post;
        $wp_query->queried_object_id = $product_post->ID;

        // Setup the global environment for this product
        APW_Woo_Page_Detector::setup_product_page_globals($product_post);

        // Register hooks to restore the original product during template rendering
        $this->template_loader->register_product_restoration_hooks();

        // Add hooks to fix Yoast SEO breadcrumbs and page title
        add_filter('wpseo_breadcrumb_links', array($this->template_loader, 'fix_yoast_breadcrumbs'), 5);
        add_filter('wpseo_title', array($this->template_loader, 'fix_yoast_title'), 5);
        add_filter('pre_get_document_title', array($this->template_loader, 'fix_document_title'), 5);
    }

    /**
     * Get template for a specific page type
     *
     * @param string $template_path Relative template path
     * @param string $page_description Description of the page type for logging
     * @return string|false Template path or false if not found
     */
    private function get_template_for_page_type($template_path, $page_description)
    {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("TEMPLATE OVERRIDE: Detected {$page_description} page");
        }

        $custom_template = $this->template_path . $template_path;

        if (file_exists($custom_template)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("TEMPLATE OVERRIDE: Loading {$page_description} template: " . $custom_template);
            }
            return $custom_template;
        }

        return false;
    }
}