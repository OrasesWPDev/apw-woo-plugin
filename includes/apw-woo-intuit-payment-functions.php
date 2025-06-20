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
                'is_checkout' => is_checkout(),
                'surcharge_updated' => WC()->session ? WC()->session->get('apw_surcharge_updated') : null,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('apw_woo_nonce')
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
    
    // Add baseline cart hash storage at priority 15 on woocommerce_before_calculate_totals (after VIP discounts at priority 5)
    add_action('woocommerce_before_calculate_totals', 'apw_woo_store_baseline_cart_hash', 15);
    
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
 * Store baseline cart hash after VIP discounts are applied
 * 
 * This function runs at priority 15 on woocommerce_before_calculate_totals 
 * (after VIP discounts at priority 5) to capture the cart state AFTER VIP discounts 
 * are applied. The surcharge calculation on woocommerce_cart_calculate_fees (priority 20)
 * will then use this baseline for comparison.
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
    
    // HOOK TIMING FIX: We're now on woocommerce_before_calculate_totals, so VIP discounts
    // are applied but fees may not be visible yet. We'll calculate VIP discount from cart items.
    $vip_discount_total = 0;
    
    // Calculate VIP discount by checking if cart items have discounted prices
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        
        // Check if this is a VIP-eligible cart (product 80 with quantity >= 5)
        // Note: We can't reliably check user role here, so we'll detect based on cart composition
        if ($product->get_id() == 80 && $quantity >= 5) {
            // VIP discount is $10 per item for 5+ quantity (will be applied by VIP discount function)
            $vip_discount_total += $quantity * 10.00;
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("HOOK TIMING FIX: Detected potential VIP discount for product 80 (qty: $quantity): $" . number_format($vip_discount_total, 2));
            }
            break; // Only one VIP discount applies
        }
    }
    
    // Generate current cart hash (INCLUDING VIP discount calculation)
    $current_cart_hash = md5(serialize([
        WC()->cart->get_subtotal(),
        WC()->cart->get_shipping_total(),
        $vip_discount_total, // Include calculated VIP discount in baseline
        WC()->session->get('chosen_payment_method')
    ]));
    
    // Get stored baseline
    $stored_baseline = WC()->session->get('apw_baseline_cart_hash');
    
    // Enhanced session persistence with edge case handling
    if ($stored_baseline !== $current_cart_hash) {
        WC()->session->set('apw_baseline_cart_hash', $current_cart_hash);
        
        // Set flag to force surcharge recalculation
        WC()->session->set('apw_force_surcharge_recalc', true);
        
        // Store VIP discount amount for fallback scenarios
        WC()->session->set('apw_vip_discount_amount', $vip_discount_total);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EDGE CASE PROTECTION: BASELINE HASH UPDATED: " . substr($current_cart_hash, 0, 8) . " (subtotal: $" . number_format(WC()->cart->get_subtotal(), 2) . ", shipping: $" . number_format(WC()->cart->get_shipping_total(), 2) . ", VIP discount: $" . number_format($vip_discount_total, 2) . ")");
            apw_woo_log("Previous baseline: " . substr($stored_baseline ?: 'none', 0, 8));
            apw_woo_log("FORCING surcharge recalculation due to baseline change");
        }
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EDGE CASE PROTECTION: BASELINE HASH UNCHANGED: " . substr($current_cart_hash, 0, 8));
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
    
    // Enhanced VIP discount detection with multiple fallbacks for edge cases
    $current_vip_discount_total = 0;
    $existing_surcharge = false;
    $fees = WC()->cart->get_fees();
    
    // Method 1: Check fees (primary detection)
    foreach ($fees as $fee) {
        if ((strpos($fee->name, 'VIP Discount') !== false || strpos($fee->name, 'Bulk Discount') !== false) && $fee->amount < 0) {
            $current_vip_discount_total += abs($fee->amount);
        }
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            $existing_surcharge = true;
        }
    }
    
    // Method 2: Fallback to stored session data (edge case handling)
    if ($current_vip_discount_total == 0) {
        $session_vip_discount = WC()->session->get('apw_vip_discount_amount');
        if ($session_vip_discount && $session_vip_discount > 0) {
            $current_vip_discount_total = $session_vip_discount;
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("EDGE CASE FALLBACK: Using VIP discount from session data: $" . number_format($current_vip_discount_total, 2));
            }
        }
    }
    
    // Method 3: Last resort cart analysis (if both fees and session data fail)
    if ($current_vip_discount_total == 0) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            // Check if this is a VIP-eligible cart (product 80 with quantity >= 5)
            if ($product->get_id() == 80 && $quantity >= 5) {
                $current_vip_discount_total = $quantity * 10.00;
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log("EDGE CASE FALLBACK: Using VIP discount from cart item analysis: $" . number_format($current_vip_discount_total, 2));
                }
                break;
            }
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
    
    // Robust edge case recalculation logic
    $should_recalculate = false;
    $recalc_reason = '';
    
    if ($force_recalc) {
        $should_recalculate = true;
        $recalc_reason = 'Force recalc flag is set';
    } elseif ($baseline_hash !== $current_baseline_hash) {
        $should_recalculate = true;
        $recalc_reason = 'Baseline hash changed';
    } elseif (!$existing_surcharge) {
        $should_recalculate = true;
        $recalc_reason = 'No existing surcharge found';
    } else {
        $recalc_reason = 'Skipping recalculation (cart unchanged, surcharge exists)';
    }
    
    if (!$should_recalculate) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("EDGE CASE ANALYSIS: " . $recalc_reason);
        }
        return; // Cart unchanged and surcharge already applied
    }
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("EDGE CASE ANALYSIS: " . $recalc_reason . " - proceeding with calculation");
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
                apw_woo_log("HOOK TIMING FIX: Removed existing surcharge of $" . number_format($fee->amount, 2));
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
        
        // FRONTEND CACHE FIX v1.23.26: Force cart fragments refresh to update frontend display
        // This ensures the frontend shows the updated surcharge amount instead of cached values
        if (function_exists('wc_add_to_cart_message')) {
            // Trigger WooCommerce to refresh cart fragments on next AJAX call
            WC()->session->set('wc_fragments_should_refresh', true);
            
            // Set flag for JavaScript to detect and force frontend updates
            WC()->session->set('apw_surcharge_updated', time());
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("FRONTEND CACHE FIX: Set fragment refresh flags for frontend update");
            }
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("HOOK TIMING FIX: Starting surcharge calculation");
            apw_woo_log("- Baseline hash changed: " . ($baseline_hash !== $current_baseline_hash ? 'YES' : 'NO'));
            apw_woo_log("- Old baseline: " . substr($baseline_hash ?: 'none', 0, 8));
            apw_woo_log("- New baseline: " . substr($current_baseline_hash, 0, 8));
            apw_woo_log("- VIP discounts found: $" . number_format($total_discount, 2));
            apw_woo_log("- Calculation base: $" . number_format($surcharge_base, 2));
            apw_woo_log("- Base: $" . number_format($surcharge_base, 2) . " (subtotal: $" . number_format($subtotal, 2) . " + shipping: $" . number_format($shipping_total, 2) . " - VIP discount: $" . number_format($total_discount, 2) . ")");
            apw_woo_log("- Final surcharge: $" . number_format($surcharge, 2));
            apw_woo_log("HOOK TIMING FIX: Successfully added surcharge fee to cart");
        }
    }
}

/**
 * FRONTEND CACHE FIX v1.23.26: AJAX endpoint to clear surcharge update flag
 * This prevents the flag from persisting across page loads after frontend update
 */
function apw_woo_clear_surcharge_flag() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'apw_woo_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Clear the surcharge update flag
    if (WC()->session) {
        WC()->session->set('apw_surcharge_updated', null);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("FRONTEND CACHE FIX: Cleared surcharge update flag via AJAX");
        }
    }
    
    wp_send_json_success('Flag cleared');
}
add_action('wp_ajax_apw_clear_surcharge_flag', 'apw_woo_clear_surcharge_flag');
add_action('wp_ajax_nopriv_apw_clear_surcharge_flag', 'apw_woo_clear_surcharge_flag');

// REMOVED: File-level hook registration that was causing duplicate surcharge calculations
// Hook registration now handled in apw_woo_init_intuit_integration() function with static protection
// This prevents multiple hook registrations when the file is loaded multiple times
