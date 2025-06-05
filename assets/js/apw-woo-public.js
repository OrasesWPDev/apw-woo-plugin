/**
 * APW WooCommerce Plugin Public Scripts
 * - Handles moving WooCommerce notices to a custom container.
 * - Hides duplicate elements added by Avalara on the checkout page.
 * - Updates cart quantity indicators.
 */
(function ($) {
    'use strict';

    // --- Global Debug Log Helper ---
    // Ensure apwWooData is available or provide a fallback
    window.apwWooData = window.apwWooData || {debug_mode: false};

    window.apwWooLog = function (message) {
        // Check if debug_mode is explicitly true in the localized data
        if (window.apwWooData && window.apwWooData.debug_mode === true && console) {
            console.log('APW Log:', message);
        }
    };

    // --- Notice Handling ---

    // Function to move notices to our container
    function moveNoticesToContainer() {
        const $targetContainer = $('.apw-woo-notices-container');
        if (!$targetContainer.length) {
            if (!window.apwNoticeContainerNotFound) {
                console.error('APW Woo Plugin Error: Target notice container ".apw-woo-notices-container" not found.');
                window.apwNoticeContainerNotFound = true;
            }
            return false;
        }

        const noticeSelectors = [
            'body > .woocommerce-message', 'body > .woocommerce-error', 'body > .woocommerce-info',
            'body > .woocommerce-notice', 'body > .message-wrapper', '.woocommerce-notices-wrapper > *',
            'header.header ~ .woocommerce-message', 'header.header ~ .woocommerce-error',
            'header.header ~ .woocommerce-info', 'header.header ~ .message-wrapper',
            '.page-wrapper > .woocommerce-message', '.page-wrapper > .woocommerce-error',
            '.page-wrapper > .woocommerce-info', '.page-wrapper > .message-wrapper',
            '.message-container:not(.apw-woo-notices-container .message-container)',
            '.woocommerce-message.message-wrapper:not(.apw-woo-notices-container .message-wrapper)'
        ];
        // Corrected selector for direct children of .woocommerce-notices-wrapper
        noticeSelectors[5] = '.woocommerce-notices-wrapper > *';
        const combinedSelector = noticeSelectors.join(', ');

        const $notices = $(combinedSelector).filter(function () {
            return !$(this).closest('.apw-woo-notices-container').length;
        });

        if ($notices.length > 0) {
            // apwWooLog(`Found ${$notices.length} notices to move`); // Can be noisy
            $targetContainer.empty();
            $notices.each(function () {
                const $notice = $(this);
                // apwWooLog('Moving notice:', $notice[0]); // Can be noisy
                $notice.detach().appendTo($targetContainer).css({
                    'display': 'block', 'visibility': 'visible', 'opacity': '1', 'position': 'static'
                }).addClass('apw-woo-processed');
            });
            return true;
        }
        return false;
    }

    // Debug function to help diagnose notice issues (if needed)
    function debugNotices() {
        // Example: Log count of notices in target container
        // const noticeCount = $('.apw-woo-notices-container').children().length;
        // apwWooLog(`Debug Notices: Found ${noticeCount} notices in target container.`);
    }

    // --- Checkout Page Specific Adjustments ---

    // Function to hide unwanted Avalara elements on checkout (REFINED VERSION)
    function hideDuplicateAvalaraElements() {

        // Selector for the container where Avalara adds its notice/checkbox
        const fieldWrapperSelector = '.woocommerce-shipping-fields > .shipping_address > .woocommerce-shipping-fields__field-wrapper';

        // Selector for the duplicate H3 (inside the nested div)
        const duplicateCheckboxSelector = fieldWrapperSelector + ' h3#ship-to-different-address';

        // Selector for the button we WANT TO KEEP (inside .shipping_address)
        const keepButtonSelector = '.shipping_address button.wc_avatax_validate_address[data-address-type="shipping"]';

        // Selector for ALL shipping validate buttons on the page
        const allShippingButtonsSelector = 'button.wc_avatax_validate_address[data-address-type="shipping"]';

        var $wrapper = $(fieldWrapperSelector);

        // --- Hide the duplicate elements ---
        if ($wrapper.length) {
            // Hide the notice text node by wrapping it
            $wrapper.contents().filter(function () {
                return this.nodeType === 3 && this.nodeValue.trim().startsWith('AvaTax uses this email ID');
            }).each(function () {
                if (!$(this).parent().hasClass('apw-hidden-avatax-notice')) {
                    $(this).wrap('<span class="apw-hidden-avatax-notice" style="display: none !important;"></span>');
                    apwWooLog('JS Hide: Wrapped and hid Avalara notice text.');
                }
            });

            // Hide the duplicate H3 checkbox section added by Avalara inside the wrapper
            const $duplicateCheckbox = $(duplicateCheckboxSelector);
            if ($duplicateCheckbox.length > 0 && $duplicateCheckbox.is(':visible')) {
                $duplicateCheckbox.hide();
                apwWooLog('JS Hide: Duplicate checkbox H3 hidden.');
            }
        } else {
            // Log if the primary wrapper isn't found when expected
            // apwWooLog('JS Hide Warning: Could not find fieldWrapperSelector: ' + fieldWrapperSelector);
        }

        // --- Hide the SECOND 'Validate Address' button ---
        // Find ALL shipping buttons, then exclude the one we want to keep, and hide the rest.
        const $allShippingButtons = $(allShippingButtonsSelector);
        const $buttonToKeep = $(keepButtonSelector);

        // Ensure we target accurately, hide buttons NOT inside .shipping_address
        $allShippingButtons.each(function () {
            const $thisButton = $(this);
            // Check if this button is the one inside .shipping_address
            if ($thisButton.closest('.shipping_address').length === 0) {
                // This button is NOT inside .shipping_address, so hide it
                if ($thisButton.is(':visible')) {
                    $thisButton.hide();
                    apwWooLog('JS Hide: Hid duplicate shipping validation button (outside .shipping_address).');
                }
            } else {
                // This IS the button inside .shipping_address, ensure it's visible (optional)
                // $thisButton.show(); // Usually not needed unless hidden by other means
            }
        });

    } // End hideDuplicateAvalaraElements function


    // --- Document Ready ---
    $(document).ready(function () {

        apwWooLog('APW Woo Plugin: Document Ready.');
        
        // Force refresh cart indicator on page load
        setTimeout(function() {
            // Use WooCommerce's built-in AJAX endpoint to get fresh cart data
            if (typeof wc_cart_fragments_params !== 'undefined') {
                $.ajax({
                    type: 'GET',
                    url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                    success: function(response) {
                        if (response && response.fragments) {
                            apwWooLog('Forced cart refresh on page load');
                            $(document.body).trigger('wc_fragments_refreshed');
                        }
                    }
                });
            }
        }, 500);
        
        // Apply styling to My Account message buttons
        function fixMyAccountMessageButtons() {
            // Target the specific buttons in the orders and downloads pages
            $('.woocommerce-MyAccount-content .message-container .woocommerce-Button, ' +
              '.woocommerce-MyAccount-content .message-container .button.wc-forward').each(function() {
                var $button = $(this);
                
                // Force the correct styling
                $button.css({
                    'display': 'block',
                    'margin-top': '1.5rem',
                    'width': 'fit-content',
                    'background': 'linear-gradient(204deg, #244B5A, #178093)',
                    'background-color': '#244B5A',
                    'color': '#ffffff',
                    'border-radius': '58px',
                    'font-family': 'Montserrat, sans-serif',
                    'font-weight': '700',
                    'font-size': '1.1rem',
                    'text-transform': 'uppercase',
                    'padding': '12px 30px',
                    'border': 'none',
                    'text-align': 'center',
                    'text-decoration': 'none',
                    'transition': 'opacity 0.3s ease',
                    'box-shadow': 'none',
                    'line-height': 'normal',
                    'height': 'auto',
                    'min-height': 'unset',
                    'max-height': 'unset'
                });
                
                // Add hover event
                $button.off('mouseenter mouseleave').hover(
                    function() {
                        $(this).css({
                            'opacity': '0.85',
                            'color': '#ffffff',
                            'background': 'linear-gradient(204deg, #244B5A, #178093)',
                            'background-color': '#244B5A'
                        });
                    },
                    function() {
                        $(this).css({
                            'opacity': '1',
                            'color': '#ffffff',
                            'background': 'linear-gradient(204deg, #244B5A, #178093)',
                            'background-color': '#244B5A'
                        });
                    }
                );
                
                // Add a class to mark as processed
                $button.addClass('apw-styled-button-processed');
            });
            
            // Also ensure the text in the message container has proper styling
            $('.woocommerce-MyAccount-content .message-container').each(function() {
                // Set text color and font for all text nodes
                $(this).contents().filter(function() {
                    return this.nodeType === 3; // Text nodes only
                }).wrap('<span style="font-family: Montserrat, sans-serif !important; font-size: 1.3125rem !important; color: #0D252C !important; line-height: 1.5 !important;"></span>');
                
                // Make sure the container itself has the right styling
                $(this).css({
                    'font-family': 'Montserrat, sans-serif',
                    'font-size': '1.3125rem',
                    'color': '#0D252C',
                    'line-height': '1.5',
                    'background-color': 'rgba(182, 198, 204, 0.1)',
                    'border-left': '4px solid #178093',
                    'border-radius': '8px'
                });
            });
        }
        
        // Run on page load
        fixMyAccountMessageButtons();
        
        // Run after AJAX completions
        $(document).ajaxComplete(function() {
            setTimeout(fixMyAccountMessageButtons, 100);
        });
        
        // Run when fragments are refreshed
        $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function() {
            setTimeout(fixMyAccountMessageButtons, 100);
        });
        
        // --- Cart Quantity Indicator ---
        function updateCartQuantityIndicators(forceUpdate = false) {
            // Get cart count from WooCommerce fragments or data attribute
            let cartCount = 0;
            let foundCount = false;
            
            apwWooLog('Updating cart quantity indicators (force: ' + forceUpdate + ')');
            
            // Check if user is logged in (using WordPress body class)
            // Try to get count from WC fragments first
            if (typeof wc_cart_fragments_params !== 'undefined') {
                try {
                    const fragments = JSON.parse(sessionStorage.getItem(wc_cart_fragments_params.fragment_name));
                    if (fragments && fragments['div.widget_shopping_cart_content']) {
                        const $content = $(fragments['div.widget_shopping_cart_content']);
                        
                        // Check if cart is explicitly empty
                        const $emptyMessage = $content.find('.woocommerce-mini-cart__empty-message, .empty');
                        if ($emptyMessage.length > 0) {
                            cartCount = 0;
                            foundCount = true;
                            apwWooLog('Cart is explicitly empty from fragments');
                        } else {
                            // IMPORTANT: Count total quantity, not just number of items
                            let totalQuantity = 0;
                            
                            // First try to get quantities from cart fragments
                            const $cartItems = $content.find('.cart_list li:not(.empty)');
                            if ($cartItems.length === 0) {
                                cartCount = 0;
                                foundCount = true;
                                apwWooLog('No cart items found in fragments');
                            } else {
                                $cartItems.each(function() {
                                    // Look for quantity in the item
                                    const qtyText = $(this).find('.quantity').text();
                                    const qtyMatch = qtyText.match(/(\d+)\s*×/); // Match "2 ×" format
                                    if (qtyMatch && qtyMatch[1]) {
                                        totalQuantity += parseInt(qtyMatch[1], 10);
                                    } else {
                                        // If no quantity found, count as 1
                                        totalQuantity += 1;
                                    }
                                });
                                
                                cartCount = totalQuantity;
                                foundCount = true;
                                apwWooLog('Cart count from fragments (total quantity): ' + cartCount);
                            }
                        }
                    }
                } catch (e) {
                    apwWooLog('Error parsing fragments: ' + e.message);
                }
            }
            
            // If fragments didn't work, try the cart counter in the DOM
            if (!foundCount) {
                const $miniCart = $('.widget_shopping_cart_content');
                if ($miniCart.length) {
                    // Check for empty message first
                    const $emptyMessage = $miniCart.find('.woocommerce-mini-cart__empty-message, .empty');
                    if ($emptyMessage.length > 0) {
                        cartCount = 0;
                        foundCount = true;
                        apwWooLog('Cart is explicitly empty from DOM');
                    } else {
                        const $cartItems = $miniCart.find('.cart_list li:not(.empty)');
                        cartCount = $cartItems.length;
                        foundCount = true;
                        apwWooLog('Cart count from DOM: ' + cartCount);
                    }
                }
            }
            
            // If we still don't have a count, check for WC data
            if (!foundCount && typeof window.wc_cart_fragments_params !== 'undefined') {
                // Try to get from any visible counter
                const $visibleCounter = $('.cart-contents-count, .cart-count');
                if ($visibleCounter.length) {
                    const textCount = $visibleCounter.first().text().trim();
                    if (textCount !== '' && !isNaN(parseInt(textCount))) {
                        cartCount = parseInt(textCount);
                        foundCount = true;
                        apwWooLog('Cart count from visible counter: ' + cartCount);
                    }
                }
            }
            
            // Try to get count from cart page if we're on it
            if (!foundCount && $('.woocommerce-cart-form').length) {
                const $cartItems = $('.woocommerce-cart-form .cart_item');
                if ($cartItems.length === 0) {
                    cartCount = 0;
                    foundCount = true;
                    apwWooLog('Cart page has no items');
                } else {
                    let itemCount = 0;
                    $cartItems.each(function() {
                        const qty = parseInt($(this).find('input.qty').val()) || 0;
                        itemCount += qty;
                    });
                    cartCount = itemCount;
                    foundCount = true;
                    apwWooLog('Cart count from cart form: ' + cartCount);
                }
            }
            
            // Check if we have a WC cart count from PHP
            if (!foundCount && typeof window.apwWooCartCount !== 'undefined') {
                cartCount = window.apwWooCartCount;
                foundCount = true;
                apwWooLog('Using WC cart count from PHP: ' + cartCount);
            }
            
            // If we still haven't found a count or if forcing update, try AJAX endpoint
            if (!foundCount || forceUpdate) {
                if (typeof wc_cart_fragments_params !== 'undefined') {
                    $.ajax({
                        type: 'GET',
                        url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                        success: function(data) {
                            if (data && data.fragments) {
                                try {
                                    const $content = $(data.fragments['div.widget_shopping_cart_content']);
                                    
                                    // Check if cart is explicitly empty
                                    const $emptyMessage = $content.find('.woocommerce-mini-cart__empty-message, .empty');
                                    if ($emptyMessage.length > 0) {
                                        $('.cart-quantity-indicator').attr('data-cart-count', 0);
                                        apwWooLog('AJAX: Cart is explicitly empty');
                                        return;
                                    }
                                    
                                    // Count total quantity, not just number of items
                                    let totalQuantity = 0;
                                    
                                    // First try to get quantities from cart fragments
                                    const $cartItems = $content.find('.cart_list li:not(.empty)');
                                    if ($cartItems.length === 0) {
                                        $('.cart-quantity-indicator').attr('data-cart-count', 0);
                                        apwWooLog('AJAX: No cart items found');
                                        return;
                                    }
                                    
                                    $cartItems.each(function() {
                                        // Look for quantity in the item
                                        const qtyText = $(this).find('.quantity').text();
                                        const qtyMatch = qtyText.match(/(\d+)\s*×/); // Match "2 ×" format
                                        if (qtyMatch && qtyMatch[1]) {
                                            totalQuantity += parseInt(qtyMatch[1], 10);
                                        } else {
                                            // If no quantity found, count as 1
                                            totalQuantity += 1;
                                        }
                                    });
                                    
                                    $('.cart-quantity-indicator').attr('data-cart-count', totalQuantity);
                                    apwWooLog('AJAX: Cart count updated to: ' + totalQuantity);
                                } catch (e) {
                                    apwWooLog('Error processing AJAX fragments: ' + e.message);
                                }
                            }
                        },
                        error: function() {
                            apwWooLog('AJAX error getting cart fragments');
                        }
                    });
                }
            }
            
            // Update all cart quantity indicators with the count
            $('.cart-quantity-indicator').attr('data-cart-count', cartCount);
            apwWooLog('Updated cart quantity indicators with count: ' + cartCount);
        }
        
        // Initial update
        updateCartQuantityIndicators();
        
        // Update when cart fragments are refreshed
        $(document.body).on('wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart updated_cart_totals', function(event) {
            apwWooLog('Cart updated (' + event.type + '), refreshing quantity indicators');
            
            // Use delayed updates to ensure WooCommerce has fully processed the changes
            setTimeout(function() {
                updateCartQuantityIndicators(true);
            }, 150);
            
            // Additional delayed update for empty cart scenarios
            setTimeout(function() {
                updateCartQuantityIndicators(true);
            }, 500);
        });
        
        // Additional listener for cart quantity changes
        $(document).on('change', '.woocommerce-cart-form input.qty', function() {
            const newQty = parseInt($(this).val()) || 0;
            apwWooLog('Cart quantity changed to: ' + newQty);
            
            // Check if quantity was set to 0 (item removal)
            if (newQty === 0) {
                // Use multiple delayed updates for item removal
                setTimeout(function() { updateCartQuantityIndicators(true); }, 200);
                setTimeout(function() { updateCartQuantityIndicators(true); }, 600);
                setTimeout(function() { updateCartQuantityIndicators(true); }, 1200);
            } else {
                setTimeout(function() {
                    updateCartQuantityIndicators(true);
                }, 300);
            }
        });
        
        // Enhanced cart emptying detection
        function detectCartEmptying() {
            // Check if cart form exists and has no items
            const $cartForm = $('.woocommerce-cart-form');
            if ($cartForm.length) {
                const $cartItems = $cartForm.find('.cart_item');
                if ($cartItems.length === 0) {
                    apwWooLog('Cart form is empty, updating indicators');
                    $('.cart-quantity-indicator').attr('data-cart-count', 0);
                    return true;
                }
            }
            
            // Check mini cart for empty state
            const $miniCart = $('.widget_shopping_cart_content');
            if ($miniCart.length) {
                const $emptyMessage = $miniCart.find('.woocommerce-mini-cart__empty-message, .empty');
                if ($emptyMessage.length > 0) {
                    apwWooLog('Mini cart shows empty message, updating indicators');
                    $('.cart-quantity-indicator').attr('data-cart-count', 0);
                    return true;
                }
            }
            
            return false;
        }
        
        // Listen for clicks on remove buttons in cart
        $(document).on('click', '.woocommerce-cart-form .product-remove a.remove, .cart_item .remove, .apw-woo-product-remove a.apw-woo-remove', function(e) {
            apwWooLog('Remove button clicked, scheduling indicator refresh');
            
            // Store the clicked button to check if it's our custom remove button
            const $removeButton = $(this);
            const isCustomRemove = $removeButton.hasClass('apw-woo-remove');
            
            // Check if this is the last item in the cart
            const $cartItems = $('.woocommerce-cart-form .cart_item, .cart_list li:not(.empty)');
            const isLastItem = $cartItems.length <= 1;
            
            if (isCustomRemove) {
                // For our custom remove buttons, we need to handle the AJAX update manually
                e.preventDefault();
                
                // Get the cart item key from the data attribute or href
                const cartItemKey = $removeButton.data('product_id') || 
                                   $removeButton.attr('href').split('remove_item=')[1].split('&')[0];
                
                if (cartItemKey) {
                    apwWooLog('Removing item with key: ' + cartItemKey);
                    
                    // If this is the last item, immediately set count to 0
                    if (isLastItem) {
                        $('.cart-quantity-indicator').attr('data-cart-count', 0);
                        apwWooLog('Last item removed, setting count to 0');
                    }
                    
                    // Show loading state
                    $removeButton.closest('tr').addClass('processing').block({
                        message: null,
                        overlayCSS: { opacity: 0.6 }
                    });
                    
                    // Make AJAX request to remove the item
                    $.ajax({
                        type: 'POST',
                        url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_from_cart'),
                        data: {
                            cart_item_key: cartItemKey
                        },
                        success: function(response) {
                            // Force refresh cart fragments to update the cart count
                            $(document.body).trigger('wc_fragment_refresh');
                            
                            // Reload the page after a short delay to show the updated cart
                            setTimeout(function() {
                                window.location.reload();
                            }, 500);
                        },
                        error: function() {
                            // On error, still try to reload the page
                            window.location.reload();
                        }
                    });
                }
            } else {
                // For standard WooCommerce remove buttons
                // If this is the last item, immediately set count to 0
                if (isLastItem) {
                    $('.cart-quantity-indicator').attr('data-cart-count', 0);
                    apwWooLog('Last item being removed, setting count to 0 immediately');
                }
                
                // Use multiple timeouts to catch the update at different stages
                setTimeout(function() { updateCartQuantityIndicators(true); }, 100);
                setTimeout(function() { updateCartQuantityIndicators(true); }, 400);
                setTimeout(function() { updateCartQuantityIndicators(true); }, 800);
                setTimeout(function() { updateCartQuantityIndicators(true); }, 1500);
                
                // Also detect cart emptying explicitly
                setTimeout(detectCartEmptying, 600);
                setTimeout(detectCartEmptying, 1200);
                
                // Force a fragment refresh to ensure the cart count updates
                setTimeout(function() {
                    $(document.body).trigger('wc_fragment_refresh');
                }, 300);
            }
        });
        
        // Listen for AJAX requests that might be cart updates
        $(document).ajaxComplete(function(event, xhr, settings) {
            // Check if the AJAX call is related to cart updates
            if (settings.url && (
                settings.url.indexOf('wc-ajax=remove_from_cart') > -1 ||
                settings.url.indexOf('wc-ajax=cart') > -1 ||
                settings.url.indexOf('remove_item') > -1 ||
                settings.url.indexOf('add_to_cart') > -1 ||
                settings.url.indexOf('update_item_quantity') > -1
            )) {
                apwWooLog('Cart-related AJAX completed, refreshing indicators');
                
                // Multiple updates with increasing delays for empty cart scenarios
                setTimeout(function() { updateCartQuantityIndicators(true); }, 100);
                setTimeout(function() { updateCartQuantityIndicators(true); }, 300);
                setTimeout(detectCartEmptying, 500);
                
                // Force a fragment refresh to ensure the cart count updates properly
                setTimeout(function() {
                    $(document.body).trigger('wc_fragment_refresh');
                }, 200);
            }
        });

        // --- Notice Handling Initialization ---
        apwWooLog('Initializing Notice Handler.');
        moveNoticesToContainer(); // Initial check
        // setTimeout(debugNotices, 500); // Uncomment if notice debugging is needed

        // Observe for dynamically added notices
        const observeElements = [
            document.querySelector('.woocommerce-notices-wrapper'),
            document.querySelector('header.header'),
            document.querySelector('.page-wrapper'),
            document.querySelector('#wrapper'),
            document.querySelector('body')
        ].filter(el => el !== null);

        if (observeElements.length > 0) {
            const noticeObserver = new MutationObserver(function (mutations) {
                clearTimeout(window.apwNoticeCheckTimeout);
                window.apwNoticeCheckTimeout = setTimeout(moveNoticesToContainer, 50);
            });
            const config = {childList: true, subtree: true};
            observeElements.forEach(element => {
                noticeObserver.observe(element, config);
            });
        } else {
            console.error('APW Woo Plugin: Could not find elements to observe for notices.');
        }

        // Check notices on ajax completion and clicks (existing logic)
        $(document).ajaxComplete(function () {
            setTimeout(moveNoticesToContainer, 100);
        });
        $(document).on('click', '.add_to_cart_button, .single_add_to_cart_button', function () {
            setTimeout(moveNoticesToContainer, 150);
            for (let delay of [300, 600, 1200]) {
                setTimeout(moveNoticesToContainer, delay);
            }
        });

        // Periodic check (reduced frequency/count as Observer is primary)
        let checkCount = 0;
        const maxChecks = 5;
        const checkInterval = setInterval(function () {
            moveNoticesToContainer();
            checkCount++;
            if (checkCount >= maxChecks) {
                clearInterval(checkInterval);
            }
        }, 1000); // Check every second for 5 seconds

        // Final check on load
        $(window).on('load', function () {
            setTimeout(moveNoticesToContainer, 200);
        });
        // --- End Notice Handling Initialization ---


        // --- Checkout Page Specific Initialization ---
        if ($('body').hasClass('woocommerce-checkout')) {
            apwWooLog('Initializing Checkout adjustments.');

            // Function to run the check + hide logic for Avalara elements
            function runAvalaraHideLogic() {
                // Check if the main checkbox exists - ensures we are likely in the right state
                if ($('h3#ship-to-different-address input#ship-to-different-address-checkbox').length) {
                    hideDuplicateAvalaraElements();
                }
            }

            // Initial run with delay
            setTimeout(runAvalaraHideLogic, 250);

            // Listen for the change event on the *correct* primary checkbox
            $(document).on('change', 'h3#ship-to-different-address > label > input#ship-to-different-address-checkbox', function () {
                apwWooLog('Checkbox changed, running hide logic.');
                setTimeout(runAvalaraHideLogic, 150);
            });

            // Fallback: Use MutationObserver on the shipping_address div style attribute
            const shippingDiv = document.querySelector('.shipping_address');
            if (shippingDiv) {
                const observer = new MutationObserver(function (mutationsList) {
                    for (let mutation of mutationsList) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                            apwWooLog('Shipping div style changed, running hide logic.');
                            setTimeout(runAvalaraHideLogic, 150);
                            break;
                        }
                    }
                });
                observer.observe(shippingDiv, {attributes: true});
                apwWooLog('MutationObserver attached to .shipping_address style attribute.');
            } else {
                apwWooLog('Could not find .shipping_address div for MutationObserver.');
            }
        }
        // --- End Checkout Page Specific Initialization ---

    }); // End Document Ready

})(jQuery);
