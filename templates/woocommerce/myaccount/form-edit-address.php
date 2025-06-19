<?php
/**
 * Edit address form - APW WooCommerce Plugin Override
 *
 * This template overrides the default WooCommerce edit address form template,
 * ensuring company and phone fields are properly marked as required.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 3.6.0-apw.3
 *
 * Original WooCommerce template version: 3.6.0
 */

defined('ABSPATH') || exit;

$page_title = ('billing' === $load_address) ? esc_html__('Billing address', 'woocommerce') : esc_html__('Shipping address', 'woocommerce');

do_action('woocommerce_before_edit_account_address_form'); ?>

<?php if (!$load_address) : ?>
    <?php wc_get_template('myaccount/my-address.php'); ?>
<?php else : ?>

    <form method="post">

        <h3><?php echo apply_filters('woocommerce_my_account_edit_address_title', $page_title, $load_address); ?></h3><?php // @codingStandardsIgnoreLine ?>

        <div class="woocommerce-address-fields">
            <?php do_action("woocommerce_before_edit_address_form_{$load_address}"); ?>

            <div class="woocommerce-address-fields__field-wrapper">
                <?php
                // Debug: Log all available fields in debug mode
                if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                    apw_woo_log('EDIT ADDRESS TEMPLATE: Address fields for ' . $load_address . ': ' . print_r(array_keys($address), true));
                }
                
                foreach ($address as $key => $field) {
                    // Enhanced field enforcement for template level
                    if ($key === $load_address . '_company' || $key === $load_address . '_phone') {
                        $field['required'] = true;
                        
                        // Add required class to ensure proper styling
                        if (!isset($field['class'])) {
                            $field['class'] = array();
                        }
                        if (!in_array('required', $field['class'])) {
                            $field['class'][] = 'required';
                        }
                        if (!in_array('validate-required', $field['class'])) {
                            $field['class'][] = 'validate-required';
                        }
                        
                        // Ensure proper field configuration
                        if ($key === $load_address . '_company') {
                            $field['label'] = $field['label'] ?? __('Company name', 'woocommerce');
                            $field['placeholder'] = $field['placeholder'] ?? __('Company name', 'woocommerce');
                        }
                        
                        // Log field processing
                        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                            apw_woo_log('EDIT ADDRESS TEMPLATE: Processing required field: ' . $key . ' with classes: ' . implode(', ', $field['class']));
                        }
                    }

                    woocommerce_form_field($key, $field, wc_get_post_data_by_key($key, $field['value']));
                }
                
                // Fallback: If company field wasn't in the array, add it manually
                if (!isset($address[$load_address . '_company'])) {
                    if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                        apw_woo_log('EDIT ADDRESS TEMPLATE: Company field missing, adding fallback field');
                    }
                    
                    $company_field = array(
                        'label'        => __('Company name', 'woocommerce'),
                        'placeholder'  => __('Company name', 'woocommerce'),
                        'required'     => true,
                        'class'        => array('form-row-wide', 'required', 'validate-required'),
                        'autocomplete' => 'organization',
                        'type'         => 'text'
                    );
                    
                    woocommerce_form_field($load_address . '_company', $company_field, '');
                }
                ?>
            </div>

            <?php do_action("woocommerce_after_edit_address_form_{$load_address}"); ?>

            <p>
                <button type="submit" class="button" name="save_address"
                        value="<?php esc_attr_e('Save address', 'woocommerce'); ?>"><?php esc_html_e('Save address', 'woocommerce'); ?></button>
                <?php wp_nonce_field('woocommerce-edit_address', 'woocommerce-edit-address-nonce'); ?>
                <input type="hidden" name="action" value="edit_address"/>
            </p>
        </div>

    </form>

<?php endif; ?>

<?php do_action('woocommerce_after_edit_account_address_form'); ?>
