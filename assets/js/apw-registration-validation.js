/**
 * APW Registration Fields Validation
 * 
 * Client-side validation for custom registration fields
 * Provides immediate feedback and improved user experience
 *
 * @package APW_Woo_Plugin
 * @since 1.18.0
 */

(function ($) {
    'use strict';

    // Debug logging function (only active when debug mode is enabled)
    function apwRegLog(message) {
        if (typeof console !== 'undefined' && console.log && window.apwWooDebug) {
            console.log('[APW Registration] ' + message);
        }
    }

    /**
     * Registration field validation class
     */
    var APWRegistrationValidation = {
        
        /**
         * Initialize validation
         */
        init: function() {
            this.bindEvents();
            apwRegLog('Registration validation initialized');
        },

        /**
         * Bind validation events
         */
        bindEvents: function() {
            var self = this;
            
            // Real-time validation on field blur
            $('.apw-registration-field input').on('blur', function() {
                self.validateField($(this));
            });

            // Clear validation on field focus
            $('.apw-registration-field input').on('focus', function() {
                self.clearFieldValidation($(this));
            });

            // Form submission validation
            $('.woocommerce-form-register').on('submit', function(e) {
                if (!self.validateAllFields()) {
                    e.preventDefault();
                    apwRegLog('Form submission prevented due to validation errors');
                }
            });

            // Phone number formatting
            $('#apw_phone').on('input', function() {
                self.formatPhoneNumber($(this));
            });
        },

        /**
         * Validate individual field
         */
        validateField: function($field) {
            var fieldName = $field.attr('name');
            var fieldValue = $field.val().trim();
            var isValid = true;
            var errorMessage = '';

            // Reset field state
            this.clearFieldValidation($field);

            switch (fieldName) {
                case 'apw_first_name':
                    if (fieldValue === '') {
                        isValid = false;
                        errorMessage = 'First Name is required.';
                    } else if (fieldValue.length < 2) {
                        isValid = false;
                        errorMessage = 'First Name must be at least 2 characters long.';
                    }
                    break;

                case 'apw_last_name':
                    if (fieldValue === '') {
                        isValid = false;
                        errorMessage = 'Last Name is required.';
                    } else if (fieldValue.length < 2) {
                        isValid = false;
                        errorMessage = 'Last Name must be at least 2 characters long.';
                    }
                    break;

                case 'apw_company':
                    if (fieldValue === '') {
                        isValid = false;
                        errorMessage = 'Company Name is required.';
                    } else if (fieldValue.length < 2) {
                        isValid = false;
                        errorMessage = 'Company Name must be at least 2 characters long.';
                    }
                    break;

                case 'apw_phone':
                    if (fieldValue === '') {
                        isValid = false;
                        errorMessage = 'Phone Number is required.';
                    } else if (!this.isValidPhoneNumber(fieldValue)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid phone number.';
                    }
                    break;

                case 'apw_referred_by':
                    // Optional field - only validate if not empty
                    if (fieldValue !== '' && fieldValue.length < 2) {
                        isValid = false;
                        errorMessage = 'Referred By must be at least 2 characters long.';
                    }
                    break;
            }

            // Apply validation state
            if (isValid) {
                this.markFieldValid($field);
            } else {
                this.markFieldInvalid($field, errorMessage);
            }

            return isValid;
        },

        /**
         * Validate all registration fields
         */
        validateAllFields: function() {
            var self = this;
            var isFormValid = true;

            $('.apw-registration-field input').each(function() {
                if (!self.validateField($(this))) {
                    isFormValid = false;
                }
            });

            return isFormValid;
        },

        /**
         * Mark field as valid
         */
        markFieldValid: function($field) {
            $field.removeClass('woocommerce-invalid').addClass('woocommerce-validated');
            $field.closest('.apw-registration-field').find('.field-error').remove();
            apwRegLog('Field ' + $field.attr('name') + ' marked as valid');
        },

        /**
         * Mark field as invalid
         */
        markFieldInvalid: function($field, errorMessage) {
            $field.removeClass('woocommerce-validated').addClass('woocommerce-invalid');
            
            // Remove existing error message
            $field.closest('.apw-registration-field').find('.field-error').remove();
            
            // Add new error message
            $field.after('<span class="field-error" style="color: #e74c3c; font-size: 0.875rem; display: block; margin-top: 0.25rem;">' + errorMessage + '</span>');
            
            apwRegLog('Field ' + $field.attr('name') + ' marked as invalid: ' + errorMessage);
        },

        /**
         * Clear field validation state
         */
        clearFieldValidation: function($field) {
            $field.removeClass('woocommerce-invalid woocommerce-validated');
            $field.closest('.apw-registration-field').find('.field-error').remove();
        },

        /**
         * Validate phone number format
         */
        isValidPhoneNumber: function(phone) {
            // Remove all non-digit characters
            var digitsOnly = phone.replace(/\D/g, '');
            
            // Check if it's a valid length (7-15 digits)
            return digitsOnly.length >= 7 && digitsOnly.length <= 15;
        },

        /**
         * Format phone number as user types
         */
        formatPhoneNumber: function($field) {
            var value = $field.val().replace(/\D/g, ''); // Remove non-digits
            var formattedValue = '';

            if (value.length >= 10) {
                // Format as (XXX) XXX-XXXX for US numbers
                formattedValue = '(' + value.substr(0, 3) + ') ' + value.substr(3, 3) + '-' + value.substr(6, 4);
                if (value.length > 10) {
                    formattedValue += ' ext. ' + value.substr(10);
                }
            } else if (value.length >= 7) {
                // Format as XXX-XXXX for shorter numbers
                formattedValue = value.substr(0, 3) + '-' + value.substr(3);
            } else {
                formattedValue = value;
            }

            // Only update if the formatted value is different
            if ($field.val() !== formattedValue) {
                $field.val(formattedValue);
                apwRegLog('Phone number formatted: ' + formattedValue);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Only initialize on account/registration pages
        if ($('.woocommerce-form-register').length > 0) {
            APWRegistrationValidation.init();
        }
    });

    // Make validation object available globally for debugging
    window.APWRegistrationValidation = APWRegistrationValidation;

})(jQuery);