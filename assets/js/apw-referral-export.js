/**
 * APW Referral Export Admin JavaScript
 * 
 * Handles the admin interface for referral exports
 * Provides dynamic form behavior and AJAX export functionality
 *
 * @package APW_Woo_Plugin
 * @since 1.18.0
 */

(function ($) {
    'use strict';

    /**
     * Referral export admin functionality
     */
    var APWReferralExportAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initializeForm();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Show/hide filter options based on export type
            $('#export_type').on('change', this.toggleFilterOptions);
            
            // Handle AJAX export (if implemented)
            $('.apw-ajax-export').on('click', this.handleAjaxExport);
            
            // Form validation
            $('form').on('submit', this.validateForm);
        },

        /**
         * Initialize form state
         */
        initializeForm: function() {
            // Set default dates if date range is selected
            this.setDefaultDates();
            
            // Show appropriate filter options
            this.toggleFilterOptions();
        },

        /**
         * Toggle filter options based on export type
         */
        toggleFilterOptions: function() {
            var exportType = $('#export_type').val();
            
            // Hide all filter options first
            $('.filter-option').hide();
            
            // Show relevant filter option
            switch (exportType) {
                case 'by_referrer':
                    $('#referrer-filter').show();
                    break;
                case 'date_range':
                    $('#date-filter').show();
                    break;
            }
        },

        /**
         * Set default dates for date range filter
         */
        setDefaultDates: function() {
            var today = new Date();
            var thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            // Set default end date to today
            if (!$('#end_date').val()) {
                $('#end_date').val(today.toISOString().split('T')[0]);
            }
            
            // Set default start date to 30 days ago
            if (!$('#start_date').val()) {
                $('#start_date').val(thirtyDaysAgo.toISOString().split('T')[0]);
            }
        },

        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            var exportType = $('#export_type').val();
            var isValid = true;
            var errorMessage = '';

            // Clear previous errors
            $('.form-error').remove();

            // Validate based on export type
            switch (exportType) {
                case 'by_referrer':
                    var referrerName = $('#referrer_name').val().trim();
                    if (!referrerName) {
                        isValid = false;
                        errorMessage = 'Please enter a referrer name to filter by.';
                        $('#referrer_name').focus();
                    }
                    break;

                case 'date_range':
                    var startDate = $('#start_date').val();
                    var endDate = $('#end_date').val();
                    
                    if (!startDate || !endDate) {
                        isValid = false;
                        errorMessage = 'Please select both start and end dates.';
                        $('#start_date').focus();
                    } else if (new Date(startDate) > new Date(endDate)) {
                        isValid = false;
                        errorMessage = 'Start date cannot be after end date.';
                        $('#start_date').focus();
                    }
                    break;
            }

            // Show error if validation failed
            if (!isValid) {
                e.preventDefault();
                $('<div class="notice notice-error form-error"><p>' + errorMessage + '</p></div>')
                    .insertAfter('.apw-export-options h2');
                
                $('html, body').animate({
                    scrollTop: $('.form-error').offset().top - 100
                }, 500);
            }

            return isValid;
        },

        /**
         * Handle AJAX export (future enhancement)
         */
        handleAjaxExport: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.text('Exporting...').prop('disabled', true);
            
            // Prepare data
            var data = {
                action: 'apw_export_referrals',
                nonce: apwReferralExport.nonce,
                export_type: $('#export_type').val(),
                referrer_name: $('#referrer_name').val(),
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val(),
                include_order_data: $('#include_order_data').is(':checked') ? 1 : 0
            };

            // Send AJAX request
            $.post(apwReferralExport.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        // Create download link
                        var downloadLink = $('<a>')
                            .attr('href', response.data.download_url)
                            .attr('download', response.data.file_name)
                            .text('Download ' + response.data.file_name);
                        
                        // Show success message
                        $('<div class="notice notice-success"><p>Export completed! ' + 
                          response.data.count + ' users exported. </p></div>')
                            .append(downloadLink)
                            .insertAfter('.apw-export-options h2');
                        
                        // Trigger download
                        downloadLink[0].click();
                        
                    } else {
                        // Show error message
                        $('<div class="notice notice-error"><p>Export failed: ' + 
                          (response.data || 'Unknown error') + '</p></div>')
                            .insertAfter('.apw-export-options h2');
                    }
                })
                .fail(function() {
                    // Show error message
                    $('<div class="notice notice-error"><p>Export failed due to a network error.</p></div>')
                        .insertAfter('.apw-export-options h2');
                })
                .always(function() {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('.notice').first().offset().top - 100
                    }, 500);
                });
        },

        /**
         * Show loading indicator
         */
        showLoading: function(message) {
            message = message || 'Processing...';
            
            if ($('.apw-loading').length === 0) {
                $('<div class="apw-loading notice notice-info">')
                    .append('<p><span class="spinner is-active"></span> ' + message + '</p>')
                    .insertAfter('.apw-export-options h2');
            }
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('.apw-loading').remove();
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on the referral export page
        if ($('#export_type').length > 0) {
            APWReferralExportAdmin.init();
        }
    });

    // Make available globally for debugging
    window.APWReferralExportAdmin = APWReferralExportAdmin;

})(jQuery);