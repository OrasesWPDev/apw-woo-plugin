/**
 * APW WooCommerce Dynamic Pricing JavaScript
 *
 * Handles AJAX updating of product prices based on quantity
 */
(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        console.log('APW WooCommerce Dynamic Pricing JS file loaded');

        if (typeof apwWooDynamicPricing === 'undefined') {
            console.error('Dynamic Pricing data not available');
            return;
        }

        // Find quantity input on single product page
        const $quantityInput = $('form.cart .quantity input.qty');
        const $priceDisplay = $(apwWooDynamicPricing.price_selector);

        // Ensure we have both elements
        if (!$quantityInput.length || !$priceDisplay.length) {
            console.log('Required elements not found for dynamic pricing');
            return;
        }

        // Get product ID from price display data attribute or from the form
        let productId = $priceDisplay.data('product-id');

        // If not found in price display, try to get from the form
        if (!productId) {
            productId = $('form.cart').data('product_id') || $('form.cart input[name="product_id"]').val();
        }

        // Still no product ID? Try other selectors
        if (!productId) {
            productId = $('input[name="add-to-cart"]').val();
        }

        if (!productId) {
            console.error('Product ID not available for dynamic pricing');
            return;
        }

        console.log('Dynamic pricing initialized for product ID: ' + productId);

        // Initialize with current quantity
        let currentQuantity = parseInt($quantityInput.val(), 10) || 1;
        let updateTimeout = null;

        // Function to update price via AJAX
        function updatePrice(quantity) {
            // Don't update if quantity hasn't changed
            if (quantity === currentQuantity) {
                return;
            }

            currentQuantity = quantity;

            // Show loading indicator
            $priceDisplay.addClass('updating');

            // Clear any pending updates
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

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
                        console.log('Price updated successfully', response.data);

                        // Update price display with new price
                        $priceDisplay.html(response.data.formatted_price);

                        // Trigger custom event for other scripts to react
                        $(document).trigger('apw_price_updated', [response.data]);
                    } else {
                        console.error('Price update failed: Invalid response', response);
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

        // Update when quantity changes (with small delay for better UX)
        $quantityInput.on('change keyup input', function () {
            const newQty = parseInt($(this).val(), 10) || 1;

            // Clear any pending updates
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

            // Set a small delay before updating to prevent excessive requests
            updateTimeout = setTimeout(function () {
                if (newQty !== currentQuantity) {
                    updatePrice(newQty);
                }
            }, 300);
        });

        // Also capture quantity changes from the plus/minus buttons
        $('.quantity .plus, .quantity .minus').on('click', function () {
            // Wait a brief moment for the quantity to update
            setTimeout(function () {
                const newQty = parseInt($quantityInput.val(), 10) || 1;
                if (newQty !== currentQuantity) {
                    updatePrice(newQty);
                }
            }, 100);
        });

        // Ensure price updates when variations are changed
        if ($('form.variations_form').length) {
            $('form.variations_form').on('found_variation', function (event, variation) {
                // If variation has its own ID, use that
                if (variation && variation.variation_id) {
                    productId = variation.variation_id;
                    console.log('Variation selected, using ID: ' + productId);
                }

                // Reset current quantity to force update
                currentQuantity = 0;

                // Update with current quantity
                const newQty = parseInt($quantityInput.val(), 10) || 1;
                updatePrice(newQty);
            });

            // Also handle reset event
            $('form.variations_form').on('reset_data', function () {
                // Revert to main product ID from the form
                productId = $('form.cart').data('product_id');

                // Reset current quantity to force update
                currentQuantity = 0;

                // Update with current quantity
                const newQty = parseInt($quantityInput.val(), 10) || 1;
                updatePrice(newQty);
            });
        }

        // Initial price update on page load
        updatePrice(currentQuantity);
    });

})(jQuery);