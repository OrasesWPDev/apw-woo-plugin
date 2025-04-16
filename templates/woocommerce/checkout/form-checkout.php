<?php
/**
 * Checkout Form - APW WooCommerce Plugin Override
 *
 * This template overrides the default WooCommerce checkout form template,
 * applying the standard page structure and header block from the plugin
 * using the direct shortcode method, matching single-product.php logic.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 3.5.0-apw.2
 *
 * Original WooCommerce template version: 3.5.0 (Ensure compatibility or update based on your WC version)
 */

if (!defined('ABSPATH')) {
    exit;
}

// APW Woo Plugin: Log checkout template loading if debug mode is on
$apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
$apw_log_exists = function_exists('apw_woo_log');

if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CHECKOUT TEMPLATE: Loading custom checkout template: templates/woocommerce/checkout/form-checkout.php with theme structure');
}

get_header();

$target_block_id = 'fourth-level-page-header'; // Same as single-product.php
$checkout_page_id = wc_get_page_id('checkout');
// Define the correct title ONLY for the fallback scenario
$correct_checkout_title = $checkout_page_id ? get_the_title($checkout_page_id) : __('Checkout', 'woocommerce');

// Ensure $checkout variable is available (WooCommerce usually makes it global on this template)
global $checkout;
if (!is_a($checkout, 'WC_Checkout')) {
    // If somehow $checkout is not available, try to instantiate it.
    $checkout = WC()->checkout();
    if ($apw_debug_mode && $apw_log_exists) {
        apw_woo_log('CHECKOUT TEMPLATE WARNING: Global $checkout was not available, instantiated manually.');
    }
}

?>
<main id="main" class="apw-woo-checkout-main">
    <!-- APW-WOO-TEMPLATE: form-checkout.php (structured) is loaded -->

    <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
    <div class="apw-woo-section-wrapper apw-woo-header-block">
        <?php
        /**
         * Hook: apw_woo_before_checkout_header
         */
        do_action('apw_woo_before_checkout_header');

        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('CHECKOUT TEMPLATE: Rendering header block section using direct do_shortcode. Block ID: ' . $target_block_id);
        }

        // Replicate the exact logic from single-product.php / final cart.php for rendering the block
        if (shortcode_exists('block')) {
            echo do_shortcode('[block id="' . esc_attr($target_block_id) . '"]'); // Direct echo, no ob_start/preg_replace
        } else {
            // Fallback if shortcode doesn't exist
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log('CHECKOUT TEMPLATE WARNING: Shortcode [block] does not exist. Falling back to standard title.');
            }
            echo '<div class="container page-title-container-fallback"><h1 class="apw-woo-page-title entry-title">' . esc_html($correct_checkout_title) . '</h1></div>';
        }

        /**
         * Hook: apw_woo_after_checkout_header
         */
        do_action('apw_woo_after_checkout_header');
        ?>
    </div><!-- /.apw-woo-header-block -->

    <!-- Notice Container - For WooCommerce messages -->
    <div class="apw-woo-notices-container">
        <?php 
        // This will print all queued notices
        wc_print_notices(); 
        ?>
    </div>

    <!-- Main Content Container -->
    <div class="container">
        <div class="row">
            <div class="col apw-woo-content-wrapper">
                <?php
                /**
                 * Hook: apw_woo_before_checkout_content
                 */
                do_action('apw_woo_before_checkout_content');

                // If checkout registration is disabled and not logged in, the user cannot checkout.
                if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
                    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce')));
                    do_action('apw_woo_after_checkout_content'); // Hook for consistency
                    echo '</div></div></div></main>'; // Close main structure
                    get_footer(); // Include footer
                    return; // Exit template
                }

                /**
                 * Hook: woocommerce_before_checkout_form.
                 * @hooked woocommerce_checkout_login_form - 10
                 * @hooked woocommerce_checkout_coupon_form - 10
                 * @hooked woocommerce_output_all_notices - 10 (added for notices)
                 */
                do_action('woocommerce_before_checkout_form', $checkout);

                ?>
                <form name="checkout" method="post" class="checkout woocommerce-checkout"
                      action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
                    <?php if ($checkout->get_checkout_fields()) : ?>
                        <?php do_action('woocommerce_checkout_before_customer_details'); ?>
                        <div class="col2-set" id="customer_details">
                            <div class="col-1">
                                <?php do_action('woocommerce_checkout_billing'); ?>
                            </div>
                            <div class="col-2">
                                <?php do_action('woocommerce_checkout_shipping'); ?>
                            </div>
                        </div>
                        <?php do_action('woocommerce_checkout_after_customer_details'); ?>
                    <?php endif; ?>

                    <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>
                    <h3 id="order_review_heading"><?php esc_html_e('Your order', 'woocommerce'); ?></h3>
                    <?php do_action('woocommerce_checkout_before_order_review'); ?>

                    <div id="order_review" class="woocommerce-checkout-review-order">
                        <?php do_action('woocommerce_checkout_order_review'); ?>
                    </div>

                    <?php do_action('woocommerce_checkout_after_order_review'); ?>
                </form>
                <?php
                do_action('woocommerce_after_checkout_form', $checkout);

                /**
                 * Hook: apw_woo_after_checkout_content
                 */
                do_action('apw_woo_after_checkout_content');
                ?>
            </div> <!-- /.col.apw-woo-content-wrapper -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</main><!-- /#main -->
<?php

// APW Woo Plugin: Log end of checkout template if debug mode is on
if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CHECKOUT TEMPLATE: Finished rendering custom checkout template with theme structure.');
}

get_footer();
?>
<!-- APW-WOO-TEMPLATE: Custom form-checkout.php (structured) is loaded -->
