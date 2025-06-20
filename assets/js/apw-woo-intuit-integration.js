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

    // BEST PRACTICES v1.23.16: Ensure payment method changes trigger proper updates
    function ensurePaymentMethodUpdateTriggers() {
        var selectedPayment = $('input[name="payment_method"]:checked').val();
        if (selectedPayment === 'intuit_payments_credit_card') {
            logWithTime('BEST PRACTICES: Intuit payment method - ensuring checkout updates');
            // Use WooCommerce's native update mechanism
            $('body').trigger('update_checkout');
        }
    }

    // BEST PRACTICES v1.23.16: Simple verification without manual intervention  
    function logCurrentSurchargeState() {
        var surchargeRow = $('.order-total tr:contains("Credit Card Surcharge")');
        if (surchargeRow.length) {
            var amount = surchargeRow.find('.amount').text();
            logWithTime('BEST PRACTICES: Current surcharge amount: ' + amount);
        } else {
            logWithTime('BEST PRACTICES: No surcharge currently displayed');
        }
    }

    // FRONTEND CACHE FIX v1.23.26: Force checkout refresh when surcharge is updated
    function checkAndRefreshSurcharge() {
        if (apwWooIntuitData.surcharge_updated) {
            var updateTime = parseInt(apwWooIntuitData.surcharge_updated);
            var currentTime = Math.floor(Date.now() / 1000);
            
            // If update flag is recent (within last 30 seconds), force refresh
            if (currentTime - updateTime < 30) {
                logWithTime('FRONTEND CACHE FIX: Detected recent surcharge update, forcing checkout refresh');
                
                // Force WooCommerce to refresh checkout fragments
                $('body').trigger('update_checkout');
                
                // Clear the flag so we don't keep refreshing
                $.post(apwWooIntuitData.ajax_url, {
                    action: 'apw_clear_surcharge_flag',
                    nonce: apwWooIntuitData.nonce
                }, function(response) {
                    if (response.success) {
                        logWithTime('FRONTEND CACHE FIX: Cleared surcharge update flag');
                        apwWooIntuitData.surcharge_updated = null;
                    }
                });
                
                return true;
            }
        }
        return false;
    }

    // Main initialization
    function initialize() {
        logWithTime('BEST PRACTICES: Initializing Intuit payment integration');
        if (!apwWooIntuitData.is_checkout) {
            logWithTime('Not on checkout page, integration inactive');
            return;
        }
        
        initIntuitPayment();
        
        // FRONTEND CACHE FIX v1.23.26: Check for recent surcharge updates on page load
        setTimeout(checkAndRefreshSurcharge, 500);
        
        // BEST PRACTICES v1.23.16: Ensure proper payment method handling on load
        setTimeout(ensurePaymentMethodUpdateTriggers, 1000);
        
        // Re-init after checkout update (standard WooCommerce pattern)
        $(document.body).on('updated_checkout', function() {
            logWithTime('BEST PRACTICES: Checkout updated, re-init Intuit integration');
            initIntuitPayment();
            
            // Log current state for debugging
            setTimeout(logCurrentSurchargeState, 500);
        });
        
        // Re-init after payment method change (standard WooCommerce pattern)
        $(document.body).on('payment_method_selected', function() {
            logWithTime('BEST PRACTICES: Payment method selected event, re-init Intuit');
            setTimeout(initIntuitPayment, 300);
        });
        
        // BEST PRACTICES v1.23.16: Proper payment method change handling
        $(document).on('change', 'input[name="payment_method"]', function() {
            var method = $(this).val();
            logWithTime('BEST PRACTICES: Payment method changed to: ' + method);
            
            // Always trigger checkout update for any payment method change
            // WooCommerce will handle fee recalculation automatically
            $('body').trigger('update_checkout');
        });
        
        // FRONTEND CACHE FIX v1.23.26: Monitor DOM for surcharge changes
        var lastSurchargeAmount = '';
        function monitorSurchargeChanges() {
            var surchargeRow = $('.order-total tr:contains("Credit Card Surcharge")');
            if (surchargeRow.length) {
                var currentAmount = surchargeRow.find('.amount').text().trim();
                if (currentAmount !== lastSurchargeAmount) {
                    logWithTime('FRONTEND CACHE FIX: Surcharge amount changed from "' + lastSurchargeAmount + '" to "' + currentAmount + '"');
                    lastSurchargeAmount = currentAmount;
                    
                    // If we see the old incorrect amount, trigger refresh
                    if (currentAmount.includes('$17.14')) {
                        logWithTime('FRONTEND CACHE FIX: Detected incorrect surcharge amount $17.14, triggering refresh');
                        setTimeout(function() {
                            $('body').trigger('update_checkout');
                        }, 100);
                    }
                }
            }
        }
        
        // Monitor surcharge changes every 2 seconds
        setInterval(monitorSurchargeChanges, 2000);
        
        // BEST PRACTICES v1.23.16: Simple debug logging (no intervention)
        if (apwWooIntuitData.debug_mode) {
            setInterval(function() {
                var selectedPayment = $('input[name="payment_method"]:checked').val();
                if (selectedPayment === 'intuit_payments_credit_card') {
                    logCurrentSurchargeState();
                }
            }, 10000); // Log state every 10 seconds in debug mode
        }
    }

    // Run on document ready
    $(document).ready(initialize);

})(jQuery);
