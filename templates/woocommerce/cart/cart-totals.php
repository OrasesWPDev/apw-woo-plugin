<?php
/**
 * Cart totals - APW WooCommerce Plugin Override
 *
 * This template overrides the default WooCommerce cart totals template,
 * replacing dynamic shipping calculations with "Calculated at checkout" text
 * and using APW Woo Plugin styling classes for consistency.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 2.3.6-apw.1
 */

defined('ABSPATH') || exit;

// APW Woo Plugin: Log cart totals template loading if debug mode is on
$apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
$apw_log_exists = function_exists('apw_woo_log');

if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CART TOTALS: Loading custom cart-totals.php template with "Calculated at checkout" for shipping and total');
}
?>
<div class="cart_totals apw-woo-cart-totals <?php echo (WC()->customer->has_calculated_shipping()) ? 'calculated_shipping' : ''; ?>">

    <?php do_action('woocommerce_before_cart_totals'); ?>

    <h2 class="apw-woo-cart-totals-title"><?php esc_html_e('Cart totals', 'woocommerce'); ?></h2>

    <table cellspacing="0" class="shop_table shop_table_responsive apw-woo-cart-totals-table">

        <tr class="cart-subtotal apw-woo-cart-subtotal">
            <th><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
            <td data-title="<?php esc_attr_e('Subtotal', 'woocommerce'); ?>"><?php wc_cart_totals_subtotal_html(); ?></td>
        </tr>

        <?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
            <tr class="cart-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?> apw-woo-cart-discount">
                <th><?php wc_cart_totals_coupon_label($coupon); ?></th>
                <td data-title="<?php echo esc_attr(wc_cart_totals_coupon_label($coupon, false)); ?>"><?php wc_cart_totals_coupon_html($coupon); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php do_action('woocommerce_cart_totals_before_shipping'); ?>

        <tr class="woocommerce-shipping-totals shipping apw-woo-cart-shipping">
            <th><?php esc_html_e('Shipping', 'woocommerce'); ?></th>
            <td data-title="<?php esc_attr_e('Shipping', 'woocommerce'); ?>">
                <span class="apw-woo-calculated-at-checkout"><?php esc_html_e('Calculated at checkout', 'apw-woo-plugin'); ?></span>
            </td>
        </tr>

        <?php do_action('woocommerce_cart_totals_after_shipping'); ?>

        <?php foreach (WC()->cart->get_fees() as $fee) : ?>
            <tr class="fee apw-woo-cart-fee">
                <th><?php echo esc_html($fee->name); ?></th>
                <td data-title="<?php echo esc_attr($fee->name); ?>"><?php wc_cart_totals_fee_html($fee); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php
        if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) {
            $taxable_address = WC()->customer->get_taxable_address();
            $estimated_text  = '';

            if (WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping()) {
                /* translators: %s location. */
                $estimated_text = sprintf(' <small>' . esc_html__('(estimated for %s)', 'woocommerce') . '</small>', WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]);
            }

            if ('itemized' === get_option('woocommerce_tax_total_display')) {
                foreach (WC()->cart->get_tax_totals() as $code => $tax) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        ?>
                    <tr class="tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?> apw-woo-cart-tax">
                        <th><?php echo esc_html($tax->label) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
                        <td data-title="<?php echo esc_attr($tax->label); ?>"><?php echo wp_kses_post($tax->formatted_amount); ?></td>
                    </tr>
                <?php
                }
            } else {
                ?>
                <tr class="tax-total apw-woo-cart-tax-total">
                    <th><?php echo esc_html(WC()->countries->tax_or_vat()) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
                    <td data-title="<?php echo esc_attr(WC()->countries->tax_or_vat()); ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
                </tr>
        <?php
            }
        }
        ?>

        <?php do_action('woocommerce_cart_totals_before_order_total'); ?>

        <tr class="order-total apw-woo-cart-order-total">
            <th><?php esc_html_e('Total', 'woocommerce'); ?></th>
            <td data-title="<?php esc_attr_e('Total', 'woocommerce'); ?>">
                <span class="apw-woo-calculated-at-checkout"><?php esc_html_e('Calculated at checkout', 'apw-woo-plugin'); ?></span>
            </td>
        </tr>

        <?php do_action('woocommerce_cart_totals_after_order_total'); ?>

    </table>

    <div class="wc-proceed-to-checkout apw-woo-proceed-to-checkout">
        <?php do_action('woocommerce_proceed_to_checkout'); ?>
    </div>

    <?php do_action('woocommerce_after_cart_totals'); ?>

</div>
