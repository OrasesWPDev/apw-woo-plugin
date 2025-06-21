# APW WooCommerce Plugin - Testing Environment Setup

## Prerequisites Installed ✅
- **Composer**: Testing packages installed (56 packages including PHPUnit, PHPStan, PHPCS)
- **Subversion**: Installed via Homebrew for WordPress test suite
- **MySQL**: Installed via Homebrew for test database

## Before Starting the Refactor

### 1. Start MySQL Service
```bash
brew services start mysql
```

### 2. Complete WordPress Test Suite Setup
```bash
./bin/setup-tests.sh
```

### 3. Verify Testing Environment
```bash
composer run test
```

## Testing Commands Available

### Phase-Based Testing (Required for Refactor)
```bash
composer run test:phase1     # Test Phase 1 (Critical Payment Processing)
composer run test:phase2     # Test Phase 2 (Service Consolidation)  
composer run test:phase3     # Test Phase 3 (Code Optimization)
```

### Feature-Specific Testing
```bash
composer run test:payment    # Test payment processing specifically
composer run test:customer   # Test customer functionality
composer run test:product    # Test product functionality
composer run test:cart       # Test cart functionality
```

### Code Quality Checks
```bash
composer run lint            # WordPress/WooCommerce coding standards
composer run lint:fix        # Auto-fix coding standard violations
composer run analyze         # PHPStan static analysis (level 5)
composer run analyze:strict  # PHPStan strict analysis (level 8)
composer run mess-detect     # PHP Mess Detector
composer run quality         # Run all quality checks
```

### Comprehensive Testing
```bash
composer run test:all        # All tests + quality checks
composer run test:critical   # Payment + Phase 1 tests only
composer run test:coverage   # Generate HTML coverage report
```

## Mandatory Testing Gates (Per Spec Files)

Each specification file requires testing completion before moving to the next phase:

1. **Phase 1**: 95% unit test coverage for payment processing
2. **Phase 2**: 90% coverage for customer/VIP services  
3. **Phase 3**: Maintain coverage while reducing code

## Testing Directory Structure

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

## Configuration Files Ready

- **composer.json**: All testing dependencies installed
- **phpunit.xml**: Phase-based test organization with coverage
- **phpcs.xml**: WordPress/WooCommerce coding standards
- **phpstan.neon**: Static analysis with WordPress/WooCommerce stubs
- **bin/setup-tests.sh**: Complete environment setup script
- **bin/install-wp-tests.sh**: WordPress test suite installer

## Critical Payment Bug Testing

For the credit card surcharge fix (Product #80 scenario):
```bash
# Specific test for the $17.14 vs $15.64 surcharge bug
composer run test:payment
```

## Development Workflow

1. **Before Each Phase**: Run baseline tests
2. **During Development**: Use feature-specific tests  
3. **After Each Phase**: Run phase completion tests
4. **Before Committing**: Run full quality checks

## Notes

- MySQL service must be running for integration tests
- WordPress test suite uses temporary database `wordpress_test`
- Coverage reports generated in `tests/coverage/`
- All quality tools configured for WordPress/WooCommerce standards
- PHPStan uses custom stubs for WordPress/WooCommerce functions