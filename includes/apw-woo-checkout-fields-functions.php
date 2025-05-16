<?php
/**
 * Custom Checkout Field Functions for APW WooCommerce Plugin
 *
 * - Adds an 'Additional Emails' field to the checkout page, validates it,
 *   saves it to the order, displays it in admin, and adds emails as CC.
 * - Modifies 'Company' and 'Phone' fields to be required and adjusts layout.
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define the additional email field key
define('APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY', 'apw_woo_billing_additional_emails');

/**
 * Add the additional emails field to the checkout billing section.
 *
 * @param array $fields Existing checkout fields.
 * @return array Modified checkout fields.
 */
function apw_woo_add_additional_emails_field($fields)
{
    if (isset($fields['billing'])) {
        $fields['billing'][APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY] = array(
            'label' => __('Additional Emails (comma-separated)', 'apw-woo-plugin'),
            'placeholder' => _x('email1@example.com, email2@example.com', 'placeholder', 'apw-woo-plugin'),
            'required' => false,
            'class' => array('form-row-wide'), // Use standard WC classes
            'clear' => true,
            'priority' => 110 // Place it after standard email field (usually priority 110)
        );
    }
    return $fields;
}

// Note: The filter to add the 'additional_emails_field' will be combined later or needs to be called carefully.

/**
 * Validate the additional emails field during checkout process.
 * Checks if entered values are valid email addresses.
 */
function apw_woo_validate_additional_emails_field()
{
    $field_key = APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY;

    if (isset($_POST[$field_key]) && !empty(trim($_POST[$field_key]))) {
        $additional_emails_str = sanitize_text_field($_POST[$field_key]);
        $email_array = explode(',', $additional_emails_str);
        $email_array = array_map('trim', $email_array);
        $email_array = array_filter($email_array);

        $invalid_emails_found = false;
        foreach ($email_array as $email) {
            if (!is_email($email)) {
                $invalid_emails_found = true;
                break;
            }
        }

        if ($invalid_emails_found) {
            wc_add_notice(
                __('Please enter valid email addresses in the "Additional Emails" field, separated by commas.', 'apw-woo-plugin'),
                'error'
            );
        }
    }
}

add_action('woocommerce_checkout_process', 'apw_woo_validate_additional_emails_field');


/**
 * Save the additional emails field value to order meta.
 *
 * @param int $order_id The ID of the order being processed.
 */
function apw_woo_save_additional_emails_field($order_id)
{
    $field_key = APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY;

    if (isset($_POST[$field_key])) {
        $additional_emails_value = sanitize_text_field($_POST[$field_key]);
        update_post_meta($order_id, $field_key, $additional_emails_value);
    }
}

add_action('woocommerce_checkout_update_order_meta', 'apw_woo_save_additional_emails_field');


/**
 * Display the additional emails field value in the admin order details view.
 *
 * @param WC_Order $order The order object.
 */
function apw_woo_display_additional_emails_admin($order)
{
    $field_key = APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY;
    $additional_emails = $order->get_meta($field_key, true);

    if (!empty($additional_emails)) {
        ?>
        <div class="order_data_column apw-woo-admin-additional-emails">
            <h4><?php esc_html_e('Additional Emails', 'apw-woo-plugin'); ?></h4>
            <p><?php echo esc_html($additional_emails); ?></p>
        </div>
        <?php
    }
}

add_action('woocommerce_admin_order_data_after_billing_address', 'apw_woo_display_additional_emails_admin', 10, 1);


/**
 * Add the additional emails as CC recipients to specified WooCommerce order emails.
 *
 * @param string $headers The original email headers.
 * @param string $email_id The ID of the email being sent.
 * @param WC_Order $order The order object.
 * @return string Modified email headers.
 */
function apw_woo_add_cc_to_emails($headers, $email_id, $order)
{
    if (!is_a($order, 'WC_Order')) {
        return $headers;
    }

    $allowed_email_ids = apply_filters('apw_woo_cc_email_ids', array(
        'customer_on_hold_order',
        'customer_processing_order',
        'customer_completed_order',
        'customer_refunded_order',
        'customer_invoice',
    ));

    if (in_array($email_id, $allowed_email_ids)) {
        $field_key = APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY;
        $extra_emails_str = $order->get_meta($field_key, true);

        if (!empty($extra_emails_str)) {
            $extra_emails_array = explode(',', $extra_emails_str);
            $extra_emails_array = array_map('trim', $extra_emails_array);
            $extra_emails_array = array_filter($extra_emails_array);

            foreach ($extra_emails_array as $cc_email) {
                if (is_email($cc_email)) {
                    $headers .= "Cc: " . sanitize_email($cc_email) . "\r\n";
                }
            }
        }
    }

    return $headers;
}

add_filter('woocommerce_email_headers', 'apw_woo_add_cc_to_emails', 10, 3);

// --- NEW AND MODIFIED FUNCTIONS FOR COMPANY AND PHONE FIELDS ---

/**
 * Modify checkout fields to:
 * - Add/Ensure 'Company name' field for billing and shipping.
 * - Make billing company/phone always required.
 * - Position billing company before country on the same line.
 * - Add shipping phone field and make it conditionally required.
 * - Make shipping company field conditionally required.
 * - Position shipping company before country on the same line.
 * - Incorporates the 'Additional Emails' field addition.
 *
 * @param array $fields The original checkout fields.
 * @return array The modified checkout fields.
 */
function apw_woo_modify_checkout_fields_structure_and_requirements($fields)
{

    // --- Billing Fields ---
    if (isset($fields['billing'])) {

        // 1. Billing Company
        if (!isset($fields['billing']['billing_company'])) {
            $fields['billing']['billing_company'] = array(
                'label' => __('Company name', 'woocommerce'),
                'placeholder' => _x('Company name', 'placeholder', 'woocommerce'),
                'required' => true,
                'class' => array('form-row-first'),
                'priority' => 30,
            );
        } else {
            $fields['billing']['billing_company']['required'] = true;
            $fields['billing']['billing_company']['class'] = array('form-row-first');
            $fields['billing']['billing_company']['priority'] = 30;
        }

        // 2. Billing Country / Region
        if (isset($fields['billing']['billing_country'])) {
            $fields['billing']['billing_country']['class'] = array('form-row-last');
            $fields['billing']['billing_country']['priority'] = 40;
        }

        // 3. Billing Phone
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required'] = true;
            $fields['billing']['billing_phone']['class'] = array('form-row-wide');
        }

        // 4. Additional Emails Field (integrated from apw_woo_add_additional_emails_field)
        $fields['billing'][APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY] = array(
            'label' => __('Additional Emails (comma-separated)', 'apw-woo-plugin'),
            'placeholder' => _x('email1@example.com, email2@example.com', 'placeholder', 'apw-woo-plugin'),
            'required' => false,
            'class' => array('form-row-wide'),
            'clear' => true,
            'priority' => 110 // After standard email
        );
    }

    // --- Shipping Fields (Conditionally Handled) ---
    $ship_to_different_address_is_checked_on_load = false;
    if (function_exists('WC') && WC()->checkout() && method_exists(WC()->checkout(), 'get_value')) {
        $ship_to_different_address_is_checked_on_load = (bool)WC()->checkout()->get_value('ship_to_different_address');
    } elseif (isset($_POST['ship_to_different_address']) && $_POST['ship_to_different_address']) {
        $ship_to_different_address_is_checked_on_load = true;
    }

    if (isset($fields['shipping'])) {

        // 1. Shipping Company
        if (!isset($fields['shipping']['shipping_company'])) {
            $fields['shipping']['shipping_company'] = array(
                'label' => __('Company name', 'woocommerce'),
                'placeholder' => _x('Company name', 'placeholder', 'woocommerce'),
                'required' => true, // Always required
                'class' => array('form-row-first'),
                'priority' => 30,
            );
        } else {
            $fields['shipping']['shipping_company']['required'] = true; // Always required
            $fields['shipping']['shipping_company']['class'] = array('form-row-first');
            $fields['shipping']['shipping_company']['priority'] = 30;
        }

        // 2. Shipping Country / Region
        if (isset($fields['shipping']['shipping_country'])) {
            $fields['shipping']['shipping_country']['class'] = array('form-row-last');
            $fields['shipping']['shipping_country']['priority'] = 40;
        }

        // 3. Shipping Phone - Add this field as it doesn't exist by default
        $fields['shipping']['shipping_phone'] = array(
            'label' => __('Phone', 'woocommerce'),
            'placeholder' => _x('Phone', 'placeholder', 'woocommerce'),
            'required' => true, // Always required
            'class' => array('form-row-wide'),
            'clear' => true,
            'priority' => 100, // After address fields
            'type' => 'tel',
        );
    }

    return $fields;
}

add_filter('woocommerce_checkout_fields', 'apw_woo_modify_checkout_fields_structure_and_requirements', 20);

/**
 * Validate shipping company and phone if "Ship to a different address" is checked.
 * This function is called during the checkout submission process.
 */
function apw_woo_validate_conditional_shipping_fields_on_submit()
{
    if (isset($_POST['ship_to_different_address']) && 1 == $_POST['ship_to_different_address']) {

        // Validate shipping company field
        if (!isset($_POST['shipping_company']) || empty(trim($_POST['shipping_company']))) {
            wc_add_notice(__('Shipping company is a required field.', 'apw-woo-plugin'), 'error');
        }

        // Validate shipping phone field
        if (!isset($_POST['shipping_phone']) || empty(trim($_POST['shipping_phone']))) {
            wc_add_notice(__('Shipping phone is a required field.', 'apw-woo-plugin'), 'error');
        }
    }
}

add_action('woocommerce_checkout_process', 'apw_woo_validate_conditional_shipping_fields_on_submit');

/**
 * Make company field required in address forms
 */
function apw_woo_make_company_required($fields) {
    if (isset($fields['company'])) {
        $fields['company']['required'] = true;
        
        // Add required class
        if (!isset($fields['company']['class'])) {
            $fields['company']['class'] = array();
        }
        $fields['company']['class'][] = 'validate-required';
    }
    return $fields;
}

/**
 * Make phone field required in address forms
 */
function apw_woo_make_phone_required($fields) {
    if (isset($fields['phone']) || isset($fields['billing_phone']) || isset($fields['shipping_phone'])) {
        // Handle different possible field keys
        foreach (array('phone', 'billing_phone', 'shipping_phone') as $key) {
            if (isset($fields[$key])) {
                $fields[$key]['required'] = true;
                
                // Add required class
                if (!isset($fields[$key]['class'])) {
                    $fields[$key]['class'] = array();
                }
                $fields[$key]['class'][] = 'validate-required';
            }
        }
    }
    return $fields;
}

/**
 * Save shipping phone field from My Account edit address form
 */
function apw_woo_save_shipping_phone_field($customer_id, $posted_data) {
    if (isset($posted_data['shipping_phone'])) {
        update_user_meta($customer_id, 'shipping_phone', sanitize_text_field($posted_data['shipping_phone']));
    }
}

/**
 * Validate required address fields on My Account page
 */
function apw_woo_validate_address_fields() {
    // Only run on the edit address page
    if (!is_wc_endpoint_url('edit-address')) {
        return;
    }
    
    // Check if form was submitted
    if (!isset($_POST['action']) || $_POST['action'] !== 'edit_address') {
        return;
    }
    
    $load_address = isset($_GET['address']) ? wc_clean(wp_unslash($_GET['address'])) : 'billing';
    
    // Validate company field
    if (empty($_POST[$load_address . '_company'])) {
        wc_add_notice(__('Company is a required field.', 'apw-woo-plugin'), 'error');
    }
    
    // Validate phone field
    if (empty($_POST[$load_address . '_phone'])) {
        wc_add_notice(__('Phone is a required field.', 'apw-woo-plugin'), 'error');
    }
}

// Make fields required in My Account address forms
add_filter('woocommerce_default_address_fields', 'apw_woo_make_company_required', 999);
add_filter('woocommerce_billing_fields', 'apw_woo_make_phone_required', 999);
add_filter('woocommerce_shipping_fields', 'apw_woo_make_phone_required', 999);
add_action('template_redirect', 'apw_woo_validate_address_fields');
add_action('woocommerce_customer_save_address', 'apw_woo_save_shipping_phone_field', 20, 2);

// Debug logging for address pages
if (APW_WOO_DEBUG_MODE) {
    add_action('init', function() {
        if (is_account_page() && is_wc_endpoint_url('edit-address') && function_exists('apw_woo_log')) {
            $load_address = isset($_GET['address']) ? wc_clean(wp_unslash($_GET['address'])) : 'billing';
            apw_woo_log('On edit-address page for ' . $load_address . ' address - using custom template');
        }
    }, 999);
}

?>
