# Payment Service Refactor Specification

## Overview
This specification addresses the critical payment processing bugs in the APW WooCommerce Plugin, specifically the credit card surcharge calculation issues that occur when VIP discounts are applied.

## Critical Problem Analysis

### Current Issue
**File**: `includes/apw-woo-intuit-payment-functions.php` (301 lines)
**Problem**: Credit card surcharge shows incorrect amounts (e.g., $17.14 instead of $15.64) when VIP/quantity discounts are applied.

**Root Cause**: The surcharge calculation doesn't properly account for VIP discounts that are applied as negative fees.

### Expected Behavior
Surcharge should be calculated as: `(subtotal + shipping - discounts) × 3%`

### Actual Behavior  
Surcharge is calculated on gross amount before VIP discounts are properly factored in.

## Current Code Issues

### 1. Fee Existence Check Prevents Recalculation
**Lines 237-258** (now removed in v1.23.16):
```php
// Check if surcharge already exists to prevent duplicates
$existing_fees = WC()->cart->get_fees();
foreach ($existing_fees as $fee) {
    if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
        return; // Exit early - prevents recalculation when cart changes
    }
}
```
**Problem**: Once surcharge exists, it never recalculates when cart state changes.

### 2. Missing Fee Collection Logic
**Lines 276-280**:
```php
// Get VIP discounts (negative fee amounts)
$total_discount = 0;
foreach ($existing_fees as $fee) {
    if ($fee->amount < 0) {
        $total_discount += abs($fee->amount);
    }
}
```
**Problem**: `$existing_fees` variable is never defined in the current function.

### 3. Flag Usage Issue
**Lines 130-137**: The `apw_woo_force_surcharge_recalc` flag is set but never checked in the actual calculation function.

## Solution Architecture

### WordPress-Native Approach
Instead of complex service classes with dependency injection, use WordPress patterns:

### 1. Clean Calculation Function
```php
function apw_woo_calculate_credit_card_surcharge() {
    // Only run on checkout with correct payment method
    if (!is_checkout() || WC()->session->get('chosen_payment_method') !== 'intuit_payments_credit_card') {
        return 0;
    }
    
    // Get cart totals
    $cart = WC()->cart;
    $subtotal = $cart->get_subtotal();
    $shipping_total = $cart->get_shipping_total();
    
    // Calculate total discounts from negative fees (VIP discounts)
    $total_discounts = 0;
    foreach ($cart->get_fees() as $fee) {
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
```

### 2. Simple Fee Management
```php
function apw_woo_apply_credit_card_surcharge() {
    // Remove existing surcharge to prevent duplicates
    apw_woo_remove_credit_card_surcharge();
    
    // Calculate new surcharge
    $surcharge = apw_woo_calculate_credit_card_surcharge();
    
    if ($surcharge > 0) {
        WC()->cart->add_fee(__('Credit Card Surcharge (3%)', 'apw-woo-plugin'), $surcharge, true);
    }
}

function apw_woo_remove_credit_card_surcharge() {
    $cart = WC()->cart;
    $fees = $cart->get_fees();
    
    foreach ($fees as $key => $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            unset($cart->fees[$key]);
        }
    }
}
```

### 3. Proper Hook Integration
```php
function apw_woo_init_payment_processing() {
    // Only initialize once
    static $initialized = false;
    if ($initialized) return;
    
    // Hook into cart fee calculations
    add_action('woocommerce_cart_calculate_fees', 'apw_woo_apply_credit_card_surcharge', 20);
    
    // Hook into payment method changes
    add_action('woocommerce_checkout_update_order_meta', 'apw_woo_save_payment_data', 10, 2);
    
    $initialized = true;
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Payment processing hooks initialized');
    }
}
```

## Implementation Steps

### Phase 1: Critical Fix (Immediate)
1. **Backup Current Code**: Save current version as v1.23.19-backup
2. **Fix Fee Collection**: Add proper `$existing_fees` variable definition
3. **Remove Existence Check**: Allow recalculation when cart state changes
4. **Add Change Detection**: Use cart state hash to trigger recalculation
5. **Test Thoroughly**: Verify surcharge calculation with VIP discounts

### Phase 2: Clean Implementation
1. **Refactor Functions**: Separate calculation from application logic
2. **Improve Debugging**: Add comprehensive logging for troubleshooting
3. **Hook Priority**: Ensure surcharge runs after VIP discounts (priority 20)
4. **Error Handling**: Add proper error handling and fallbacks

### Phase 3: Optimization
1. **Performance**: Cache calculations within request lifecycle
2. **Code Reduction**: Consolidate related functions
3. **Documentation**: Add comprehensive inline documentation
4. **Testing**: Create unit tests for edge cases

## Testing Scenarios

### Test Case 1: Basic Surcharge
- **Product**: Any product, quantity 1
- **Expected**: 3% surcharge on (subtotal + shipping)
- **Verification**: Manual calculation matches applied fee

### Test Case 2: VIP Discount Applied
- **Product**: Product #80, quantity 5 
- **Customer**: VIP customer eligible for discount
- **Expected**: Surcharge = (subtotal + shipping - VIP discount) × 3%
- **Current Bug**: Shows $17.14, should show $15.64

### Test Case 3: Payment Method Switch
- **Action**: Switch from Credit Card to other payment method
- **Expected**: Surcharge removed completely
- **Verification**: No surcharge fee in cart

### Test Case 4: Cart Updates
- **Action**: Change quantities, add/remove items
- **Expected**: Surcharge recalculates correctly
- **Verification**: Surcharge matches current cart totals

## Code Reduction Impact

### Current Payment File: 301 lines
### Target: ~150 lines (50% reduction)

**Reductions**:
- Remove complex state tracking: -30 lines
- Consolidate fee management: -20 lines  
- Simplify hook registration: -15 lines
- Remove duplicate validation: -25 lines
- Clean up debug logging: -10 lines

**Additions**:
- Proper error handling: +15 lines
- Comprehensive testing helpers: +20 lines
- Documentation improvements: +10 lines

**Net Reduction**: ~151 lines (-50%)

## Rollback Plan

### If Issues Arise
1. **Immediate**: Revert to v1.23.19 backup
2. **Investigate**: Use debug mode to identify specific issues
3. **Gradual Fix**: Apply fixes incrementally with testing
4. **Fallback**: Disable surcharge feature if critical issues persist

### Monitoring
- Enable debug mode during initial deployment
- Monitor customer reports for payment issues
- Track admin order processing for anomalies
- Verify with test transactions before production

## Success Metrics

### Bug Resolution
- [ ] Credit card surcharge calculates correctly with VIP discounts
- [ ] Surcharge updates properly when cart contents change
- [ ] No infinite loops in cart calculation process
- [ ] Payment method switching works correctly

### Code Quality  
- [ ] 50% reduction in payment processing file size
- [ ] Clear separation between calculation and application logic
- [ ] Comprehensive debug logging for troubleshooting
- [ ] WordPress coding standards compliance

### Performance
- [ ] No performance degradation in checkout process
- [ ] Efficient fee calculation (no unnecessary recalculations)
- [ ] Proper caching of calculations within request

This specification provides a WordPress-native solution that fixes the critical payment processing bugs while significantly reducing code complexity.