<?php
/**
 * Template for displaying products within a specific category
 *
 * @package APW_Woo_Plugin
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Loading category products display template');
}

// Add defensive code here - before get_header()
// Ensure we have a valid category object
if (!isset($current_category) || !is_object($current_category)) {
    $current_category = get_queried_object();
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Category template: Valid $current_category not provided, fetching from queried object');
    }

    // Additional verification that we got a valid term
    if (!is_a($current_category, 'WP_Term') || $current_category->taxonomy !== 'product_cat') {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Category template WARNING: Not on a valid product category page');
        }
    }
}

get_header();
?>
    <main id="main" class="apw-woo-category-products-main">
        <!-- APW-WOO-TEMPLATE: category-products-display.php is loaded -->

        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_category_header
             * @param WP_Term $current_category Current category object
             */
            do_action('apw_woo_before_category_header', $current_category);

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Rendering category page header');
            }

            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="third-level-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                echo '<h1 class="apw-woo-page-title">' . esc_html(single_term_title('', false)) . '</h1>';
            }

            /**
             * Hook: apw_woo_after_category_header
             * @param WP_Term $current_category Current category object
             */
            do_action('apw_woo_after_category_header', $current_category);
            ?>
        </div>

        <!-- Use Flatsome's container while keeping our plugin-specific classes -->
        <div class="container">
            <div class="row">
                <div class="col apw-woo-content-wrapper">
                    <?php
                    /**
                     * Hook: apw_woo_before_category_content
                     * @param WP_Term $current_category Current category object
                     */
                    do_action('apw_woo_before_category_content', $current_category);

                    // Get the current category
                    $current_category = get_queried_object();

                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log('Displaying products for category: ' . $current_category->name);
                    }
                    ?>

                    <!-- Product Grid Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-products-section">
                            <?php
                            /**
                             * Hook: apw_woo_before_products_grid
                             * @param WP_Term $current_category Current category object
                             */
                            do_action('apw_woo_before_products_grid', $current_category);

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Fetching products for category: ' . $current_category->slug);
                            }

                            // Get products in this category
                            $products = apply_filters('apw_woo_category_products', wc_get_products([
                                'status' => 'publish',
                                'limit' => -1,
                                'category' => [$current_category->slug],
                            ]), $current_category);

                            if (!empty($products)) {
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('Found ' . count($products) . ' products to display');
                                }
                                ?>

                                <div class="apw-woo-products-grid">
                                    <?php
                                    foreach ($products as $product) {
                                        // Get product data
                                        $product_id = $product->get_id();
                                        $product_title = $product->get_name();
                                        $product_link = get_permalink($product_id);
                                        $product_image_id = $product->get_image_id();
                                        $product_image = wp_get_attachment_url($product_image_id);

                                        if (APW_WOO_DEBUG_MODE) {
                                            apw_woo_log('Rendering product: ' . $product_title);
                                        }

                                        /**
                                         * Hook: apw_woo_before_product_item
                                         * @param WC_Product $product Current product object
                                         * @param WP_Term $current_category Current category object
                                         */
                                        do_action('apw_woo_before_product_item', $product, $current_category);
                                        ?>

                                        <!-- Individual Product Item -->
                                        <div class="apw-woo-product-item">
                                            <div class="row apw-woo-product-row">
                                                <div class="col apw-woo-product-header-col">
                                                    <!-- Product Header: Title and View Product Button -->
                                                    <div class="apw-woo-product-header">
                                                        <h1 class="apw-woo-product-title"><?php echo esc_html($product_title); ?></h1>
                                                        <a href="<?php echo esc_url($product_link); ?>" class="apw-woo-view-product-button">
                                                            <?php echo esc_html(apply_filters('apw_woo_view_product_text', __('View Product', 'apw-woo-plugin'), $product)); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row apw-woo-product-image-row">
                                                <div class="col apw-woo-product-image-col">
                                                    <!-- Product Image Container -->
                                                    <div class="apw-woo-product-image-wrapper">
                                                        <a href="<?php echo esc_url($product_link); ?>" class="apw-woo-product-image-link">
                                                            <img src="<?php echo esc_url($product_image); ?>"
                                                                 alt="<?php echo esc_attr($product_title); ?>"
                                                                 class="apw-woo-product-image" />
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php
                                            /**
                                             * Hook: apw_woo_after_product_content
                                             * @param WC_Product $product Current product object
                                             * @param WP_Term $current_category Current category object
                                             */
                                            do_action('apw_woo_after_product_content', $product, $current_category);
                                            ?>
                                        </div>

                                        <?php
                                        /**
                                         * Hook: apw_woo_after_product_item
                                         * @param WC_Product $product Current product object
                                         * @param WP_Term $current_category Current category object
                                         */
                                        do_action('apw_woo_after_product_item', $product, $current_category);
                                    }
                                    ?>
                                </div>

                                <?php
                            } else {
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('No products found in category: ' . $current_category->slug);
                                }

                                /**
                                 * Hook: apw_woo_no_products_found
                                 * @param WP_Term $current_category Current category object
                                 */
                                do_action('apw_woo_no_products_found', $current_category);
                                ?>

                                <!-- No Products Found Message -->
                                <div class="apw-woo-no-products">
                                    <p><?php echo esc_html(apply_filters('apw_woo_no_products_text', __('No products found in this category.', 'apw-woo-plugin'), $current_category)); ?></p>
                                </div>

                                <?php
                            }

                            /**
                             * Hook: apw_woo_after_products_grid
                             * @param WP_Term $current_category Current category object
                             */
                            do_action('apw_woo_after_products_grid', $current_category);
                            ?>
                        </div>
                    </div>

                    <!-- FAQ Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-faq-section-container">
                            <?php
                            /**
                             * Hook: apw_woo_before_category_faqs
                             * @param WP_Term $current_category Current category object
                             */
                            do_action('apw_woo_before_category_faqs', $current_category);

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Loading FAQ display for category: ' . $current_category->name);
                            }

                            // Include the FAQ display partial, passing the current category
                            if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                                // Set the category object that will be accessible in the included file
                                if (!isset($current_category) || !is_object($current_category)) {
                                    if (APW_WOO_DEBUG_MODE) {
                                        apw_woo_log('ERROR: Invalid category object passed to FAQ display');
                                    }
                                    $faq_category = null;
                                } else {
                                    $faq_category = apply_filters('apw_woo_faq_category', $current_category);
                                    if (APW_WOO_DEBUG_MODE) {
                                        apw_woo_log('Passing category to FAQ display: ' . $faq_category->name);
                                    }
                                }
                                include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
                            } else {
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('FAQ display partial not found');
                                }
                            }

                            /**
                             * Hook: apw_woo_after_category_faqs
                             * @param WP_Term $current_category Current category object
                             */
                            do_action('apw_woo_after_category_faqs', $current_category);
                            ?>
                        </div>
                    </div>

                    <?php
                    /**
                     * Hook: apw_woo_after_category_content
                     * @param WP_Term $current_category Current category object
                     */
                    do_action('apw_woo_after_category_content', $current_category);
                    ?>
                </div>
            </div>
        </div>
    </main>
<?php
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Completed rendering category products template for: ' . $current_category->name);
}

get_footer();
?>