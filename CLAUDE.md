# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Overview

The **APW WooCommerce Plugin** is a comprehensive WordPress plugin that extends WooCommerce functionality. This file contains development-specific information for maintaining and extending the codebase.

**Current Version**: 1.19.2

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
- `APW_Woo_GitHub_Updater` - Standalone GitHub API auto-updater with environment detection (staging/production)
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

When working on this plugin, use these commands for testing:

```bash
# Enable debug mode in apw-woo-plugin.php
define('APW_WOO_DEBUG_MODE', true);

# Check logs for debugging
tail -f logs/debug-$(date +%Y-%m-%d).log

# Test with different themes for compatibility
# Test with minimum required versions of dependencies
```

## Auto-Updater System

### Overview
The plugin includes a standalone GitHub-based auto-updater system (`APW_Woo_GitHub_Updater`) with direct GitHub API integration and no external dependencies.

### Environment Detection
The updater automatically detects the environment based on site URL:
- **Staging**: `https://allpointstage.wpenginepowered.com/`
- **Production**: `https://allpointwireless.com`

### Configuration
- **Repository**: `https://github.com/OrasesWPDev/apw-woo-plugin/`
- **Check Period**: 1 minute for both environments
- **Admin Only**: Updates only check in WordPress admin context
- **No Dependencies**: Direct GitHub API integration without vendor libraries

### Developer Usage
```php
// Access updater instance
$plugin = APW_Woo_Plugin::get_instance();
$updater = $plugin->get_updater();

// Get updater status
$status = $updater->get_update_checker_status();

// Force update check
$update = $updater->force_update_check();

// Check environment
$environment = $updater->get_environment();
```

### Force Update Check
Add `?apw_force_update_check=1` to any admin URL (requires admin privileges).

### Staging Features
- Enhanced logging when `APW_WOO_DEBUG_MODE` is enabled
- Admin notices showing update status
- More detailed debug information

### Architecture Benefits
- **Vendor-Free**: No external library dependencies for cleaner distribution
- **Direct API**: Uses WordPress HTTP API for GitHub communication
- **Lightweight**: Minimal code footprint compared to complex updater libraries

## Recent Development Changes

### Version 1.19.2 (Latest)
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

## Development Notes

- Always use WordPress coding standards
- Test with WooCommerce latest and minimum supported versions
- Ensure Flatsome theme compatibility
- Maintain backward compatibility when possible
- Document all new functions and classes
- Use proper escaping for all output
- Validate and sanitize all inputs
- Follow existing naming conventions