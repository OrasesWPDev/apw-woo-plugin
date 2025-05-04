/**
 * APW WooCommerce Intuit Payment Integration
 * 
 * This script ensures proper integration with the Intuit/QuickBooks payment gateway
 * by handling field creation and initialization.
 */
(function($) {
    'use strict';
    
    // Helper function for logging with timestamp
    function logWithTime(message) {
        if (typeof apwWooIntuitData !== 'undefined' && apwWooIntuitData.debug_mode) {
            const now = new Date();
            const timestamp = now.getHours() + ':' + now.getMinutes() + ':' + now.getSeconds();
            console.log('[' + timestamp + '] INTUIT INTEGRATION: ' + message);
        }
    }
    
    // Function to check if Intuit fields exist and create them if needed
    function ensureIntuitFieldsExist() {
        // Check if Intuit JS token field exists
        if ($('input[name="wc-intuit-payments-credit-card-js-token"]').length === 0) {
            logWithTime('Intuit JS token field missing - creating it');
            $('form.checkout').append('<input type="hidden" id="wc-intuit-payments-credit-card-js-token" name="wc-intuit-payments-credit-card-js-token" value="" />');
        } else {
            logWithTime('Intuit JS token field exists');
        }

        // Check if Intuit card type field exists
        if ($('input[name="wc-intuit-payments-credit-card-card-type"]').length === 0) {
            logWithTime('Intuit card type field missing - creating it');
            $('form.checkout').append('<input type="hidden" id="wc-intuit-payments-credit-card-card-type" name="wc-intuit-payments-credit-card-card-type" value="" />');
        } else {
            logWithTime('Intuit card type field exists');
        }
    }
    
    // Function to initialize Intuit payment processing
    function initIntuitPayment() {
        let ok = false;
        // Try sbjs tokenization
        if (window.sbjs && typeof window.sbjs.init === 'function') {
            logWithTime('Calling sbjs.init()');
            try {
                sbjs.init();
                logWithTime('sbjs.init OK');
                ok = true;
            } catch (e) {
                logWithTime('sbjs.init failed: ' + e.message);
            }
        }
        // Find and call the Intuit Payments form handler
        const handlerKey = Object.keys(window).find(k => k.startsWith('SV_WC_Payment_Form_Handler_'));
        if (handlerKey) {
            const handler = window[handlerKey];
            if (handler && typeof handler.init === 'function') {
                logWithTime(`Calling ${handlerKey}.init()`);
                try {
                    handler.init();
                    logWithTime(`${handlerKey}.init OK`);
                    ok = true;
                } catch (e) {
                    logWithTime(`${handlerKey}.init failed: ` + e.message);
                }
            } else {
                logWithTime(`${handlerKey}.init is not a function`);
            }
        } else {
            logWithTime('No SV_WC_Payment_Form_Handler found');
        }
        return ok;
    }
    
    // Function to monitor for Intuit object and initialize when available
    function monitorForIntuitObject() {
        // If already initialized, don't continue monitoring
        if (window.apwIntuitInitialized) {
            return;
        }
        
        // Check if WFQBC object exists now
        if (typeof window.WFQBC !== 'undefined') {
            logWithTime('Intuit WFQBC object detected during monitoring');
            if (initIntuitPayment()) {
                window.apwIntuitInitialized = true;
                logWithTime('Intuit payment successfully initialized');
            }
        } else {
            // Schedule another check
            setTimeout(monitorForIntuitObject, 500);
        }
    }
    
    // Main initialization function
    function initialize() {
        logWithTime('Initializing Intuit payment integration');

        // DEBUG: list any window keys matching "WFQBC" or having init() so we can find the real global
        if (typeof window !== 'undefined' && apwWooIntuitData.debug_mode) {
            var wfqKeys = Object.keys(window).filter(function(k){
                return /wfqbc|intuit|qb/i.test(k);
            });
            logWithTime('DEBUG: window globals matching /(wfqbc|intuit|qb)/i: ' + (wfqKeys.length ? wfqKeys.join(', ') : '[none]'));

            var initKeys = Object.keys(window).filter(function(k){
                try {
                    return window[k] && typeof window[k] === 'object' && typeof window[k].init === 'function';
                } catch (e) {
                    return false;
                }
            });
            logWithTime('DEBUG: window globals with .init() method: ' + (initKeys.length ? initKeys.join(', ') : '[none]'));
        }
        
        // Only run on checkout page
        if (typeof apwWooIntuitData === 'undefined' || !apwWooIntuitData.is_checkout) {
            logWithTime('Not on checkout page, integration inactive');
            return;
        }
        
        // Ensure gateway hidden fields exist for GenCert
        ensureIntuitFieldsExist();
        
        // Try to initialize immediately
        if (initIntuitPayment()) {
            window.apwIntuitInitialized = true;
            logWithTime('Intuit payment successfully initialized on first attempt');
        } else {
            // Start monitoring for the Intuit object
            logWithTime('Starting monitor for Intuit object');
            monitorForIntuitObject();
        }
        
        // Listen for checkout form updates
        $(document.body).on('updated_checkout', function() {
            logWithTime('Checkout updated event detected');
            
            // Ensure fields still exist after update
            ensureIntuitFieldsExist();
            
            // Try to initialize again if not already done
            if (!window.apwIntuitInitialized && initIntuitPayment()) {
                window.apwIntuitInitialized = true;
                logWithTime('Intuit payment initialized after checkout update');
            }
        });
        
        // Listen for payment method changes
        $(document.body).on('payment_method_selected', function() {
            logWithTime('Payment method changed');
            
            // Short delay to let the payment form render
            setTimeout(function() {
                // Try to initialize again if not already done
                if (!window.apwIntuitInitialized && initIntuitPayment()) {
                    window.apwIntuitInitialized = true;
                    logWithTime('Intuit payment initialized after payment method change');
                }
            }, 300);
        });
    }
    
    // Initialize when document is ready
    $(document).ready(initialize);
    
})(jQuery);
