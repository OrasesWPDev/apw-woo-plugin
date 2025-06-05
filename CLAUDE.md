# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a custom WordPress plugin (`apw-woo-plugin`) that extends WooCommerce functionality with enhanced product
displays, custom templates, dynamic pricing integration, FAQ systems, Intuit payment gateway integration, recurring
billing management, and cart quantity indicators. The plugin is designed to work with the Flatsome theme and provides
custom URL structures for WooCommerce pages.

## Core Architecture

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

- Dynamic cart count display system
- CSS pseudo-element based quantity indicators
- Real-time updates via AJAX and WooCommerce cart fragments
- Functions in `includes/apw-woo-cart-indicator-functions.php`
- Note: CSS file `assets/css/apw-woo-cart-indicator.css` is referenced but missing

### Checkout and Shipping Enhancements

- Custom checkout form fields and validation
- Enhanced cross-sells functionality
- Specialized shipping calculations and options
- WooCommerce tabs customization

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

## Common Workflows

### Adding New Templates

1. Create template in appropriate `templates/woocommerce/` subdirectory
2. Follow WooCommerce template hierarchy conventions
3. Include debug comments and proper hooks
4. Test with debug mode enabled to verify template loading

### Extending Functionality

1. Use appropriate hooks throughout the codebase (`apw_woo_*` prefix for custom hooks)
2. Follow class-based architecture for complex features (see `APW_Woo_Recurring_Billing` as example)
3. Add logging for debug mode when appropriate using `apw_woo_log()` function
4. Maintain backward compatibility for existing functionality
5. Use singleton pattern for main feature classes
6. Initialize features through dedicated initialization functions (e.g., `apw_woo_initialize_*`)
7. Include proper dependency checks before feature activation

### Asset Development

1. Place CSS files in `assets/css/` (auto-discovered)
2. Place JS files in `assets/js/` (auto-discovered)
3. Use semantic naming for page-specific assets (e.g., `apw-woo-intuit-integration.js`)
4. Test cache busting during development
5. Include proper dependency declarations in `wp_enqueue_script()` calls
6. Use `wp_localize_script()` for passing PHP data to JavaScript
7. Implement proper error handling for missing asset files

### Troubleshooting

1. Enable debug mode by setting `APW_WOO_DEBUG_MODE` to `true`
2. Check log files in `logs/` directory for detailed execution information
3. Use browser developer tools to inspect template comments for verification
4. Verify all dependencies are active and properly configured

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

## Helpful documentation when needed

https://docs.uxthemes.com/
https://wordpress.org/documentation/
https://www.advancedcustomfields.com/resources/
https://woocommerce.com/document/woocommerce-intuit-qbms/
https://woocommerce.com/documentation/woocommerce/
https://woocommerce.com/document/introduction-to-hooks-actions-and-filters/
https://www.businessbloomer.com/woocommerce-visual-hook-guide-single-product-page/
https://woocommerce.com/document/product-add-ons/?_gl=1*yn2bzq*_up*MQ..*_gs*MQ..&gclid=EAIaIQobChMIucmHjeSbjAMVbmBHAR1YSSvmEAAYASAAEgIfRfD_BwE
https://woocommerce.com/document/woocommerce-dynamic-pricing/
