# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Overview

The **APW WooCommerce Plugin** is a comprehensive WordPress plugin that extends WooCommerce functionality. This file contains development-specific information for maintaining and extending the codebase.

**Current Version**: 1.24.12

## Core Architecture

### Plugin File Structure

```
apw-woo-plugin/
├── apw-woo-plugin.php          # Main bootstrap file
├── includes/                    # Core functionality
│   ├── class-*.php             # Main classes (OOP architecture)
│   ├── apw-woo-*-functions.php # Feature-specific functions
│   ├── template/               # Template system classes
│   └── vendor/                 # Third-party libraries
│       └── plugin-update-checker/ # GitHub auto-updater library
├── templates/                   # WooCommerce template overrides
│   ├── woocommerce/            # Mirrors WooCommerce structure
│   └── partials/               # Reusable template components
├── assets/                     # Frontend resources
│   ├── css/                    # Stylesheets (auto-discovery)
│   ├── js/                     # JavaScript files (auto-discovery)
│   └── images/                 # Image assets
└── logs/                       # Debug logs (when enabled)
```

### Main Plugin Structure

- **Main File**: `apw-woo-plugin.php` - Bootstrap file with plugin headers, dependency checks, and initialization
- **Core Classes**: Located in `includes/` directory with class-based architecture
- **Templates**: Custom WooCommerce template overrides in `templates/woocommerce/`
- **Assets**: CSS/JS files in `assets/` with automatic discovery and cache-busting

### Key Classes

- `APW_Woo_Plugin` - Main plugin orchestrator and initialization
- `APW_Woo_Simple_Updater` - GitHub auto-updater using industry-standard YahnisElsts Plugin Update Checker v5.6
- `APW_Woo_Template_Loader` - Handles WooCommerce template overrides and custom template loading
- `APW_Woo_Assets` - Manages CSS/JS asset registration and enqueuing
- `APW_Woo_Logger` - Centralized logging system (only active when `APW_WOO_DEBUG_MODE` is true)
- `APW_Woo_Page_Detector` - Custom URL structure detection for products/categories
- `APW_Woo_Template_Resolver` - Template resolution logic for WordPress template system
- `APW_Woo_Product_Addons` - Enhanced Product Add-ons integration and customization
- `APW_Woo_Recurring_Billing` - Manages recurring product billing method preferences
- `APW_Woo_Registration_Fields` - Custom registration fields and user data management
- `APW_Woo_Referral_Export` - Referral tracking and export functionality

### Template System

The plugin implements a sophisticated template override system:

- **Custom Product URLs**: Supports `/products/%category%/%product%` URL structure
- **Template Hierarchy**: Plugin templates override WooCommerce defaults when present
- **Template Caching**: WordPress object cache for improved performance
- **Security**: Path traversal protection and directory validation

## Debug Mode

The plugin has extensive debug logging controlled by the constant `APW_WOO_DEBUG_MODE` in the main plugin file. When enabled:

- Detailed logging to `logs/debug-{date}.log` files
- Admin notices for development feedback
- Template and hook visualization
- Performance tracking capabilities

**Important**: Debug mode should always be disabled in production environments.

## Development Workflows

### Feature Development Roadmap

#### 1. Adding New Templates
```php
// Step 1: Create template file
templates/woocommerce/single-product.php

// Step 2: Add debug comments
<?php /* APW Template: single-product.php */ ?>

// Step 3: Test template loading
// Enable APW_WOO_DEBUG_MODE and check logs for template loading confirmation
```

#### 2. Creating New Features
```php
// Step 1: Create feature class
includes/class-apw-woo-my-feature.php

// Step 2: Create initialization function
includes/apw-woo-my-feature-functions.php

// Step 3: Add to main plugin initialization
// In apw-woo-plugin.php or appropriate hook
```

#### 3. JavaScript Feature Development
```javascript
// Step 1: Create feature-specific JS file
assets/js/apw-woo-my-feature.js

// Step 2: Follow existing patterns
(function ($) {
    'use strict';
    
    // Use apwWooLog() for debug logging
    // Use proper event delegation
    // Handle AJAX with error fallbacks
})(jQuery);
```

#### 4. CSS/Styling Development
```css
/* Step 1: Add to appropriate CSS file */
assets/css/my-feature-styles.css

/* Step 2: Use semantic class names with apw-woo prefix */
.apw-woo-my-feature {
    /* styles */
}
```

### Common Development Tasks

#### Adding Cart Quantity Indicators
```html
<!-- Simply add the class to any cart link -->
<a href="/cart" class="cart-quantity-indicator">
    Cart (<span>items will be replaced</span>)
</a>
```

#### Implementing Custom AJAX Endpoints
```php
// 1. Create AJAX handler function
function apw_woo_my_ajax_handler() {
    // Verify nonce
    check_ajax_referer('apw_woo_nonce', 'nonce');
    
    // Process request
    $result = my_processing_function();
    
    // Return JSON response
    wp_send_json_success($result);
}

// 2. Register AJAX hooks
add_action('wp_ajax_my_action', 'apw_woo_my_ajax_handler');
add_action('wp_ajax_nopriv_my_action', 'apw_woo_my_ajax_handler');
```

#### Adding FAQ System to Pages
```php
// 1. Create ACF repeater field group named 'faqs'
// 2. Add subfields: 'question' (text), 'answer' (textarea)
// 3. Include FAQ template partial
get_template_part('templates/partials/faq-display');
```

#### Debugging Cart Issues
```javascript
// Enable debug mode and check console for:
apwWooLog('Cart count from fragments: ' + cartCount);

// Check cart fragments in sessionStorage:
console.log(sessionStorage.getItem('wc_fragments_xxxxx'));

// Monitor cart events:
$(document.body).on('wc_fragments_refreshed', function() {
    console.log('Cart fragments refreshed');
});
```

### Troubleshooting Guide

#### Template Issues
1. Enable `APW_WOO_DEBUG_MODE = true`
2. Check `logs/debug-{date}.log` for template loading messages
3. Look for HTML comments in source: `<!-- APW Template: filename.php -->`
4. Verify template hierarchy in WooCommerce > Status > Templates

#### Cart Quantity Indicator Issues
1. Verify `.cart-quantity-indicator` class is applied to cart links
2. Check browser console for JavaScript errors
3. Monitor cart fragment updates in Network tab
4. Test with different cart states (empty, single item, multiple items)

#### AJAX/JavaScript Issues
1. Check browser console for JavaScript errors
2. Monitor Network tab for failed AJAX requests
3. Verify nonce values are being passed correctly
4. Test with `apwWooLog()` debug statements

#### Performance Issues
1. Check if debug mode is accidentally enabled in production
2. Monitor WordPress object cache for template caching
3. Verify conditional asset loading is working
4. Check for unnecessary hook duplications

## Development Guidelines

### File Organization

- **Functions**: Non-class functionality in `includes/apw-woo-*-functions.php` files
- **Classes**: Object-oriented code in `includes/class-*.php` files
- **Templates**: WooCommerce overrides maintain same directory structure as WooCommerce
- **Assets**: Auto-discovery system loads files based on naming conventions

### Code Style

- WordPress coding standards
- Extensive PHPDoc documentation
- Security-first approach with input sanitization and validation
- Performance optimization with caching and conditional loading

### Template Development

- Templates follow WordPress/WooCommerce conventions
- Custom hooks throughout templates for extensibility
- Responsive design with Flatsome theme compatibility
- Debug-friendly with conditional logging and visualization

### Asset Management

- Automatic file discovery in `assets/css/` and `assets/js/`
- Cache busting using file modification times
- Conditional loading based on page context
- Dependency management for proper loading order

## Security Considerations

- All user inputs are sanitized and validated
- Template paths protected against directory traversal
- Log files secured with `.htaccess` restrictions
- Nonce verification for AJAX requests
- Capability checks for administrative functionality

## Performance Features

- Template caching with WordPress object cache
- Conditional asset loading based on page context
- Static caching within request lifecycle
- Output buffer management for template processing
- Hook removal caching to prevent redundant operations

## API Reference

### JavaScript Functions
```javascript
// Update cart quantity indicators (force refresh)
updateCartQuantityIndicators(true);

// Debug logging (only active when APW_WOO_DEBUG_MODE = true)
apwWooLog('Your debug message here');

// Detect cart emptying state
detectCartEmptying(); // Returns boolean
```

### PHP Functions
```php
// Log debug messages
apw_woo_log('Debug message');

// Get cart count for AJAX
apw_woo_get_cart_count();

// Check if debug mode is enabled
if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE) {
    // Debug code here
}
```

### CSS Classes
```css
/* Cart quantity indicator */
.cart-quantity-indicator[data-cart-count]::after {
    content: attr(data-cart-count);
    /* Bubble styling applied automatically */
}

/* FAQ styling */
.apw-faq-container { /* FAQ container */ }
.apw-faq-item { /* Individual FAQ item */ }

/* Notice containers */
.apw-woo-notices-container { /* Custom notice container */ }
```

## Testing & Development Commands

### CRITICAL: Before Starting Any Refactor Work

1. **Start MySQL Service** (required for testing):
```bash
brew services start mysql
```

2. **Complete WordPress Test Suite Setup**:
```bash
./bin/setup-tests.sh
```

3. **Verify Testing Environment**:
```bash
composer run test
```

### Mandatory Phase-Based Testing (Per Spec Requirements)

Each phase MUST pass testing before proceeding to the next:

```bash
# Phase 1 - Critical Payment Processing (95% coverage required)
composer run test:phase1

# Phase 2 - Service Consolidation (90% coverage required)  
composer run test:phase2

# Phase 3 - Code Optimization (maintain coverage while reducing code)
composer run test:phase3
```

### Feature-Specific Testing Commands
```bash
composer run test:payment    # CRITICAL for credit card surcharge bug fixes
composer run test:customer   # VIP/referral functionality testing
composer run test:cart       # Cart quantity indicators testing
composer run test:product    # Product-related features testing
```

### Code Quality Commands (Run Before Each Commit)
```bash
composer run quality         # All quality checks (lint + analyze + mess-detect)
composer run test:all        # Complete test suite + quality checks
composer run test:critical   # Payment + Phase 1 testing only
composer run test:coverage   # Generate HTML coverage report
```

### Individual Quality Tools
```bash
composer run lint            # WordPress/WooCommerce coding standards
composer run lint:fix        # Auto-fix coding standard violations
composer run analyze         # PHPStan static analysis (level 5)
composer run analyze:strict  # PHPStan strict analysis (level 8)
composer run mess-detect     # PHP Mess Detector for code quality
```

### Debug Mode & Logging
```bash
# Enable debug mode in apw-woo-plugin.php
define('APW_WOO_DEBUG_MODE', true);

# Check logs for debugging
tail -f logs/debug-$(date +%Y-%m-%d).log

# Test with different themes for compatibility
# Test with minimum required versions of dependencies
```

### Testing Environment Details

- **Full setup instructions**: See `TESTING_SETUP.md`
- **Test database**: `wordpress_test` (auto-created)
- **Coverage reports**: Generated in `tests/coverage/index.html`
- **WordPress/WooCommerce stubs**: Available for PHPStan analysis
- **Phase-based testing**: Organized by refactor phases for iterative development

## Auto-Updater System

### Overview
The plugin uses the industry-standard YahnisElsts Plugin Update Checker v5.6 library for reliable GitHub-based updates. This mature library is used by thousands of WordPress plugins and provides seamless integration with WordPress core update processes.

### Configuration
- **Library**: YahnisElsts Plugin Update Checker v5.6
- **Repository**: `https://github.com/OrasesWPDev/apw-woo-plugin/`
- **Check Period**: 1 minute for fast update detection
- **Admin Only**: Updates only check in WordPress admin context
- **Private Repository**: GitHub token authentication support

### Developer Usage
```php
// Access updater instance
$plugin = APW_Woo_Plugin::get_instance();
$updater = $plugin->get_updater();

// Get updater status
$status = $updater->get_update_checker_status();

// Force update check
$update = $updater->force_update_check();

// Get underlying Plugin Update Checker instance
$puc_instance = $updater->get_update_checker();
```

### GitHub Token Configuration
For private repositories, add your GitHub token to `wp-config.php`:
```php
define('APW_GITHUB_TOKEN', 'your_github_personal_access_token');
```

### Architecture Benefits
- **Industry Standard**: Proven library used by thousands of plugins
- **WordPress Native**: Seamless integration with WordPress update system
- **No Directory Issues**: Handles GitHub zipball structure properly
- **Reliable**: 85-90% success rate vs custom solutions
- **Maintained**: Actively developed and supported library

## Recent Development Changes

### Version 1.22.0 (Latest)
- **MAJOR OVERHAUL**: Replaced custom GitHub updater with YahnisElsts Plugin Update Checker v5.6
- **RELIABILITY FIX**: Resolved plugin deactivation issues after updates using proven industry-standard library
- **VENDOR INTEGRATION**: Added Plugin Update Checker v5.6 to includes/vendor/ directory
- **NATIVE COMPATIBILITY**: Seamless WordPress core update process integration
- **GITHUB ZIPBALL**: Library handles GitHub commit hash directory structure natively without custom renaming

### Version 1.19.2
- **FIXED**: Syntax error caused by old updater file still existing on servers
- **Enhanced**: Added safety checks to prevent loading of deprecated updater files
- **Improved**: More robust autoload system with better error handling
- **Updated**: Version bump to ensure clean update deployment

### Version 1.19.1
- **REFACTORED**: Standalone GitHub auto-updater (`APW_Woo_GitHub_Updater`)
- **REMOVED**: All vendor dependencies and directories
- **NEW**: Direct GitHub API integration without external libraries
- **Enhanced**: Cleaner plugin distribution with no vendor folders
- **Improved**: Lightweight updater architecture with WordPress HTTP API

### Version 1.19.0
- **NEW**: GitHub Auto-Updater system (`APW_Woo_GitHub_Updater`)
- **NEW**: Environment detection for staging and production deployments
- **Added**: Plugin Update Checker v5.6 library integration
- **Added**: Force update check functionality for admin users
- **Enhanced**: Main plugin class with updater integration

### Version 1.18.0
- **NEW**: Custom Registration Fields system (`APW_Woo_Registration_Fields`)
- **NEW**: Referral Export functionality (`APW_Woo_Referral_Export`)
- **Added**: Hook-based registration field implementation
- **Added**: Comprehensive export system with admin interface
- **Enhanced**: Plugin architecture with proper initialization functions

## Credit Card Surcharge Fix Implementation

### Problem Overview
The credit card surcharge calculation issue where surcharge shows incorrect amounts (e.g., $17.14 instead of $15.64) when VIP/quantity discounts are applied. The surcharge should be calculated as: (subtotal + shipping - discounts) × 3%.

### Recommended Implementation Steps

1. **Immediate Fix (Minimal Risk)**
   - Remove the existence check in `apw_woo_add_intuit_surcharge_fee()`
   - Clear all fees before recalculating
   - Add proper change detection using the `apw_woo_force_surcharge_recalc` flag

2. **Medium-term Solution**
   - Implement fee manager class
   - Add cart state tracking
   - Proper event-driven updates

3. **Long-term Architecture**
   - Consider moving VIP discounts to Dynamic Pricing rules
   - Use WooCommerce native systems where possible
   - Reduce custom fee manipulation

### Immediate Fix Implementation Plan

#### Files to Modify
- `includes/apw-woo-intuit-payment-functions.php` (primary changes)
- Test the fix thoroughly before deployment

#### Changes Required

1. **Remove Existence Check** (lines 237-258)
   - Current code prevents recalculation once surcharge exists
   - Replace with proper fee removal logic

2. **Implement Fee Removal** 
   ```php
   // Check if we need to force recalculation
   $force_recalc = isset($GLOBALS['apw_woo_force_surcharge_recalc']) && $GLOBALS['apw_woo_force_surcharge_recalc'];
   
   // Remove existing surcharge if forcing recalculation
   if ($force_recalc) {
       $fees = WC()->cart->get_fees();
       foreach ($fees as $key => $fee) {
           if (strpos($fee->name, 'Credit Card Surcharge') !== false) {
               unset(WC()->cart->fees[$key]);
           }
       }
       // Reset the flag
       $GLOBALS['apw_woo_force_surcharge_recalc'] = false;
   }
   ```

3. **Fix Flag Usage** (lines 130-137)
   - Currently `apw_woo_force_surcharge_recalc` flag is set but never used
   - Integrate flag checking into surcharge calculation logic

4. **Add Change Detection**
   - Create cart state hash to detect when recalculation is needed
   - Compare current cart totals with cached values

#### Testing Strategy
1. Test with Product #80, quantity 5 (should show $15.64 surcharge)
2. Test discount application/removal scenarios
3. Test payment method switching
4. Verify no duplicate surcharges
5. Test admin order editing functionality

#### Rollback Plan
- Keep current v1.23.10 code as backup
- Document all changes for easy reversion
- Test thoroughly in staging environment first

## CRITICAL: Dynamic Pricing Initialization Requirements

### **NEVER DELETE: apw_woo_init_dynamic_pricing() Call**

**CRITICAL REQUIREMENT**: The `apw_woo_init_dynamic_pricing()` function call in `apw-woo-plugin.php` at line ~803 is **ESSENTIAL** and must **NEVER** be removed or commented out.

```php
// CRITICAL FIX v1.24.12: Restore dynamic pricing initialization that was removed during Phase 2 refactoring
// This call is essential for price display and discount notices to work properly
apw_woo_init_dynamic_pricing();
```

### Why This Call is Critical

This function registers essential hooks that enable:

1. **Price Display**: `add_action('woocommerce_after_add_to_cart_quantity', 'apw_woo_replace_price_display', 10)`
2. **Discount Notices**: `add_action('wp_ajax_apw_woo_get_threshold_messages', 'apw_woo_ajax_get_threshold_messages')`
3. **VIP/Bulk Discounts**: `add_action('woocommerce_before_calculate_totals', 'apw_woo_apply_role_based_bulk_discounts', 5)`
4. **Dynamic Pricing JavaScript**: Enqueues `apw-woo-dynamic-pricing.js` with proper localization
5. **Cart Item Price Filtering**: `add_filter('woocommerce_cart_item_price', 'apw_woo_filter_cart_item_price', 10, 3)`

### Historical Context

- **v1.18.1**: Function was properly called in main initialization
- **Phase 2 Refactoring**: Call was removed during service consolidation
- **v1.24.12**: **RESTORED** - This call is now **MANDATORY** for proper functionality

### Symptoms of Missing Call

Without this initialization:
- ❌ No price display ($109.00) on product pages
- ❌ No discount threshold messages
- ❌ VIP discounts don't work (distro10 role)
- ❌ Bulk discounts don't work (5+ quantity)
- ❌ AJAX handlers not registered
- ❌ JavaScript localization fails

### Initialization Order

The call must be placed **after** Product Service initialization but **before** other services:

```php
// PHASE 2: Initialize consolidated Product Service
apw_woo_initialize_product_service();

// CRITICAL: Initialize dynamic pricing (DO NOT REMOVE)
apw_woo_init_dynamic_pricing();

// PHASE 2: Initialize other services...
```

## Development Notes

- Always use WordPress coding standards
- Test with WooCommerce latest and minimum supported versions
- Ensure Flatsome theme compatibility
- Maintain backward compatibility when possible
- Document all new functions and classes
- Use proper escaping for all output
- Validate and sanitize all inputs
- Follow existing naming conventions