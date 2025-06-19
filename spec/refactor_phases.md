# Refactor Implementation Phases

## Overview
This specification outlines the three-phase approach to refactoring the APW WooCommerce Plugin, prioritizing critical bug fixes, followed by code consolidation, and finally optimization and cleanup.

## Phase Structure Strategy

### Risk-First Approach
1. **Phase 1**: Fix critical bugs that affect revenue (payment processing)
2. **Phase 2**: Consolidate scattered code for maintainability
3. **Phase 3**: Optimize and clean up for performance

### Minimal Disruption Strategy
- Each phase delivers working, tested code
- No phase breaks existing functionality
- Rollback capability at each phase boundary
- Progressive enhancement rather than complete rewrites

## Phase 1: Critical Bug Fixes (Week 1)

### Primary Goal: Fix Payment Processing Issues

#### Critical Issue Resolution
**Problem**: Credit card surcharge shows $17.14 instead of $15.64 when VIP discounts applied
**Root Cause**: Surcharge calculation doesn't account for VIP discounts properly
**Files Affected**: `includes/apw-woo-intuit-payment-functions.php`

#### Implementation Steps

##### Day 1-2: Analysis and Setup
```bash
# Create feature branch for payment fixes
git checkout -b fix/payment-surcharge-calculation

# Backup current working version
cp includes/apw-woo-intuit-payment-functions.php includes/apw-woo-intuit-payment-functions.php.backup

# Enable debug mode for testing
# Set APW_WOO_DEBUG_MODE = true in main plugin file
```

##### Day 3-4: Core Fix Implementation
```php
// Fix #1: Remove existence check that prevents recalculation
function apw_woo_add_intuit_surcharge_fee() {
    // Remove existing surcharge to allow recalculation
    apw_woo_remove_existing_surcharge();
    
    // Calculate surcharge properly with VIP discounts
    $cart = WC()->cart;
    $subtotal = $cart->get_subtotal();
    $shipping = $cart->get_shipping_total();
    
    // Get VIP discounts (negative fees)
    $vip_discount = 0;
    foreach ($cart->get_fees() as $fee) {
        if ($fee->amount < 0 && strpos($fee->name, 'VIP') !== false) {
            $vip_discount += abs($fee->amount);
        }
    }
    
    // Correct calculation: (subtotal + shipping - VIP discount) * 3%
    $surcharge_base = $subtotal + $shipping - $vip_discount;
    $surcharge = max(0, $surcharge_base * 0.03);
    
    if ($surcharge > 0) {
        WC()->cart->add_fee(__('Credit Card Surcharge (3%)', 'apw-woo-plugin'), $surcharge, true);
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("FIXED: Surcharge calculation - Base: $" . number_format($surcharge_base, 2) . " = $" . number_format($subtotal, 2) . " + $" . number_format($shipping, 2) . " - $" . number_format($vip_discount, 2));
            apw_woo_log("FIXED: Surcharge (3%): $" . number_format($surcharge, 2));
        }
    }
}

function apw_woo_remove_existing_surcharge() {
    $fees = WC()->cart->get_fees();
    foreach ($fees as $key => $fee) {
        if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
            unset(WC()->cart->fees[$key]);
        }
    }
}
```

##### Day 5: Testing and Validation
```php
// Test scenarios for surcharge calculation
function apw_woo_test_payment_scenarios() {
    if (!APW_WOO_DEBUG_MODE) return;
    
    // Test Case 1: Product #80, quantity 5, VIP customer
    // Expected: Surcharge should be $15.64, not $17.14
    
    // Test Case 2: Regular customer, no VIP discount
    // Expected: Surcharge = (subtotal + shipping) * 3%
    
    // Test Case 3: Payment method change
    // Expected: Surcharge removed when switching away from credit card
}
```

#### Success Criteria Phase 1
- [ ] Credit card surcharge calculates correctly with VIP discounts
- [ ] Test Case: Product #80, qty 5 shows $15.64 surcharge
- [ ] No infinite loops in cart calculation
- [ ] Payment method switching works properly
- [ ] All existing payment functionality preserved

#### Deliverables Phase 1
- Fixed payment calculation logic
- Comprehensive debug logging for payment issues
- Test cases documenting correct surcharge behavior
- Documentation of changes made

---

## Phase 2: Code Consolidation (Week 2-3)

### Primary Goal: Consolidate Scattered Functionality

#### Service Class Creation

##### Week 2: Customer and VIP Service
```php
// New file: includes/class-apw-woo-customer-service.php
class APW_Woo_Customer_Service {
    
    private static $instance = null;
    private $vip_cache = [];
    
    // Consolidate from:
    // - class-apw-woo-registration-fields.php (registration logic)
    // - class-apw-woo-referral-export.php (export logic)
    // - Scattered VIP discount logic
    
    public function __construct() {
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_vip_discount'], 10);
        add_action('user_register', [$this, 'process_registration_fields']);
    }
    
    public function apply_vip_discount() {
        // Consolidated VIP discount logic
        // Runs at priority 10, before payment surcharge (priority 20)
    }
    
    public function export_referral_customers($referrer_name = '') {
        // Consolidated export logic without HTML mixing
    }
}
```

##### Week 3: Product and Cart Services
```php
// New file: includes/class-apw-woo-product-service.php
class APW_Woo_Product_Service {
    
    // Consolidate from:
    // - apw-woo-dynamic-pricing-functions.php
    // - apw-woo-product-addons-functions.php
    // - apw-woo-cross-sells-functions.php
    
    public function get_dynamic_pricing($product_id, $quantity) {
        // Consolidated pricing logic with caching
    }
}

// New file: includes/class-apw-woo-cart-service.php  
class APW_Woo_Cart_Service {
    
    // Consolidate from:
    // - apw-woo-cart-indicator-functions.php
    // - apw-woo-shipping-functions.php
    
    public function update_cart_indicators() {
        // Consolidated cart quantity indicator logic
    }
}
```

#### Function File Reduction
```php
// BEFORE: 10 function files (8,500 lines)
// apw-woo-intuit-payment-functions.php
// apw-woo-dynamic-pricing-functions.php
// apw-woo-cart-indicator-functions.php
// apw-woo-account-functions.php
// apw-woo-recurring-billing-functions.php
// apw-woo-checkout-fields-functions.php
// apw-woo-cross-sells-functions.php
// apw-woo-product-addons-functions.php
// apw-woo-shipping-functions.php
// apw-woo-tabs-functions.php

// AFTER: 4 service classes + 2 function files (4,675 lines)
// class-apw-woo-payment-service.php (200 lines)
// class-apw-woo-customer-service.php (300 lines)
// class-apw-woo-product-service.php (250 lines)
// class-apw-woo-cart-service.php (200 lines)
// apw-woo-template-functions.php (150 lines)
// apw-woo-admin-functions.php (100 lines)
```

#### Migration Strategy
1. **Create new service class**
2. **Move functions to appropriate methods**
3. **Update hook registrations**
4. **Test functionality**
5. **Remove old function file**
6. **Update autoloader**

#### Success Criteria Phase 2
- [ ] 4 service classes created and functional
- [ ] Function files reduced from 10 to 2
- [ ] All existing hooks continue working
- [ ] Code reduction of 45% in function files achieved
- [ ] Service classes follow WordPress singleton pattern

#### Deliverables Phase 2
- 4 new service classes with consolidated functionality
- Updated plugin initialization to load services
- Migration documentation
- Reduced codebase by ~3,825 lines

---

## Phase 3: Optimization and Cleanup (Week 4-5)

### Primary Goal: Performance and Code Quality

#### Week 4: Template and Asset Optimization

##### Template Cleanup
```php
// BEFORE: Verbose debug output (50 lines per template)
<?php if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE): ?>
    <div class="apw-woo-debug-info" style="margin-top: 20px; padding: 15px;">
        <h4>Debug Information</h4>
        <ul>
            <li><strong>User Status:</strong> <?php echo is_user_logged_in() ? 'Logged In' : 'Not Logged In'; ?></li>
            <!-- ... 15 more debug lines ... -->
        </ul>
    </div>
<?php endif; ?>

// AFTER: Minimal debug helper (8 lines)
<?php if (APW_WOO_DEBUG_MODE && current_user_can('manage_options')): ?>
    <div class="apw-debug">Debug: User <?php echo get_current_user_id(); ?> | Cart <?php echo WC()->cart->get_cart_contents_count(); ?></div>
<?php endif; ?>
```

##### Asset Optimization
```php
// Conditional asset loading
function apw_woo_enqueue_scripts() {
    // Only load checkout scripts on checkout
    if (is_checkout()) {
        wp_enqueue_script('apw-woo-checkout', APW_WOO_PLUGIN_URL . 'assets/js/checkout.js');
    }
    
    // Only load product scripts on product pages
    if (is_product()) {
        wp_enqueue_script('apw-woo-product', APW_WOO_PLUGIN_URL . 'assets/js/product.js');
    }
}
```

#### Week 5: Final Cleanup and Documentation

##### Main Plugin File Simplification
```php
// BEFORE: Complex autoload (100+ lines)
function apw_woo_autoload($class_name) {
    // Complex file mapping logic
    // Multiple fallback attempts
    // Extensive error handling
}

// AFTER: Simple autoload (20 lines)
function apw_woo_autoload($class) {
    if (strpos($class, 'APW_Woo_') !== 0) return;
    
    $file = APW_WOO_PLUGIN_DIR . 'includes/class-' . str_replace('_', '-', strtolower($class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}
```

##### Performance Improvements
```php
// Add caching to expensive operations
class APW_Woo_Performance_Cache {
    
    private static $cache = [];
    
    public static function get($key, $callback) {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $callback();
        }
        return self::$cache[$key];
    }
}

// Use in services
public function get_customer_vip_status($customer_id) {
    return APW_Woo_Performance_Cache::get(
        'vip_status_' . $customer_id,
        function() use ($customer_id) {
            return $this->calculate_vip_status($customer_id);
        }
    );
}
```

#### Success Criteria Phase 3
- [ ] Template files reduced by 20% (4,200 → 3,360 lines)
- [ ] Main plugin file reduced by 40% (1,086 → 650 lines)
- [ ] Asset loading optimized for context
- [ ] Performance caching implemented
- [ ] Overall 30-40% code reduction achieved

#### Deliverables Phase 3
- Optimized templates with minimal debug output
- Simplified main plugin file structure
- Performance caching system
- Comprehensive documentation
- Final code reduction verification

---

## Cross-Phase Considerations

### Version Control Strategy
```bash
# Phase 1 branch
git checkout -b phase1/payment-fixes
# Complete Phase 1, merge to main

# Phase 2 branch
git checkout -b phase2/code-consolidation
# Complete Phase 2, merge to main

# Phase 3 branch  
git checkout -b phase3/optimization
# Complete Phase 3, merge to main
```

### Testing Strategy Per Phase
```php
// Phase 1: Payment testing
function test_payment_calculations() {
    // Test surcharge with VIP discounts
    // Test payment method switching
    // Test cart updates
}

// Phase 2: Service integration testing
function test_service_consolidation() {
    // Test service class instantiation
    // Test hook registration
    // Test function migration
}

// Phase 3: Performance testing
function test_optimization_results() {
    // Test page load times
    // Test memory usage
    // Test cache effectiveness
}
```

### Rollback Plans
- **Phase 1**: Revert payment function file to backup
- **Phase 2**: Keep old function files until services verified
- **Phase 3**: Feature flags for optimization features

### Communication Strategy
- **Daily Updates**: Progress reports for each phase
- **Weekly Reviews**: Stakeholder demos of working functionality
- **Issue Tracking**: Document any problems encountered
- **Success Metrics**: Quantified improvements at each phase

## Final Success Metrics

### Code Reduction (Quantitative)
- [ ] **Total**: 20,539 → 12,323-14,377 lines (30-40% reduction)
- [ ] **Main File**: 1,086 → 650 lines (40% reduction)
- [ ] **Function Files**: 8,500 → 4,675 lines (45% reduction)
- [ ] **Class Files**: 6,800 → 5,100 lines (25% reduction)
- [ ] **Templates**: 4,200 → 3,360 lines (20% reduction)

### Bug Resolution (Qualitative)
- [ ] Credit card surcharge calculation fixed
- [ ] VIP discount timing issues resolved
- [ ] Payment processing reliability improved
- [ ] All existing functionality preserved

### Architecture Improvement (Structural)
- [ ] Service-based organization implemented
- [ ] WordPress-native patterns throughout
- [ ] Improved maintainability and extensibility
- [ ] Clear separation of concerns achieved

This phased approach ensures systematic improvement while minimizing risk and maintaining functionality throughout the refactor process.