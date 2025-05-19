<?php
/**
 * Account-related functions for APW WooCommerce Plugin
 *
 * @package APW_Woo_Plugin
 * @since 1.16.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure company field is included and required for all address types
 *
 * @param array $fields Default address fields
 * @return array Modified address fields
 */
function apw_woo_customize_default_address_fields($fields) {
    if (isset($fields['company'])) {
        $fields['company']['required'] = true;
        $fields['company']['class'][] = 'required';
        $fields['company']['priority'] = 25; // Adjust priority to control field order
    }
    
    if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
        apw_woo_log('ACCOUNT FIELDS: Company field set as required in default address fields');
    }
    
    return $fields;
}
add_filter('woocommerce_default_address_fields', 'apw_woo_customize_default_address_fields');

/**
 * Ensure phone field is required for billing
 *
 * @param array $fields Billing fields
 * @return array Modified billing fields
 */
function apw_woo_customize_billing_fields($fields) {
    if (isset($fields['billing_phone'])) {
        $fields['billing_phone']['required'] = true;
        if (!isset($fields['billing_phone']['class'])) {
            $fields['billing_phone']['class'] = array();
        }
        if (!in_array('required', $fields['billing_phone']['class'])) {
            $fields['billing_phone']['class'][] = 'required';
        }
    }
    
    if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
        apw_woo_log('ACCOUNT FIELDS: Phone field set as required in billing fields');
    }
    
    return $fields;
}
add_filter('woocommerce_billing_fields', 'apw_woo_customize_billing_fields');

/**
 * Add phone field to shipping fields (WooCommerce doesn't include this by default)
 *
 * @param array $fields Shipping fields
 * @return array Modified shipping fields
 */
function apw_woo_customize_shipping_fields($fields) {
    // Add phone field to shipping if it doesn't exist
    if (!isset($fields['shipping_phone'])) {
        $fields['shipping_phone'] = array(
            'label'        => __('Phone', 'woocommerce'),
            'required'     => true,
            'class'        => array('form-row-wide', 'required'),
            'clear'        => true,
            'type'         => 'tel',
            'validate'     => array('phone'),
            'autocomplete' => 'tel',
            'priority'     => 100,
        );
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('ACCOUNT FIELDS: Added phone field to shipping fields');
        }
    } else {
        // If it exists, make sure it's required
        $fields['shipping_phone']['required'] = true;
        if (!isset($fields['shipping_phone']['class'])) {
            $fields['shipping_phone']['class'] = array();
        }
        if (!in_array('required', $fields['shipping_phone']['class'])) {
            $fields['shipping_phone']['class'][] = 'required';
        }
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('ACCOUNT FIELDS: Updated existing phone field in shipping fields');
        }
    }
    
    return $fields;
}
add_filter('woocommerce_shipping_fields', 'apw_woo_customize_shipping_fields');

/**
 * Add validation for required company and phone fields
 *
 * @param array $address_fields Address fields array
 * @param string $load_address The address being loaded (billing or shipping)
 * @return array Modified address fields
 */
function apw_woo_validate_required_address_fields($address_fields, $load_address) {
    // Ensure company field is required
    if (isset($address_fields[$load_address . '_company'])) {
        $address_fields[$load_address . '_company']['required'] = true;
        if (!isset($address_fields[$load_address . '_company']['class'])) {
            $address_fields[$load_address . '_company']['class'] = array();
        }
        if (!in_array('required', $address_fields[$load_address . '_company']['class'])) {
            $address_fields[$load_address . '_company']['class'][] = 'required';
        }
    }
    
    // Ensure phone field is required
    if (isset($address_fields[$load_address . '_phone'])) {
        $address_fields[$load_address . '_phone']['required'] = true;
        if (!isset($address_fields[$load_address . '_phone']['class'])) {
            $address_fields[$load_address . '_phone']['class'] = array();
        }
        if (!in_array('required', $address_fields[$load_address . '_phone']['class'])) {
            $address_fields[$load_address . '_phone']['class'][] = 'required';
        }
    }
    
    return $address_fields;
}
add_filter('woocommerce_address_to_edit', 'apw_woo_validate_required_address_fields', 10, 2);
