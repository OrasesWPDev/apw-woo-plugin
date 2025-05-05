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

    // Initialize WFQBC with configuration
    function initIntuitPayment() {
        if (typeof window.WFQBC === 'undefined' || typeof window.WFQBC.init !== 'function') {
            logWithTime('WFQBC not ready or WFQBC.init is not a function');
            return false;
        }
        logWithTime('Attempting to call WFQBC.init with config');
        try {
            WFQBC.init({
                formSelector: 'form.checkout',
                submitSelector: '#place_order',
                tokenFieldName: 'wc-intuit-payments-credit-card-js-token',
                cardTypeFieldName: 'wc-intuit-payments-credit-card-card-type',
                disclaimerSelector: '.payment_box.payment_method_intuit_payments_credit_card .wfqbc-disclaimer'
            });
            logWithTime('WFQBC.init called successfully');
            return true;
        } catch (e) {
            logWithTime('WFQBC.init threw an error: ' + e.message);
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
        initIntuitPayment(); // Initial call

        // Re-init after checkout update
        $(document.body).off('updated_checkout.apwIntuit').on('updated_checkout.apwIntuit', function () {
            logWithTime('Event triggered: updated_checkout. Re-initializing Intuit...');
            // Add a small delay to ensure WC finishes updating the DOM
            setTimeout(initIntuitPayment, 150);
        });

        // Re-init after payment method change
        $(document.body).off('payment_method_selected.apwIntuit').on('payment_method_selected.apwIntuit', function () {
            logWithTime('Event triggered: payment_method_selected. Re-initializing Intuit...');
            // Add a small delay
            setTimeout(initIntuitPayment, 150);
        });
    }

    // Run the initialization logic on document ready
    $(document).ready(initialize);

})(jQuery);