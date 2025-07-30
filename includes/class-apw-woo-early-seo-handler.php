<?php
/**
 * Early SEO Handler for APW WooCommerce Plugin
 *
 * Handles early product detection and Yoast SEO filter registration
 * using official Yoast APIs to fix SEO metadata issues for custom
 * product URL structures.
 *
 * @package APW_Woo_Plugin
 * @since 2.0.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Early_SEO_Handler Class
 *
 * Detects products from custom URL structure early in WordPress 
 * request lifecycle and uses official Yoast SEO filters to ensure
 * correct metadata generation.
 */
class APW_Woo_Early_SEO_Handler
{
    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Detected product from URL
     *
     * @var WP_Post|null
     */
    private $detected_product = null;

    /**
     * WooCommerce product object
     *
     * @var WC_Product|null
     */
    private $wc_product = null;

    /**
     * Flag to track if Yoast filters are registered
     *
     * @var bool
     */
    private $yoast_filters_registered = false;

    /**
     * Get singleton instance
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
     * Constructor - private to enforce singleton
     */
    private function __construct()
    {
        // Constructor intentionally empty - initialization happens in init()
    }

    /**
     * Initialize early SEO handling
     *
     * FIX v2.0.3: Added parse_request hook for breadcrumb fix
     * Hooks into both 'parse_request' and 'wp' actions to detect products
     * and register official Yoast SEO filters.
     */
    public function init()
    {
        // Only initialize on frontend
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // FIX v2.0.3: Add early detection in parse_request for breadcrumbs
        add_action('parse_request', array($this, 'handle_parse_request_detection'), 10);
        
        // Hook into 'wp' action early (priority 10) but after existing fixes (priority 5)
        add_action('wp', array($this, 'handle_early_seo_detection'), 10);

        // Register cleanup after page render
        add_action('wp_footer', array($this, 'cleanup_after_render'), 99);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('EARLY SEO: Handler initialized - will detect products in parse_request AND wp hooks');
        }
    }

    /**
     * Handle early SEO detection in 'wp' hook
     *
     * Detects products from custom URL structure and registers
     * official Yoast SEO filters for proper metadata generation.
     */
    public function handle_early_seo_detection()
    {
        global $wp, $post, $wp_query;
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('=== EARLY SEO v2.0.3: ENHANCED DEBUGGING START ===');
            apw_woo_log('EARLY SEO: Starting product detection from URL');
            apw_woo_log('EARLY SEO: Current URL path: ' . ($wp->request ?? 'none'));
            apw_woo_log('EARLY SEO: Current post: ' . ($post ? "{$post->post_title} (ID: {$post->ID}, slug: {$post->post_name})" : 'none'));
            apw_woo_log('EARLY SEO: WP Query flags - is_single: ' . ($wp_query->is_single ? 'true' : 'false') . ', is_product: ' . (isset($wp_query->is_product) && $wp_query->is_product ? 'true' : 'false'));
            apw_woo_log('EARLY SEO: Global product: ' . (isset($GLOBALS['product']) && $GLOBALS['product'] ? $GLOBALS['product']->get_name() . ' (ID: ' . $GLOBALS['product']->get_id() . ')' : 'none'));
        }

        // Detect product from current URL
        $this->detected_product = $this->detect_product_from_url();

        if ($this->detected_product) {
            // Get WooCommerce product object
            $this->wc_product = wc_get_product($this->detected_product->ID);
            
            if (!$this->wc_product) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("EARLY SEO ERROR: Could not create WC_Product for post ID {$this->detected_product->ID}");
                }
                return;
            }

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("EARLY SEO SUCCESS: Detected product '{$this->wc_product->get_name()}' (ID: {$this->detected_product->ID}, slug: {$this->detected_product->post_name})");
                apw_woo_log("EARLY SEO: Product image ID: " . ($this->wc_product->get_image_id() ?: 'none'));
                
                // Check if this differs from global product
                if (isset($GLOBALS['product']) && $GLOBALS['product'] && $GLOBALS['product']->get_id() !== $this->wc_product->get_id()) {
                    apw_woo_log("EARLY SEO WARNING: Detected product differs from global product! Global: {$GLOBALS['product']->get_name()} (ID: {$GLOBALS['product']->get_id()})");
                }
            }

            // Set global product data for context
            $GLOBALS['apw_detected_product'] = $this->detected_product;
            $GLOBALS['apw_detected_wc_product'] = $this->wc_product;

            // Register official Yoast SEO filters
            $this->register_yoast_filters();
            
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: No product detected from URL - skipping SEO fixes');
                apw_woo_log('=== EARLY SEO v2.0.3: ENHANCED DEBUGGING END (NO PRODUCT) ===');
            }
        }
    }

    /**
     * Detect product from current URL
     *
     * FIX v2.0.3: Enhanced detection to prevent "Cat 5 Cable" contamination
     * Parses the request URI to find products matching the
     * /products/%category%/%product% URL structure.
     *
     * @return WP_Post|null Product post object or null if not found
     */
    private function detect_product_from_url()
    {
        global $wp;

        // Get the current request path
        $request_path = $wp->request ?? '';
        
        if (empty($request_path)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: No request path found');
            }
            return null;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: Analyzing URL path: '{$request_path}'");
        }

        // Split URL into parts
        $url_parts = explode('/', trim($request_path, '/'));
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('EARLY SEO: URL parts: [' . implode(', ', $url_parts) . ']');
            apw_woo_log('EARLY SEO: URL parts count: ' . count($url_parts));
        }

        // Check if URL matches /products/ structure
        if (count($url_parts) < 2 || $url_parts[0] !== 'products') {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: URL does not match /products/ structure (need at least 2 parts starting with "products")');
            }
            return null;
        }

        // Get the last part as potential product slug
        $product_slug = end($url_parts);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: Extracted product slug from URL: '{$product_slug}'");
            
            // Enhanced debugging for accessories category issue
            if (count($url_parts) >= 3 && $url_parts[1] === 'accessories') {
                apw_woo_log("EARLY SEO: ACCESSORIES CATEGORY DETECTED - this is where the Cat 5 Cable bug occurs!");
                apw_woo_log("EARLY SEO: Full URL structure: /products/{$url_parts[1]}/{$product_slug}");
            }
        }

        // Find product by slug
        $found_product = $this->find_product_by_slug($product_slug);
        
        if (APW_WOO_DEBUG_MODE) {
            if ($found_product) {
                apw_woo_log("EARLY SEO: Successfully found product: '{$found_product->post_title}' (ID: {$found_product->ID}, slug: '{$found_product->post_name}')");
                
                // CRITICAL: Check if we found the wrong product
                if ($found_product->post_name !== $product_slug) {
                    apw_woo_log("EARLY SEO: ⚠️  WARNING: Product slug mismatch! Requested: '{$product_slug}', Found: '{$found_product->post_name}'");
                    apw_woo_log("EARLY SEO: This could be the source of the Cat 5 Cable contamination bug!");
                }
            } else {
                apw_woo_log("EARLY SEO: No product found for slug: '{$product_slug}'");
            }
        }
        
        return $found_product;
    }

    /**
     * Find product by slug
     *
     * FIX v2.0.3: Enhanced debugging to identify Cat 5 Cable contamination
     * 
     * @param string $slug Product slug
     * @return WP_Post|null Product post object or null if not found
     */
    private function find_product_by_slug($slug)
    {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: find_product_by_slug() called with slug: '{$slug}'");
        }
        
        // Use APW_Woo_Page_Detector if available for consistency
        if (class_exists('APW_Woo_Page_Detector') && method_exists('APW_Woo_Page_Detector', 'get_product_by_slug')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: Using APW_Woo_Page_Detector::get_product_by_slug()');
            }
            
            $detector_result = APW_Woo_Page_Detector::get_product_by_slug($slug);
            
            if (APW_WOO_DEBUG_MODE) {
                if ($detector_result) {
                    apw_woo_log("EARLY SEO: APW_Woo_Page_Detector returned: '{$detector_result->post_title}' (ID: {$detector_result->ID}, slug: '{$detector_result->post_name}')");
                    
                    // Check for the Cat 5 Cable contamination bug
                    if ($detector_result->post_name === 'cat-5-cable' && $slug !== 'cat-5-cable') {
                        apw_woo_log("EARLY SEO: 🚨 BUG DETECTED! APW_Woo_Page_Detector returned 'cat-5-cable' when we requested '{$slug}'");
                        apw_woo_log("EARLY SEO: This is the root cause of the accessories category contamination!");
                    }
                } else {
                    apw_woo_log('EARLY SEO: APW_Woo_Page_Detector returned null/false');
                }
            }
            
            return $detector_result;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('EARLY SEO: APW_Woo_Page_Detector not available, using direct database query');
        }

        // Fallback to direct database query
        $args = array(
            'name' => $slug,
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => 1
        );

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('EARLY SEO: Database query args: ' . json_encode($args));
        }

        $products = get_posts($args);

        if (empty($products)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("EARLY SEO: Direct database query found no products with slug: '{$slug}'");
            }
            return null;
        }

        $found_product = $products[0];
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: Direct database query found: '{$found_product->post_title}' (ID: {$found_product->ID}, slug: '{$found_product->post_name}')");
            
            // Verify we got the right product
            if ($found_product->post_name !== $slug) {
                apw_woo_log("EARLY SEO: ⚠️  SLUG MISMATCH in direct query! Requested: '{$slug}', Got: '{$found_product->post_name}'");
            }
        }

        return $found_product;
    }

    /**
     * Handle product detection in 'parse_request' hook (before breadcrumbs)
     * 
     * FIX v2.0.3: This runs BEFORE Yoast generates breadcrumbs to fix
     * the "Cat 5 Cable" contamination issue.
     *
     * @param WP $wp WordPress environment object
     */
    public function handle_parse_request_detection($wp)
    {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('=== EARLY SEO v2.0.3: PARSE_REQUEST HOOK START ===');
            apw_woo_log('PARSE_REQUEST: Starting early product detection for breadcrumbs fix');
        }

        // Detect product from current URL using WP object
        $detected_product = $this->detect_product_from_parse_request($wp);
        
        if ($detected_product) {
            // Get WooCommerce product object
            $wc_product = wc_get_product($detected_product->ID);
            
            if (!$wc_product) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("PARSE_REQUEST ERROR: Could not create WC_Product for post ID {$detected_product->ID}");
                }
                return;
            }

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("PARSE_REQUEST SUCCESS: Detected product '{$wc_product->get_name()}' (ID: {$detected_product->ID}, slug: {$detected_product->post_name})");
            }

            // FIX: Set global post data BEFORE breadcrumbs are generated
            $this->set_global_product_data($detected_product, $wc_product);
            
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('PARSE_REQUEST: No product detected - skipping global fixes');
            }
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('=== EARLY SEO v2.0.3: PARSE_REQUEST HOOK END ===');
        }
    }

    /**
     * Detect product from parse_request hook
     *
     * FIX v2.0.3: Specialized detection for parse_request hook
     * 
     * @param WP $wp WordPress environment object  
     * @return WP_Post|null Product post object or null if not found
     */
    private function detect_product_from_parse_request($wp)
    {
        // Get the current request path from WP object
        $request_path = isset($wp->request) ? $wp->request : '';
        
        if (empty($request_path)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('PARSE_REQUEST: No request path found in WP object');
            }
            return null;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PARSE_REQUEST: Analyzing URL path: '{$request_path}'");
        }

        // Split URL into parts
        $url_parts = explode('/', trim($request_path, '/'));
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('PARSE_REQUEST: URL parts: [' . implode(', ', $url_parts) . ']');
        }

        // Check if URL matches /products/ structure
        if (count($url_parts) < 2 || $url_parts[0] !== 'products') {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('PARSE_REQUEST: URL does not match /products/ structure');
            }
            return null;
        }

        // Get the last part as potential product slug
        $product_slug = end($url_parts);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("PARSE_REQUEST: Extracted product slug: '{$product_slug}'");
        }

        // Find product by slug
        $found_product = $this->find_product_by_slug($product_slug);
        
        if (APW_WOO_DEBUG_MODE) {
            if ($found_product) {
                apw_woo_log("PARSE_REQUEST: Found product: '{$found_product->post_title}' (ID: {$found_product->ID}, slug: '{$found_product->post_name}')");
            } else {
                apw_woo_log("PARSE_REQUEST: No product found for slug: '{$product_slug}'");
            }
        }
        
        return $found_product;
    }

    /**
     * Set global product data for WordPress
     * 
     * FIX v2.0.3: This fixes breadcrumbs and other WordPress functions
     * that depend on the global $post object.
     *
     * @param WP_Post $product_post The product post object
     * @param WC_Product $wc_product The WooCommerce product object
     */
    private function set_global_product_data($product_post, $wc_product)
    {
        global $post, $wp_query;
        
        if (APW_WOO_DEBUG_MODE) {
            $old_post_title = $post ? $post->post_title : 'none';
            apw_woo_log("GLOBAL FIX: Changing global post from '{$old_post_title}' to '{$product_post->post_title}'");
        }

        // Set the global post to our detected product
        $post = $product_post;
        setup_postdata($post);
        
        // Update WP_Query to reflect this product
        if (isset($wp_query)) {
            $wp_query->queried_object = $product_post;
            $wp_query->queried_object_id = $product_post->ID;
            $wp_query->is_single = true;
            $wp_query->is_singular = true;
            $wp_query->is_product = true;
        }
        
        // Set global WooCommerce product
        $GLOBALS['product'] = $wc_product;
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("GLOBAL FIX: Set global post, wp_query, and WooCommerce product to '{$product_post->post_title}'");
        }
    }

    /**
     * Register official Yoast SEO filters with detected product
     *
     * Uses official Yoast API filters as documented at developer.yoast.com
     * to ensure proper SEO metadata generation.
     */
    private function register_yoast_filters()
    {
        if ($this->yoast_filters_registered) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: Yoast filters already registered - skipping');
            }
            return;
        }

        // Check if Yoast SEO is active
        if (!class_exists('WPSEO_Options')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: Yoast SEO not detected - skipping filter registration');
            }
            return;
        }

        // Register official Yoast SEO filters using documented API
        // Priority 10 or higher as per Yoast documentation
        add_filter('wpseo_title', array($this, 'filter_yoast_title'), 10, 2);
        add_filter('wpseo_metadesc', array($this, 'filter_yoast_description'), 10, 2);
        add_filter('wpseo_opengraph_title', array($this, 'filter_yoast_og_title'), 10, 2);
        add_filter('wpseo_opengraph_desc', array($this, 'filter_yoast_og_description'), 10, 2);
        add_filter('wpseo_opengraph_url', array($this, 'filter_yoast_og_url'), 10, 2);
        add_filter('wpseo_canonical', array($this, 'filter_yoast_canonical'), 10, 2);
        add_filter('wpseo_schema_graph', array($this, 'filter_yoast_schema_graph'), 10, 2);
        
        // FIX v2.0.3: Add missing image filters for proper product images
        add_filter('wpseo_opengraph_image', array($this, 'filter_yoast_og_image'), 10, 2);
        add_filter('wpseo_opengraph_image_id', array($this, 'filter_yoast_og_image_id'), 10, 2);
        add_filter('wpseo_twitter_image', array($this, 'filter_yoast_twitter_image'), 10, 2);
        
        // FIX v2.0.3: Add breadcrumb filter to prevent "Cat 5 Cable" contamination
        add_filter('wpseo_breadcrumb_links', array($this, 'filter_yoast_breadcrumb_links'), 10, 1);

        $this->yoast_filters_registered = true;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: Registered official Yoast filters for product '{$this->wc_product->get_name()}'");
        }
    }


    /**
     * Filter Yoast title using detected product
     *
     * Official Yoast wpseo_title filter as documented at developer.yoast.com
     *
     * @param string $title Original title
     * @param object $presentation Yoast presentation object (optional)
     * @return string Modified title
     */
    public function filter_yoast_title($title, $presentation = null)
    {
        if (!$this->wc_product) {
            return $title;
        }

        $product_name = $this->wc_product->get_name();
        $site_name = get_bloginfo('name');

        // Create proper title format
        $new_title = $product_name . ' | ' . $site_name;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST TITLE FILTER: Changed '{$title}' to '{$new_title}'");
        }

        return $new_title;
    }

    /**
     * Filter Yoast meta description using detected product
     *
     * Official Yoast wpseo_metadesc filter as documented at developer.yoast.com
     *
     * @param string $description Original description
     * @param object $presentation Yoast presentation object (optional)
     * @return string Modified description
     */
    public function filter_yoast_description($description, $presentation = null)
    {
        if (!$this->wc_product) {
            return $description;
        }

        // Use product short description or excerpt
        $product_description = $this->wc_product->get_short_description();
        
        if (empty($product_description)) {
            // Fallback to product description (first 160 chars)
            $full_description = $this->wc_product->get_description();
            if (!empty($full_description)) {
                $product_description = wp_trim_words(strip_tags($full_description), 25, '...');
            }
        }

        // If we still don't have a description, create a basic one
        if (empty($product_description)) {
            $product_description = $this->wc_product->get_name() . ' available at ' . get_bloginfo('name') . '.';
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST DESCRIPTION FILTER: Set description for '{$this->wc_product->get_name()}'");
        }

        return $product_description;
    }

    /**
     * Filter Yoast OpenGraph title using detected product
     *
     * @param string $title Original OG title
     * @param object $presentation Yoast presentation object (optional)
     * @return string Modified OG title
     */
    public function filter_yoast_og_title($title, $presentation = null)
    {
        if (!$this->wc_product) {
            return $title;
        }

        $new_title = $this->wc_product->get_name();

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST OG TITLE FILTER: Set to '{$new_title}'");
        }

        return $new_title;
    }

    /**
     * Filter Yoast OpenGraph description using detected product
     *
     * @param string $description Original OG description
     * @param object $presentation Yoast presentation object (optional)
     * @return string Modified OG description
     */
    public function filter_yoast_og_description($description, $presentation = null)
    {
        // Use the same logic as meta description
        return $this->filter_yoast_description($description, $presentation);
    }

    /**
     * Filter Yoast OpenGraph URL using detected product
     *
     * @param string $url Original OG URL
     * @param object $presentation Yoast presentation object (optional)
     * @return string Modified OG URL
     */
    public function filter_yoast_og_url($url, $presentation = null)
    {
        if (!$this->detected_product) {
            return $url;
        }

        // Use the current request URL as the OG URL (the custom URL structure)
        global $wp;
        $current_url = home_url($wp->request);
        
        // Ensure it ends with a slash for consistency
        if (substr($current_url, -1) !== '/') {
            $current_url .= '/';
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST OG URL FILTER: Changed '{$url}' to '{$current_url}'");
        }

        return $current_url;
    }

    /**
     * Filter Yoast canonical URL using detected product
     *
     * @param string $canonical Original canonical URL
     * @param object $presentation Yoast presentation object (optional)  
     * @return string Modified canonical URL
     */
    public function filter_yoast_canonical($canonical, $presentation = null)
    {
        if (!$this->detected_product) {
            return $canonical;
        }

        // Use the current request URL as canonical (the custom URL structure)
        global $wp;
        $current_url = home_url($wp->request);
        
        // Ensure it ends with a slash for consistency
        if (substr($current_url, -1) !== '/') {
            $current_url .= '/';
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST CANONICAL FILTER: Set to '{$current_url}'");
        }

        return $current_url;
    }

    /**
     * Filter Yoast schema graph using detected product
     *
     * @param array $data Original schema graph data
     * @param object $presentation Yoast presentation object (optional)
     * @return array Modified schema graph data
     */
    public function filter_yoast_schema_graph($data, $presentation = null)
    {
        if (!$this->detected_product || !$this->wc_product || !is_array($data)) {
            return $data;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST SCHEMA FILTER: Processing schema graph for '{$this->wc_product->get_name()}'");
        }

        // Get the correct URLs and data for the detected product
        global $wp;
        $current_url = home_url($wp->request);
        if (substr($current_url, -1) !== '/') {
            $current_url .= '/';
        }
        
        $product_name = $this->wc_product->get_name();
        $product_description = $this->wc_product->get_short_description();
        if (empty($product_description)) {
            $full_description = $this->wc_product->get_description();
            if (!empty($full_description)) {
                $product_description = wp_trim_words(strip_tags($full_description), 25, '...');
            }
        }
        if (empty($product_description)) {
            $product_description = $product_name . ' available at ' . get_bloginfo('name') . '.';
        }

        // Process each item in the graph array
        foreach ($data as $key => &$item) {
            if (!is_array($item)) {
                continue;
            }

            // Update WebPage items
            if (isset($item['@type']) && (
                (is_array($item['@type']) && in_array('WebPage', $item['@type'])) ||
                (is_string($item['@type']) && $item['@type'] === 'WebPage')
            )) {
                // Update URLs
                if (isset($item['@id'])) {
                    $item['@id'] = $current_url;
                }
                if (isset($item['url'])) {
                    $item['url'] = $current_url;
                }
                
                // Update name/title
                if (isset($item['name'])) {
                    $item['name'] = $product_name . ' | ' . get_bloginfo('name');
                }
                
                // Update description
                if (isset($item['description'])) {
                    $item['description'] = $product_description;
                }

                // Fix primaryImageOfPage and image references
                if (isset($item['primaryImageOfPage']['@id'])) {
                    $item['primaryImageOfPage']['@id'] = $current_url . '#primaryimage';
                }
                if (isset($item['image']['@id'])) {
                    $item['image']['@id'] = $current_url . '#primaryimage';
                }
                
                // FIX v2.0.3: Fix thumbnailUrl to use correct product image
                if (isset($item['thumbnailUrl'])) {
                    $product_image_id = $this->wc_product->get_image_id();
                    if ($product_image_id) {
                        $product_image_url = wp_get_attachment_image_url($product_image_id, 'full');
                        if ($product_image_url) {
                            $item['thumbnailUrl'] = $product_image_url;
                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("YOAST SCHEMA FILTER: Fixed thumbnailUrl to use product image: {$product_image_url}");
                            }
                        }
                    }
                }
                
                // Fix breadcrumb reference
                if (isset($item['breadcrumb']['@id'])) {
                    $item['breadcrumb']['@id'] = $current_url . '#breadcrumb';
                }

                // Fix ReadAction potentialAction targets
                if (isset($item['potentialAction']) && is_array($item['potentialAction'])) {
                    foreach ($item['potentialAction'] as &$action) {
                        if (isset($action['@type']) && $action['@type'] === 'ReadAction') {
                            if (isset($action['target']) && is_array($action['target'])) {
                                // Replace all target URLs
                                for ($i = 0; $i < count($action['target']); $i++) {
                                    $action['target'][$i] = $current_url;
                                }
                            }
                        }
                    }
                }

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("YOAST SCHEMA FILTER: Updated WebPage item URLs and content");
                }
            }

            // Update BreadcrumbList items
            if (isset($item['@type']) && $item['@type'] === 'BreadcrumbList') {
                if (isset($item['itemListElement']) && is_array($item['itemListElement'])) {
                    foreach ($item['itemListElement'] as &$breadcrumb) {
                        // Update the last breadcrumb item (the product name)
                        if (isset($breadcrumb['position']) && 
                            $breadcrumb['position'] == count($item['itemListElement'])) {
                            if (isset($breadcrumb['name'])) {
                                $breadcrumb['name'] = $product_name;
                            }
                        }
                    }
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("YOAST SCHEMA FILTER: Updated breadcrumb product name to '{$product_name}'");
                    }
                }
                
                // Update the breadcrumb @id to match current URL
                if (isset($item['@id'])) {
                    $item['@id'] = $current_url . '#breadcrumb';
                }
            }

            // Update ImageObject items
            if (isset($item['@type']) && $item['@type'] === 'ImageObject') {
                if (isset($item['@id']) && strpos($item['@id'], '#primaryimage') !== false) {
                    $item['@id'] = $current_url . '#primaryimage';
                    
                    // FIX v2.0.3: Update ImageObject to use correct product image
                    $product_image_id = $this->wc_product->get_image_id();
                    if ($product_image_id) {
                        $product_image_url = wp_get_attachment_image_url($product_image_id, 'full');
                        if ($product_image_url) {
                            $item['url'] = $product_image_url;
                            $item['contentUrl'] = $product_image_url;
                            
                            // Get image metadata
                            $image_meta = wp_get_attachment_metadata($product_image_id);
                            if ($image_meta && isset($image_meta['width']) && isset($image_meta['height'])) {
                                $item['width'] = $image_meta['width'];
                                $item['height'] = $image_meta['height'];
                            }
                            
                            // Update caption to product name
                            $item['caption'] = $this->wc_product->get_name() . ' | ' . get_bloginfo('name');
                            
                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("YOAST SCHEMA FILTER: Updated ImageObject to use product image: {$product_image_url}");
                            }
                        }
                    }
                }
            }
        }

        // Universal URL replacement to fix any mismatched product/category references
        $data_json = json_encode($data);
        
        // Use regex to find and replace any /products/{category}/{product}/ URLs that don't match current URL
        if (preg_match_all('#/products/[^/]+/[^/]+/#', $data_json, $matches)) {
            $found_urls = array_unique($matches[0]);
            $current_path = rtrim(parse_url($current_url, PHP_URL_PATH), '/') . '/';
            
            foreach ($found_urls as $found_url) {
                if ($found_url !== $current_path) {
                    $data_json = str_replace($found_url, $current_path, $data_json);
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("YOAST SCHEMA FILTER: Replaced mismatched URL '{$found_url}' with correct URL '{$current_path}'");
                    }
                }
            }
            
            // Also fix fragment identifiers (e.g., #primaryimage, #breadcrumb)
            $current_base = rtrim($current_path, '/');
            $pattern = '#/products/[^/]+/[^/]+/(#\w+)#';
            if (preg_match_all($pattern, $data_json, $fragment_matches)) {
                foreach ($fragment_matches[0] as $i => $full_match) {
                    $fragment = $fragment_matches[1][$i];
                    $correct_fragment = $current_base . '/' . $fragment;
                    $data_json = str_replace($full_match, $correct_fragment, $data_json);
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("YOAST SCHEMA FILTER: Fixed fragment reference '{$full_match}' to '{$correct_fragment}'");
                    }
                }
            }
            
            $data = json_decode($data_json, true);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("YOAST SCHEMA FILTER: Applied universal URL replacement for schema consistency");
            }
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST SCHEMA FILTER: Completed schema graph processing for '{$product_name}'");
        }

        return $data;
    }

    /**
     * Filter Yoast OpenGraph image using detected product
     *
     * FIX v2.0.3: New method to handle product images properly
     *
     * @param string $image Original OG image URL
     * @param object $presentation Yoast presentation object (optional)
     * @return string Modified OG image URL
     */
    public function filter_yoast_og_image($image, $presentation = null)
    {
        if (!$this->wc_product) {
            return $image;
        }

        // Get product featured image
        $image_id = $this->wc_product->get_image_id();
        
        if (!$image_id) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("YOAST IMAGE FILTER: No featured image for product '{$this->wc_product->get_name()}', keeping original");
            }
            return $image;
        }

        // Get the full-size image URL
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        
        if (!$image_url) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("YOAST IMAGE FILTER: Could not get image URL for image ID {$image_id}");
            }
            return $image;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST IMAGE FILTER: Changed OG image from '{$image}' to '{$image_url}' for product '{$this->wc_product->get_name()}'");
        }

        return $image_url;
    }

    /**
     * Filter Yoast OpenGraph image ID using detected product
     *
     * FIX v2.0.3: New method to provide correct image ID
     *
     * @param int $image_id Original OG image ID
     * @param object $presentation Yoast presentation object (optional)
     * @return int Modified OG image ID
     */
    public function filter_yoast_og_image_id($image_id, $presentation = null)
    {
        if (!$this->wc_product) {
            return $image_id;
        }

        $product_image_id = $this->wc_product->get_image_id();
        
        if (!$product_image_id) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("YOAST IMAGE ID FILTER: No featured image ID for product '{$this->wc_product->get_name()}', keeping original");
            }
            return $image_id;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("YOAST IMAGE ID FILTER: Changed OG image ID from '{$image_id}' to '{$product_image_id}' for product '{$this->wc_product->get_name()}'");
        }

        return $product_image_id;
    }

    /**
     * Filter Yoast Twitter image using detected product
     *
     * FIX v2.0.3: New method to handle Twitter card images
     *
     * @param string $image Original Twitter image URL
     * @param object $presentation Yoast presentation object (optional)
     * @return string Modified Twitter image URL
     */
    public function filter_yoast_twitter_image($image, $presentation = null)
    {
        // Use the same logic as OpenGraph image
        return $this->filter_yoast_og_image($image, $presentation);
    }

    /**
     * Filter Yoast breadcrumb links using detected product
     *
     * FIX v2.0.3: Prevents "Cat 5 Cable" contamination in breadcrumbs
     * by ensuring the correct product name appears in the breadcrumb trail.
     *
     * @param array $links Breadcrumb links array
     * @return array Modified breadcrumb links
     */
    public function filter_yoast_breadcrumb_links($links)
    {
        if (!$this->wc_product || !is_array($links) || empty($links)) {
            return $links;
        }

        // Find and replace the last item in the breadcrumb trail (the product name)
        $last_index = count($links) - 1;
        
        if (isset($links[$last_index]) && is_array($links[$last_index])) {
            $product_name = $this->wc_product->get_name();
            $product_url = get_permalink($this->detected_product->ID);
            
            // Store original text for debugging
            $original_text = isset($links[$last_index]['text']) ? $links[$last_index]['text'] : 'undefined';
            
            // Update the last breadcrumb item with correct product info
            $links[$last_index]['text'] = $product_name;
            $links[$last_index]['url'] = $product_url;
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("BREADCRUMB FIX: Updated last breadcrumb from '{$original_text}' to '{$product_name}'");
                apw_woo_log("BREADCRUMB FIX: Updated breadcrumb URL to '{$product_url}'");
            }
        }

        return $links;
    }

    /**
     * Cleanup after page render
     *
     * Removes Yoast filters and cleans up global state to prevent
     * cross-contamination between requests.
     */
    public function cleanup_after_render()
    {
        if ($this->yoast_filters_registered) {
            // Remove official Yoast SEO filters
            remove_filter('wpseo_title', array($this, 'filter_yoast_title'), 10);
            remove_filter('wpseo_metadesc', array($this, 'filter_yoast_description'), 10);
            remove_filter('wpseo_opengraph_title', array($this, 'filter_yoast_og_title'), 10);
            remove_filter('wpseo_opengraph_desc', array($this, 'filter_yoast_og_description'), 10);
            remove_filter('wpseo_opengraph_url', array($this, 'filter_yoast_og_url'), 10);
            remove_filter('wpseo_canonical', array($this, 'filter_yoast_canonical'), 10);
            remove_filter('wpseo_schema_graph', array($this, 'filter_yoast_schema_graph'), 10);
            
            // FIX v2.0.3: Remove image filters
            remove_filter('wpseo_opengraph_image', array($this, 'filter_yoast_og_image'), 10);
            remove_filter('wpseo_opengraph_image_id', array($this, 'filter_yoast_og_image_id'), 10);
            remove_filter('wpseo_twitter_image', array($this, 'filter_yoast_twitter_image'), 10);
            
            // FIX v2.0.3: Remove breadcrumb filter
            remove_filter('wpseo_breadcrumb_links', array($this, 'filter_yoast_breadcrumb_links'), 10);

            $this->yoast_filters_registered = false;

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO CLEANUP: Removed Yoast filters after page render');
            }
        }

        // Clean up global variables
        unset($GLOBALS['apw_detected_product']);
        unset($GLOBALS['apw_detected_wc_product']);

        // Reset instance state
        $this->detected_product = null;
        $this->wc_product = null;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('EARLY SEO CLEANUP: Cleanup complete');
        }
    }
}