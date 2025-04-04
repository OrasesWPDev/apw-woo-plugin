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

    // Temporarily store original product to restore later if needed
    $original_product = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;

    // Ensure the current product is set in the global scope for plugins that need it
    if (!isset($GLOBALS['product']) || !is_a($GLOBALS['product'], 'WC_Product') || $GLOBALS['product']->get_id() != $product_id) {
        $GLOBALS['product'] = is_object($product) && is_a($product, 'WC_Product') ?
            $product : wc_get_product($product_id);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Temporarily setting global product to ID: ' . $product_id);
        }
    }

    try {
        // Get rules from the Dynamic Pricing plugin - try multiple approaches
        if (class_exists('WC_Dynamic_Pricing_Advanced_Product')) {
            $pricing_obj = WC_Dynamic_Pricing_Advanced_Product::instance();

            // First approach: Use the plugin's dedicated method if available
            if (method_exists($pricing_obj, 'get_pricing_rules_for_product')) {
                $pricing_rules = $pricing_obj->get_pricing_rules_for_product($product_id);

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Retrieved pricing rules using get_pricing_rules_for_product method');
                }
            } // Second approach: Try to access rules property directly
            elseif (property_exists($pricing_obj, 'rules')) {
                $all_rules = $pricing_obj->rules;

                // Filter rules to find those applicable to this product
                foreach ($all_rules as $rule_set) {
                    // Check if this rule applies to our product
                    if (isset($rule_set['targets']) && in_array($product_id, $rule_set['targets'])) {
                        $pricing_rules[] = $rule_set;
                    }
                }

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Retrieved pricing rules by accessing rules property directly');
                }
            } // Third approach: Try to use alternative methods the plugin might have
            elseif (method_exists($pricing_obj, 'get_pricing_rules')) {
                $all_rules = $pricing_obj->get_pricing_rules();

                // Filter rules to those that apply to our product
                foreach ($all_rules as $rule_id => $rule_set) {
                    // Check different rule formats
                    if (isset($rule_set['targets']) && in_array($product_id, $rule_set['targets'])) {
                        $pricing_rules[] = $rule_set;
                    } elseif (isset($rule_set['products']) && in_array($product_id, $rule_set['products'])) {
                        $pricing_rules[] = $rule_set;
                    }
                }

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Retrieved pricing rules using get_pricing_rules method');
                }
            }
        }
    } catch (Exception $e) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Error retrieving dynamic pricing rules: ' . $e->getMessage(), 'error');
        }
    }

    // Restore original product if we changed it
    if ($original_product !== null && isset($GLOBALS['product']) && $GLOBALS['product']->get_id() != $original_product->get_id()) {
        $GLOBALS['product'] = $original_product;

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Restored original global product');
        }
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log(sprintf('Found %d dynamic pricing rules for product #%d', count($pricing_rules), $product_id));

        // Log details of the first rule if any found
        if (!empty($pricing_rules)) {
            $first_rule = reset($pricing_rules);
            apw_woo_log('Sample rule: ' . json_encode(array_slice($first_rule, 0, 3)) . '...');
        }
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

    foreach ($pricing_rules as $rule) {
        // Check if we have pricing rules by quantity
        if (isset($rule['rules']) && is_array($rule['rules'])) {
            foreach ($rule['rules'] as $price_rule) {
                if (isset($price_rule['from']) && $quantity >= $price_rule['from']) {
                    // If there's a 'to' limit, check if quantity is less than or equal to it
                    if (!isset($price_rule['to']) || $quantity <= $price_rule['to']) {
                        // Apply the pricing rule
                        if (isset($price_rule['amount'])) {
                            // Fixed price
                            $discounted_price = (float)$price_rule['amount'];
                            $rule_applied = true;

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("Applied fixed price rule: {$price_rule['amount']} for quantity {$quantity}");
                            }
                        } elseif (isset($price_rule['percentage'])) {
                            // Percentage discount
                            $discount_percentage = (float)$price_rule['percentage'];
                            $discounted_price = $base_price * (1 - ($discount_percentage / 100));
                            $rule_applied = true;

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("Applied percentage discount: {$discount_percentage}% for quantity {$quantity}");
                            }
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
 * Register and enqueue dynamic pricing JavaScript
 */
function apw_woo_enqueue_dynamic_pricing_scripts()
{
    // We need a custom check instead of just is_product()
    $is_custom_product_page = false;

    // Get current request path
    global $wp;
    $current_url = $wp->request;

    // Check if URL matches our product pattern
    if (preg_match('#^products/([^/]+)/([^/]+)$#', $current_url)) {
        $is_custom_product_page = true;
    }

    // Only enqueue on product pages (standard or custom), cart or checkout pages
    if (!is_product() && !$is_custom_product_page && !is_cart() && !is_checkout()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Not a product, cart, or checkout page - skipping dynamic pricing scripts');
        }
        return;
    }

    $js_dir = APW_WOO_PLUGIN_URL . 'assets/js/';
    $js_file = 'apw-woo-dynamic-pricing.js';
    $js_path = APW_WOO_PLUGIN_DIR . 'assets/js/' . $js_file;

    if (APW_WOO_DEBUG_MODE) {
        $page_type = is_product() ? 'product page' :
            ($is_custom_product_page ? 'custom product page' :
                (is_cart() ? 'cart page' :
                    (is_checkout() ? 'checkout page' : 'unknown page')));
        apw_woo_log('Loading dynamic pricing script on ' . $page_type);
        apw_woo_log('Checking for JS file at: ' . $js_path);
    }

    // Check if the file exists
    if (!file_exists($js_path)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic pricing JS file not found: ' . $js_path);
        }
        return;
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Enqueuing dynamic pricing script: ' . $js_dir . $js_file);
    }

    // Enqueue the JavaScript file
    wp_enqueue_script(
        'apw-woo-dynamic-pricing',
        $js_dir . $js_file,
        array('jquery'),
        filemtime($js_path),
        true
    );

    // Localize script with data needed for AJAX
    wp_localize_script(
        'apw-woo-dynamic-pricing',
        'apwWooDynamicPricing',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('apw_woo_dynamic_pricing'),
            'price_selector' => '.apw-woo-price-display',
            'is_cart' => is_cart(),
            'is_checkout' => is_checkout(),
            'is_product' => (is_product() || $is_custom_product_page)
        )
    );

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Dynamic pricing script successfully enqueued');
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