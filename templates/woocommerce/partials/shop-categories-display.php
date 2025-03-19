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

get_header();
?>

    <main id="main" class="apw-woo-shop-categories-main">
        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="second-level-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                echo '<h1 class="apw-woo-page-title">' . esc_html(woocommerce_page_title(false)) . '</h1>';
            }
            ?>
        </div>

        <div class="apw-woo-container">
            <!-- Shop Introduction Section -->
            <div class="apw-woo-product-intro">
                <h1 class="apw-woo-section-title">Our Products</h1>
                <div class="apw-woo-section-description">
                    <p>This is a placeholder paragraph that can be edited later. It will provide an introduction to the product categories offered by the company.</p>
                </div>
            </div>

            <!-- Product Categories Grid -->
            <?php
            // Get product categories
            $product_categories = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => 0, // Get only top-level categories
            ]);

            if (!empty($product_categories) && !is_wp_error($product_categories)) {
                ?>
                <div class="apw-woo-categories-grid">
                    <?php
                    foreach ($product_categories as $category) {
                        // Skip the "Uncategorized" category
                        if ($category->slug === 'uncategorized') {
                            continue;
                        }

                        // Get category image
                        $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
                        $image = wp_get_attachment_url($thumbnail_id);

                        // Get category link
                        $category_link = get_term_link($category);
                        ?>
                        <!-- Individual Category Item -->
                        <div class="apw-woo-category-item">
                            <!-- Category Header: Title and View All Button -->
                            <div class="apw-woo-category-header">
                                <h1 class="apw-woo-category-title"><?php echo esc_html($category->name); ?></h1>
                                <a href="<?php echo esc_url($category_link); ?>" class="apw-woo-view-all-button">
                                    View All
                                </a>
                            </div>

                            <!-- Category Image Container -->
                            <div class="apw-woo-category-image-wrapper">
                                <a href="<?php echo esc_url($category_link); ?>" class="apw-woo-category-image-link">
                                    <img src="<?php echo esc_url($image); ?>"
                                         alt="<?php echo esc_attr($category->name); ?>"
                                         class="apw-woo-category-image" />
                                </a>
                            </div>

                            <!-- Category Description -->
                            <?php if (!empty($category->description)) : ?>
                                <div class="apw-woo-category-description">
                                    <?php echo wp_kses_post($category->description); ?>
                                </div>
                            <?php else : ?>
                                <div class="apw-woo-category-description">
                                    <p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumyt, consetetur sadipscing elitr, sed diam nonumy eirmod tem tempor invidunt ut.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <?php
            } else {
                ?>
                <!-- No Categories Found Message -->
                <div class="apw-woo-no-categories">
                    <p><?php esc_html_e('No product categories found.', 'apw-woo-plugin'); ?></p>
                </div>
                <?php
            }
            ?>

            <!-- Include FAQ Display Partial -->
            <?php
            // Include the FAQ display partial, passing the page ID from which to pull the FAQs
            if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                // Set the page ID variable that will be accessible in the included file
                $faq_page_id = 66; // Page ID for shop page FAQs
                include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
            }
            ?>
        </div>
    </main>

<?php
get_footer();