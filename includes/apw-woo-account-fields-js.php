<?php
/**
 * JavaScript enhancements for My Account address fields
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue JavaScript to ensure required fields work properly on My Account pages
 */
function apw_woo_enqueue_account_fields_js() {
    // Only on account pages
    if (!is_account_page() || !is_wc_endpoint_url('edit-address')) {
        return;
    }
    
    if (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
        apw_woo_log('Enqueuing account fields JS');
    }
    
    // Enqueue inline script
    wp_enqueue_script('apw-woo-account-fields', '', array('jquery'), APW_WOO_VERSION, true);
    wp_add_inline_script('apw-woo-account-fields', '
        jQuery(function($) {
            // Get the address type
            var addressType = window.location.href.indexOf("address=shipping") > -1 ? "shipping" : "billing";
            
            // Make company field required
            $("#" + addressType + "_company").prop("required", true);
            $("label[for=\'" + addressType + "_company\']").append("<abbr class=\'required\' title=\'required\'>*</abbr>");
            
            // Make phone field required
            $("#" + addressType + "_phone").prop("required", true);
            $("label[for=\'" + addressType + "_phone\']").append("<abbr class=\'required\' title=\'required\'>*</abbr>");
            
            if (window.console && window.console.log) {
                console.log("APW: Made " + addressType + " company and phone fields required");
            }
        });
    ');
}
add_action('wp_enqueue_scripts', 'apw_woo_enqueue_account_fields_js');
