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
     * Hooks into the 'wp' action to detect products and register
     * official Yoast SEO filters.
     */
    public function init()
    {
        // Only initialize on frontend
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // Hook into 'wp' action early (priority 10) but after existing fixes (priority 5)
        add_action('wp', array($this, 'handle_early_seo_detection'), 10);

        // Register cleanup after page render
        add_action('wp_footer', array($this, 'cleanup_after_render'), 99);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('EARLY SEO: Handler initialized - will detect products in wp hook');
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
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('EARLY SEO: Starting product detection from URL');
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
                apw_woo_log("EARLY SEO: Detected product '{$this->wc_product->get_name()}' (ID: {$this->detected_product->ID})");
            }

            // Set global product data for context
            $GLOBALS['apw_detected_product'] = $this->detected_product;
            $GLOBALS['apw_detected_wc_product'] = $this->wc_product;

            // Register official Yoast SEO filters
            $this->register_yoast_filters();
            
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: No product detected from URL - skipping SEO fixes');
            }
        }
    }

    /**
     * Detect product from current URL
     *
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
            return null;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: Analyzing URL path: {$request_path}");
        }

        // Split URL into parts
        $url_parts = explode('/', trim($request_path, '/'));

        // Check if URL matches /products/ structure
        if (count($url_parts) < 2 || $url_parts[0] !== 'products') {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('EARLY SEO: URL does not match /products/ structure');
            }
            return null;
        }

        // Get the last part as potential product slug
        $product_slug = end($url_parts);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: Checking for product with slug: {$product_slug}");
        }

        // Find product by slug
        return $this->find_product_by_slug($product_slug);
    }

    /**
     * Find product by slug
     *
     * @param string $slug Product slug
     * @return WP_Post|null Product post object or null if not found
     */
    private function find_product_by_slug($slug)
    {
        // Use APW_Woo_Page_Detector if available for consistency
        if (class_exists('APW_Woo_Page_Detector') && method_exists('APW_Woo_Page_Detector', 'get_product_by_slug')) {
            return APW_Woo_Page_Detector::get_product_by_slug($slug);
        }

        // Fallback to direct database query
        $args = array(
            'name' => $slug,
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => 1
        );

        $products = get_posts($args);

        if (empty($products)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("EARLY SEO: No product found with slug: {$slug}");
            }
            return null;
        }

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EARLY SEO: Found product: {$products[0]->post_title} (ID: {$products[0]->ID})");
        }

        return $products[0];
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