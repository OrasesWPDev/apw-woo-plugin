/**
 * APW WooCommerce Payment Debugging Script
 * This script helps diagnose issues with payment processing, particularly for Intuit/QuickBooks gateway
 */
(function ($) {
    'use strict';

    // Helper function for logging with timestamp
    function logWithTime(message) {
        const now = new Date();
        const timestamp = now.getHours() + ':' + now.getMinutes() + ':' + now.getSeconds();
        // Only log if debug mode is generally enabled for the plugin
        if (window.apwWooData && window.apwWooData.debug_mode === true) {
            console.log('[' + timestamp + '] PAYMENT DEBUG: ' + message);
        }
    }

    // Log when document is ready
    $(document).ready(function () {
        logWithTime('Document ready - starting payment debugging');

        // Check if we're on the checkout page
        if (!$('body').hasClass('woocommerce-checkout') || !$('form.checkout').length) {
            logWithTime('Not on checkout page, debugging inactive');
            return;
        }

        logWithTime('Checkout form detected - debugging active');

        function checkIntuitState(context) {
            logWithTime(`(${context}) Initial payment box check:`);
            const $paymentBox = $('#payment');
            logWithTime(`- Payment box exists: ${$paymentBox.length}`);
            logWithTime(`- Payment box visible: ${$paymentBox.is(':visible')}`);
            logWithTime(`- Payment box display: ${$paymentBox.css('display')}`);
            logWithTime(`- Payment box height: ${$paymentBox.height()}px`);

            // Check for payment methods
            const $paymentMethods = $('input[name="payment_method"]');
            logWithTime(`- Payment methods found: ${$paymentMethods.length}`);
            $paymentMethods.each(function () {
                logWithTime(`  - Method: ${$(this).val()} (checked: ${$(this).is(':checked')})`);
            });

            // Check for token fields existence
            logWithTime(`- Intuit JS token field exists: ${$('input[name="wc-intuit-payments-credit-card-js-token"]').length}`);
            logWithTime(`- Intuit card type field exists: ${$('input[name="wc-intuit-payments-credit-card-card-type"]').length}`);

            // Log all hidden fields in the payment form for debugging
            logWithTime('- Hidden payment fields:');
            $('#payment input[type="hidden"]').each(function () {
                logWithTime(`  - ${$(this).attr('name')}: ${$(this).val()}`);
            });

            // Check for Intuit globals
            const sbjsExists = typeof window.sbjs !== 'undefined';
            logWithTime(`- sbjs object exists: ${sbjsExists}`);
            if (sbjsExists) {
                logWithTime(`  - sbjs.init function exists: ${typeof window.sbjs.init === 'function'}`);
            }
            const genCertExists = typeof window.GenCert !== 'undefined';
            logWithTime(`- GenCert object exists: ${genCertExists}`);
            if (genCertExists) {
                logWithTime(`  - GenCert.init function exists: ${typeof window.GenCert.init === 'function'}`);
            }
            const wfqbcExists = typeof window.WFQBC !== 'undefined';
            logWithTime(`- WFQBC object exists: ${wfqbcExists}`);
            if (wfqbcExists) {
                logWithTime(`  - WFQBC.init function exists: ${typeof window.WFQBC.init === 'function'}`);
            }

            // Check for terms and conditions
            const $termsCheckbox = $('#terms');
            logWithTime(`- Terms checkbox exists: ${$termsCheckbox.length}`);
            if ($termsCheckbox.length) {
                logWithTime(`  - Terms checked: ${$termsCheckbox.is(':checked')}`);
            }
        }

        // Initial check after a delay
        setTimeout(function () {
            checkIntuitState('Initial Load');
        }, 1000);

        // Monitor checkout updates
        $(document.body).off('updated_checkout.apwDebug').on('updated_checkout.apwDebug', function () {
            logWithTime('Checkout updated event fired');
            // Check state again after update, with delay
            setTimeout(function () {
                checkIntuitState('After Update');
            }, 500); // Reduced delay slightly
        });

        // Monitor payment method selection
        $(document.body).off('payment_method_selected.apwDebug').on('payment_method_selected.apwDebug', function () {
            logWithTime('Payment method selected event fired');
            // Check state again after selection, with delay
            setTimeout(function () {
                checkIntuitState('After Method Select');
            }, 500);
        });


        // Monitor form submission
        $(document).on('submit', 'form.checkout', function (e) {
            logWithTime('Checkout form submitted');

            // Log payment method
            const $selectedMethod = $('input[name="payment_method"]:checked');
            logWithTime(`- Selected payment method: ${$selectedMethod.length ? $selectedMethod.val() : 'none'}`);

            // Log token and card type fields VALUES at submission time
            const $tokenField = $('input[name="wc-intuit-payments-credit-card-js-token"]');
            const $cardTypeField = $('input[name="wc-intuit-payments-credit-card-card-type"]');

            logWithTime(`- Intuit Token Field Value: ${$tokenField.length ? ($tokenField.val() || 'EMPTY') : 'MISSING'}`);
            logWithTime(`- Intuit Card Type Value: ${$cardTypeField.length ? ($cardTypeField.val() || 'EMPTY') : 'MISSING'}`);


            // Check terms again at submission
            const $termsCheckbox = $('#terms');
            logWithTime(`- Terms checkbox exists: ${$termsCheckbox.length}`);
            if ($termsCheckbox.length) {
                logWithTime(`  - Terms checked: ${$termsCheckbox.is(':checked')}`);
                if (!$termsCheckbox.is(':checked')) {
                    logWithTime('  - WARNING: Terms not checked - this could block submission');
                }
            }

            // Final assessment
            if (!$tokenField.length || !$tokenField.val()) {
                logWithTime('DIAGNOSIS: Payment token missing or empty at submission - should cause "invalid token" errors');
            }
            if (!$cardTypeField.length || !$cardTypeField.val()) {
                logWithTime('DIAGNOSIS: Card type missing or empty at submission - should cause "invalid card type" errors');
            }
        });
    });

})(jQuery);