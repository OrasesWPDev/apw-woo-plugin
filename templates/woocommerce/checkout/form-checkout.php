<?php
/**
 * Checkout Form - APW WooCommerce Plugin Override
 *
 * This template overrides the default WooCommerce checkout form template,
 * applying the standard page structure and header block from the plugin
 * using the direct shortcode method, matching single-product.php logic.
 * It removes the default 2-column layout for billing/shipping and adds
 * custom apw-woo- prefixed classes for easier styling.
 * MODIFIED: For guests, display a message and redirect to account page after delay.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 3.5.0-apw.6 // Increment version
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
    apw_woo_log('CHECKOUT TEMPLATE: Loading custom checkout template: templates/woocommerce/checkout/form-checkout.php with guest redirect message');
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
    <!-- APW-WOO-TEMPLATE: form-checkout.php (structured, single-column, custom classes, guest redirect) is loaded -->

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

    <?php
    // Check if we're on the order-received endpoint
    if (is_wc_endpoint_url('order-received')) {
        $order_id = absint(get_query_var('order-received'));

        if ($order_id > 0) {
            // Get the order
            $order = wc_get_order($order_id);

            if ($order) {
                // Display the order received content
                ?>
                <div class="apw-woo-order-received apw-woo-section-wrapper">
                    <?php
                    // This hook displays the order details and thank you message
                    do_action('woocommerce_thankyou', $order_id);
                    ?>
                </div>
                <?php

                // Don't display the checkout form if we're showing the thank you page
                get_footer();
                exit;
            }
        }
    }
    ?>

    <!-- Main Content Container -->
    <div class="container">
        <div class="row">
            <div class="col apw-woo-content-wrapper">
                <?php
                /**
                 * Hook: apw_woo_before_checkout_content
                 */
                do_action('apw_woo_before_checkout_content');

                // --- MODIFICATION START: Display redirect message + JS for guests ---
                // Check if guest checkout is disabled and user is not logged in
                if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {

                    if ($apw_debug_mode && $apw_log_exists) {
                        apw_woo_log('CHECKOUT TEMPLATE: Guest checkout disabled. Displaying redirect message and script.');
                    }

                    // Get the account page URL dynamically
                    $account_page_url = wc_get_page_permalink('myaccount');

                    if ($account_page_url) {
                        ?>
                        <div class="apw-woo-checkout-redirect-notice woocommerce-info"> <?php // Added woocommerce-info for basic styling ?>
                            <p><?php echo esc_html__('Please log in or register a new account before proceeding to checkout.', 'apw-woo-plugin'); ?></p>
                            <p><?php echo esc_html__('You will automatically be redirected to the Account page in 3 seconds.', 'apw-woo-plugin'); ?></p>
                            <p><a href="<?php echo esc_url($account_page_url); ?>"
                                  class="apw-woo-account-redirect-link button wc-forward"><?php echo esc_html__('Click here if you are not redirected.', 'apw-woo-plugin'); ?></a>
                            </p> <?php // Added standard WC button classes ?>
                        </div>
                        <script type="text/javascript">
                            setTimeout(function () {
                                window.location.href = '<?php echo esc_js($account_page_url); ?>';
                            }, 3000); // 3000 milliseconds = 3 seconds
                        </script>
                        <?php
                    } else {
                        // Fallback message if account page isn't set in WooCommerce settings
                        if ($apw_debug_mode && $apw_log_exists) {
                            apw_woo_log('CHECKOUT TEMPLATE WARNING: WooCommerce My Account page is not set. Cannot redirect guest.', 'warning');
                        }
                        echo '<p class="apw-woo-guest-checkout-message woocommerce-info">'; // Added a class and WC notice class
                        echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('Checkout is unavailable for guests. Please log in or register an account to proceed.', 'apw-woo-plugin')));
                        echo '</p>';
                    }

                    // Ensure standard hooks/footer run before exiting
                    do_action('apw_woo_after_checkout_content'); // Hook for consistency
                    echo '</div></div></div></main>'; // Close main structure
                    get_footer(); // Include footer
                    return; // Exit template processing here to prevent checkout form rendering
                }
                // --- MODIFICATION END ---

                /**
                 * Hook: woocommerce_before_checkout_form.
                 * @hooked woocommerce_checkout_login_form - 10
                 * @hooked woocommerce_checkout_coupon_form - 10
                 */
                // IMPORTANT: Only show login/coupon form if the user *isn't* being redirected (i.e., if they are logged in or guest checkout is allowed)
                if (is_user_logged_in() || !(!$checkout->is_registration_enabled() && $checkout->is_registration_required())) {
                    do_action('woocommerce_before_checkout_form', $checkout);
                }

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
                        <!--                        --><?php //do_action('woocommerce_checkout_order_review'); ?>

                        <?php do_action('woocommerce_review_order_before_payment'); ?>
                        <div id="payment" class="woocommerce-checkout-payment apw-woo-payment-section">
                            <?php
                            /*
                            if (WC()->cart && WC()->cart->needs_payment()) : ?>
                                <ul class="wc_payment_methods payment_methods methods">
                                    <?php
                                    if (!empty($available_gateways)) {
                                        foreach ($available_gateways as $gateway) {
                                            wc_get_template('checkout/payment-method.php', array('gateway' => $gateway));
                                        }
                                    } else {
                                        echo '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info">' . apply_filters('woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__('Sorry, it seems that there are no available payment methods for your state. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce') : esc_html__('Please fill in your details above to see available payment methods.', 'woocommerce')) . '</li>';
                                    }
                                    ?>
                                </ul>
                            <?php endif;
                            */
                            ?>
                            <?php /* <div class="form-row place-order">
                                <noscript>
                                    <?php
                                    /* translators: $1 and $2 opening and closing emphasis tags respectively */
                                    printf(esc_html__('Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate Totals%2$s button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce'), '<em>', '</em>');
                                    ?>
                                    <br/>
                                    <button type="submit" class="button alt" name="woocommerce_checkout_update_totals"
                                            value="<?php esc_attr_e('Update totals', 'woocommerce'); ?>"><?php esc_html_e('Update totals', 'woocommerce'); ?></button>
                                </noscript>

                                <?php wc_get_template('checkout/terms.php'); ?>

                                <?php do_action('woocommerce_review_order_before_submit'); ?>

                                <?php echo apply_filters('woocommerce_order_button_html', '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '">' . esc_html($order_button_text) . '</button>'); // @codingStandardsIgnoreLine ?>

                                <?php do_action('woocommerce_review_order_after_submit'); ?>

                                <?php wp_nonce_field('woocommerce-process-checkout', 'woocommerce-process-checkout-nonce'); ?>
                            </div>
                            */
                            ?>
                        </div>
                        <?php do_action('woocommerce_review_order_after_payment'); ?>
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
    apw_woo_log('CHECKOUT TEMPLATE: Finished rendering custom checkout template with guest redirect logic.');
}

get_footer();
?>
<!-- APW-WOO-TEMPLATE: Custom form-checkout.php (structured, single-column, custom classes, guest redirect) is loaded -->
