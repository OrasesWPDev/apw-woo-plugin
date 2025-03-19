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

get_header();

// Get the current category
$current_category = get_queried_object();
?>

    <main id="main" class="apw-woo-category-products-main">
        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="third-level-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                echo '<h1 class="apw-woo-page-title">' . esc_html(single_term_title('', false)) . '</h1>';
            }
            ?>
        </div>

        <div class="apw-woo-container">
            <!-- Product Grid -->
            <?php
            // Get products in this category
            $products = wc_get_products([
                'status' => 'publish',
                'limit' => -1,
                'category' => [$current_category->slug],
            ]);

            if (!empty($products)) {
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
                        ?>
                        <!-- Individual Product Item -->
                        <div class="apw-woo-product-item">
                            <!-- Product Header: Title and View Product Button -->
                            <div class="apw-woo-category-header">
                                <h1 class="apw-woo-product-title"><?php echo esc_html($product_title); ?></h1>
                                <a href="<?php echo esc_url($product_link); ?>" class="apw-woo-view-all-button">
                                    View Product
                                </a>
                            </div>

                            <!-- Product Image Container -->
                            <div class="apw-woo-product-image-wrapper">
                                <a href="<?php echo esc_url($product_link); ?>" class="apw-woo-product-image-link">
                                    <img src="<?php echo esc_url($product_image); ?>"
                                         alt="<?php echo esc_attr($product_title); ?>"
                                         class="apw-woo-product-image" />
                                </a>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <?php
            } else {
                ?>
                <!-- No Products Found Message -->
                <div class="apw-woo-no-products">
                    <p><?php esc_html_e('No products found in this category.', 'apw-woo-plugin'); ?></p>
                </div>
                <?php
            }
            ?>

            <!-- Include FAQ Display Partial -->
            <?php
            // Include the FAQ display partial, passing the current category
            if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                // Set the category object that will be accessible in the included file
                $faq_category = $current_category;
                include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
            }
            ?>
        </div>
    </main>

<?php
get_footer();