/**
 * APW WooCommerce Checkout JavaScript
 * 
 * Handles checkout page interactions including:
 * - Payment method change triggers
 * - Cart/checkout totals updates
 * - Form validation and submission
 * 
 * @package APW_Woo_Plugin
 * @since 1.17.6
 */

(function($) {
    'use strict';

    /**
     * Initialize checkout functionality
     */
    function initCheckout() {
        // Only run on checkout page
        if (!$('form.checkout').length) {
            return;
        }

        // Debug logging if enabled
        if (typeof apwWooCheckoutData !== 'undefined' && apwWooCheckoutData.debug_mode) {
            console.log('APW Checkout: Initializing checkout scripts');
        }

        // Update checkout when payment method changes
        $('form.checkout').on('change', 'input[name="payment_method"]', function() {
            if (typeof apwWooCheckoutData !== 'undefined' && apwWooCheckoutData.debug_mode) {
                console.log('APW Checkout: Payment method changed to', $(this).val());
            }
            $('body').trigger('update_checkout');
        });

        // Trigger initial update on page load
        $('body').trigger('update_checkout');
    }

    /**
     * Initialize cart functionality
     */
    function initCart() {
        // Only run on cart page
        if (!$('form.woocommerce-cart-form').length) {
            return;
        }

        // Debug logging if enabled
        if (typeof apwWooCheckoutData !== 'undefined' && apwWooCheckoutData.debug_mode) {
            console.log('APW Checkout: Initializing cart scripts');
        }

        // Trigger initial cart totals update
        $('body').trigger('update_cart_totals');
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initCheckout();
        initCart();
    });

    /**
     * Re-initialize on updated_checkout event (WooCommerce AJAX)
     */
    $(document.body).on('updated_checkout', function() {
        // Re-bind events after checkout updates
        if (typeof apwWooCheckoutData !== 'undefined' && apwWooCheckoutData.debug_mode) {
            console.log('APW Checkout: Checkout updated, re-binding events');
        }
        
        // Ensure payment method change handler is still bound
        $('form.checkout').off('change', 'input[name="payment_method"]').on('change', 'input[name="payment_method"]', function() {
            if (typeof apwWooCheckoutData !== 'undefined' && apwWooCheckoutData.debug_mode) {
                console.log('APW Checkout: Payment method changed to', $(this).val());
            }
            $('body').trigger('update_checkout');
        });
    });

})(jQuery);