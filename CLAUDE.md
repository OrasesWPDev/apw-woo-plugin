# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

The **APW WooCommerce Plugin** is a comprehensive WordPress plugin that extends WooCommerce functionality with advanced e-commerce features. Built specifically for the **Flatsome theme**, this plugin provides enhanced product displays, custom checkout processes, dynamic pricing integration, payment gateway enhancements, and sophisticated cart management systems.

**Current Version**: 1.18.0

### What This Plugin Does

This plugin transforms a standard WooCommerce store into a feature-rich e-commerce platform by:

1. **Custom Product Display System** - Overrides WooCommerce templates with enhanced product layouts, category displays, and shop pages optimized for the Flatsome theme
2. **Advanced Cart Management** - Real-time cart quantity indicators with bubble notifications that update instantly across all pages
3. **Dynamic Pricing Integration** - Seamless integration with WooCommerce Dynamic Pricing plugin for bulk discounts and threshold notifications
4. **Payment Gateway Enhancements** - Intuit QBMS integration with credit card surcharge calculations and enhanced checkout experience
5. **Recurring Billing System** - Manages subscription-like products with customer billing method preferences
6. **FAQ Management** - ACF-powered context-aware FAQ system that displays relevant questions based on products, categories, or pages
7. **Custom URL Structure** - SEO-friendly URLs with `/products/%category%/%product%` format
8. **Enhanced Checkout Process** - Custom fields, shipping enhancements, and form validation
9. **Product Add-ons Integration** - Extended compatibility with WooCommerce Product Add-ons plugin
10. **Account Customizations** - Enhanced My Account page with custom styling and functionality
11. **Custom Registration Fields** - Extended user registration with required fields (First/Last Name, Company, Phone) and optional referral tracking
12. **Referral Export System** - Comprehensive export functionality for tracking and analyzing user referrals with CSV exports and admin dashboard

### Target Use Case

This plugin is designed for **medium to large e-commerce stores** that need:
- Advanced product presentation capabilities
- Sophisticated pricing strategies (bulk discounts, dynamic pricing)
- Enhanced payment processing with surcharge handling
- Professional checkout experience with custom fields
- Real-time cart feedback and notifications
- SEO-optimized product URLs
- Context-aware customer support (FAQ system)
- Subscription or recurring product management
- Enhanced user registration and customer data collection
- Referral tracking and export capabilities for marketing analysis

## Developer Roadmap

### Quick Start Guide

1. **Installation Requirements**
   - WordPress 5.3+
   - PHP 7.2+
   - WooCommerce 5.0+
   - Advanced Custom Fields PRO
   - Flatsome Theme (recommended)

2. **Initial Setup**
   - Activate required dependencies first
   - Enable debug mode: Set `APW_WOO_DEBUG_MODE` to `true` in main plugin file
   - Check logs in `logs/debug-{date}.log` for initialization status
   - Verify template overrides are loading correctly

3. **Key Configuration Areas**
   - Cart quantity indicators: Add `cart-quantity-indicator` class to cart links
   - FAQ system: Configure ACF repeater fields with 'question' and 'answer' subfields
   - Dynamic pricing: Install WooCommerce Dynamic Pricing plugin for full functionality
   - Payment gateway: Configure Intuit QBMS settings for surcharge calculations

## Core Architecture

### Plugin File Structure

```
apw-woo-plugin/
├── apw-woo-plugin.php          # Main bootstrap file
├── includes/                    # Core functionality
│   ├── class-*.php             # Main classes (OOP architecture)
│   ├── apw-woo-*-functions.php # Feature-specific functions
│   └── template/               # Template system classes
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
- `APW_Woo_Template_Loader` - Handles WooCommerce template overrides and custom template loading
- `APW_Woo_Assets` - Manages CSS/JS asset registration and enqueuing
- `APW_Woo_Logger` - Centralized logging system (only active when `APW_WOO_DEBUG_MODE` is true)
- `APW_Woo_Page_Detector` - Custom URL structure detection for products/categories
- `APW_Woo_Template_Resolver` - Template resolution logic for WordPress template system
- `APW_Woo_Product_Addons` - Enhanced Product Add-ons integration and customization
- `APW_Woo_Recurring_Billing` - Manages recurring product billing method preferences

### Template System

The plugin implements a sophisticated template override system:

- **Custom Product URLs**: Supports `/products/%category%/%product%` URL structure
- **Template Hierarchy**: Plugin templates override WooCommerce defaults when present
- **Template Caching**: WordPress object cache for improved performance
- **Security**: Path traversal protection and directory validation

## Debug Mode

The plugin has extensive debug logging controlled by the constant `APW_WOO_DEBUG_MODE` in the main plugin file. When
enabled:

- Detailed logging to `logs/debug-{date}.log` files
- Admin notices for development feedback
- Template and hook visualization
- Performance tracking capabilities

**Important**: Debug mode should always be disabled in production environments.

## Custom Functionality

### Dynamic Pricing Integration

- Integrates with WooCommerce Dynamic Pricing plugin
- Custom AJAX endpoints for real-time price updates
- Functions in `includes/apw-woo-dynamic-pricing-functions.php`
- JavaScript handling in `assets/js/apw-woo-dynamic-pricing.js`

### FAQ System

- ACF-powered FAQ display system
- Context-aware FAQs (product, category, or page-specific)
- Template partial: `templates/partials/faq-display.php`
- Supports repeater field structure with 'question' and 'answer' subfields

### Product Add-ons Integration

- Enhanced WooCommerce Product Add-ons compatibility
- Custom styling and functionality extensions

### Account Customizations

- Enhanced WooCommerce My Account page functionality
- Custom address form handling
- Login/registration enhancements

### Intuit Payment Gateway Integration

- Integration with WooCommerce Intuit QBMS payment gateway
- JavaScript enhancements for payment processing
- Credit card surcharge calculation (3% fee)
- Functions in `includes/apw-woo-intuit-payment-functions.php`
- JavaScript handling in `assets/js/apw-woo-intuit-integration.js`

### Recurring Billing Management

- Checkout field for preferred monthly billing method selection
- Automatic display for products tagged with 'recurring'
- Order meta storage and admin display
- Class-based implementation in `APW_Woo_Recurring_Billing`

### Cart Quantity Indicators

- **Real-time cart count display system** with bubble notifications
- **CSS pseudo-element based quantity indicators** showing total item quantities (not just item count)
- **Advanced empty cart detection** - Fixed issue where bubble didn't update to "0" until page reload
- **Multi-layered update system** using WooCommerce cart fragments, AJAX fallbacks, and explicit cart monitoring
- **Last-item removal detection** - Immediately shows "0" when removing final cart item
- Functions in `includes/apw-woo-cart-indicator-functions.php`
- JavaScript implementation in `assets/js/apw-woo-public.js:252-594`
- CSS styling in `assets/css/woocommerce-custom.css` (`.cart-quantity-indicator` styles)

**Usage**: Add `cart-quantity-indicator` class to any cart link element:
```html
<a href="/cart" class="cart-quantity-indicator">Cart</a>
```

### Checkout and Shipping Enhancements

- Custom checkout form fields and validation
- Enhanced cross-sells functionality
- Specialized shipping calculations and options
- WooCommerce tabs customization

### Custom Registration Fields

- **Extended WooCommerce registration** with additional required fields
- **Required Fields**: First Name, Last Name, Company Name, Phone Number
- **Optional Field**: Referred By (for referral tracking)
- **Hook-based implementation** - No template overrides required for maintainability
- **Client-side validation** with real-time feedback and phone number formatting
- **Admin integration** - Custom user list columns and profile editor fields
- **WooCommerce sync** - Automatic population of billing fields during first checkout
- Main class: `APW_Woo_Registration_Fields`
- CSS styling: `assets/css/apw-registration-fields.css`
- JavaScript validation: `assets/js/apw-registration-validation.js`

**Usage**: Fields automatically appear on WooCommerce registration form when plugin is active.

### Referral Export System

- **Comprehensive export functionality** for users with referral data
- **Multiple export options**: All referrals, by specific referrer, date range filtering
- **CSV export format** with user details, registration info, and WooCommerce order data
- **Admin dashboard** with export statistics and recent exports management
- **Bulk actions** on Users list page for selective exports
- **User list filtering** by referral status (with/without referrals)
- **Secure file handling** with automatic cleanup (7-day retention)
- **Background processing** for large exports to prevent timeouts
- Main class: `APW_Woo_Referral_Export`
- Admin interface: `Users > Referral Export`
- CSS styling: `assets/css/apw-referral-export-admin.css`
- JavaScript interface: `assets/js/apw-referral-export.js`

**Usage**: Access via WordPress Admin → Users → Referral Export or use bulk actions on Users list.

**Export Data Includes**:
- User ID, Username, Email
- Registration fields (First/Last Name, Company, Phone, Referred By)
- Registration date and last login
- WooCommerce order count and total spent (optional)

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

## Dependencies

### Required Plugins

- **WooCommerce**: Core e-commerce functionality
- **Advanced Custom Fields PRO**: FAQ system and custom field management

### Optional Integrations

- **WooCommerce Dynamic Pricing**: Enhanced pricing functionality
- **WooCommerce Product Add-ons**: Extended product options
- **WooCommerce Intuit QBMS**: Payment gateway with surcharge and JavaScript enhancements

### Theme Requirements

- **Flatsome Theme**: Optimized for this theme's structure and styling
- Custom CSS handles responsive behavior and theme integration

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

## Recent Updates & Changelog

### Version 1.18.0 (Latest)
- **NEW**: Custom Registration Fields - Extended WooCommerce registration with required fields (First/Last Name, Company, Phone) and optional Referred By field
- **NEW**: Referral Export System - Comprehensive export functionality for tracking and analyzing user referrals
- **Added**: Hook-based registration field implementation with no template overrides required
- **Added**: Client-side validation with real-time feedback and phone number formatting
- **Added**: Admin user list columns and profile editor integration for new fields
- **Added**: Automatic sync of registration data to WooCommerce billing fields during first checkout
- **Added**: CSV export functionality with multiple filtering options (all, by referrer, date range)
- **Added**: Admin dashboard for referral exports with statistics and file management
- **Added**: Bulk actions on Users list page for selective referral exports
- **Added**: User list filtering by referral status
- **Added**: Secure file handling with automatic cleanup and access protection
- **Enhanced**: Plugin architecture with proper initialization functions for new features
- **Security**: All user inputs properly sanitized and validated
- **Compatibility**: Flatsome theme integration and responsive design

### Version 1.17.12
- **Optimized**: Dynamic pricing threshold message timing for instant display
- **Fixed**: Multiple simultaneous AJAX calls causing delayed threshold messages
- **Enhanced**: Duplicate call prevention for price and threshold updates
- **Improved**: Request cancellation to prevent overlapping AJAX calls
- **Reduced**: Response delays from 150ms to 50ms for faster threshold display
- **Faster**: Animation timing reduced from 450ms to 200ms for immediate visibility
- **Fixed**: Enter key on quantity input now triggers price updates instead of adding to cart
- **Enhanced**: Global form submission prevention for quantity inputs with Enter key
- **Improved**: Users must explicitly click "Add to Cart" button to add items to cart

### Version 1.17.11
- **Fixed**: Cart quantity indicator bubble not updating to "0" when cart is emptied
- **Enhanced**: Cart indicator JavaScript with comprehensive empty cart detection
- **Improved**: Multi-layered update system for better cart state synchronization
- **Added**: Immediate count=0 setting when last item is being removed
- **Added**: Enhanced event handling with multiple delayed updates for timing issues

### Version 1.17.10
- Optimized threshold message performance and reduced duplicate executions
- Fixed cart discount display timing and threshold message responsiveness
- Implemented event-driven bulk discount threshold notifications

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

## Helpful Documentation

### Official Documentation
- [Flatsome Theme Documentation](https://docs.uxthemes.com/)
- [WordPress Developer Documentation](https://wordpress.org/documentation/)
- [Advanced Custom Fields Resources](https://www.advancedcustomfields.com/resources/)
- [WooCommerce Documentation](https://woocommerce.com/documentation/woocommerce/)

### WooCommerce Specific
- [WooCommerce Intuit QBMS](https://woocommerce.com/document/woocommerce-intuit-qbms/)
- [WooCommerce Hooks & Filters](https://woocommerce.com/document/introduction-to-hooks-actions-and-filters/)
- [WooCommerce Visual Hook Guide](https://www.businessbloomer.com/woocommerce-visual-hook-guide-single-product-page/)
- [WooCommerce Product Add-ons](https://woocommerce.com/document/product-add-ons/)
- [WooCommerce Dynamic Pricing](https://woocommerce.com/document/woocommerce-dynamic-pricing/)

### Development Resources
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WooCommerce Template Structure](https://woocommerce.com/document/template-structure/)
- [WordPress AJAX in Plugins](https://developer.wordpress.org/plugins/javascript/ajax/)
