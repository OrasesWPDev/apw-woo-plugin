/**
 * APW WooCommerce Plugin Public Scripts
 * Handles AJAX notice relocation using MutationObserver, targeting specific notice class.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        console.log('APW Woo Plugin: Document Ready. Targeted Observer JS Loaded.');

        // Define the target container where notices should be moved
        const $targetContainer = $('.apw-woo-notices-container');
        // Define the selector for the SPECIFIC notice message we want to move
        const noticeSelector = 'div.woocommerce-message.message-wrapper'; // Target the exact wrapper
        // Define the element to observe for changes. Start specific, fallback broader.
        let observeTargetElement = document.querySelector('.woocommerce-notices-wrapper'); // Standard WC wrapper
        if (!observeTargetElement) {
            observeTargetElement = document.querySelector('header.header') || document.body; // Fallback: header or body
            console.log('APW Woo Plugin: Default notice wrapper not found. Observing:', observeTargetElement.tagName);
        } else {
            console.log('APW Woo Plugin: Observing default notice wrapper.');
        }

        // --- Ensure target container exists ---
        if (!$targetContainer.length) {
            console.error('APW Woo Plugin Error: Target notice container ".apw-woo-notices-container" not found. Cannot relocate notice.');
            return;
        }
        if (!observeTargetElement) {
            console.error('APW Woo Plugin Error: Could not find an element to observe for notices.');
            return;
        }

        // --- Create a MutationObserver ---
        const observer = new MutationObserver(function (mutationsList, observer) {
            for (const mutation of mutationsList) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    let noticeFound = false;
                    $(mutation.addedNodes).each(function () {
                        const $node = $(this);
                        // Check if the added node *is* the notice or *contains* the notice
                        const $originalNotice = $node.is(noticeSelector) ? $node : $node.find(noticeSelector).first();

                        if ($originalNotice.length > 0) {
                            console.log('APW Woo Plugin: Detected notice node added:', $originalNotice[0]);
                            noticeFound = true; // Mark as found

                            // Clone the notice to move it
                            const $clonedNotice = $originalNotice.clone();

                            // Move the cloned notice to our target container
                            console.log('APW Woo Plugin: Moving notice to target container.');
                            $targetContainer.empty().append($clonedNotice).show();

                            // Remove the original notice from its original location
                            // IMPORTANT: Do this *after* successfully cloning and appending
                            console.log('APW Woo Plugin: Removing original notice.');
                            $originalNotice.remove(); // Remove the original node

                            // We found and moved the notice, we can stop checking this set of mutations
                            return false; // Exit the .each loop
                        }
                    });

                    if (noticeFound) {
                        // Optional: Disconnect observer if you only expect one notice per interaction
                        // observer.disconnect();
                        // console.log('APW Woo Plugin: Observer disconnected after finding notice.');
                    }
                }
            }
        });

        // --- Configuration for the observer ---
        const config = {
            childList: true, // Observe direct children being added or removed
            subtree: true     // Observe all descendants, not just direct children
        };

        // --- Start observing ---
        observer.observe(observeTargetElement, config);
        console.log('APW Woo Plugin: MutationObserver started on:', observeTargetElement);

    }); // End document ready

})(jQuery);