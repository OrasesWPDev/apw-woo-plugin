<?php
/**
 * Template for displaying single product pages
 *
 * @package APW_Woo_Plugin
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Loading single product template');
}
// Hook visualization function (only for admins in debug mode)
function apw_woo_hook_visualizer($hook_name)
{
    // [existing hook visualizer code]
}

// Improved compact hook visualization
function apw_woo_visualize_hook($hook_name, $params = array())
{
    // [existing visualization code]
}

// [existing hook registration code]

/**
 * Add wrapper around product options (before form)
 */
function apw_woo_add_product_options_wrapper()
{
    echo '<div class="apw-woo-product-options-wrapper">';
}

add_action('woocommerce_before_add_to_cart_form', 'apw_woo_add_product_options_wrapper', 5);

/**
 * Close product options wrapper and add purchase section structure
 */
function apw_woo_add_quantity_section()
{
    // Close product options wrapper
    echo '</div><!-- End product options wrapper -->';

    // Create structured purchase section
    echo '<div class="apw-woo-purchase-section">';
    echo '<div class="apw-woo-quantity-row">';
    echo '<span class="apw-woo-quantity-label">Quantity</span>';
}

add_action('woocommerce_before_add_to_cart_quantity', 'apw_woo_add_quantity_section', 5);

/**
 * Close purchase section after add to cart button
 */
function apw_woo_close_purchase_section()
{
    echo '</div><!-- End purchase section -->';
}

add_action('woocommerce_after_add_to_cart_button', 'apw_woo_close_purchase_section', 15);

get_header();
// Get current product
global $product;
if (!is_a($product, 'WC_Product')) {
    $product = wc_get_product(get_the_ID());
}
// Store original product to prevent global variable changes from affecting our template
$original_product = $product;
$original_product_id = $product ? $product->get_id() : 0;
if (APW_WOO_DEBUG_MODE && $product) {
    apw_woo_log("PRODUCT DEBUG: Template loading with post: " . $product->get_name() . " (ID: " . $product->get_id() . ")");
    apw_woo_log("PRODUCT DEBUG: Current URL: " . $_SERVER['REQUEST_URI']);
    apw_woo_log("PRODUCT DEBUG: get_the_ID() returns: " . get_the_ID());
    global $wp_query;
    apw_woo_log("PRODUCT DEBUG: WP Query object: " . print_r($wp_query->query, true));
}
if ($product) :
    ?>
    <main id="main" class="site-main apw-woo-single-product-main" role="main">
        <!-- APW-WOO-TEMPLATE: single-product.php is loaded -->
        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_product_header
             */
            do_action('apw_woo_before_product_header', $product);
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Rendering product page header');
            }
            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="third-level-woo-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                echo '<h1 class="apw-woo-page-title">' . esc_html($product->get_name()) . '</h1>';
            }
            /**
             * Hook: apw_woo_after_product_header
             */
            do_action('apw_woo_after_product_header', $product);
            ?>
        </div>

        <!-- Use Flatsome's container while keeping our plugin-specific classes -->
        <div class="container">
            <div class="row">
                <div class="col apw-woo-content-wrapper">
                    <?php
                    /**
                     * Hook: woocommerce_before_single_product
                     *
                     * @hooked woocommerce_output_all_notices - 10
                     */
                    do_action('woocommerce_before_single_product');
                    ?>
                    <div id="product-<?php the_ID(); ?>" <?php wc_product_class('', get_the_ID()); ?>>
                        <!-- Product Content Section -->
                        <div class="row apw-woo-row">
                            <!-- Gallery Column - Left Side -->
                            <div class="large-6 medium-6 small-12 apw-woo-product-gallery-col">
                                <?php do_action('apw_woo_before_product_gallery', $product); ?>
                                <div class="apw-woo-product-gallery-wrapper">
                                    <?php do_action('woocommerce_before_single_product_summary'); ?>
                                </div>
                                <?php do_action('apw_woo_after_product_gallery', $product); ?>
                            </div>
                            <!-- Product Summary Column - Right Side -->
                            <div class="large-6 medium-6 small-12 apw-woo-product-summary-col">
                                <?php do_action('apw_woo_before_product_summary', $product); ?>
                                <div class="apw-woo-product-summary">
                                    <!-- Product Title -->
                                    <!--                                    <div class="apw-woo-product-title-wrapper">-->
                                    <!--                                        --><?php //the_title('<h1 class="apw-woo-product-title">', '</h1>');
                                    ?>
                                    <!--                                    </div>-->
                                    <!-- Product Description -->
                                    <div class="apw-woo-product-description-wrapper">
                                        <?php echo apply_filters('the_content', $product->get_description()); ?>
                                    </div>
                                    <!-- Add to Cart Form -->
                                    <div class="apw-woo-add-to-cart-wrapper">
                                        <?php 
                                        // Debug logging for cart actions
                                        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                                            apw_woo_log('PRODUCT DEBUG: About to render add to cart form');
                                            apw_woo_log('PRODUCT DEBUG: User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No'));
                                            apw_woo_log('PRODUCT DEBUG: Cart count: ' . (function_exists('WC') && isset(WC()->cart) ? WC()->cart->get_cart_contents_count() : 'N/A'));
                                            
                                            // Log information about the View Cart URL
                                            if (function_exists('wc_get_cart_url')) {
                                                apw_woo_log('PRODUCT DEBUG: Cart URL: ' . wc_get_cart_url());
                                            }
                                            
                                            // Log information about the current page
                                            global $wp;
                                            apw_woo_log('PRODUCT DEBUG: Current URL: ' . home_url($wp->request));
                                        }
                                        
                                        woocommerce_template_single_add_to_cart(); 
                                        ?>
                                    </div>

                                    <!-- Notice Container - For WooCommerce messages - MOVED HERE -->
                                    <div class="apw-woo-notices-container apw-woo-notices-below-cart">
                                        <?php
                                        // Debug logging for notices
                                        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                                            apw_woo_log('PRODUCT DEBUG: Checking for notices to display');
                                            apw_woo_log('PRODUCT DEBUG: wc_print_notices function exists: ' . (function_exists('wc_print_notices') ? 'Yes' : 'No'));
                                            apw_woo_log('PRODUCT DEBUG: wc_has_notices function exists: ' . (function_exists('wc_has_notices') ? 'Yes' : 'No'));
                                            if (function_exists('wc_has_notices')) {
                                                apw_woo_log('PRODUCT DEBUG: Has notices: ' . (wc_has_notices() ? 'Yes' : 'No'));
                                            }
                                        }
                                        
                                        // Check if there are notices before printing
                                        if (function_exists('wc_print_notices') && function_exists('wc_has_notices') && wc_has_notices()) {
                                            wc_print_notices();
                                        }
                                        ?>
                                    </div>

                                    <!-- Product Meta -->
                                    <div class="apw-woo-product-meta">
                                        <?php if ($product->get_sku()) : ?>
                                            <span class="sku_wrapper"><?php esc_html_e('SKU:', 'woocommerce'); ?>
                                                <span class="sku"><?php echo esc_html($product->get_sku()); ?></span>
                                            </span>
                                            <span class="apw-woo-meta-separator">|</span>
                                        <?php endif; ?>
                                        <?php echo wc_get_product_category_list($product->get_id(), ', ', '<span class="posted_in">' . _n('Category:', 'Categories:', count($product->get_category_ids()), 'woocommerce') . ' ', '</span>'); ?>
                                    </div>
                                    
                                    <?php if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE): ?>
                                    <!-- Debug Information for Administrators -->
                                    <?php if (current_user_can('manage_options')): ?>
                                    <div class="apw-woo-debug-info" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-left: 4px solid #007cba;">
                                        <h4 style="margin-top: 0;">Debug Information</h4>
                                        <ul style="margin-bottom: 0;">
                                            <li><strong>User Status:</strong> <?php echo is_user_logged_in() ? 'Logged In (ID: ' . get_current_user_id() . ')' : 'Not Logged In'; ?></li>
                                            <li><strong>Cart Items:</strong> <?php echo function_exists('WC') && isset(WC()->cart) ? WC()->cart->get_cart_contents_count() : 'N/A'; ?></li>
                                            <li><strong>Cart URL:</strong> <?php echo function_exists('wc_get_cart_url') ? wc_get_cart_url() : 'N/A'; ?></li>
                                            <li><strong>Current URL:</strong> <?php echo home_url(add_query_arg(array(), $wp->request)); ?></li>
                                            <li><strong>WC Session Active:</strong> <?php echo function_exists('WC') && isset(WC()->session) && WC()->session->has_session() ? 'Yes' : 'No'; ?></li>
                                            <li><strong>Template File:</strong> <?php echo __FILE__; ?></li>
                                            <li><strong>Output Buffer Level:</strong> <?php echo ob_get_level(); ?></li>
                                        </ul>
                                        <p style="margin-top: 10px; margin-bottom: 0;">
                                            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" style="color: #007cba; text-decoration: underline;" onclick="console.log('View Cart clicked');">Test View Cart Link</a>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php do_action('apw_woo_after_product_summary', $product); ?>
                            </div>
                        </div>
                        <?php
                        /**
                         * Hook: woocommerce_after_single_product_summary.
                         *
                         * @hooked woocommerce_output_product_data_tabs - 10
                         * @hooked woocommerce_upsell_display - 15
                         * @hooked woocommerce_related_products - 20
                         */
                        do_action('woocommerce_after_single_product_summary');
                        ?>
                        <!-- FAQ Section -->
                        <div class="row apw-woo-row">
                            <div class="col apw-woo-faq-section-container">
                                <?php
                                // Reset product to original product before FAQ display
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('PRODUCT DEBUG BEFORE FAQ: Current post: ' . (isset($post) ? $post->post_title . ' (ID: ' . $post->ID . ')' : 'No post'));
                                    apw_woo_log('PRODUCT DEBUG BEFORE FAQ: Current product: ' . ($product ? $product->get_name() . ' (ID: ' . $product->get_id() . ')' : 'No product'));
                                    apw_woo_log('PRODUCT DEBUG BEFORE FAQ: Original product was: ' . ($original_product ? $original_product->get_name() . ' (ID: ' . $original_product_id . ')' : 'No original product'));
                                }
                                // Reset to original product if it has changed
                                if ($original_product && $product && $product->get_id() != $original_product_id) {
                                    $product = $original_product;
                                    $post = get_post($original_product_id);
                                    setup_postdata($post);
                                    if (APW_WOO_DEBUG_MODE) {
                                        apw_woo_log('PRODUCT DEBUG AFTER RESET: Current post: ' . ($post ? $post->post_title . ' (ID: ' . $post->ID . ')' : 'No post'));
                                        apw_woo_log('PRODUCT DEBUG AFTER RESET: Current product: ' . ($product ? $product->get_name() . ' (ID: ' . $product->get_id() . ')' : 'No product'));
                                    }
                                }
                                /**
                                 * Hook: apw_woo_before_product_faqs
                                 */
                                do_action('apw_woo_before_product_faqs', $product);
                                // Include the FAQ display partial
                                if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                                    // Verify product is valid before passing to FAQ display
                                    if (!is_a($product, 'WC_Product')) {
                                        if (APW_WOO_DEBUG_MODE) {
                                            apw_woo_log('ERROR: Invalid product object passed to FAQ display');
                                        }
                                        $faq_product = null;
                                    } else {
                                        $faq_product = apply_filters('apw_woo_faq_product', $product);
                                        if (APW_WOO_DEBUG_MODE) {
                                            apw_woo_log('Passing product to FAQ display: ' . $faq_product->get_name() . ' (ID: ' . $faq_product->get_id() . ')');
                                        }
                                    }
                                    include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
                                } else {
                                    if (APW_WOO_DEBUG_MODE) {
                                        apw_woo_log('FAQ display partial not found');
                                    }
                                }
                                /**
                                 * Hook: apw_woo_after_product_faqs
                                 */
                                do_action('apw_woo_after_product_faqs', $product);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>
<?php
else:
    ?>
    <div class="container">
        <div class="row">
            <div class="col">
                <p><?php esc_html_e('Product not found.', 'apw-woo-plugin'); ?></p>
            </div>
        </div>
    </div>
<?php
endif;
get_footer();
