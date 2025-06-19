/**
 * APW WooCommerce Dynamic Pricing JavaScript
 * Handles AJAX updating of product prices based on quantity
 */
(function ($) {
    'use strict';

    // Debug logging function with timestamp
    function logWithTimestamp(message) {
        if (typeof apwWooDynamicPricing !== 'undefined' && apwWooDynamicPricing.debug_mode) {
            console.log('[' + new Date().toLocaleTimeString() + '] DYNAMIC PRICING: ' + message);
        }
    }

    // Debug logging function that only logs when debug is enabled
    function debugLog() {
        if (typeof apwWooDynamicPricing !== 'undefined' && apwWooDynamicPricing.debug_mode) {
            console.log('[APW Dynamic Pricing]', ...arguments);
        }
    }

    // Error logging function that always logs errors
    function errorLog() {
        console.error.apply(console, arguments);
    }

    // Function to update threshold messages based on actual discount rules
    function updateThresholdMessages(messages) {
        // Find or create message container
        let $messageContainer = $('.apw-woo-threshold-messages');

        if (!$messageContainer.length) {
            // Create container between quantity/price and add to cart button
            $messageContainer = $('<div class="apw-woo-threshold-messages"></div>');

            // Try to insert after quantity row or price display
            let $insertAfter = $('.quantity').last();
            if (!$insertAfter.length) {
                $insertAfter = $('.apw-woo-price-display').last();
            }
            if (!$insertAfter.length) {
                $insertAfter = $('.price').last();
            }

            if ($insertAfter.length) {
                $insertAfter.after($messageContainer);
                debugLog('Created threshold messages container after: ' + $insertAfter[0].className);
            } else {
                // Fallback: insert before add to cart button
                const $addToCartButton = $('.single_add_to_cart_button, .add_to_cart_button').first();
                if ($addToCartButton.length) {
                    $addToCartButton.before($messageContainer);
                    debugLog('Created threshold messages container before add to cart button');
                } else {
                    debugLog('Could not find suitable location for threshold messages container');
                    return;
                }
            }
        }

        // Clear existing messages
        $messageContainer.empty();

        if (messages && messages.length > 0) {
            logWithTimestamp('Displaying ' + messages.length + ' threshold messages');

            messages.forEach(function (messageData) {
                const messageClass = 'apw-threshold-message apw-threshold-' + messageData.type;
                const messageHtml = '<div class="' + messageClass + '">' +
                    '<span class="message-icon">✓</span> ' +
                    '<span class="message-text">' + messageData.message + '</span>' +
                    '</div>';

                $messageContainer.append(messageHtml);

                logWithTimestamp('Added ' + messageData.type + ' message: ' + messageData.message);
            });

            // Show container with faster animation for immediate visibility
            $messageContainer.fadeIn(200);
        } else {
            // Hide container if no messages
            $messageContainer.fadeOut(100);
            logWithTimestamp('No threshold messages to display - hiding container');
        }
    }

    // Function to check threshold messages via AJAX with debouncing
    let thresholdCheckTimeout = null;
    let pendingThresholdRequest = null;
    let lastCheckedQuantity = null;
    let lastCheckedProductId = null;
    
    function checkThresholdMessages(productId, quantity) {
        if (!productId || quantity < 1) {
            debugLog('Invalid product ID or quantity for threshold check');
            return;
        }

        // Prevent duplicate calls for same product/quantity
        if (productId === lastCheckedProductId && quantity === lastCheckedQuantity) {
            debugLog('Skipping duplicate threshold check for product ' + productId + ', qty ' + quantity);
            return;
        }

        debugLog('Checking threshold messages for product ' + productId + ' with quantity ' + quantity);

        // Cancel any pending threshold check
        if (thresholdCheckTimeout) {
            clearTimeout(thresholdCheckTimeout);
        }

        // Abort any pending AJAX request
        if (pendingThresholdRequest) {
            pendingThresholdRequest.abort();
            pendingThresholdRequest = null;
        }

        // Store current values to prevent duplicates
        lastCheckedProductId = productId;
        lastCheckedQuantity = quantity;

        // Make immediate request for faster response
        pendingThresholdRequest = $.ajax({
            type: 'POST',
            url: apwWooDynamicPricing.ajax_url,
            data: {
                action: 'apw_woo_get_threshold_messages',
                nonce: apwWooDynamicPricing.threshold_nonce,
                product_id: productId,
                quantity: quantity
            },
            success: function (response) {
                debugLog('Threshold AJAX response received:', response);

                if (response.success && response.data) {
                    logWithTimestamp('Threshold check successful for qty ' + quantity);
                    updateThresholdMessages(response.data.threshold_messages || []);
                } else {
                    errorLog('Threshold check failed:', response);
                    updateThresholdMessages([]); // Clear messages on failure
                }
                pendingThresholdRequest = null;
            },
            error: function (xhr, status, error) {
                if (xhr.statusText !== 'abort') {
                    errorLog('Threshold AJAX error:', error);
                    updateThresholdMessages([]); // Clear messages on error
                }
                pendingThresholdRequest = null;
            }
        });
    }

    // Initialize when document is ready
    $(document).ready(function () {
        debugLog('APW WooCommerce Dynamic Pricing JS file loaded');
        
        // Global form submission prevention for cart forms when Enter is pressed in quantity fields
        $(document).on('submit', 'form.cart', function (e) {
            // Check if the form submission was triggered by Enter key in a quantity input
            const activeElement = document.activeElement;
            if (activeElement && 
                (activeElement.type === 'number' || 
                 activeElement.classList.contains('qty') ||
                 activeElement.name === 'quantity' ||
                 $(activeElement).closest('.quantity').length > 0)) {
                
                debugLog('Form submission prevented - triggered by quantity input');
                e.preventDefault();
                e.stopPropagation();
                
                // Trigger price update instead
                const qty = parseInt($(activeElement).val(), 10) || 1;
                debugLog('Triggering price update for quantity: ' + qty);
                
                return false;
            }
        });
        // Add this near the top of the document ready function
        debugLog('Analyzing DOM structure for quantity inputs:');
        debugLog('- form.cart exists: ' + ($('form.cart').length > 0));
        debugLog('- .quantity exists: ' + ($('.quantity').length > 0));
        debugLog('- All inputs: ' + $('input').length);
        debugLog('- Number inputs: ' + $('input[type="number"]').length);
        $('input').each(function () {
            debugLog('Input: ' + this.name + ' (type: ' + $(this).attr('type') + ')');
        });

        if (typeof apwWooDynamicPricing === 'undefined') {
            errorLog('Dynamic Pricing data not available');
            return;
        }

        // Find quantity input on single product page with expanded selectors
        // ORIGINAL (not finding anything):
        // const $quantityInput = $('form.cart .quantity input.qty, .single_variation_wrap .quantity input.qty');

        // UPDATED with more comprehensive selectors:
        const $quantityInput = $('input.qty, [name="quantity"], form.cart .quantity input, .quantity input[type="number"], .woocommerce-variation-add-to-cart .quantity input');

        // Log specific details about quantity inputs for debugging
        if ($('input.qty').length) debugLog('Found qty input with input.qty selector');
        if ($('[name="quantity"]').length) debugLog('Found qty input with [name="quantity"] selector');
        if ($('form.cart .quantity input').length) debugLog('Found qty input with form.cart .quantity input selector');
        if ($('.quantity input[type="number"]').length) debugLog('Found qty input with .quantity input[type="number"] selector');

        // Find price elements with expanded selectors for single product pages
        let $priceDisplay = $(apwWooDynamicPricing.price_selector);

        // If no price elements found, try comprehensive alternative selectors
        if (!$priceDisplay.length) {
            $priceDisplay = $('.woocommerce-Price-amount, .price .amount, .product p.price, .product-info .price-wrapper .amount, .apw-woo-price-display, .single-product .price, .product-summary .price .amount, .woocommerce-variation-price .amount');
            debugLog('Using fallback price selectors, found: ' + $priceDisplay.length + ' elements');
        }

        // Additional fallback for APW-specific price displays
        if (!$priceDisplay.length) {
            $priceDisplay = $('.apw-woo-add-to-cart-wrapper .price, .apw-woo-product-summary .price, .woocommerce div.product p.price');
            debugLog('Using APW-specific price selectors, found: ' + $priceDisplay.length + ' elements');
        }

        // Log detailed information for debugging
        debugLog('Price elements found: ' + $priceDisplay.length);
        debugLog('Quantity inputs found: ' + $quantityInput.length);

        // Ensure we have both elements
        if (!$quantityInput.length || !$priceDisplay.length) {
            errorLog('Required elements not found for dynamic pricing');

            // Try one more approach - add a quantity change listener to any elements that might be quantity inputs
            debugLog('Adding global quantity change listeners as fallback');

            // Add global Enter key prevention for quantity inputs
            $(document).on('keydown', 'input[type="number"], .quantity input, input.qty, [name="quantity"]', function (e) {
                if (e.which === 13 || e.keyCode === 13) { // Enter key
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const newQty = parseInt($(this).val(), 10) || 1;
                    debugLog('Global Enter key handler - quantity input with value: ' + newQty);
                    
                    // Trigger price update instead of form submission
                    updatePrice(newQty);
                    return false;
                }
            });

            // Add listeners to any input that might be a quantity input
            $(document).on('change keyup input', 'input[type="number"], .quantity input', function (e) {
                // Skip if this was triggered by Enter key
                if (e.which === 13 || e.keyCode === 13) {
                    return;
                }
                
                debugLog('Potential quantity input changed: ' + $(this).val());
                const newQty = parseInt($(this).val(), 10) || 1;
                updatePrice(newQty);
            });

            // Listen for clicks on any elements that might be quantity buttons
            $(document).on('click', '.quantity .plus, .quantity .minus, .quantity button, .quantity a.plus, .quantity a.minus', function () {
                debugLog('Potential quantity button clicked');
                setTimeout(function () {
                    const newQty = parseInt($('input[type="number"], .quantity input').val(), 10) || 1;
                    debugLog('After button click, quantity appears to be: ' + newQty);
                    updatePrice(newQty);
                }, 100);
            });

            // Try to find quantity again after DOM is fully loaded
            $(window).on('load', function () {
                const $lateQuantityInput = $('input.qty, [name="quantity"], .quantity input');
                if ($lateQuantityInput.length) {
                    debugLog('Found quantity input after window load: ' + $lateQuantityInput.length);
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

        // Get product ID from multiple possible sources with enhanced detection
        let productId = $priceDisplay.data('product-id');

        // If not found in price display, try multiple fallbacks
        if (!productId) {
            // Try form data attributes and inputs
            productId = $('form.cart').data('product_id') ||
                $('form.cart').data('product-id') ||
                $('form.cart input[name="product_id"]').val() ||
                $('input[name="add-to-cart"]').val() ||
                $('button.single_add_to_cart_button').val() ||
                $('.single_add_to_cart_button').val();
        }

        // Enhanced product ID detection from page context
        if (!productId) {
            // Try to get from global WooCommerce data if available
            if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.product_id) {
                productId = wc_add_to_cart_params.product_id;
            }
            // Try body class detection
            else {
                const bodyClasses = $('body').attr('class');
                const productMatch = bodyClasses.match(/postid-(\d+)/);
                if (productMatch) {
                    productId = productMatch[1];
                    debugLog('Extracted product ID from body class: ' + productId);
                }
            }
        }

        // If still no product ID, try to get from URL
        if (!productId) {
            // Try to extract from URL (assumes /products/category/product-slug/ format)
            const urlParts = window.location.pathname.split('/');
            // Get the slug (usually second-to-last part of URL)
            const productSlug = urlParts[urlParts.length - 2];
            debugLog('Could not find product ID in DOM, extracted slug from URL: ' + productSlug);

            // We'll need to modify the AJAX request to use slug instead of ID
            // Set a flag to indicate we're using URL-based identification
            window.usingProductSlug = true;
        }

        if (!productId && !window.usingProductSlug) {
            errorLog('Product ID not available for dynamic pricing and could not extract from URL');
            return;
        }

        debugLog('Dynamic pricing initialized for product ID: ' + (productId || 'Using URL slug'));

        // Initialize with current quantity
        let currentQuantity = parseInt($quantityInput.val(), 10) || 1;
        let updateTimeout = null;
        let pendingPriceRequest = null;

        // Function to update price via AJAX
        function updatePrice(quantity) {
            // Don't update if quantity hasn't changed
            if (quantity === currentQuantity) {
                debugLog('Quantity unchanged, skipping update: ' + quantity);
                return;
            }

            debugLog('Updating price for quantity: ' + quantity + ', Product ID: ' + productId);
            
            // Cancel any pending AJAX request
            if (pendingPriceRequest) {
                pendingPriceRequest.abort();
                pendingPriceRequest = null;
            }
            
            currentQuantity = quantity;

            // Validate we have necessary data
            if (!productId && !window.usingProductSlug) {
                errorLog('Cannot update price: No product ID available');
                return;
            }

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
            pendingPriceRequest = $.ajax({
                type: 'POST',
                url: apwWooDynamicPricing.ajax_url,
                data: requestData,
                success: function (response) {
                    debugLog('AJAX response received:', response);

                    if (response.success && response.data) {
                        logWithTimestamp('Price updated successfully from ' + (response.data.original_price || 'unknown') + ' to ' + response.data.formatted_price);
                        debugLog('Full response data:', response.data);

                        // Get all potential price elements using the configured selector
                        var priceElements = $(apwWooDynamicPricing.price_selector);
                        logWithTimestamp('Found ' + priceElements.length + ' potential price elements');

                        // Filter out addon-related elements if addon exclusion selector is available
                        var mainPriceElements = priceElements;
                        if (apwWooDynamicPricing.addon_exclusion_selector) {
                            mainPriceElements = priceElements.not(apwWooDynamicPricing.addon_exclusion_selector + ' *');
                            logWithTimestamp('After excluding addons: ' + mainPriceElements.length + ' main price elements');
                        }

                        // Log each element being updated
                        var priceUpdated = false;
                        mainPriceElements.each(function (index) {
                            var element = $(this);
                            var oldPrice = element.html();
                            logWithTimestamp('Updating element ' + (index + 1) + ': ' + element.prop('tagName') +
                                ' with classes: ' + (element.attr('class') || 'none') +
                                ' | Old: ' + oldPrice + ' | New: ' + response.data.formatted_price);

                            element.html(response.data.formatted_price);
                            priceUpdated = true;
                        });

                        // Log addon elements that were specifically excluded
                        if (apwWooDynamicPricing.addon_exclusion_selector) {
                            var addonElements = $(apwWooDynamicPricing.addon_exclusion_selector + ' .woocommerce-Price-amount, ' +
                                apwWooDynamicPricing.addon_exclusion_selector + ' .amount');
                            if (addonElements.length > 0) {
                                logWithTimestamp('Preserved ' + addonElements.length + ' addon price elements from dynamic pricing updates');
                                addonElements.each(function (index) {
                                    var element = $(this);
                                    logWithTimestamp('Preserved addon element ' + (index + 1) + ': ' +
                                        element.html() + ' (classes: ' + (element.attr('class') || 'none') + ')');
                                });
                            }
                        }

                        if (!priceUpdated) {
                            errorLog('No price elements were updated - check selectors');
                        }

                        // If we didn't have a product ID before but received one, store it
                        if (!productId && response.data.product_id) {
                            productId = response.data.product_id;
                            debugLog('Received product ID from server: ' + productId);
                            // We don't need the slug anymore
                            window.usingProductSlug = false;
                        }

                        // Check threshold messages after successful price update
                        checkThresholdMessages(productId, quantity);

                        // Trigger custom event for other scripts to react
                        $(document).trigger('apw_price_updated', [response.data]);
                    } else {
                        errorLog('Price update failed: Invalid response', response);
                        if (response.data && response.data.message) {
                            errorLog('Server message: ' + response.data.message);
                        }
                    }
                    pendingPriceRequest = null;
                },
                error: function (xhr, status, error) {
                    if (xhr.statusText !== 'abort') {
                        errorLog('Price update failed:', error);
                    }
                    pendingPriceRequest = null;
                },
                complete: function () {
                    // Remove loading indicator from all price elements
                    $priceDisplay.removeClass('updating');
                }
            });
        }

        // Prevent Enter key from submitting the form on quantity input
        $quantityInput.on('keydown', function (e) {
            if (e.which === 13 || e.keyCode === 13) { // Enter key
                e.preventDefault(); // Prevent form submission
                e.stopPropagation(); // Stop event bubbling
                
                const newQty = parseInt($(this).val(), 10) || 1;
                debugLog('Enter key pressed on quantity input with value: ' + newQty);
                
                // Trigger immediate price update instead of form submission
                if (newQty !== currentQuantity) {
                    // Clear any pending timeout
                    if (updateTimeout) {
                        clearTimeout(updateTimeout);
                    }
                    updatePrice(newQty);
                }
                
                return false; // Additional prevention of default behavior
            }
        });

        // Update when quantity changes (with small delay for better UX)
        $quantityInput.on('change keyup input', function (e) {
            // Skip if this was triggered by Enter key (already handled above)
            if (e.which === 13 || e.keyCode === 13) {
                return;
            }
            
            const newQty = parseInt($(this).val(), 10) || 1;
            debugLog('Quantity input changed to: ' + newQty);

            // Clear any pending updates
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

            // Faster response time - reduced from 150ms to 50ms for immediate threshold display
            updateTimeout = setTimeout(function () {
                if (newQty !== currentQuantity) {
                    updatePrice(newQty);
                    // Threshold messages are now checked automatically after price update
                }
            }, 50);
        });

        // Enhanced detection for quantity button clicks - including Flatsome theme buttons
        $(document).on('click', '.quantity .plus, .quantity .minus, .quantity button.plus, .quantity button.minus, .ux-quantity__button, input[type="button"].plus, input[type="button"].minus', function (e) {
            debugLog('Quantity button clicked: ' + ($(this).hasClass('plus') || $(this).hasClass('ux-quantity__button--plus') ? 'plus' : 'minus'));

            // Wait a brief moment for the quantity to update
            setTimeout(function () {
                // Re-query the input in case it's been replaced
                const $currentQtyInput = $('input.qty, [name="quantity"]').first();
                const newQty = parseInt($currentQtyInput.val(), 10) || 1;
                debugLog('New quantity after button click: ' + newQty);

                if (newQty !== currentQuantity) {
                    updatePrice(newQty);
                    // Threshold messages are now checked automatically after price update
                }
            }, 50); // Reduced delay for faster response
        });

        // Ensure price updates when variations are changed
        if ($('form.variations_form').length) {
            $('form.variations_form').on('found_variation', function (event, variation) {
                // If variation has its own ID, use that
                if (variation && variation.variation_id) {
                    productId = variation.variation_id;
                    debugLog('Variation selected, using ID: ' + productId);
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
                debugLog('Variation reset, reverting to product ID: ' + productId);

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
                            debugLog('Quantity changed through DOM mutation: ' + newQty);
                            updatePrice(newQty);
                        }
                    }
                }
            };

            const observer = new MutationObserver(callback);
            observer.observe(targetNode, config);
            debugLog('Quantity mutation observer initialized');
        }

        // Initial price update and threshold check on page load
        setTimeout(function () {
            // Force re-check current quantity in case it was changed before script loaded
            const actualQty = parseInt($quantityInput.val(), 10) || 1;
            if (actualQty !== currentQuantity) {
                debugLog('Initial quantity mismatch - updating from ' + currentQuantity + ' to ' + actualQty);
                currentQuantity = actualQty;
            }
            updatePrice(currentQuantity);
            // Threshold messages are now checked automatically after price update
        }, 200); // Reduced delay for faster initial load

        // Also check after full page load in case of slow loading
        $(window).on('load', function () {
            setTimeout(function () {
                const currentQtyValue = parseInt($quantityInput.val(), 10) || 1;
                if (currentQtyValue !== currentQuantity) {
                    debugLog('Window load quantity check - updating to: ' + currentQtyValue);
                    updatePrice(currentQtyValue);
                }
            }, 500);
        });

        // Focus only on single product page functionality
        debugLog('Single product page dynamic pricing initialized');
    });
})(jQuery);
