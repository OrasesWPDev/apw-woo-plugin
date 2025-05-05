;(function ($) {
    'use strict';

    // Ensure localization object exists and inherit core WC flags
    window.apwWooIntuitData = window.apwWooIntuitData || {debug_mode: false, is_checkout: false};
    if (typeof window.apwWooData !== 'undefined') {
        apwWooIntuitData.is_checkout = window.apwWooData.page_type === 'checkout';
        apwWooIntuitData.debug_mode = window.apwWooData.debug_mode;
    }

    // Logging helper
    function logWithTime(message) {
        if (apwWooIntuitData.debug_mode) {
            var now = new Date();
            var ts = now.getHours() + ':' + now.getMinutes() + ':' + now.getSeconds();
            console.log('[' + ts + '] INTUIT INTEGRATION: ' + message);
        }
    }

    // Initialize WFQBC with configuration - NOW WITH MORE CHECKS
    function initIntuitPayment() {
        logWithTime('Attempting initIntuitPayment...'); // Log entry into this function

        // Check if WFQBC object exists
        if (typeof window.WFQBC === 'undefined') {
            logWithTime('Error: WFQBC object is not defined.');
            return false;
        }
        logWithTime('WFQBC object exists.');

        // Check if WFQBC.init method exists
        if (typeof window.WFQBC.init !== 'function') {
            logWithTime('Error: WFQBC.init is not a function.');
            // Log WFQBC structure for debugging if init is missing
            if (typeof window.WFQBC === 'object') {
                logWithTime('WFQBC object structure: ' + JSON.stringify(Object.keys(window.WFQBC)));
            }
            return false;
        }
        logWithTime('WFQBC.init function exists. Calling it...');

        try {
            WFQBC.init({
                formSelector: 'form.checkout',
                submitSelector: '#place_order',
                tokenFieldName: 'wc-intuit-payments-credit-card-js-token',
                cardTypeFieldName: 'wc-intuit-payments-credit-card-card-type',
                disclaimerSelector: '.payment_box.payment_method_intuit_payments_credit_card .wfqbc-disclaimer'
            });
            logWithTime('WFQBC.init called successfully.');
            return true;
        } catch (e) {
            logWithTime('WFQBC.init threw an error: ' + e.message);
            console.error(e); // Log the full error object
            return false;
        }
    }

    // Main initialization logic
    function initialize() {
        logWithTime('Initializing Intuit payment integration logic...');

        if (!apwWooIntuitData.is_checkout) {
            logWithTime('Not on checkout page, integration inactive.');
            return;
        }

        logWithTime('On checkout page, proceeding with Intuit init.');

        // ADDED DELAY: Wait a very short time after document ready
        setTimeout(function () {
            logWithTime('Delayed init: Attempting initial initIntuitPayment call.');
            initIntuitPayment(); // Initial call after delay
        }, 150); // Delay in milliseconds (e.g., 150ms)


        // Re-init after checkout update
        $(document.body).off('updated_checkout.apwIntuit').on('updated_checkout.apwIntuit', function () {
            logWithTime('Event triggered: updated_checkout. Re-initializing Intuit...');
            // Add a small delay to ensure WC finishes updating the DOM
            setTimeout(initIntuitPayment, 250); // Increased delay slightly
        });

        // Re-init after payment method change
        $(document.body).off('payment_method_selected.apwIntuit').on('payment_method_selected.apwIntuit', function () {
            logWithTime('Event triggered: payment_method_selected. Re-initializing Intuit...');
            // Add a small delay
            setTimeout(initIntuitPayment, 250); // Increased delay slightly
        });
    }

    // Run the initialization logic on document ready
    $(document).ready(initialize);

})(jQuery);