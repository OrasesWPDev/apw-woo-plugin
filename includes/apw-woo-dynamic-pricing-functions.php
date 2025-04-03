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

    // Get the pricing rules (if Dynamic Pricing plugin provides such a function)
    $pricing_rules = array();

    // Using Dynamic Pricing API if available
    if (class_exists('WC_Dynamic_Pricing_Product_Rules')) {
        $pricing_rules = WC_Dynamic_Pricing_Product_Rules::get_product_rules($product_id);
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
 * Get formatted price HTML that includes dynamic pricing information
 *
 * @param WC_Product $product The product object
 * @param array $args Optional display arguments
 * @return string HTML content with pricing information
 */
function apw_woo_get_dynamic_price_html($product, $args = array())
{
    if (!$product || !is_a($product, 'WC_Product')) {
        return '';
    }

    // Default price display
    $price_html = wc_price($product->get_price());

    // Check if dynamic pricing is active and if this product has dynamic pricing rules
    if (apw_woo_is_dynamic_pricing_active() && apw_woo_product_has_pricing_rules($product)) {
        // Get the pricing rules
        $pricing_rules = apw_woo_get_product_pricing_rules($product);

        // If we have rules, enhance the price display
        if (!empty($pricing_rules)) {
            // This will need to be customized based on the actual structure of pricing rules
            // from your Dynamic Pricing plugin
            $price_html = apply_filters('apw_woo_dynamic_price_html', $price_html, $product, $pricing_rules);
        }
    }

    return $price_html;
}

/**
 * Filter to display dynamic pricing information in the price HTML
 */
function apw_woo_dynamic_price_filter($price_html, $product, $pricing_rules)
{
    // Build enhanced price display with quantity pricing information
    // This is a placeholder and should be customized for your specific needs
    $enhanced_html = $price_html;

    // Add quantity price breakdown if needed
    // Example: $enhanced_html .= '<div class="quantity-pricing">Qty 5+: $99</div>';

    return $enhanced_html;
}

add_filter('apw_woo_dynamic_price_html', 'apw_woo_dynamic_price_filter', 10, 3);

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
    // Check if Dynamic Pricing is active
    if (!apw_woo_is_dynamic_pricing_active()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Dynamic Pricing plugin not active - integration skipped');
        }
        return;
    }

    // Include the class file
    require_once APW_WOO_PLUGIN_DIR . 'includes/class-apw-woo-dynamic-pricing.php';

    // Initialize the class
    $dynamic_pricing = APW_Woo_Dynamic_Pricing::get_instance();

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Dynamic Pricing integration initialized');
    }
}