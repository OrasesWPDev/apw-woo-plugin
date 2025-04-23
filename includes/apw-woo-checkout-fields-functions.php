<?php
/**
 * Custom Checkout Field Functions for APW WooCommerce Plugin
 *
 * Adds an 'Additional Emails' field to the checkout page, validates it,
 * saves it to the order, displays it in admin, and adds emails as CC to specified order emails.
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define the new field key
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

add_filter('woocommerce_checkout_fields', 'apw_woo_add_additional_emails_field');


/**
 * Validate the additional emails field during checkout process.
 * Checks if entered values are valid email addresses.
 */
function apw_woo_validate_additional_emails_field()
{
    $field_key = APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY;

    // Check if the field is set and not empty
    if (isset($_POST[$field_key]) && !empty(trim($_POST[$field_key]))) {
        $additional_emails_str = sanitize_text_field($_POST[$field_key]);
        $email_array = explode(',', $additional_emails_str);
        $email_array = array_map('trim', $email_array);
        $email_array = array_filter($email_array); // Remove empty entries after trimming

        $invalid_emails_found = false;
        foreach ($email_array as $email) {
            if (!is_email($email)) {
                $invalid_emails_found = true;
                break; // Stop checking after the first invalid email is found
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
        // Sanitize the input before saving
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
    $additional_emails = $order->get_meta($field_key, true); // Use $order object method

    if (!empty($additional_emails)) {
        ?>
        <div class="order_data_column apw-woo-admin-additional-emails">
            <h4><?php esc_html_e('Additional Emails', 'apw-woo-plugin'); ?></h4>
            <p><?php echo esc_html($additional_emails); ?></p> <?php // Escaped output ?>
        </div>
        <?php
    }
}

// Removed duplicate add_action here
add_action('woocommerce_admin_order_data_after_billing_address', 'apw_woo_display_additional_emails_admin', 10, 1); // Hooking after billing address for better placement


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
    // Ensure we have a valid order object
    if (!is_a($order, 'WC_Order')) {
        return $headers;
    }

    // Define which email notifications should include the CC
    $allowed_email_ids = apply_filters('apw_woo_cc_email_ids', array(
        'customer_on_hold_order',
        'customer_processing_order',
        'customer_completed_order',
        'customer_refunded_order',
        'customer_invoice',
        // Add other relevant email IDs here if needed
    ));

    // Check if the current email ID is allowed
    if (in_array($email_id, $allowed_email_ids)) {
        $field_key = APW_WOO_ADDITIONAL_EMAIL_FIELD_KEY;
        $extra_emails_str = $order->get_meta($field_key, true);

        if (!empty($extra_emails_str)) {
            $extra_emails_array = explode(',', $extra_emails_str);
            $extra_emails_array = array_map('trim', $extra_emails_array);
            $extra_emails_array = array_filter($extra_emails_array); // Remove empty values

            // Add each valid email as a CC header
            foreach ($extra_emails_array as $cc_email) {
                if (is_email($cc_email)) { // Double-check validity before adding
                    $headers .= "Cc: " . sanitize_email($cc_email) . "\r\n";
                }
            }
        }
    }

    return $headers;
}

add_filter('woocommerce_email_headers', 'apw_woo_add_cc_to_emails', 10, 3);

?>