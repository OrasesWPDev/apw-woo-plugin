# CLAUDE.md - Specification Development Guide

This file provides guidance to Claude Code when working with the APW WooCommerce Plugin refactor specifications.

## Development Constraints

### File Analysis Limits
- **CRITICAL**: Only read 2 files at a time maximum from the spec directory
- Focus on specific phase requirements rather than comprehensive overview
- Use the navigation guide in README.md to determine which 2 files to read per phase

### Global Memory Adherence
- **File Size Limit**: Keep all files under 600 lines of code
- **Development Principles**: Adhere to DRY (Don't Repeat Yourself) and KISS (Keep It Simple, Stupid)
- **WordPress Standards**: Follow WordPress coding standards and conventions

### Local Memory Requirements
- **Testing First**: Always start MySQL service and run test setup before any development
- **Phase-based Approach**: Complete each phase fully before moving to next
- **2-File Focus**: Never read more than 2 specification files during implementation

## Local Testing Setup (MANDATORY)

### Prerequisites Before Any Development
```bash
# 1. Start MySQL service (REQUIRED)
brew services start mysql

# 2. Complete WordPress test suite setup
./bin/setup-tests.sh

# 3. Verify testing environment works
composer run test
```

### Phase-Based Testing Commands

**Phase 1 - Critical Payment Processing (95% coverage required)**
```bash
composer run test:phase1     # Must pass before Phase 2
composer run test:payment    # Specific payment bug testing
```

**Phase 2 - Service Consolidation (90% coverage required)**
```bash
composer run test:phase2     # Must pass before Phase 3  
composer run test:customer   # Customer/VIP functionality
```

**Phase 3 - Code Optimization (maintain coverage)**
```bash
composer run test:phase3     # Final optimization testing
composer run test:all        # Complete validation
```

**Quality Gates (Run Before Each Commit)**
```bash
composer run lint            # WordPress coding standards
composer run analyze         # PHPStan static analysis
composer run quality         # All quality checks combined
```

## Specification Navigation Guide

### Phase 1 Implementation (Week 1)
**Read ONLY these 2 files:**
1. `3_payment_service_refactor.md` - Critical payment surcharge bug fix
2. `8_testing_strategy.md` - Testing requirements and validation

**Focus**: Fix payment calculation showing $17.14 instead of $15.64 with VIP discounts

### Phase 2 Implementation (Week 2-3)  
**Read ONLY these 2 files:**
1. `4_customer_vip_service.md` - Customer management consolidation
2. `5_code_organization.md` - Directory structure and file organization

**Focus**: Consolidate 10 function files (8,500 lines) into 4 service classes (4,675 lines)

### Phase 3 Implementation (Week 4-5)
**Read ONLY these 2 files:**
1. `6_code_reduction_plan.md` - Line reduction targets and strategies
2. `7_security_standards.md` - WordPress security best practices

**Focus**: Achieve 30-40% code reduction (20,539 → 12,323-14,377 lines)

## Critical Development Rules

### Code Constraints
- **File Size**: Maximum 600 lines per file
- **DRY Principle**: Eliminate duplicate code and consolidate similar functions
- **KISS Principle**: Prefer simple WordPress-native solutions over complex patterns
- **WordPress Standards**: Use WordPress coding standards, security practices, and conventions

### Testing Requirements
- **Mandatory Gates**: Each phase MUST pass all tests before proceeding
- **No Regressions**: Previous phase tests must continue passing
- **Critical Verification**: Product #80, Qty 5 must show $15.64 surcharge (not $17.14)

### Development Workflow
1. **Start**: Ensure MySQL running and tests passing
2. **Read**: Only 2 specification files per phase
3. **Implement**: Follow phase-specific requirements
4. **Test**: Run phase-specific test suite
5. **Validate**: Run quality checks before commit
6. **Progress**: Only move to next phase when current phase tests pass

## File Reading Strategy

### Do Not Read Multiple Specs
- **Avoid**: Reading README.md + multiple detailed specs simultaneously
- **Instead**: Use README.md navigation guide to identify which 2 files to read
- **Focus**: Deep dive into 2 relevant files rather than surface-level many files

### Recommended Reading Patterns
**For Payment Bug Fix**: Read `3_payment_service_refactor.md` first, then `8_testing_strategy.md`
**For Service Consolidation**: Read `4_customer_vip_service.md` first, then `5_code_organization.md`  
**For Code Optimization**: Read `6_code_reduction_plan.md` first, then `7_security_standards.md`

## Critical Success Metrics

### Must-Pass Criteria (Blocking)
- [ ] **Payment Bug Fixed**: Product #80, Qty 5 shows $15.64 surcharge
- [ ] **Testing Gates**: Phase-specific tests pass before progression
- [ ] **Code Standards**: WordPress coding standards compliance
- [ ] **File Limits**: All files under 600 lines
- [ ] **DRY/KISS**: Simple, non-repetitive code following WordPress patterns

### Quality Targets
- [ ] **30-40% Code Reduction**: From 20,539 to 12,323-14,377 lines
- [ ] **Service Consolidation**: 10 function files → 4 service classes
- [ ] **Performance**: No degradation in page load times
- [ ] **Maintainability**: Easy to add new features and fix issues

## Emergency Procedures

### If Tests Fail
1. **Stop Development**: Do not proceed to next phase
2. **Debug**: Use `composer run test:critical` for payment-specific issues
3. **Rollback**: Revert to last working state if needed
4. **Validate**: Ensure MySQL service is running

### If File Size Exceeds 600 Lines
1. **Split Functions**: Extract related functions into separate files
2. **Consolidate**: Remove duplicate code and verbose comments
3. **Simplify**: Apply KISS principle to reduce complexity

### If Reading Too Many Files
1. **Stop**: Close all specification files
2. **Reset**: Use README.md navigation guide only
3. **Focus**: Open only the 2 files specified for current phase
4. **Implement**: Work with deep understanding of those 2 files only

This specification development guide ensures focused, test-driven development following WordPress standards while maintaining the critical payment bug fix priority.