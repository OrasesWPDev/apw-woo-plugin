<?php
/**
 * Intuit/QuickBooks Payment Gateway Integration Functions
 *
 * Provides integration between the APW WooCommerce Plugin and the Intuit/QuickBooks
 * payment gateway, ensuring proper field creation and initialization.
 *
 * @package APW_Woo_Plugin
 * @since 1.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if Intuit/QuickBooks payment gateway is active and available
 *
 * @return bool True if Intuit gateway is active
 */
function apw_woo_is_intuit_gateway_active() {
    // Check if the payment gateway class exists
    if (class_exists('WC_Gateway_Intuit_QBMS') || class_exists('WC_Gateway_QBMS_Credit_Card')) {
        return true;
    }
    
    // Check if the gateway is in the available gateways list
    if (function_exists('WC') && method_exists(WC(), 'payment_gateways')) {
        // Get the WC_Payment_Gateways instance and list its available gateways
        $gateways_api      = WC()->payment_gateways();
        $available_gateways = $gateways_api->get_available_payment_gateways();
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('DEBUG: available gateways: ' . implode(', ', array_keys($available_gateways)));
        }
        return isset($available_gateways['intuit_qbms_credit_card'])
            || isset($available_gateways['intuit_payments_credit_card']);
    }
    
    return false;
}

/**
 * Add hidden fields for Intuit payment processing
 * 
 * This function adds the necessary hidden fields that Intuit's JavaScript
 * expects to find and populate during the payment process.
 */
function apw_woo_add_intuit_payment_fields() {
    // Only add fields if we're on the checkout page
    if (!is_checkout()) {
        return;
    }
    
    // Only proceed if the Intuit gateway is active
    if (!apw_woo_is_intuit_gateway_active()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Intuit gateway not active - skipping payment field addition');
        }
        return;
    }
    
    // Custom field injection disabled; using Intuit's own hidden inputs
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Added Intuit payment token and card type fields to checkout');
    }
}

// Add the fields early in the checkout form
add_action('woocommerce_checkout_before_customer_details', 'apw_woo_add_intuit_payment_fields', 10);

/**
 * Enqueue scripts for Intuit payment integration
 */
function apw_woo_enqueue_intuit_scripts() {
    // Only enqueue on checkout page
    if (!is_checkout()) {
        return;
    }
    
    // Only proceed if the Intuit gateway is active
    if (!apw_woo_is_intuit_gateway_active()) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Intuit gateway not active - skipping script enqueue');
        }
        return;
    }
    
    // Enqueue our integration script
    $js_file = 'apw-woo-intuit-integration.js';
    $js_path = APW_WOO_PLUGIN_DIR . 'assets/js/' . $js_file;
    
    if (file_exists($js_path)) {
        wp_enqueue_script(
            'apw-woo-intuit-integration',
            APW_WOO_PLUGIN_URL . 'assets/js/' . $js_file,
            array('jquery', 'wc-checkout', 'wc-intuit-qbms-checkout'), // Dependencies
            filemtime($js_path),
            true // Load in footer
        );
        
        // Pass data to our script
        wp_localize_script(
            'apw-woo-intuit-integration',
            'apwWooIntuitData',
            array(
                'debug_mode' => APW_WOO_DEBUG_MODE,
                'is_checkout' => is_checkout()
            )
        );
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Enqueued Intuit integration script');
        }
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Intuit integration script not found at: ' . $js_path, 'warning');
        }
    }
}

// Enqueue scripts
add_action('wp_enqueue_scripts', 'apw_woo_enqueue_intuit_scripts');

/**
 * Reset surcharge calculation flags when cart changes significantly
 * This ensures surcharge is recalculated when VIP discounts are applied/removed
 */
function apw_woo_reset_surcharge_calculation_flags() {
    // Set global flag to force surcharge recalculation
    $GLOBALS['apw_woo_force_surcharge_recalc'] = true;
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('SURCHARGE FIX: Reset calculation flags due to cart changes');
    }
}

/**
 * Initialize Intuit payment gateway integration
 */
function apw_woo_init_intuit_integration() {
    // Debug: log gateway detection before proceeding
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('DEBUG: apw_woo_is_intuit_gateway_active() returns: ' . (function_exists('apw_woo_is_intuit_gateway_active') && apw_woo_is_intuit_gateway_active() ? 'true' : 'false'));
    }
    // Prevent multiple initializations
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    // Check if Intuit gateway is active
    $intuit_active = apw_woo_is_intuit_gateway_active();
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Intuit gateway active check: ' . ($intuit_active ? 'YES' : 'NO'));
    }
    
    if (!$intuit_active) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Intuit gateway not active - integration skipped');
        }
        return;
    }
    
    // Add the payment fields
    add_action('woocommerce_checkout_before_customer_details', 'apw_woo_add_intuit_payment_fields', 10);
    
    // Add script enqueuing
    add_action('wp_enqueue_scripts', 'apw_woo_enqueue_intuit_scripts');
    
    // Add a filter to ensure our fields are preserved during checkout
    add_filter('woocommerce_checkout_posted_data', 'apw_woo_preserve_intuit_fields');
    
    // Add the surcharge calculation hook with priority 15 to run after discounts (priority 5)
    add_action('woocommerce_cart_calculate_fees', 'apw_woo_add_intuit_surcharge_fee', 15);
    
    // CRITICAL FIX: Hook into discount application to trigger surcharge recalculation
    add_action('apw_woo_bulk_discount_applied', 'apw_woo_reset_surcharge_calculation_flags', 10);
    add_action('woocommerce_cart_updated', 'apw_woo_reset_surcharge_calculation_flags', 10);
    
    // Mark as initialized
    $initialized = true;
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Intuit payment integration initialized with surcharge recalculation triggers');
    }
}

/**
 * Preserve Intuit payment fields during checkout process
 *
 * @param array $data Posted checkout data
 * @return array Modified checkout data
 */
function apw_woo_preserve_intuit_fields($data) {
    // Ensure payment token and card type are preserved if they exist
    if (isset($_POST['wc-intuit-payments-credit-card-js-token']) && !empty($_POST['wc-intuit-payments-credit-card-js-token'])) {
        $data['wc-intuit-payments-credit-card-js-token'] = sanitize_text_field($_POST['wc-intuit-payments-credit-card-js-token']);
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Intuit token preserved (length: " . strlen($data['wc-intuit-payments-credit-card-js-token']) . ")");
        }
    }

    if (isset($_POST['wc-intuit-payments-credit-card-card-type']) && !empty($_POST['wc-intuit-payments-credit-card-card-type'])) {
        $data['wc-intuit-payments-credit-card-card-type'] = sanitize_text_field($_POST['wc-intuit-payments-credit-card-card-type']);
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Intuit card type preserved: " . $data['wc-intuit-payments-credit-card-card-type']);
        }
    }
    
    return $data;
}

/**
 * Add Intuit credit card surcharge fee on checkout only
 * 
 * Applies a 3% surcharge when Intuit credit card payment method is selected.
 * Only applies on the checkout page to avoid affecting cart calculations.
 * 
 * COMPREHENSIVE FIX: Removes stale surcharge fees and recalculates based on current cart state
 */
function apw_woo_add_intuit_surcharge_fee() {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Only run on the checkout page (not cart or elsewhere)
    if (!is_checkout()) {
        return;
    }

    $chosen_gateway = WC()->session->get('chosen_payment_method');
    if ($chosen_gateway === 'intuit_payments_credit_card') {
        
        // NEW APPROACH: Check if surcharge already exists instead of trying to remove
        // This prevents duplicate fees when fee removal methods don't work
        $existing_fees = WC()->cart->get_fees();
        $surcharge_already_exists = false;
        
        foreach ($existing_fees as $fee) {
            if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
                $surcharge_already_exists = true;
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("SURCHARGE FIX: Credit card surcharge already exists with amount: $" . number_format($fee->amount, 2));
                }
                break;
            }
        }
        
        // Skip adding surcharge if one already exists
        if ($surcharge_already_exists) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("SURCHARGE FIX: Skipping surcharge addition - already exists in cart");
            }
            return;
        }
        
        // Calculate surcharge base: subtotal + shipping - VIP discounts (before tax)
        $cart_totals = WC()->cart->get_totals();
        $subtotal = $cart_totals['subtotal'] ?? 0;
        $shipping_total = $cart_totals['shipping_total'] ?? 0;
        
        // Get actual discount from negative cart fees (VIP discounts are fees, not coupons)
        $total_discount = 0;
        foreach (WC()->cart->get_fees() as $fee) {
            if ($fee->amount < 0) {
                $total_discount += abs($fee->amount);
            }
        }
        
        // Base for surcharge calculation: subtotal + shipping - discount fees (before tax)
        $surcharge_base = $subtotal + $shipping_total - $total_discount;
        
        // Calculate 3% surcharge on the pre-tax total
        $surcharge = $surcharge_base * 0.03;

        if ($surcharge > 0) {
            WC()->cart->add_fee(__('Credit Card Surcharge (3%)', 'apw-woo-plugin'), $surcharge, true);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("SURCHARGE FIX: Added Credit Card Surcharge:");
                apw_woo_log("- Subtotal: $" . number_format($subtotal, 2));
                apw_woo_log("- Shipping: $" . number_format($shipping_total, 2));
                apw_woo_log("- Discount fees: $" . number_format($total_discount, 2));
                apw_woo_log("- Base for surcharge: $" . number_format($surcharge_base, 2));
                apw_woo_log("- Applied 3% surcharge: $" . number_format($surcharge, 2));
                apw_woo_log("- No duplicate surcharge detected");
            }
        }
    } else {
        // Not using Intuit payment method - surcharge shouldn't be added but may persist from previous selection
        if (APW_WOO_DEBUG_MODE) {
            $existing_fees = WC()->cart->get_fees();
            $has_surcharge = false;
            foreach ($existing_fees as $fee) {
                if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
                    $has_surcharge = true;
                    break;
                }
            }
            if ($has_surcharge) {
                apw_woo_log("SURCHARGE FIX: Non-Intuit payment method selected - surcharge from previous selection may persist until page refresh");
            }
        }
    }
}

// REMOVED: File-level hook registration that was causing duplicate surcharge calculations
// Hook registration now handled in apw_woo_init_intuit_integration() function with static protection
// This prevents multiple hook registrations when the file is loaded multiple times
