<?php
/**
 * Checkout Form - APW WooCommerce Plugin Override
 *
 * This template overrides the default WooCommerce checkout form template,
 * applying the standard page structure and header block from the plugin.
 * It MANUALLY RENDERS billing fields for custom layout control.
 * Standard action hooks are used for shipping, order review, and payment.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 3.5.0-apw.5 // Increment version - Manual Billing Fields
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
    apw_woo_log('CHECKOUT TEMPLATE: Loading custom checkout template: templates/woocommerce/checkout/form-checkout.php with MANUALLY RENDERED BILLING FIELDS');
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
    <!-- APW-WOO-TEMPLATE: form-checkout.php (structured, manual billing fields, custom classes) is loaded -->

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

                            <div class="woocommerce-billing-fields">
                                <h3><?php esc_html_e('Billing details', 'woocommerce'); ?></h3>

                                <?php do_action('woocommerce_before_checkout_billing_form', $checkout); ?>

                                <?php
                                // --- START: Manual Billing Field Rendering ---
                                $billing_fields = $checkout->get_checkout_fields('billing');

                                if ($billing_fields && is_array($billing_fields)) :

                                    echo '<div class="apw-woo-billing-fields-wrapper">'; // Custom wrapper

                                    // --- Row 1: First Name | Last Name (50/50) ---
                                    echo '<div class="apw-woo-form-row apw-woo-form-row-two-col">';
                                    if (isset($billing_fields['billing_first_name'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">';
                                        woocommerce_form_field('billing_first_name', $billing_fields['billing_first_name'], $checkout->get_value('billing_first_name'));
                                        echo '</div>';
                                    }
                                    if (isset($billing_fields['billing_last_name'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">';
                                        woocommerce_form_field('billing_last_name', $billing_fields['billing_last_name'], $checkout->get_value('billing_last_name'));
                                        echo '</div>';
                                    }
                                    echo '</div>'; // End Row 1

                                    // --- Row 2: Street Address 1 | Street Address 2 (50/50) ---
                                    echo '<div class="apw-woo-form-row apw-woo-form-row-two-col">'; // Custom row wrapper

                                    // Street Address 1
                                    if (isset($billing_fields['billing_address_1'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">'; // Custom field wrapper (50%)
                                        woocommerce_form_field('billing_address_1', $billing_fields['billing_address_1'], $checkout->get_value('billing_address_1'));
                                        echo '</div>';
                                    }

                                    // Street Address 2 (Label Modified)
                                    if (isset($billing_fields['billing_address_2'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">'; // Custom field wrapper (50%)

                                        // Modify label before rendering
                                        $billing_fields['billing_address_2']['label'] = __('Street address continued', 'apw-woo-plugin');
                                        $billing_fields['billing_address_2']['label_class'] = array(); // Ensure label is visible
                                        // Optional: Remove placeholder
                                        // $billing_fields['billing_address_2']['placeholder'] = '';

                                        woocommerce_form_field('billing_address_2', $billing_fields['billing_address_2'], $checkout->get_value('billing_address_2'));
                                        echo '</div>';
                                    }

                                    echo '</div>'; // End Row 2 (apw-woo-form-row-two-col)

                                    // --- Row 3: Town/City | State (50/50) ---
                                    echo '<div class="apw-woo-form-row apw-woo-form-row-two-col">';
                                    if (isset($billing_fields['billing_city'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">';
                                        woocommerce_form_field('billing_city', $billing_fields['billing_city'], $checkout->get_value('billing_city'));
                                        echo '</div>';
                                    }
                                    if (isset($billing_fields['billing_state'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">';
                                        woocommerce_form_field('billing_state', $billing_fields['billing_state'], $checkout->get_value('billing_state'));
                                        echo '</div>';
                                    }
                                    echo '</div>'; // End Row 3

                                    // --- Row 4: ZIP Code | Country | Phone (33/33/33) ---
                                    echo '<div class="apw-woo-form-row apw-woo-form-row-three-col">'; // Use three-col class
                                    if (isset($billing_fields['billing_postcode'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-third">'; // Use third class
                                        woocommerce_form_field('billing_postcode', $billing_fields['billing_postcode'], $checkout->get_value('billing_postcode'));
                                        echo '</div>';
                                    }
                                    if (isset($billing_fields['billing_country'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-third">'; // Use third class
                                        woocommerce_form_field('billing_country', $billing_fields['billing_country'], $checkout->get_value('billing_country'));
                                        echo '</div>';
                                    }
                                    if (isset($billing_fields['billing_phone'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-third">'; // Use third class
                                        woocommerce_form_field('billing_phone', $billing_fields['billing_phone'], $checkout->get_value('billing_phone'));
                                        echo '</div>';
                                    }
                                    echo '</div>'; // End Row 4

                                    // --- Row 5: Email | Additional Emails (50/50) ---
                                    echo '<div class="apw-woo-form-row apw-woo-form-row-two-col">';
                                    if (isset($billing_fields['billing_email'])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">';
                                        woocommerce_form_field('billing_email', $billing_fields['billing_email'], $checkout->get_value('billing_email'));
                                        echo '</div>';
                                    }
                                    // Include our custom field - ensure its definition uses the correct key 'apw_woo_billing_additional_emails'
                                    $additional_email_key = defined('APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY') ? APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY : 'apw_woo_billing_additional_emails';
                                    if (isset($billing_fields[$additional_email_key])) {
                                        echo '<div class="apw-woo-field apw-woo-field-half">';
                                        woocommerce_form_field($additional_email_key, $billing_fields[$additional_email_key], $checkout->get_value($additional_email_key));
                                        echo '</div>';
                                    }
                                    echo '</div>'; // End Row 5

                                    // Render any other billing fields added by plugins that we haven't explicitly handled
                                    foreach ($billing_fields as $key => $field) {
                                        // List of fields already handled above
                                        $handled_keys = [
                                            'billing_first_name', 'billing_last_name',
                                            'billing_address_1', 'billing_address_2',
                                            'billing_city', 'billing_state',
                                            'billing_postcode', 'billing_country', 'billing_phone',
                                            'billing_email', $additional_email_key
                                        ];
                                        if (!in_array($key, $handled_keys)) {
                                            echo '<div class="apw-woo-form-row apw-woo-form-row-full-col">'; // Fallback row
                                            echo '<div class="apw-woo-field apw-woo-field-full">'; // Fallback width
                                            woocommerce_form_field($key, $field, $checkout->get_value($key));
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }


                                    echo '</div>'; // End apw-woo-billing-fields-wrapper

                                endif; // End check for $billing_fields
                                // --- END: Manual Billing Field Rendering ---
                                ?>

                                <?php do_action('woocommerce_after_checkout_billing_form', $checkout); ?>
                            </div> <?php // End .woocommerce-billing-fields ?>


                            <?php // Shipping fields section - Keep using the action hook for simplicity unless specific layout needed ?>
                            <div class="woocommerce-shipping-fields">
                                <?php if (true === WC()->cart->needs_shipping_address()) : ?>

                                    <h3 id="ship-to-different-address">
                                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                                            <input id="ship-to-different-address-checkbox"
                                                   class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked(apply_filters('woocommerce_ship_to_different_address_checked', 'shipping' === get_option('woocommerce_ship_to_destination') ? 1 : 0), 1); ?>
                                                   type="checkbox" name="ship_to_different_address" value="1"/>
                                            <span><?php esc_html_e('Ship to a different address?', 'woocommerce'); ?></span>
                                        </label>
                                    </h3>

                                    <div class="shipping_address"
                                         style="display: none;"> <?php // This div is toggled by WC JS ?>
                                        <?php do_action('woocommerce_before_checkout_shipping_form', $checkout); ?>

                                        <div class="woocommerce-shipping-fields__field-wrapper">
                                            <?php
                                            // Use standard action hook for shipping fields - will appear below billing
                                            do_action('woocommerce_checkout_shipping');
                                            ?>
                                        </div>

                                        <?php do_action('woocommerce_after_checkout_shipping_form', $checkout); ?>
                                    </div>

                                <?php endif; ?>
                            </div>
                            <!--                            <div class="woocommerce-additional-fields">-->
                            <!--                                --><?php //do_action('woocommerce_before_order_notes', $checkout); ?>
                            <!---->
                            <!--                                --><?php //if (apply_filters('woocommerce_enable_order_notes_field', 'yes' === get_option('woocommerce_enable_order_comments', 'yes'))) : ?>
                            <!---->
                            <!--                                    --><?php //if (!WC()->cart->needs_shipping() || wc_ship_to_billing_address_only()) : ?>
                            <!--                                        <h3>-->
                            <?php //esc_html_e('Additional information', 'woocommerce'); ?><!--</h3>-->
                            <!--                                    --><?php //endif; ?>
                            <!---->
                            <!--                                    <div class="woocommerce-additional-fields__field-wrapper">-->
                            <!--                                        --><?php //foreach ($checkout->get_checkout_fields('order') as $key => $field) : ?>
                            <!--                                            --><?php //// Render order notes using standard function ?>
                            <!--                                            --><?php //woocommerce_form_field($key, $field, $checkout->get_value($key)); ?>
                            <!--                                        --><?php //endforeach; ?>
                            <!--                                    </div>-->
                            <!---->
                            <!--                                --><?php //endif; ?>
                            <!---->
                            <!--                                --><?php //do_action('woocommerce_after_order_notes', $checkout); ?>
                            <!--                            </div>-->


                        </div> <?php // End #customer_details ?>

                        <?php do_action('woocommerce_checkout_after_customer_details'); ?>

                    <?php endif; // End if ($checkout->get_checkout_fields()) ?>

                    <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>

                    <?php // Added apw-woo-order-review-heading class ?>
                    <h3 id="order_review_heading"
                        class="apw-woo-order-review-heading"><?php esc_html_e('Your order', 'woocommerce'); ?></h3>

                    <?php do_action('woocommerce_checkout_before_order_review'); ?>

                    <?php // Added apw-woo-order-review class ?>
                    <div id="order_review" class="woocommerce-checkout-review-order apw-woo-order-review">
                        <?php // This action outputs the order table AND the payment section ?>
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
    apw_woo_log('CHECKOUT TEMPLATE: Finished rendering custom checkout template with MANUALLY RENDERED BILLING FIELDS.');
}

get_footer();
?>
<!-- APW-WOO-TEMPLATE: Custom form-checkout.php (structured, manual billing fields, custom classes) is loaded -->
