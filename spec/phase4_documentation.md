# Phase 4: Documentation Implementation Specification

## Overview
Create comprehensive, maintainable documentation covering architecture, API references, user guides, and development workflows to ensure long-term maintainability and efficient onboarding.

## Documentation Objectives

### 4.1 Documentation Goals
- **Complete Coverage**: Document all functionality and architecture
- **Developer-Friendly**: Clear API documentation and examples
- **User-Focused**: Easy-to-follow user guides
- **Maintainable**: Documentation that stays current with code changes
- **Searchable**: Well-organized and easily discoverable information

## Documentation Architecture

### 4.2 Documentation Structure
```
docs/
├── README.md                           # Project overview
├── CHANGELOG.md                        # Version history
├── CONTRIBUTING.md                     # Contribution guidelines
├── architecture/
│   ├── overview.md                     # System architecture
│   ├── services.md                     # Service layer documentation
│   ├── integrations.md                 # Third-party integrations
│   ├── database-schema.md              # Database structure
│   └── security.md                     # Security implementation
├── api/
│   ├── services/                       # Service API documentation
│   │   ├── payment-service.md
│   │   ├── pricing-service.md
│   │   ├── cart-service.md
│   │   └── customer-service.md
│   ├── hooks/                          # WordPress hooks documentation
│   │   ├── actions.md
│   │   └── filters.md
│   └── endpoints/                      # REST API endpoints
│       ├── customers.md
│       └── exports.md
├── user-guide/
│   ├── installation.md                 # Installation instructions
│   ├── configuration.md                # Configuration options
│   ├── features/                       # Feature-specific guides
│   │   ├── vip-discounts.md
│   │   ├── payment-surcharges.md
│   │   ├── customer-registration.md
│   │   └── referral-exports.md
│   └── troubleshooting.md              # Common issues and solutions
├── development/
│   ├── setup.md                        # Development environment setup
│   ├── coding-standards.md             # Coding standards and guidelines
│   ├── testing.md                      # Testing procedures
│   ├── deployment.md                   # Deployment procedures
│   └── extending.md                    # Plugin extension guide
└── examples/
    ├── custom-integrations.md          # Integration examples
    ├── hooks-usage.md                  # Hook usage examples
    └── service-extensions.md           # Service extension examples
```

## API Documentation

### 4.3 Service API Documentation

#### 4.3.1 Payment Service Documentation Template
```markdown
# Payment Gateway Service API

## Overview
The Payment Gateway Service handles all payment processing, including surcharge calculations and payment method integrations.

## Class: `PaymentGatewayService`

### Namespace
`APW\WooPlugin\Services\Payment\PaymentGatewayService`

### Constructor
```php
public function __construct(
    ConfigInterface $config,
    LoggerServiceInterface $logger,
    CacheServiceInterface $cache
)
```

### Methods

#### `applySurcharge(string $payment_method): void`
Applies payment method surcharge to the current cart.

**Parameters:**
- `$payment_method` (string): The payment method ID

**Example:**
```php
$payment_service = $container->make('PaymentGatewayServiceInterface');
$payment_service->applySurcharge('intuit_qbms_credit_card');
```

**Hooks Fired:**
- `apw_woo_before_surcharge_calculation`
- `apw_woo_after_surcharge_applied`

#### `getSurchargeRate(string $payment_method): float`
Returns the surcharge rate for a specific payment method.

**Parameters:**
- `$payment_method` (string): The payment method ID

**Returns:**
- `float`: Surcharge rate as decimal (e.g., 0.03 for 3%)

**Example:**
```php
$rate = $payment_service->getSurchargeRate('intuit_qbms_credit_card');
// Returns: 0.03 (3%)
```

#### `calculateSurchargeAmount(float $subtotal, string $payment_method): float`
Calculates the surcharge amount for a given subtotal and payment method.

**Parameters:**
- `$subtotal` (float): The cart subtotal
- `$payment_method` (string): The payment method ID

**Returns:**
- `float`: Calculated surcharge amount

**Example:**
```php
$surcharge = $payment_service->calculateSurchargeAmount(100.00, 'intuit_qbms_credit_card');
// Returns: 3.00
```

## Configuration Options

### Payment Method Surcharges
```php
'payment_surcharges' => [
    'intuit_qbms_credit_card' => 0.03,  // 3%
    'stripe' => 0.029,                   // 2.9%
    'paypal' => 0.035                    // 3.5%
]
```

## Error Handling
All methods throw appropriate exceptions:
- `PaymentMethodNotSupportedException`: Invalid payment method
- `SurchargeCalculationException`: Calculation errors
- `ConfigurationException`: Missing configuration

## Testing
See: `tests/Unit/Services/PaymentGatewayServiceTest.php`
```

#### 4.3.2 Pricing Service Documentation
```markdown
# Pricing Service API

## Overview
Handles all pricing calculations including VIP discounts, dynamic pricing, and bulk discounts.

## Class: `PricingService`

### Methods

#### `applyVIPDiscounts(\WC_Cart $cart): void`
Applies VIP customer discounts to cart items.

**Parameters:**
- `$cart` (\WC_Cart): WooCommerce cart object

**VIP Discount Tiers:**
- Orders $500+: 10% discount
- Orders $300+: 8% discount  
- Orders $100+: 5% discount

**Example:**
```php
$pricing_service = $container->make('PricingServiceInterface');
$pricing_service->applyVIPDiscounts(WC()->cart);
```

#### `getCustomerVIPStatus(int $customer_id): array`
Gets VIP status and discount information for a customer.

**Returns:**
```php
[
    'is_vip' => true,
    'discount_rate' => 0.10,
    'tier' => 'gold',
    'total_spent' => 2500.00
]
```

## Configuration

### VIP Discount Thresholds
```php
'vip_discount_thresholds' => [
    500 => 0.10,  // 10% for $500+
    300 => 0.08,  // 8% for $300+
    100 => 0.05   // 5% for $100+
]
```
```

### 4.4 WordPress Hooks Documentation

#### 4.4.1 Actions Documentation
```markdown
# WordPress Actions Reference

## Cart and Pricing Actions

### `apw_woo_before_cart_calculation`
Fired before cart totals are calculated.

**Parameters:**
- `$cart` (\WC_Cart): The cart object

**Example:**
```php
add_action('apw_woo_before_cart_calculation', function($cart) {
    // Custom logic before calculation
});
```

### `apw_woo_vip_discount_applied`
Fired when VIP discount is applied to cart.

**Parameters:**
- `$discount_amount` (float): Applied discount amount
- `$customer_id` (int): Customer ID
- `$cart` (\WC_Cart): Cart object

### `apw_woo_surcharge_applied`
Fired when payment surcharge is applied.

**Parameters:**
- `$surcharge_amount` (float): Applied surcharge amount
- `$payment_method` (string): Payment method ID

## Customer Actions

### `apw_woo_customer_registered`
Fired when new customer completes registration.

**Parameters:**
- `$customer_id` (int): New customer ID
- `$registration_data` (array): Registration form data

### `apw_woo_referral_created`
Fired when customer referral is recorded.

**Parameters:**
- `$customer_id` (int): New customer ID
- `$referrer_name` (string): Referring customer name
```

#### 4.4.2 Filters Documentation
```markdown
# WordPress Filters Reference

## Pricing Filters

### `apw_woo_vip_discount_rate`
Filters the VIP discount rate for a customer.

**Parameters:**
- `$rate` (float): Default discount rate
- `$customer_id` (int): Customer ID
- `$cart_total` (float): Cart total

**Return:** `float` - Modified discount rate

**Example:**
```php
add_filter('apw_woo_vip_discount_rate', function($rate, $customer_id, $cart_total) {
    // Double VIP discount for orders over $1000
    if ($cart_total > 1000) {
        return $rate * 2;
    }
    return $rate;
}, 10, 3);
```

### `apw_woo_payment_surcharge_rate`
Filters payment method surcharge rate.

**Parameters:**
- `$rate` (float): Default surcharge rate
- `$payment_method` (string): Payment method ID
- `$cart_total` (float): Cart total

**Return:** `float` - Modified surcharge rate

## Registration Filters

### `apw_woo_registration_fields`
Filters custom registration fields.

**Parameters:**
- `$fields` (array): Field configuration array

**Return:** `array` - Modified fields array

**Example:**
```php
add_filter('apw_woo_registration_fields', function($fields) {
    $fields['custom_field'] = [
        'type' => 'text',
        'label' => 'Custom Field',
        'required' => true
    ];
    return $fields;
});
```
```

## User Documentation

### 4.5 User Guide Templates

#### 4.5.1 VIP Discounts User Guide
```markdown
# VIP Customer Discounts

## Overview
The APW WooCommerce Plugin automatically applies tiered discounts to VIP customers based on their order total.

## Discount Tiers

### Automatic VIP Qualification
Customers automatically qualify for VIP discounts based on order total:

- **Bronze VIP** (Orders $100-$299): 5% discount
- **Silver VIP** (Orders $300-$499): 8% discount  
- **Gold VIP** (Orders $500+): 10% discount

### Manual VIP Assignment
Administrators can manually assign VIP status:

1. Go to **Users > All Users**
2. Edit the customer's profile
3. Check **VIP Customer** checkbox
4. Optionally set **Custom VIP Discount Rate**
5. Save changes

## How Discounts Apply

### Cart Display
- VIP discounts appear as line items in the cart
- Discount amount shows before payment surcharges
- Total savings displayed prominently

### Checkout Process
- Discounts automatically apply during checkout
- No coupon codes required
- Works with existing WooCommerce coupons

## Troubleshooting

### Discount Not Applying
1. Verify customer has VIP status
2. Check order total meets minimum threshold
3. Ensure no conflicting discounts
4. Clear cart and re-add items

### Custom Discount Rates
- Admin-set custom rates override automatic tiers
- Custom rates apply regardless of order total
- Set to 0 to disable VIP discounts for specific customers
```

#### 4.5.2 Payment Surcharges Guide
```markdown
# Payment Method Surcharges

## Overview
Certain payment methods incur processing fees that are passed to customers as surcharges.

## Surcharge Rates

### Default Rates
- **Credit Card (Intuit)**: 3.0%
- **PayPal**: 3.5%
- **Stripe**: 2.9%

### How Surcharges Work
1. Customer selects payment method at checkout
2. Surcharge automatically calculates based on cart total
3. Surcharge appears as separate line item
4. Final total includes surcharge amount

## Configuration

### Admin Settings
Navigate to **WooCommerce > Settings > APW Settings**:

1. **Enable Surcharges**: Toggle surcharge functionality
2. **Payment Method Rates**: Set individual rates per method
3. **Minimum Order**: Set minimum order for surcharges
4. **Display Settings**: Configure how surcharges appear

### Exemptions
- VIP customers may receive surcharge exemptions
- Bulk orders over threshold may be exempt
- Administrative orders can bypass surcharges

## Legal Compliance
- Surcharges clearly disclosed before payment
- Complies with payment processor guidelines
- Configurable per local regulations
```

### 4.6 Development Documentation

#### 4.6.1 Development Setup Guide
```markdown
# Development Environment Setup

## Prerequisites
- PHP 8.1+
- WordPress 6.0+
- WooCommerce 7.0+
- Composer
- Node.js 16+

## Installation

### 1. Clone Repository
```bash
git clone https://github.com/your-org/apw-woo-plugin.git
cd apw-woo-plugin
```

### 2. Install Dependencies
```bash
# PHP dependencies
composer install

# JavaScript dependencies  
npm install
```

### 3. Environment Configuration
```bash
# Copy environment template
cp .env.example .env

# Edit configuration
vim .env
```

### 4. Database Setup
```bash
# Run migrations
php bin/setup-database.php

# Seed test data
php bin/seed-test-data.php
```

## Development Workflow

### Code Standards
- Follow WordPress Coding Standards
- Use PSR-4 autoloading
- Write PHPDoc for all methods
- Include unit tests for new features

### Testing
```bash
# Run all tests
composer test

# Run specific test suite
composer test:unit
composer test:integration

# Generate coverage report
composer test:coverage
```

### Build Process
```bash
# Development build
npm run dev

# Production build
npm run build

# Watch for changes
npm run watch
```
```

## Documentation Automation

### 4.7 Auto-Generated Documentation

#### 4.7.1 PHPDoc Generation
```php
<?php
namespace APW\WooPlugin\Documentation;

class DocGenerator {
    public function generateApiDocs(): void {
        $config = [
            'source' => 'src/',
            'destination' => 'docs/api/',
            'template' => 'templates/api/',
            'exclude' => ['vendor/', 'tests/']
        ];
        
        $this->runPhpDocumentor($config);
    }
    
    public function generateHooksDocs(): void {
        $hook_scanner = new HookScanner();
        $hooks = $hook_scanner->scanForHooks('src/');
        
        $this->generateHooksReference($hooks);
    }
    
    private function generateHooksReference(array $hooks): void {
        $actions = array_filter($hooks, fn($hook) => $hook['type'] === 'action');
        $filters = array_filter($hooks, fn($hook) => $hook['type'] === 'filter');
        
        $this->writeActionsDoc($actions);
        $this->writeFiltersDoc($filters);
    }
}
```

#### 4.7.2 Automated Documentation Updates
```bash
#!/bin/bash
# scripts/update-docs.sh

echo "=== Updating Documentation ==="

# Generate API documentation
echo "Generating API docs..."
vendor/bin/phpdoc run

# Update hooks documentation
echo "Updating hooks documentation..."
php bin/generate-hooks-docs.php

# Update changelog
echo "Updating changelog..."
php bin/update-changelog.php

# Validate documentation
echo "Validating documentation..."
node scripts/validate-docs.js

echo "Documentation update complete!"
```

## Documentation Maintenance

### 4.8 Documentation Review Process

#### 4.8.1 Review Checklist
- [ ] **Accuracy**: All code examples work correctly
- [ ] **Completeness**: All public APIs documented
- [ ] **Clarity**: Instructions are clear and unambiguous
- [ ] **Examples**: Practical examples provided
- [ ] **Links**: All internal links functional
- [ ] **Screenshots**: UI screenshots current and accurate

#### 4.8.2 Automated Validation
```javascript
// scripts/validate-docs.js
const fs = require('fs');
const path = require('path');

class DocValidator {
    validateCodeBlocks() {
        // Extract and validate PHP code blocks
        // Check syntax and basic functionality
    }
    
    validateLinks() {
        // Check all internal links resolve
        // Verify external links are accessible
    }
    
    validateScreenshots() {
        // Check screenshot files exist
        // Verify alt text present
    }
}
```

## Success Criteria

### 4.9 Documentation Quality Metrics
- [ ] **API Coverage**: 100% of public methods documented
- [ ] **User Guide Coverage**: All features have user documentation
- [ ] **Code Examples**: Working examples for all APIs
- [ ] **Installation Guide**: Complete setup instructions
- [ ] **Troubleshooting**: Common issues documented with solutions
- [ ] **Search Functionality**: Documentation is searchable
- [ ] **Mobile Friendly**: Documentation readable on all devices

### 4.10 Maintenance Requirements
- [ ] **Regular Updates**: Documentation updated with each release
- [ ] **Accuracy Checks**: Quarterly review for accuracy
- [ ] **User Feedback**: Documentation feedback mechanism in place
- [ ] **Version Control**: Documentation versioned with code

## Next Steps
Upon completion:
1. Proceed to Phase 5: Plugin Extensibility
2. Implement documentation review process
3. Set up automated documentation generation
4. Create documentation maintenance schedule