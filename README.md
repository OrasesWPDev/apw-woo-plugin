# APW WooCommerce Plugin

A comprehensive WordPress plugin that extends WooCommerce functionality with advanced e-commerce features. Built specifically for the **Flatsome theme**, this plugin provides enhanced product displays, custom checkout processes, dynamic pricing integration, payment gateway enhancements, and sophisticated cart management systems.

**Current Version**: 1.19.2

## 🚀 Features

### Core E-commerce Enhancements
- **Custom Product Display System** - Enhanced product layouts, category displays, and shop pages optimized for Flatsome theme
- **Advanced Cart Management** - Real-time cart quantity indicators with bubble notifications
- **Dynamic Pricing Integration** - Seamless integration with WooCommerce Dynamic Pricing plugin
- **Payment Gateway Enhancements** - Intuit QBMS integration with credit card surcharge calculations
- **Custom URL Structure** - SEO-friendly URLs with `/products/%category%/%product%` format

### Customer Management
- **Custom Registration Fields** - Extended user registration with required fields and referral tracking
- **Referral Export System** - Comprehensive export functionality for analyzing user referrals
- **Enhanced Checkout Process** - Custom fields, shipping enhancements, and form validation
- **Recurring Billing System** - Manages subscription-like products with customer billing preferences

### Content & Support
- **FAQ Management** - ACF-powered context-aware FAQ system
- **Product Add-ons Integration** - Extended compatibility with WooCommerce Product Add-ons plugin
- **Account Customizations** - Enhanced My Account page with custom styling and functionality

### Developer & Maintenance Features
- **GitHub Auto-Updater** - Standalone GitHub API auto-updater with environment detection (staging/production)
- **Environment-Aware Updates** - Different update behaviors for staging and production environments  
- **Vendor-Free Architecture** - No external dependencies for cleaner plugin distribution
- **Debug Logging** - Comprehensive logging system for development and troubleshooting

## 📋 Requirements

### Minimum Requirements
- **WordPress**: 5.3 or higher
- **PHP**: 7.2 or higher
- **WooCommerce**: 5.0 or higher

### Required Plugins
- **WooCommerce** - Core e-commerce functionality
- **Advanced Custom Fields PRO** - FAQ system and custom field management

### Recommended
- **Flatsome Theme** - Plugin is optimized for this theme's structure and styling

### Optional Integrations
- **WooCommerce Dynamic Pricing** - Enhanced pricing functionality
- **WooCommerce Product Add-ons** - Extended product options
- **WooCommerce Intuit QBMS** - Payment gateway with surcharge calculations

## 🔧 Installation

1. **Download** the plugin files
2. **Upload** to your WordPress site via:
   - WordPress Admin → Plugins → Add New → Upload Plugin
   - Or upload to `/wp-content/plugins/` directory via FTP
3. **Activate** the plugin through WordPress Admin → Plugins
4. **Configure** settings as needed (see Configuration section)

## ⚙️ Configuration

### Initial Setup
1. Ensure WooCommerce and ACF Pro are installed and activated
2. The plugin will automatically integrate with your existing WooCommerce setup
3. New registration fields will appear on the WooCommerce registration form
4. Referral export functionality will be available in Users → Referral Export

### Debug Mode
For development and troubleshooting, debug mode can be enabled by editing the main plugin file:
```php
define('APW_WOO_DEBUG_MODE', true);
```
This enables detailed logging to `logs/debug-{date}.log` files.

### Auto-Updater Configuration
The plugin includes a standalone GitHub-based auto-updater with no external dependencies:

#### Environment Detection
- **Staging**: `https://allpointstage.wpenginepowered.com/`
- **Production**: `https://allpointwireless.com`

#### Update Settings
- **Check Frequency**: 1 minute for both environments
- **Repository**: [https://github.com/OrasesWPDev/apw-woo-plugin](https://github.com/OrasesWPDev/apw-woo-plugin)
- **Force Update**: Add `?apw_force_update_check=1` to any admin URL (admin users only)

#### Features
- Direct GitHub API integration (no vendor dependencies)
- Automatic update detection from GitHub releases
- Environment-aware logging (enhanced for staging)
- Admin notices for update status (staging only when debug mode enabled)
- Secure admin-only update checking
- Clean plugin distribution without vendor directories

## 🎯 Key Features Guide

### Custom Registration Fields

#### What It Does
Extends the standard WooCommerce registration form with additional required fields for better customer data collection.

#### Fields Added
- **First Name** (required)
- **Last Name** (required)
- **Company Name** (required)
- **Phone Number** (required)
- **Referred By** (optional)

#### Features
- Real-time client-side validation
- Phone number formatting
- Admin user list integration
- Automatic sync to WooCommerce billing fields during first checkout

#### Usage
Fields automatically appear on the WooCommerce registration form at `/my-account/`. No additional setup required.

### Referral Export System

#### What It Does
Provides comprehensive export functionality for users who were referred by others, enabling referral program tracking and analysis.

#### Access Points
- **Admin Dashboard**: Users → Referral Export
- **Bulk Actions**: Select users on Users list page and use "Export Selected (Referrals Only)"
- **Quick Export**: "Export All Referrals" button on Users list page

#### Export Options
1. **All Referrals** - Export all users with referral data
2. **By Referrer** - Filter by specific referrer name
3. **Date Range** - Export users registered within a date range

#### Export Data Includes
- User ID, Username, Email
- Registration fields (First/Last Name, Company, Phone, Referred By)
- Registration date and last login
- WooCommerce order count and total spent (optional)

#### File Management
- Exports saved as CSV files
- 7-day automatic cleanup
- Secure file storage (no direct access)
- Download links in admin interface

### Cart Quantity Indicators

#### What It Does
Displays real-time cart item counts with visual bubble notifications that update instantly across all pages.

#### Usage
Add the CSS class `cart-quantity-indicator` to any cart link:
```html
<a href="/cart" class="cart-quantity-indicator">Cart</a>
```

#### Features
- Real-time updates via AJAX
- Shows total item quantities (not just item count)
- Immediate "0" display when cart is emptied
- Works with Flatsome theme styling

### Dynamic Pricing Integration

#### What It Does
Enhances WooCommerce Dynamic Pricing plugin with real-time price updates and threshold notifications.

#### Features
- Instant price updates when quantities change
- Bulk discount threshold messages
- Optimized AJAX performance
- Prevents form submission on Enter key for quantity inputs

### FAQ System

#### What It Does
Displays context-aware FAQs based on products, categories, or pages using Advanced Custom Fields.

#### Setup Required
1. Create ACF repeater field group named 'faqs'
2. Add subfields: 'question' (text), 'answer' (textarea)
3. Assign to products, categories, or pages as needed

#### Usage
FAQs automatically display on relevant pages when configured.

### Payment Integration

#### Intuit QBMS Enhancement
- Credit card surcharge calculation (3% fee)
- Enhanced checkout experience
- JavaScript payment processing improvements

### Recurring Billing

#### What It Does
Adds a "Preferred Monthly Billing Method" field for products tagged as 'recurring'.

#### Setup
1. Tag products with 'recurring' tag
2. Field automatically appears on checkout for those products
3. Selection saved to order meta and visible in admin

## 🛠️ Customization

### Styling
The plugin includes responsive CSS files that integrate with Flatsome theme:
- `assets/css/apw-registration-fields.css` - Registration form styling
- `assets/css/woocommerce-custom.css` - General WooCommerce customizations
- `assets/css/faq-styles.css` - FAQ display styling

### JavaScript Enhancement
Client-side functionality provided by:
- `assets/js/apw-registration-validation.js` - Registration form validation
- `assets/js/apw-woo-public.js` - Cart indicators and general functionality
- `assets/js/apw-woo-dynamic-pricing.js` - Dynamic pricing interactions

### Hooks and Filters
The plugin uses WordPress/WooCommerce hooks for extensibility:
- `woocommerce_register_form` - Add registration fields
- `woocommerce_registration_errors` - Validate registration
- `woocommerce_created_customer` - Save registration data
- Various user and admin hooks for customization

## 📊 Admin Features

### User Management
- **Enhanced User List** - Additional columns for Company, Phone, and Referred By
- **Sortable Columns** - Sort users by custom fields
- **Filtering** - Filter users by referral status
- **Bulk Actions** - Export selected users with referrals

### Export Dashboard
- **Statistics** - View total users with referrals
- **Export History** - Manage recent export files
- **Advanced Filtering** - Multiple export criteria
- **Secure Downloads** - Protected file access

### Debug Information
When debug mode is enabled:
- Detailed logging of all plugin operations
- Template loading confirmation
- Performance tracking
- Error diagnostics

## 🔒 Security Features

### Data Protection
- All user inputs sanitized and validated
- Nonce verification for AJAX requests
- Capability checks for administrative functions
- Secure file storage with access restrictions

### Privacy Compliance
- User data preserved on plugin uninstall (as requested)
- No unnecessary data collection
- Secure export file handling
- Automatic cleanup of temporary files

## 🚨 Troubleshooting

### Common Issues

#### Company Field Not Showing on Address Forms
1. Ensure WooCommerce is active and updated
2. Check WooCommerce → Settings → General that company field is enabled
3. Verify plugin is properly activated
4. Enable debug mode to check logs for field enforcement messages
5. Clear any caching plugins that might interfere with address forms
6. Check for theme conflicts by temporarily switching to a default theme

#### Registration Fields Not Showing
1. Ensure WooCommerce is active and updated
2. Check if registration is enabled in WooCommerce settings
3. Verify plugin is properly activated
4. Enable debug mode to check logs

#### Export Showing HTML in CSV
1. Ensure you're using version 1.18.1 or later
2. Re-export your data to get properly formatted currency values
3. Check that CSV opens correctly in spreadsheet applications
4. Verify export files in uploads/apw-referral-exports/ directory

#### Export Not Working
1. Verify user has 'manage_woocommerce' capability
2. Check file permissions in uploads directory
3. Ensure users have referral data to export
4. Review error logs for specific issues

#### Cart Indicators Not Updating
1. Check if `cart-quantity-indicator` class is applied
2. Verify JavaScript is loading without errors
3. Test with different cart states
4. Check WooCommerce cart fragments functionality

### Debug Mode
Enable debug mode for detailed troubleshooting:
```php
define('APW_WOO_DEBUG_MODE', true);
```
Log files will be created in the `logs/` directory.

### Support Information
- Check `logs/debug-{date}.log` for detailed error information
- Verify all required plugins are active and updated
- Test with default WordPress theme to isolate theme conflicts
- Ensure server meets minimum PHP and WordPress requirements

## 📈 Performance

### Optimization Features
- Template caching with WordPress object cache
- Conditional asset loading based on page context
- Efficient database queries with proper indexing
- Static caching within request lifecycle
- Automatic cleanup of temporary files

### Best Practices
- Keep debug mode disabled in production
- Regularly clean up old export files
- Monitor log file sizes in development
- Use proper caching plugins for optimal performance

## 📝 Changelog

### Version 1.18.2 (Latest)
- **FIXED**: CSV export showing HTML entities (`&#36;`) instead of currency symbol (`$`) in Total Spent column
- **Enhanced**: Robust price formatting with HTML entity decoding for clean CSV output
- **Improved**: Better handling of various price data formats (string/numeric/formatted)
- **Added**: Enhanced debug logging for CSV price formatting when debug mode enabled
- **Updated**: .gitignore to exclude CSV test files from version control

### Version 1.18.1
- **FIXED**: Company name field not displaying on billing/shipping address edit forms
- **FIXED**: CSV export showing HTML markup in Total Spent column instead of plain text
- **Enhanced**: Address field enforcement with multiple priority hooks for better compatibility
- **Enhanced**: Template-level fallback mechanisms for missing company fields
- **Added**: Comprehensive debug logging for address field modifications
- **Improved**: CSV export formatting with proper currency symbol handling

### Version 1.18.0
- **NEW**: Custom Registration Fields with required fields and referral tracking
- **NEW**: Referral Export System with comprehensive CSV export functionality
- **Added**: Hook-based registration implementation with client-side validation
- **Added**: Admin dashboard for referral exports with statistics
- **Added**: Bulk actions and filtering on Users list page
- **Added**: Secure file handling with automatic cleanup
- **Enhanced**: Plugin architecture with proper initialization
- **Security**: Comprehensive input sanitization and validation

### Version 1.17.12
- **Optimized**: Dynamic pricing threshold message timing
- **Fixed**: Multiple AJAX calls causing delayed messages
- **Enhanced**: Cart quantity indicators performance
- **Improved**: User experience with faster animations

### Version 1.17.11
- **Fixed**: Cart quantity indicator not updating to "0" when cart emptied
- **Enhanced**: Multi-layered update system for better synchronization
- **Improved**: Event handling for cart state changes

## 🤝 Contributing

This plugin follows WordPress coding standards and best practices. For development information, see the `CLAUDE.md` file.

## 📄 License

GPL v2 or later - http://www.gnu.org/licenses/gpl-2.0.txt

---

**Built for WordPress • Enhanced for WooCommerce • Optimized for Flatsome**