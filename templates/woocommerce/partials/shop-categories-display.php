<?php
/**
 * Template for displaying product categories on the shop page
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Loading shop categories display template');
}

get_header();
?>
    <main id="main" class="apw-woo-shop-categories-main">
        <!-- APW-WOO-TEMPLATE: shop-categories-display.php is loaded -->

        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_shop_header
             */
            do_action('apw_woo_before_shop_header');

            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Rendering shop page header');
            }

            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="second-level-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                echo '<h1 class="apw-woo-page-title">' . esc_html(woocommerce_page_title(false)) . '</h1>';
            }

            /**
             * Hook: apw_woo_after_shop_header
             */
            do_action('apw_woo_after_shop_header');
            ?>
        </div>

        <!-- Use Flatsome's container while keeping our plugin-specific classes -->
        <div class="container">
            <div class="row">
                <div class="col apw-woo-content-wrapper">
                    <?php
                    /**
                     * Hook: apw_woo_before_shop_content
                     */
                    do_action('apw_woo_before_shop_content');
                    ?>

                    <!-- Shop Introduction Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-intro-section">
                            <?php
                            /**
                             * Hook: apw_woo_before_shop_intro
                             */
                            do_action('apw_woo_before_shop_intro');
                            ?>

                            <div class="apw-woo-product-intro">
                                <h1 class="apw-woo-section-title"><?php echo esc_html(apply_filters('apw_woo_shop_intro_title', __('Our Products', 'apw-woo-plugin'))); ?></h1>
                                <div class="apw-woo-section-description">
                                    <?php echo wp_kses_post(apply_filters('apw_woo_shop_intro_description', '<p>' . __('This is a placeholder paragraph that can be edited later. It will provide an introduction to the product categories offered by the company.', 'apw-woo-plugin') . '</p>')); ?>
                                </div>
                            </div>

                            <?php
                            /**
                             * Hook: apw_woo_after_shop_intro
                             */
                            do_action('apw_woo_after_shop_intro');
                            ?>
                        </div>
                    </div>

                    <!-- Product Categories Grid Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-categories-section">
                            <?php
                            /**
                             * Hook: apw_woo_before_categories_grid
                             */
                            do_action('apw_woo_before_categories_grid');

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Fetching product categories for shop page');
                            }

                            // Get product categories
                            $product_categories = apply_filters('apw_woo_shop_categories', get_terms([
                                'taxonomy'   => 'product_cat',
                                'hide_empty' => true,
                                'parent'     => 0, // Get only top-level categories
                            ]));

                            if (!empty($product_categories) && !is_wp_error($product_categories)) {
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('Found ' . count($product_categories) . ' product categories to display');
                                }
                                ?>

                                <div class="apw-woo-categories-grid">
                                    <?php
                                    foreach ($product_categories as $category) {
                                        // Skip the "Uncategorized" category
                                        if (apply_filters('apw_woo_skip_uncategorized', $category->slug === 'uncategorized', $category)) {
                                            if (APW_WOO_DEBUG_MODE) {
                                                apw_woo_log('Skipping uncategorized category');
                                            }
                                            continue;
                                        }

                                        if (APW_WOO_DEBUG_MODE) {
                                            apw_woo_log('Rendering category: ' . $category->name);
                                        }

                                        // Get category image
                                        $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
                                        $image = wp_get_attachment_url($thumbnail_id);

                                        // Get category link
                                        $category_link = get_term_link($category);

                                        /**
                                         * Hook: apw_woo_before_category_item
                                         * @param WP_Term $category Current category object
                                         */
                                        do_action('apw_woo_before_category_item', $category);
                                        ?>

                                        <!-- Individual Category Item -->
                                        <div class="apw-woo-category-item">
                                            <div class="row apw-woo-category-row">
                                                <div class="col apw-woo-category-header-col">
                                                    <!-- Category Header: Title and View All Button -->
                                                    <div class="apw-woo-category-header">
                                                        <h1 class="apw-woo-category-title"><?php echo esc_html($category->name); ?></h1>
                                                        <a href="<?php echo esc_url($category_link); ?>" class="apw-woo-view-all-button">
                                                            <?php echo esc_html(apply_filters('apw_woo_view_all_text', __('View All', 'apw-woo-plugin'))); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row apw-woo-category-image-row">
                                                <div class="col apw-woo-category-image-col">
                                                    <!-- Category Image Container -->
                                                    <div class="apw-woo-category-image-wrapper">
                                                        <a href="<?php echo esc_url($category_link); ?>" class="apw-woo-category-image-link">
                                                            <img src="<?php echo esc_url($image); ?>"
                                                                 alt="<?php echo esc_attr($category->name); ?>"
                                                                 class="apw-woo-category-image" />
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row apw-woo-category-desc-row">
                                                <div class="col apw-woo-category-desc-col">
                                                    <!-- Category Description -->
                                                    <?php
                                                    /**
                                                     * Hook: apw_woo_before_category_description
                                                     * @param WP_Term $category Current category object
                                                     */
                                                    do_action('apw_woo_before_category_description', $category);

                                                    if (!empty($category->description)) :
                                                        if (APW_WOO_DEBUG_MODE) {
                                                            apw_woo_log('Using custom description for category: ' . $category->name);
                                                        }
                                                        ?>
                                                        <div class="apw-woo-category-description">
                                                            <?php echo wp_kses_post($category->description); ?>
                                                        </div>
                                                    <?php else :
                                                        if (APW_WOO_DEBUG_MODE) {
                                                            apw_woo_log('Using default description for category: ' . $category->name);
                                                        }
                                                        ?>
                                                        <div class="apw-woo-category-description">
                                                            <?php echo wp_kses_post(apply_filters('apw_woo_default_category_description', '<p>' . __('Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumyt, consetetur sadipscing elitr, sed diam nonumy eirmod tem tempor invidunt ut.', 'apw-woo-plugin') . '</p>', $category)); ?>
                                                        </div>
                                                    <?php endif;

                                                    /**
                                                     * Hook: apw_woo_after_category_description
                                                     * @param WP_Term $category Current category object
                                                     */
                                                    do_action('apw_woo_after_category_description', $category);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        /**
                                         * Hook: apw_woo_after_category_item
                                         * @param WP_Term $category Current category object
                                         */
                                        do_action('apw_woo_after_category_item', $category);
                                    }
                                    ?>
                                </div>

                                <?php
                            } else {
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('No product categories found');
                                }

                                /**
                                 * Hook: apw_woo_no_categories_found
                                 */
                                do_action('apw_woo_no_categories_found');
                                ?>

                                <!-- No Categories Found Message -->
                                <div class="apw-woo-no-categories">
                                    <p><?php echo esc_html(apply_filters('apw_woo_no_categories_text', __('No product categories found.', 'apw-woo-plugin'))); ?></p>
                                </div>

                                <?php
                            }

                            /**
                             * Hook: apw_woo_after_categories_grid
                             */
                            do_action('apw_woo_after_categories_grid');
                            ?>
                        </div>
                    </div>

                    <!-- FAQ Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-faq-section-container">
                            <?php
                            /**
                             * Hook: apw_woo_before_shop_faqs
                             */
                            do_action('apw_woo_before_shop_faqs');

                            if (APW_WOO_DEBUG_MODE) {
                                apw_woo_log('Loading FAQ display for shop page');
                            }

                            // Include the FAQ display partial, passing the page ID from which to pull the FAQs
                            if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                                // Set the page ID variable that will be accessible in the included file
                                $faq_page_id = apw_woo_get_faq_page_id('shop');
                                include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
                            } else {
                                if (APW_WOO_DEBUG_MODE) {
                                    apw_woo_log('FAQ display partial not found');
                                }
                            }

                            /**
                             * Hook: apw_woo_after_shop_faqs
                             */
                            do_action('apw_woo_after_shop_faqs');
                            ?>
                        </div>
                    </div>

                    <?php
                    /**
                     * Hook: apw_woo_after_shop_content
                     */
                    do_action('apw_woo_after_shop_content');
                    ?>
                </div>
            </div>
        </div>
    </main>
<?php
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Completed rendering shop categories template');
}

get_footer();
?>