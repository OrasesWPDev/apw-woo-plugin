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

// Add debug information about current post and product
if (APW_WOO_DEBUG_MODE) {
    global $post, $product, $wp_query;
    apw_woo_log("PRODUCT DEBUG: Template loading with post: " .
        ($post ? $post->post_title . " (ID: " . $post->ID . ")" : "No post set"));
    apw_woo_log("PRODUCT DEBUG: Current product global: " .
        (isset($GLOBALS['product']) && $GLOBALS['product'] ? $GLOBALS['product']->get_name() . " (ID: " . $GLOBALS['product']->get_id() . ")" : "No product set"));
    apw_woo_log("PRODUCT DEBUG: Current URL: " . $_SERVER['REQUEST_URI']);
    apw_woo_log("PRODUCT DEBUG: get_the_ID() returns: " . get_the_ID());
    apw_woo_log("PRODUCT DEBUG: WP Query object: " . print_r($wp_query->query, true));
}

// Hook visualization function (only for admins in debug mode)
function apw_woo_hook_visualizer($hook_name) {
    if (!APW_WOO_DEBUG_MODE || !current_user_can('manage_options')) {
        return function() {};
    }
    return function() use ($hook_name) {
        $args = func_get_args();
        apw_woo_visualize_hook($hook_name, $args);
    };
}

// Helper function to visualize hook data (only for admins)
function apw_woo_visualize_hook($hook_name, $params = array()) {
    if (!APW_WOO_DEBUG_MODE || !current_user_can('manage_options')) {
        return;
    }
    echo '<div style="margin: 10px 0; padding: 10px; border: 2px dashed #ff6b6b; background-color: #fff; color: #333; font-family: monospace;">';
    echo '<h4 style="margin: 0 0 5px 0; color: #ff6b6b;">HOOK: ' . esc_html($hook_name) . '</h4>';
    if (!empty($params)) {
        echo '<p>Available Parameters:</p>';
        echo '<ul style="margin: 0; padding-left: 20px;">';
        foreach ($params as $key => $value) {
            echo '<li>';
            if (is_object($value)) {
                echo 'Object: ' . esc_html(get_class($value));
                // Show basic info for WC_Product objects
                if ($value instanceof WC_Product) {
                    echo ' (ID: ' . esc_html($value->get_id()) . ', Name: ' . esc_html($value->get_name()) . ')';
                }
            } elseif (is_array($value)) {
                echo 'Array: ' . count($value) . ' items';
            } else {
                echo gettype($value) . ': ' . esc_html(var_export($value, true));
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No parameters available for this hook.</p>';
    }
    echo '</div>';
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
        'apw_woo_after_product_faqs'
    );
    // Add visualizers to all hooks
    foreach ($hooks_to_visualize as $hook) {
        add_action($hook, apw_woo_hook_visualizer($hook), 999);
    }
}

// Handle Product Add-Ons placement - preserve the hook structure
add_action('init', 'apw_woo_move_product_addons');
function apw_woo_move_product_addons() {
    // Only if Product Add-ons class exists
    if (class_exists('WC_Product_Addons_Display')) {
        // Remove from default location, preserving the hook itself
        remove_action('woocommerce_before_add_to_cart_button', array('WC_Product_Addons_Display', 'display'), 10);
        // Add to our desired location, maintaining correct hook integration
        add_action('woocommerce_product_meta_end', 'apw_woo_display_product_addons', 15);
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Add-ons relocated to product meta section');
        }
    }
}

// Function to display Product Add-Ons in our custom location
function apw_woo_display_product_addons() {
    global $product;
    if (!is_a($product, 'WC_Product')) {
        return;
    }
    // Check if Product Add-Ons are available for this product
    if (function_exists('get_product_addons')) {
        $product_addons = get_product_addons($product->get_id());
        if (!empty($product_addons)) {
            echo '<div class="apw-woo-product-addons">';
            echo '<h3 class="apw-woo-product-addons-title">' . esc_html__('Product Options', 'apw-woo-plugin') . '</h3>';
            // Use the original display method to ensure compatibility
            if (class_exists('WC_Product_Addons_Display') && method_exists('WC_Product_Addons_Display', 'display')) {
                WC_Product_Addons_Display::display();
            }
            echo '</div>'; // .apw-woo-product-addons
        }
    }
}

get_header();

// Get current product
global $product, $post;

// IMPORTANT: Store the original product and post for later use
// This prevents issues with loops that might change these globals
$original_product = $product;
$original_post = $post;

if (APW_WOO_DEBUG_MODE) {
    apw_woo_log("PRODUCT DEBUG: Stored original product: " .
        ($original_product ? $original_product->get_name() . " (ID: " . $original_product->get_id() . ")" : "No product set"));
}

if (!is_a($original_product, 'WC_Product')) {
    $original_product = wc_get_product(get_the_ID());
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("PRODUCT DEBUG: Created product from get_the_ID(): " .
            ($original_product ? $original_product->get_name() . " (ID: " . $original_product->get_id() . ")" : "Failed to create product"));
    }
}

if ($original_product) :
    ?>
    <main id="main" class="site-main apw-woo-single-product-main" role="main">
        <!-- APW-WOO-TEMPLATE: single-product.php is loaded -->
        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_product_header
             */
            do_action('apw_woo_before_product_header', $original_product);
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Rendering product page header');
            }
            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="fourth-level-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                echo '<h1 class="apw-woo-page-title">' . esc_html($original_product->get_name()) . '</h1>';
            }
            /**
             * Hook: apw_woo_after_product_header
             */
            do_action('apw_woo_after_product_header', $original_product);
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
                            <div class="col-md-6 apw-woo-product-gallery-col">
                                <?php do_action('apw_woo_before_product_gallery', $original_product); ?>
                                <div class="apw-woo-product-gallery-wrapper">
                                    <?php
                                    /**
                                     * Hook: woocommerce_before_single_product_summary
                                     *
                                     * @hooked woocommerce_show_product_sale_flash - 10
                                     * @hooked woocommerce_show_product_images - 20
                                     */
                                    do_action('woocommerce_before_single_product_summary');
                                    ?>
                                </div>
                                <?php do_action('apw_woo_after_product_gallery', $original_product); ?>
                            </div>
                            <div class="col-md-6 apw-woo-product-summary-col">
                                <?php do_action('apw_woo_before_product_summary', $original_product); ?>
                                <div class="apw-woo-product-summary">
                                    <?php
                                    /**
                                     * Hook: woocommerce_single_product_summary
                                     *
                                     * @hooked woocommerce_template_single_title - 5
                                     * @hooked woocommerce_template_single_rating - 10
                                     * @hooked woocommerce_template_single_price - 10
                                     * @hooked woocommerce_template_single_excerpt - 20
                                     * @hooked woocommerce_template_single_add_to_cart - 30
                                     * @hooked woocommerce_template_single_meta - 40
                                     * @hooked woocommerce_template_single_sharing - 50
                                     * @hooked WC_Structured_Data::generate_product_data() - 60
                                     */
                                    do_action('woocommerce_single_product_summary');
                                    ?>
                                </div>
                                <?php do_action('apw_woo_after_product_summary', $original_product); ?>
                            </div>
                        </div>
                        <?php
                        /**
                         * Hook: woocommerce_after_single_product_summary
                         *
                         * @hooked woocommerce_output_product_data_tabs - 10
                         * @hooked woocommerce_upsell_display - 15
                         * @hooked woocommerce_output_related_products - 20
                         */
                        do_action('woocommerce_after_single_product_summary');
                        ?>
                    </div>
                    <?php
                    /**
                     * Hook: woocommerce_after_single_product
                     */
                    do_action('woocommerce_after_single_product');
                    ?>
                    <!-- Product Description -->
                    <?php if ($original_product->get_description()) : ?>
                        <div class="row apw-woo-row">
                            <div class="col apw-woo-product-description-section">
                                <?php do_action('apw_woo_before_product_description', $original_product); ?>
                                <div class="apw-woo-product-description">
                                    <h2 class="apw-woo-product-description-title">
                                        <?php esc_html_e('Product Description', 'apw-woo-plugin'); ?>
                                    </h2>
                                    <?php echo apply_filters('the_content', $original_product->get_description()); ?>
                                </div>
                                <?php do_action('apw_woo_after_product_description', $original_product); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                  <!-- FAQ Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-faq-section-container">
                            <?php
                            /**
                             * Check what happened to our product globals
                             */
                            if (APW_WOO_DEBUG_MODE) {
                                global $post, $product;
                                apw_woo_log("PRODUCT DEBUG BEFORE FAQ: Current post: " .
                                    ($post ? $post->post_title . " (ID: " . $post->ID . ")" : "No post set"));
                                apw_woo_log("PRODUCT DEBUG BEFORE FAQ: Current product: " .
                                    ($product ? $product->get_name() . " (ID: " . $product->get_id() . ")" : "No product set"));
                                apw_woo_log("PRODUCT DEBUG BEFORE FAQ: Original product was: " .
                                    ($original_product ? $original_product->get_name() . " (ID: " . $original_product->get_id() . ")" : "No original product set"));
                            }

                            /**
                             * Hook: apw_woo_before_product_faqs
                             */
                            do_action('apw_woo_before_product_faqs', $original_product);

                            // IMPORTANT: Reset post and product to original values before passing to FAQ display
                            global $post, $product;
                            $post = $original_post;
                            setup_postdata($post);
                            $product = $original_product;

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log("PRODUCT DEBUG AFTER RESET: Current post: " .
                                    ($post ? $post->post_title . " (ID: " . $post->ID . ")" : "No post set"));
                                apw_woo_log("PRODUCT DEBUG AFTER RESET: Current product: " .
                                    ($product ? $product->get_name() . " (ID: " . $product->get_id() . ")" : "No product set"));
                            }

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
                            do_action('apw_woo_after_product_faqs', $original_product);
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