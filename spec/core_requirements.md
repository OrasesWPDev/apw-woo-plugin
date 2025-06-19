# Core Requirements Specification

## Overview
This document defines the 5 core business requirements for the APW WooCommerce Plugin refactor, focusing on maintaining existing functionality while fixing critical bugs and improving maintainability.

## Current State Analysis
- **Total Lines**: ~20,539 lines across 21 PHP files
- **Critical Issues**: Credit card surcharge miscalculation, VIP discount timing problems
- **Architecture Problems**: Scattered functionality across 10+ function files
- **Maintenance Challenges**: Difficult to add new features due to poor code organization

## Core Business Requirements

### 1. Product Display & Templates
**Status**: Working but needs optimization
**Files**: `templates/woocommerce/single-product.php`, template loading classes
**Requirements**:
- Maintain existing shortcode functionality (`[block id="third-level-woo-page-header"]`)
- Preserve custom product URL structure (`/products/%category%/%product%`)
- Keep FAQ system integration with ACF
- Maintain Flatsome theme compatibility
- Preserve debug mode template visualization

**Critical**: Must not break any existing shortcode functionality

### 2. Payment Processing
**Status**: BROKEN - Critical fixes required
**Files**: `includes/apw-woo-intuit-payment-functions.php`
**Issues**:
- Credit card surcharge shows $17.14 instead of $15.64 when VIP discounts applied
- VIP discount timing causes incorrect fee calculations
- Surcharge should be: (subtotal + shipping - discounts) × 3%

**Requirements**:
- Fix surcharge calculation to run after VIP discounts are applied
- Prevent infinite loop issues with `calculate_totals()` calls
- Maintain Intuit/QuickBooks payment gateway integration
- Preserve payment token handling and security

**Priority**: CRITICAL - Fix first

### 3. Customer Management
**Status**: Working but scattered
**Files**: `class-apw-woo-registration-fields.php`, `class-apw-woo-referral-export.php`
**Requirements**:
- Registration field validation (first_name, last_name, company_name, phone_number)
- Referral tracking and export functionality
- VIP customer identification and discount eligibility
- Customer data export to CSV format
- Integration with WordPress user system

### 4. Pricing & Discounts
**Status**: Working but timing issues with payments
**Files**: `apw-woo-dynamic-pricing-functions.php`, VIP discount logic
**Requirements**:
- Dynamic pricing rules based on quantity
- VIP discount tiers: 5% ($100+), 8% ($300+), 10% ($500+)
- Integration with WooCommerce Dynamic Pricing plugin
- Proper timing with payment surcharge calculations
- Cart-level discount application

**Integration Note**: Must work correctly with payment surcharge calculations

### 5. System Integrations
**Status**: Working but needs consolidation
**Files**: Various integration files
**Requirements**:
- WooCommerce core integration (hooks, filters, templates)
- Advanced Custom Fields (ACF) for FAQ system
- Flatsome theme compatibility
- GitHub auto-updater system
- Asset management (CSS/JS auto-discovery)

## Non-Functional Requirements

### Performance
- Target 30-40% code reduction from current ~20,539 lines
- Maintain WordPress object cache usage
- Optimize template loading and asset delivery
- Reduce memory footprint through better organization

### Security
- WordPress security standards (nonces, capability checks, input validation)
- Secure template loading with path traversal protection
- Proper data sanitization and escaping
- Log file security (.htaccess protection)

### Maintainability
- Clear separation of concerns
- Easy to add new features
- Comprehensive logging system (when debug mode enabled)
- Follow WordPress coding standards

### Compatibility
- WordPress 5.3+ and WooCommerce 5.0+
- PHP 7.2+ support
- Flatsome theme integration
- Existing shortcode backward compatibility

## Success Criteria

### Immediate (Phase 1)
- [ ] Credit card surcharge calculation fixed
- [ ] VIP discount timing issues resolved
- [ ] Payment processing works correctly for all scenarios
- [ ] All existing shortcodes continue to function

### Short-term (Phase 2)
- [ ] Code organization improved with service classes
- [ ] Function files consolidated from 10 to 4
- [ ] Customer management unified into single service
- [ ] Template system optimized

### Long-term (Phase 3)
- [ ] 30-40% code reduction achieved
- [ ] Easy to add new features
- [ ] Comprehensive test coverage
- [ ] Performance improvements documented

## Constraints

### Must Preserve
- All existing shortcode functionality
- Current URL structure for products
- FAQ system with ACF integration
- Flatsome theme compatibility
- Debug mode features for development

### Must Not Break
- Existing customer data
- Payment processing workflows
- Product display templates
- Admin functionality
- Auto-updater system

### Technical Debt to Address
- Bloated main plugin file (1,086 lines)
- Complex autoload function (100+ lines → 20 lines)
- Scattered VIP discount logic
- Duplicate payment processing hooks
- Poor error handling in critical paths

## Implementation Priorities

1. **CRITICAL**: Fix payment processing bugs (surcharge calculation)
2. **HIGH**: Consolidate customer and pricing services
3. **MEDIUM**: Code organization and reduction
4. **LOW**: Performance optimizations and cleanup

This specification ensures the refactor maintains all existing functionality while addressing critical bugs and improving the codebase for future development.