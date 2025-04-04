/**
 * APW WooCommerce Dynamic Pricing JavaScript
 * Handles AJAX updating of product prices based on quantity
 */
(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        console.log('APW WooCommerce Dynamic Pricing JS file loaded');
        // Add this near the top of the document ready function
        console.log('Analyzing DOM structure for quantity inputs:');
        console.log('- form.cart exists: ' + ($('form.cart').length > 0));
        console.log('- .quantity exists: ' + ($('.quantity').length > 0));
        console.log('- All inputs: ' + $('input').length);
        console.log('- Number inputs: ' + $('input[type="number"]').length);
        $('input').each(function () {
            console.log('Input: ' + this.name + ' (type: ' + $(this).attr('type') + ')');
        });

        if (typeof apwWooDynamicPricing === 'undefined') {
            console.error('Dynamic Pricing data not available');
            return;
        }

        // Find quantity input on single product page with expanded selectors
        // ORIGINAL (not finding anything):
        // const $quantityInput = $('form.cart .quantity input.qty, .single_variation_wrap .quantity input.qty');

        // UPDATED with more comprehensive selectors:
        const $quantityInput = $('input.qty, [name="quantity"], form.cart .quantity input, .quantity input[type="number"], .woocommerce-variation-add-to-cart .quantity input');

        // Log specific details about quantity inputs for debugging
        if ($('input.qty').length) console.log('Found qty input with input.qty selector');
        if ($('[name="quantity"]').length) console.log('Found qty input with [name="quantity"] selector');
        if ($('form.cart .quantity input').length) console.log('Found qty input with form.cart .quantity input selector');
        if ($('.quantity input[type="number"]').length) console.log('Found qty input with .quantity input[type="number"] selector');

        // Find price elements with expanded selectors
        let $priceDisplay = $(apwWooDynamicPricing.price_selector);

        // If no price elements found, try alternative selectors
        if (!$priceDisplay.length) {
            $priceDisplay = $('.woocommerce-Price-amount, .price .amount, .product p.price, .product-info .price-wrapper .amount');
            console.log('Using fallback price selectors, found: ' + $priceDisplay.length + ' elements');
        }

        // Log detailed information for debugging
        console.log('Price elements found: ' + $priceDisplay.length);
        console.log('Quantity inputs found: ' + $quantityInput.length);

        // Ensure we have both elements
        if (!$quantityInput.length || !$priceDisplay.length) {
            console.error('Required elements not found for dynamic pricing');

            // Try one more approach - add a quantity change listener to any elements that might be quantity inputs
            console.log('Adding global quantity change listeners as fallback');

            // Add listeners to any input that might be a quantity input
            $(document).on('change keyup input', 'input[type="number"], .quantity input', function () {
                console.log('Potential quantity input changed: ' + $(this).val());
                const newQty = parseInt($(this).val(), 10) || 1;
                updatePrice(newQty);
            });

            // Listen for clicks on any elements that might be quantity buttons
            $(document).on('click', '.quantity .plus, .quantity .minus, .quantity button, .quantity a.plus, .quantity a.minus', function () {
                console.log('Potential quantity button clicked');
                setTimeout(function () {
                    const newQty = parseInt($('input[type="number"], .quantity input').val(), 10) || 1;
                    console.log('After button click, quantity appears to be: ' + newQty);
                    updatePrice(newQty);
                }, 100);
            });

            // Try to find quantity again after DOM is fully loaded
            $(window).on('load', function () {
                const $lateQuantityInput = $('input.qty, [name="quantity"], .quantity input');
                if ($lateQuantityInput.length) {
                    console.log('Found quantity input after window load: ' + $lateQuantityInput.length);
                    $quantityInput = $lateQuantityInput;

                    // Setup normal change handlers
                    $quantityInput.on('change keyup input', function () {
                        const newQty = parseInt($(this).val(), 10) || 1;
                        updatePrice(newQty);
                    });
                }
            });

            return;
        }

        // Get product ID from multiple possible sources
        let productId = $priceDisplay.data('product-id');

        // If not found in price display, try multiple fallbacks
        if (!productId) {
            productId = $('form.cart').data('product_id') ||
                $('form.cart input[name="product_id"]').val() ||
                $('input[name="add-to-cart"]').val() ||
                $('button.single_add_to_cart_button').val();
        }

        // If still no product ID, try to get from URL
        if (!productId) {
            // Try to extract from URL (assumes /products/category/product-slug/ format)
            const urlParts = window.location.pathname.split('/');
            // Get the slug (usually second-to-last part of URL)
            const productSlug = urlParts[urlParts.length - 2];
            console.log('Could not find product ID in DOM, extracted slug from URL: ' + productSlug);

            // We'll need to modify the AJAX request to use slug instead of ID
            // Set a flag to indicate we're using URL-based identification
            window.usingProductSlug = true;
        }

        if (!productId && !window.usingProductSlug) {
            console.error('Product ID not available for dynamic pricing and could not extract from URL');
            return;
        }

        console.log('Dynamic pricing initialized for product ID: ' + (productId || 'Using URL slug'));

        // Initialize with current quantity
        let currentQuantity = parseInt($quantityInput.val(), 10) || 1;
        let updateTimeout = null;

        // Function to update price via AJAX
        function updatePrice(quantity) {
            // Don't update if quantity hasn't changed
            if (quantity === currentQuantity) {
                return;
            }

            console.log('Updating price for quantity: ' + quantity);
            currentQuantity = quantity;

            // Show loading indicator on all price elements
            $priceDisplay.addClass('updating');

            // Clear any pending updates
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

            // Prepare data for AJAX request
            const requestData = {
                action: 'apw_woo_get_dynamic_price',
                nonce: apwWooDynamicPricing.nonce,
                quantity: quantity
            };

            // Add either product ID or slug
            if (productId) {
                requestData.product_id = productId;
            } else if (window.usingProductSlug) {
                requestData.product_slug = productSlug;
            }

            // Make AJAX request
            $.ajax({
                type: 'POST',
                url: apwWooDynamicPricing.ajax_url,
                data: requestData,
                success: function (response) {
                    if (response.success && response.data) {
                        console.log('Price updated successfully', response.data);

                        // Update all price elements with new price
                        $priceDisplay.html(response.data.formatted_price);

                        // If we didn't have a product ID before but received one, store it
                        if (!productId && response.data.product_id) {
                            productId = response.data.product_id;
                            console.log('Received product ID from server: ' + productId);
                            // We don't need the slug anymore
                            window.usingProductSlug = false;
                        }

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
                    // Remove loading indicator from all price elements
                    $priceDisplay.removeClass('updating');
                }
            });
        }

        // Update when quantity changes (with small delay for better UX)
        $quantityInput.on('change keyup input', function () {
            const newQty = parseInt($(this).val(), 10) || 1;
            console.log('Quantity input changed to: ' + newQty);

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

        // Enhanced detection for quantity button clicks
        $(document).on('click', '.quantity .plus, .quantity .minus, .quantity button.plus, .quantity button.minus', function (e) {
            console.log('Quantity button clicked: ' + ($(this).hasClass('plus') ? 'plus' : 'minus'));

            // Wait a brief moment for the quantity to update
            setTimeout(function () {
                const newQty = parseInt($quantityInput.val(), 10) || 1;
                console.log('New quantity after button click: ' + newQty);

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
                console.log('Variation reset, reverting to product ID: ' + productId);

                // Reset current quantity to force update
                currentQuantity = 0;

                // Update with current quantity
                const newQty = parseInt($quantityInput.val(), 10) || 1;
                updatePrice(newQty);
            });
        }

        // Watch for DOM changes that might affect quantity or add/update input elements
        // This helps with themes that dynamically update the quantity field
        const targetNode = document.querySelector('form.cart');
        if (targetNode) {
            const config = {childList: true, subtree: true, attributes: true};
            const callback = function (mutationsList, observer) {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'childList' ||
                        (mutation.type === 'attributes' && mutation.target.matches('input.qty'))) {
                        // Check if quantity has changed
                        const newQty = parseInt($quantityInput.val(), 10) || 1;
                        if (newQty !== currentQuantity) {
                            console.log('Quantity changed through DOM mutation: ' + newQty);
                            updatePrice(newQty);
                        }
                    }
                }
            };

            const observer = new MutationObserver(callback);
            observer.observe(targetNode, config);
            console.log('Quantity mutation observer initialized');
        }

        // Initial price update on page load with small delay to ensure DOM is ready
        setTimeout(function () {
            updatePrice(currentQuantity);
        }, 300);
    });
})(jQuery);