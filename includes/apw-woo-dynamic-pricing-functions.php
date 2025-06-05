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
        apw_woo_log("=== DYNAMIC PRICING AJAX DEBUG ===");
        apw_woo_log("Product ID: {$product_id} | Quantity: {$quantity}");
        apw_woo_log("Request from: " . ($_SERVER['HTTP_REFERER'] ?? 'unknown'));
        apw_woo_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
        
        // Check if this might be an addon-related request
        $is_addon_request = isset($_POST['addon_data']) || 
                           strpos($_SERVER['HTTP_REFERER'] ?? '', 'addon') !== false;
        apw_woo_log("Potential addon request: " . ($is_addon_request ? 'YES' : 'NO'));
        
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

    // Get original price for comparison
    $original_price = $product->get_price();
    
    // Send back both unit price and total with debug info
    wp_send_json_success(array(
        'unit_price' => $unit_price,
        'total_price' => $total_price,
        'formatted_price' => $formatted_unit_price,
        'formatted_total' => $formatted_total_price,
        'quantity' => $quantity,
        'product_id' => $product_id,
        'original_price' => $original_price,
        'price_changed' => (abs($unit_price - $original_price) > 0.01),
        'debug_info' => APW_WOO_DEBUG_MODE ? array(
            'product_name' => $product->get_name(),
            'has_pricing_rules' => apw_woo_product_has_pricing_rules($product),
            'is_dynamic_pricing_active' => apw_woo_is_dynamic_pricing_active()
        ) : null
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

    // Display the price with data attributes for JS to update
    echo '<div class="apw-woo-price-display price" data-product-id="' . esc_attr($product->get_id()) . '" data-quantity="' . esc_attr($quantity) . '">';
    echo '<span class="woocommerce-Price-amount amount">' . wc_price($unit_price) . '</span>';
    echo '</div>';
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

        // Create specific selector that excludes addon areas
        $main_price_selectors = array(
            '.apw-woo-price-display',
            '.summary .price:not(.addon-wrap .price)',
            '.summary .woocommerce-Price-amount:not(.addon-wrap .woocommerce-Price-amount)',
            '.product .price .amount:not(.addon-wrap .amount)',
            '.woocommerce-product-details__short-description + .price .amount'
        );

        // Log the selectors being used
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic pricing selectors: ' . implode(', ', $main_price_selectors));
            apw_woo_log('Excluding addon price elements from dynamic pricing updates');
        }

        // Localize script with data needed for AJAX
        wp_localize_script(
            'apw-woo-dynamic-pricing',
            'apwWooDynamicPricing',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('apw_woo_dynamic_pricing'),
                'threshold_nonce' => wp_create_nonce('apw_woo_threshold_check'),
                'price_selector' => implode(', ', $main_price_selectors),
                'addon_exclusion_selector' => '.addon-wrap, .apw-woo-product-addons, .wc-pao-addon-wrap',
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
 * Filter cart item price display to apply dynamic pricing discounts
 *
 * This function hooks into WooCommerce's cart item price display to ensure
 * that individual line items show the correct discounted price based on quantity,
 * matching the subtotal calculations.
 *
 * @param string $price_html The formatted price HTML from WooCommerce
 * @param array $cart_item The cart item data
 * @param string $cart_item_key The cart item key
 * @return string Modified price HTML with dynamic pricing applied
 */
function apw_woo_filter_cart_item_price($price_html, $cart_item, $cart_item_key)
{
    // PERFORMANCE: Early exit if dynamic pricing is not active
    if (!apw_woo_is_dynamic_pricing_active()) {
        return $price_html;
    }

    // PERFORMANCE: Static cache to prevent repeated calls for the same cart item
    static $price_cache = array();
    $cache_key = $cart_item_key . '_' . $cart_item['quantity'];
    
    if (isset($price_cache[$cache_key])) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CART ITEM PRICE FILTER: Using cached price for {$cache_key}");
        }
        return $price_cache[$cache_key];
    }

    // Debug logging for cart item price filtering
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("CART ITEM PRICE FILTER: Processing cart item key: {$cart_item_key} | Product ID: {$cart_item['product_id']} | Quantity: {$cart_item['quantity']}");
    }

    // Ensure we have valid cart item data
    if (!isset($cart_item['data']) || !isset($cart_item['quantity']) || !isset($cart_item['product_id'])) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CART ITEM PRICE FILTER: Invalid cart item data, returning original price");
        }
        return $price_html;
    }

    // Get the product object from cart item
    $product = $cart_item['data'];
    if (!$product || !is_a($product, 'WC_Product')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CART ITEM PRICE FILTER: Invalid product object, returning original price");
        }
        return $price_html;
    }

    // Get the quantity for this cart item
    $quantity = (int) $cart_item['quantity'];
    if ($quantity < 1) {
        $quantity = 1;
    }

    // PERFORMANCE: Check if this product has pricing rules before expensive calculation
    if (!apw_woo_product_has_pricing_rules($product)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CART ITEM PRICE FILTER: No pricing rules for product #{$cart_item['product_id']}, skipping");
        }
        // Cache the original price
        $price_cache[$cache_key] = $price_html;
        return $price_html;
    }

    // Calculate the dynamic price for this quantity using our existing function
    $dynamic_unit_price = apw_woo_get_price_by_quantity($product, $quantity);

    // Get the regular price for comparison
    $regular_price = (float) $product->get_regular_price();
    $original_price = (float) $product->get_price();

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("CART ITEM PRICE FILTER: Product #{$cart_item['product_id']} | Regular: {$regular_price} | Original: {$original_price} | Dynamic: {$dynamic_unit_price} | Qty: {$quantity}");
    }

    // Only modify the price if dynamic pricing is different from the original
    if (abs($dynamic_unit_price - $original_price) > 0.01) { // Use small tolerance for float comparison
        // POTENTIAL ISSUE FIX: Check if dynamic price is valid before applying
        if ($dynamic_unit_price <= 0) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("CART ITEM PRICE FILTER: Invalid dynamic price ({$dynamic_unit_price}), returning original price");
            }
            return $price_html;
        }

        // Format the new price using WooCommerce's price formatting
        $new_price_html = wc_price($dynamic_unit_price);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CART ITEM PRICE FILTER: Applied dynamic pricing | Original HTML: {$price_html} | New HTML: {$new_price_html}");
        }

        // Cache the new price before returning
        $price_cache[$cache_key] = $new_price_html;
        return $new_price_html;
    }

    // No dynamic pricing applies, return original price
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("CART ITEM PRICE FILTER: No dynamic pricing change needed for product #{$cart_item['product_id']}");
    }

    // Cache the original price before returning
    $price_cache[$cache_key] = $price_html;
    return $price_html;
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

    // CART ITEM PRICE FIX: Hook into cart item price display to apply dynamic pricing
    // This ensures that individual cart line items show the correct discounted price
    // to match the subtotal calculations
    add_filter('woocommerce_cart_item_price', 'apw_woo_filter_cart_item_price', 10, 3);

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Dynamic Pricing: Added cart item price filter hook');
    }

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
    
    // Register threshold message AJAX handlers
    add_action('wp_ajax_apw_woo_get_threshold_messages', 'apw_woo_ajax_get_threshold_messages');
    add_action('wp_ajax_nopriv_apw_woo_get_threshold_messages', 'apw_woo_ajax_get_threshold_messages');

    // Replace the default price display with our dynamic version - place it in quantity row
    // Priority 10 to run before apw_woo_close_quantity_row (priority 15)
    add_action('woocommerce_after_add_to_cart_quantity', 'apw_woo_replace_price_display', 10);

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

/**
 * Apply role-based bulk discounts as cart fees
 * 
 * Configurable discount system that applies discounts based on:
 * - Product ID and quantity thresholds
 * - User roles (optional)
 * - Priority system for multiple matching rules
 */
function apw_woo_apply_role_based_bulk_discounts($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    // Prevent multiple executions on the same request
    static $already_applied = false;
    if ($already_applied) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('BULK DISCOUNT: Already applied in this request, skipping duplicate');
        }
        return;
    }
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('BULK DISCOUNT: Starting bulk discount calculation');
    }

    // Configurable rules array - can be filtered by other plugins/themes
    $rules = array(
        array(
            'product_id' => 80,
            'discount_amount' => 10, // $10 off per item
            'priority' => 100,
            'min_quantity' => 1, // at least 1 in cart
            'role' => 'distro10', // Only for distro10 role
            'discount_name' => 'VIP Discount',
        ),
        array(
            'product_id' => 80,
            'discount_amount' => 10, // $10 off per item
            'priority' => 50,
            'min_quantity' => 5, // at least 5 in cart
            'role' => '', // For anyone else (no role requirement)
            'discount_name' => 'Bulk Discount',
        ),
    );

    /**
     * Filter the bulk discount rules
     *
     * @param array $rules Array of discount rules
     */
    $rules = apply_filters('apw_woo_bulk_discount_rules', $rules);

    $user = wp_get_current_user();
    $cart_items_by_product = array();

    // Step 1: Collect cart item quantities by product
    foreach ($cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $parent_id = wp_get_post_parent_id($product_id) ?: $product_id;
        $cart_items_by_product[$parent_id]['qty'] = ($cart_items_by_product[$parent_id]['qty'] ?? 0) + $cart_item['quantity'];
        $cart_items_by_product[$parent_id]['cart_items'][] = $cart_item;
    }

    // Step 2: For each product, find highest priority matching rule
    foreach ($cart_items_by_product as $product_id => $data) {
        $qty = $data['qty'];
        $matching_rule = null;
        $matched_priority = -INF;

        foreach ($rules as $rule) {
            if ((int)$rule['product_id'] !== (int)$product_id) {
                continue;
            }
            
            if ($qty < (int)($rule['min_quantity'] ?? 1)) {
                continue;
            }

            // Role logic
            $apply_for_role = true;
            if (!empty($rule['role'])) {
                $rule_roles = is_array($rule['role']) ? $rule['role'] : array($rule['role']);
                $apply_for_role = false;
                foreach ($rule_roles as $role) {
                    if (in_array($role, (array)$user->roles)) {
                        $apply_for_role = true;
                        break;
                    }
                }
            }

            // Skip if rule is for a role and user does not have it
            if (!$apply_for_role) {
                continue;
            }

            if ($rule['priority'] > $matched_priority) {
                $matching_rule = $rule;
                $matched_priority = $rule['priority'];
            }
        }

        // Step 3: Apply the rule as a fee (negative amount = discount)
        if ($matching_rule && $qty > 0) {
            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : '';
            $label = $matching_rule['discount_name'];
            if ($product_name) {
                $label .= " (" . $product_name . ")";
            }
            $discount = $matching_rule['discount_amount'] * $qty;
            $cart->add_fee($label, -$discount, true);

            // Fire action hooks for discount qualification and application
            do_action('apw_woo_bulk_discount_qualified', $matching_rule, $product_id, $qty);
            do_action('apw_woo_bulk_discount_applied', $matching_rule, $discount, $product_id, $qty);

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Applied bulk discount: $" . number_format($discount, 2) . " for {$qty} x {$product_name} (Rule: {$matching_rule['discount_name']})");
            }
        }
    }
    
    // Mark as applied to prevent duplicate execution
    $already_applied = true;
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('BULK DISCOUNT: Bulk discount calculation completed');
    }
}

/**
 * Check if current request should exclude addon price updates
 *
 * @param int $product_id The product ID to check
 * @return bool True if addon prices should be protected
 */
function apw_woo_should_exclude_addon_prices($product_id) {
    // Check if product has addons
    if (!function_exists('apw_woo_product_has_addons')) {
        return false;
    }
    
    $has_addons = apw_woo_product_has_addons($product_id);
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Product #{$product_id} has addons: " . ($has_addons ? 'YES' : 'NO'));
        
        if ($has_addons) {
            apw_woo_log("ADDON PROTECTION: Dynamic pricing should avoid updating addon price elements");
        }
    }
    
    return $has_addons;
}

/**
 * Simulate bulk discount calculation without actually applying fees
 * 
 * This function simulates what would happen if a product/quantity was added to cart
 * and returns threshold messages without modifying the actual cart.
 */
function apw_woo_simulate_bulk_discount_thresholds($product_id, $quantity) {
    // Get the same rules array as the actual discount function
    $rules = array(
        array(
            'product_id' => 80,
            'discount_amount' => 10, // $10 off per item
            'priority' => 100,
            'min_quantity' => 1, // at least 1 in cart
            'role' => 'distro10', // Only for distro10 role
            'discount_name' => 'VIP Discount',
            'threshold_message' => 'VIP discount active - savings applied at cart'
        ),
        array(
            'product_id' => 80,
            'discount_amount' => 10, // $10 off per item
            'priority' => 50,
            'min_quantity' => 5, // at least 5 in cart
            'role' => '', // For anyone else (no role requirement)
            'discount_name' => 'Bulk Discount',
            'threshold_message' => 'Quantity discount achieved - will be applied at cart'
        ),
    );

    // Apply the same filter as the real function
    $rules = apply_filters('apw_woo_bulk_discount_rules', $rules);
    
    $user = wp_get_current_user();
    $messages = array();
    $matching_rule = null;
    $matched_priority = -INF;

    // Find the highest priority matching rule (same logic as real function)
    foreach ($rules as $rule) {
        if ((int)$rule['product_id'] !== (int)$product_id) {
            continue;
        }
        
        if ($quantity < (int)($rule['min_quantity'] ?? 1)) {
            continue;
        }

        // Role logic (same as real function)
        $apply_for_role = true;
        if (!empty($rule['role'])) {
            $rule_roles = is_array($rule['role']) ? $rule['role'] : array($rule['role']);
            $apply_for_role = false;
            foreach ($rule_roles as $role) {
                if (in_array($role, (array)$user->roles)) {
                    $apply_for_role = true;
                    break;
                }
            }
        }

        // Skip if rule is for a role and user does not have it
        if (!$apply_for_role) {
            continue;
        }

        if ($rule['priority'] > $matched_priority) {
            $matching_rule = $rule;
            $matched_priority = $rule['priority'];
        }
    }

    // Add messages for qualifying discounts
    if ($matching_rule) {
        $messages[] = array(
            'type' => 'discount',
            'message' => $matching_rule['threshold_message'] ?? $matching_rule['discount_name'],
            'rule_name' => $matching_rule['discount_name'],
            'threshold' => $matching_rule['min_quantity']
        );
    }

    // Add shipping threshold if quantity is 10+
    $shipping_threshold = apply_filters('apw_woo_free_shipping_threshold', 10, $product_id);
    if ($quantity >= $shipping_threshold) {
        $messages[] = array(
            'type' => 'shipping',
            'message' => 'Free ground shipping at qty ' . $shipping_threshold,
            'threshold' => $shipping_threshold
        );
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("THRESHOLD SIMULATION: Product #{$product_id}, Qty: {$quantity}, Messages: " . count($messages));
    }

    return $messages;
}

/**
 * AJAX handler to simulate threshold messages for product page
 */
function apw_woo_ajax_get_threshold_messages() {
    // Security check
    check_ajax_referer('apw_woo_threshold_check', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

    if (!$product_id) {
        wp_send_json_error(array('message' => 'Invalid product ID'));
        return;
    }

    if ($quantity < 1) {
        $quantity = 1;
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("THRESHOLD AJAX: Checking thresholds for Product #{$product_id}, Qty: {$quantity}");
    }

    // Simulate what would happen if this product/quantity was in cart
    $threshold_messages = apw_woo_simulate_bulk_discount_thresholds($product_id, $quantity);

    wp_send_json_success(array(
        'product_id' => $product_id,
        'quantity' => $quantity,
        'threshold_messages' => $threshold_messages,
        'debug_info' => APW_WOO_DEBUG_MODE ? array(
            'user_roles' => wp_get_current_user()->roles,
            'messages_found' => count($threshold_messages)
        ) : null
    ));
}

// Hook into WooCommerce cart fee calculation
add_action('woocommerce_cart_calculate_fees', 'apw_woo_apply_role_based_bulk_discounts');

/**
 * Ensure cart is calculated on cart page load to show discounts immediately
 */
function apw_woo_ensure_cart_calculated_on_load() {
    if (is_cart() && !is_admin() && !defined('DOING_AJAX')) {
        // Force cart calculation to ensure fees are applied
        if (WC()->cart && !WC()->cart->is_empty()) {
            WC()->cart->calculate_totals();
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('CART LOAD: Forced cart calculation to ensure bulk discounts display immediately');
            }
        }
    }
}
add_action('wp', 'apw_woo_ensure_cart_calculated_on_load', 20);