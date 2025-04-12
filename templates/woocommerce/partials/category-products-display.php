<?php
/**
 * Template for displaying products within a specific category.
 *
 * Includes a theme-managed header block, a category introduction (H2 title + description),
 * the product grid, and an FAQ section.
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// --- Pre-Header Validation (Optional but Recommended) ---
$pre_header_category = get_queried_object();
$is_valid_category_page = ($pre_header_category instanceof WP_Term && $pre_header_category->taxonomy === 'product_cat');

if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Loading category products display template (category-products-display.php)');
    if (!$is_valid_category_page) {
        $object_type = is_object($pre_header_category) ? get_class($pre_header_category) : gettype($pre_header_category);
        apw_woo_log('Category template WARNING: Initial check indicates this might not be a valid product category page. Queried object type: ' . $object_type, 'warning');
    } else {
        apw_woo_log('Category template: Initial check confirms a WP_Term object for taxonomy: ' . $pre_header_category->taxonomy);
    }
}
// --- End Pre-Header Validation ---

get_header();
?>
    <main id="main" class="apw-woo-category-products-main">
        <!-- APW-WOO-TEMPLATE: category-products-display.php is loaded -->

        <!-- Header Block -->
        <!-- Outputs the reusable block and attempts to override its title on category pages. -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_category_header
             * @param WP_Term|object|null $pre_header_category The initially queried object.
             */
            do_action('apw_woo_before_category_header', $pre_header_category);

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Rendering category page header section using Block ID: third-level-woo-page-header');
            }

            // Define the target block ID (the duplicated block for Woo pages).
            $target_block_id = 'third-level-woo-page-header';

            // Check if we are on a product category page and the block shortcode exists.
            if (is_product_category() && shortcode_exists('block')) {

                // --- Capture and Modify Block Output ---
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Attempting to capture and modify block output for ID: ' . $target_block_id);
                }

                // Start output buffering to capture the shortcode output.
                ob_start();
                echo do_shortcode('[block id="' . $target_block_id . '"]');
                $block_html = ob_get_clean();

                // Get the category object again *safely* inside this scope.
                // Use get_queried_object() here as $current_category might not be set yet depending on hook order.
                $header_category_object = get_queried_object();

                // Ensure it's the correct object type before proceeding.
                if (is_a($header_category_object, 'WP_Term') && isset($header_category_object->taxonomy) && $header_category_object->taxonomy === 'product_cat' && isset($header_category_object->name)) {
                    $correct_title = $header_category_object->name;

                    // Define the regex pattern to find the H1 with class containing "entry-title".
                    $pattern = '/(<h1[^>]*class="[^"]*entry-title[^"]*"[^>]*>)(.*?)(<\/h1>)/is';

                    // Perform the replacement.
                    $modified_block_html = preg_replace($pattern, '$1' . esc_html($correct_title) . '$3', $block_html, 1);

                    // Log replacement status.
                    if (APW_WOO_DEBUG_MODE) {
                        if ($modified_block_html !== null && $modified_block_html !== $block_html) {
                            apw_woo_log('Successfully replaced page header title in block output with: "' . esc_html($correct_title) . '"');
                        } elseif ($modified_block_html === null) {
                            apw_woo_log('ERROR: preg_replace failed during title override for block ID ' . $target_block_id . '.', 'error');
                            $modified_block_html = $block_html;
                        } else {
                            apw_woo_log('WARNING: Could not find H1 pattern (class="entry-title") in block ID ' . $target_block_id . ' to replace title. Outputting original block HTML.', 'warning');
                            $modified_block_html = $block_html;
                        }
                    }
                    // Output the modified (or original if failed) HTML.
                    echo wp_kses_post($modified_block_html);

                } else {
                    // Fallback if the queried object isn't the expected category term.
                    if (APW_WOO_DEBUG_MODE) {
                        $header_object_type = is_object($header_category_object) ? get_class($header_category_object) : gettype($header_category_object);
                        apw_woo_log('ERROR: Cannot override block title because get_queried_object() returned invalid type (' . $header_object_type . ') within header block section. Outputting original block.', 'error');
                    }
                    echo $block_html; // Output the original captured HTML
                }
                // --- End Capture and Modify ---

            } elseif (shortcode_exists('block')) {
                // If not a product category, output the block unmodified.
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Not a product category page. Outputting block ID ' . $target_block_id . ' without title modification.');
                }
                echo do_shortcode('[block id="' . $target_block_id . '"]');
            } else {
                // Fallback if '[block]' shortcode doesn't exist.
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('WARNING: Shortcode [block] does not exist. Falling back to standard title.', 'warning');
                }
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
                    // --- Fetch and Validate Category Object for Content Area ---
                    // This remains crucial to ensure the rest of the template has the correct context.
                    $current_category = get_queried_object();
                    if (!is_a($current_category, 'WP_Term') || !isset($current_category->taxonomy) || $current_category->taxonomy !== 'product_cat') {
                        if (APW_WOO_DEBUG_MODE) {
                            $object_type = is_object($current_category) ? get_class($current_category) : gettype($current_category);
                            apw_woo_log('Category template CONTENT ERROR: Expected WP_Term for product_cat, but got ' . $object_type . '. Aborting content rendering.', 'error');
                        }
                        echo '<p>' . esc_html__('Error: Could not load category information.', 'apw-woo-plugin') . '</p>';
                        echo '</div></div></div></main>';
                        get_footer();
                        exit;
                    }
                    if (APW_WOO_DEBUG_MODE) {
                        $category_name_for_log = isset($current_category->name) ? $current_category->name : '[Name property missing]';
                        $category_id_for_log = isset($current_category->term_id) ? $current_category->term_id : '[ID property missing]';
                        apw_woo_log('Validated current category for content area: ' . $category_name_for_log . ' (ID: ' . $category_id_for_log . ')');
                    }
                    // --- END Fetch and Validate Category Object ---

                    /**
                     * Hook: apw_woo_before_category_content
                     */
                    do_action('apw_woo_before_category_content', $current_category);


                    /* --- START: Category Introduction Section --- */
                    ?>
                    <!-- Category Introduction Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-intro-section">
                            <?php
                            do_action('apw_woo_before_category_intro', $current_category);
                            if (APW_WOO_DEBUG_MODE && isset($current_category->name)) {
                                apw_woo_log('Rendering category introduction section for: ' . $current_category->name);
                            }
                            ?>
                            <div class="apw-woo-product-intro">
                                <?php
                                // Use the validated category name for the title.
                                $intro_title = isset($current_category->name) ? esc_html($current_category->name) : __('Category', 'apw-woo-plugin');
                                ?>
                                <!-- Display the category title as H2 -->
                                <h2 class="apw-woo-section-title"> <?php // CHANGED TO H2 ?>
                                    <?php echo esc_html(apply_filters('apw_woo_category_intro_title', $intro_title, $current_category)); ?>
                                </h2>

                                <?php
                                // Get description
                                $intro_description = !empty($current_category->description)
                                    ? $current_category->description
                                    : '<p>' . sprintf(__('Explore our range of %s products.', 'apw-woo-plugin'), esc_html($intro_title)) . '</p>';
                                ?>
                                <!-- Display the category description -->
                                <div class="apw-woo-section-description">
                                    <?php echo wp_kses_post(apply_filters('apw_woo_category_intro_description', $intro_description, $current_category)); ?>
                                </div><!-- /.apw-woo-section-description -->
                            </div><!-- /.apw-woo-product-intro -->
                            <?php
                            do_action('apw_woo_after_category_intro', $current_category);
                            ?>
                        </div> <?php // End .col.apw-woo-intro-section ?>
                    </div> <?php // End .row.apw-woo-row for intro section ?>
                    <!-- End Category Introduction Section -->
                    <?php
                    /* --- END: Category Introduction Section --- */


                    /* --- START: Product Grid Section --- */
                    // Uses the $current_category validated above.
                    ?>
                    <!-- Product Grid Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-products-section">
                            <?php
                            do_action('apw_woo_before_products_grid', $current_category);

                            // Fetch products.
                            if (APW_WOO_DEBUG_MODE && isset($current_category->slug)) {
                                apw_woo_log('Fetching products for category slug: ' . $current_category->slug);
                            }
                            $product_args = [
                                'status' => 'publish', 'limit' => -1,
                                'category' => isset($current_category->slug) ? [$current_category->slug] : [],
                            ];
                            $products = apply_filters('apw_woo_category_products', wc_get_products($product_args), $current_category);

                            // Product Loop.
                            if (!empty($products)) {
                                if (APW_WOO_DEBUG_MODE && isset($current_category->name)) {
                                    apw_woo_log('Found ' . count($products) . ' products to display in category: ' . $current_category->name);
                                }
                                ?>
                                <div class="apw-woo-products-grid">
                                    <?php
                                    foreach ($products as $product) {
                                        $product_id = $product->get_id();
                                        $product_title = $product->get_name();
                                        $product_link = get_permalink($product_id);
                                        $product_image_id = $product->get_image_id();
                                        $product_image_url = $product_image_id ? wp_get_attachment_url($product_image_id) : wc_placeholder_img_src();

                                        if (APW_WOO_DEBUG_MODE) {
                                            apw_woo_log('Rendering product item: ' . $product_title . ' (ID: ' . $product_id . ')');
                                        }
                                        do_action('apw_woo_before_product_item', $product, $current_category);
                                        ?>
                                        <div class="apw-woo-product-item">
                                            <a href="<?php echo esc_url($product_link); ?>"
                                               class="apw-woo-product-card-link">
                                                <div class="apw-card-row apw-woo-product-row">
                                                    <div class="apw-card-column apw-woo-product-header-col">
                                                        <div class="apw-woo-product-header">
                                                            <h4 class="apw-woo-product-title"><?php echo esc_html($product_title); ?></h4>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="apw-card-row apw-woo-product-image-row">
                                                    <div class="apw-card-column apw-woo-product-image-col">
                                                        <div class="apw-woo-product-image-wrapper">
                                                            <img src="<?php echo esc_url($product_image_url); ?>"
                                                                 alt="<?php echo esc_attr($product_title); ?>"
                                                                 class="apw-woo-product-image"/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php do_action('apw_woo_after_product_content', $product, $current_category); ?>
                                            </a>
                                        </div>
                                        <?php
                                        do_action('apw_woo_after_product_item', $product, $current_category);
                                    } // End foreach
                                    ?>
                                </div> <!-- /.apw-woo-products-grid -->
                                <?php
                            } else { // No products found
                                if (APW_WOO_DEBUG_MODE && isset($current_category->slug)) {
                                    apw_woo_log('No products found in category slug: ' . $current_category->slug);
                                }
                                do_action('apw_woo_no_products_found', $current_category);
                                ?>
                                <div class="apw-woo-no-products">
                                    <p><?php echo esc_html(apply_filters('apw_woo_no_products_text', __('No products were found matching your selection.', 'woocommerce'), $current_category)); ?></p>
                                </div>
                                <?php
                            } // End if/else (!empty($products))

                            do_action('apw_woo_after_products_grid', $current_category);
                            ?>
                        </div><!-- /.col .apw-woo-products-section -->
                    </div><!-- /.row .apw-woo-row (Product Grid) -->
                    <!-- End Product Grid Section -->
                    <?php
                    /* --- END: Product Grid Section --- */


                    /* --- START: FAQ Section --- */
                    // Uses the $current_category validated above.
                    ?>
                    <!-- FAQ Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-faq-section-container">
                            <?php
                            do_action('apw_woo_before_category_faqs', $current_category);
                            if (APW_WOO_DEBUG_MODE && isset($current_category->name)) {
                                apw_woo_log('Attempting to load FAQ display partial for category: ' . $current_category->name);
                            }
                            $faq_partial_path = APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php';
                            if (file_exists($faq_partial_path)) {
                                $faq_category = apply_filters('apw_woo_faq_category', $current_category);
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('Passing category object to faq-display.php: ' . ($faq_category && isset($faq_category->name) ? $faq_category->name : 'NULL or Name Missing'));
                                }
                                include($faq_partial_path);
                            } else {
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('FAQ display partial not found at: ' . $faq_partial_path, 'error');
                                }
                            }
                            do_action('apw_woo_after_category_faqs', $current_category);
                            ?>
                        </div><!-- /.col .apw-woo-faq-section-container -->
                    </div><!-- /.row .apw-woo-row (FAQ) -->
                    <!-- End FAQ Section -->
                    <?php
                    /* --- END: FAQ Section --- */


                    /**
                     * Hook: apw_woo_after_category_content
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
    $category_name_for_log = (isset($current_category) && is_a($current_category, 'WP_Term') && isset($current_category->name)) ? $current_category->name : '[Unknown Category - Check Validation Log]';
    apw_woo_log('Completed rendering category products template for: ' . $category_name_for_log);
}

get_footer();
?>