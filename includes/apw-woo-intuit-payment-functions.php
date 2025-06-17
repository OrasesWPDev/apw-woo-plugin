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
 * BEST PRACTICES v1.23.16: Simple cart change detection
 * Triggers WooCommerce native cart update when significant changes occur
 * Follows WooCommerce patterns - let the system handle fee lifecycle naturally
 */
function apw_woo_trigger_cart_update_on_changes() {
    // Simply trigger WooCommerce's native cart update
    // This will cause all fees to be recalculated naturally
    if (function_exists('WC') && WC()->cart) {
        WC()->cart->calculate_totals();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('BEST PRACTICES: Triggered native WooCommerce cart totals recalculation');
        }
    }
}

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
 * BEST PRACTICES v1.23.16: Simple checkout initialization
 * Let WooCommerce handle checkout state naturally
 * Only ensure cart totals are calculated (which WooCommerce does anyway)
 */
function apw_woo_ensure_checkout_totals_calculated() {
    if (!is_checkout() || is_admin()) {
        return;
    }
    
    // WooCommerce calculates totals automatically, but ensure it happens
    if (function_exists('WC') && WC()->cart) {
        WC()->cart->calculate_totals();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('BEST PRACTICES: Ensured cart totals are calculated on checkout');
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
    
    // BEST PRACTICES v1.23.16: Simple hooks for natural WooCommerce integration
    add_action('apw_woo_bulk_discount_applied', 'apw_woo_trigger_cart_update_on_changes', 10);
    add_action('woocommerce_cart_updated', 'apw_woo_trigger_cart_update_on_changes', 10);
    
    // BEST PRACTICES v1.23.16: Ensure checkout totals are calculated
    add_action('woocommerce_checkout_init', 'apw_woo_ensure_checkout_totals_calculated', 5);
    
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
    
    // Check if surcharge already exists (WooCommerce might call this multiple times)
    $existing_fees = WC()->cart->get_fees();
    foreach ($existing_fees as $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("BEST PRACTICES: Surcharge already exists: $" . number_format($fee->amount, 2));
            }
            return; // WooCommerce handles duplicate prevention naturally
        }
    }
    
    // Calculate surcharge: (subtotal + shipping - VIP discounts) × 3%
    $cart_totals = WC()->cart->get_totals();
    $subtotal = $cart_totals['subtotal'] ?? 0;
    $shipping_total = $cart_totals['shipping_total'] ?? 0;
    
    // Get VIP discounts (negative fee amounts)
    $total_discount = 0;
    foreach ($existing_fees as $fee) {
        if ($fee->amount < 0) {
            $total_discount += abs($fee->amount);
        }
    }
    
    // Calculate 3% surcharge on the base amount
    $surcharge_base = $subtotal + $shipping_total - $total_discount;
    $surcharge = $surcharge_base * 0.03;

    if ($surcharge > 0) {
        // Simple fee addition - let WooCommerce handle the rest
        WC()->cart->add_fee(__('Credit Card Surcharge (3%)', 'apw-woo-plugin'), $surcharge, true);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("BEST PRACTICES: Added surcharge:");
            apw_woo_log("- Base: $" . number_format($surcharge_base, 2) . " (subtotal: $" . number_format($subtotal, 2) . " + shipping: $" . number_format($shipping_total, 2) . " - discounts: $" . number_format($total_discount, 2) . ")");
            apw_woo_log("- Surcharge (3%): $" . number_format($surcharge, 2));
        }
    }
}

// REMOVED: File-level hook registration that was causing duplicate surcharge calculations
// Hook registration now handled in apw_woo_init_intuit_integration() function with static protection
// This prevents multiple hook registrations when the file is loaded multiple times
