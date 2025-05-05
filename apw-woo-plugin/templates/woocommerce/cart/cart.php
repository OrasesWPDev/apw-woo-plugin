<?php
/**
 * Cart Page - APW WooCommerce Plugin Override with Structure, Debug Logging & Custom Classes
 *
 * This template overrides the default WooCommerce cart template, applying the
 * standard page structure (header, footer, block, container) used in this plugin.
 * It renders the header block using the same direct shortcode method as single-product.php.
 * It maintains the core structure and hooks of the original WooCommerce template (version 7.9.0)
 * while adding 'apw-woo-' prefixed CSS classes for custom styling and adjusting the actions area structure.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 7.9.0-apw.8 // Increment version
 *
 * Original WooCommerce template version: 7.9.0
 */

defined('ABSPATH') || exit;

// Register our template locator to use custom cart-totals.php
if (!function_exists('apw_woo_locate_cart_template')) {
    function apw_woo_locate_cart_template($template, $template_name, $template_path)
    {
        // Only modify cart-totals.php
        if ($template_name !== 'cart/cart-totals.php') {
            return $template;
        }

        // Check if our custom template exists
        $plugin_template = APW_WOO_PLUGIN_DIR . 'templates/woocommerce/' . $template_name;

        if (file_exists($plugin_template)) {
            if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                apw_woo_log('CART TEMPLATE: Using custom cart-totals.php template from plugin');
            }
            return $plugin_template;
        }

        return $template;
    }

    add_filter('woocommerce_locate_template', 'apw_woo_locate_cart_template', 10, 3);
}

// APW Woo Plugin: Log cart template loading if debug mode is on
$apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
$apw_log_exists = function_exists('apw_woo_log');

if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CART TEMPLATE: Loading custom cart template: templates/woocommerce/cart/cart.php with theme structure, APW classes, and revised actions structure');
}

get_header();

$target_block_id = 'third-level-woo-page-header'; // Same as single-product.php
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
    <div class="container apw-woo-cart-container">
        <div class="row apw-woo-cart-row">
            <div class="col apw-woo-content-wrapper">
                <?php
                /**
                 * Hook: apw_woo_before_cart_content
                 */
                do_action('apw_woo_before_cart_content');

                /**
                 * Hook: woocommerce_before_cart.
                 * @hooked woocommerce_output_all_notices - 10 // Removed by plugin?
                 * @hooked wc_print_notices - 10 // Moved to apw-woo-notices-container
                 */
                do_action('woocommerce_before_cart');
                ?>

                <form class="apw-woo-cart-form woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>"
                      method="post">
                    <?php do_action('woocommerce_before_cart_table'); ?>

                    <table class="apw-woo-cart-table shop_table shop_table_responsive cart woocommerce-cart-form__contents"
                           cellspacing="0">
                        <thead class="apw-woo-cart-thead">
                        <tr class="apw-woo-cart-header-row">
                            <th class="apw-woo-product-remove product-remove"><span
                                        class="screen-reader-text"><?php esc_html_e('Remove item', 'woocommerce'); ?></span>
                            </th>
                            <th class="apw-woo-product-thumbnail product-thumbnail"><span
                                        class="screen-reader-text"><?php esc_html_e('Thumbnail image', 'woocommerce'); ?></span>
                            </th>
                            <th class="apw-woo-product-name product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                            <th class="apw-woo-product-price product-price"><?php esc_html_e('Price', 'woocommerce'); ?></th>
                            <th class="apw-woo-product-quantity product-quantity"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                            <th class="apw-woo-product-subtotal product-subtotal"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
                        </tr>
                        </thead>
                        <tbody class="apw-woo-cart-tbody">
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
                                <tr class="apw-woo-cart-item woocommerce-cart-form__cart-item <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">

                                    <td class="apw-woo-product-remove product-remove">
                                        <?php
                                        echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            'woocommerce_cart_item_remove_link',
                                            sprintf(
                                                '<a href="%s" class="apw-woo-remove remove" aria-label="%s" data-product_id="%s" data-product_sku="%s" data-cart_item_key="%s">&times;</a>',
                                                esc_url(wc_get_cart_remove_url($cart_item_key)),
                                                /* translators: %s is the product name */
                                                esc_attr(sprintf(__('Remove %s from cart', 'woocommerce'), wp_strip_all_tags($product_name))),
                                                esc_attr($product_id),
                                                esc_attr($_product->get_sku()),
                                                esc_attr($cart_item_key)
                                            ),
                                            $cart_item_key
                                        );
                                        ?>
                                    </td>

                                    <td class="apw-woo-product-thumbnail product-thumbnail">
                                        <?php
                                        /** Filter the product thumbnail displayed in the WooCommerce cart. @since 2.1.0 */
                                        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image('woocommerce_thumbnail', ['class' => 'apw-woo-cart-item-thumbnail-img']), $cart_item, $cart_item_key); // Added class

                                        if (!$product_permalink) {
                                            echo $thumbnail; // PHPCS: XSS ok.
                                        } else {
                                            // Added class to link
                                            printf('<a href="%s" class="apw-woo-cart-item-thumbnail-link">%s</a>', esc_url($product_permalink), $thumbnail); // PHPCS: XSS ok.
                                        }
                                        ?>
                                    </td>

                                    <td class="apw-woo-product-name product-name"
                                        data-title="<?php esc_attr_e('Product', 'woocommerce'); ?>">
                                        <?php
                                        if (!$product_permalink) {
                                            // Added class to product name span/text
                                            echo '<span class="apw-woo-cart-item-name">' . wp_kses_post($product_name . '&nbsp;') . '</span>';
                                        } else {
                                            /** This filter is documented above. @since 2.1.0 */
                                            // Added class to product name link
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a class="apw-woo-cart-item-name-link" href="%s">%s</a>', esc_url($product_permalink), $_product->get_name()), $cart_item, $cart_item_key));
                                        }

                                        do_action('woocommerce_after_cart_item_name', $cart_item, $cart_item_key);

                                        // Meta data. Added wrapper div
                                        echo '<div class="apw-woo-cart-item-meta">';
                                        echo wc_get_formatted_cart_item_data($cart_item); // PHPCS: XSS ok.
                                        echo '</div>';

                                        // Backorder notification. Added class to p
                                        if ($_product->backorders_require_notification() && $_product->is_on_backorder($cart_item['quantity'])) {
                                            echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification', '<p class="apw-woo-backorder-notification backorder_notification">' . esc_html__('Available on backorder', 'woocommerce') . '</p>', $product_id));
                                        }
                                        ?>
                                    </td>

                                    <td class="apw-woo-product-price product-price"
                                        data-title="<?php esc_attr_e('Price', 'woocommerce'); ?>">
                                        <?php
                                        // Added wrapper span with class
                                        echo '<span class="apw-woo-cart-item-price">';
                                        echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key); // PHPCS: XSS ok.
                                        echo '</span>';
                                        ?>
                                    </td>

                                    <td class="apw-woo-product-quantity product-quantity"
                                        data-title="<?php esc_attr_e('Quantity', 'woocommerce'); ?>">
                                        <?php
                                        // Added wrapper div with class
                                        echo '<div class="apw-woo-quantity-input quantity">'; // Keep 'quantity' for WC JS maybe
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
                                                // Added apw-woo class to input
                                                'input_name' => "cart[{$cart_item_key}][qty]",
                                                'input_value' => $cart_item['quantity'],
                                                'max_value' => $max_quantity,
                                                'min_value' => $min_quantity,
                                                'product_name' => $product_name,
                                                'classes' => array('apw-woo-qty', 'input-text', 'qty', 'text') // Added apw-woo-qty
                                            ),
                                            $_product,
                                            false
                                        );

                                        echo apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item); // PHPCS: XSS ok.
                                        echo '</div>'; // Close apw-woo-quantity-input
                                        ?>
                                    </td>

                                    <td class="apw-woo-product-subtotal product-subtotal"
                                        data-title="<?php esc_attr_e('Subtotal', 'woocommerce'); ?>">
                                        <?php
                                        // Added wrapper span with class
                                        echo '<span class="apw-woo-cart-item-subtotal">';
                                        echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); // PHPCS: XSS ok.
                                        echo '</span>';
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

                        <tr class="apw-woo-cart-actions-row">
                            <td colspan="6" class="apw-woo-actions actions">

                                <?php // This hook usually adds the Continue Shopping button - Placed first for Flexbox layout ?>
                                <?php do_action('woocommerce_cart_actions'); ?>

                                <div class="apw-woo-actions-right"> <?php // Wrapper for right-aligned elements ?>

                                    <?php if (wc_coupons_enabled()) { ?>
                                        <div class="apw-woo-coupon coupon">
                                            <label for="coupon_code"
                                                   class="apw-woo-coupon-label screen-reader-text"><?php esc_html_e('Coupon:', 'woocommerce'); ?></label>
                                            <input type="text" name="coupon_code" class="apw-woo-coupon-code input-text"
                                                   id="coupon_code" value=""
                                                   placeholder="<?php esc_attr_e('Coupon code', 'woocommerce'); ?>"/>
                                            <?php do_action('woocommerce_cart_coupon'); // Hook for plugins ?>
                                        </div>
                                    <?php } ?>

                                    <div class="apw-woo-action-buttons"> <?php // Wrapper for side-by-side buttons ?>
                                        <?php if (wc_coupons_enabled()) { ?>
                                            <button type="submit"
                                                    class="apw-woo-apply-coupon-button button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>"
                                                    name="apply_coupon"
                                                    value="<?php esc_attr_e('Apply coupon', 'woocommerce'); ?>"><?php esc_html_e('Apply coupon', 'woocommerce'); ?></button>
                                        <?php } ?>

                                        <button type="submit"
                                                class="button<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>"
                                                name="update_cart"
                                                value="<?php esc_attr_e('Update cart', 'woocommerce'); ?>"><?php esc_html_e('Update cart', 'woocommerce'); ?></button>
                                    </div><?php // <!-- END BUTTON WRAPPER --> ?>

                                </div> <?php // <!-- END RIGHT WRAPPER --> ?>

                                <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                            </td>
                        </tr>

                        <?php do_action('woocommerce_after_cart_contents'); ?>
                        </tbody>
                    </table>
                    <?php // do_action('woocommerce_after_cart_table'); // Intentionally commented out by user ?>
                </form>

                <?php do_action('woocommerce_before_cart_collaterals'); ?>

                <div class="apw-woo-cart-collaterals cart-collaterals">
                    <?php
                    /**
                     * Cart collaterals hook.
                     * @hooked woocommerce_cross_sell_display - 10 (Removed by user request)
                     * @hooked woocommerce_cart_totals - 10
                     */
                    // do_action('woocommerce_cart_collaterals'); // Cross-sells removed as requested
                    woocommerce_cart_totals(); // Directly call cart totals
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
        </div> <!-- /.row.apw-woo-cart-row -->
    </div> <!-- /.container.apw-woo-cart-container -->
</main><!-- /#main -->
<?php
// APW Woo Plugin: Log end of cart template if debug mode is on
if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('CART TEMPLATE: Finished rendering custom cart template with theme structure, APW classes, and revised actions structure.');
}

get_footer();
?>
<!-- APW-WOO-TEMPLATE: Custom cart.php (structured & revised actions) is loaded -->
