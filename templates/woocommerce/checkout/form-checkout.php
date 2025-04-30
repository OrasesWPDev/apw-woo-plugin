<?php
/**
 * Checkout Form - APW WooCommerce Plugin Override
 *
 * This template overrides the default WooCommerce checkout form template,
 * applying the standard page structure and header block from the plugin
 * using the direct shortcode method, matching single-product.php logic.
 * It removes the default 2-column layout for billing/shipping and adds
 * custom apw-woo- prefixed classes for easier styling.
 * Includes temporary debug code for woocommerce_after_checkout_form hook.
 * MODIFIED: Clarified guest checkout message and added debug log.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 3.5.0-apw.5 // Increment version
 *
 * Original WooCommerce template version: 3.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// APW Woo Plugin: Log checkout template loading if debug mode is on
$apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
$apw_log_exists = function_exists('apw_woo_log');

if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CHECKOUT TEMPLATE: Loading custom checkout template: templates/woocommerce/checkout/form-checkout.php with theme structure, revised layout, custom classes, and modified guest block');
}

get_header();

$target_block_id = 'third-level-woo-page-header'; // Same as single-product.php
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
    <!-- APW-WOO-TEMPLATE: form-checkout.php (structured, single-column, custom classes, modified guest block) is loaded -->

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

                // --- MODIFICATION START: Updated this block ---
                // If checkout registration is disabled and not logged in, the user cannot checkout.
                if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
                    // Add debug log inside the block
                    if ($apw_debug_mode && $apw_log_exists) {
                        apw_woo_log('CHECKOUT TEMPLATE: Guest checkout is disabled and user is not logged in. Displaying block message and exiting template.');
                    }
                    // Display a clearer message
                    echo '<p class="apw-woo-guest-checkout-message">'; // Added a class for potential styling
                    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('Checkout is unavailable for guests. Please log in or register an account to proceed.', 'apw-woo-plugin')));
                    echo '</p>';
                    // Ensure standard hooks/footer run before exiting
                    do_action('apw_woo_after_checkout_content'); // Hook for consistency
                    echo '</div></div></div></main>'; // Close main structure
                    get_footer(); // Include footer
                    return; // Exit template processing here
                }
                // --- MODIFICATION END ---

                /**
                 * Hook: woocommerce_before_checkout_form.
                 * @hooked woocommerce_checkout_login_form - 10
                 * @hooked woocommerce_checkout_coupon_form - 10
                 */
                do_action('woocommerce_before_checkout_form', $checkout);

                ?>
                <?php // Added apw-woo-checkout-form class ?>
                <form name="checkout" method="post" class="checkout woocommerce-checkout apw-woo-checkout-form"
                      action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
                    <?php if ($checkout->get_checkout_fields()) : ?>

                        <?php do_action('woocommerce_checkout_before_customer_details'); ?>

                        <?php // Removed col2-set, col-1, col-2 wrappers ?>
                        <div id="customer_details"
                             class="apw-woo-customer-details"> <?php // Added apw-woo-customer-details class ?>

                            <?php // Billing fields will render here ?>
                            <?php do_action('woocommerce_checkout_billing'); ?>

                            <?php // Shipping fields will render here (and be toggled by WC JS) ?>
                            <?php do_action('woocommerce_checkout_shipping'); ?>

                        </div> <?php // End #customer_details ?>

                        <?php do_action('woocommerce_checkout_after_customer_details'); ?>

                    <?php endif; ?>

                    <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>

                    <?php // Added apw-woo-order-review-heading class ?>
                    <h3 id="order_review_heading"
                        class="apw-woo-order-review-heading"><?php esc_html_e('Your order', 'woocommerce'); ?></h3>

                    <?php do_action('woocommerce_checkout_before_order_review'); ?>

                    <?php // Added apw-woo-order-review class ?>
                    <div id="order_review" class="woocommerce-checkout-review-order apw-woo-order-review">
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
    apw_woo_log('CHECKOUT TEMPLATE: Finished rendering custom checkout template with theme structure, revised layout, custom classes, and modified guest block.');
}

get_footer();
?>
<!-- APW-WOO-TEMPLATE: Custom form-checkout.php (structured, single-column, custom classes, modified guest block) is loaded -->