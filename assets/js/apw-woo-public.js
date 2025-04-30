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
        setTimeout(function () {
            // Use WooCommerce's built-in AJAX endpoint to get fresh cart data
            if (typeof wc_cart_fragments_params !== 'undefined') {
                $.ajax({
                    type: 'GET',
                    url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                    success: function (response) {
                        if (response && response.fragments) {
                            apwWooLog('Forced cart refresh on page load');
                            $(document.body).trigger('wc_fragments_refreshed');
                        }
                    }
                });
            }
        }, 500);

        // --- Cart Quantity Indicator ---
        function updateCartQuantityIndicators() {
            // Get cart count from WooCommerce fragments or data attribute
            let cartCount = 0;

            // *** MODIFICATION START: Removed login check block ***
            // Check if user is logged in (using WordPress body class)
            // const isLoggedIn = $('body').hasClass('logged-in');
            // If user is not logged in, set empty data attribute to hide bubble but keep link visible
            // if (!isLoggedIn) {
            //     $('.cart-quantity-indicator').attr('data-cart-count', '');
            //     window.apwWooCartCount = '';
            //     apwWooLog('User not logged in, hiding cart count bubble but keeping link visible');
            //     return;
            // }
            // *** MODIFICATION END ***

            // For logged-in users, proceed with normal count retrieval
            // Try to get count from WC fragments first
            if (typeof wc_cart_fragments_params !== 'undefined') {
                try {
                    const fragments = JSON.parse(sessionStorage.getItem(wc_cart_fragments_params.fragment_name));
                    if (fragments && fragments['div.widget_shopping_cart_content']) {
                        const $content = $(fragments['div.widget_shopping_cart_content']);

                        // IMPORTANT: Count total quantity, not just number of items
                        let totalQuantity = 0;

                        // First try to get quantities from cart fragments
                        const $cartItems = $content.find('.cart_list li:not(.empty)');
                        $cartItems.each(function () {
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
                        apwWooLog('Cart count from fragments (total quantity): ' + cartCount);
                    }
                } catch (e) {
                    apwWooLog('Error parsing fragments: ' + e.message);
                }
            }

            // If fragments didn't work, try the cart counter in the DOM
            if (cartCount === 0) {
                const $miniCart = $('.widget_shopping_cart_content');
                if ($miniCart.length) {
                    const $cartItems = $miniCart.find('.cart_list li:not(.empty)');
                    cartCount = $cartItems.length;
                    apwWooLog('Cart count from DOM: ' + cartCount);
                }
            }

            // If we still don't have a count, check for WC data
            if (cartCount === 0 && typeof window.wc_cart_fragments_params !== 'undefined') {
                // Try to get from any visible counter
                const $visibleCounter = $('.cart-contents-count, .cart-count');
                if ($visibleCounter.length) {
                    const textCount = $visibleCounter.first().text().trim();
                    if (textCount && !isNaN(parseInt(textCount))) {
                        cartCount = parseInt(textCount);
                        apwWooLog('Cart count from visible counter: ' + cartCount);
                    }
                }
            }

            // Try to get count from cart page if we're on it
            if ($('.woocommerce-cart-form').length) {
                let itemCount = 0;
                $('.woocommerce-cart-form .cart_item').each(function () {
                    const qty = parseInt($(this).find('input.qty').val()) || 0;
                    itemCount += qty;
                });
                if (itemCount > 0) {
                    cartCount = itemCount;
                    apwWooLog('Cart count from cart form: ' + cartCount);
                }
            }

            // Last resort - try to get from WC AJAX endpoint
            if (cartCount === 0 && typeof wc_cart_fragments_params !== 'undefined') {
                $.ajax({
                    type: 'GET',
                    url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                    success: function (data) {
                        if (data && data.fragments) {
                            try {
                                const $content = $(data.fragments['div.widget_shopping_cart_content']);

                                // Count total quantity, not just number of items
                                let totalQuantity = 0;

                                // First try to get quantities from cart fragments
                                const $cartItems = $content.find('.cart_list li:not(.empty)');
                                $cartItems.each(function () {
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

                                if (totalQuantity > 0) {
                                    cartCount = totalQuantity;
                                    apwWooLog('Cart count from AJAX (total quantity): ' + cartCount);
                                    $('.cart-quantity-indicator').attr('data-cart-count', cartCount);
                                }
                            } catch (e) {
                                apwWooLog('Error processing AJAX fragments: ' + e.message);
                            }
                        }
                    }
                });
            }

            // Check if we have a WC cart count from PHP
            if (typeof window.apwWooCartCount !== 'undefined' && window.apwWooCartCount > 0 && cartCount === 0) {
                cartCount = window.apwWooCartCount;
                apwWooLog('Using WC cart count from PHP: ' + cartCount);
            }

            // Update all cart quantity indicators with the count
            $('.cart-quantity-indicator').attr('data-cart-count', cartCount);
            apwWooLog('Updated cart quantity indicators with count: ' + cartCount);
        }

        // Initial update
        updateCartQuantityIndicators();

        // Update when cart fragments are refreshed
        $(document.body).on('wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart updated_cart_totals', function () {
            apwWooLog('Cart updated, refreshing quantity indicators');
            updateCartQuantityIndicators();
        });

        // Additional listener for cart quantity changes
        $(document).on('change', '.woocommerce-cart-form input.qty', function () {
            setTimeout(function () {
                apwWooLog('Cart quantity changed, refreshing indicators');
                updateCartQuantityIndicators();
            }, 300);
        });

        // Listen for clicks on remove buttons in cart
        $(document).on('click', '.woocommerce-cart-form .product-remove a.remove, .cart_item .remove', function () {
            apwWooLog('Remove button clicked, scheduling indicator refresh');
            // Multiple timeouts to catch the update at different stages
            setTimeout(updateCartQuantityIndicators, 100);
            setTimeout(updateCartQuantityIndicators, 500);
            setTimeout(updateCartQuantityIndicators, 1000);
        });

        // Listen for AJAX requests that might be cart updates
        $(document).ajaxComplete(function (event, xhr, settings) {
            // Check if the AJAX call is related to cart updates
            if (settings.url && (
                settings.url.indexOf('wc-ajax=remove_from_cart') > -1 ||
                settings.url.indexOf('wc-ajax=cart') > -1 ||
                settings.url.indexOf('remove_item') > -1
            )) {
                apwWooLog('Cart-related AJAX completed, refreshing indicators');
                setTimeout(updateCartQuantityIndicators, 100);
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