# Tax Address Centralization Project

## Project Overview

### **Problem Statement**
The APW WooCommerce Plugin fails when canceling orders in WooCommerce admin with error:
```
Call to undefined method Automattic\WooCommerce\Admin\Overrides\Order::get_tax_address()
```

### **Root Cause Analysis**
- **Context**: WooCommerce admin uses `Automattic\WooCommerce\Admin\Overrides\Order` instead of standard `WC_Order`
- **Issue**: Admin Overrides Order class lacks `get_tax_address()` method
- **Affected Lines**: 1527 & 1612 in `apw-woo-dynamic-pricing-functions.php`
- **Duplicate Code**: Both locations contain identical tax calculation logic

### **Solution Strategy**
3-phase implementation with testing between each phase:
1. **Phase 1**: Fix immediate error with compatibility helper
2. **Phase 2**: Eliminate duplicate tax calculation code (DRY principle)
3. **Phase 3**: File size reduction toward <600 line requirement

---

## Phase 1: Fix Immediate Error (v1.24.14)

### **Status**: 🔄 Ready to Start
### **Branch**: `fix/tax-address-phase1-v1.24.14`
### **Goal**: Fix admin order cancellation error

### **Implementation Steps**

#### **1. Add Compatibility Helper Function**
**Location**: Top of `apw-woo-dynamic-pricing-functions.php`
```php
/**
 * Get tax address compatible with both WC_Order and Admin\Overrides\Order
 * 
 * @param WC_Order $order The order object
 * @return array Tax address array
 * @since 1.24.14
 */
function apw_woo_get_compatible_tax_address($order) {
    // Check if get_tax_address() method exists (standard WC_Order)
    if (method_exists($order, 'get_tax_address')) {
        return $order->get_tax_address();
    }
    
    // Fallback for Admin Overrides Order - manual construction
    return array(
        'country'  => $order->get_billing_country() ?: $order->get_shipping_country(),
        'state'    => $order->get_billing_state() ?: $order->get_shipping_state(),
        'postcode' => $order->get_billing_postcode() ?: $order->get_shipping_postcode(),
        'city'     => $order->get_billing_city() ?: $order->get_shipping_city()
    );
}
```

#### **2. Replace Broken Calls**
**Line 1527**: Replace `$order->get_tax_address()` with `apw_woo_get_compatible_tax_address($order)`
**Line 1612**: Replace `$order->get_tax_address()` with `apw_woo_get_compatible_tax_address($order)`

#### **3. Update Version**
- Plugin header: `Version: 1.24.14`
- Version constant: `define('APW_WOO_VERSION', '1.24.14');`

### **Branch Strategy**
```bash
git checkout main
git pull origin main
git checkout -b fix/tax-address-phase1-v1.24.14
```

### **Commit Messages**
1. `TAX ADDRESS FIX v1.24.14: Add compatibility helper for Admin Overrides Order`
2. `VERSION UPDATE v1.24.14: Update plugin version for tax address fix`

### **Testing Checklist**
- [ ] Admin order cancellation works without error
- [ ] Order editing scenarios work normally
- [ ] Tax calculations produce same results
- [ ] No performance degradation
- [ ] No regressions in existing functionality

### **Success Criteria**
- ✅ Admin order cancellation works without `get_tax_address()` error
- ✅ Tax calculations remain identical to before
- ✅ Clean code review
- ✅ Ready for Phase 2 implementation

### **PR Template**
```
## PHASE 1: Fix admin order cancellation tax address error (#XX)

### Problem
Admin order cancellation fails with `get_tax_address()` method not found error.

### Solution
- Added compatibility helper function for different WooCommerce order types
- Replaced broken calls with helper function usage
- Updated version to 1.24.14

### Testing
- [x] Admin order cancellation works
- [x] Tax calculations remain accurate
- [x] No regressions found

### Next Phase
Phase 2 will eliminate duplicate tax calculation code.
```

---

## Phase 2: Eliminate Duplicate Code (v1.24.15)

### **Status**: 🔄 Awaiting Phase 1 Completion
### **Branch**: `fix/tax-address-phase2-v1.24.15`
### **Goal**: Apply DRY principle to tax calculation code

### **Implementation Steps**

#### **1. Create Centralized Tax Calculation Function**
**Location**: After the compatibility helper function
```php
/**
 * Calculate tax for discount fees with centralized logic
 * 
 * @param WC_Order_Item_Fee $fee The fee object
 * @param WC_Order $order The order object
 * @param string $rule_name The discount rule name for logging
 * @since 1.24.15
 */
function apw_woo_calculate_discount_fee_tax($fee, $order, $rule_name) {
    $fee_total = $fee->get_total();
    if ($fee_total >= 0) {
        return; // Only process discount fees (negative amounts)
    }
    
    $tax_address = apw_woo_get_compatible_tax_address($order);
    $tax_rates = WC_Tax::get_rates($fee->get_tax_class(), $tax_address);
    $fee_taxes = WC_Tax::calc_tax(abs($fee_total), $tax_rates, false);
    
    if (!empty($fee_taxes)) {
        $total_tax = -array_sum($fee_taxes);
        $fee->set_total_tax($total_tax);
        $fee->set_taxes(array('total' => array_map(function($tax) { return -$tax; }, $fee_taxes)));
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("TAX CALCULATION: Applied tax for '{$rule_name}': \${$total_tax}");
        }
    }
}
```

#### **2. Replace Duplicate Code Blocks**
**Lines 1527-1538**: Replace with `apw_woo_calculate_discount_fee_tax($fee, $order, $rule['name']);`
**Lines 1612-1626**: Replace with `apw_woo_calculate_discount_fee_tax($fee, $order, $fee_name);`

#### **3. Update Version**
- Plugin header: `Version: 1.24.15`
- Version constant: `define('APW_WOO_VERSION', '1.24.15');`

### **Testing Checklist**
- [ ] Tax calculations remain identical
- [ ] Debug logging works correctly
- [ ] No performance regression
- [ ] Code is more maintainable

### **Success Criteria**
- ✅ Duplicate code eliminated
- ✅ Single source of truth for tax calculations
- ✅ Consistent debug logging
- ✅ No functional changes

---

## Phase 3: File Size Reduction (v1.24.16)

### **Status**: 🔄 Awaiting Phase 2 Completion
### **Branch**: `fix/tax-address-phase3-v1.24.16`
### **Goal**: Move toward <600 line requirement

### **Current Status**
- **Current file size**: 1640 lines
- **Target**: <600 lines per global requirements
- **Reduction needed**: ~1000+ lines

### **Implementation Steps**

#### **1. Extract Large Functions**
Identify functions that can be moved to separate files:
- Large discount rule processing functions
- Admin-specific functionality
- Complex tax calculation logic

#### **2. Create Service Classes**
Move functionality to appropriate service classes:
- `class-apw-woo-tax-service.php` for tax-related functions
- `class-apw-woo-admin-discount-service.php` for admin discount logic

#### **3. Consolidate Related Functions**
Group related functions and extract to logical modules

#### **4. Update Version**
- Plugin header: `Version: 1.24.16`
- Version constant: `define('APW_WOO_VERSION', '1.24.16');`

### **Testing Checklist**
- [ ] All functionality remains intact
- [ ] No performance degradation
- [ ] File size under 600 lines
- [ ] Clean architecture

### **Success Criteria**
- ✅ File size reduced to <600 lines
- ✅ Better code organization
- ✅ Maintainable architecture
- ✅ No functional changes

---

## Progress Tracking

### **Phase 1 Status**
- **Started**: [Date]
- **Branch Created**: [ ]
- **Helper Function Added**: [ ]
- **Broken Calls Fixed**: [ ]
- **Version Updated**: [ ]
- **Testing Complete**: [ ]
- **PR Created**: [ ]
- **PR Merged**: [ ]
- **Completed**: [Date]

### **Phase 2 Status**
- **Started**: [Date]
- **Branch Created**: [ ]
- **Centralized Function Added**: [ ]
- **Duplicate Code Removed**: [ ]
- **Version Updated**: [ ]
- **Testing Complete**: [ ]
- **PR Created**: [ ]
- **PR Merged**: [ ]
- **Completed**: [Date]

### **Phase 3 Status**
- **Started**: [Date]
- **Branch Created**: [ ]
- **Functions Extracted**: [ ]
- **Service Classes Created**: [ ]
- **File Size Reduced**: [ ]
- **Version Updated**: [ ]
- **Testing Complete**: [ ]
- **PR Created**: [ ]
- **PR Merged**: [ ]
- **Completed**: [Date]

---

## Testing Notes

### **Phase 1 Testing Results**
- **Admin order cancellation**: [Pass/Fail/Notes]
- **Order editing**: [Pass/Fail/Notes]
- **Tax calculation accuracy**: [Pass/Fail/Notes]
- **Performance**: [Pass/Fail/Notes]
- **Regressions**: [None/List]

### **Phase 2 Testing Results**
- **Tax calculations**: [Pass/Fail/Notes]
- **Debug logging**: [Pass/Fail/Notes]
- **Performance**: [Pass/Fail/Notes]
- **Code maintainability**: [Pass/Fail/Notes]

### **Phase 3 Testing Results**
- **Functionality**: [Pass/Fail/Notes]
- **Performance**: [Pass/Fail/Notes]
- **File size**: [Pass/Fail/Notes]
- **Architecture**: [Pass/Fail/Notes]

---

## Rollback Plans

### **Phase 1 Rollback**
If Phase 1 fails, revert to v1.24.13 and investigate alternative solutions.

### **Phase 2 Rollback**
If Phase 2 fails, revert to Phase 1 completion state (v1.24.14).

### **Phase 3 Rollback**
If Phase 3 fails, revert to Phase 2 completion state (v1.24.15).

---

## Future Considerations

### **Potential Integrations**
- Third-party tax services (if needed in future)
- Enhanced tax calculation features
- Performance optimizations

### **Maintenance Notes**
- Monitor WooCommerce updates for order class changes
- Keep compatibility helper updated
- Review tax calculation logic for accuracy

---

*Last Updated: [Date]*
*Project Status: Phase 1 Ready*