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
    private $template_path; // Fixed: Removed type hint for compatibility if needed

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
     * Find the appropriate template based on current page type, setting up context where needed.
     *
     * @param string $default_template The default template path from WordPress.
     * @return string The resolved template path.
     */
    public function resolve_template($default_template)
    {
        // --- Early Exit for Static Resources ---
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        // Fixed Regex - Ensure dot is escaped and query string part is correct
        if (preg_match('/\\.(css|js|woff2?|ttf|svg|png|jpe?g|gif|ico)(\\?.*)?$/i', $request_uri)) {
            // No need to log skipped resources unless debugging specific asset issues
            // if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            //     apw_woo_log("RESOLVER: Skipping template override for resource file: {$request_uri}");
            // }
            return $default_template;
        }

        global $wp, $wp_query;

        // --- Debugging Setup ---
        $apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
        $apw_log_exists = function_exists('apw_woo_log');

        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log("RESOLVER START: Attempting to resolve template. Default: " . basename($default_template));
            apw_woo_log("RESOLVER CONTEXT: Current URL: {$request_uri}");
            apw_woo_log("RESOLVER CONTEXT: WP Request: " . ($wp->request ?? 'N/A'));
            apw_woo_log("RESOLVER CONTEXT: is_cart(): " . (function_exists('is_cart') && is_cart() ? 'true' : 'false'));
            apw_woo_log("RESOLVER CONTEXT: is_checkout(): " . (function_exists('is_checkout') && is_checkout() ? 'true' : 'false'));
            apw_woo_log("RESOLVER CONTEXT: is_account_page(): " . (function_exists('is_account_page') && is_account_page() ? 'true' : 'false'));
            apw_woo_log("RESOLVER CONTEXT: is_shop(): " . (function_exists('is_shop') && is_shop() ? 'true' : 'false'));
            apw_woo_log("RESOLVER CONTEXT: is_product_category(): " . (function_exists('is_product_category') && is_product_category() ? 'true' : 'false'));
            apw_woo_log("RESOLVER CONTEXT: is_product(): " . (function_exists('is_product') && is_product() ? 'true' : 'false'));
        }

        // --- Step 1: Check for Product with Custom URL Structure ---
        // maybe_get_product_template() handles its own context setup via setup_product_for_template()
        $product_template = $this->maybe_get_product_template($wp);
        if ($product_template) {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("RESOLVER RESULT: Using custom product template (via maybe_get_product_template): " . basename($product_template));
            }
            return $product_template;
        }

        // --- Step 2: Check for Category with Custom URL Structure (/products/{category}/) ---
        $url_parts = explode('/', trim($wp->request ?? '', '/'));
        if (count($url_parts) >= 2 && $url_parts[0] === 'products') {
            $category_slug = $url_parts[1];
            // Ensure this isn't actually a product URL before checking for category
            if (count($url_parts) == 2 || (count($url_parts) > 2 && !$this->find_product_for_template(end($url_parts)))) {
                $category = get_term_by('slug', $category_slug, 'product_cat');
                if ($category && !is_wp_error($category)) {
                    // Category found - Setup necessary query vars for category archive
                    if ($apw_debug_mode && $apw_log_exists) {
                        apw_woo_log("RESOLVER: Detected custom category URL: /products/{$category_slug}/. Setting up query vars.");
                    }
                    $wp_query->set('product_cat', $category_slug);
                    $wp_query->set('term', $category_slug); // Often redundant but safe
                    $wp_query->set('term_id', $category->term_id);
                    $wp_query->is_tax = true; // Set taxonomy flag
                    $wp_query->is_archive = true; // Set archive flag
                    $wp_query->queried_object = $category;
                    $wp_query->queried_object_id = $category->term_id;
                    // Prevent it being misinterpreted as other page types
                    $wp_query->is_page = false;
                    $wp_query->is_single = false;
                    $wp_query->is_singular = false;
                    $wp_query->is_post_type_archive = false;
                    $wp_query->is_shop = false; // Explicitly false

                    // Attempt to load the custom category template
                    $category_template = $this->template_path . self::CATEGORY_TEMPLATE;
                    if (file_exists($category_template)) {
                        if ($apw_debug_mode && $apw_log_exists) {
                            apw_woo_log("RESOLVER RESULT: Using custom category template (via custom URL): " . basename($category_template));
                        }
                        return $category_template;
                    }
                }
            }
        }

        // --- Step 3: Check for Shop Page with Custom URL Structure (/products/) ---
        if (count($url_parts) === 1 && $url_parts[0] === 'products') {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("RESOLVER: Detected custom shop URL: /products/. Setting up query vars.");
            }
            // Mark as shop page - WordPress needs these flags set correctly
            $wp_query->is_shop = true;
            $wp_query->is_post_type_archive = true;
            $wp_query->is_archive = true;
            // Prevent misinterpretation
            $wp_query->is_page = false;
            $wp_query->is_singular = false;
            $wp_query->is_tax = false;

            // Attempt to load the custom shop template
            $shop_template = $this->template_path . self::SHOP_TEMPLATE;
            if (file_exists($shop_template)) {
                if ($apw_debug_mode && $apw_log_exists) {
                    apw_woo_log("RESOLVER RESULT: Using custom shop template (via custom URL): " . basename($shop_template));
                }
                return $shop_template;
            }
        }

        // --- Step 4: Check Standard WooCommerce Pages (Cart, Checkout, Account, Shop, Category) ---
        $page_templates = [
            // Check specific pages first, as they might also trigger is_archive etc.
            'cart' => [
                'condition' => 'is_cart', // WooCommerce conditional
                'template' => self::CART_TEMPLATE,
                'page_type' => 'cart', // For context setup
                'description' => 'cart'
            ],
            'checkout' => [
                'condition' => 'is_checkout', // WooCommerce conditional
                'template' => self::CHECKOUT_TEMPLATE,
                'page_type' => 'checkout', // For context setup
                'description' => 'checkout'
            ],
            'account' => [
                'condition' => 'is_account_page', // WooCommerce conditional
                'template' => self::MY_ACCOUNT_TEMPLATE,
                'page_type' => 'myaccount', // For context setup ('myaccount' is key for wc_get_page_id)
                'description' => 'account'
            ],
            // Now check archives (order matters if URLs overlap, e.g., /shop/category/)
            'category' => [
                'condition' => 'is_product_category', // WooCommerce conditional
                'template' => self::CATEGORY_TEMPLATE,
                'page_type' => null, // Context already set by WC core for standard category URLs
                'description' => 'product category (standard URL)'
            ],
            'shop' => [
                // Use our detector for main shop page to avoid matching category/tag archives etc. if shop base is used
                'condition' => [APW_Woo_Page_Detector::class, 'is_main_shop_page'],
                'template' => self::SHOP_TEMPLATE,
                'page_type' => 'shop', // For context setup if needed (usually WC core handles this)
                'description' => 'main shop (standard URL)'
            ]
            // Note: is_product() case is handled by Step 1 (maybe_get_product_template)
        ];

        foreach ($page_templates as $type => $settings) {
            $condition = $settings['condition'];
            $condition_callable = is_callable($condition) ? $condition : null;

            // Fallback for simple function names as strings
            if ($condition_callable === null && is_string($condition) && function_exists($condition)) {
                $condition_callable = $condition;
            }

            // Check if condition exists and is met
            if ($condition_callable && call_user_func($condition_callable)) {
                if ($apw_debug_mode && $apw_log_exists) {
                    apw_woo_log("RESOLVER: Condition met for '{$settings['description']}'. Checking for custom template.");
                }

                // --- Context Setup for Standard Pages (Cart, Checkout, Account) ---
                if (in_array($type, ['cart', 'checkout', 'account'])) {
                    $page_id = 0;
                    if (function_exists('wc_get_page_id') && $settings['page_type']) {
                        $page_id = wc_get_page_id($settings['page_type']);
                    }

                    if ($page_id > 0 && method_exists($this, 'setup_standard_page_context')) {
                        $context_setup_success = $this->setup_standard_page_context($page_id);
                        if (!$context_setup_success && $apw_debug_mode && $apw_log_exists) {
                            apw_woo_log('RESOLVER WARNING: Failed to set up context for ' . $type . ' page (ID: ' . $page_id . '). Header block might not render correctly.', 'warning');
                        }
                    } elseif ($apw_debug_mode && $apw_log_exists) {
                        apw_woo_log('RESOLVER WARNING: Could not get page ID or setup method missing for ' . $type . '. Context not set.', 'warning');
                    }
                }
                // --- End Context Setup ---

                // Attempt to find and return the custom template file for this condition
                $custom_template = $this->get_template_for_page_type(
                    $settings['template'],
                    $settings['description']
                );

                if ($custom_template) {
                    if ($apw_debug_mode && $apw_log_exists) {
                        apw_woo_log("RESOLVER RESULT: Using custom template (via standard condition '{$settings['description']}'): " . basename($custom_template));
                    }
                    // We found our custom template, return it
                    return $custom_template;
                } else {
                    // If our specific custom template *file* for this condition doesn't exist,
                    // stop checking our plugin templates for this request and let WordPress handle it.
                    if ($apw_debug_mode && $apw_log_exists) {
                        apw_woo_log('RESOLVER NOTE: Custom template file specified for ' . $settings['description'] . ' (' . basename($settings['template']) . ') not found. Falling back to default WP/Theme template hierarchy.');
                    }
                    return $default_template; // Exit our logic and return the default
                }
            } // End if condition met
        } // End foreach loop

        // --- Step 5: Fallback for Unmatched /products/* URLs ---
        // This should ideally not be hit if Steps 1, 2, 3 cover all cases, but acts as a safety net.
        if (!empty($url_parts) && $url_parts[0] === 'products') {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("RESOLVER FALLBACK: URL starts with /products/ but didn't match product, category, or shop custom rules: {$wp->request}. Attempting to load shop template as fallback.");
            }
            // Try to load shop template as a last resort for /products/ structure
            $shop_template = $this->template_path . self::SHOP_TEMPLATE;
            if (file_exists($shop_template)) {
                if ($apw_debug_mode && $apw_log_exists) {
                    apw_woo_log("RESOLVER RESULT: Using custom shop template (via /products/ fallback): " . basename($shop_template));
                }
                return $shop_template;
            }
        }

        // --- Final Fallback ---
        // If we've reached here, none of our plugin's conditions were met or custom templates found/intended.
        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log("RESOLVER RESULT: No custom template override applied. Using default template: " . basename($default_template));
        }
        return $default_template;
    }

    /**
     * Setup global variables for a standard WordPress page context.
     * (This is the helper method added in the previous step - ensure it exists in the class)
     *
     * @param int $page_id The ID of the page to set up context for.
     * @return bool True on success, false on failure.
     */
    private function setup_standard_page_context($page_id)
    {
        global $post, $wp_query;
        $apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
        $apw_log_exists = function_exists('apw_woo_log');


        if (empty($page_id) || !is_numeric($page_id)) {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log('RESOLVER SETUP ERROR: Invalid Page ID provided for context setup: ' . print_r($page_id, true), 'error');
            }
            return false;
        }

        $page_post = get_post($page_id);

        if (!$page_post instanceof WP_Post) {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log('RESOLVER SETUP ERROR: Could not get post object for Page ID: ' . $page_id, 'error');
            }
            // Don't reset query here as it might interfere with the original request processing if we return false
            return false;
        }

        // Set the global post object
        $post = $page_post;
        setup_postdata($post); // Make template tags like the_title() work based on this $post

        // Backup original query vars before potentially modifying them
        // $original_query_vars = $wp_query->query_vars; // Consider if needed for complex scenarios

        // --- Reset flags to ensure clean slate before setting page context ---
        $wp_query->is_single = false;
        $wp_query->is_preview = false;
        $wp_query->is_page = false; // Will be set true below
        $wp_query->is_archive = false;
        $wp_query->is_date = false;
        $wp_query->is_year = false;
        $wp_query->is_month = false;
        $wp_query->is_day = false;
        $wp_query->is_time = false;
        $wp_query->is_author = false;
        $wp_query->is_category = false;
        $wp_query->is_tag = false;
        $wp_query->is_tax = false;
        $wp_query->is_search = false;
        $wp_query->is_feed = false;
        $wp_query->is_comment_feed = false;
        $wp_query->is_trackback = false;
        $wp_query->is_home = false;
        $wp_query->is_privacy_policy = false;
        $wp_query->is_404 = false;
        $wp_query->is_embed = false;
        $wp_query->is_paged = false;
        $wp_query->is_admin = false;
        $wp_query->is_attachment = false;
        $wp_query->is_singular = false; // Will be set true below
        $wp_query->is_robots = false;
        $wp_query->is_favicon = false;
        $wp_query->is_post_type_archive = false;
        $wp_query->is_front_page = ($post->ID == get_option('page_on_front'));

        // --- Set flags specific to a standard page ---
        $wp_query->is_page = true;
        $wp_query->is_singular = true; // Pages are singular

        // Set the queried object correctly
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $post->ID;

        // Ensure relevant query vars are present (though flags are often more critical for templates)
        $wp_query->set('page_id', $post->ID);
        // Remove potentially conflicting query vars if they exist from previous steps
        $wp_query->set('product', null);
        $wp_query->set('product_cat', null);
        $wp_query->set('pagename', $post->post_name); // Set pagename


        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('RESOLVER SETUP: Successfully set up context for Page ID: ' . $page_id . ' (' . $post->post_title . ')');
            apw_woo_log('RESOLVER SETUP: WP_Query flags - is_page: ' . ($wp_query->is_page ? 'true' : 'false') . ', is_singular: ' . ($wp_query->is_singular ? 'true' : 'false'));
        }

        // Reset post data just in case setup_postdata caused issues elsewhere, although usually fine
        // wp_reset_postdata(); // Typically not needed here as the main query loop hasn't run yet

        return true;
    }

    // Ensure the get_template_for_page_type method exists as well
    // ** FIX: Removed the duplicate definition that caused the error **
    // private function get_template_for_page_type(...) { ... } // REMOVED THIS DUPLICATE

    /**
     * Log debug information for template overrides
     */
    private function log_template_override_debug_info()
    {
        // Use class properties/constants for debug mode check
        $apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
        if (!$apw_debug_mode || !function_exists('apw_woo_log')) {
            return;
        }

        global $wp;
        apw_woo_log("TEMPLATE OVERRIDE DEBUG: Checking URL: " . ($wp->request ?? 'N/A'));
        apw_woo_log("TEMPLATE OVERRIDE DEBUG: is_shop(): " . (function_exists('is_shop') && is_shop() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE DEBUG: is_product_category(): " . (function_exists('is_product_category') && is_product_category() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE DEBUG: is_product(): " . (function_exists('is_product') && is_product() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE DEBUG: is_cart(): " . (function_exists('is_cart') && is_cart() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE DEBUG: is_checkout(): " . (function_exists('is_checkout') && is_checkout() ? 'true' : 'false'));
        apw_woo_log("TEMPLATE OVERRIDE DEBUG: is_account_page(): " . (function_exists('is_account_page') && is_account_page() ? 'true' : 'false'));
    }


    /**
     * Check for product with custom URL structure and return its template
     *
     * @param object $wp WordPress environment object.
     * @return string|false Template path or false if not a product.
     */
    private function maybe_get_product_template($wp)
    {
        // Use class properties/constants for debug mode check
        $apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
        $apw_log_exists = function_exists('apw_woo_log');

        // Special handling for /products/%product_cat%/ permalink structure
        $url_parts = explode('/', trim($wp->request ?? '', '/'));

        // Log more detailed URL information in debug mode
        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log("PRODUCT DETECTION (maybe_get_product_template): Analyzing URL parts: " . implode(', ', $url_parts));
        }

        if (count($url_parts) < 2 || $url_parts[0] !== 'products') {
            return false;
        }

        // Get the product slug (last part of URL)
        $product_slug = end($url_parts);
        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log("PRODUCT DETECTION (maybe_get_product_template): Checking for product with slug: " . $product_slug);
        }

        // Try to find a product with this slug
        $product_post = $this->find_product_for_template($product_slug);

        // FIX: Clear any potentially incorrect/cached global product reference
        // unset($GLOBALS['product']); // This might be too aggressive, let setup_product_for_template handle it.
        global $wp_query;
        // Resetting queried_object here might be premature if find_product_for_template fails
        // if (isset($wp_query->queried_object)) {
        //     $wp_query->queried_object = null;
        //     $wp_query->queried_object_id = 0;
        // }

        if (!$product_post) {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("PRODUCT DETECTION (maybe_get_product_template): No product found with slug: " . $product_slug);
            }
            return false;
        }

        // Set a global flag that we can check elsewhere
        $GLOBALS['apw_is_custom_product_url'] = true;

        // Setup product globals - we've found a product that matches the URL
        $this->setup_product_for_template($product_post, $wp->request ?? '');

        // Load the single product template
        $custom_template = $this->template_path . self::PRODUCT_TEMPLATE;
        if (file_exists($custom_template)) {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("PRODUCT DETECTION (maybe_get_product_template): Loading single product template: " . $custom_template);
            }
            return $custom_template;
        } else {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("PRODUCT DETECTION ERROR (maybe_get_product_template): Could not find product template at: " . $custom_template, 'error');
            }
            return false;
        }
    }

    /**
     * Find product post by slug with caching
     *
     * @param string $product_slug The product slug.
     * @return WP_Post|false Product post or false if not found.
     */
    private function find_product_for_template($product_slug)
    {
        static $product_cache = [];

        // Check static cache first
        if (isset($product_cache[$product_slug])) {
            return $product_cache[$product_slug];
        }

        // Use our page detector's method to find products by slug
        $product_post = false; // Default to false
        if (class_exists('APW_Woo_Page_Detector') && method_exists('APW_Woo_Page_Detector', 'get_product_by_slug')) {
            $product_post = APW_Woo_Page_Detector::get_product_by_slug($product_slug);
        } elseif (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('RESOLVER ERROR: APW_Woo_Page_Detector class or get_product_by_slug method not found.', 'error');
        }


        // Store in static cache for future lookups (even if false)
        $product_cache[$product_slug] = $product_post;

        return $product_post;
    }


    /**
     * Setup global variables for a product page identified by custom URL.
     *
     * @param WP_Post $product_post The product post object.
     * @param string $request_url The current request URL (for logging).
     */
    private function setup_product_for_template($product_post, $request_url)
    {
        // Use class properties/constants for debug mode check
        $apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
        $apw_log_exists = function_exists('apw_woo_log');

        global $post; // Ensure global $post is accessible
        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log("PRODUCT SETUP: Setting up globals for product '" . $product_post->post_title . "' (ID: " . $product_post->ID . ") from URL: " . $request_url);
        }

        // Store the original product ID and object to ensure we can restore it
        if (class_exists('APW_Woo_Template_Loader') && method_exists('APW_Woo_Template_Loader', 'set_original_product')) {
            APW_Woo_Template_Loader::set_original_product(
                $product_post->ID,
                wc_get_product($product_post->ID)
            );
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("PRODUCT SETUP: Stored original product: " . $product_post->post_title . " (ID: " . $product_post->ID . ")");
            }
        } elseif ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('PRODUCT SETUP WARNING: APW_Woo_Template_Loader class or set_original_product method not found.', 'warning');
        }


        // Add early filters for title to ensure they use the correct product
        add_filter('pre_get_document_title', function ($title) use ($product_post) {
            $site_name = get_bloginfo('name');
            $product_title = $product_post->post_title;
            $new_title = $product_title . ' - ' . $site_name;
            // Only log if debug mode is on
            if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log("EARLY TITLE FIX: Set document title to \"" . $new_title . "\"");
            }
            return $new_title;
        }, 0);  // Priority 0 to run before other filters

        // Force the WP query object to use our product
        global $wp_query;
        // Reset potentially conflicting flags first
        $wp_query->is_page = false;
        $wp_query->is_archive = false;
        $wp_query->is_tax = false;
        $wp_query->is_post_type_archive = false;

        // Set product specific flags (redundant if setup_product_page_globals works, but safe)
        $wp_query->is_single = true;
        $wp_query->is_singular = true;
        $wp_query->is_product = true; // Specific WooCommerce flag

        $wp_query->queried_object = $product_post;
        $wp_query->queried_object_id = $product_post->ID;


        // Setup the global environment for this product using Page Detector method
        if (class_exists('APW_Woo_Page_Detector') && method_exists('APW_Woo_Page_Detector', 'setup_product_page_globals')) {
            APW_Woo_Page_Detector::setup_product_page_globals($product_post);
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log('PRODUCT SETUP: Called APW_Woo_Page_Detector::setup_product_page_globals()');
            }
        } elseif ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('PRODUCT SETUP WARNING: APW_Woo_Page_Detector class or setup_product_page_globals method not found.', 'warning');
        }


        // Register hooks to restore the original product during template rendering
        if (method_exists($this->template_loader, 'register_product_restoration_hooks')) {
            $this->template_loader->register_product_restoration_hooks();
        } elseif ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('PRODUCT SETUP WARNING: template_loader->register_product_restoration_hooks method not found.', 'warning');
        }

        // Add hooks to fix Yoast SEO breadcrumbs and page title
        // Ensure template_loader object exists before adding filters that depend on it
        if (isset($this->template_loader) && is_object($this->template_loader)) {
            add_filter('wpseo_breadcrumb_links', array($this->template_loader, 'fix_yoast_breadcrumbs'), 5);
            add_filter('wpseo_title', array($this->template_loader, 'fix_yoast_title'), 5);
            add_filter('pre_get_document_title', array($this->template_loader, 'fix_document_title'), 5); // Note: We added another pre_get_document_title filter earlier, this one might override or chain depending on exact priorities later.
        } elseif ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('PRODUCT SETUP WARNING: template_loader object not available for Yoast fix hooks.', 'warning');
        }
    }

    /**
     * Get template for a specific page type
     *
     * @param string $template_path Relative template path.
     * @param string $page_description Description of the page type for logging.
     * @return string|false Template path or false if not found.
     */
    // ** FIX: Ensure only ONE definition of this method exists in the class **
    private function get_template_for_page_type($template_path, $page_description)
    {
        $apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
        $apw_log_exists = function_exists('apw_woo_log');

        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log("RESOLVER: Checking for custom template file for {$page_description}: " . basename($template_path));
        }

        $custom_template = $this->template_path . $template_path;

        if (file_exists($custom_template)) {
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log("RESOLVER: Found custom template file: " . basename($custom_template));
            }
            return $custom_template;
        }

        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log("RESOLVER: Custom template file NOT found: " . basename($custom_template));
        }

        return false; // Return false if the file doesn't exist
    }

} // End class APW_Woo_Template_Resolver