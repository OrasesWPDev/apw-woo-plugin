<?php
/**
 * Template for displaying products within a specific category.
 *
 * This template includes:
 * 1. A header block (often managed by a theme shortcode or block).
 * 2. An introductory section displaying the category title and description.
 * 3. A grid displaying products belonging to the current category.
 * 4. An FAQ section relevant to the category.
 *
 * It relies on the $current_category variable being correctly identified early on.
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly to prevent direct URL access.
if (!defined('ABSPATH')) {
    exit;
}

// Log template loading if debug mode is enabled.
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Loading category products display template (category-products-display.php)');
}

/**
 * Include the theme's header template.
 * It's crucial to call this before any HTML output intended for the main body.
 */
get_header();

?>
    <main id="main" class="apw-woo-category-products-main">
        <!-- Debug comment indicating which template file is rendering this main content -->
        <!-- APW-WOO-TEMPLATE: category-products-display.php is loaded -->

        <!-- Header Block -->
        <!-- Typically contains a hero image, page title, and breadcrumbs. -->
        <!-- Often managed by theme settings or a reusable block/shortcode. -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_category_header
             * Allows adding content before the main category header content.
             *
             * @param WP_Term|null $current_category Current category object if available, null otherwise.
             *                                         Note: $current_category might not be fully reliable here yet.
             */
            do_action('apw_woo_before_category_header', get_queried_object()); // Pass queried object for potential use

            // Log header rendering start.
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Rendering category page header section');
            }

            // Attempt to display a reusable block for the header.
            if (shortcode_exists('block')) {
                // Use a specific block ID intended for category pages.
                echo do_shortcode('[block id="third-level-page-header"]');
            } else {
                // Fallback if the '[block]' shortcode isn't available.
                // Display the category title using WordPress's standard function.
                echo '<h1 class="apw-woo-page-title">' . esc_html(single_term_title('', false)) . '</h1>';
            }

            /**
             * Hook: apw_woo_after_category_header
             * Allows adding content after the main category header content.
             *
             * @param WP_Term|null $current_category Current category object if available, null otherwise.
             */
            do_action('apw_woo_after_category_header', get_queried_object()); // Pass queried object for potential use
            ?>
        </div><!-- /.apw-woo-header-block -->

        <!-- Main Content Container -->
        <!-- Uses Flatsome's .container > .row > .col structure for layout consistency -->
        <div class="container">
            <div class="row">
                <div class="col apw-woo-content-wrapper">
                    <?php
                    // --- START: Fetch and Validate Category Object ---
                    // Get the object WordPress has identified for the current query (should be the category term).
                    $current_category = get_queried_object();

                    // Validate that we have a valid WordPress Term object and it's specifically a 'product_cat'.
                    if (!is_a($current_category, 'WP_Term') || $current_category->taxonomy !== 'product_cat') {
                        // Log an error if the validation fails.
                        if (APW_WOO_DEBUG_MODE) {
                            $object_type = is_object($current_category) ? get_class($current_category) : gettype($current_category);
                            apw_woo_log('Category template ERROR: Expected WP_Term for product_cat, but got ' . $object_type . '. Aborting content rendering.', 'error');
                        }
                        // Display a user-friendly error message.
                        echo '<p>' . esc_html__('Error: Could not load category information.', 'apw-woo-plugin') . '</p>';

                        // Close open tags and exit gracefully to prevent broken layout.
                        echo '</div></div></div></main>'; // Close .col, .row, .container, main
                        get_footer(); // Include the footer
                        exit; // Stop script execution
                    }

                    // Log successful validation if in debug mode.
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log('Validated current category: ' . $current_category->name . ' (ID: ' . $current_category->term_id . ') for content area.');
                    }
                    // --- END: Fetch and Validate Category Object ---


                    /**
                     * Hook: apw_woo_before_category_content
                     * Allows adding content right after the main content wrapper starts,
                     * before the category introduction.
                     *
                     * @param WP_Term $current_category The validated current category object.
                     */
                    do_action('apw_woo_before_category_content', $current_category);


                    /* --- START: Category Introduction Section --- */
                    ?>
                    <!-- Category Introduction Section -->
                    <!-- Displays the category title and description. -->
                    <div class="row apw-woo-row"> <?php // Outer row for the section layout ?>
                        <div class="col apw-woo-intro-section"> <?php // Column containing the intro content ?>
                            <?php
                            /**
                             * Hook: apw_woo_before_category_intro
                             * Allows adding content before the category title and description wrapper.
                             *
                             * @param WP_Term $current_category The validated current category object.
                             */
                            do_action('apw_woo_before_category_intro', $current_category);
                            ?>

                            <div class="apw-woo-product-intro">
                                <?php
                                // Set the title using the validated category name. Escape for security.
                                $intro_title = esc_html($current_category->name);
                                ?>
                                <!-- Display the category title as H1 -->
                                <h1 class="apw-woo-section-title">
                                    <?php
                                    // Apply filters to allow modification of the title, then echo.
                                    echo esc_html(apply_filters('apw_woo_category_intro_title', $intro_title, $current_category));
                                    ?>
                                </h1>

                                <?php
                                // Get the category description. Use fallback text if empty.
                                $intro_description = !empty($current_category->description)
                                    ? $current_category->description // Use the existing description
                                    : '<p>' . sprintf(__('This is a placeholder for the %s category description.', 'apw-woo-plugin'), esc_html($intro_title)) . '</p>'; // Generate placeholder
                                ?>
                                <!-- Display the category description -->
                                <div class="apw-woo-section-description">
                                    <?php
                                    // Apply filters, allow specific HTML tags (like <p>, <a>, <strong> etc.), then echo.
                                    echo wp_kses_post(apply_filters('apw_woo_category_intro_description', $intro_description, $current_category));
                                    ?>
                                </div><!-- /.apw-woo-section-description -->
                            </div><!-- /.apw-woo-product-intro -->

                            <?php
                            /**
                             * Hook: apw_woo_after_category_intro
                             * Allows adding content after the category title and description wrapper.
                             *
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
                    ?>
                    <!-- Product Grid Section -->
                    <!-- Displays the products belonging to the current category. -->
                    <div class="row apw-woo-row"> <?php // Outer row for the grid section ?>
                        <div class="col apw-woo-products-section"> <?php // Column containing the product grid ?>
                            <?php
                            /**
                             * Hook: apw_woo_before_products_grid
                             * Allows adding content before the product grid container.
                             *
                             * @param WP_Term $current_category The validated current category object.
                             */
                            do_action('apw_woo_before_products_grid', $current_category);

                            // Log product fetching if in debug mode.
                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Fetching products for category: ' . $current_category->slug);
                            }

                            // Arguments for fetching products.
                            $product_args = [
                                'status' => 'publish', // Only get published products.
                                'limit' => -1, // Get all products in the category.
                                'category' => [$current_category->slug], // Filter by the current category slug.
                                // Add 'orderby' and 'order' if specific sorting is needed, e.g.:
                                // 'orderby' => 'title',
                                // 'order'   => 'ASC',
                            ];

                            // Fetch products using WooCommerce function. Apply filter for customization.
                            $products = apply_filters('apw_woo_category_products', wc_get_products($product_args), $current_category);

                            // Check if any products were found.
                            if (!empty($products)) {
                                // Log product count if in debug mode.
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('Found ' . count($products) . ' products to display in category: ' . $current_category->name);
                                }
                                ?>
                                <!-- Product Grid Container -->
                                <div class="apw-woo-products-grid">
                                    <?php
                                    // Loop through each product.
                                    foreach ($products as $product) {
                                        // Get essential product data for display.
                                        $product_id = $product->get_id();
                                        $product_title = $product->get_name();
                                        $product_link = get_permalink($product_id); // Use for the main card link.
                                        $product_image_id = $product->get_image_id();
                                        $product_image_url = $product_image_id ? wp_get_attachment_url($product_image_id) : wc_placeholder_img_src(); // Fallback to placeholder

                                        // Log individual product rendering.
                                        if (APW_WOO_DEBUG_MODE) {
                                            apw_woo_log('Rendering product item: ' . $product_title . ' (ID: ' . $product_id . ')');
                                        }

                                        /**
                                         * Hook: apw_woo_before_product_item
                                         * Allows adding content before an individual product card starts.
                                         *
                                         * @param WC_Product $product Current product object.
                                         * @param WP_Term $current_category Current category object.
                                         */
                                        do_action('apw_woo_before_product_item', $product, $current_category);
                                        ?>
                                        <!-- Individual Product Item/Card -->
                                        <div class="apw-woo-product-item">
                                            <!-- Make the entire card clickable -->
                                            <a href="<?php echo esc_url($product_link); ?>"
                                               class="apw-woo-product-card-link">

                                                <!-- Product Header Row -->
                                                <div class="apw-card-row apw-woo-product-row">
                                                    <div class="apw-card-column apw-woo-product-header-col">
                                                        <div class="apw-woo-product-header">
                                                            <!-- Product Title -->
                                                            <h4 class="apw-woo-product-title"><?php echo esc_html($product_title); ?></h4>
                                                            <?php // "View Product" button removed from product card as per previous CSS review discussion. ?>
                                                        </div><!-- /.apw-woo-product-header -->
                                                    </div><!-- /.apw-card-column -->
                                                </div><!-- /.apw-card-row -->

                                                <!-- Product Image Row -->
                                                <div class="apw-card-row apw-woo-product-image-row">
                                                    <div class="apw-card-column apw-woo-product-image-col">
                                                        <div class="apw-woo-product-image-wrapper">
                                                            <!-- Product Image -->
                                                            <img src="<?php echo esc_url($product_image_url); ?>"
                                                                 alt="<?php echo esc_attr($product_title); ?>"
                                                                 class="apw-woo-product-image"/>
                                                        </div><!-- /.apw-woo-product-image-wrapper -->
                                                    </div><!-- /.apw-card-column -->
                                                </div><!-- /.apw-card-row -->

                                                <?php
                                                /**
                                                 * Hook: apw_woo_after_product_content
                                                 * Allows adding extra content inside the product card link,
                                                 * e.g., price, quick view button (if styled appropriately).
                                                 *
                                                 * @param WC_Product $product Current product object.
                                                 * @param WP_Term $current_category Current category object.
                                                 */
                                                do_action('apw_woo_after_product_content', $product, $current_category);
                                                ?>
                                            </a> <!-- /.apw-woo-product-card-link -->
                                        </div> <!-- /.apw-woo-product-item -->
                                        <?php
                                        /**
                                         * Hook: apw_woo_after_product_item
                                         * Allows adding content after an individual product card ends.
                                         *
                                         * @param WC_Product $product Current product object.
                                         * @param WP_Term $current_category Current category object.
                                         */
                                        do_action('apw_woo_after_product_item', $product, $current_category);
                                    } // End foreach ($products as $product)
                                    ?>
                                </div> <!-- /.apw-woo-products-grid -->
                                <?php
                            } else { // if (!empty($products))
                                // No products found in this category.
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('No products found in category: ' . $current_category->slug);
                                }

                                /**
                                 * Hook: apw_woo_no_products_found
                                 * Allows replacing or adding to the 'No products found' message.
                                 *
                                 * @param WP_Term $current_category Current category object.
                                 */
                                do_action('apw_woo_no_products_found', $current_category);
                                ?>
                                <!-- No Products Found Message -->
                                <div class="apw-woo-no-products">
                                    <p>
                                        <?php
                                        // Display a translatable message. Allow filtering.
                                        echo esc_html(apply_filters('apw_woo_no_products_text', __('No products found in this category.', 'apw-woo-plugin'), $current_category));
                                        ?>
                                    </p>
                                </div><!-- /.apw-woo-no-products -->
                                <?php
                            } // End if (!empty($products))

                            /**
                             * Hook: apw_woo_after_products_grid
                             * Allows adding content after the product grid container (e.g., pagination if needed).
                             *
                             * @param WP_Term $current_category The validated current category object.
                             */
                            do_action('apw_woo_after_products_grid', $current_category);
                            ?>
                        </div><?php // End .col.apw-woo-products-section ?>
                    </div><?php // End .row.apw-woo-row for product grid ?>
                    <!-- End Product Grid Section -->
                    <?php
                    /* --- END: Product Grid Section --- */


                    /* --- START: FAQ Section --- */
                    ?>
                    <!-- FAQ Section -->
                    <!-- Loads FAQs associated with the current category. -->
                    <div class="row apw-woo-row"> <?php // Outer row for the FAQ section ?>
                        <div class="col apw-woo-faq-section-container"> <?php // Column containing the FAQs ?>
                            <?php
                            /**
                             * Hook: apw_woo_before_category_faqs
                             * Allows adding content before the FAQ partial is included.
                             *
                             * @param WP_Term $current_category The validated current category object.
                             */
                            do_action('apw_woo_before_category_faqs', $current_category);

                            // Log FAQ loading intent.
                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Attempting to load FAQ display partial for category: ' . $current_category->name);
                            }

                            // Path to the FAQ display template partial.
                            $faq_partial_path = APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php';

                            // Check if the partial file exists before including.
                            if (file_exists($faq_partial_path)) {
                                // Make the validated $current_category available to the included partial.
                                // Use apply_filters to allow modification if needed elsewhere.
                                $faq_category = apply_filters('apw_woo_faq_category', $current_category);

                                // Log the category object being passed if in debug mode.
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('Passing category object to faq-display.php: ' . ($faq_category ? $faq_category->name : 'NULL'));
                                }

                                // Include the partial. The partial should use the $faq_category variable.
                                include($faq_partial_path);

                            } else { // if (file_exists($faq_partial_path))
                                // Log an error if the partial is missing.
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('FAQ display partial not found at: ' . $faq_partial_path, 'error');
                                }
                            } // End if (file_exists($faq_partial_path))

                            /**
                             * Hook: apw_woo_after_category_faqs
                             * Allows adding content after the FAQ partial is included.
                             *
                             * @param WP_Term $current_category The validated current category object.
                             */
                            do_action('apw_woo_after_category_faqs', $current_category);
                            ?>
                        </div><?php // End .col.apw-woo-faq-section-container ?>
                    </div><?php // End .row.apw-woo-row for FAQ section ?>
                    <!-- End FAQ Section -->
                    <?php
                    /* --- END: FAQ Section --- */


                    /**
                     * Hook: apw_woo_after_category_content
                     * Allows adding content at the very end of the main content wrapper,
                     * after all standard sections (intro, grid, FAQs).
                     *
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
    // Use a temporary variable to avoid errors if $current_category somehow became invalid after validation.
    $category_name_for_log = (isset($current_category) && is_a($current_category, 'WP_Term')) ? $current_category->name : '[Unknown Category]';
    apw_woo_log('Completed rendering category products template for: ' . $category_name_for_log);
}

/**
 * Include the theme's footer template.
 */
get_footer();
?>