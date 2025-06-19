# Phase 3: Code Cleanup Implementation Specification

## Overview
Eliminate code duplication, consolidate repeated patterns, remove bloated functions, and simplify complex conditional logic throughout the APW WooCommerce Plugin codebase.

## Cleanup Objectives

### 3.1 Cleanup Goals
- **Eliminate Duplication**: Remove duplicate functions and logic patterns
- **Consolidate Validation**: Centralize input validation and sanitization
- **Remove Debug Code**: Clean up development debugging statements
- **Simplify Conditionals**: Reduce complex nested logic
- **Extract Constants**: Replace magic numbers and hardcoded values

## Duplicate Function Elimination

### 3.2 Identified Duplications

#### 3.2.1 Product Validation Pattern
**Found in Multiple Files**:
- `apw-woo-dynamic-pricing-functions.php`
- `apw-woo-product-addons-functions.php`
- `apw-woo-cross-sells-functions.php`

**Current Duplicate Code**:
```php
// Repeated in 3+ files
if (!$product_id) {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Invalid product passed to function');
    }
    return array();
}

$product = wc_get_product($product_id);
if (!$product) {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Couldn't get product object for ID: {$product_id}");
    }
    return array();
}
```

**Consolidated Solution**:
```php
<?php
namespace APW\WooPlugin\Common\Validators;

class ProductValidator {
    private LoggerServiceInterface $logger;

    public function __construct(LoggerServiceInterface $logger) {
        $this->logger = $logger;
    }

    public function validateProductId($product_input): ?int {
        $product_id = 0;
        
        if (is_numeric($product_input)) {
            $product_id = (int) $product_input;
        } elseif (is_object($product_input) && is_a($product_input, 'WC_Product')) {
            $product_id = $product_input->get_id();
        }

        if ($product_id <= 0) {
            $this->logger->warning('Invalid product ID provided', [
                'input_type' => gettype($product_input),
                'input_value' => is_scalar($product_input) ? $product_input : 'complex_type'
            ]);
            return null;
        }

        return $product_id;
    }

    public function getValidatedProduct($product_input): ?\WC_Product {
        $product_id = $this->validateProductId($product_input);
        
        if (!$product_id) {
            return null;
        }

        $product = wc_get_product($product_id);
        
        if (!$product) {
            $this->logger->warning("Product not found for ID: {$product_id}");
            return null;
        }

        return $product;
    }

    public function validateProductExists(int $product_id): bool {
        return $this->getValidatedProduct($product_id) !== null;
    }
}
```

#### 3.2.2 Plugin Activation Check Pattern
**Found in Multiple Files**:
- `apw-woo-dynamic-pricing-functions.php`
- `apw-woo-product-addons-functions.php`
- `apw-woo-intuit-payment-functions.php`

**Current Duplicate Code**:
```php
// Repeated pattern with slight variations
function apw_woo_is_dynamic_pricing_active() {
    return class_exists('WC_Dynamic_Pricing');
}

function apw_woo_is_product_addons_active() {
    return class_exists('WC_Product_Addons');
}

function apw_woo_is_intuit_gateway_active() {
    return class_exists('WC_Gateway_Intuit_QBMS') || class_exists('WC_Gateway_QBMS_Credit_Card');
}
```

**Consolidated Solution**:
```php
<?php
namespace APW\WooPlugin\Common\Validators;

class PluginValidator {
    private array $plugin_checks = [
        'dynamic_pricing' => ['WC_Dynamic_Pricing'],
        'product_addons' => ['WC_Product_Addons'],
        'intuit_gateway' => ['WC_Gateway_Intuit_QBMS', 'WC_Gateway_QBMS_Credit_Card'],
        'acf' => ['ACF'],
        'woocommerce' => ['WooCommerce', 'WC']
    ];

    public function isPluginActive(string $plugin_key): bool {
        if (!isset($this->plugin_checks[$plugin_key])) {
            throw new \InvalidArgumentException("Unknown plugin key: {$plugin_key}");
        }

        $classes_to_check = $this->plugin_checks[$plugin_key];
        
        foreach ($classes_to_check as $class_name) {
            if (class_exists($class_name)) {
                return true;
            }
        }

        return false;
    }

    public function getActivePlugins(): array {
        $active = [];
        
        foreach (array_keys($this->plugin_checks) as $plugin_key) {
            if ($this->isPluginActive($plugin_key)) {
                $active[] = $plugin_key;
            }
        }

        return $active;
    }

    public function requirePlugin(string $plugin_key): void {
        if (!$this->isPluginActive($plugin_key)) {
            throw new \RuntimeException("Required plugin not active: {$plugin_key}");
        }
    }
}
```

#### 3.2.3 Logging Pattern Consolidation
**Found Throughout Codebase**:
```php
// Repeated debug logging pattern
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('Message here');
}

// Variation with context
if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
    apw_woo_log('Message here', 'warning');
}
```

**Consolidated Solution**:
```php
<?php
namespace APW\WooPlugin\Services;

class LoggerService implements LoggerServiceInterface {
    private ConfigInterface $config;
    private string $log_directory;

    public function __construct(ConfigInterface $config, string $log_directory) {
        $this->config = $config;
        $this->log_directory = $log_directory;
    }

    public function debug(string $message, array $context = []): void {
        $this->log($message, 'debug', $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log($message, 'info', $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log($message, 'warning', $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log($message, 'error', $context);
    }

    public function log(string $message, string $level = 'info', array $context = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }

        $formatted_message = $this->formatMessage($message, $level, $context);
        $this->writeToFile($formatted_message);
    }

    private function shouldLog(string $level): bool {
        if (!$this->config->get('debug_mode', false)) {
            return false;
        }

        $min_level = $this->config->get('log_level', 'info');
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

        return ($levels[$level] ?? 1) >= ($levels[$min_level] ?? 1);
    }

    private function formatMessage(string $message, string $level, array $context): string {
        $timestamp = date('Y-m-d H:i:s T');
        $context_string = !empty($context) ? ' ' . json_encode($context) : '';
        
        return "[{$timestamp}] [{$level}] {$message}{$context_string}" . PHP_EOL;
    }
}
```

### 3.3 Validation and Sanitization Consolidation

#### 3.3.1 Input Validation Utilities
```php
<?php
namespace APW\WooPlugin\Common\Validators;

class InputValidator {
    public function sanitizeEmail(string $email): string {
        return sanitize_email($email);
    }

    public function validateEmails(string $emails_string): array {
        $emails = array_map('trim', explode(',', $emails_string));
        $valid_emails = [];
        $errors = [];

        foreach ($emails as $email) {
            if (empty($email)) {
                continue;
            }

            if (!is_email($email)) {
                $errors[] = "Invalid email format: {$email}";
                continue;
            }

            $valid_emails[] = $this->sanitizeEmail($email);
        }

        return [
            'valid_emails' => $valid_emails,
            'errors' => $errors
        ];
    }

    public function sanitizePhoneNumber(string $phone): string {
        // Remove all non-digit characters
        $digits_only = preg_replace('/\D/', '', $phone);
        
        // Format US phone numbers
        if (strlen($digits_only) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($digits_only, 0, 3),
                substr($digits_only, 3, 3),
                substr($digits_only, 6, 4)
            );
        }

        return $digits_only;
    }

    public function validatePhoneNumber(string $phone): bool {
        $digits_only = preg_replace('/\D/', '', $phone);
        return strlen($digits_only) >= 10 && strlen($digits_only) <= 15;
    }

    public function sanitizeTextField(string $text, int $max_length = 255): string {
        $sanitized = sanitize_text_field($text);
        
        if (strlen($sanitized) > $max_length) {
            $sanitized = substr($sanitized, 0, $max_length);
        }

        return $sanitized;
    }

    public function validateRequiredFields(array $data, array $required_fields): array {
        $errors = [];

        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $field_label = ucfirst(str_replace(['_', '-'], ' ', $field));
                $errors[$field] = "{$field_label} is required.";
            }
        }

        return $errors;
    }
}
```

### 3.4 Debug Code Removal

#### 3.4.1 Debug Code Audit
**Search and Remove Patterns**:
```bash
# Find debug-only code that should be removed
grep -r "// DEBUG" includes/
grep -r "// DEBUGGING" includes/
grep -r "// TEMP" includes/
grep -r "print_r" includes/
grep -r "var_dump" includes/
grep -r "die(" includes/
grep -r "exit(" includes/
```

#### 3.4.2 Debug Code Examples to Remove
```php
// REMOVE: Development debugging statements
// DEBUG - dump the exact structure of the rules
apw_woo_log("RULES STRUCTURE: " . print_r($product_pricing_rules, true));

// REMOVE: Temporary testing code
if (isset($_GET['test_pricing'])) {
    die('Testing pricing rules');
}

// REMOVE: Development comments
// DEBUGGING - this needs to be fixed later
// TODO - implement better error handling
// HACK - temporary workaround
```

#### 3.4.3 Clean Debug Implementation
```php
<?php
namespace APW\WooPlugin\Common\Debug;

class DebugHelper {
    private LoggerServiceInterface $logger;
    private ConfigInterface $config;

    public function __construct(LoggerServiceInterface $logger, ConfigInterface $config) {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function dumpVariable($variable, string $label = ''): void {
        if (!$this->config->get('debug_mode')) {
            return;
        }

        $output = $label ? "{$label}: " : '';
        $output .= print_r($variable, true);
        
        $this->logger->debug($output);
    }

    public function traceFunction(string $function_name, array $args = []): void {
        if (!$this->config->get('debug_mode')) {
            return;
        }

        $this->logger->debug("Function called: {$function_name}", [
            'arguments' => $args,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ]);
    }

    public function benchmarkStart(string $operation): void {
        if (!$this->config->get('debug_mode')) {
            return;
        }

        $GLOBALS["apw_benchmark_{$operation}"] = microtime(true);
    }

    public function benchmarkEnd(string $operation): void {
        if (!$this->config->get('debug_mode')) {
            return;
        }

        $start_key = "apw_benchmark_{$operation}";
        
        if (!isset($GLOBALS[$start_key])) {
            return;
        }

        $duration = microtime(true) - $GLOBALS[$start_key];
        unset($GLOBALS[$start_key]);
        
        $this->logger->debug("Benchmark {$operation}: {$duration}s");
    }
}
```

### 3.5 Complex Conditional Simplification

#### 3.5.1 Before: Complex Nested Conditionals
```php
// BEFORE: Complex nested logic from existing code
function apw_woo_get_faq_page_id($context = 'shop') {
    $context = sanitize_key($context);
    $valid_contexts = array('shop', 'category', 'product');
    
    if (!in_array($context, $valid_contexts)) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Invalid FAQ context requested: {$context}, defaulting to 'shop'");
        }
        $context = 'shop';
    }
    
    $default_ids = array('shop' => 0, 'category' => 0, 'product' => 0);
    $option_name = 'apw_woo_faq_' . $context . '_page_id';
    $page_id = get_option($option_name, $default_ids[$context]);
    $page_id = apply_filters('apw_woo_' . $context . '_faq_page_id', $page_id);
    
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("FAQ Page ID for {$context}: {$page_id}");
    }
    
    return absint($page_id);
}
```

#### 3.5.2 After: Simplified with Early Returns
```php
<?php
namespace APW\WooPlugin\Services;

class FAQService {
    private const VALID_CONTEXTS = ['shop', 'category', 'product'];
    private const DEFAULT_CONTEXT = 'shop';

    private LoggerServiceInterface $logger;

    public function __construct(LoggerServiceInterface $logger) {
        $this->logger = $logger;
    }

    public function getFAQPageId(string $context = self::DEFAULT_CONTEXT): int {
        $context = $this->validateContext($context);
        $page_id = $this->getPageIdFromOptions($context);
        $page_id = $this->applyContextFilter($context, $page_id);

        $this->logger->debug("FAQ Page ID for {$context}: {$page_id}");

        return $page_id;
    }

    private function validateContext(string $context): string {
        $sanitized = sanitize_key($context);
        
        if (!in_array($sanitized, self::VALID_CONTEXTS, true)) {
            $this->logger->warning("Invalid FAQ context: {$context}, using default: " . self::DEFAULT_CONTEXT);
            return self::DEFAULT_CONTEXT;
        }

        return $sanitized;
    }

    private function getPageIdFromOptions(string $context): int {
        $option_name = "apw_woo_faq_{$context}_page_id";
        return (int) get_option($option_name, 0);
    }

    private function applyContextFilter(string $context, int $page_id): int {
        $filter_name = "apw_woo_{$context}_faq_page_id";
        return (int) apply_filters($filter_name, $page_id);
    }
}
```

### 3.6 Hardcoded Values Extraction

#### 3.6.1 Configuration Constants
```php
<?php
namespace APW\WooPlugin\Common;

class Constants {
    // Payment configuration
    public const DEFAULT_SURCHARGE_RATE = 0.03;
    public const INTUIT_GATEWAY_ID = 'intuit_qbms_credit_card';
    
    // VIP discount thresholds
    public const VIP_DISCOUNT_THRESHOLDS = [
        500 => 0.10,  // 10% discount for $500+
        300 => 0.08,  // 8% discount for $300+
        100 => 0.05   // 5% discount for $100+
    ];
    
    // Field validation
    public const PHONE_MIN_LENGTH = 10;
    public const PHONE_MAX_LENGTH = 15;
    public const EMAIL_MAX_LENGTH = 254;
    
    // File management
    public const LOG_RETENTION_DAYS = 30;
    public const EXPORT_RETENTION_DAYS = 7;
    
    // Hook priorities
    public const HOOK_PRIORITIES = [
        'dynamic_pricing' => 5,
        'vip_discounts' => 8,
        'payment_surcharges' => 15,
        'additional_fees' => 20
    ];
}
```

### 3.7 Function Size Reduction

#### 3.7.1 Before: Bloated Function
```php
// BEFORE: 100+ line function doing too much
function apw_woo_autoload_files() {
    $includes_dir = APW_WOO_PLUGIN_DIR . 'includes';
    static $loaded_files = array();
    
    // 100+ lines of file loading logic...
    // Multiple responsibilities mixed together
}
```

#### 3.7.2 After: Modular Approach
```php
<?php
namespace APW\WooPlugin\Core;

class FileLoader {
    private string $includes_dir;
    private array $loaded_files = [];
    private LoggerServiceInterface $logger;

    public function __construct(string $includes_dir, LoggerServiceInterface $logger) {
        $this->includes_dir = $includes_dir;
        $this->logger = $logger;
    }

    public function loadAllFiles(): void {
        $this->ensureIncludesDirectory();
        $this->loadCoreFiles();
        $this->loadFunctionFiles();
        $this->loadClassFiles();
        $this->loadSubdirectoryFiles();
    }

    private function ensureIncludesDirectory(): void {
        if (!file_exists($this->includes_dir)) {
            wp_mkdir_p($this->includes_dir);
            $this->logger->info('Created includes directory');
        }
    }

    private function loadCoreFiles(): void {
        $core_files = ['class-apw-woo-logger.php'];
        
        foreach ($core_files as $file) {
            $this->loadFile($this->includes_dir . '/' . $file);
        }
    }

    private function loadFunctionFiles(): void {
        $pattern = $this->includes_dir . '/*.php';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (!$this->isClassFile($file) && !$this->isAlreadyLoaded($file)) {
                $this->loadFile($file);
            }
        }
    }

    private function loadFile(string $file_path): bool {
        if (!file_exists($file_path) || $this->isAlreadyLoaded($file_path)) {
            return false;
        }

        require_once $file_path;
        $this->loaded_files[realpath($file_path)] = true;
        $this->logger->debug('Loaded file: ' . basename($file_path));
        
        return true;
    }

    private function isClassFile(string $file_path): bool {
        return strpos(basename($file_path), 'class-') === 0;
    }

    private function isAlreadyLoaded(string $file_path): bool {
        $real_path = realpath($file_path);
        return isset($this->loaded_files[$real_path]);
    }
}
```

## Cleanup Automation

### 3.8 Automated Cleanup Tools
```bash
#!/bin/bash
# cleanup-script.sh - Automated code cleanup

echo "=== APW Plugin Code Cleanup ==="

# Remove debug comments
echo "Removing debug comments..."
find includes/ -name "*.php" -exec sed -i '' '/\/\/ DEBUG/d' {} \;
find includes/ -name "*.php" -exec sed -i '' '/\/\/ DEBUGGING/d' {} \;
find includes/ -name "*.php" -exec sed -i '' '/\/\/ TEMP/d' {} \;

# Remove development print statements
echo "Removing development print statements..."
grep -r "print_r\|var_dump" includes/ | cut -d: -f1 | sort -u > debug_files.txt

# Format code with WordPress standards
echo "Applying WordPress coding standards..."
./vendor/bin/phpcbf --standard=WordPress includes/

# Check for remaining issues
echo "Checking for remaining issues..."
./vendor/bin/phpcs --standard=WordPress includes/

echo "Cleanup complete!"
```

## Testing Cleanup Results

### 3.9 Cleanup Validation
```php
<?php
namespace APW\WooPlugin\Tests;

class CleanupValidationTest extends TestCase {
    public function testNoDuplicateFunctions(): void {
        $function_names = [];
        $files = glob(__DIR__ . '/../includes/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches);
            
            foreach ($matches[1] as $function_name) {
                if (isset($function_names[$function_name])) {
                    $this->fail("Duplicate function found: {$function_name} in {$file} and {$function_names[$function_name]}");
                }
                $function_names[$function_name] = $file;
            }
        }
        
        $this->assertTrue(true, 'No duplicate functions found');
    }

    public function testNoDebugCode(): void {
        $debug_patterns = ['print_r', 'var_dump', 'die(', 'exit('];
        $files = glob(__DIR__ . '/../includes/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            foreach ($debug_patterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $this->fail("Debug code found in {$file}: {$pattern}");
                }
            }
        }
        
        $this->assertTrue(true, 'No debug code found');
    }

    public function testFunctionComplexity(): void {
        $max_lines = 50;
        $files = glob(__DIR__ . '/../includes/*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[0] as $index => $match) {
                $function_start = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $function_end = $this->findFunctionEnd($lines, $function_start - 1);
                $function_length = $function_end - $function_start + 1;
                
                if ($function_length > $max_lines) {
                    $function_name = $matches[1][$index][0];
                    $this->fail("Function {$function_name} in {$file} is too long: {$function_length} lines (max: {$max_lines})");
                }
            }
        }
        
        $this->assertTrue(true, 'All functions within complexity limits');
    }

    private function findFunctionEnd(array $lines, int $start): int {
        $brace_count = 0;
        $in_function = false;
        
        for ($i = $start; $i < count($lines); $i++) {
            $line = $lines[$i];
            
            if (strpos($line, '{') !== false) {
                $brace_count += substr_count($line, '{');
                $in_function = true;
            }
            
            if (strpos($line, '}') !== false) {
                $brace_count -= substr_count($line, '}');
                
                if ($in_function && $brace_count === 0) {
                    return $i + 1;
                }
            }
        }
        
        return count($lines);
    }
}
```

## Success Criteria

### 3.10 Cleanup Metrics
- [ ] **Function Reduction**: Eliminate 40+ duplicate functions
- [ ] **File Size Reduction**: Reduce main plugin file from 1087 to <300 lines
- [ ] **Complexity Reduction**: All functions <50 lines
- [ ] **Debug Code Removal**: Zero debug statements in production code
- [ ] **Validation Consolidation**: Single validation utility class
- [ ] **Constant Extraction**: No hardcoded values in business logic
- [ ] **Standards Compliance**: 100% WordPress coding standards

### 3.11 Quality Gates
- **Code Coverage**: Maintain >80% test coverage during cleanup
- **Performance**: No performance degradation from refactoring
- **Functionality**: All existing features work after cleanup
- **Maintainability**: Improved code readability and organization

## Next Steps
Upon completion:
1. Proceed to Phase 3: Performance Optimization
2. Implement automated code quality checks
3. Establish continuous integration for cleanup validation
4. Update development documentation