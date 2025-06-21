# APW WooCommerce Plugin Refactor Specifications

## Overview

This directory contains comprehensive specifications for refactoring the APW WooCommerce Plugin from ~20,539 lines to 12,323-14,377 lines (30-40% reduction) while fixing critical payment processing bugs and improving code organization.

## Critical Business Context

**Payment Bug**: Credit card surcharge shows $17.14 instead of $15.64 when VIP discounts are applied. This is a revenue-impacting bug that must be fixed first.

**Goal**: Reduce code bloat, fix payment issues, consolidate scattered functionality, and improve maintainability while preserving all existing features.

## Specification Files (Sequential Order)

### Phase 0: Planning & Overview
- **[0_refactor_phases.md](0_refactor_phases.md)** - Master implementation plan with 3 phases, mandatory testing gates, and timeline

### Phase 1: Foundation & Requirements  
- **[1_core_requirements.md](1_core_requirements.md)** - 5 core business requirements, success criteria, and constraints
- **[2_architecture_decisions.md](2_architecture_decisions.md)** - WordPress-native patterns, rejected enterprise anti-patterns

### Phase 2: Critical Implementation
- **[3_payment_service_refactor.md](3_payment_service_refactor.md)** - CRITICAL: Fix payment surcharge bug, consolidate payment logic
- **[4_customer_vip_service.md](4_customer_vip_service.md)** - Consolidate customer management, VIP status, referral tracking

### Phase 3: Organization & Quality
- **[5_code_organization.md](5_code_organization.md)** - Directory structure, file organization, autoloading strategy
- **[6_code_reduction_plan.md](6_code_reduction_plan.md)** - Specific line reduction targets and consolidation strategies
- **[7_security_standards.md](7_security_standards.md)** - WordPress security best practices, input validation, output escaping
- **[8_testing_strategy.md](8_testing_strategy.md)** - Testing approach, mandatory gates, validation procedures

## Three-Phase Refactor Plan

### Phase 1: Critical Bug Fixes (Week 1)
**Goal**: Fix payment processing issues that affect revenue

#### Critical Payment Bug Fix
- **Problem**: Credit card surcharge calculation incorrect with VIP discounts  
- **Expected**: Product #80, Qty 5 → $15.64 surcharge (currently shows $17.14)
- **Root Cause**: VIP discount timing and fee collection logic issues
- **Files**: `includes/apw-woo-intuit-payment-functions.php`

#### Success Criteria
```bash
# MANDATORY: These tests must pass before Phase 2
composer run test:phase1      # All Phase 1 payment tests pass
composer run test:payment     # Specific payment processing tests pass  
composer run lint             # WordPress coding standards
composer run analyze          # No static analysis errors

# CRITICAL VERIFICATION: Product #80, Qty 5 scenario shows $15.64 surcharge
```

#### Deliverables
- Fixed payment calculation logic (remove existence check, fix fee collection)
- Payment surcharge calculated after VIP discounts (priority 20 vs 10)
- Comprehensive debug logging for payment issues
- Test cases documenting correct surcharge behavior

### Phase 2: Code Consolidation (Week 2-3)
**Goal**: Consolidate scattered functionality into service classes

#### Service Consolidation
- **Before**: 10 function files (8,500 lines) scattered across codebase
- **After**: 4 service classes (4,675 lines) with clear responsibilities

#### New Service Classes
1. **Payment Service** (200 lines) - Payment processing, surcharge, billing
2. **Customer Service** (300 lines) - Registration, VIP status, referrals  
3. **Product Service** (250 lines) - Pricing, add-ons, cross-sells
4. **Cart Service** (200 lines) - Cart indicators, shipping

#### Success Criteria
```bash
# MANDATORY: These tests must pass before Phase 3
composer run test:phase2      # All Phase 2 service tests pass
composer run test:customer    # Customer service functionality tests
composer run test:phase1      # No regressions from Phase 1
composer run lint             # Code standards maintained
composer run analyze          # No new static analysis issues
```

#### Deliverables
- 4 new service classes with consolidated functionality
- 45% reduction in function files (8,500 → 4,675 lines)
- Updated plugin initialization to load services
- Migration documentation

### Phase 3: Optimization & Cleanup (Week 4-5)  
**Goal**: Performance optimization and final code reduction

#### Optimization Areas
- **Template Cleanup**: 20% reduction (4,200 → 3,360 lines)
- **Main Plugin File**: 40% reduction (1,086 → 650 lines)  
- **Debug Output**: Minimize verbose debug sections
- **Asset Loading**: Context-specific loading

#### Success Criteria
```bash
# MANDATORY: Final validation of complete refactor
composer run test:all         # ALL tests pass
composer run test:phase3      # Phase 3 optimization tests pass
composer run test:phase2      # No regressions from Phase 2
composer run test:phase1      # No regressions from Phase 1
phpunit --coverage-html tests/coverage  # Generate coverage report
```

#### Deliverables
- 30-40% overall code reduction achieved
- Performance improvements documented
- Optimized templates and asset loading
- Final architecture documentation

## Code Reduction Targets

### Quantitative Goals
- **Total Reduction**: 20,539 → 12,323-14,377 lines (30-40%)
- **Main Plugin File**: 1,086 → 650 lines (40% reduction)
- **Function Files**: 8,500 → 4,675 lines (45% reduction) 
- **Class Files**: 6,800 → 5,100 lines (25% reduction)
- **Template Files**: 4,200 → 3,360 lines (20% reduction)

### Reduction Strategies
- **Eliminate Duplicate Validation**: 15 functions validate products differently
- **Consolidate Database Queries**: Multiple functions query same data
- **Remove Verbose Debug Output**: 30-50 lines of debug HTML per template
- **Simplify Autoload Logic**: 100+ lines → 20 lines
- **Optimize Hook Registration**: Single initialization vs scattered registrations

## WordPress-Native Architecture

### Key Principles
- **No Enterprise Patterns**: Reject dependency injection, service containers, event systems
- **WordPress APIs Only**: Use `WP_Query`, `get_user_meta`, WooCommerce objects
- **Hook System Integration**: Use `add_action`/`add_filter` with proper priorities
- **WordPress Security**: Nonces, capability checks, input sanitization
- **Template Hierarchy**: Follow WordPress template loading conventions

### Service Organization
```
includes/
├── services/
│   ├── class-apw-woo-payment-service.php
│   ├── class-apw-woo-customer-service.php  
│   ├── class-apw-woo-product-service.php
│   └── class-apw-woo-cart-service.php
├── integrations/
│   ├── class-apw-woo-intuit-integration.php
│   └── class-apw-woo-acf-integration.php
└── functions/
    ├── template-functions.php
    └── utility-functions.php
```

## Critical Success Metrics

### Must-Pass Criteria (Blocking)
- [ ] **Payment Bug Fixed**: Product #80, Qty 5 shows $15.64 surcharge
- [ ] **No Regressions**: All existing functionality preserved
- [ ] **Code Standards**: WordPress coding standards compliance
- [ ] **Security Standards**: Input validation, output escaping, capability checks
- [ ] **Performance**: No degradation in page load times

### Quality Improvements
- [ ] **30-40% Code Reduction**: Quantified line reduction achieved
- [ ] **Consolidated Services**: Clear separation of concerns
- [ ] **Improved Maintainability**: Easy to add new features
- [ ] **WordPress Compliance**: Native patterns throughout

## Testing Strategy

### Mandatory Testing Gates
**No phase progression without ALL tests passing:**

#### Phase 1 Gate
```bash
composer run test:phase1     # Critical payment processing tests
composer run test:payment    # Specific payment scenarios  
composer run lint           # Code quality standards
composer run analyze        # Static analysis
```

#### Phase 2 Gate  
```bash
composer run test:phase2     # Service consolidation tests
composer run test:customer   # Customer service tests
composer run test:phase1     # No Phase 1 regressions
```

#### Phase 3 Gate
```bash
composer run test:all        # Complete test suite
composer run test:phase3     # Optimization tests
# All previous phase tests must continue passing
```

### Test Environment Setup

**CRITICAL: Complete setup before starting any refactor work**

#### Prerequisites Required
- **Composer**: Testing packages installed (56 packages including PHPUnit, PHPStan, PHPCS)
- **Subversion**: Required for WordPress test suite (`brew install svn`)
- **MySQL**: Required for test database (`brew install mysql`)

#### Before Starting the Refactor

1. **Start MySQL Service**
```bash
brew services start mysql
```

2. **Complete WordPress Test Suite Setup**
```bash
./bin/setup-tests.sh
```

3. **Verify Testing Environment**
```bash
composer run test
```

#### Available Testing Commands

**Phase-Based Testing (Required for Refactor)**
```bash
composer run test:phase1     # Test Phase 1 (Critical Payment Processing)
composer run test:phase2     # Test Phase 2 (Service Consolidation)  
composer run test:phase3     # Test Phase 3 (Code Optimization)
```

**Feature-Specific Testing**
```bash
composer run test:payment    # Test payment processing specifically
composer run test:customer   # Test customer functionality
composer run test:product    # Test product functionality
composer run test:cart       # Test cart functionality
```

**Code Quality Checks**
```bash
composer run lint            # WordPress/WooCommerce coding standards
composer run lint:fix        # Auto-fix coding standard violations
composer run analyze         # PHPStan static analysis (level 5)
composer run analyze:strict  # PHPStan strict analysis (level 8)
composer run mess-detect     # PHP Mess Detector
composer run quality         # Run all quality checks
```

**Comprehensive Testing**
```bash
composer run test:all        # All tests + quality checks
composer run test:critical   # Payment + Phase 1 tests only
composer run test:coverage   # Generate HTML coverage report
```

#### Testing Directory Structure
```
tests/
├── phase1/          # Phase 1 critical fixes tests
├── phase2/          # Phase 2 consolidation tests
├── phase3/          # Phase 3 optimization tests
├── integration/     # Cross-feature integration tests
├── utilities/       # Test utilities and helpers
├── fixtures/        # Test data and fixtures
├── stubs/          # WordPress/WooCommerce stubs for PHPStan
├── coverage/       # HTML coverage reports (generated)
└── bootstrap.php   # PHPUnit bootstrap file
```

#### Configuration Files Ready
- **composer.json**: All testing dependencies installed
- **phpunit.xml**: Phase-based test organization with coverage
- **phpcs.xml**: WordPress/WooCommerce coding standards
- **phpstan.neon**: Static analysis with WordPress/WooCommerce stubs
- **bin/setup-tests.sh**: Complete environment setup script
- **bin/install-wp-tests.sh**: WordPress test suite installer

#### Development Workflow
1. **Before Each Phase**: Run baseline tests
2. **During Development**: Use feature-specific tests  
3. **After Each Phase**: Run phase completion tests
4. **Before Committing**: Run full quality checks

**📋 See [TESTING_SETUP.md](TESTING_SETUP.md) for complete testing documentation**

## Risk Mitigation

### Backup Strategy
- **Git Branches**: Phase-specific branches for rollback capability
- **Feature Flags**: Allow individual feature rollback
- **Progressive Testing**: Test incrementally after each major change

### Rollback Plans
- **Phase 1**: Revert payment function file to backup if issues
- **Phase 2**: Keep old function files until services verified  
- **Phase 3**: Feature flags for optimization features

## Senior Developer Review Points

### Architecture Review
1. **WordPress Compliance**: Does the architecture follow WordPress conventions?
2. **Service Design**: Are the 4 service classes appropriately scoped?
3. **Performance Impact**: Will the changes improve or maintain performance?
4. **Security Considerations**: Are WordPress security best practices followed?

### Implementation Review  
1. **Payment Bug Fix**: Is the surcharge calculation logic correct?
2. **Code Reduction**: Are the line reduction targets realistic and beneficial?
3. **Testing Strategy**: Is the testing approach comprehensive enough?
4. **Migration Path**: Is the migration from current to new structure safe?

### Business Impact Review
1. **Revenue Protection**: Will the payment bug fix resolve the $17.14 vs $15.64 issue?
2. **Feature Preservation**: Are all existing features maintained?
3. **Maintainability**: Will the new structure be easier to maintain and extend?
4. **Timeline**: Is the 5-week timeline realistic for the scope?

## Dependencies & Prerequisites

### Technical Requirements
- PHP 7.2+
- WordPress 5.3+ 
- WooCommerce 5.0+
- MySQL service running (for testing)

### Testing Infrastructure
- Composer for dependency management
- PHPUnit for automated testing
- WordPress test suite installed
- WooCommerce testing framework

### Development Tools
- WordPress coding standards (PHPCS)
- PHPStan for static analysis
- Git for version control and branching

## Success Validation

Upon completion, the refactored plugin should demonstrate:

1. **Critical Bug Fixed**: Payment surcharge calculates correctly with VIP discounts
2. **Code Reduced**: 30-40% line reduction achieved with maintained functionality  
3. **Better Organization**: Clear service-based architecture with WordPress patterns
4. **Improved Performance**: No degradation, potential improvements from optimization
5. **Enhanced Maintainability**: Easier to add features and fix issues
6. **WordPress Compliance**: Full adherence to WordPress plugin standards

This specification provides a complete roadmap for the APW WooCommerce Plugin refactor, balancing critical bug fixes, code improvement, and risk mitigation while maintaining business functionality.