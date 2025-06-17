;(function($){
    'use strict';

    // Ensure localization object exists
    window.apwWooIntuitData = window.apwWooIntuitData || {debug_mode:false, is_checkout:false};

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

    // FRONTEND SYNC v1.23.15: Check surcharge amount and force update if incorrect
    function checkSurchargeAmount() {
        var surchargeRow = $('.order-total tr:contains("Credit Card Surcharge")');
        if (surchargeRow.length) {
            var amount = surchargeRow.find('.amount').text();
            logWithTime('Current surcharge amount: ' + amount);
            
            // If we detect the wrong amount, force another update
            if (amount.includes('$17.14')) {
                logWithTime('Detected stale surcharge amount $17.14 - forcing update');
                setTimeout(function() {
                    $('body').trigger('update_checkout');
                }, 500);
                return true; // Indicates update was triggered
            }
        }
        return false; // No update needed
    }

    // FRONTEND SYNC v1.23.15: Force checkout refresh for Intuit payment method
    function forceCheckoutRefreshForIntuit() {
        var selectedPayment = $('input[name="payment_method"]:checked').val();
        if (selectedPayment === 'intuit_payments_credit_card') {
            logWithTime('Intuit payment method detected - forcing checkout update');
            $('body').trigger('update_checkout');
        }
    }

    // Main initialization
    function initialize() {
        logWithTime('Initializing Intuit payment integration with frontend sync');
        if (!apwWooIntuitData.is_checkout) {
            logWithTime('Not on checkout page, integration inactive');
            return;
        }
        
        initIntuitPayment();
        
        // FRONTEND SYNC v1.23.15: Force refresh on page load if Intuit is selected
        setTimeout(forceCheckoutRefreshForIntuit, 1000);
        
        // Re-init after checkout update
        $(document.body).on('updated_checkout', function() {
            logWithTime('Checkout updated, re-init Intuit integration');
            initIntuitPayment();
            
            // FRONTEND SYNC v1.23.15: Check surcharge amount after update
            setTimeout(checkSurchargeAmount, 500);
        });
        
        // Re-init after payment method change
        $(document.body).on('payment_method_selected', function() {
            logWithTime('Payment method changed, re-init Intuit integration');
            setTimeout(initIntuitPayment, 300);
        });
        
        // FRONTEND SYNC v1.23.15: Monitor payment method changes for Intuit
        $(document).on('change', 'input[name="payment_method"]', function() {
            var method = $(this).val();
            logWithTime('Payment method changed to: ' + method);
            if (method === 'intuit_payments_credit_card') {
                logWithTime('Intuit selected - triggering checkout update');
                $('body').trigger('update_checkout');
            }
        });
        
        // FRONTEND SYNC v1.23.15: Periodic surcharge verification
        if (apwWooIntuitData.debug_mode) {
            setInterval(function() {
                var selectedPayment = $('input[name="payment_method"]:checked').val();
                if (selectedPayment === 'intuit_payments_credit_card') {
                    checkSurchargeAmount();
                }
            }, 10000); // Check every 10 seconds in debug mode
        }
    }

    // Run on document ready
    $(document).ready(initialize);

})(jQuery);
