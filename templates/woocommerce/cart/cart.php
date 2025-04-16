<?php
/**
 * Cart Page - APW WooCommerce Plugin Override with Structure & Debug Logging
 *
 * This template overrides the default WooCommerce cart template, applying the
 * standard page structure (header, footer, block, container) used in this plugin.
 * It renders the header block using the same direct shortcode method as single-product.php.
 * It maintains the core structure and hooks of the original WooCommerce template (version 7.9.0).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 7.9.0-apw.5
 *
 * Original WooCommerce template version: 7.9.0
 */

defined('ABSPATH') || exit;

// APW Woo Plugin: Log cart template loading if debug mode is on
$apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
$apw_log_exists = function_exists('apw_woo_log');

if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CART TEMPLATE: Loading custom cart template: templates/woocommerce/cart/cart.php with theme structure');
}

get_header();

$target_block_id = 'fourth-level-page-header'; // Same as single-product.php
$cart_page_id = wc_get_page_id('cart');
// Define the correct title, even though we aren't using preg_replace in the header block section anymore
$correct_cart_title = $cart_page_id ? get_the_title($cart_page_id) : __('Cart', 'woocommerce');

?>
<main id="main" class="apw-woo-cart-main">
    <!-- APW-WOO-TEMPLATE: cart.php (structured) is loaded -->

    <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
    <div class="apw-woo-section-wrapper apw-woo-header-block">
        <?php
        /**
         * Hook: apw_woo_before_cart_header
         */
        do_action('apw_woo_before_cart_header');

        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('CART TEMPLATE: Rendering header block section using direct do_shortcode. Block ID: ' . $target_block_id);
        }

        // Replicate the exact logic from single-product.php for rendering the block
        if (shortcode_exists('block')) {
            echo do_shortcode('[block id="' . esc_attr($target_block_id) . '"]'); // Direct echo, no ob_start/preg_replace
        } else {
            // Fallback if shortcode doesn't exist
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log('CART TEMPLATE WARNING: Shortcode [block] does not exist. Falling back to standard title.', 'warning');
            }
            echo '<div class="container page-title-container-fallback">'; // Optional container
            echo '<h1 class="apw-woo-page-title entry-title">' . esc_html($correct_cart_title) . '</h1>';
            echo '</div>';
        }

        /**
         * Hook: apw_woo_after_cart_header
         */
        do_action('apw_woo_after_cart_header');
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
                 * Hook: apw_woo_before_cart_content
                 */
                do_action('apw_woo_before_cart_content');

                /**
                 * Hook: woocommerce_before_cart.
                 * @hooked woocommerce_output_all_notices - 10
                 * @hooked wc_print_notices - 10
                 */
                do_action('woocommerce_before_cart'); ?>

                <form class="woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
                    <?php do_action('woocommerce_before_cart_table'); ?>

                    <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents"
                           cellspacing="0">
                        <thead>
                        <tr>
                            <th class="product-remove"><span
                                        class="screen-reader-text"><?php esc_html_e('Remove item', 'woocommerce'); ?></span>
                            </th>
                            <th class="product-thumbnail"><span
                                        class="screen-reader-text"><?php esc_html_e('Thumbnail image', 'woocommerce'); ?></span>
                            </th>
                            <th class="product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                            <th class="product-price"><?php esc_html_e('Price', 'woocommerce'); ?></th>
                            <th class="product-quantity"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                            <th class="product-subtotal"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php do_action('woocommerce_before_cart_contents'); ?>

                        <?php
                        // APW Woo Plugin: Log before cart item loop
                        if ($apw_debug_mode && $apw_log_exists) {
                            $cart_contents = WC()->cart->get_cart();
                            $cart_count = count($cart_contents);
                            apw_woo_log("CART TEMPLATE: Starting cart items loop. Cart item count: " . $cart_count);
                            if ($cart_count === 0) {
                                apw_woo_log("CART TEMPLATE: Cart is empty.");
                            }
                        }

                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                            // APW Woo Plugin: Log start of processing for a cart item
                            if ($apw_debug_mode && $apw_log_exists) {
                                apw_woo_log("CART TEMPLATE: Processing cart item key: " . $cart_item_key . " | Product ID: " . $cart_item['product_id'] . " | Qty: " . $cart_item['quantity']);
                            }

                            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                            /** Filter the product name. @since 2.1.0 */
                            $product_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);

                            if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                                $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                                ?>
                                <tr class="woocommerce-cart-form__cart-item <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">

                                    <td class="product-remove">
                                        <?php
                                        echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            'woocommerce_cart_item_remove_link',
                                            sprintf(
                                                '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                                esc_url(wc_get_cart_remove_url($cart_item_key)),
                                                /* translators: %s is the product name */
                                                esc_attr(sprintf(__('Remove %s from cart', 'woocommerce'), wp_strip_all_tags($product_name))),
                                                esc_attr($product_id),
                                                esc_attr($_product->get_sku())
                                            ),
                                            $cart_item_key
                                        );
                                        ?>
                                    </td>

                                    <td class="product-thumbnail">
                                        <?php
                                        /** Filter the product thumbnail displayed in the WooCommerce cart. @since 2.1.0 */
                                        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);

                                        if (!$product_permalink) {
                                            echo $thumbnail; // PHPCS: XSS ok.
                                        } else {
                                            printf('<a href="%s">%s</a>', esc_url($product_permalink), $thumbnail); // PHPCS: XSS ok.
                                        }
                                        ?>
                                    </td>

                                    <td class="product-name"
                                        data-title="<?php esc_attr_e('Product', 'woocommerce'); ?>">
                                        <?php
                                        if (!$product_permalink) {
                                            echo wp_kses_post($product_name . '&nbsp;');
                                        } else {
                                            /** This filter is documented above. @since 2.1.0 */
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product_permalink), $_product->get_name()), $cart_item, $cart_item_key));
                                        }

                                        do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);

                                        // Meta data.
                                        echo wc_get_formatted_cart_item_data($cart_item); // PHPCS: XSS ok.

                                        // Backorder notification.
                                        if ($_product->backorders_require_notification() && $_product->is_on_backorder($cart_item['quantity'])) {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                                        }
                                        ?>
                                    </td>

                                    <td class="product-price" data-title="<?php esc_attr_e('Price', 'woocommerce'); ?>">
                                        <?php
                                        echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key); // PHPCS: XSS ok.
                                        ?>
                                    </td>

                                    <td class="product-quantity"
                                        data-title="<?php esc_attr_e('Quantity', 'woocommerce'); ?>">
                                        <?php
                                        if ($_product->is_sold_individually()) {
                                            $min_quantity = 1;
                                            $max_quantity = 1;
                                        } else {
                                            /* translators: %s: Quantity. */
                                            $min_quantity = 0;
                                            $max_quantity = $_product->get_max_purchase_quantity();
                                        }

                                        $product_quantity = woocommerce_quantity_input(
                                            array(
                                                'input_name' => "cart[{$cart_item_key}][qty]",
                                                'input_value' => $cart_item['quantity'],
                                                'max_value' => $max_quantity,
                                                'min_value' => $min_quantity,
                                                'product_name' => $product_name,
                                            ),
                                            $_product,
                                            false
                                        );

                                        echo apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item); // PHPCS: XSS ok.
                                        ?>
                                    </td>

                                    <td class="product-subtotal"
                                        data-title="<?php esc_attr_e('Subtotal', 'woocommerce'); ?>">
                                        <?php
                                        echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); // PHPCS: XSS ok.
                                        ?>
                                    </td>
                                </tr>
                                <?php
                            } else {
                                // APW Woo Plugin: Log if a cart item is not visible
                                if ($apw_debug_mode && $apw_log_exists) {
                                    apw_woo_log("CART TEMPLATE: Cart item key: " . $cart_item_key . " is not visible or invalid. Skipping display.");
                                }
                            }
                        } // End foreach cart item loop

                        // APW Woo Plugin: Log after cart item loop
                        if ($apw_debug_mode && $apw_log_exists) {
                            apw_woo_log("CART TEMPLATE: Finished cart items loop.");
                        }
                        ?>

                        <?php do_action('woocommerce_cart_contents'); ?>

                        <tr>
                            <td colspan="6" class="actions">

                                <?php if (wc_coupons_enabled()) { ?>
                                    <div class="coupon">
                                        <label for="coupon_code"
                                               class="screen-reader-text"><?php esc_html_e('Coupon:', 'woocommerce'); ?></label>
                                        <input type="text" name="coupon_code" class="input-text" id="coupon_code"
                                               value=""
                                               placeholder="<?php esc_attr_e('Coupon code', 'woocommerce'); ?>"/>
                                        <button type="submit"
                                                class="button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>"
                                                name="apply_coupon"
                                                value="<?php esc_attr_e('Apply coupon', 'woocommerce'); ?>"><?php esc_html_e('Apply coupon', 'woocommerce'); ?></button>
                                        <?php do_action('woocommerce_cart_coupon'); ?>
                                    </div>
                                <?php } ?>

                                <button type="submit"
                                        class="button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>"
                                        name="update_cart"
                                        value="<?php esc_attr_e('Update cart', 'woocommerce'); ?>"><?php esc_html_e('Update cart', 'woocommerce'); ?></button>

                                <?php do_action('woocommerce_cart_actions'); ?>

                                <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                            </td>
                        </tr>

                        <?php do_action('woocommerce_after_cart_contents'); ?>
                        </tbody>
                    </table>
                    <?php do_action('woocommerce_after_cart_table'); ?>
                </form>

                <?php do_action('woocommerce_before_cart_collaterals'); ?>

                <div class="cart-collaterals">
                    <?php
                    /**
                     * Cart collaterals hook.
                     * @hooked woocommerce_cross_sell_display
                     * @hooked woocommerce_cart_totals - 10
                     */
                    do_action('woocommerce_cart_collaterals');
                    ?>
                </div>

                <?php do_action('woocommerce_after_cart'); ?>

                <?php
                /**
                 * Hook: apw_woo_after_cart_content
                 */
                do_action('apw_woo_after_cart_content');
                ?>
            </div> <!-- /.col.apw-woo-content-wrapper -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</main><!-- /#main -->
<?php

// APW Woo Plugin: Log end of cart template if debug mode is on
if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CART TEMPLATE: Finished rendering custom cart template with theme structure.');
}

get_footer();
?>
<!-- APW-WOO-TEMPLATE: Custom cart.php (structured) is loaded -->
