# Allpoint Wireless WooCommerce Plugin - Development Guide

## Project Overview

This document outlines the implementation requirements for 3 critical issues in the Allpoint Wireless
WordPress/WooCommerce plugin located at `~/projects/apw-apw-woo-plugin`.

**Status**: Approved by Devon D'Andrea on July 8, 2025
**Technical Review**: Sr. Developer Richard Sacco completed a 2-hour technical spike (JIRA: WATM-1803) reviewing
integration requirements in May 2025

**PRIORITY ORDER**: Task 1 is the TOP PRIORITY and should be implemented first.

## Development Tasks

### 1. Allpoint Command Integration & Order Processing (TOP PRIORITY)

**Problem**: Orders with recurring products need to integrate with Allpoint Command backend and provide proper customer
notifications and registration flow.

**Implementation Requirements**:

*Note: Based on Sr. Developer Richard Sacco's technical spike (WATM-1803), the integration architecture and requirements
have been validated for feasibility.*

#### 1.1 Environment-Based API Integration

- **Environment Detection**: Use environment-based functions to determine endpoints
    - Production: `https://allpointcommand.com`
    - Staging: `https://watm.beta.orases.dev`
    - Review: `https://watm.review.orases.dev`
    - WordPress Staging: `http://allpointstage.wpenginepowered.com/`
    - WordPress Development: `http://allpointwi1dev.wpenginepowered.com/`
- **API Posting**: For orders containing products with "recurring" tag, POST data to:
    - `{environment}/woocommerce` endpoint
    - Use staging/development for testing, production for live orders
- **Data Format**: Post the following JSON structure:
  ```
  {
    "order_number": 1,
    "woocommerce_user_id": 1,
    "order_details": {
      "addresses": {
        "billing": {...},
        "shipping": {...}
      },
      "items": {
        "product_id": {
          "name": "Product Name",
          "slug": "product-slug",
          "quantity": 1
        }
      }
    },
    "order_date": "2023-07-17 13:20:15",
    "woocommerce_token": "generated_md5_token"
  }
  ```

#### 1.2 Token Generation & Customer Registration Flow

- **Token Generation**: Create unique token using `md5(order_id + customer_id)`
- **Registration Links**: Display post-checkout markup for recurring orders with link to:
    - `{environment}/create-company?token={generated_token}`

#### 1.3 Customer Email Notifications

- **Trigger**: Send email for all orders containing recurring products
- **Content Updates**:
    - Replace "The Wireless Box" with "Allpoint Wireless"
    - Replace "checkurbox.com" references with appropriate Allpoint Command URL
    - Remove all ATM-related content
- **Rental Product Handling**:
    - For products with "rental" tag, include rental agreement notification
    - Attach rental document from `/assets/pdf/` directory in plugin
    - Include specific rental shipping hold messaging

#### 1.4 Post-Checkout Display

- **Recurring Orders**: Show registration instructions with styled button
- **Visual Design**: Implement animated gradient button (pizzazz class)
- **Messaging**: Clear instructions to complete registration at Allpoint Command
- **Conditional Display**: Only show for orders with recurring tag products

**Files to implement**:

- `includes/class-allpoint-command-integration.php` - Main integration logic
- `includes/class-environment-manager.php` - Environment detection
- `includes/class-order-notifications.php` - Email handling
- `templates/checkout/recurring-order-notice.php` - Post-checkout display
- `assets/pdf/` - Directory for rental agreement document

**Key Areas**:

- WooCommerce order completion hooks (`woocommerce_thankyou`, `woocommerce_order_status_processing`)
- Product tag detection for "recurring" and "rental" tags
- Environment-based URL generation
- Email template customization
- Rental document handling from `/assets/pdf/` directory

**Expected Solution**:

- Seamless integration with Allpoint Command backend
- Environment-aware posting (staging vs production vs development)
- Proper error handling and logging
- Updated email notifications with correct branding
- Clear customer registration flow
- Rental document attachment from plugin assets

**Testing Requirements**:

- Test orders with recurring products only
- Test orders with rental products (document attachment)
- Test orders with mixed product types
- Test orders without recurring/rental products
- Verify API posting to correct environment
- Test token generation and registration links
- Verify email delivery and content
- Test error handling for API failures
- Validate logging for staging vs production endpoints

### 2. Tax Calculation Fix When Discounts Are Applied

**Problem**: Tax disappears when discounts are applied, requiring manual adjustments in wp-admin orders.

**Implementation Requirements**:

- **Files to examine/modify**:
    - Look for tax calculation hooks in the main plugin file
    - Check for custom tax calculation functions
    - Examine WooCommerce filter/action implementations
- **Key Areas**:
    - WooCommerce tax calculation filters (`woocommerce_calculated_total`, `woocommerce_calculate_totals`)
    - Discount application logic
    - Order total calculation methods
- **Expected Solution**:
    - Ensure tax is recalculated after discount application
    - Implement proper tax calculation sequence
    - Add debugging/logging for tax calculation issues
- **Testing Requirements**:
    - Test with percentage discounts
    - Test with fixed amount discounts
    - Test with coupon codes
    - Verify tax appears correctly in order totals and admin

### 3. Order Email Formatting and Customer Options

**Problem**: Order emails need formatting improvements and must include customer-selected options (carrier type, billing
preference).

**Implementation Requirements**:

- **Files to examine/modify**:
    - Email template files (likely in `templates/emails/` or similar)
    - Order processing hooks
    - Custom field handling for carrier type and billing preferences
- **Key Areas**:
    - WooCommerce email templates (`customer-processing-order.php`, `admin-new-order.php`)
    - Email hooks (`woocommerce_email_order_meta`, `woocommerce_email_customer_details`)
    - Custom order meta fields
- **Expected Solution**:
    - Create/modify email templates to include:
        - Carrier type selection
        - Billing preference information
        - Improved formatting for better readability
    - Ensure data is captured during checkout and stored as order meta
    - Add custom email sections for wireless-specific information
- **Testing Requirements**:
    - Test order emails with different carrier types
    - Test order emails with different billing preferences
    - Verify both customer and admin emails include the information
    - Test email formatting across different email clients

## Technical Implementation Guidelines

### Code Organization

```
apw-apw-woo-plugin/
├── assets/
│   ├── css/
│   ├── js/
│   └── pdf/                              # Rental agreement documents
├── includes/
│   ├── class-allpoint-command-integration.php # Task 1 (TOP PRIORITY)
│   ├── class-environment-manager.php     # Task 1 (TOP PRIORITY)
│   ├── class-order-notifications.php     # Task 1 (TOP PRIORITY)
│   ├── class-tax-calculator.php          # Task 2
│   └── class-email-customizer.php        # Task 3
├── templates/
│   ├── checkout/
│   │   └── recurring-order-notice.php    # Task 1 (TOP PRIORITY)
│   └── emails/
│       ├── recurring-order-notification.php # Task 1 (TOP PRIORITY)
│       ├── customer-processing-order.php # Task 3
│       └── admin-new-order.php           # Task 3
├── assets/
│   ├── css/
│   └── js/
└── apw-main-plugin.php
```

### WordPress/WooCommerce Hooks to Utilize

**Task 1 - Allpoint Command Integration (TOP PRIORITY)**:

- `woocommerce_thankyou` - Display registration instructions
- `woocommerce_order_status_processing` - Trigger API posts and emails
- `wp_mail` - Email notifications
- HTTP POST APIs for data transmission

**Task 2 - Tax Calculation**:

- `woocommerce_calculated_total`
- `woocommerce_calculate_totals`
- `woocommerce_cart_calculate_totals`

**Task 3 - Email Formatting**:

- `woocommerce_email_order_meta`
- `woocommerce_email_customer_details`
- `woocommerce_email_styles`

### Data Storage Requirements

**Custom Order Meta Fields**:

- `_apw_carrier_type` - Store selected carrier type
- `_apw_billing_preference` - Store billing preference
- `_apw_contains_recurring_products` - Boolean flag for Allpoint Command integration
- `_apw_allpoint_command_token` - Generated token for registration
- `_apw_api_post_status` - Track API posting success/failure

### Product Classification

**Required Product Meta**:

- Identify products with "recurring" tag for API integration
- Identify products with "rental" tag for document attachment
- Identify wireless devices vs accessories for email content
- Ensure clear distinction for integration and email logic

### Security Considerations

- Sanitize all input data
- Use nonces for form submissions
- Validate user permissions for admin functions
- Escape output data properly

### Performance Considerations

- Cache expensive calculations where possible
- Minimize database queries
- Use transients for temporary data storage
- Optimize email template loading

### Environment Considerations

- Test on staging environment first
- Backup database before deployment
- Monitor error logs during initial rollout
- Have rollback plan ready

### Documentation Updates

- Update user documentation for new features
- Document new email template variables
- Create troubleshooting guide for common issues
- Update admin interface help text

*This document serves as the complete development roadmap for the Allpoint Wireless WooCommerce plugin improvements.
Task 1 (Allpoint Command Integration) is the top priority and should be implemented first, followed by tax calculation
fixes, then email formatting improvements.*