/**
 * APW WooCommerce Dynamic Pricing JavaScript
 *
 * Handles AJAX updating of product prices based on quantity
 */
console.log('APW WooCommerce Dynamic Pricing JS file loaded');
(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        if (typeof apwWooDynamicPricing === 'undefined') {
            console.error('Dynamic Pricing data not available');
            return;
        }

        // Find quantity input on single product page
        const $quantityInput = $('form.cart .quantity input.qty');
        const $priceDisplay = $(apwWooDynamicPricing.price_selector);

        // Ensure we have both elements
        if (!$quantityInput.length || !$priceDisplay.length) {
            return;
        }

        // Get product ID from price display data attribute
        const productId = $priceDisplay.data('product-id');
        if (!productId) {
            console.error('Product ID not available');
            return;
        }

        // Initialize with current quantity
        let currentQuantity = parseInt($quantityInput.val(), 10) || 1;

        // Function to update price via AJAX
        function updatePrice(quantity) {
            // Don't update if quantity hasn't changed
            if (quantity === currentQuantity) {
                return;
            }

            currentQuantity = quantity;

            // Show loading indicator
            $priceDisplay.addClass('updating');

            // Make AJAX request
            $.ajax({
                type: 'POST',
                url: apwWooDynamicPricing.ajax_url,
                data: {
                    action: 'apw_woo_get_dynamic_price',
                    nonce: apwWooDynamicPricing.nonce,
                    product_id: productId,
                    quantity: quantity
                },
                success: function (response) {
                    if (response.success && response.data) {
                        // Update price display with new price
                        $priceDisplay.html(response.data.formatted_price);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Price update failed:', error);
                },
                complete: function () {
                    // Remove loading indicator
                    $priceDisplay.removeClass('updating');
                }
            });
        }

        // Update when quantity changes
        $quantityInput.on('change keyup input', function () {
            const newQty = parseInt($(this).val(), 10) || 1;
            if (newQty !== currentQuantity) {
                updatePrice(newQty);
            }
        });

        // Also capture quantity changes from the plus/minus buttons
        $('.quantity .plus, .quantity .minus').on('click', function () {
            // Wait a brief moment for the quantity to update
            setTimeout(function () {
                const newQty = parseInt($quantityInput.val(), 10) || 1;
                if (newQty !== currentQuantity) {
                    updatePrice(newQty);
                }
            }, 50);
        });

        // Ensure price updates when variations are changed
        if ($('form.variations_form').length) {
            $('form.variations_form').on('found_variation', function (event, variation) {
                // Reset current quantity to force update
                currentQuantity = 0;
                // Update with current quantity
                const newQty = parseInt($quantityInput.val(), 10) || 1;
                updatePrice(newQty);
            });
        }
    });

})(jQuery);