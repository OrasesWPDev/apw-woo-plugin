/**
 * APW WooCommerce Plugin Public Scripts
 * Notice handling that moves notices to our custom container
 */
(function ($) {
    'use strict';

    // Function to move notices to our container
    function moveNoticesToContainer() {
        // Define the target container where notices should be moved
        const $targetContainer = $('.apw-woo-notices-container');
        
        // Ensure target container exists
        if (!$targetContainer.length) {
            console.error('APW Woo Plugin Error: Target notice container ".apw-woo-notices-container" not found.');
            return false;
        }
        
        // Define notice selectors - all possible notice types outside our container
        const noticeSelectors = [
            // WooCommerce standard notices
            'body > .woocommerce-message',
            'body > .woocommerce-error',
            'body > .woocommerce-info',
            'body > .woocommerce-notice',
            'body > .message-wrapper',
            '.woocommerce-notices-wrapper > *',
            // Notices after header
            'header.header ~ .woocommerce-message',
            'header.header ~ .woocommerce-error',
            'header.header ~ .woocommerce-info',
            'header.header ~ .message-wrapper',
            // Notices in page wrapper
            '.page-wrapper > .woocommerce-message',
            '.page-wrapper > .woocommerce-error',
            '.page-wrapper > .woocommerce-info',
            '.page-wrapper > .message-wrapper',
            // Flatsome specific selectors
            '.message-container:not(.apw-woo-notices-container .message-container)',
            '.woocommerce-message.message-wrapper:not(.apw-woo-notices-container .message-wrapper)'
        ];
        
        // Combined selector for finding notices
        const combinedSelector = noticeSelectors.join(', ');
        
        // Find notices outside our container
        const $notices = $(combinedSelector).filter(function() {
            // Exclude notices that are already in our container
            return !$(this).closest('.apw-woo-notices-container').length;
        });
        
        if ($notices.length > 0) {
            console.log(`APW Woo Plugin: Found ${$notices.length} notices to move`);
            
            // Clear existing notices in our container
            $targetContainer.empty();
            
            $notices.each(function() {
                const $notice = $(this);
                console.log('Moving notice:', $notice[0]);
                
                // Instead of cloning, move the actual notice to preserve all event handlers
                $notice.detach().appendTo($targetContainer);
                
                // Ensure the notice is visible
                $notice.css({
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1',
                    'position': 'static'
                }).addClass('apw-woo-processed');
            });
            
            return true;
        }
        
        return false;
    }

    // Debug function to help diagnose notice issues
    function debugNotices() {
        console.log('APW Woo Plugin: Debugging notices');
        
        // Check for notices in various locations
        const locations = [
            'body > .woocommerce-message, body > .woocommerce-error, body > .woocommerce-info',
            '.woocommerce-notices-wrapper > *',
            'header.header ~ .woocommerce-message, header.header ~ .woocommerce-error',
            '.page-wrapper > .woocommerce-message, .page-wrapper > .woocommerce-error',
            '.apw-woo-notices-container > *'
        ];
        
        locations.forEach(selector => {
            const $elements = $(selector);
            console.log(`Found ${$elements.length} notices matching: ${selector}`);
            if ($elements.length > 0) {
                $elements.each(function(i) {
                    console.log(`- Notice ${i+1}: ${this.className}, Visible: ${$(this).is(':visible')}, Text: ${$(this).text().substring(0, 50)}...`);
                });
            }
        });
    }

    // Run on document ready
    $(document).ready(function () {
        console.log('APW Woo Plugin: Document Ready. Notice handler loaded.');

        // Immediately check for notices to move
        moveNoticesToContainer();
        
        // Debug notices after a short delay
        setTimeout(debugNotices, 500);

        // Define elements to observe - include all possible notice containers
        const observeElements = [
            document.querySelector('.woocommerce-notices-wrapper'),
            document.querySelector('header.header'),
            document.querySelector('.page-wrapper'),
            document.querySelector('#wrapper'),
            document.querySelector('body')
        ].filter(el => el !== null);

        if (observeElements.length === 0) {
            console.error('APW Woo Plugin: Could not find elements to observe.');
            return;
        }

        // Create a MutationObserver to watch for new notices
        const observer = new MutationObserver(function (mutations) {
            // Use setTimeout to allow WooCommerce to fully process the DOM
            setTimeout(function () {
                moveNoticesToContainer();
            }, 10);
        });

        // Configuration for the observer
        const config = {
            childList: true,
            subtree: true
        };

        // Start observing key elements
        observeElements.forEach(element => {
            observer.observe(element, config);
        });

        // Check after AJAX requests complete
        $(document).ajaxComplete(function () {
            console.log('APW Woo Plugin: AJAX completed, checking for notices');
            // Use setTimeout to allow WooCommerce to fully process the DOM
            setTimeout(function () {
                moveNoticesToContainer();
            }, 50);
        });

        // Also check when add-to-cart buttons are clicked
        $(document).on('click', '.add_to_cart_button, .single_add_to_cart_button', function () {
            console.log('APW Woo Plugin: Add to cart button clicked');
            // Wait for WooCommerce to process the request and add notices
            setTimeout(function () {
                moveNoticesToContainer();
            }, 100);
            
            // Check multiple times after button click to catch delayed notices
            for (let delay of [200, 500, 1000, 1500]) {
                setTimeout(function() {
                    moveNoticesToContainer();
                }, delay);
            }
        });
        
        // Periodically check for notices for the first few seconds
        let checkCount = 0;
        const maxChecks = 20; // Increased from 10 to 20
        const checkInterval = setInterval(function() {
            moveNoticesToContainer();
            checkCount++;
            if (checkCount >= maxChecks) {
                clearInterval(checkInterval);
            }
        }, 250); // Reduced from 500ms to 250ms for more frequent checks
        
        // Also check when the page is fully loaded
        $(window).on('load', function() {
            moveNoticesToContainer();
        });
    });

})(jQuery);
