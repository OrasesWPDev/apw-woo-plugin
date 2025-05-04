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
        // Check if payment token field exists
        if ($('input[name="payment_token"]').length === 0) {
            logWithTime('Payment token field missing - creating it');
            $('form.checkout').append('<input type="hidden" id="payment_token" name="payment_token" value="" />');
        } else {
            logWithTime('Payment token field exists');
        }
        
        // Check if card type field exists
        if ($('input[name="card_type"]').length === 0) {
            logWithTime('Card type field missing - creating it');
            $('form.checkout').append('<input type="hidden" id="card_type" name="card_type" value="" />');
        } else {
            logWithTime('Card type field exists');
        }
    }
    
    // Function to initialize Intuit payment processing
    function initIntuitPayment() {
        // Check if WFQBC object exists
        if (typeof window.WFQBC !== 'undefined') {
            logWithTime('Intuit WFQBC object found');
            
            // Check if init function exists
            if (typeof window.WFQBC.init === 'function') {
                logWithTime('Calling WFQBC.init()');
                try {
                    window.WFQBC.init();
                    logWithTime('WFQBC.init() called successfully');
                    return true;
                } catch (e) {
                    logWithTime('Error calling WFQBC.init(): ' + e.message);
                }
            } else {
                logWithTime('WFQBC.init is not a function');
            }
        } else {
            logWithTime('Intuit WFQBC object not found');
        }
        
        return false;
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
        
        // Ensure the required fields exist
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
