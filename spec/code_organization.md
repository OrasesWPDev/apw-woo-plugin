# Code Organization Specification

## Overview
This specification defines the reorganized directory structure and file organization for the APW WooCommerce Plugin, focusing on clear separation of concerns, WordPress conventions, and maintainable architecture.

## Current Directory Analysis

### Current Structure Issues
```
apw-woo-plugin/
├── apw-woo-plugin.php (1,086 lines - BLOATED)
├── includes/ (20 files, mixed concerns)
│   ├── class-*.php (6 files, varying quality)
│   ├── apw-woo-*-functions.php (10 files, scattered logic)
│   └── vendor/ (external libraries)
├── templates/ (organized but verbose)
├── assets/ (well organized)
└── logs/ (appropriate)
```

### Problems with Current Organization
1. **Mixed File Types**: Classes and functions intermixed in includes/
2. **Unclear Naming**: Inconsistent naming conventions
3. **Large Files**: Main plugin file handles too many concerns
4. **Scattered Logic**: Related functionality spread across multiple files
5. **No Clear Hierarchy**: Flat structure makes navigation difficult

## New Directory Structure

### Target Organization
```
apw-woo-plugin/
├── apw-woo-plugin.php (650 lines - simplified)
├── includes/
│   ├── class-apw-woo-plugin.php (main orchestrator)
│   ├── services/ (business logic services)
│   │   ├── class-apw-woo-payment-service.php
│   │   ├── class-apw-woo-customer-service.php
│   │   ├── class-apw-woo-product-service.php
│   │   └── class-apw-woo-cart-service.php
│   ├── integrations/ (third-party integrations)
│   │   ├── class-apw-woo-intuit-integration.php
│   │   ├── class-apw-woo-acf-integration.php
│   │   └── class-apw-woo-dynamic-pricing-integration.php
│   ├── admin/ (admin-specific functionality)
│   │   ├── class-apw-woo-admin-menu.php
│   │   ├── class-apw-woo-settings.php
│   │   └── class-apw-woo-export-handler.php
│   ├── functions/ (WordPress-style helper functions)
│   │   ├── template-functions.php
│   │   └── utility-functions.php
│   └── vendor/ (external libraries)
├── templates/ (organized by purpose)
│   ├── woocommerce/ (WooCommerce overrides)
│   ├── admin/ (admin template parts)
│   └── partials/ (reusable components)
├── assets/ (frontend resources)
│   ├── css/ (stylesheets)
│   ├── js/ (JavaScript files)
│   └── images/ (image assets)
└── logs/ (debug logs when enabled)
```

## File Organization Principles

### 1. Separation by Purpose

#### Service Classes (Business Logic)
```php
// includes/services/class-apw-woo-payment-service.php
class APW_Woo_Payment_Service {
    // Handles payment processing, surcharge calculation
    // Consolidates from: apw-woo-intuit-payment-functions.php
    //                   apw-woo-recurring-billing-functions.php
    //                   apw-woo-checkout-fields-functions.php
}

// includes/services/class-apw-woo-customer-service.php  
class APW_Woo_Customer_Service {
    // Handles customer management, VIP status, registration
    // Consolidates from: class-apw-woo-registration-fields.php
    //                   class-apw-woo-referral-export.php
    //                   scattered VIP logic
}

// includes/services/class-apw-woo-product-service.php
class APW_Woo_Product_Service {
    // Handles product pricing, dynamic pricing, add-ons
    // Consolidates from: apw-woo-dynamic-pricing-functions.php
    //                   apw-woo-product-addons-functions.php
    //                   apw-woo-cross-sells-functions.php
}

// includes/services/class-apw-woo-cart-service.php
class APW_Woo_Cart_Service {
    // Handles cart operations, indicators, shipping
    // Consolidates from: apw-woo-cart-indicator-functions.php
    //                   apw-woo-shipping-functions.php
}
```

#### Integration Classes (Third-party)
```php
// includes/integrations/class-apw-woo-intuit-integration.php
class APW_Woo_Intuit_Integration {
    // Specific to Intuit/QuickBooks payment gateway
    // Clean separation from general payment logic
}

// includes/integrations/class-apw-woo-acf-integration.php
class APW_Woo_ACF_Integration {
    // Handles Advanced Custom Fields integration
    // FAQ system, custom fields
}

// includes/integrations/class-apw-woo-dynamic-pricing-integration.php
class APW_Woo_Dynamic_Pricing_Integration {
    // Interfaces with WooCommerce Dynamic Pricing plugin
}
```

#### Admin Classes (WordPress Admin)
```php
// includes/admin/class-apw-woo-admin-menu.php
class APW_Woo_Admin_Menu {
    // Admin menu structure and pages
}

// includes/admin/class-apw-woo-settings.php
class APW_Woo_Settings {
    // Settings page handling using WordPress Settings API
}

// includes/admin/class-apw-woo-export-handler.php
class APW_Woo_Export_Handler {
    // CSV export functionality for admin
}
```

### 2. WordPress Naming Conventions

#### File Naming Standards
```php
// Class files: class-[plugin-prefix]-[purpose].php
class-apw-woo-payment-service.php
class-apw-woo-customer-service.php

// Function files: [purpose]-functions.php  
template-functions.php
utility-functions.php

// Template files: [woocommerce-path]/[template-name].php
templates/woocommerce/single-product.php
templates/partials/faq-display.php
```

#### Class Naming Standards
```php
// Service classes
class APW_Woo_Payment_Service
class APW_Woo_Customer_Service

// Integration classes  
class APW_Woo_Intuit_Integration
class APW_Woo_ACF_Integration

// Admin classes
class APW_Woo_Admin_Menu
class APW_Woo_Settings
```

### 3. Autoloading Strategy

#### Simple WordPress-Compatible Autoloader
```php
// In main plugin file
function apw_woo_autoload($class_name) {
    // Only handle our classes
    if (strpos($class_name, 'APW_Woo_') !== 0) {
        return;
    }
    
    // Convert class name to file name
    $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
    
    // Service classes
    $service_file = APW_WOO_PLUGIN_DIR . 'includes/services/' . $file_name;
    if (file_exists($service_file)) {
        require_once $service_file;
        return;
    }
    
    // Integration classes
    $integration_file = APW_WOO_PLUGIN_DIR . 'includes/integrations/' . $file_name;
    if (file_exists($integration_file)) {
        require_once $integration_file;
        return;
    }
    
    // Admin classes
    $admin_file = APW_WOO_PLUGIN_DIR . 'includes/admin/' . $file_name;
    if (file_exists($admin_file)) {
        require_once $admin_file;
        return;
    }
    
    // Main includes directory (legacy)
    $main_file = APW_WOO_PLUGIN_DIR . 'includes/' . $file_name;
    if (file_exists($main_file)) {
        require_once $main_file;
    }
}

spl_autoload_register('apw_woo_autoload');
```

## File Content Organization

### 1. Service Class Structure Template

#### Standard Service Class Layout
```php
<?php
/**
 * [Service Name] Service
 *
 * @package APW_Woo_Plugin
 * @since 1.24.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * [Service Name] Service Class
 *
 * Handles [specific business domain] functionality
 */
class APW_Woo_[Service]_Service {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Service-specific cache
     */
    private $cache = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook registration with proper priorities
        add_action('init', [$this, 'init']);
        add_action('wp_loaded', [$this, 'late_init']);
    }
    
    /**
     * Early initialization
     */
    public function init() {
        // Early setup code
    }
    
    /**
     * Late initialization  
     */
    public function late_init() {
        // Late setup code that requires other plugins
    }
    
    // Public API methods
    
    // Private helper methods
    
    // Static utility methods if needed
}
```

### 2. Function File Organization

#### Template Functions
```php
<?php
/**
 * Template Helper Functions
 *
 * @package APW_Woo_Plugin
 * @since 1.24.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get template part with plugin override capability
 */
function apw_woo_get_template_part($slug, $name = '', $args = []) {
    // Template hierarchy implementation
}

/**
 * Render FAQ section
 */
function apw_woo_render_faq_section($faqs, $args = []) {
    // FAQ rendering logic
}

/**
 * Get cart quantity for display
 */
function apw_woo_get_cart_quantity() {
    // Cart quantity helper
}

// Only template-related functions in this file
```

#### Utility Functions
```php
<?php
/**
 * Utility Helper Functions
 *
 * @package APW_Woo_Plugin
 * @since 1.24.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug logging helper
 */
function apw_woo_log($message, $level = 'info') {
    if (!APW_WOO_DEBUG_MODE) return;
    
    $logger = APW_Woo_Logger::get_instance();
    $logger->log($message, $level);
}

/**
 * Validate product helper
 */
function apw_woo_get_valid_product($product_id) {
    $product = wc_get_product($product_id);
    return $product && $product->exists() ? $product : false;
}

/**
 * Format price for display
 */
function apw_woo_format_price($amount, $currency = null) {
    return wc_price($amount, ['currency' => $currency]);
}

// Only utility functions in this file
```

## Template Organization

### 1. WooCommerce Override Structure
```
templates/
├── woocommerce/
│   ├── single-product.php (main product template)
│   ├── archive-product.php (shop page template)
│   ├── cart/ (cart-related templates)
│   └── checkout/ (checkout-related templates)
├── admin/
│   ├── settings-page.php
│   ├── export-form.php
│   └── dashboard-widget.php
└── partials/
    ├── faq-display.php
    ├── cart-indicator.php
    └── debug-info.php
```

### 2. Template Loading Function
```php
function apw_woo_load_template($template_name, $args = [], $template_path = '') {
    // Extract variables for template use
    if (!empty($args) && is_array($args)) {
        extract($args, EXTR_SKIP);
    }
    
    // Template hierarchy
    $template_locations = [
        get_stylesheet_directory() . '/apw-woo-plugin/' . $template_name,
        get_template_directory() . '/apw-woo-plugin/' . $template_name,
        APW_WOO_PLUGIN_DIR . 'templates/' . $template_name
    ];
    
    foreach ($template_locations as $template_file) {
        if (file_exists($template_file)) {
            include $template_file;
            return;
        }
    }
    
    // Template not found - log warning in debug mode
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Template not found: {$template_name}", 'warning');
    }
}
```

## Asset Organization

### 1. Context-Specific Loading
```php
function apw_woo_enqueue_assets() {
    // Only load what's needed based on context
    
    if (is_checkout()) {
        wp_enqueue_script(
            'apw-woo-checkout',
            APW_WOO_PLUGIN_URL . 'assets/js/checkout.js',
            ['jquery', 'wc-checkout'],
            APW_WOO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'apw-woo-checkout',
            APW_WOO_PLUGIN_URL . 'assets/css/checkout.css',
            ['woocommerce-general'],
            APW_WOO_VERSION
        );
    }
    
    if (is_product()) {
        wp_enqueue_script(
            'apw-woo-product',
            APW_WOO_PLUGIN_URL . 'assets/js/product.js',
            ['jquery'],
            APW_WOO_VERSION,
            true
        );
    }
    
    // Always load core styles (minimal)
    wp_enqueue_style(
        'apw-woo-core',
        APW_WOO_PLUGIN_URL . 'assets/css/core.css',
        [],
        APW_WOO_VERSION
    );
}
add_action('wp_enqueue_scripts', 'apw_woo_enqueue_assets');
```

### 2. Asset File Structure
```
assets/
├── css/
│   ├── core.css (always loaded)
│   ├── checkout.css (checkout only)
│   ├── product.css (product pages only)
│   └── admin.css (admin only)
├── js/
│   ├── checkout.js (checkout functionality)
│   ├── product.js (product page functionality)
│   ├── cart-indicator.js (cart updates)
│   └── admin.js (admin functionality)
└── images/
    ├── icons/
    └── placeholders/
```

## Migration Path

### 1. File Migration Order
1. **Create new directory structure**
2. **Move and refactor service classes**
3. **Consolidate function files**
4. **Update autoloader**
5. **Test each component**
6. **Remove old files**

### 2. Migration Commands
```bash
# Create new directory structure
mkdir -p includes/{services,integrations,admin,functions}
mkdir -p templates/{admin,partials}

# Move existing classes to appropriate directories
mv includes/class-apw-woo-registration-fields.php includes/admin/
mv includes/class-apw-woo-logger.php includes/

# Create new service classes (consolidating functions)
# Create new integration classes
# Update main plugin file autoloader
```

## Success Metrics

### Organization Quality
- [ ] Clear separation of concerns by directory
- [ ] Consistent naming conventions throughout
- [ ] Logical file hierarchy that's easy to navigate
- [ ] Appropriate file sizes (no files > 400 lines)

### Performance Impact
- [ ] Conditional loading reduces memory usage
- [ ] Autoloader efficiency improved
- [ ] Asset loading optimized by context
- [ ] No performance degradation from reorganization

### Maintainability
- [ ] Easy to locate specific functionality
- [ ] Clear patterns for adding new features
- [ ] Reduced cognitive load for developers
- [ ] Improved code discoverability

### WordPress Compliance
- [ ] Follows WordPress plugin directory conventions
- [ ] Compatible with WordPress coding standards
- [ ] Proper template hierarchy implementation
- [ ] Asset loading follows WordPress patterns

This organized structure makes the plugin much easier to navigate, maintain, and extend while following WordPress best practices.