<?php
/**
 * Product Add-ons Helper Functions
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if WooCommerce Product Add-ons plugin is active and available
 *
 * @return bool True if Product Add-ons is active
 */
function apw_woo_is_product_addons_active() {
    return class_exists('WC_Product_Addons');
}

/**
 * Get product add-ons for a specific product
 *
 * @param int|WC_Product $product Product ID or product object
 * @return array Array of product add-ons or empty array if none found
 */
function apw_woo_get_product_addons($product) {
    // Make sure we have a product ID
    $product_id = 0;

    if (is_numeric($product)) {
        $product_id = $product;
    } elseif (is_object($product) && is_a($product, 'WC_Product')) {
        $product_id = $product->get_id();
    }

    if (!$product_id) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Invalid product passed to apw_woo_get_product_addons');
        }
        return array();
    }

    // Make sure Product Add-ons is active
    if (!function_exists('get_product_addons')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Add-ons function not available');
        }
        return array();
    }

    // Get the add-ons
    $addons = get_product_addons($product_id);

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log(sprintf('Found %d product add-on groups for product #%d', count($addons), $product_id));
    }

    return $addons;
}

/**
 * Check if a product has any add-ons (either specific or global)
 *
 * @param int|WC_Product $product Product ID or product object
 * @return bool True if the product has add-ons
 */
function apw_woo_product_has_addons($product) {
    $addons = apw_woo_get_product_addons($product);
    return !empty($addons);
}

/**
 * Get formatted HTML for product add-ons
 *
 * This is a wrapper around the Product Add-ons display function
 * that returns HTML instead of outputting directly
 *
 * @param int|WC_Product $product Product ID or product object
 * @param array $args Optional display arguments
 * @return string HTML content or empty string if no add-ons
 */
function apw_woo_get_product_addons_html($product, $args = array()) {
    // Default arguments
    $defaults = array(
        'container_class' => 'apw-woo-product-addons',
        'title_class' => 'apw-woo-product-addons-title',
        'title' => __('Product Options', 'apw-woo-plugin'),
        'show_title' => true,
    );

    // Parse arguments
    $args = wp_parse_args($args, $defaults);

    // Get product object
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }

    // Make sure we have a valid product
    if (!is_a($product, 'WC_Product')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Invalid product passed to apw_woo_get_product_addons_html');
        }
        return '';
    }

    // Make sure Product Add-ons is active
    if (!class_exists('WC_Product_Addons_Display') || !method_exists('WC_Product_Addons_Display', 'display')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Add-ons display function not available');
        }
        return '';
    }

    // Check if product has add-ons
    if (!apw_woo_product_has_addons($product)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('No add-ons found for product #' . $product->get_id());
        }
        return '';
    }

    // Start output buffering to capture add-ons HTML
    ob_start();

    // Store global product
    $original_product = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;

    // Set current product as global for add-ons rendering
    $GLOBALS['product'] = $product;

    // Custom hook
    do_action('apw_woo_before_product_addons_html', $product);

    echo '<div class="' . esc_attr($args['container_class']) . '">';

    if ($args['show_title']) {
        echo '<h3 class="' . esc_attr($args['title_class']) . '">' . esc_html($args['title']) . '</h3>';
    }

    // Custom start hook
    do_action('woocommerce_product_addons_start', $product);

    // Render the add-ons
    WC_Product_Addons_Display::display();

    // Custom end hook
    do_action('woocommerce_product_addons_end', $product);

    echo '</div>';

    // Custom hook
    do_action('apw_woo_after_product_addons_html', $product);

    // Get the content
    $content = ob_get_clean();

    // Restore original product
    $GLOBALS['product'] = $original_product;

    return $content;
}

/**
 * Initialize product add-ons hooks and integration
 *
 * This function is called from the main plugin file
 * to set up the product add-ons integration
 *
 * @return void
 */
function apw_woo_init_product_addons() {
    // Check if Product Add-ons is active
    if (!apw_woo_is_product_addons_active()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Add-ons plugin not active - integration skipped');
        }
        return;
    }

    // Include the class file
    require_once APW_WOO_PLUGIN_DIR . 'includes/class-apw-woo-product-addons.php';

    // Initialize the class
    $product_addons = APW_Woo_Product_Addons::get_instance();

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Product Add-ons integration initialized');
    }
}