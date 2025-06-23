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
    
    // Add the surcharge calculation hook with priority 30 to run after VIP discounts and other fees
    add_action('woocommerce_cart_calculate_fees', 'apw_woo_add_intuit_surcharge_fee', 30);
    
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
 * Calculate credit card surcharge amount
 * 
 * Pure calculation function that determines surcharge based on cart totals
 * @return float Surcharge amount
 */
function apw_woo_calculate_credit_card_surcharge() {
    // Only calculate if payment method is credit card
    if (!is_checkout() || WC()->session->get('chosen_payment_method') !== 'intuit_payments_credit_card') {
        return 0;
    }
    
    // Get cart totals
    $cart = WC()->cart;
    $subtotal = $cart->get_subtotal();
    $shipping_total = $cart->get_shipping_total();
    
    // Calculate total discounts from negative fees (VIP discounts)
    $total_discounts = 0;
    $existing_fees = $cart->get_fees();
    
    foreach ($existing_fees as $fee) {
        // Only count negative fees that aren't surcharges themselves
        if ($fee->amount < 0 && strpos($fee->name, 'Surcharge') === false) {
            $total_discounts += abs($fee->amount);
        }
    }
    
    // Calculate surcharge base: subtotal + shipping - discounts
    $surcharge_base = $subtotal + $shipping_total - $total_discounts;
    $surcharge = max(0, $surcharge_base * 0.03); // 3%
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Surcharge calculation:");
        apw_woo_log("- Subtotal: $" . number_format($subtotal, 2));
        apw_woo_log("- Shipping: $" . number_format($shipping_total, 2));
        apw_woo_log("- Discounts: $" . number_format($total_discounts, 2));
        apw_woo_log("- Base: $" . number_format($surcharge_base, 2));
        apw_woo_log("- Surcharge (3%): $" . number_format($surcharge, 2));
    }
    
    return $surcharge;
}

/**
 * Remove existing credit card surcharge fees (ENHANCED v1.23.22)
 */
function apw_woo_remove_credit_card_surcharge() {
    $cart = WC()->cart;
    $fees = $cart->get_fees();
    $removed_count = 0;
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("ENHANCED REMOVAL: Starting fee removal process");
        apw_woo_log("ENHANCED REMOVAL: Found " . count($fees) . " total fees");
    }
    
    // Method 1: Remove from fees array by key
    foreach ($fees as $key => $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false || 
            strpos($fee->name, 'Surcharge') !== false) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("ENHANCED REMOVAL: Removing fee '$fee->name' (amount: $" . number_format($fee->amount, 2) . ") via key: $key");
            }
            unset($cart->fees[$key]);
            $removed_count++;
        }
    }
    
    // Method 2: Direct array manipulation for stubborn fees
    if (isset($cart->fees) && is_array($cart->fees)) {
        $cart->fees = array_filter($cart->fees, function($fee) {
            $keep = strpos($fee->name, 'Credit Card Surcharge') === false && 
                    strpos($fee->name, 'Surcharge') === false;
            if (!$keep && APW_WOO_DEBUG_MODE) {
                apw_woo_log("ENHANCED REMOVAL: Filtering out fee: '$fee->name'");
            }
            return $keep;
        });
    }
    
    // Method 3: Clear all WooCommerce fee-related caches
    if (WC()->session) {
        WC()->session->set('cart_fees', null);
        WC()->session->set('cart_totals', null);
        WC()->session->set('cart_contents_total', null);
    }
    
    // Method 4: Clear object cache for cart
    wp_cache_delete('cart_' . WC()->session->get_customer_id(), 'woocommerce');
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("ENHANCED REMOVAL: Removed $removed_count surcharge fees");
        apw_woo_log("ENHANCED REMOVAL: Remaining fees after removal: " . count($cart->get_fees()));
    }
}

/**
 * Apply credit card surcharge fee (ENHANCED v1.23.22)
 * 
 * Handles removal, deduplication, and application of surcharge with forced recalculation
 */
function apw_woo_apply_credit_card_surcharge() {
    // Step 1: Remove existing surcharge to prevent duplicates
    apw_woo_remove_credit_card_surcharge();
    
    // Step 2: Calculate new surcharge
    $surcharge = apw_woo_calculate_credit_card_surcharge();
    
    if ($surcharge > 0) {
        // Step 3: Double-check no surcharge already exists (deduplication)
        $existing_fees = WC()->cart->get_fees();
        $surcharge_exists = false;
        
        foreach ($existing_fees as $fee) {
            if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
                $surcharge_exists = true;
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("DEDUPLICATION: Found existing surcharge fee: '$fee->name' ($" . number_format($fee->amount, 2) . ")");
                }
                break;
            }
        }
        
        // Step 4: Only add if no existing surcharge found
        if (!$surcharge_exists) {
            // Use unique fee ID to prevent WooCommerce-level duplication
            $fee_id = 'apw_cc_surcharge_' . round($surcharge * 100); // Unique ID based on amount
            
            WC()->cart->add_fee(__('Credit Card Surcharge (3%)', 'apw-woo-plugin'), $surcharge, true);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("DEDUPLICATION: Added new surcharge: $" . number_format($surcharge, 2) . " (ID: $fee_id)");
            }
        } else {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("DEDUPLICATION: Skipped adding surcharge - already exists");
            }
        }
        
        // Step 5: Force complete WooCommerce recalculation
        WC()->cart->calculate_totals();
        
        // Step 6: Clear multiple cache levels
        if (WC()->session) {
            WC()->session->set('cart_totals', null);
            WC()->session->set('cart_fees', null);
            WC()->session->set('cart_contents_total', null);
            WC()->session->set('cart_contents_weight', null);
        }
        
        // Step 7: Clear object cache
        wp_cache_delete('cart_' . WC()->session->get_customer_id(), 'woocommerce');
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("ENHANCED APPLY: Forced totals recalculation and cache clearing");
            apw_woo_log("ENHANCED APPLY: Final fee count: " . count(WC()->cart->get_fees()));
        }
    }
}

/**
 * Main surcharge fee handler for WooCommerce hooks (ENHANCED v1.23.22)
 * 
 * This function is called by WooCommerce during cart calculation
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
            apw_woo_log("ENHANCED HANDLER: No surcharge - payment method is: " . ($chosen_gateway ?: 'none'));
        }
        // Remove surcharge if payment method changed and force recalculation
        apw_woo_remove_credit_card_surcharge();
        
        // Force complete recalculation when removing surcharge
        WC()->cart->calculate_totals();
        
        if (WC()->session) {
            WC()->session->set('cart_totals', null);
            WC()->session->set('cart_fees', null);
        }
        return;
    }
    
    // Apply surcharge with enhanced logic
    apw_woo_apply_credit_card_surcharge();
    
    // ENHANCED: Force immediate frontend updates for all contexts
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // Schedule a late hook to ensure frontend gets updated values
        add_action('woocommerce_after_calculate_totals', function() {
            // Clear all cart-related transients
            delete_transient('wc_cart_hash_' . WC()->session->get_customer_id());
            
            // Force session regeneration
            if (WC()->session) {
                WC()->session->set('cart_totals', null);
                WC()->session->set('cart_fees', null);
                WC()->session->save_data();
            }
            
            // Clear object cache
            wp_cache_flush_group('woocommerce');
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("ENHANCED HANDLER: Forced complete frontend refresh via late hook");
            }
        }, 999); // Very late priority to run after everything else
    }
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("ENHANCED HANDLER: Surcharge processing completed");
    }
}

// REMOVED: File-level hook registration that was causing duplicate surcharge calculations
// Hook registration now handled in apw_woo_init_intuit_integration() function with static protection
// This prevents multiple hook registrations when the file is loaded multiple times
