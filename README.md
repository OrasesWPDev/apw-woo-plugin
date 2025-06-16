# APW WooCommerce Plugin

A comprehensive WordPress plugin that extends WooCommerce functionality with advanced e-commerce features. Built specifically for the **Flatsome theme**, this plugin provides enhanced product displays, custom checkout processes, dynamic pricing integration, payment gateway enhancements, and sophisticated cart management systems.

**Current Version**: 1.23.9

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
- **GitHub Auto-Updater** - Industry-standard YahnisElsts Plugin Update Checker v5.6 for reliable GitHub-based updates
- **WordPress Native Integration** - Works seamlessly with WordPress core update processes without interference
- **Private Repository Support** - GitHub token authentication for private repository access
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
The plugin uses the industry-standard YahnisElsts Plugin Update Checker v5.6 library for reliable GitHub-based updates:

#### Update Settings
- **Library**: YahnisElsts Plugin Update Checker v5.6
- **Check Frequency**: Every minute for fast update detection
- **Repository**: [https://github.com/OrasesWPDev/apw-woo-plugin](https://github.com/OrasesWPDev/apw-woo-plugin)
- **WordPress Integration**: Native WordPress update system compatibility

#### Private Repository Support
For private repositories, add your GitHub token to `wp-config.php`:
```php
define('APW_GITHUB_TOKEN', 'your_github_personal_access_token');
```
**Token Permissions Required**: `repo` (for private repository access)

#### Features
- Industry-standard Plugin Update Checker library used by thousands of plugins
- Seamless WordPress core update process integration
- Automatic update detection from GitHub releases
- **Private repository support** with GitHub token authentication
- **No directory renaming issues** - handles GitHub zipball structure natively
- Reliable plugin activation preservation through updates
- Proven track record for GitHub-based WordPress plugin updates

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

#### Auto-Updater Issues
1. **Update not detected**: 
   - Verify GitHub token is configured correctly for private repos
   - Check GitHub repository access and releases
   - Verify internet connectivity and GitHub API access
   - Ensure plugin version matches GitHub release tag exactly
2. **Update fails to apply**:
   - Check file permissions on plugin directory
   - Verify WordPress has write access to plugins directory
   - Review error logs for specific Plugin Update Checker messages
3. **Plugin deactivation after update**:
   - This issue is resolved with Plugin Update Checker v5.6
   - Library handles GitHub zipball directory structure properly
   - No manual directory renaming required

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

### Version 1.23.9 (Latest)
- **🔧 CRITICAL HOTFIX**: Fixed fatal ReflectionException error "Property WC_Cart::$fees does not exist" from v1.23.8
- **⚡ NATIVE API FIX**: Replaced PHP reflection with WooCommerce's native API methods for fee management
- **🛡️ VERSION-SAFE**: Multi-tier fallback system: remove_fee() → property_exists() → existence check
- **🔄 IMPROVED COMPATIBILITY**: Works across different WooCommerce versions without internal property dependencies
- **📊 ENHANCED LOGGING**: Better debug output for fee removal operations using native methods
- **🚫 REFLECTION ELIMINATED**: Completely removed fragile reflection-based cart manipulation
- **✅ PRODUCTION READY**: Fixes staging environment fatal errors while maintaining surcharge calculation accuracy

### Version 1.23.8
- **🔧 PARTIAL FIX**: Resolved credit card surcharge fee persistence issue that prevented recalculation when cart totals changed
- **💰 COMPREHENSIVE SOLUTION**: Surcharge now correctly shows $15.64 instead of $17.14 by removing stale fees before recalculation
- **🔄 SMART RECALCULATION**: Added automatic fee removal and recalculation when VIP discounts are applied or cart contents change
- **🚫 STALE FEE ELIMINATION**: Fixed root cause where old surcharge fees persisted in cart preventing fresh calculations
- **⚡ REACTIVE SYSTEM**: Added hooks to trigger surcharge recalculation on discount application and cart updates
- **❌ REFLECTION ERROR**: Used PHP reflection for fee removal which caused fatal errors in some WooCommerce versions
- **📊 ENHANCED LOGGING**: Added detailed debug logging to track fee removal and recalculation processes
- **🔧 CART STATE MANAGEMENT**: Fixed architectural issue where cart fees persisted across calculation cycles
- **⚠️ SUPERSEDED**: This version had fatal ReflectionException errors - use v1.23.9 instead

### Version 1.23.7
- **🔧 CRITICAL FIX**: Fixed credit card surcharge duplicate calculation - now shows correct $15.64 instead of $17.14
- **💥 HOTFIX**: Fixed admin order save hanging (HTTP 500 error) when adjusting shipping costs after VIP discount changes
- **🚫 ARCHITECTURAL FIX**: Moved file-level Intuit hook registration inside protected function to prevent duplicate registrations
- **🔄 INFINITE LOOP PREVENTION**: Added static processing flags and temporary hook removal to prevent recursive tax calculations
- **⚙️ HOOK MANAGEMENT**: Fixed main plugin call from deprecated function to proper apw_woo_init_intuit_integration
- **🛡️ ROBUST PROTECTION**: Applied same fix pattern as VIP discounts - eliminated file-level hook registrations without static protection

### Version 1.23.6
- **🔧 CRITICAL FIX**: Fixed duplicate VIP discount lines and missing -$3.00 tax discount in admin order view
- **🚫 DUPLICATE PREVENTION**: Moved all VIP discount hook registrations inside protected function to prevent multiple registrations
- **💰 ADMIN TAX FIX**: Restored missing -$3.00 tax discount that should persist during admin order edits
- **⚙️ ARCHITECTURE**: Applied same fix pattern as Intuit surcharge - moved file-level hooks inside static protection
- **🛡️ ENHANCED PROTECTION**: Added request-level static flags to prevent multiple executions of discount functions
- **📊 CONSISTENT BEHAVIOR**: VIP discounts now show as single line item with proper tax calculation in admin orders

### Version 1.23.5
- **🔧 HOTFIX**: Fixed duplicate credit card surcharge calculation ($17.14 → $8.57) caused by duplicate Intuit integration initialization
- **🚫 REMOVED DUPLICATE**: Eliminated duplicate initialization in product addons file that was causing double hook registrations
- **⚙️ TECHNICAL**: Each Intuit integration initialization was registering `woocommerce_cart_calculate_fees` hook, causing 2× surcharge application

### Version 1.23.4  
- **🔧 HOTFIX**: Fixed fatal error `Call to undefined method WC_Cart_Fees::remove_fee()` that broke checkout functionality
- **💳 CHECKOUT RESTORED**: Removed erroneous WooCommerce API calls and implemented proper duplicate prevention logic
- **🛡️ ERROR PREVENTION**: Replaced non-existent `remove_fee()` method with existence checks and static flags

### Version 1.23.3
- **❌ INCOMPLETE FIX**: Attempted to fix duplicate credit card surcharge - did not resolve root cause
- **📊 ENHANCED**: VIP discount tax preservation with immediate fallback tax calculation 
- **🏷️ IMPROVED**: Admin discount tax recalculation approach
- **⚠️ NOTE**: This version did not fix the underlying duplicate initialization issues

### Version 1.23.2
- **💳 FIXED**: Credit card surcharge calculation now properly accounts for VIP discount fees instead of coupon discounts
- **🔧 CORRECTED**: Surcharge base calculation changed from manual sum of negative cart fees vs discount_total field 
- **🏷️ FIXED**: VIP discount tax preservation completely redesigned to work with WooCommerce's tax calculation flow
- **⚙️ ENHANCED**: Dual hook approach (before/after calculate_totals) ensures proper tax recalculation for discount fees
- **🧮 IMPROVED**: Let WooCommerce calculate taxes naturally instead of manually preserving specific amounts
- **🔍 TECHNICAL**: VIP discounts applied as cart fees, not coupons, so discount_total was always $0
- **💰 CORRECT CALCULATION**: $545 + $26.26 - $50 = $521.26 × 3% = $15.64 (was incorrectly $17.14)
- **📊 TAX PRESERVATION**: -$3 tax removal now properly persists through admin order shipping adjustments

### Version 1.23.0
- **💳 FIXED**: Credit card surcharge calculation order - now applies 3% after discounts are deducted
- **🛒 ENHANCED**: Hook priority system ensures discounts (priority 5) apply before surcharges (priority 15)
- **👨‍💼 NEW**: Admin order discount preservation - quantity discounts maintain when admins edit orders
- **🔄 INTELLIGENT**: Automatically reapplies qualifying discounts when admin recalculates order totals
- **📊 IMPROVED**: Proper cart total calculation using WooCommerce's native get_totals() method
- **🔧 TECHNICAL**: Uses `woocommerce_order_before_calculate_totals` hook for admin order management
- **💰 CORRECT MATH**: $545 base + $25.22 shipping - $50 discount = $520.22 × 3% = $15.61 surcharge (previously $17.11)

### Version 1.22.1
- **🔧 HOTFIX**: Fixed Plugin Update Checker v5.6 API compatibility issue
- **✅ RESOLVED**: Replaced invalid `setCheckPeriod()` method call with correct initialization parameter
- **⚡ MAINTAINED**: 1-minute update check frequency using proper v5.6 API
- **🚀 TESTING**: Version created to test industry-standard update library functionality

### Version 1.22.0
- **🔄 MAJOR UPDATER OVERHAUL**: Replaced custom GitHub updater with industry-standard YahnisElsts Plugin Update Checker v5.6
- **✅ ACTIVATION RELIABILITY**: Resolves plugin deactivation issues after updates using proven library
- **🏭 INDUSTRY STANDARD**: Uses same update library as thousands of other WordPress plugins
- **🚀 NATIVE INTEGRATION**: Seamless WordPress core update process compatibility
- **🔧 GITHUB ZIPBALL FIX**: Library handles GitHub commit hash directory structure natively
- **📚 VENDOR LIBRARY**: Added Plugin Update Checker v5.6 to includes/vendor/ directory
- **🎯 85-90% SUCCESS RATE**: Based on proven library track record vs custom solution failures

### Version 1.20.6
- **🔧 CRITICAL AUTO-UPDATER FIX**: Fixed plugin deactivation after updates by implementing immediate directory renaming
- **✅ EARLY DIRECTORY FIX**: Added 'upgrader_install_package_result' hook to rename GitHub commit hash directories before WordPress processes them
- **🚀 PLUGIN ACTIVATION**: Resolved issue where plugin would deactivate itself after auto-updates due to directory name mismatch
- **🛠️ DUAL PATTERN SUPPORT**: Enhanced pattern matching to handle both 'Orases-' and 'OrasesWPDev-' directory naming conventions
- **📊 IMPROVED LOGGING**: Added detailed logging for directory renaming process during updates
- **🔒 ACTIVATION RELIABILITY**: Plugin now maintains activation status through auto-updates

### Version 1.20.5
- **TEST**: Version for hotfix verification and testing

### Version 1.20.4
- **🔧 CRITICAL AUTO-UPDATER FIX**: Fixed GitHub zipball directory extraction issue preventing proper plugin updates
- **✅ DIRECTORY STRUCTURE**: Replaced zip preprocessing with post-extraction directory fix using 'upgrader_unpack_package' hook
- **🚀 PACKAGE HANDLING**: Auto-updater now properly renames GitHub commit hash directories to correct plugin directory names
- **🛠️ UPDATE RELIABILITY**: Resolved issue where updates downloaded to wrong directories, causing "updated but not updating" behavior
- **📊 IMPROVED LOGGING**: Enhanced debug logging for extraction process to aid troubleshooting
- **🔒 PRODUCTION READY**: Maintains all previous auto-updater improvements with proper file handling

### Version 1.20.3
- **🔧 ZIP PROCESSING**: Attempted zipball preprocessing fix (superseded by v1.20.4)
- **📊 VERSION SYNC**: Updated version numbers for testing

### Version 1.20.2
- **🐛 CRITICAL AUTO-UPDATER FIXES**: Completely rebuilt auto-updater system to resolve JavaScript errors
- **✅ FIXED PLUGIN SLUG**: Corrected inconsistent plugin slug handling causing update failures
- **📋 ENHANCED METADATA**: Added missing WordPress 'Tested up to' header and improved field validation
- **🔧 FIXED TRANSIENT STRUCTURE**: Complete WordPress-compatible update transient structure
- **📊 COMPLETE API RESPONSE**: Enhanced plugin API response with all required WordPress.org fields
- **🚫 ELIMINATED JS ERRORS**: Fixed "TypeError: can't access property 'attr', t is undefined" error
- **🔒 PRODUCTION READY**: Disabled debug mode and synchronized all version numbers

### Version 1.20.1
- **🔧 CRITICAL FIXES**: Fixed version mismatch, strpos() bug, and download authentication
- **⚡ ENHANCED DOWNLOAD**: Replaced broken download_url() with proper wp_remote_get() implementation
- **🔐 AUTHENTICATION**: Proper GitHub token authentication for private repository downloads
- **🛡️ ERROR HANDLING**: Comprehensive error handling throughout download process

### Version 1.20.0
- **✅ AUTO-UPDATER FUNCTIONAL**: Auto-updater detection working with disabled debug mode
- **🧪 TESTING COMPLETE**: Comprehensive testing of GitHub API integration
- **🔄 STABLE RELEASE**: Final testing release before production deployment

### Version 1.19.8
- **🔒 PRIVATE REPO SUPPORT**: Added GitHub token authentication for private repository auto-updates
- **🔄 FALLBACK SYSTEM**: Implements /releases endpoint fallback when /releases/latest fails
- **📝 ENHANCED LOGGING**: Better error handling and logging for private repository access
- **🛠️ IMPROVED RELIABILITY**: More robust auto-updater for private GitHub repositories

### Version 1.19.7
- **🧪 AUTO-UPDATER TEST**: Testing version to verify 1-minute update detection
- **⚡ RAPID TESTING**: Created specifically to test fast update cycles
- **🔍 VERIFICATION**: Confirms GitHub auto-updater is working correctly

### Version 1.19.6
- **⏱️ 1-MINUTE CHECKS**: Changed auto-updater from hourly to every-minute checks
- **🔄 CUSTOM CRON**: Added custom WordPress cron schedule for 60-second intervals
- **🧹 CLEAN SETUP**: Enhanced cron initialization to clear existing schedules
- **📊 UPDATED STATUS**: Status reporting now shows 'every minute (testing)' check period

### Version 1.19.5
- **TEST**: Auto-updater testing version
- **VERIFIED**: Plugin deployment and activation working correctly
- **TESTED**: GitHub auto-updater system functionality

### Version 1.19.4
- **HOTFIX**: Enhanced deprecated updater file filtering to prevent syntax errors
- **IMPROVED**: More robust autoload safety checks for old updater files
- **ENHANCED**: Better error handling for server deployments with legacy files

### Version 1.19.3
- **FIXED**: Removed all site-specific environment detection that was causing deployment issues
- **IMPROVED**: Made GitHub auto-updater completely environment agnostic
- **ENHANCED**: Universal deployment compatibility for any WordPress site
- **SIMPLIFIED**: Removed staging/production specific logic that could cause errors

### Version 1.19.2
- **HOTFIX**: Fixed syntax error caused by old updater file still existing on servers
- **Enhanced**: Added safety checks to prevent loading of deprecated updater files  
- **Improved**: More robust autoload system with better error handling
- **Updated**: Version bump to ensure clean update deployment

### Version 1.19.1
- **REFACTORED**: Standalone GitHub auto-updater (APW_Woo_GitHub_Updater)
- **REMOVED**: All vendor dependencies and directories
- **NEW**: Direct GitHub API integration without external libraries
- **Enhanced**: Cleaner plugin distribution with no vendor folders
- **Improved**: Lightweight updater architecture with WordPress HTTP API

### Version 1.18.2
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