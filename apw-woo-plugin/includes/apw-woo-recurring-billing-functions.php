<?php
/**
 * Handles the 'Preferred Monthly Billing Method' field for recurring products.
 *
 * @package APW_Woo_Plugin
 * @since   1.11.0 // Or your next version
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Recurring_Billing Class
 *
 * Manages the display, validation, and saving of the preferred billing
 * method field for products tagged as 'recurring'.
 */
class APW_Woo_Recurring_Billing
{

    /**
     * Instance of this class
     * @var self
     */
    private static $instance = null;

    /**
     * Product tag slug that triggers the field display.
     * @var string
     */
    private const RECURRING_TAG_SLUG = 'recurring';

    /**
     * Meta key for storing the selected billing preference.
     * @var string
     */
    private const META_KEY = '_apw_woo_preferred_billing_method'; // Prefixed meta key

    /**
     * Get instance
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to prevent direct instantiation.
     */
    private function __construct()
    {
        if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
            apw_woo_log('Recurring Billing class constructed');
        }
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
            apw_woo_log('Initializing Recurring Billing hooks...');
        }

        // Display the field after customer details section, before order review heading
        add_action('woocommerce_checkout_after_customer_details', array($this, 'display_preferred_billing_field'), 10);

        // Validate the field during checkout process
        add_action('woocommerce_checkout_process', array($this, 'validate_preferred_billing_field'));

        // Save the field value when the order is created
        add_action('woocommerce_checkout_create_order', array($this, 'save_preferred_billing_field'), 10, 2);

        // Display the saved value in the admin order view
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_preferred_billing_admin'), 10, 1); // Pass 1 arg ($order)

        if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
            apw_woo_log('Recurring Billing hooks initialized.');
        }
    }

    /**
     * Check if the current WooCommerce cart contains any product with the recurring tag.
     *
     * @return bool True if a recurring product is found, false otherwise.
     */
    private function apw_check_cart_for_recurring_tag()
    {
        // Ensure WooCommerce and cart are available
        if (!function_exists('WC') || !isset(WC()->cart) || WC()->cart === null) { // Added isset check
            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Check: WC() or WC()->cart not available.', 'warning');
            }
            return false;
        }

        $cart = WC()->cart;
        if ($cart->is_empty()) {
            return false; // No need to check an empty cart
        }

        $recurring_found = false;
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            // Use has_term to check for the specific tag slug
            if (has_term(self::RECURRING_TAG_SLUG, 'product_tag', $product_id)) {
                $recurring_found = true;
                if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Recurring Billing Check: Found product ID ' . $product_id . ' with tag "' . self::RECURRING_TAG_SLUG . '".');
                }
                break; // Found one, no need to check further
            }
        }

        return $recurring_found;
    }


    /**
     * Display the "Preferred monthly billing method" field on the checkout page.
     *
     * Hooked into 'woocommerce_checkout_after_customer_details'.
     * Only displays if a product with the recurring tag is in the cart.
     *
     * @param WC_Checkout $checkout The checkout object (passed by the hook).
     */
    public function display_preferred_billing_field($checkout) // Corrected to expect $checkout object
    {
        if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
            apw_woo_log('Recurring Billing Display: display_preferred_billing_field hook triggered.');
        }

        // Check if we received a valid checkout object
        if (!is_a($checkout, 'WC_Checkout')) {
            // If not, try getting it globally as a fallback
            $checkout = WC()->checkout();
            if (!is_a($checkout, 'WC_Checkout')) {
                if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Recurring Billing Display: Failed to get valid WC_Checkout object.', 'error');
                }
                return; // Cannot proceed without a valid checkout object
            }
        }

        // Check if a recurring product tag exists in the cart
        if ($this->apw_check_cart_for_recurring_tag()) {
            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Display: Recurring product found in cart. Displaying field.');
            }

            echo '<div class="apw-woo-preferred-billing-wrapper">'; // Add a wrapper for styling/targeting

            // Get the previously selected value if validation failed and page reloaded
            $selected_value = $checkout->get_value(self::META_KEY);

            woocommerce_form_field(
                self::META_KEY, // Use the prefixed meta key as the field name
                array(
                    'type' => 'select',
                    'class' => array('apw-woo-preferred-billing-field', 'form-row-wide'), // Add custom class
                    'label' => __('Preferred monthly billing method', 'apw-woo-plugin'),
                    'required' => true, // Make the field required
                    'options' => array(
                        '' => __(' -- Select an Option -- ', 'apw-woo-plugin'), // Placeholder
                        'YES-NEW' => __('New Customer - must provide new monthly billing method', 'apw-woo-plugin'),
                        'NO-EXISTING' => __('Existing customer – use default monthly billing on file', 'apw-woo-plugin'),
                        'YES-EXISTING' => __('Existing customer – use new monthly billing method', 'apw-woo-plugin'),
                    ),
                    'default' => '', // Default to placeholder
                ),
                $selected_value // Pass the potentially previously selected value
            );

            echo '</div>';

        } else {
            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Display: No recurring product found in cart. Field not displayed.');
            }
        }
    }

    /**
     * Validate the preferred billing field during checkout submission.
     *
     * Hooked into 'woocommerce_checkout_process'.
     * Adds an error if a recurring product is in the cart and the field is empty.
     */
    public function validate_preferred_billing_field()
    {
        if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
            apw_woo_log('Recurring Billing Validation: validate_preferred_billing_field hook triggered.');
        }

        // Only validate if a recurring product is in the cart (meaning the field should be displayed and required)
        if ($this->apw_check_cart_for_recurring_tag()) {
            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Validation: Recurring product found, checking field value.');
            }
            // Check if the field is set in the POST data and if it's empty
            if (isset($_POST[self::META_KEY]) && empty(trim($_POST[self::META_KEY]))) {
                // Add an error notice
                wc_add_notice(__('Please select your <strong>Preferred monthly billing method</strong>.', 'apw-woo-plugin'), 'error');
                if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Recurring Billing Validation: Field is empty. Added error notice.');
                }
            } else if (isset($_POST[self::META_KEY])) {
                if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Recurring Billing Validation: Field has value: ' . sanitize_text_field($_POST[self::META_KEY]));
                }
            } else {
                // This case should ideally not happen if the field is displayed, but good to log
                if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Recurring Billing Validation: Field key ' . self::META_KEY . ' not found in POST data.', 'warning');
                }
                // Add error notice here as well, as the field is required but missing from POST
                wc_add_notice(__('The <strong>Preferred monthly billing method</strong> field is required.', 'apw-woo-plugin'), 'error');
            }
        } else {
            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Validation: No recurring product in cart, skipping validation.');
            }
        }
    }

    /**
     * Save the preferred billing field value to order meta.
     *
     * Hooked into 'woocommerce_checkout_create_order'.
     *
     * @param WC_Order $order The order object being created.
     * @param array $data The data submitted via the checkout form.
     */
    public function save_preferred_billing_field($order, $data)
    {
        // Check if our field's value exists in the POST data (validation should have already run)
        if (isset($_POST[self::META_KEY]) && !empty($_POST[self::META_KEY])) {
            // Sanitize the value before saving
            $selected_value = sanitize_text_field($_POST[self::META_KEY]);

            // Save the sanitized value to the order meta
            $order->update_meta_data(self::META_KEY, $selected_value);

            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Saving: Saved value "' . $selected_value . '" to order ID ' . $order->get_id() . ' with meta key ' . self::META_KEY);
            }
        } elseif ($this->apw_check_cart_for_recurring_tag()) {
            // Log if the field was required but somehow not present during saving (shouldn't happen if validation worked)
            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Saving: WARNING - Recurring product in cart, but meta key ' . self::META_KEY . ' not found or empty in POST during saving.', 'warning');
            }
        }
    }

    /**
     * Display the saved preferred billing method in the admin order details.
     *
     * Hooked into 'woocommerce_admin_order_data_after_billing_address'.
     *
     * @param WC_Order $order The order object being viewed.
     */
    public function display_preferred_billing_admin($order)
    {
        // Get the saved meta data
        $saved_value = $order->get_meta(self::META_KEY);

        // Only display if a value was actually saved
        if ($saved_value) {
            $display_text = __('N/A', 'apw-woo-plugin'); // Default text

            // Translate the saved key back to the descriptive text
            switch ($saved_value) {
                case 'YES-NEW':
                    $display_text = __('New Customer - must provide new monthly billing method', 'apw-woo-plugin');
                    break;
                case 'NO-EXISTING':
                    $display_text = __('Existing customer – use default monthly billing on file', 'apw-woo-plugin');
                    break;
                case 'YES-EXISTING':
                    $display_text = __('Existing customer – use new monthly billing method', 'apw-woo-plugin');
                    break;
            }

            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                apw_woo_log('Recurring Billing Admin Display: Displaying value for Order ID ' . $order->get_id() . ': ' . $display_text);
            }

            // Output the information in the admin
            ?>
            <div class="apw-preferred-billing-admin order_data_column"> <?php // Add custom class ?>
                <h4><?php esc_html_e('Customer Billing Preference', 'apw-woo-plugin'); ?></h4>
                <p>
                    <strong><?php esc_html_e('Preferred Monthly Billing:', 'apw-woo-plugin'); ?></strong><br>
                    <?php echo esc_html($display_text); ?>
                </p>
            </div>
            <?php
        } else {
            // Optionally log if no value was found, but only if expected (e.g., if order contains recurring item)
            // We might need apw_check_order_for_recurring_tag() if this becomes noisy.
            if (function_exists('apw_woo_log') && APW_WOO_DEBUG_MODE) {
                // Check if any item in this specific order has the recurring tag
                $has_recurring = false;
                foreach ($order->get_items() as $item_id => $item) {
                    if (has_term(self::RECURRING_TAG_SLUG, 'product_tag', $item->get_product_id())) {
                        $has_recurring = true;
                        break;
                    }
                }
                if ($has_recurring) {
                    apw_woo_log('Recurring Billing Admin Display: No saved value found for key ' . self::META_KEY . ' on Order ID ' . $order->get_id() . ' which contains a recurring item.');
                }
            }
        }
    }

} // End class APW_Woo_Recurring_Billing

/**
 * Function to initialize the Recurring Billing class.
 * To be called from the main plugin file's init sequence.
 */
function apw_woo_initialize_recurring_billing() // Function definition should be here
{
    // Ensure the class exists before getting instance
    if (class_exists('APW_Woo_Recurring_Billing')) {
        APW_Woo_Recurring_Billing::get_instance();
    } elseif (APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
        apw_woo_log('Recurring Billing class (APW_Woo_Recurring_Billing) not found during initialization.', 'error');
    }
}

?>