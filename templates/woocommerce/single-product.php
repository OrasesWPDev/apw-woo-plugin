<?php
/**
 * Template for displaying single product pages with support for Product Add-Ons
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

apw_woo_log('Loading single product template');

get_header();

// Get current product
global $product;
if (!is_a($product, 'WC_Product')) {
    $product = wc_get_product(get_the_ID());
}

if ($product) :
    // Basic product data
    $product_id = $product->get_id();
    $product_title = $product->get_name();
    $product_price = $product->get_price_html();

    // Get product add-ons if they exist
    $has_addons = false;
    $product_addons = array();

    // Check if Product Add-Ons plugin is active and get add-ons
    if (function_exists('WC_Product_Addons_Helper::get_product_addons')) {
        $product_addons = WC_Product_Addons_Helper::get_product_addons($product_id);
        $has_addons = !empty($product_addons);
        apw_woo_log('Product has ' . count($product_addons) . ' add-ons');
    } elseif (function_exists('get_product_addons')) {
        // Fallback for older versions of the plugin
        $product_addons = get_product_addons($product_id);
        $has_addons = !empty($product_addons);
        apw_woo_log('Product has ' . count($product_addons) . ' add-ons (legacy method)');
    }

    // Make add-ons data available via filter
    $product_addons = apply_filters('apw_woo_product_addons', $product_addons, $product);

    apw_woo_log('Preparing to render product: ' . $product_title);

    /**
     * Hook: apw_woo_before_single_product
     * @param WC_Product $product Current product object
     * @param array $product_addons Product add-ons if available
     */
    do_action('apw_woo_before_single_product', $product, $product_addons);
    ?>

    <main id="main" class="apw-woo-single-product-main">
        <main id="main" class="apw-woo-single-product-main">
        <!-- APW-WOO-TEMPLATE: single-product.php is loaded -->
        <!-- Header Block -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_product_header
             */
            do_action('apw_woo_before_product_header', $product);

            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="fourth-level-page-header"]');
            } else {
                echo '<h1 class="apw-woo-page-title">' . esc_html($product_title) . '</h1>';
            }

            /**
             * Hook: apw_woo_after_product_header
             */
            do_action('apw_woo_after_product_header', $product);
            ?>
        </div>

        <div class="container">
            <div class="row">
                <div class="col apw-woo-content-wrapper">
                    <!-- Product Content Section -->
                    <div class="row apw-woo-row">
                        <div class="col-md-6 apw-woo-product-gallery-col">
                            <?php
                            /**
                             * Hook: apw_woo_before_product_gallery
                             */
                            do_action('apw_woo_before_product_gallery', $product);
                            ?>

                            <div class="apw-woo-product-gallery-wrapper">
                                <?php
                                if (function_exists('woocommerce_show_product_images')) {
                                    woocommerce_show_product_images();
                                } elseif (has_post_thumbnail()) {
                                    echo '<div class="apw-woo-product-image">';
                                    the_post_thumbnail('large');
                                    echo '</div>';
                                }
                                ?>
                            </div>

                            <?php
                            /**
                             * Hook: apw_woo_after_product_gallery
                             */
                            do_action('apw_woo_after_product_gallery', $product);
                            ?>
                        </div>

                        <div class="col-md-6 apw-woo-product-summary-col">
                            <?php
                            /**
                             * Hook: apw_woo_before_product_summary
                             */
                            do_action('apw_woo_before_product_summary', $product);
                            ?>

                            <div class="apw-woo-product-summary">
                                <!-- Product Title -->
                                <h1 class="apw-woo-product-title"><?php echo esc_html($product_title); ?></h1>

                                <!-- Product Price -->
                                <div class="apw-woo-product-price">
                                    <?php echo $product_price; ?>
                                </div>

                                <!-- Product Short Description -->
                                <?php if ($product->get_short_description()) : ?>
                                    <div class="apw-woo-product-short-description">
                                        <?php echo apply_filters('the_content', $product->get_short_description()); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Add to Cart Form with Add-Ons -->
                                <div class="apw-woo-product-add-to-cart-section">
                                    <?php if ($has_addons) : ?>
                                        <?php
                                        /**
                                         * Hook: apw_woo_before_product_addons
                                         * @param array $product_addons Product add-ons
                                         * @param WC_Product $product Current product
                                         */
                                        do_action('apw_woo_before_product_addons', $product_addons, $product);
                                        ?>

                                        <!-- Add-Ons Container - will be populated by WooCommerce Product Add-Ons -->
                                        <div class="apw-woo-product-addons-container">
                                            <!-- The add-ons will be rendered inside the add-to-cart form below -->
                                        </div>

                                        <?php
                                        /**
                                         * Hook: apw_woo_after_product_addons
                                         * @param array $product_addons Product add-ons
                                         * @param WC_Product $product Current product
                                         */
                                        do_action('apw_woo_after_product_addons', $product_addons, $product);
                                        ?>
                                    <?php endif; ?>

                                    <!-- Add To Cart Form -->
                                    <div class="apw-woo-add-to-cart-form">
                                        <?php
                                        // This will render the add-to-cart form WITH the product add-ons
                                        if (function_exists('woocommerce_template_single_add_to_cart')) {
                                            // Add a filter to customize add-ons HTML if needed
                                            add_filter('woocommerce_product_addons_option_html', 'apw_woo_customize_addon_html', 10, 4);

                                            // Output the form
                                            woocommerce_template_single_add_to_cart();

                                            // Remove our filter after use
                                            remove_filter('woocommerce_product_addons_option_html', 'apw_woo_customize_addon_html');
                                        }
                                        ?>
                                    </div>

                                    <?php
                                    /**
                                     * Hook: apw_woo_after_add_to_cart
                                     */
                                    do_action('apw_woo_after_add_to_cart', $product);
                                    ?>
                                </div>
                            </div>

                            <?php
                            /**
                             * Hook: apw_woo_after_product_summary
                             */
                            do_action('apw_woo_after_product_summary', $product);
                            ?>
                        </div>
                    </div>

                    <!-- Product Description -->
                    <?php if ($product->get_description()) : ?>
                        <div class="row apw-woo-row">
                            <div class="col apw-woo-product-description-section">
                                <?php
                                /**
                                 * Hook: apw_woo_before_product_description
                                 */
                                do_action('apw_woo_before_product_description', $product);
                                ?>

                                <div class="apw-woo-product-description">
                                    <h2 class="apw-woo-product-description-title">
                                        <?php esc_html_e('Product Description', 'apw-woo-plugin'); ?>
                                    </h2>
                                    <?php echo apply_filters('the_content', $product->get_description()); ?>
                                </div>

                                <?php
                                /**
                                 * Hook: apw_woo_after_product_description
                                 */
                                do_action('apw_woo_after_product_description', $product);
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- FAQ Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-faq-section-container">
                            <?php
                            /**
                             * Hook: apw_woo_before_product_faqs
                             */
                            do_action('apw_woo_before_product_faqs', $product);

                            // Include the FAQ display partial
                            if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                                $faq_product = $product;
                                include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
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
    /**
     * Hook: apw_woo_after_single_product
     */
    do_action('apw_woo_after_single_product', $product);

else :
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

/**
 * Function to customize the add-on option HTML
 * This is registered as a filter in the template above
 *
 * @param string $html Current HTML
 * @param array $addon Add-on data
 * @param int $i Loop index
 * @param string $value Current value
 * @return string Modified HTML
 */
function apw_woo_customize_addon_html($html, $addon, $i, $value) {
    // Add our custom wrapper class to each add-on for easier styling
    $addon_type = isset($addon['type']) ? $addon['type'] : '';
    $addon_name = isset($addon['name']) ? sanitize_title($addon['name']) : '';

    // Log the add-on type for debugging
    apw_woo_log('Processing add-on: ' . (isset($addon['name']) ? $addon['name'] : 'unnamed') . ' (Type: ' . $addon_type . ')');

    // Wrap the HTML with our custom classes for easier targeting with CSS
    return '<div class="apw-woo-product-addon apw-woo-product-addon-' . esc_attr($addon_type) . ' apw-woo-product-addon-' . esc_attr($addon_name) . '">' . $html . '</div>';
}
?>