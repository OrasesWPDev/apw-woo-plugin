# Phase 1: Directory Structure Migration Specification

## Overview
Migrate the APW WooCommerce Plugin from the incorrectly nested directory structure to the proper location and establish the new repository foundation.

## Current State
- **Incorrect Path**: `/Users/chadmacbook/projects/apw-new-woo/apw-new-woo`
- **Desired Path**: `/Users/chadmacbook/projects/apw-new-woo`
- **Status**: Empty repository with only `.git` folder

## Migration Tasks

### 1.1 Directory Structure Correction

#### Acceptance Criteria
- [ ] Working directory is `/Users/chadmacbook/projects/apw-new-woo`
- [ ] No nested `apw-new-woo` subdirectory exists
- [ ] Git repository is properly initialized and functional
- [ ] All original plugin files are copied to new location

#### Implementation Steps

1. **Move to Parent Directory**
   ```bash
   cd /Users/chadmacbook/projects/apw-new-woo
   ```

2. **Copy Original Plugin Files**
   ```bash
   cp -r ../apw-woo-plugin/* .
   cp -r ../apw-woo-plugin/.* . 2>/dev/null || true
   ```

3. **Verify Git Repository Status**
   ```bash
   git status
   git remote -v
   ```

4. **Remove Nested Directory if Exists**
   ```bash
   rm -rf apw-new-woo/
   ```

### 1.2 Repository Initialization

#### Git Configuration Verification
```bash
# Verify remote repository
git remote get-url origin
# Expected: https://github.com/chad-orases/apw-new-woo

# Verify branch configuration
git branch -a
# Expected: main branch exists
```

#### Initial File Structure Validation
```
/Users/chadmacbook/projects/apw-new-woo/
├── apw-woo-plugin.php          # Main plugin file
├── README.md                   # Documentation
├── CLAUDE.md                   # Development notes
├── assets/                     # CSS, JS, images
│   ├── css/
│   ├── js/
│   └── images/
├── includes/                   # PHP classes and functions
│   ├── class-*.php
│   ├── apw-woo-*-functions.php
│   ├── template/
│   └── vendor/
├── templates/                  # Template files
│   ├── partials/
│   └── woocommerce/
└── spec/                       # Project specifications (new)
```

### 1.3 Initial Commit and Push

#### Pre-commit Checklist
- [ ] All original files copied successfully
- [ ] No sensitive data (tokens, credentials) in code
- [ ] Directory structure is correct
- [ ] Git repository is functional

#### Commit Process
```bash
# Stage all files
git add .

# Create initial commit
git commit -m "Initial commit: Copy original APW WooCommerce Plugin for refactoring

- Migrated from apw-woo-plugin repository
- Contains original v1.23.19 codebase
- Ready for comprehensive refactoring
- All original functionality preserved

🤖 Generated with Claude Code

Co-Authored-By: Claude <noreply@anthropic.com>"

# Push to remote repository
git push -u origin main
```

### 1.4 Verification Steps

#### Directory Structure Verification
```bash
# Verify we're in correct directory
pwd
# Expected: /Users/chadmacbook/projects/apw-new-woo

# Check file count matches original
find . -name "*.php" | wc -l
# Compare with original repository

# Verify no nested directories
ls -la | grep "apw-new-woo"
# Should return no results
```

#### Git Repository Verification
```bash
# Verify commit was successful
git log --oneline -1

# Verify push was successful
git status
# Expected: "Your branch is up to date with 'origin/main'"

# Verify remote tracking
git branch -vv
```

#### Functionality Verification
```bash
# Verify main plugin file exists and is readable
head -20 apw-woo-plugin.php

# Verify includes directory structure
ls -la includes/

# Verify assets are present
ls -la assets/css/ assets/js/
```

## Success Criteria

### Primary Objectives
1. **Correct Directory Structure**: Working directory is `/Users/chadmacbook/projects/apw-new-woo`
2. **Complete File Migration**: All original plugin files copied successfully
3. **Functional Git Repository**: Can commit and push changes
4. **Clean Repository State**: No unnecessary nested directories
5. **Preservation of Original Code**: All functionality intact for refactoring

### Validation Commands
```bash
# Final validation script
#!/bin/bash
echo "=== Directory Structure Validation ==="
pwd
echo ""

echo "=== File Count Verification ==="
echo "PHP files: $(find . -name "*.php" | wc -l)"
echo "CSS files: $(find . -name "*.css" | wc -l)"
echo "JS files: $(find . -name "*.js" | wc -l)"
echo ""

echo "=== Git Status ==="
git status --porcelain
echo ""

echo "=== Remote Configuration ==="
git remote -v
echo ""

echo "=== Latest Commit ==="
git log --oneline -1
```

## Risk Mitigation

### Potential Issues
1. **File Permission Problems**: Ensure proper read/write permissions
2. **Hidden File Loss**: Use appropriate copy commands for dotfiles
3. **Git History Loss**: Verify repository integrity after migration
4. **Path Dependencies**: Check for hardcoded paths in code

### Rollback Plan
If migration fails:
1. Restore from original `apw-woo-plugin` directory
2. Re-create repository if Git issues occur
3. Manual file-by-file copy if bulk copy fails

## Next Steps
Upon successful completion:
1. Begin Phase 2: Architecture Analysis
2. Document any discovered issues during migration
3. Update project tracking with completed tasks
4. Proceed with codebase analysis and refactoring planning