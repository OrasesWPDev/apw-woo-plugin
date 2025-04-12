<?php
/**
 * Template for displaying products within a specific category.
 *
 * This version includes an added introductory section (Title + Description)
 * based on the current category, followed by the product grid and FAQs.
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Log template loading if debug mode is enabled.
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Loading category products display template (category-products-display.php)');
}

// --- Pre-Header Validation (Optional but Recommended) ---
// Attempt to get the queried object early.
$pre_header_category = get_queried_object();
$is_valid_category_page = ($pre_header_category instanceof WP_Term && $pre_header_category->taxonomy === 'product_cat');

// Log initial validation status.
if (APW_WOO_DEBUG_MODE) {
    if (!$is_valid_category_page) {
        $object_type = is_object($pre_header_category) ? get_class($pre_header_category) : gettype($pre_header_category);
        apw_woo_log('Category template WARNING: Initial check indicates this might not be a valid product category page. Queried object type: ' . $object_type, 'warning');
    } else {
        apw_woo_log('Category template: Initial check confirms a WP_Term object for taxonomy: ' . $pre_header_category->taxonomy);
    }
}
// --- End Pre-Header Validation ---

/**
 * Include the theme's header template.
 */
get_header();

?>
<main id="main" class="apw-woo-category-products-main">
    <!-- Debug comment indicating which template file is rendering this main content -->
    <!-- APW-WOO-TEMPLATE: category-products-display.php is loaded -->

    <!-- Header Block -->
    <!-- Managed by theme settings or shortcode. -->
    <div class="apw-woo-section-wrapper apw-woo-header-block">
        <?php
        /**
         * Hook: apw_woo_before_category_header
         * @param WP_Term|object|null $pre_header_category The initially queried object.
         */
        do_action('apw_woo_before_category_header', $pre_header_category);

        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Rendering category page header section');
        }

        // Display reusable block for the header.
        if (shortcode_exists('block')) {
            echo do_shortcode('[block id="third-level-woo-page-header"]');
        } else {
            // Fallback: Display category title using standard WordPress function.
            echo '<h1 class="apw-woo-page-title">' . esc_html(single_term_title('', false)) . '</h1>';
        }

        /**
         * Hook: apw_woo_after_category_header
         * @param WP_Term|object|null $pre_header_category The initially queried object.
         */
        do_action('apw_woo_after_category_header', $pre_header_category);
        ?>
    </div><!-- /.apw-woo-header-block -->

    <!-- Main Content Container -->
    <div class="container">
        <div class="row">
            <div class="col apw-woo-content-wrapper">
                <?php
                // --- START: Fetch and Validate Category Object for Content ---
                // Get the object WordPress identifies for the current page. This is critical.
                $current_category = get_queried_object();

                // Make a backup copy of the category object to prevent it from being overwritten
                $original_category = $current_category;

                // Validate that we have a WP_Term object for 'product_cat'.
                if (!is_a($current_category, 'WP_Term') || !isset($current_category->taxonomy) || $current_category->taxonomy !== 'product_cat') {
                    if (APW_WOO_DEBUG_MODE) {
                        $object_type = is_object($current_category) ? get_class($current_category) : gettype($current_category);
                        apw_woo_log('Category template CONTENT ERROR: Expected WP_Term for product_cat, but got ' . $object_type . '. Cannot display content properly.', 'error');
                    }
                    // Display error and exit.
                    echo '<p>' . esc_html__('Error: Could not load category information.', 'apw-woo-plugin') . '</p>';
                    echo '</div></div></div></main>';
                    get_footer();
                    exit;
                }

                // Log successful validation.
                if (APW_WOO_DEBUG_MODE) {
                    // Check if name property exists before logging
                    $category_name_for_log = isset($current_category->name) ? $current_category->name : '[Name property missing]';
                    $category_id_for_log = isset($current_category->term_id) ? $current_category->term_id : '[ID property missing]';
                    apw_woo_log('Validated current category for content area: ' . $category_name_for_log . ' (ID: ' . $category_id_for_log . ')');
                }
                // --- END: Fetch and Validate Category Object for Content ---


                /**
                 * Hook: apw_woo_before_category_content
                 * @param WP_Term $current_category The validated current category object.
                 */
                do_action('apw_woo_before_category_content', $current_category);


                /* --- START: Category Introduction Section --- */
                ?>
                <!-- Category Introduction Section -->
                <div class="row apw-woo-row">
                    <div class="col apw-woo-intro-section">
                        <?php
                        /**
                         * Hook: apw_woo_before_category_intro
                         * @param WP_Term $current_category The validated current category object.
                         */
                        do_action('apw_woo_before_category_intro', $current_category);

                        if (APW_WOO_DEBUG_MODE) {
                            apw_woo_log('Rendering category introduction section for: ' . $current_category->name);
                        }
                        ?>
                        <div class="apw-woo-product-intro">
                            <?php
                            // IMPORTANT: Store category name in a variable before any product loops run
                            // Use the validated category name for the title.
                            $intro_title = esc_html($current_category->name);

                            // Store category description before any product loops run
                            $intro_description = !empty($current_category->description)
                                ? $current_category->description
                                : '<p>' . sprintf(__('Explore our range of %s products.', 'apw-woo-plugin'), esc_html($intro_title)) . '</p>';
                            ?>
                            <!-- Display the category title as H1 -->
                            <h1 class="apw-woo-section-title">
                                <?php echo esc_html(apply_filters('apw_woo_category_intro_title', $intro_title, $current_category)); ?>
                            </h1>

                            <?php
                            // Use the previously stored description
                            ?>
                            <!-- Display the category description -->
                            <div class="apw-woo-section-description">
                                <?php echo wp_kses_post(apply_filters('apw_woo_category_intro_description', $intro_description, $current_category)); ?>
                            </div><!-- /.apw-woo-section-description -->
                        </div><!-- /.apw-woo-product-intro -->
                        <?php
                        /**
                         * Hook: apw_woo_after_category_intro
                         * @param WP_Term $current_category The validated current category object.
                         */
                        do_action('apw_woo_after_category_intro', $current_category);
                        ?>
                    </div> <?php // End .col.apw-woo-intro-section ?>
                </div> <?php // End .row.apw-woo-row for intro section ?>
                <!-- End Category Introduction Section -->
                <?php
                /* --- END: Category Introduction Section --- */


                /* --- START: Product Grid Section --- */
                // **IMPORTANT**: This section uses the SAME $current_category variable validated above.
                // Do NOT call get_queried_object() again here.
                ?>
                <!-- Product Grid Section -->
                <div class="row apw-woo-row">
                    <div class="col apw-woo-products-section">
                        <?php
                        /**
                         * Hook: apw_woo_before_products_grid
                         * @param WP_Term $current_category The validated current category object.
                         */
                        do_action('apw_woo_before_products_grid', $current_category);

                        // Log product fetching.
                        if (APW_WOO_DEBUG_MODE) {
                            // Check slug property exists
                            $category_slug_for_log = isset($current_category->slug) ? $current_category->slug : '[Slug property missing]';
                            apw_woo_log('Fetching products for category slug: ' . $category_slug_for_log);
                        }

                        // Before fetching products, ensure we're still using the original category
                        // This prevents any hooks from changing our category object
                        $current_category = $original_category;

                        // Arguments for fetching products.
                        $product_args = [
                            'status' => 'publish',
                            'limit' => -1,
                            // Use the slug from the validated category object.
                            'category' => isset($current_category->slug) ? [$current_category->slug] : [],
                        ];

                        // Fetch products.
                        $products = apply_filters('apw_woo_category_products', wc_get_products($product_args), $current_category);

                        // Check if products were found.
                        if (!empty($products)) {
                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Found ' . count($products) . ' products to display in category: ' . $current_category->name);
                            }
                            ?>
                            <!-- Product Grid Container -->
                            <div class="apw-woo-products-grid">
                                <?php
                                // Loop through products.
                                foreach ($products as $product) {
                                    // Save the current global post/product before processing this item
                                    $original_post = $GLOBALS['post'] ?? null;
                                    $original_product = $GLOBALS['product'] ?? null;

                                    // Get product data.
                                    $product_id = $product->get_id();
                                    $product_title = $product->get_name();
                                    $product_link = get_permalink($product_id);
                                    $product_image_id = $product->get_image_id();
                                    $product_image_url = $product_image_id ? wp_get_attachment_url($product_image_id) : wc_placeholder_img_src(); // Use placeholder if no image

                                    if (APW_WOO_DEBUG_MODE) {
                                        apw_woo_log('Rendering product item: ' . $product_title . ' (ID: ' . $product_id . ')');
                                    }

                                    /**
                                     * Hook: apw_woo_before_product_item
                                     * @param WC_Product $product Current product object.
                                     * @param WP_Term $current_category Current category object.
                                     */
                                    do_action('apw_woo_before_product_item', $product, $current_category);
                                    ?>
                                    <!-- Individual Product Item -->
                                    <div class="apw-woo-product-item">
                                        <a href="<?php echo esc_url($product_link); ?>"
                                           class="apw-woo-product-card-link">
                                            <!-- Product Header Row -->
                                            <div class="apw-card-row apw-woo-product-row"> <?php // Using updated CSS structure ?>
                                                <div class="apw-card-column apw-woo-product-header-col"> <?php // Using updated CSS structure ?>
                                                    <div class="apw-woo-product-header">
                                                        <h4 class="apw-woo-product-title"><?php echo esc_html($product_title); ?></h4>
                                                        <?php // Button removed as per your original code structure in this section ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Product Image Row -->
                                            <div class="apw-card-row apw-woo-product-image-row"> <?php // Using updated CSS structure ?>
                                                <div class="apw-card-column apw-woo-product-image-col"> <?php // Using updated CSS structure ?>
                                                    <div class="apw-woo-product-image-wrapper">
                                                        <img src="<?php echo esc_url($product_image_url); ?>"
                                                             alt="<?php echo esc_attr($product_title); ?>"
                                                             class="apw-woo-product-image"/>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                            /**
                                             * Hook: apw_woo_after_product_content
                                             * @param WC_Product $product Current product object.
                                             * @param WP_Term $current_category Current category object.
                                             */
                                            do_action('apw_woo_after_product_content', $product, $current_category);
                                            ?>
                                        </a> <!-- Closing the wrapper link -->
                                    </div> <!-- /.apw-woo-product-item -->
                                    <?php
                                    /**
                                     * Hook: apw_woo_after_product_item
                                     * @param WC_Product $product Current product object.
                                     * @param WP_Term $current_category Current category object.
                                     */
                                    do_action('apw_woo_after_product_item', $product, $current_category);

                                    // Restore the original post/product after processing this item
                                    if ($original_post) {
                                        $GLOBALS['post'] = $original_post;
                                    }
                                    if ($original_product) {
                                        $GLOBALS['product'] = $original_product;
                                    }
                                } // End foreach
                                ?>
                            </div> <!-- /.apw-woo-products-grid -->
                            <?php
                        } else { // No products found
                            if (APW_WOO_DEBUG_MODE) {
                                // Check slug property exists
                                $category_slug_for_log = isset($current_category->slug) ? $current_category->slug : '[Slug property missing]';
                                apw_woo_log('No products found in category slug: ' . $category_slug_for_log);
                            }
                            /**
                             * Hook: apw_woo_no_products_found
                             * @param WP_Term $current_category Current category object.
                             */
                            do_action('apw_woo_no_products_found', $current_category);
                            ?>
                            <!-- No Products Found Message -->
                            <div class="apw-woo-no-products">
                                <p><?php echo esc_html(apply_filters('apw_woo_no_products_text', __('No products were found matching your selection.', 'woocommerce'), $current_category)); ?></p>
                            </div>
                            <?php
                        } // End if/else (!empty($products))

                        /**
                         * Hook: apw_woo_after_products_grid
                         * @param WP_Term $current_category The validated current category object.
                         */
                        do_action('apw_woo_after_products_grid', $current_category);
                        ?>
                    </div><!-- /.col .apw-woo-products-section -->
                </div><!-- /.row .apw-woo-row (Product Grid) -->
                <!-- End Product Grid Section -->
                <?php
                /* --- END: Product Grid Section --- */


                /* --- START: FAQ Section --- */
                ?>
                <!-- FAQ Section -->
                <div class="row apw-woo-row">
                    <div class="col apw-woo-faq-section-container">
                        <?php
                        /**
                         * Hook: apw_woo_before_category_faqs
                         * @param WP_Term $current_category The validated current category object.
                         */
                        do_action('apw_woo_before_category_faqs', $current_category);

                        if (APW_WOO_DEBUG_MODE) {
                            apw_woo_log('Attempting to load FAQ display partial for category: ' . $current_category->name);
                        }

                        // Path to the FAQ partial.
                        $faq_partial_path = APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php';

                        // Include partial if it exists.
                        if (file_exists($faq_partial_path)) {
                            // Set variable for the partial (it expects $faq_category).
                            $faq_category = apply_filters('apw_woo_faq_category', $current_category);

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Passing category object to faq-display.php: ' . ($faq_category ? $faq_category->name : 'NULL'));
                            }
                            include($faq_partial_path); // Include the partial
                        } else {
                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('FAQ display partial not found at: ' . $faq_partial_path, 'error');
                            }
                        }

                        /**
                         * Hook: apw_woo_after_category_faqs
                         * @param WP_Term $current_category The validated current category object.
                         */
                        do_action('apw_woo_after_category_faqs', $current_category);
                        ?>
                    </div><!-- /.col .apw-woo-faq-section-container -->
                </div><!-- /.row .apw-woo-row (FAQ) -->
                <!-- End FAQ Section -->
                <?php
                /* --- END: FAQ Section --- */


                /**
                 * Hook: apw_woo_after_category_content
                 * @param WP_Term $current_category The validated current category object.
                 */
                do_action('apw_woo_after_category_content', $current_category);
                ?>
            </div> <!-- /.col.apw-woo-content-wrapper -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</main><!-- /#main -->
<?php

// Log template completion.
if (APW_WOO_DEBUG_MODE) {
    // Safely get name for logging.
    $category_name_for_log = (isset($current_category) && is_a($current_category, 'WP_Term') && isset($current_category->name)) ? $current_category->name : '[Unknown Category - Check Validation Log]';
    apw_woo_log('Completed rendering category products template for: ' . $category_name_for_log);
}

/**
 * Include the theme's footer template.
 */
get_footer();
?>
