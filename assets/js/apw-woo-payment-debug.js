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
        console.log('[' + timestamp + '] PAYMENT DEBUG: ' + message);
    }

    // Log when document is ready
    $(document).ready(function () {
        logWithTime('Document ready - starting payment debugging');

        // Check if we're on the checkout page
        if (!$('form.checkout').length) {
            logWithTime('Not on checkout page, debugging inactive');
            return;
        }

        logWithTime('Checkout form detected - debugging active');

        // Log initial payment box state
        setTimeout(function () {
            logWithTime('Initial payment box check:');
            logWithTime('- Payment box exists: ' + $('#payment').length);
            logWithTime('- Payment box visible: ' + $('#payment').is(':visible'));
            logWithTime('- Payment box display: ' + $('#payment').css('display'));
            logWithTime('- Payment box height: ' + $('#payment').height() + 'px');

            // Check for payment methods
            const paymentMethods = $('input[name="payment_method"]');
            logWithTime('- Payment methods found: ' + paymentMethods.length);
            paymentMethods.each(function () {
                logWithTime('  - Method: ' + $(this).val() + ' (checked: ' + $(this).is(':checked') + ')');
            });

            // Check for token fields
            logWithTime('- Intuit JS token field exists: ' + $('input[name="wc-intuit-payments-credit-card-js-token"]').length);
            logWithTime('- Intuit card type field exists: ' + $('input[name="wc-intuit-payments-credit-card-card-type"]').length);

            // Log all hidden fields in the payment form for debugging
            logWithTime('- Hidden payment fields:');
            $('#payment input[type="hidden"]').each(function () {
                logWithTime('  - ' + $(this).attr('name') + ': ' + $(this).val());
            });

             // Check for Intuit globals
             logWithTime('- sbjs object exists: ' + (typeof window.sbjs !== 'undefined'));
             if (typeof window.sbjs !== 'undefined') {
                 logWithTime('  - sbjs.init function exists: ' + (typeof window.sbjs.init === 'function'));
             }
             logWithTime('- GenCert object exists: ' + (typeof window.GenCert !== 'undefined'));
             if (typeof window.GenCert !== 'undefined') {
                 logWithTime('  - GenCert.init function exists: ' + (typeof window.GenCert.init === 'function'));
             }

            // Check for terms and conditions
            const termsCheckbox = $('#terms');
            logWithTime('- Terms checkbox exists: ' + termsCheckbox.length);
            if (termsCheckbox.length) {
                logWithTime('  - Terms checked: ' + termsCheckbox.is(':checked'));
            }
        }, 1000);

        // Monitor checkout updates
        $(document.body).on('updated_checkout', function () {
            logWithTime('Checkout updated event fired');

            // Check payment box after update
            setTimeout(function () {
                logWithTime('Post-update payment box check:');
                logWithTime('- Payment box visible: ' + $('#payment').is(':visible'));
                logWithTime('- Payment box height: ' + $('#payment').height() + 'px');

                // Check for token fields again
                logWithTime('- Payment token field exists: ' + $('input[name="payment_token"]').length);
                logWithTime('- Card type field exists: ' + $('input[name="card_type"]').length);

                // Try to trigger Intuit initialization if it exists
                 // Check for Intuit globals after update
                 logWithTime('- sbjs object exists: ' + (typeof window.sbjs !== 'undefined'));
                 if (typeof window.sbjs !== 'undefined') {
                     logWithTime('  - sbjs.init function exists: ' + (typeof window.sbjs.init === 'function'));
                 }
                 logWithTime('- GenCert object exists: ' + (typeof window.GenCert !== 'undefined'));
                 if (typeof window.GenCert !== 'undefined') {
                     logWithTime('  - GenCert.init function exists: ' + (typeof window.GenCert.init === 'function'));
                 }
            }, 1500);
        });

        // Monitor form submission
        $(document).on('submit', 'form.checkout', function (e) {
            logWithTime('Checkout form submitted');

            // Log payment method
            const selectedMethod = $('input[name="payment_method"]:checked');
            logWithTime('- Selected payment method: ' + (selectedMethod.length ? selectedMethod.val() : 'none'));

            // Log token fields
            const paymentToken = $('input[name="payment_token"]');
            const cardType = $('input[name="card_type"]');

            logWithTime('- Payment token field exists: ' + paymentToken.length);
            if (paymentToken.length) {
                const tokenValue = paymentToken.val();
                logWithTime('  - Token value: ' + (tokenValue ? tokenValue.substring(0, 5) + '...' : 'empty'));
                logWithTime('  - Token length: ' + (tokenValue ? tokenValue.length : 0));
            }

            logWithTime('- Card type field exists: ' + cardType.length);
            if (cardType.length) {
                logWithTime('  - Card type value: ' + cardType.val());
            }

            // Check terms
            const termsCheckbox = $('#terms');
            logWithTime('- Terms checkbox exists: ' + termsCheckbox.length);
            if (termsCheckbox.length) {
                logWithTime('  - Terms checked: ' + termsCheckbox.is(':checked'));

                // If terms exist but aren't checked, this could block submission
                if (!termsCheckbox.is(':checked')) {
                    logWithTime('  - WARNING: Terms not checked - this could block submission');
                }
            }

            // Log all form data for debugging
            logWithTime('- Form data summary:');
            const formData = $(this).serializeArray();
            let paymentFieldsFound = 0;

            for (let i = 0; i < formData.length; i++) {
                const field = formData[i];
                // Only log payment-related fields to avoid cluttering the console
                if (field.name.indexOf('payment') !== -1 ||
                    field.name === 'terms' ||
                    field.name === 'card_type' ||
                    field.name === 'woocommerce-process-checkout-nonce') {

                    let valueToLog = field.value;
                    // Mask sensitive data
                    if (field.name === 'payment_token' && valueToLog) {
                        valueToLog = valueToLog.substring(0, 5) + '...' +
                            (valueToLog.length > 10 ? valueToLog.substring(valueToLog.length - 5) : '');
                    }

                    logWithTime('  - ' + field.name + ': ' + valueToLog);
                    paymentFieldsFound++;
                }
            }

            logWithTime('- Payment-related fields found: ' + paymentFieldsFound);

            // Final assessment
            if (!paymentToken.length || !paymentToken.val()) {
                logWithTime('DIAGNOSIS: Payment token missing or empty - this will cause "invalid token" errors');
            }

            if (!cardType.length || !cardType.val()) {
                logWithTime('DIAGNOSIS: Card type missing or empty - this will cause "invalid card type" errors');
            }
        });
    });

})(jQuery);
