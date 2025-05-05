<?php
/**
 * WooCommerce Cross-Sells Display Functions
 *
 * Handles fetching and displaying cross-sell products on the single product page.
 *
 * @package APW_Woo_Plugin
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display the cross-sells section below the FAQs.
 *
 * Fetches cross-sell product IDs, retrieves product data, and outputs
 * them in a simple grid format with image and name linking to the product.
 */
function apw_woo_display_cross_sells()
{

    // Start logging for this function
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Cross-sells: Running apw_woo_display_cross_sells function.');
    }

//    // --- Step 1: Verify Context ---
//    // Use our reliable page detector first
//    $is_product_context = (function_exists('APW_Woo_Page_Detector') && APW_Woo_Page_Detector::is_product_page());
//
//    if (!$is_product_context) {
//        if (APW_WOO_DEBUG_MODE) {
//            apw_woo_log('Cross-sells: Exiting - not detected as a single product page.');
//        }
//        return; // Exit if not on a product page
//    }

    global $product;

    // Ensure we have a valid product object from the global scope
    if (!is_a($product, 'WC_Product')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Cross-sells: Global $product object is invalid or not a WC_Product.', 'warning');
        }
        // Attempt to get product from the main query as a fallback
        $product = wc_get_product(get_queried_object_id());
        if (!is_a($product, 'WC_Product')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Cross-sells: Could not retrieve a valid product object. Exiting.', 'error');
            }
            return;
        }
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Cross-sells: Used fallback to get product ID: ' . $product->get_id());
        }
    }

    $product_id = $product->get_id();

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Cross-sells: Target product ID is: ' . $product_id);
    }

    // --- Step 2: Get Cross-Sell IDs ---
    $cross_sell_ids = $product->get_cross_sell_ids(); // Use the WC_Product method

    // Check if we have an array of IDs and it's not empty
    if (empty($cross_sell_ids) || !is_array($cross_sell_ids)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Cross-sells: No cross-sell IDs found for product ID: ' . $product_id);
        }
        return; // No cross-sells to display
    }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Cross-sells: Found ' . count($cross_sell_ids) . ' cross-sell IDs: ' . implode(', ', $cross_sell_ids));
    }

    // --- Step 3: Prepare Output ---
    ob_start(); // Start output buffering to build the HTML

    ?>
    <div class="apw-woo-cross-sells-section">
        <h2 class="apw-woo-cross-sells-title">
            <?php echo esc_html(apply_filters('apw_woo_cross_sells_title', __('You may be interested in...', 'apw-woo-plugin'))); ?>
        </h2>
        <div class="apw-woo-cross-sells-grid">
            <?php
            $cross_sells_displayed = 0;
            foreach ($cross_sell_ids as $cross_sell_id) :
                // Get the cross-sell product object
                $cs_product = wc_get_product($cross_sell_id);

                // --- Validate the cross-sell product ---
                if (!$cs_product || !is_a($cs_product, 'WC_Product') || $cs_product->get_status() !== 'publish') {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log('Cross-sells: Skipping invalid or non-published product with ID: ' . $cross_sell_id);
                    }
                    continue; // Skip to the next ID
                }

                // --- Get Data for Display ---
                $cs_permalink = $cs_product->get_permalink();
                // Get image - consider adding a filter for size later if needed
                $cs_image = $cs_product->get_image('woocommerce_thumbnail', array('class' => 'apw-woo-cross-sell-img'), true);
                $cs_name = $cs_product->get_name();

                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Cross-sells: Displaying item - ID: ' . $cross_sell_id . ', Name: ' . $cs_name);
                }

                // --- Output Item HTML ---
                ?>
                <div class="apw-woo-cross-sell-item">
                    <a href="<?php echo esc_url($cs_permalink); ?>" class="apw-woo-cross-sell-link">
                        <div class="apw-woo-cross-sell-image-wrapper">
                            <?php echo $cs_image; // WC get_image() returns safe HTML
                            ?>
                        </div>
                        <h3 class="apw-woo-cross-sell-name">
                            <?php echo esc_html($cs_name); ?>
                        </h3>
                    </a>
                </div>
                <?php
                $cross_sells_displayed++;
            endforeach; // End loop through cross_sell_ids
            ?>
        </div><!-- .apw-woo-cross-sells-grid -->
    </div><!-- .apw-woo-cross-sells-section -->
    <?php

    // --- Final Logging ---
    if ($cross_sells_displayed === 0 && APW_WOO_DEBUG_MODE) {
        apw_woo_log('Cross-sells: Found IDs but no valid/published cross-sell products could be displayed.');
    } elseif (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Cross-sells: Finished displaying ' . $cross_sells_displayed . ' cross-sell products.');
    }

    // Output the buffered content
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo ob_get_clean();
}

/**
 * Hook the cross-sells display function into the single product page template.
 * Runs after the custom FAQ section hook defined in templates/partials/faq-display.php
 */
add_action('apw_woo_after_product_faqs', 'apw_woo_display_cross_sells', 15);
?>