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

    // Get rules from the Dynamic Pricing plugin
    // We need to access the stored rules from the plugin's settings
    if (class_exists('WC_Dynamic_Pricing_Advanced_Product')) {
        $pricing_obj = WC_Dynamic_Pricing_Advanced_Product::instance();

        // Access the pricing rules - actual method might vary by plugin version
        if (method_exists($pricing_obj, 'get_pricing_rules_for_product')) {
            $pricing_rules = $pricing_obj->get_pricing_rules_for_product($product_id);
        } elseif (property_exists($pricing_obj, 'rules')) {
            // Try to access rules property directly if the method doesn't exist
            $all_rules = $pricing_obj->rules;

            // Filter to find rules applicable to this product
            foreach ($all_rules as $rule_set) {
                if (isset($rule_set['targets']) && in_array($product_id, $rule_set['targets'])) {
                    $pricing_rules[] = $rule_set;
                }
            }
        }
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log(sprintf('Found %d dynamic pricing rules for product #%d', count($pricing_rules), $product_id));
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
    // Convert to product object if needed
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }

    if (!$product || !is_a($product, 'WC_Product')) {
        return 0;
    }

    // Get the regular price as a fallback
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();
    $base_price = ($sale_price && $sale_price < $regular_price) ? $sale_price : $regular_price;

    // If Dynamic Pricing plugin isn't active, return the base price
    if (!apw_woo_is_dynamic_pricing_active()) {
        return $base_price;
    }

    // Get the pricing rules for this product
    $pricing_rules = apw_woo_get_product_pricing_rules($product);

    if (empty($pricing_rules)) {
        return $base_price;
    }

    // Find applicable pricing rule based on quantity
    $discounted_price = $base_price;

    foreach ($pricing_rules as $rule) {
        // Rule structure might vary based on the plugin's implementation
        // This is a simplified example - adjust according to actual rule structure

        // Check if we have pricing rules by quantity
        if (isset($rule['rules']) && is_array($rule['rules'])) {
            foreach ($rule['rules'] as $price_rule) {
                if (isset($price_rule['from']) && $quantity >= $price_rule['from']) {
                    // If there's a 'to' limit, check if quantity is less than or equal to it
                    if (!isset($price_rule['to']) || $quantity <= $price_rule['to']) {
                        // Apply the pricing rule
                        if (isset($price_rule['amount'])) {
                            // Fixed price
                            $discounted_price = $price_rule['amount'];
                        } elseif (isset($price_rule['percentage'])) {
                            // Percentage discount
                            $discounted_price = $base_price * (1 - ($price_rule['percentage'] / 100));
                        }
                    }
                }
            }
        }
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log(sprintf('Calculated price for product #%d with quantity %d: Base=%f, Discounted=%f',
            $product->get_id(), $quantity, $base_price, $discounted_price));
    }

    return $discounted_price;
}

/**
 * AJAX handler to get updated dynamic price
 */
function apw_woo_ajax_get_dynamic_price()
{
    check_ajax_referer('apw_woo_dynamic_pricing', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

    if (!$product_id) {
        wp_send_json_error();
        return;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error();
        return;
    }

    // Get unit price based on quantity
    $unit_price = apw_woo_get_price_by_quantity($product, $quantity);

    wp_send_json_success(array(
        'unit_price' => $unit_price,
        'formatted_price' => wc_price($unit_price)
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

    // Check if Dynamic Pricing is active
    if (!apw_woo_is_dynamic_pricing_active()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic Pricing plugin not active - integration skipped');
        }
        return;
    }

    // Include the integration class if needed
    $class_file = APW_WOO_PLUGIN_DIR . 'includes/class-apw-woo-dynamic-pricing.php';
    if (file_exists($class_file)) {
        require_once $class_file;

        // Initialize the class if it exists
        if (class_exists('APW_Woo_Dynamic_Pricing')) {
            $dynamic_pricing = APW_Woo_Dynamic_Pricing::get_instance();
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

    // Mark as initialized to prevent recursive calls
    $initialized = true;

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Dynamic Pricing integration initialized');
    }
}