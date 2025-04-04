<?php
/**
 * WooCommerce Page Detection Class
 *
 * Handles detection of WooCommerce page types and URL structure parsing.
 *
 * @package APW_Woo_Plugin
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Page_Detector Class
 *
 * Detects various WooCommerce page types and custom URL structures
 */
class APW_Woo_Page_Detector
{
    /**
     * Store detected product cache
     *
     * @var array
     */
    private static $detected_products = [];

    /**
     * Detect if current page is a product page using multiple detection methods
     *
     * @param object $wp The WordPress environment object
     * @return bool True if the current page is a product page
     */
    public static function is_product_page($wp = null)
    {
        global $post;

        if (null === $wp) {
            global $wp;
        }

        // Create a debug logger closure to reduce code repetition and conditionally log
        $log_debug = function ($message) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PRODUCT DETECTION: $message");
            }
        };

        $log_debug("Starting product detection");

        if ($wp && !empty($wp->request)) {
            $log_debug("Request URL: " . $wp->request);
        }

        if ($post) {
            $log_debug("Current post: {$post->post_name} (ID: {$post->ID}, Type: " . get_post_type($post) . ")");
        } else {
            $log_debug("No current post object found");
        }

        // Method 1: Standard WooCommerce function (fastest check)
        if (is_product()) {
            $log_debug("Method 1 SUCCESS - Detected as product via is_product()");
            return true;
        }

        $log_debug("Method 1 FAILED - is_product() returned false");

        // Method 2: WordPress singular check
        $post_type = get_post_type();
        $is_singular_product = is_singular('product');

        if ($post_type === 'product' && $is_singular_product) {
            $log_debug("Method 2 SUCCESS - Detected as product via get_post_type and is_singular");
            return true;
        }

        $log_debug("Method 2 FAILED - get_post_type: " . ($post_type ?: 'none') .
            ", is_singular('product'): " . ($is_singular_product ? 'true' : 'false'));

        // Method 3: Custom URL structure detection
        if (empty($wp->request)) {
            $log_debug("Method 3 FAILED - No request URL found");
            $log_debug("All methods failed - not a product page");
            return false;
        }

        $url_parts = explode('/', trim($wp->request, '/'));
        $log_debug("Method 3 - URL parts: " . json_encode($url_parts));

        // Check if URL starts with 'products' and has at least 2 parts
        if (count($url_parts) < 2 || $url_parts[0] !== 'products') {
            $log_debug("Method 3 FAILED - URL does not match expected product URL pattern");
            $log_debug("All methods failed - not a product page");
            return false;
        }

        // Get the last part of the URL as the product slug
        $product_slug = end($url_parts);
        $log_debug("Method 3 - Trying to find product with slug: $product_slug");

        // Try to find this product - use WP cache for better performance
        $product_post = self::get_product_by_slug($product_slug);

        if (!$product_post) {
            $log_debug("Method 3 FAILED - No product found with slug: $product_slug");

            // Check if this might be a category for better error messages
            $term = get_term_by('slug', $product_slug, 'product_cat');
            if ($term) {
                $log_debug("Note - Found a category with this slug instead: {$term->name}");
            }

            $log_debug("All methods failed - not a product page");
            return false;
        }

        $log_debug("Method 3 SUCCESS - Found product by slug: $product_slug (ID: {$product_post->ID})");

        // Set up the product in the global scope
        self::setup_product_page_globals($product_post);
        $log_debug("Override main query settings to force product page");

        return true;
    }

    /**
     * Get a product by slug with caching for better performance
     *
     * @param string $slug The product slug
     * @return WP_Post|false Product post object or false if not found
     */
    public static function get_product_by_slug($slug)
    {
        // First check our internal static cache
        if (isset(self::$detected_products[$slug])) {
            return self::$detected_products[$slug];
        }

        // Check for cached product by slug in WP object cache
        $cache_key = 'apw_product_' . sanitize_key($slug);
        $cached_product = wp_cache_get($cache_key, 'apw-woo-plugin');

        if (false !== $cached_product) {
            // Store in static cache for this request
            self::$detected_products[$slug] = $cached_product;
            return $cached_product;
        }

        // Not in cache, get from database
        $args = array(
            'name' => $slug,
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => 1
        );

        $products = get_posts($args);

        if (empty($products)) {
            // Cache the negative result to avoid repeated queries
            wp_cache_set($cache_key, false, 'apw-woo-plugin', MINUTE_IN_SECONDS * 5);
            self::$detected_products[$slug] = false;
            return false;
        }

        $product_post = $products[0];

        // Cache the product for future requests
        wp_cache_set($cache_key, $product_post, 'apw-woo-plugin', HOUR_IN_SECONDS);
        self::$detected_products[$slug] = $product_post;

        return $product_post;
    }

    /**
     * Set up global variables for product page rendering
     *
     * @param WP_Post $product_post The product post object
     */
    public static function setup_product_page_globals($product_post)
    {
        global $post, $wp_query;

        // Make sure WP knows we're on this product
        $post = $product_post;
        setup_postdata($post);

        // Override the main query with more complete WooCommerce flags
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->is_product = true;
        $wp_query->is_post_type_archive = false;
        $wp_query->is_archive = false;
        $wp_query->is_tax = false;
        $wp_query->is_category = false;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $post->ID;

        // Also set in wp global for compatibility with some functions
        $GLOBALS['wp']->query_vars['product'] = $post->post_name;

        // Create the WooCommerce product object in global scope
        $GLOBALS['product'] = wc_get_product($post);

        // Set special flag that can be checked by other code
        $GLOBALS['apw_woo_is_custom_product_page'] = true;

        // Let other code know what happened
        do_action('apw_woo_after_product_page_setup', $post, $GLOBALS['product']);
    }

    /**
     * Check if current page is the main shop page
     *
     * @return bool True if on the main shop page
     */
    public static function is_main_shop_page()
    {
        return is_shop() && !is_search();
    }

    /**
     * Get the current WooCommerce page type
     *
     * @return string The page type identifier
     */
    public static function get_page_type()
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
}