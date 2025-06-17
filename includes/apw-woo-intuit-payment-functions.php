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
 * Uses session-based tracking compatible with WooCommerce's fee architecture
 */
function apw_woo_reset_surcharge_calculation_flags() {
    // Clear session-based calculation tracking to force fresh calculation
    WC()->session->set('apw_surcharge_calculated_this_cycle', false);
    WC()->session->set('apw_cart_state_hash', ''); // Force hash mismatch
    
    // ENHANCED v1.23.15: Clear any WooCommerce totals cache
    WC()->cart->reset_fees();
    
    // ENHANCED v1.23.15: Force session regeneration for cart hash
    $cart_hash = WC()->cart->get_cart_hash();
    WC()->session->set('cart_hash', $cart_hash);
    
    // Set global flag for backward compatibility
    $GLOBALS['apw_woo_force_surcharge_recalc'] = true;
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('ENHANCED RESET: Cleared all surcharge session data and cart cache');
    }
}

/**
 * Generate cart state hash for change detection
 * Used to detect when cart totals change and surcharge needs recalculation
 */
function apw_woo_get_cart_state_hash() {
    $cart_data = array(
        'subtotal' => WC()->cart->get_subtotal(),
        'shipping_total' => WC()->cart->get_shipping_total(),
        'discount_total' => WC()->cart->get_discount_total(),
        'fee_total' => WC()->cart->get_fee_total(),
        'payment_method' => WC()->session->get('chosen_payment_method')
    );
    return md5(serialize($cart_data));
}

/**
 * FRONTEND SYNC v1.23.15: Aggressive cache clearing function
 * Clears ALL possible sources of cached cart data to force frontend updates
 */
function apw_woo_clear_all_cart_cache() {
    // Clear WooCommerce sessions
    WC()->session->set('wc_fragments_hash', '');
    WC()->session->set('wc_cart_hash', '');
    WC()->session->set('cart_hash', '');
    
    // Clear WordPress object cache for cart
    $customer_id = WC()->session->get_customer_id();
    if ($customer_id) {
        wp_cache_delete('cart-' . $customer_id, 'woocommerce');
        wp_cache_delete('cart_totals_' . $customer_id, 'woocommerce');
    }
    
    // Force fragments refresh
    if (function_exists('wc_setcookie')) {
        wc_setcookie('woocommerce_cart_hash', '', time() - 3600);
    }
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('AGGRESSIVE CACHE CLEAR: Cleared all cart-related cache for frontend sync');
    }
}

/**
 * FRONTEND SYNC v1.23.15: Force checkout surcharge recalculation
 * Ensures fresh surcharge calculation when checkout page loads
 */
function apw_woo_force_checkout_surcharge_recalc() {
    if (!is_checkout() || is_admin()) {
        return;
    }
    
    $chosen_gateway = WC()->session->get('chosen_payment_method');
    if ($chosen_gateway === 'intuit_payments_credit_card') {
        // Clear all surcharge-related session data to force fresh calculation
        WC()->session->set('apw_surcharge_calculated_this_cycle', false);
        WC()->session->set('apw_cart_state_hash', '');
        
        // Clear all cart cache
        apw_woo_clear_all_cart_cache();
        
        // Force cart totals recalculation
        WC()->cart->calculate_totals();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('CHECKOUT LOAD: Forced surcharge recalculation and cache clearing for checkout page');
        }
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
    
    // FRONTEND SYNC v1.23.15: Hook into checkout initialization to force fresh calculation
    add_action('woocommerce_checkout_init', 'apw_woo_force_checkout_surcharge_recalc', 5);
    
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
 * CONDITIONAL LOGIC FIX: Uses proper WooCommerce fee calculation approach with conditional logic
 * instead of trying to remove fees (which doesn't work with WooCommerce's architecture)
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
    if ($chosen_gateway !== 'intuit_payments_credit_card') {
        // Not using Intuit payment method - don't add surcharge
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CONDITIONAL SURCHARGE: Skipping surcharge - payment method is: " . ($chosen_gateway ?: 'none'));
        }
        return;
    }
    
    // Generate current cart state hash for change detection and session management
    $current_cart_hash = apw_woo_get_cart_state_hash();
    $stored_cart_hash = WC()->session->get('apw_cart_state_hash');
    
    // Track if this is a fresh calculation cycle
    $is_fresh_calculation = ($current_cart_hash !== $stored_cart_hash) || 
                           (isset($GLOBALS['apw_woo_force_surcharge_recalc']) && $GLOBALS['apw_woo_force_surcharge_recalc']);
    
    if ($is_fresh_calculation) {
        WC()->session->set('apw_cart_state_hash', $current_cart_hash);
        // Clear any previous calculation flags
        $GLOBALS['apw_woo_force_surcharge_recalc'] = false;
        WC()->session->set('apw_surcharge_calculated_this_cycle', false);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('CONDITIONAL SURCHARGE: Fresh calculation cycle detected');
        }
    }
    
    // Prevent duplicate surcharge addition within the same calculation cycle
    if (WC()->session->get('apw_surcharge_calculated_this_cycle')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CONDITIONAL SURCHARGE: Skipping - already calculated this cycle");
        }
        return;
    }
    
    // Check if surcharge already exists - if fresh calculation, we need to clear it first
    $existing_fees = WC()->cart->get_fees();
    $surcharge_exists = false;
    foreach ($existing_fees as $fee_key => $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            $surcharge_exists = true;
            if ($is_fresh_calculation) {
                // Fresh calculation cycle - remove the old surcharge so we can recalculate
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("CONDITIONAL SURCHARGE: Fresh cycle detected - removing existing surcharge of $" . number_format($fee->amount, 2));
                }
                unset(WC()->cart->fees[$fee_key]);
                $surcharge_exists = false; // We removed it, so proceed with new calculation
                
                // FRONTEND SYNC v1.23.15: Use aggressive cache clearing after stale fee removal
                apw_woo_clear_all_cart_cache();
                
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("CONDITIONAL SURCHARGE: Applied aggressive cache clearing after removing stale fee");
                }
                break;
            } else {
                // Not fresh calculation - surcharge exists and is valid
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("CONDITIONAL SURCHARGE: Surcharge already exists with amount: $" . number_format($fee->amount, 2) . " (not fresh calculation)");
                }
                WC()->session->set('apw_surcharge_calculated_this_cycle', true);
                return;
            }
        }
    }
    
    // If surcharge still exists after check (and it's not a fresh calculation), don't add another
    if ($surcharge_exists && !$is_fresh_calculation) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CONDITIONAL SURCHARGE: Surcharge exists and not fresh calculation - skipping");
        }
        WC()->session->set('apw_surcharge_calculated_this_cycle', true);
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
        
        // Mark this calculation cycle as complete
        WC()->session->set('apw_surcharge_calculated_this_cycle', true);
        
        // FRONTEND SYNC v1.23.15: Use aggressive cache clearing to force frontend updates
        apw_woo_clear_all_cart_cache();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("CONDITIONAL SURCHARGE: Added Credit Card Surcharge:");
            apw_woo_log("- Subtotal: $" . number_format($subtotal, 2));
            apw_woo_log("- Shipping: $" . number_format($shipping_total, 2));
            apw_woo_log("- Discount fees: $" . number_format($total_discount, 2));
            apw_woo_log("- Base for surcharge: $" . number_format($surcharge_base, 2));
            apw_woo_log("- Applied 3% surcharge: $" . number_format($surcharge, 2));
            apw_woo_log("- Fresh calculation: " . ($is_fresh_calculation ? 'YES' : 'NO'));
            apw_woo_log("- Cache-busting: Cart fragments cleared for frontend update");
        }
    }
}

// REMOVED: File-level hook registration that was causing duplicate surcharge calculations
// Hook registration now handled in apw_woo_init_intuit_integration() function with static protection
// This prevents multiple hook registrations when the file is loaded multiple times
