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
    if (!APW_WOO_DEBUG_MODE || !current_user_can('manage_options')) {
        return function () {
        };
    }
    return function () use ($hook_name) {
        $args = func_get_args();
        apw_woo_visualize_hook($hook_name, $args);
    };
}

// Improved compact hook visualization
function apw_woo_visualize_hook($hook_name, $params = array())
{
    if (!APW_WOO_DEBUG_MODE || !current_user_can('manage_options')) {
        return;
    }
    static $hook_counter = 0;
    $hook_counter++;
    $hook_id = 'hook-' . $hook_counter;
    // Create a more compact display with toggle functionality
    ?>
    <div class="apw-hook-viz"
         style="margin: 5px 0; padding: 5px; border: 1px dashed #ff6b6b; background-color: #fff; font-family: monospace; font-size: 12px; max-width: 100%; overflow-x: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: bold; color: #ff6b6b;">HOOK: <?php echo esc_html($hook_name); ?></span>
            <a href="#"
               onclick="document.getElementById('<?php echo esc_attr($hook_id); ?>').style.display = document.getElementById('<?php echo esc_attr($hook_id); ?>').style.display === 'none' ? 'block' : 'none'; return false;"
               style="color: #0073aa; text-decoration: none; font-size: 10px;">[Toggle Details]</a>
        </div>
        <div id="<?php echo esc_attr($hook_id); ?>"
             style="display: none; margin-top: 5px; padding-top: 5px; border-top: 1px dotted #ddd;">
            <?php if (!empty($params)): ?>
                <div style="font-size: 11px; margin-bottom: 3px;">Parameters:</div>
                <div style="margin-left: 10px;">
                    <?php foreach ($params as $key => $value): ?>
                        <div style="margin-bottom: 2px; word-break: break-word;">
                            <strong><?php echo esc_html($key); ?>:</strong>
                            <?php
                            if (is_object($value)) {
                                echo 'Object: ' . esc_html(get_class($value));
                                if ($value instanceof WC_Product) {
                                    echo ' (ID: ' . esc_html($value->get_id()) . ', Name: ' . esc_html($value->get_name()) . ')';
                                }
                            } elseif (is_array($value)) {
                                // Show condensed array info
                                echo 'Array (' . count($value) . ' items)';
                            } else {
                                // Truncate long values
                                $val_str = var_export($value, true);
                                echo esc_html(substr($val_str, 0, 50));
                                if (strlen($val_str) > 50) echo '...';
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="font-style: italic; font-size: 11px;">No parameters</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Only add visualization for admins in debug mode
if (APW_WOO_DEBUG_MODE && current_user_can('manage_options')) {
    // Register visualizers for key WooCommerce hooks
    $hooks_to_visualize = array(
        'woocommerce_before_single_product',
        'woocommerce_before_single_product_summary',
        'woocommerce_product_thumbnails',
        'woocommerce_single_product_summary',
        'woocommerce_before_add_to_cart_form',
        'woocommerce_before_variations_form',
        'woocommerce_before_add_to_cart_button',
        'woocommerce_before_single_variation',
        'woocommerce_single_variation',
        'woocommerce_after_single_variation',
        'woocommerce_after_add_to_cart_button',
        'woocommerce_after_variations_form',
        'woocommerce_after_add_to_cart_form',
        'woocommerce_product_meta_start',
        'woocommerce_product_meta_end',
        'woocommerce_share',
        'woocommerce_after_single_product_summary',
        'woocommerce_after_single_product',
        // Custom hooks for our plugin
        'apw_woo_before_product_faqs',
        'apw_woo_after_product_faqs',
        'apw_woo_before_product_addons',
        'apw_woo_after_product_addons',
        'apw_woo_product_addons'
    );
    // Add visualizers to all hooks
    foreach ($hooks_to_visualize as $hook) {
        add_action($hook, apw_woo_hook_visualizer($hook), 999);
    }
}
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
 * Add price display and close quantity row
 */
function apw_woo_add_price_display()
{
    global $product;
    if (!$product) return;

    // Add price display and close row
    echo '<div class="apw-woo-price-display">' . wc_price($product->get_price()) . '</div>';
    echo '</div><!-- End quantity row -->';
}

add_action('woocommerce_after_add_to_cart_quantity', 'apw_woo_add_price_display');

/**
 * Close purchase section after add to cart button
 */
function apw_woo_close_purchase_section()
{
    echo '</div><!-- End purchase section -->';
}

add_action('woocommerce_after_add_to_cart_button', 'apw_woo_close_purchase_section', 15);

/**
 * Remove original price display
 */
function apw_woo_remove_price_display()
{
    // Priority 10 is the default for this hook
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
}

// Run at priority 5 to ensure it happens before the template_single_price function is called
add_action('woocommerce_single_product_summary', 'apw_woo_remove_price_display', 5);


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
                echo do_shortcode('[block id="fourth-level-page-header"]');
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
                                    <div class="apw-woo-product-title-wrapper">
                                        <?php the_title('<h1 class="apw-woo-product-title">', '</h1>'); ?>
                                    </div>
                                    <!-- Product Description -->
                                    <div class="apw-woo-product-description-wrapper">
                                        <?php echo apply_filters('the_content', $product->get_description()); ?>
                                    </div>
                                    <!-- Add to Cart Form -->
                                    <div class="apw-woo-add-to-cart-wrapper">
                                        <?php woocommerce_template_single_add_to_cart(); ?>
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
                                </div>
                                <?php do_action('apw_woo_after_product_summary', $product); ?>
                            </div>
                        </div>
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