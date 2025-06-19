# Phase 3: Security & Standards Implementation Specification

## Overview
Implement comprehensive security measures, input validation, nonce verification, WordPress coding standards compliance, and robust error handling throughout the APW WooCommerce Plugin.

## Security Objectives

### 3.1 Security Goals
- **Zero Vulnerabilities**: Eliminate all security risks
- **Input Validation**: Sanitize and validate all user inputs
- **Access Control**: Proper capability checks for all operations
- **Data Protection**: Secure handling of sensitive information
- **Standards Compliance**: 100% WordPress coding standards adherence

## Input Validation & Sanitization Framework

### 3.2 Comprehensive Input Validation

#### 3.2.1 Universal Input Validator
```php
<?php
namespace APW\WooPlugin\Security;

class InputValidator {
    private array $validation_rules = [];
    private array $sanitizers = [];

    public function __construct() {
        $this->registerSanitizers();
        $this->registerValidationRules();
    }

    public function validate(array $data, array $rules): ValidationResult {
        $errors = [];
        $sanitized_data = [];

        foreach ($rules as $field => $rule_set) {
            $value = $data[$field] ?? null;
            
            try {
                $sanitized_value = $this->sanitizeField($value, $rule_set);
                $this->validateField($sanitized_value, $rule_set, $field);
                $sanitized_data[$field] = $sanitized_value;
            } catch (ValidationException $e) {
                $errors[$field] = $e->getMessage();
            }
        }

        return new ValidationResult($sanitized_data, $errors);
    }

    private function sanitizeField($value, array $rules) {
        $sanitizer = $rules['sanitizer'] ?? 'text';
        
        if (!isset($this->sanitizers[$sanitizer])) {
            throw new \InvalidArgumentException("Unknown sanitizer: {$sanitizer}");
        }

        return $this->sanitizers[$sanitizer]($value);
    }

    private function validateField($value, array $rules, string $field_name): void {
        foreach ($rules as $rule_name => $rule_params) {
            if ($rule_name === 'sanitizer') {
                continue;
            }

            if (!isset($this->validation_rules[$rule_name])) {
                throw new \InvalidArgumentException("Unknown validation rule: {$rule_name}");
            }

            $validator = $this->validation_rules[$rule_name];
            
            if (!$validator($value, $rule_params)) {
                throw new ValidationException($this->getErrorMessage($rule_name, $field_name, $rule_params));
            }
        }
    }

    private function registerSanitizers(): void {
        $this->sanitizers = [
            'text' => function($value) {
                return sanitize_text_field((string) $value);
            },
            'email' => function($value) {
                return sanitize_email((string) $value);
            },
            'url' => function($value) {
                return esc_url_raw((string) $value);
            },
            'html' => function($value) {
                return wp_kses_post((string) $value);
            },
            'int' => function($value) {
                return (int) $value;
            },
            'float' => function($value) {
                return (float) $value;
            },
            'phone' => function($value) {
                return preg_replace('/[^0-9+\-\(\)\s]/', '', (string) $value);
            },
            'alphanumeric' => function($value) {
                return preg_replace('/[^a-zA-Z0-9]/', '', (string) $value);
            }
        ];
    }

    private function registerValidationRules(): void {
        $this->validation_rules = [
            'required' => function($value, $required) {
                return !$required || !empty($value);
            },
            'email' => function($value, $check) {
                return !$check || empty($value) || is_email($value);
            },
            'min_length' => function($value, $min) {
                return strlen((string) $value) >= $min;
            },
            'max_length' => function($value, $max) {
                return strlen((string) $value) <= $max;
            },
            'numeric' => function($value, $check) {
                return !$check || is_numeric($value);
            },
            'phone' => function($value, $check) {
                if (!$check || empty($value)) return true;
                $digits = preg_replace('/\D/', '', $value);
                return strlen($digits) >= 10 && strlen($digits) <= 15;
            },
            'in' => function($value, $allowed_values) {
                return in_array($value, $allowed_values, true);
            },
            'regex' => function($value, $pattern) {
                return preg_match($pattern, (string) $value);
            }
        ];
    }

    private function getErrorMessage(string $rule, string $field, $params): string {
        $field_label = ucfirst(str_replace(['_', '-'], ' ', $field));
        
        switch ($rule) {
            case 'required':
                return "{$field_label} is required.";
            case 'email':
                return "{$field_label} must be a valid email address.";
            case 'min_length':
                return "{$field_label} must be at least {$params} characters long.";
            case 'max_length':
                return "{$field_label} cannot exceed {$params} characters.";
            case 'numeric':
                return "{$field_label} must be a number.";
            case 'phone':
                return "{$field_label} must be a valid phone number.";
            default:
                return "{$field_label} is invalid.";
        }
    }
}

class ValidationResult {
    public array $data;
    public array $errors;

    public function __construct(array $data, array $errors) {
        $this->data = $data;
        $this->errors = $errors;
    }

    public function isValid(): bool {
        return empty($this->errors);
    }

    public function getErrorsForField(string $field): array {
        return $this->errors[$field] ?? [];
    }
}

class ValidationException extends \Exception {}
```

#### 3.2.2 Request Validation Middleware
```php
<?php
namespace APW\WooPlugin\Security;

class RequestValidator {
    private InputValidator $input_validator;
    private NonceValidator $nonce_validator;

    public function __construct(InputValidator $input_validator, NonceValidator $nonce_validator) {
        $this->input_validator = $input_validator;
        $this->nonce_validator = $nonce_validator;
    }

    public function validateRequest(array $validation_config): ValidationResult {
        // Validate nonce first
        if (isset($validation_config['nonce'])) {
            $this->nonce_validator->verify($validation_config['nonce']);
        }

        // Get request data
        $request_data = $this->getRequestData($validation_config['method'] ?? 'POST');

        // Validate input
        return $this->input_validator->validate($request_data, $validation_config['rules'] ?? []);
    }

    private function getRequestData(string $method): array {
        switch (strtoupper($method)) {
            case 'GET':
                return $_GET;
            case 'POST':
                return $_POST;
            case 'REQUEST':
                return $_REQUEST;
            default:
                throw new \InvalidArgumentException("Unsupported method: {$method}");
        }
    }
}
```

### 3.3 Nonce Verification System

#### 3.3.1 Comprehensive Nonce Manager
```php
<?php
namespace APW\WooPlugin\Security;

class NonceValidator {
    private const NONCE_EXPIRY = 12 * HOUR_IN_SECONDS;
    
    public function create(string $action, int $user_id = null): string {
        $user_id = $user_id ?? get_current_user_id();
        return wp_create_nonce($this->getNonceAction($action, $user_id));
    }

    public function verify(string $nonce, string $action, int $user_id = null): bool {
        if (empty($nonce)) {
            throw new SecurityException('Nonce is required');
        }

        $user_id = $user_id ?? get_current_user_id();
        $valid = wp_verify_nonce($nonce, $this->getNonceAction($action, $user_id));

        if (!$valid) {
            throw new SecurityException('Invalid nonce');
        }

        return true;
    }

    public function verifyAjax(string $action, string $nonce_key = 'nonce'): bool {
        $nonce = $_REQUEST[$nonce_key] ?? '';
        
        if (!$this->verify($nonce, $action)) {
            wp_die('Security check failed', 'Security Error', ['response' => 403]);
        }

        return true;
    }

    public function verifyAdminPost(string $action): bool {
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        
        if (!$this->verify($nonce, $action)) {
            wp_die('Security check failed');
        }

        return true;
    }

    public function createField(string $action, string $name = '_wpnonce', bool $referer = true): string {
        return wp_nonce_field($this->getNonceAction($action), $name, $referer, false);
    }

    public function createUrl(string $url, string $action): string {
        return wp_nonce_url($url, $this->getNonceAction($action));
    }

    private function getNonceAction(string $action, int $user_id = null): string {
        $user_id = $user_id ?? get_current_user_id();
        return "apw_woo_{$action}_{$user_id}";
    }
}

class SecurityException extends \Exception {}
```

#### 3.3.2 AJAX Security Handler
```php
<?php
namespace APW\WooPlugin\Controllers\Ajax;

abstract class SecureAjaxController {
    protected NonceValidator $nonce_validator;
    protected CapabilityChecker $capability_checker;

    public function __construct(NonceValidator $nonce_validator, CapabilityChecker $capability_checker) {
        $this->nonce_validator = $nonce_validator;
        $this->capability_checker = $capability_checker;
    }

    protected function handleAjaxRequest(string $action, string $required_capability = ''): void {
        try {
            // Verify nonce
            $this->nonce_validator->verifyAjax($action);

            // Check user capability
            if (!empty($required_capability)) {
                $this->capability_checker->requireCapability($required_capability);
            }

            // Process request
            $result = $this->processRequest();

            wp_send_json_success($result);

        } catch (SecurityException $e) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        } catch (CapabilityException $e) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Request failed'], 500);
        }
    }

    abstract protected function processRequest();
}
```

### 3.4 Access Control & Capabilities

#### 3.4.1 Capability Management System
```php
<?php
namespace APW\WooPlugin\Security;

class CapabilityChecker {
    private array $capability_map = [
        'export_referrals' => 'manage_woocommerce',
        'manage_pricing' => 'manage_woocommerce',
        'view_customer_data' => 'list_users',
        'edit_customer_data' => 'edit_users',
        'manage_plugin_settings' => 'manage_options'
    ];

    public function hasCapability(string $capability, int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();
        
        if (!$user_id) {
            return false;
        }

        $wp_capability = $this->mapToWordPressCapability($capability);
        
        return user_can($user_id, $wp_capability);
    }

    public function requireCapability(string $capability, int $user_id = null): void {
        if (!$this->hasCapability($capability, $user_id)) {
            throw new CapabilityException("User lacks required capability: {$capability}");
        }
    }

    public function requireAnyCapability(array $capabilities, int $user_id = null): void {
        foreach ($capabilities as $capability) {
            if ($this->hasCapability($capability, $user_id)) {
                return;
            }
        }

        throw new CapabilityException('User lacks any of the required capabilities');
    }

    public function filterByCapability(array $items, string $capability, string $user_id_field = 'user_id'): array {
        return array_filter($items, function($item) use ($capability, $user_id_field) {
            $user_id = is_array($item) ? $item[$user_id_field] : $item->$user_id_field;
            return $this->hasCapability($capability, $user_id);
        });
    }

    private function mapToWordPressCapability(string $capability): string {
        return $this->capability_map[$capability] ?? $capability;
    }
}

class CapabilityException extends \Exception {}
```

#### 3.4.2 Secure Controller Base Class
```php
<?php
namespace APW\WooPlugin\Controllers;

abstract class SecureController extends BaseController {
    protected NonceValidator $nonce_validator;
    protected CapabilityChecker $capability_checker;

    public function __construct(
        ServiceContainer $container,
        NonceValidator $nonce_validator,
        CapabilityChecker $capability_checker
    ) {
        parent::__construct($container);
        $this->nonce_validator = $nonce_validator;
        $this->capability_checker = $capability_checker;
    }

    protected function requireCapability(string $capability): void {
        $this->capability_checker->requireCapability($capability);
    }

    protected function verifyNonce(string $action, string $nonce_key = '_wpnonce'): void {
        $nonce = $_REQUEST[$nonce_key] ?? '';
        $this->nonce_validator->verify($nonce, $action);
    }

    protected function secureAction(string $action, string $capability, callable $callback) {
        try {
            $this->verifyNonce($action);
            $this->requireCapability($capability);
            return $callback();
        } catch (SecurityException | CapabilityException $e) {
            wp_die($e->getMessage(), 'Access Denied', ['response' => 403]);
        }
    }
}
```

### 3.5 Data Protection & Encryption

#### 3.5.1 Secure Data Handler
```php
<?php
namespace APW\WooPlugin\Security;

class DataProtection {
    private string $encryption_key;
    private string $algorithm = 'aes-256-gcm';

    public function __construct() {
        $this->encryption_key = $this->getEncryptionKey();
    }

    public function encrypt(string $data): string {
        $iv = random_bytes(16);
        $tag = '';
        
        $encrypted = openssl_encrypt($data, $this->algorithm, $this->encryption_key, 0, $iv, $tag);
        
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $encrypted_data): string {
        $data = base64_decode($encrypted_data);
        
        if ($data === false || strlen($data) < 32) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);

        $decrypted = openssl_decrypt($encrypted, $this->algorithm, $this->encryption_key, 0, $iv, $tag);
        
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    public function hashSensitiveData(string $data, string $salt = ''): string {
        if (empty($salt)) {
            $salt = wp_salt('secure_auth');
        }

        return wp_hash_password($data . $salt);
    }

    public function verifySensitiveData(string $data, string $hash, string $salt = ''): bool {
        if (empty($salt)) {
            $salt = wp_salt('secure_auth');
        }

        return wp_check_password($data . $salt, $hash);
    }

    public function sanitizeForStorage(array $data): array {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitizeForStorage($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            } else {
                $sanitized[$sanitized_key] = $value;
            }
        }

        return $sanitized;
    }

    private function getEncryptionKey(): string {
        $key = defined('APW_ENCRYPTION_KEY') ? APW_ENCRYPTION_KEY : '';
        
        if (empty($key)) {
            $key = get_option('apw_encryption_key');
            
            if (!$key) {
                $key = base64_encode(random_bytes(32));
                update_option('apw_encryption_key', $key, false);
            }
        }

        return base64_decode($key);
    }
}
```

### 3.6 WordPress Coding Standards Compliance

#### 3.6.1 Automated Standards Checker
```bash
#!/bin/bash
# scripts/check-standards.sh

echo "=== WordPress Coding Standards Check ==="

# Install PHPCS and WordPress standards if not present
if ! command -v phpcs &> /dev/null; then
    echo "Installing PHP_CodeSniffer..."
    composer global require "squizlabs/php_codesniffer=*"
    composer global require wp-coding-standards/wpcs
    phpcs --config-set installed_paths ~/.composer/vendor/wp-coding-standards/wpcs
fi

# Check files
echo "Checking PHP files for WordPress coding standards..."
phpcs --standard=WordPress --extensions=php --ignore=vendor/,node_modules/ ./

# Check for security issues
echo "Checking for security issues..."
phpcs --standard=WordPress-Extra --extensions=php --ignore=vendor/,node_modules/ ./

# Auto-fix what can be fixed
echo "Auto-fixing fixable issues..."
phpcbf --standard=WordPress --extensions=php --ignore=vendor/,node_modules/ ./

echo "Standards check complete!"
```

#### 3.6.2 Standards Compliance Validator
```php
<?php
namespace APW\WooPlugin\Quality;

class CodingStandardsValidator {
    private array $violations = [];

    public function validateFile(string $file_path): array {
        $content = file_get_contents($file_path);
        $this->violations = [];

        $this->checkNamingConventions($content);
        $this->checkDocumentationBlocks($content);
        $this->checkSecurityPractices($content);
        $this->checkPerformancePractices($content);

        return $this->violations;
    }

    private function checkNamingConventions(string $content): void {
        // Function names should be lowercase with underscores
        if (preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches)) {
            foreach ($matches[1] as $function_name) {
                if (!preg_match('/^[a-z_][a-z0-9_]*$/', $function_name)) {
                    $this->addViolation("Function name '{$function_name}' should be lowercase with underscores");
                }
            }
        }

        // Class names should be in PascalCase
        if (preg_match_all('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*{/', $content, $matches)) {
            foreach ($matches[1] as $class_name) {
                if (!preg_match('/^[A-Z][a-zA-Z0-9_]*$/', $class_name)) {
                    $this->addViolation("Class name '{$class_name}' should be in PascalCase");
                }
            }
        }

        // Constants should be uppercase
        if (preg_match_all('/const\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=/', $content, $matches)) {
            foreach ($matches[1] as $constant_name) {
                if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $constant_name)) {
                    $this->addViolation("Constant '{$constant_name}' should be uppercase with underscores");
                }
            }
        }
    }

    private function checkDocumentationBlocks(string $content): void {
        // All functions should have documentation
        if (preg_match_all('/(?:^|\n)(\s*)function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $function_start = $match[1];
                $function_name = $matches[2][$index][0];
                
                // Look for documentation block before function
                $before_function = substr($content, 0, $function_start);
                $lines_before = array_slice(explode("\n", $before_function), -10);
                
                $has_docblock = false;
                foreach ($lines_before as $line) {
                    if (strpos(trim($line), '/**') !== false) {
                        $has_docblock = true;
                        break;
                    }
                }

                if (!$has_docblock) {
                    $this->addViolation("Function '{$function_name}' missing documentation block");
                }
            }
        }
    }

    private function checkSecurityPractices(string $content): void {
        // Check for unescaped output
        if (preg_match('/echo\s+\$[^;]*;/', $content)) {
            $this->addViolation('Potential unescaped output detected - use esc_html(), esc_attr(), etc.');
        }

        // Check for SQL injection vulnerabilities
        if (preg_match('/\$wpdb->query\s*\(\s*["\'].*\$/', $content)) {
            $this->addViolation('Potential SQL injection - use $wpdb->prepare()');
        }

        // Check for direct superglobal access
        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)\[/', $content)) {
            $this->addViolation('Direct superglobal access detected - sanitize input');
        }
    }

    private function checkPerformancePractices(string $content): void {
        // Check for inefficient queries
        if (preg_match('/get_posts\s*\(\s*[^)]*posts_per_page[^)]*-1/', $content)) {
            $this->addViolation('Unbounded query detected - limit post count');
        }

        // Check for missing object caching
        if (preg_match('/get_option\s*\(\s*["\'][^"\']*["\']/', $content) && 
            !preg_match('/wp_cache_get|wp_cache_set/', $content)) {
            $this->addViolation('Consider caching frequently accessed options');
        }
    }

    private function addViolation(string $message): void {
        $this->violations[] = $message;
    }
}
```

### 3.7 Comprehensive Error Handling

#### 3.7.1 Error Handler System
```php
<?php
namespace APW\WooPlugin\Error;

class ErrorHandler {
    private LoggerServiceInterface $logger;
    private bool $debug_mode;

    public function __construct(LoggerServiceInterface $logger, bool $debug_mode = false) {
        $this->logger = $logger;
        $this->debug_mode = $debug_mode;
    }

    public function handleException(\Throwable $exception, array $context = []): void {
        $error_data = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context
        ];

        // Log error
        $this->logger->error('Exception occurred', $error_data);

        // Handle based on exception type
        if ($exception instanceof SecurityException) {
            $this->handleSecurityException($exception);
        } elseif ($exception instanceof ValidationException) {
            $this->handleValidationException($exception);
        } else {
            $this->handleGenericException($exception);
        }
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $error_data = [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ];

        $this->logger->error('PHP Error', $error_data);

        if ($this->debug_mode) {
            echo "Error: {$message} in {$file} on line {$line}\n";
        }

        return true;
    }

    public function handleShutdown(): void {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger->critical('Fatal Error', $error);
        }
    }

    private function handleSecurityException(SecurityException $exception): void {
        if (wp_doing_ajax()) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        } else {
            wp_die('Access denied', 'Security Error', ['response' => 403]);
        }
    }

    private function handleValidationException(ValidationException $exception): void {
        if (wp_doing_ajax()) {
            wp_send_json_error(['message' => $exception->getMessage()], 400);
        } else {
            wp_redirect(add_query_arg('error', urlencode($exception->getMessage()), wp_get_referer()));
            exit;
        }
    }

    private function handleGenericException(\Throwable $exception): void {
        $message = $this->debug_mode ? $exception->getMessage() : 'An error occurred';
        
        if (wp_doing_ajax()) {
            wp_send_json_error(['message' => $message], 500);
        } else {
            wp_die($message, 'Error', ['response' => 500]);
        }
    }
}
```

#### 3.7.2 Error Recovery System
```php
<?php
namespace APW\WooPlugin\Error;

class ErrorRecovery {
    private LoggerServiceInterface $logger;

    public function __construct(LoggerServiceInterface $logger) {
        $this->logger = $logger;
    }

    public function withFallback(callable $primary, callable $fallback, string $operation = 'operation') {
        try {
            return $primary();
        } catch (\Throwable $e) {
            $this->logger->warning("Primary {$operation} failed, using fallback", [
                'error' => $e->getMessage(),
                'operation' => $operation
            ]);

            try {
                return $fallback();
            } catch (\Throwable $fallback_error) {
                $this->logger->error("Fallback {$operation} also failed", [
                    'primary_error' => $e->getMessage(),
                    'fallback_error' => $fallback_error->getMessage(),
                    'operation' => $operation
                ]);

                throw $fallback_error;
            }
        }
    }

    public function retryWithBackoff(callable $operation, int $max_attempts = 3, int $base_delay = 1000): mixed {
        $attempt = 1;
        
        while ($attempt <= $max_attempts) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                if ($attempt === $max_attempts) {
                    throw $e;
                }

                $delay = $base_delay * pow(2, $attempt - 1); // Exponential backoff
                $this->logger->warning("Operation failed, retrying in {$delay}ms", [
                    'attempt' => $attempt,
                    'max_attempts' => $max_attempts,
                    'error' => $e->getMessage()
                ]);

                usleep($delay * 1000);
                $attempt++;
            }
        }
    }
}
```

## Testing Security Implementation

### 3.8 Security Testing Framework

#### 3.8.1 Security Test Suite
```php
<?php
namespace APW\WooPlugin\Tests\Security;

class SecurityTest extends TestCase {
    private InputValidator $validator;
    private NonceValidator $nonce_validator;

    protected function setUp(): void {
        $this->validator = new InputValidator();
        $this->nonce_validator = new NonceValidator();
    }

    public function testInputSanitization(): void {
        $malicious_input = '<script>alert("xss")</script>';
        
        $result = $this->validator->validate(['content' => $malicious_input], [
            'content' => ['sanitizer' => 'html']
        ]);

        $this->assertStringNotContainsString('<script>', $result->data['content']);
    }

    public function testSQLInjectionPrevention(): void {
        $malicious_input = "'; DROP TABLE wp_users; --";
        
        $result = $this->validator->validate(['search' => $malicious_input], [
            'search' => ['sanitizer' => 'text']
        ]);

        $this->assertStringNotContainsString('DROP TABLE', $result->data['search']);
    }

    public function testNonceValidation(): void {
        $action = 'test_action';
        $nonce = $this->nonce_validator->create($action);
        
        $this->assertTrue($this->nonce_validator->verify($nonce, $action));
        
        $this->expectException(SecurityException::class);
        $this->nonce_validator->verify('invalid_nonce', $action);
    }

    public function testCapabilityChecking(): void {
        $checker = new CapabilityChecker();
        
        // Test with user who doesn't have capability
        $this->expectException(CapabilityException::class);
        $checker->requireCapability('manage_options', 1);
    }
}
```

## Success Criteria

### 3.9 Security Compliance Checklist
- [ ] **Input Validation**: 100% of user inputs validated and sanitized
- [ ] **Nonce Verification**: All AJAX and form submissions protected
- [ ] **Capability Checks**: All admin functions require proper permissions
- [ ] **Data Encryption**: Sensitive data encrypted at rest
- [ ] **SQL Injection Prevention**: All database queries use prepared statements
- [ ] **XSS Prevention**: All output properly escaped
- [ ] **CSRF Protection**: All state-changing operations protected
- [ ] **WordPress Standards**: 100% compliance with WordPress coding standards

### 3.10 Quality Gates
- [ ] **Security Audit**: Pass external security audit
- [ ] **Penetration Testing**: No critical or high vulnerabilities
- [ ] **Code Review**: All code reviewed for security issues
- [ ] **Automated Testing**: Security tests pass in CI/CD pipeline

## Next Steps
Upon completion:
1. Proceed to Phase 4: Testing Framework Implementation
2. Establish security monitoring and alerting
3. Create security documentation and guidelines
4. Implement continuous security testing