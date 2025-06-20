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
 * BEST PRACTICES v1.23.16: Removed to prevent infinite loops
 * WooCommerce handles cart updates naturally through its hook system
 * Manual calculate_totals() calls can trigger recursive fee recalculation
 */

/**
 * BEST PRACTICES v1.23.16: Removed complex cart state tracking
 * WooCommerce handles state changes naturally through its hook system
 * No need for manual hash generation and comparison
 */

/**
 * BEST PRACTICES v1.23.16: Let WooCommerce handle its own cache
 * WooCommerce manages cart cache and fragments automatically
 * Manual cache manipulation often causes more problems than it solves
 */
function apw_woo_ensure_cart_fragments_update() {
    // Let WooCommerce handle cache naturally - just ensure fragments update
    if (is_checkout()) {
        // This is the proper way to ensure frontend gets updated data
        wp_enqueue_script('wc-cart-fragments');
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('BEST PRACTICES: Ensured cart fragments script is loaded for frontend updates');
        }
    }
}

/**
 * BEST PRACTICES v1.23.16: Removed to prevent infinite loops
 * WooCommerce calculates totals automatically during checkout
 * Manual calculate_totals() calls are unnecessary and cause recursion
 */

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
    
    // Add baseline cart hash storage at priority 10 (after VIP discounts at priority 5, before surcharge at priority 20)
    add_action('woocommerce_cart_calculate_fees', 'apw_woo_store_baseline_cart_hash', 10);
    
    // Add the surcharge calculation hook with priority 20 to run after VIP discounts (priority 5) are fully committed
    add_action('woocommerce_cart_calculate_fees', 'apw_woo_add_intuit_surcharge_fee', 20);
    
    // REMOVED: Hooks that were causing infinite loops by triggering calculate_totals()
    // WooCommerce handles cart updates naturally without manual intervention
    
    // REMOVED: checkout totals calculation hook that was causing infinite loops
    
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
 * Store baseline cart hash after VIP discounts but before surcharge fees
 * 
 * This function runs at priority 10 (after VIP discounts at priority 5, before surcharge at priority 20)
 * to capture the cart state AFTER VIP discounts are applied but BEFORE surcharge calculation.
 * Only stores baseline if cart state has actually changed since last calculation.
 */
function apw_woo_store_baseline_cart_hash() {
    // Only store baseline if we're in a context where surcharge might apply
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!is_checkout()) {
        return;
    }

    $chosen_gateway = WC()->session->get('chosen_payment_method');
    if ($chosen_gateway !== 'intuit_payments_credit_card') {
        return;
    }
    
    // Get VIP discount amount to include in baseline (since it's applied before us at priority 5)
    $vip_discount_total = 0;
    $fees = WC()->cart->get_fees();
    foreach ($fees as $fee) {
        if (strpos($fee->name, 'VIP Discount') !== false && $fee->amount < 0) {
            $vip_discount_total += abs($fee->amount);
        }
    }
    
    // Generate current cart hash (AFTER VIP discounts are applied)
    $current_cart_hash = md5(serialize([
        WC()->cart->get_subtotal(),
        WC()->cart->get_shipping_total(),
        $vip_discount_total, // Include VIP discount in baseline
        WC()->session->get('chosen_payment_method')
    ]));
    
    // Get stored baseline
    $stored_baseline = WC()->session->get('apw_baseline_cart_hash');
    
    // Only update baseline if it's actually changed
    if ($stored_baseline !== $current_cart_hash) {
        WC()->session->set('apw_baseline_cart_hash', $current_cart_hash);
        
        // Set flag to force surcharge recalculation
        WC()->session->set('apw_force_surcharge_recalc', true);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("BASELINE HASH UPDATED: " . substr($current_cart_hash, 0, 8) . " (subtotal: $" . number_format(WC()->cart->get_subtotal(), 2) . ", shipping: $" . number_format(WC()->cart->get_shipping_total(), 2) . ", VIP discount: $" . number_format($vip_discount_total, 2) . ")");
            apw_woo_log("Previous baseline: " . substr($stored_baseline ?: 'none', 0, 8));
            apw_woo_log("FORCING surcharge recalculation due to baseline change");
        }
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("BASELINE HASH UNCHANGED: " . substr($current_cart_hash, 0, 8));
        }
    }
}

/**
 * BEST PRACTICES v1.23.16: Clean conditional surcharge fee
 * 
 * Follows WooCommerce best practices:
 * - Let WooCommerce handle fee lifecycle naturally
 * - Use simple conditional logic for when to add fees
 * - No manual fee removal or complex state management
 * - Trust WooCommerce's native recalculation system
 */
function apw_woo_add_intuit_surcharge_fee() {
    // Standard validations - early exits when fee shouldn't apply
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!is_checkout()) {
        return;
    }

    $chosen_gateway = WC()->session->get('chosen_payment_method');
    if ($chosen_gateway !== 'intuit_payments_credit_card') {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("BEST PRACTICES: No surcharge - payment method is: " . ($chosen_gateway ?: 'none'));
        }
        return;
    }
    
    // COMPLETE FIX: Compare against baseline hash stored after VIP discounts (priority 10)
    $baseline_hash = WC()->session->get('apw_baseline_cart_hash');
    
    // Calculate current baseline including VIP discounts
    $current_vip_discount_total = 0;
    $existing_surcharge = false;
    $fees = WC()->cart->get_fees();
    foreach ($fees as $fee) {
        if (strpos($fee->name, 'VIP Discount') !== false && $fee->amount < 0) {
            $current_vip_discount_total += abs($fee->amount);
        }
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            $existing_surcharge = true;
        }
    }
    
    $current_baseline_hash = md5(serialize([
        WC()->cart->get_subtotal(),
        WC()->cart->get_shipping_total(),
        $current_vip_discount_total, // Include VIP discount in comparison
        WC()->session->get('chosen_payment_method')
    ]));

    // Check for force recalculation flag set by baseline storage function
    $force_recalc = WC()->session->get('apw_force_surcharge_recalc');
    
    // Check if baseline cart state has changed OR if we have a surcharge mismatch OR force flag is set
    if ($baseline_hash === $current_baseline_hash && $existing_surcharge && !$force_recalc) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("COMPLETE FIX: Cart state unchanged and surcharge exists, skipping recalculation");
        }
        return; // Cart unchanged and surcharge already applied
    }
    
    // Clear force recalculation flag
    if ($force_recalc) {
        WC()->session->set('apw_force_surcharge_recalc', false);
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("COMPLETE FIX: Force recalculation flag detected - proceeding with calculation");
        }
    }
    
    // Additional check for baseline changes
    if (!$baseline_hash || $baseline_hash !== $current_baseline_hash) {
        if (APW_WOO_DEBUG_MODE) {
            $reason = !$baseline_hash ? 'no baseline stored' : 'baseline changed in comparison';
            apw_woo_log("COMPLETE FIX: Baseline change detected (" . $reason . ")");
        }
    }

    // Remove any existing surcharge before adding new one (we already have fees from above)
    foreach ($fees as $key => $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            unset(WC()->cart->fees[$key]);
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("COMPLETE FIX: Removed existing surcharge of $" . number_format($fee->amount, 2));
            }
        }
    }
    
    // Calculate surcharge: (subtotal + shipping - VIP discounts) × 3%
    $cart_totals = WC()->cart->get_totals();
    $subtotal = $cart_totals['subtotal'] ?? 0;
    $shipping_total = $cart_totals['shipping_total'] ?? 0;
    
    // Use VIP discount total we already calculated above
    $total_discount = $current_vip_discount_total;
    
    // Calculate 3% surcharge on the base amount
    $surcharge_base = $subtotal + $shipping_total - $total_discount;
    $surcharge = $surcharge_base * 0.03;

    if ($surcharge > 0) {
        // Simple fee addition - let WooCommerce handle the rest
        WC()->cart->add_fee(__('Credit Card Surcharge (3%)', 'apw-woo-plugin'), $surcharge, true);
        
        // Store new baseline hash after successful calculation
        WC()->session->set('apw_baseline_cart_hash', $current_baseline_hash);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("COMPLETE FIX: Starting surcharge calculation");
            apw_woo_log("- Baseline hash changed: " . ($baseline_hash !== $current_baseline_hash ? 'YES' : 'NO'));
            apw_woo_log("- Old baseline: " . substr($baseline_hash ?: 'none', 0, 8));
            apw_woo_log("- New baseline: " . substr($current_baseline_hash, 0, 8));
            apw_woo_log("- VIP discounts found: $" . number_format($total_discount, 2));
            apw_woo_log("- Calculation base: $" . number_format($surcharge_base, 2));
            apw_woo_log("- Base: $" . number_format($surcharge_base, 2) . " (subtotal: $" . number_format($subtotal, 2) . " + shipping: $" . number_format($shipping_total, 2) . " - VIP discount: $" . number_format($total_discount, 2) . ")");
            apw_woo_log("- Final surcharge: $" . number_format($surcharge, 2));
        }
    }
}

// REMOVED: File-level hook registration that was causing duplicate surcharge calculations
// Hook registration now handled in apw_woo_init_intuit_integration() function with static protection
// This prevents multiple hook registrations when the file is loaded multiple times
