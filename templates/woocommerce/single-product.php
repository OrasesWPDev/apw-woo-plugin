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

get_header();

// Get current product
global $product;
if (!is_a($product, 'WC_Product')) {
    $product = wc_get_product(get_the_ID());
}

if ($product) :
    apw_woo_log('Loading single product template for product ID: ' . $product->get_id());
    ?>

    <main id="main" class="apw-woo-single-product-main">
        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="fourth-level-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                echo '<h1 class="apw-woo-page-title">' . esc_html(get_the_title()) . '</h1>';
            }
            ?>
        </div>

        <div class="apw-woo-container">
            <!-- Product Content -->
            <div class="apw-woo-product-content">
                <!-- Generic product content - to be customized later -->
                <div class="apw-woo-product-gallery-wrapper">
                    <?php
                    // Product gallery placeholder
                    if (function_exists('woocommerce_show_product_images')) {
                        woocommerce_show_product_images();
                    } else {
                        // Fallback if WooCommerce function doesn't exist
                        if (has_post_thumbnail()) {
                            echo '<div class="apw-woo-product-image">';
                            the_post_thumbnail('large');
                            echo '</div>';
                        }
                    }
                    ?>
                </div>

                <div class="apw-woo-product-summary">
                    <!-- Product title -->
                    <h1 class="apw-woo-product-title"><?php echo esc_html($product->get_name()); ?></h1>

                    <!-- Product price -->
                    <div class="apw-woo-product-price">
                        <?php echo $product->get_price_html(); ?>
                    </div>

                    <!-- Product short description -->
                    <div class="apw-woo-product-short-description">
                        <?php echo apply_filters('the_content', $product->get_short_description()); ?>
                    </div>

                    <!-- Add to cart button -->
                    <div class="apw-woo-add-to-cart">
                        <?php
                        if (function_exists('woocommerce_template_single_add_to_cart')) {
                            woocommerce_template_single_add_to_cart();
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Product description -->
            <div class="apw-woo-product-description">
                <h2 class="apw-woo-product-description-title">Product Description</h2>
                <?php echo apply_filters('the_content', $product->get_description()); ?>
            </div>

            <!-- Include FAQ Display Partial -->
            <?php
            // Include the FAQ display partial, passing the current product
            if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                // Set the product object that will be accessible in the included file
                $faq_product = $product;
                include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
            }
            ?>
        </div>
    </main>

<?php
endif;

get_footer();
?>