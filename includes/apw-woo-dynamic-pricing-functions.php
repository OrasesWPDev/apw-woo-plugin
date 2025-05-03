<?php
/**
 * Dynamic Pricing Helper Functions
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if WooCommerce Dynamic Pricing plugin is active and available
 *
 * @return bool True if Dynamic Pricing is active
 */
function apw_woo_is_dynamic_pricing_active()
{
    return class_exists('WC_Dynamic_Pricing');
}

/**
 * Get dynamic pricing rules for a specific product
 *
 * @param int|WC_Product $product Product ID or product object
 * @return array Array of dynamic pricing rules or empty array if none found
 */
function apw_woo_get_product_pricing_rules($product)
{
    // Add diagnostic call at the beginning
    if (APW_WOO_DEBUG_MODE) {
        // Get product ID for diagnostic
        $diag_id = 0;
        if (is_numeric($product)) {
            $diag_id = $product;
        } elseif (is_object($product) && is_a($product, 'WC_Product')) {
            $diag_id = $product->get_id();
        }

        if ($diag_id > 0) {
            apw_woo_debug_dynamic_pricing_rules($diag_id);
        }
    }

    // Make sure we have a product ID
    $product_id = 0;
    if (is_numeric($product)) {
        $product_id = $product;
    } elseif (is_object($product) && is_a($product, 'WC_Product')) {
        $product_id = $product->get_id();
    }

    if (!$product_id) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Invalid product passed to apw_woo_get_product_pricing_rules');
        }
        return array();
    }

    // Make sure Dynamic Pricing is active
    if (!apw_woo_is_dynamic_pricing_active()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic Pricing plugin not available');
        }
        return array();
    }

    // Initialize rules array
    $pricing_rules = array();

    // Get product object if not already provided
    if (!is_object($product)) {
        $product = wc_get_product($product_id);
    }

    if (!$product) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Couldn't get product object for ID: {$product_id}");
        }
        return array();
    }

    // APPROACH 1: Get product-specific pricing rules from post meta
    $product_pricing_rules = get_post_meta($product_id, '_pricing_rules', true);

    if (!empty($product_pricing_rules) && is_array($product_pricing_rules)) {
        // We found product-specific rules!
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Found " . count($product_pricing_rules) . " product-specific pricing rules in post meta for product #{$product_id}");
            // DEBUGGING - dump the exact structure of the rules
            apw_woo_log("RULES STRUCTURE: " . print_r($product_pricing_rules, true));
        }

        // Format rules to match our expected structure
        foreach ($product_pricing_rules as $rule_set) {
            if (isset($rule_set['rules']) && is_array($rule_set['rules'])) {
                $pricing_rules[] = $rule_set;
            }
        }
    }

    // APPROACH 2: Try to get global rules that might apply to this product

    // 2.1: Check Advanced Product pricing module
    if (class_exists('WC_Dynamic_Pricing_Advanced_Product')) {
        try {
            $pricing_obj = WC_Dynamic_Pricing_Advanced_Product::instance();

            // Try dedicated method first
            if (method_exists($pricing_obj, 'get_pricing_rules_for_product')) {
                $found_rules = $pricing_obj->get_pricing_rules_for_product($product_id);
                if (!empty($found_rules)) {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("Found " . count($found_rules) . " global advanced product rules");
                    }
                    $pricing_rules = array_merge($pricing_rules, $found_rules);
                }
            } // Try rules property as fallback
            elseif (property_exists($pricing_obj, 'rules')) {
                $all_rules = $pricing_obj->rules;

                foreach ($all_rules as $rule_id => $rule_set) {
                    // Check if rule applies to our product
                    if (isset($rule_set['targets']) && in_array($product_id, $rule_set['targets'])) {
                        $pricing_rules[] = $rule_set;
                        if (APW_WOO_DEBUG_MODE) {
                            apw_woo_log("Found global advanced product rule: " . $rule_id);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Error accessing Advanced Product pricing: ' . $e->getMessage(), 'error');
            }
        }
    }

    // 2.2: Check for category-based pricing rules
    if (class_exists('WC_Dynamic_Pricing_Advanced_Category')) {
        try {
            $cat_pricing = WC_Dynamic_Pricing_Advanced_Category::instance();

            // Get product categories
            $category_ids = array();
            $terms = get_the_terms($product_id, 'product_cat');
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $category_ids[] = $term->term_id;
                }
            }

            // Look for rules that target these categories
            if (!empty($category_ids) && property_exists($cat_pricing, 'rules')) {
                $all_cat_rules = $cat_pricing->rules;

                foreach ($all_cat_rules as $rule_id => $rule_set) {
                    if (isset($rule_set['targets']) && is_array($rule_set['targets'])) {
                        // Check for category match
                        $matches = array_intersect($category_ids, $rule_set['targets']);
                        if (!empty($matches)) {
                            $pricing_rules[] = $rule_set;
                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("Found category pricing rule: " . $rule_id);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Error accessing Category pricing: ' . $e->getMessage(), 'error');
            }
        }
    }

    // 2.3: Check for simple/bulk pricing module
    if (class_exists('WC_Dynamic_Pricing_Simple_Product')) {
        try {
            $simple_pricing = WC_Dynamic_Pricing_Simple_Product::instance();

            if (property_exists($simple_pricing, 'rules')) {
                $simple_rules = $simple_pricing->rules;

                foreach ($simple_rules as $rule_id => $rule_set) {
                    if (isset($rule_set['targets']) && in_array($product_id, $rule_set['targets'])) {
                        $pricing_rules[] = $rule_set;
                        if (APW_WOO_DEBUG_MODE) {
                            apw_woo_log("Found simple product pricing rule: " . $rule_id);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Error accessing Simple pricing: ' . $e->getMessage(), 'error');
            }
        }
    }

    // 2.4: Last resort - check global dynamic pricing manager
    global $wc_dynamic_pricing;
    if (isset($wc_dynamic_pricing) && is_object($wc_dynamic_pricing)) {
        if (method_exists($wc_dynamic_pricing, 'get_pricing_rules_for_product')) {
            try {
                $global_rules = $wc_dynamic_pricing->get_pricing_rules_for_product($product_id);
                if (!empty($global_rules)) {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("Found " . count($global_rules) . " rules from global pricing manager");
                    }
                    $pricing_rules = array_merge($pricing_rules, $global_rules);
                }
            } catch (Exception $e) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Error accessing global pricing manager: ' . $e->getMessage(), 'error');
                }
            }
        }
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log(sprintf('Found total of %d dynamic pricing rules for product #%d', count($pricing_rules), $product_id));
    }

    return $pricing_rules;
}

/**
 * Check if a product has any dynamic pricing rules
 *
 * @param int|WC_Product $product Product ID or product object
 * @return bool True if the product has dynamic pricing rules
 */
function apw_woo_product_has_pricing_rules($product)
{
    $pricing_rules = apw_woo_get_product_pricing_rules($product);
    return !empty($pricing_rules);
}

/**
 * Get the unit price for a product based on quantity, respecting dynamic pricing rules
 *
 * @param int|WC_Product $product Product ID or product object
 * @param int $quantity The quantity
 * @return float The calculated unit price
 */
function apw_woo_get_price_by_quantity($product, $quantity = 1)
{
    // Start performance tracking if enabled
    $start_time = microtime(true);

    // Convert to product object if needed
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }

    if (!$product || !is_a($product, 'WC_Product')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Invalid product passed to apw_woo_get_price_by_quantity');
        }
        return 0;
    }

    // Get the regular price as a fallback
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();
    $base_price = ($sale_price && $sale_price < $regular_price) ? $sale_price : $regular_price;

    // Convert to float for calculations
    $base_price = (float)$base_price;

    // If Dynamic Pricing plugin isn't active, return the base price
    if (!apw_woo_is_dynamic_pricing_active()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic Pricing plugin not active, returning base price: ' . $base_price);
        }
        return $base_price;
    }

    // Get the pricing rules for this product
    $pricing_rules = apw_woo_get_product_pricing_rules($product);

    if (empty($pricing_rules)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('No pricing rules found for product #' . $product->get_id() . ', returning base price: ' . $base_price);
        }
        return $base_price;
    }

    // Find applicable pricing rule based on quantity
    $discounted_price = $base_price;
    $rule_applied = false;
    $applied_rule_details = '';

// Dump the full rules array for debugging
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("FULL RULES ARRAY: " . print_r($pricing_rules, true));
    }

    foreach ($pricing_rules as $rule) {
        // Check if we have pricing rules by quantity
        if (isset($rule['rules']) && is_array($rule['rules'])) {
            foreach ($rule['rules'] as $price_rule) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("Checking rule: " . print_r($price_rule, true));
                    apw_woo_log("Current quantity: {$quantity}, Comparing against from: " . (isset($price_rule['from']) ? $price_rule['from'] : 'N/A'));
                }

                // Check if quantity matches the rule's 'from' threshold
                if (isset($price_rule['from']) && $quantity >= (int)$price_rule['from']) {
                    // Check if there's a 'to' limit and if quantity is within it
                    if (!isset($price_rule['to']) || empty($price_rule['to']) || $quantity <= (int)$price_rule['to']) {
                        if (APW_WOO_DEBUG_MODE) {
                            apw_woo_log("MATCH FOUND - Quantity {$quantity} matches rule threshold {$price_rule['from']}");
                        }

                        // Check rule type and apply appropriate pricing
                        $rule_type = isset($price_rule['type']) ? $price_rule['type'] : '';

                        if ($rule_type === 'fixed_price' && isset($price_rule['amount'])) {
                            // Fixed price rule (matches our logs)
                            $discounted_price = (float)$price_rule['amount'];
                            $rule_applied = true;
                            $applied_rule_details = "fixed price rule: {$price_rule['amount']} for quantity {$quantity}";

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("APPLYING FIXED PRICE RULE: {$price_rule['amount']} for quantity {$quantity}");
                            }
                            break 2; // Exit both loops
                        } elseif ($rule_type === 'percentage' && isset($price_rule['amount'])) {
                            // Percentage discount
                            $discount_percentage = (float)$price_rule['amount'];
                            $discounted_price = $base_price * (1 - ($discount_percentage / 100));
                            $rule_applied = true;
                            $applied_rule_details = "percentage discount: {$discount_percentage}% for quantity {$quantity}";

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("APPLYING PERCENTAGE DISCOUNT: {$discount_percentage}% for quantity {$quantity}");
                            }
                            break 2; // Exit both loops
                        } elseif (isset($price_rule['amount'])) {
                            // Fallback: if type is not recognized but amount exists, use it
                            $discounted_price = (float)$price_rule['amount'];
                            $rule_applied = true;
                            $applied_rule_details = "rule with amount: {$price_rule['amount']} for quantity {$quantity}";

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("APPLYING GENERIC RULE: {$price_rule['amount']} for quantity {$quantity}");
                            }
                            break 2; // Exit both loops
                        }
                    }
                }
            }
        }
    }

    if (APW_WOO_DEBUG_MODE) {
        $execution_time = microtime(true) - $start_time;
        $execution_ms = round($execution_time * 1000, 2);
        apw_woo_log(sprintf('Calculated price for product #%d with quantity %d: Base=%f, Discounted=%f, Rule applied=%s (took %sms)',
            $product->get_id(), $quantity, $base_price, $discounted_price, ($rule_applied ? 'yes' : 'no'), $execution_ms));
    }

    return $discounted_price;
}

/**
 * AJAX handler to get updated dynamic price
 */
function apw_woo_ajax_get_dynamic_price()
{
    // Security check
    check_ajax_referer('apw_woo_dynamic_pricing', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

    if (!$product_id) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic pricing AJAX: No product ID provided', 'warning');
        }
        wp_send_json_error(array('message' => 'Invalid product ID'));
        return;
    }

    // Validate minimum quantity
    if ($quantity < 1) {
        $quantity = 1;
    }

    // Log request details in debug mode
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Dynamic pricing AJAX request: Product ID: {$product_id}, Quantity: {$quantity}");
    }

    // Get the product
    $product = wc_get_product($product_id);

    if (!$product) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Dynamic pricing AJAX: Product #{$product_id} not found", 'error');
        }
        wp_send_json_error(array('message' => 'Product not found'));
        return;
    }

    // Get unit price based on quantity
    $unit_price = apw_woo_get_price_by_quantity($product, $quantity);

    // Calculate total price
    $total_price = $unit_price * $quantity;

    // Format prices using WooCommerce's price formatter
    $formatted_unit_price = wc_price($unit_price);
    $formatted_total_price = wc_price($total_price);

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Dynamic pricing AJAX response: Unit price: {$unit_price}, Formatted: {$formatted_unit_price}");
    }

    // Send back both unit price and total
    wp_send_json_success(array(
        'unit_price' => $unit_price,
        'total_price' => $total_price,
        'formatted_price' => $formatted_unit_price,
        'formatted_total' => $formatted_total_price,
        'quantity' => $quantity,
        'product_id' => $product_id
    ));
}

/**
 * Replace the default price display with our dynamic version
 */
function apw_woo_replace_price_display()
{
    global $product, $post;

    // If product is not available, try to get it from the post
    if (!$product && $post && $post->post_type === 'product') {
        $product = wc_get_product($post->ID);
    }

    // If still no product, try to get it from queried object
    if (!$product) {
        $queried_object = get_queried_object();
        if ($queried_object && isset($queried_object->ID) && get_post_type($queried_object->ID) === 'product') {
            $product = wc_get_product($queried_object->ID);
        }
    }

    // For cart and checkout pages, we don't need to display individual product prices here
    if (is_cart() || is_checkout()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('On cart/checkout page - skipping individual product price display');
        }
        return;
    }

    if (!$product) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Could not find product for price display');
        }
        return;
    }

    // Get the current quantity from the form (default to 1)
    $quantity = 1;

    // Get unit price based on quantity
    $unit_price = apw_woo_get_price_by_quantity($product, $quantity);

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Displaying price for product {$product->get_id()} with unit price {$unit_price}");
    }

    // Display the price with data attribute for JS to update
    echo '<div class="apw-woo-price-display" data-product-id="' . esc_attr($product->get_id()) . '">'
        . wc_price($unit_price)
        . '</div>';
    echo '</div><!-- End quantity row -->';
}

/**
 * Register and enqueue dynamic pricing JavaScript.
 * REVISED: Only enqueues on single product pages (standard or custom URLs).
 */
function apw_woo_enqueue_dynamic_pricing_scripts()
{
    // --- Check if we are on a single product page ---

    // Use WooCommerce's standard check first
    $is_standard_product_page = is_product();

    // Check for our custom product URL structure
    $is_custom_product_page = false;
    global $wp;
    $current_url = $wp->request ?? ''; // Use null coalescing operator
    // Use the more specific Page Detector class if available
    if (class_exists('APW_Woo_Page_Detector') && method_exists('APW_Woo_Page_Detector', 'is_product_page')) {
        // Use the detector which includes the URL check and other methods
        $is_custom_product_page = APW_Woo_Page_Detector::is_product_page($wp);
    } elseif (preg_match('#^products/([^/]+)/([^/]+)$#', $current_url)) {
        // Fallback URL check if detector class isn't loaded yet (less reliable)
        $is_custom_product_page = true;
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic pricing enqueue: Page Detector class not found, using basic URL match for custom product page.');
        }
    }

    // --- Enqueue Condition: Only load if it's a product page ---
    if ($is_standard_product_page || $is_custom_product_page) {

        if (APW_WOO_DEBUG_MODE) {
            $page_type = $is_standard_product_page ? 'standard product page' : 'custom URL product page';
            apw_woo_log('Enqueuing dynamic pricing script on ' . $page_type);
        }

        $js_dir = APW_WOO_PLUGIN_URL . 'assets/js/';
        $js_file = 'apw-woo-dynamic-pricing.js';
        $js_path = APW_WOO_PLUGIN_DIR . 'assets/js/' . $js_file;

        // Check if the file exists
        if (!file_exists($js_path)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Dynamic pricing JS file not found: ' . $js_path, 'error');
            }
            return; // Stop if file is missing
        }

        // Enqueue the JavaScript file
        wp_enqueue_script(
            'apw-woo-dynamic-pricing',
            $js_dir . $js_file,
            array('jquery'), // Dependency
            filemtime($js_path), // Cache busting
            true // Load in footer
        );

        // Localize script with data needed for AJAX
        wp_localize_script(
            'apw-woo-dynamic-pricing',
            'apwWooDynamicPricing',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('apw_woo_dynamic_pricing'),
                'price_selector' => '.apw-woo-price-display, .woocommerce-Price-amount, .price .amount', // Expanded selectors
                // Removed is_cart and is_checkout as they are irrelevant here
                'is_product' => true, // Script only loads on product pages now
                'debug_mode' => APW_WOO_DEBUG_MODE // Pass debug mode status
            )
        );

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic pricing script successfully enqueued.');
        }

    } else {
        // --- Not a product page, do not enqueue ---
        if (APW_WOO_DEBUG_MODE) {
            // Determine why it's not loading for more informative logs
            $reason = '';
            if (is_cart()) $reason = 'cart page';
            elseif (is_checkout()) $reason = 'checkout page';
            elseif (is_shop()) $reason = 'shop page';
            elseif (is_product_category()) $reason = 'category page';
            else $reason = 'non-product page (' . ($current_url ?: 'unknown URL') . ')';

            apw_woo_log('Skipping dynamic pricing script enqueue: Currently on ' . $reason);
        }
        return; // Explicitly return to prevent further execution
    }
}

/**
 * Initialize dynamic pricing hooks and integration
 *
 * This function is called from the main plugin file
 * to set up the dynamic pricing integration
 *
 * @return void
 */
function apw_woo_init_dynamic_pricing()
{
    // Prevent multiple initializations
    static $initialized = false;
    if ($initialized) {
        return;
    }

    // Check if Dynamic Pricing is active using more robust detection
    $dynamic_pricing_active = apw_woo_is_dynamic_pricing_active();

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Dynamic Pricing active check: ' . ($dynamic_pricing_active ? 'YES' : 'NO'));
    }

    if (!$dynamic_pricing_active) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic Pricing plugin not active - integration skipped');
        }
        return;
    }

    // Add a filter to support our custom URL structure with Dynamic Pricing
    add_filter('woocommerce_is_purchasable', function ($is_purchasable, $product) {
        // If we've set our custom flag, ensure the product is purchasable
        if (isset($GLOBALS['apw_is_custom_product_url']) && $GLOBALS['apw_is_custom_product_url']) {
            if (APW_WOO_DEBUG_MODE && !$is_purchasable) {
                apw_woo_log('Fixed purchasable status for product in custom URL');
            }
            return true;
        }
        return $is_purchasable;
    }, 10, 2);

    // Include the integration class if needed
    $class_file = APW_WOO_PLUGIN_DIR . 'includes/class-apw-woo-dynamic-pricing.php';
    if (file_exists($class_file)) {
        require_once $class_file;

        // Initialize the class if it exists
        if (class_exists('APW_Woo_Dynamic_Pricing')) {
            $dynamic_pricing = APW_Woo_Dynamic_Pricing::get_instance();
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Dynamic Pricing class initialized');
            }
        }
    }

    // Add script enqueuing
    add_action('wp_enqueue_scripts', 'apw_woo_enqueue_dynamic_pricing_scripts');

    // Register AJAX handlers
    add_action('wp_ajax_apw_woo_get_dynamic_price', 'apw_woo_ajax_get_dynamic_price');
    add_action('wp_ajax_nopriv_apw_woo_get_dynamic_price', 'apw_woo_ajax_get_dynamic_price');

    // Replace the default price display with our dynamic version
    add_action('woocommerce_after_add_to_cart_quantity', 'apw_woo_replace_price_display', 9);

    // Remove the original price display function
    remove_action('woocommerce_after_add_to_cart_quantity', 'apw_woo_add_price_display');

    // Hook to debug dynamic pricing in WooCommerce admin
    if (APW_WOO_DEBUG_MODE && is_admin()) {
        add_action('admin_notices', 'apw_woo_debug_dynamic_pricing_admin');
    }

    // Mark as initialized to prevent recursive calls
    $initialized = true;

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Dynamic Pricing integration initialized');
    }
}

/**
 * Display debug information about dynamic pricing in admin
 *
 * This function shows debug information in the WordPress admin
 * to help diagnose Dynamic Pricing integration issues
 */
function apw_woo_debug_dynamic_pricing_admin()
{
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if Dynamic Pricing is active
    $is_active = apw_woo_is_dynamic_pricing_active();

    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>APW Dynamic Pricing Debug Info:</strong></p>';
    echo '<ul>';
    echo '<li>Dynamic Pricing Plugin Active: ' . ($is_active ? 'Yes' : 'No') . '</li>';

    if ($is_active) {
        // Check for the main class
        $class_exists = class_exists('WC_Dynamic_Pricing');
        echo '<li>WC_Dynamic_Pricing class exists: ' . ($class_exists ? 'Yes' : 'No') . '</li>';

        // Check for product pricing class
        $product_pricing_exists = class_exists('WC_Dynamic_Pricing_Advanced_Product');
        echo '<li>WC_Dynamic_Pricing_Advanced_Product class exists: ' . ($product_pricing_exists ? 'Yes' : 'No') . '</li>';
    }

    echo '</ul>';
    echo '<p><em>This notice is only visible to administrators.</em></p>';
    echo '</div>';
}

/**
 * Debug dynamic pricing rules for a product
 *
 * @param int $product_id The product ID to check
 * @return void
 */
function apw_woo_debug_dynamic_pricing_rules($product_id)
{
    if (!APW_WOO_DEBUG_MODE) return;

    apw_woo_log("DYNAMIC PRICING DEBUG: Analyzing rules for product #{$product_id}");

    // Check if Dynamic Pricing is active
    if (!apw_woo_is_dynamic_pricing_active()) {
        apw_woo_log("DYNAMIC PRICING DEBUG: Dynamic Pricing plugin not active");
        return;
    }

    // Check if the product exists
    $product = wc_get_product($product_id);
    if (!$product) {
        apw_woo_log("DYNAMIC PRICING DEBUG: Product #{$product_id} not found");
        return;
    }

    // Check for main pricing class
    if (!class_exists('WC_Dynamic_Pricing_Advanced_Product')) {
        apw_woo_log("DYNAMIC PRICING DEBUG: WC_Dynamic_Pricing_Advanced_Product class not found");

        // Check for alternative classes
        if (class_exists('WC_Dynamic_Pricing')) {
            apw_woo_log("DYNAMIC PRICING DEBUG: Main WC_Dynamic_Pricing class exists");
            $main_pricing = WC_Dynamic_Pricing::instance();
            apw_woo_log("DYNAMIC PRICING DEBUG: Available properties: " . implode(', ', array_keys(get_object_vars($main_pricing))));
        }
        return;
    }

    // Get instance
    $pricing_obj = WC_Dynamic_Pricing_Advanced_Product::instance();

    // Log available methods for debugging
    apw_woo_log("DYNAMIC PRICING DEBUG: Available methods: " . implode(', ', get_class_methods($pricing_obj)));

    // Check rules property
    if (property_exists($pricing_obj, 'rules')) {
        $all_rules = $pricing_obj->rules;
        apw_woo_log("DYNAMIC PRICING DEBUG: Found " . count($all_rules) . " rule sets in total");

        // Check rules structure
        if (!empty($all_rules)) {
            $rule_keys = array_keys($all_rules);
            $first_rule_key = reset($rule_keys);
            $first_rule = $all_rules[$first_rule_key];

            apw_woo_log("DYNAMIC PRICING DEBUG: First rule ID: {$first_rule_key}");
            apw_woo_log("DYNAMIC PRICING DEBUG: Rule structure keys: " . implode(', ', array_keys($first_rule)));

            // Check if there's a targets array
            if (isset($first_rule['targets'])) {
                apw_woo_log("DYNAMIC PRICING DEBUG: Rule targets: " . implode(', ', $first_rule['targets']));
            }
        }
    } else {
        apw_woo_log("DYNAMIC PRICING DEBUG: No 'rules' property found on pricing object");
    }

    // Check alternative rule storage locations
    apw_woo_log("DYNAMIC PRICING DEBUG: Checking global objects for rules");
    global $wc_dynamic_pricing;
    if (isset($wc_dynamic_pricing) && is_object($wc_dynamic_pricing)) {
        apw_woo_log("DYNAMIC PRICING DEBUG: Found global wc_dynamic_pricing object");
    }
}