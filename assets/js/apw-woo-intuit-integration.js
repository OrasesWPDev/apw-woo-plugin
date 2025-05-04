;(function($){
    'use strict';

    // Ensure localization object exists and inherit core WC flags
    window.apwWooIntuitData = window.apwWooIntuitData || {debug_mode:false, is_checkout:false};
    if (typeof window.apwWooData !== 'undefined') {
        apwWooIntuitData.is_checkout = window.apwWooData.page_type === 'checkout';
        apwWooIntuitData.debug_mode    = window.apwWooData.debug_mode;
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
            logWithTime('WFQBC not ready');
            return false;
        }
        logWithTime('Calling WFQBC.init with config');
        try {
            WFQBC.init({
                formSelector:    'form.checkout',
                submitSelector:  '#place_order',
                tokenFieldName:  'wc-intuit-payments-credit-card-js-token',
                cardTypeFieldName: 'wc-intuit-payments-credit-card-card-type',
                disclaimerSelector: '.payment_box.payment_method_intuit_payments_credit_card .wfqbc-disclaimer'
            });
            logWithTime('WFQBC.init OK');
            return true;
        } catch (e) {
            logWithTime('WFQBC.init failed: ' + e.message);
            return false;
        }
    }

    // Main initialization
    function initialize() {
        logWithTime('Initializing Intuit payment integration');
        if (!apwWooIntuitData.is_checkout) {
            logWithTime('Not on checkout page, integration inactive');
            return;
        }
        initIntuitPayment();
        // Re-init after checkout update
        $(document.body).on('updated_checkout', function() {
            logWithTime('Checkout updated, re-init Intuit integration');
            initIntuitPayment();
        });
        // Re-init after payment method change
        $(document.body).on('payment_method_selected', function() {
            logWithTime('Payment method changed, re-init Intuit integration');
            setTimeout(initIntuitPayment, 300);
        });
    }

    // Run on document ready
    $(document).ready(initialize);

    // Expose the internal initializer for external use
    window.apwWooInitIntuit = initIntuitPayment;
})(jQuery);
