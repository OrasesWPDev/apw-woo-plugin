<?php
/**
 * The Template for displaying product archives (shop and category pages)
 *
 * This template determines which custom template to load based on the current page.
 *
 * @package APW_Woo_Plugin
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

// Direct test to see if this file is being loaded
error_log('APW WOO TEST: archive-product.php is being loaded');

// Log which template we're loading
apw_woo_log('Determining which template to display for current WooCommerce page');

// Determine which template to load based on current view
if (is_shop() && !is_search()) {
    // Main shop page - load the shop categories display template
    apw_woo_log('Current page is shop page - loading shop categories template');
    include(APW_WOO_PLUGIN_DIR . 'templates/partials/shop-categories-display.php');

} elseif (is_product_category()) {
    // Category page - load the category products display template
    $category = get_queried_object();
    apw_woo_log('Current page is category page: ' . $category->name . ' - loading category products template');
    include(APW_WOO_PLUGIN_DIR . 'templates/partials/category-products-display.php');

} else {
    // Other WooCommerce pages (search results, etc.) - use default WooCommerce template
    apw_woo_log('Current page is another WooCommerce archive page - using default template');

    get_header('shop');

    /**
     * Hook: woocommerce_before_main_content.
     */
    do_action('woocommerce_before_main_content');

    if (woocommerce_product_loop()) {
        do_action('woocommerce_before_shop_loop');
        woocommerce_product_loop_start();

        if (wc_get_loop_prop('total')) {
            while (have_posts()) {
                the_post();
                do_action('woocommerce_shop_loop');
                wc_get_template_part('content', 'product');
            }
        }

        woocommerce_product_loop_end();
        do_action('woocommerce_after_shop_loop');
    } else {
        do_action('woocommerce_no_products_found');
    }

    do_action('woocommerce_after_main_content');
    get_footer('shop');
}