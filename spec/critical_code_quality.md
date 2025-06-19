# Critical Code Quality Issues Specification

## Overview
Address critical code quality problems in the current APW WooCommerce Plugin that impact maintainability, performance, security, and developer productivity. These issues must be resolved to ensure professional-grade code quality.

## Critical Code Quality Issues

### 1. Duplicate Code and Copy-Paste Programming

#### 1.1 Current Problems
**Files with Significant Duplication**:
- `includes/apw-woo-functions.php` - 40% duplicated validation logic
- `includes/apw-woo-intuit-payment-functions.php` - Repeated cart calculation code
- `admin/class-apw-woo-admin.php` - Duplicated form handling

**Example of Duplication**:
```php
// PROBLEMATIC CODE - Duplicated in 5+ places
function validate_customer_phone_1($phone) {
    if (empty($phone)) return false;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) < 10) return false;
    return true;
}

function validate_phone_number($phone) {
    if (!$phone) return false;
    $digits = preg_replace('/\\D/', '', $phone);
    if (strlen($digits) < 10) return false;
    return true;
}

function check_phone_format($phone_num) {
    if ($phone_num == '') return false;
    $clean_phone = preg_replace('/[^0-9]/', '', $phone_num);
    if (strlen($clean_phone) < 10) return false;
    return true;
}
```

#### 1.2 Deduplication Solution
```php
<?php
namespace APW\WooPlugin\Validation;

class PhoneValidator {
    private const MIN_LENGTH = 10;
    private const MAX_LENGTH = 15;
    
    public function validate(string $phone): ValidationResult {
        $phone = trim($phone);
        
        if (empty($phone)) {
            return ValidationResult::error('Phone number is required');
        }
        
        $digits = $this->extractDigits($phone);
        
        if (strlen($digits) < self::MIN_LENGTH) {
            return ValidationResult::error('Phone number must be at least ' . self::MIN_LENGTH . ' digits');
        }
        
        if (strlen($digits) > self::MAX_LENGTH) {
            return ValidationResult::error('Phone number cannot exceed ' . self::MAX_LENGTH . ' digits');
        }
        
        return ValidationResult::success($this->formatPhone($digits));
    }
    
    public function isValid(string $phone): bool {
        return $this->validate($phone)->isValid();
    }
    
    private function extractDigits(string $phone): string {
        return preg_replace('/\D/', '', $phone);
    }
    
    private function formatPhone(string $digits): string {
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4)
            );
        }
        
        return $digits;
    }
}

class ValidationResult {
    private bool $valid;
    private string $value;
    private string $error;
    
    private function __construct(bool $valid, string $value = '', string $error = '') {
        $this->valid = $valid;
        $this->value = $value;
        $this->error = $error;
    }
    
    public static function success(string $value): self {
        return new self(true, $value);
    }
    
    public static function error(string $error): self {
        return new self(false, '', $error);
    }
    
    public function isValid(): bool {
        return $this->valid;
    }
    
    public function getValue(): string {
        return $this->value;
    }
    
    public function getError(): string {
        return $this->error;
    }
}
```

### 2. Inconsistent Error Handling

#### 2.1 Current Problems
**Issues**:
- Some functions return `false` on error, others throw exceptions
- Error messages not user-friendly
- No centralized error logging
- Mix of `die()`, `wp_die()`, and silent failures

**Example of Inconsistent Error Handling**:
```php
// PROBLEMATIC CODE - Different error handling patterns
function process_payment_1($data) {
    if (!$data) return false; // Silent failure
    if (!validate_payment($data)) {
        die('Invalid payment data'); // Kills entire page
    }
    // Process payment...
}

function process_payment_2($data) {
    if (empty($data)) {
        throw new Exception('Payment data required'); // Exception
    }
    if (!valid_payment_method($data['method'])) {
        wp_die('Payment method not supported'); // WordPress die
    }
    // Process payment...
}

function process_payment_3($data) {
    // No error handling at all!
    $result = api_call($data['card_number']);
    return $result['success']; // Might not exist
}
```

#### 2.2 Unified Error Handling Solution
```php
<?php
namespace APW\WooPlugin\Exceptions;

abstract class APWException extends \Exception {
    protected string $user_message;
    protected array $context;
    
    public function __construct(
        string $message, 
        string $user_message = '', 
        array $context = [], 
        int $code = 0, 
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->user_message = $user_message ?: $message;
        $this->context = $context;
    }
    
    public function getUserMessage(): string {
        return $this->user_message;
    }
    
    public function getContext(): array {
        return $this->context;
    }
}

class PaymentException extends APWException {}
class ValidationException extends APWException {}
class CustomerException extends APWException {}

// Centralized Error Handler
class ErrorHandler {
    private LoggerServiceInterface $logger;
    private NotificationServiceInterface $notifications;
    
    public function handle(\Throwable $exception, array $additional_context = []): void {
        $context = array_merge([
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ], $additional_context);
        
        if ($exception instanceof APWException) {
            $context = array_merge($context, $exception->getContext());
        }
        
        // Log the error
        $this->logger->error($exception->getMessage(), $context);
        
        // Handle based on context
        if (wp_doing_ajax()) {
            $this->handleAjaxError($exception);
        } elseif (is_admin()) {
            $this->handleAdminError($exception);
        } else {
            $this->handleFrontendError($exception);
        }
    }
    
    private function handleAjaxError(\Throwable $exception): void {
        $message = $exception instanceof APWException 
            ? $exception->getUserMessage() 
            : 'An unexpected error occurred';
            
        wp_send_json_error(['message' => $message], 500);
    }
    
    private function handleAdminError(\Throwable $exception): void {
        $message = $exception instanceof APWException 
            ? $exception->getUserMessage() 
            : 'An unexpected error occurred';
            
        $this->notifications->addAdminNotice($message, 'error');
    }
    
    private function handleFrontendError(\Throwable $exception): void {
        if ($exception instanceof PaymentException) {
            wc_add_notice($exception->getUserMessage(), 'error');
        } else {
            wc_add_notice('An unexpected error occurred. Please try again.', 'error');
        }
    }
}
```

### 3. Poor Variable and Function Naming

#### 3.1 Current Problems
**Issues**:
- Unclear abbreviations (`calc_cc_fee`, `proc_pmt`)
- Inconsistent naming conventions
- Single-letter variables in complex logic
- Functions that don't describe what they do

**Example of Poor Naming**:
```php
// PROBLEMATIC CODE - Poor naming throughout
function cc_proc($d) {
    global $wpdb;
    $c = get_current_user_id();
    $t = WC()->cart->get_total();
    
    if ($c && user_meta($c, 'vip')) {
        $r = calc_disc($t);
        $f = $t * 0.03;
        add_fee($f - $r);
    }
    
    return proc_result($d);
}

function calc_disc($amt) {
    // What discount? What amount?
    if ($amt > 500) return $amt * 0.1;
    if ($amt > 300) return $amt * 0.08;
    return 0;
}
```

#### 3.2 Improved Naming Solution
```php
<?php
namespace APW\WooPlugin\Services\Payment;

class CreditCardProcessingService {
    private VIPDiscountServiceInterface $vip_discount_service;
    private PaymentSurchargeServiceInterface $surcharge_service;
    private CustomerServiceInterface $customer_service;
    
    public function processCreditCardPayment(PaymentData $payment_data): PaymentResult {
        $current_customer_id = get_current_user_id();
        $cart_total_amount = WC()->cart->get_total();
        
        if ($current_customer_id && $this->customer_service->isVIPCustomer($current_customer_id)) {
            $vip_discount_amount = $this->vip_discount_service->calculateDiscount($cart_total_amount);
            $processing_fee_amount = $this->surcharge_service->calculateSurcharge($cart_total_amount);
            
            $this->surcharge_service->applySurcharge($processing_fee_amount - $vip_discount_amount);
        }
        
        return $this->processPaymentWithGateway($payment_data);
    }
    
    private function processPaymentWithGateway(PaymentData $payment_data): PaymentResult {
        // Clear, descriptive method name
        // Implementation here...
    }
}

class VIPDiscountCalculator {
    private const GOLD_TIER_THRESHOLD = 500;
    private const SILVER_TIER_THRESHOLD = 300;
    private const GOLD_TIER_DISCOUNT_RATE = 0.10; // 10%
    private const SILVER_TIER_DISCOUNT_RATE = 0.08; // 8%
    
    public function calculateVIPDiscountAmount(float $order_total_amount): float {
        if ($order_total_amount >= self::GOLD_TIER_THRESHOLD) {
            return $order_total_amount * self::GOLD_TIER_DISCOUNT_RATE;
        }
        
        if ($order_total_amount >= self::SILVER_TIER_THRESHOLD) {
            return $order_total_amount * self::SILVER_TIER_DISCOUNT_RATE;
        }
        
        return 0.0;
    }
}
```

### 4. Magic Numbers and Hard-Coded Values

#### 4.1 Current Problems
**Issues**:
- Magic numbers scattered throughout code
- Hard-coded URLs and paths
- Business logic values embedded in code
- No configuration management

**Example of Magic Numbers**:
```php
// PROBLEMATIC CODE - Magic numbers everywhere
function calculate_fees($amount) {
    if ($amount > 1000) {
        return $amount * 0.025; // What is 2.5%?
    }
    
    if ($amount > 500) {
        return $amount * 0.03 + 2.50; // Why 3% + $2.50?
    }
    
    return max(0.50, $amount * 0.035); // What's the minimum 50 cents for?
}

function check_vip_status($customer_id) {
    $orders = get_user_orders($customer_id);
    $total = 0;
    
    foreach ($orders as $order) {
        $total += $order->total;
    }
    
    return $total >= 2500; // Magic VIP threshold
}
```

#### 4.2 Configuration-Driven Solution
```php
<?php
namespace APW\WooPlugin\Config;

class PaymentConfiguration {
    // Configuration constants with clear names
    private const CONFIG_KEY = 'apw_payment_config';
    
    private array $default_config = [
        'fee_tiers' => [
            'premium' => [
                'threshold' => 1000.00,
                'rate' => 0.025,
                'description' => 'Premium tier - 2.5% for orders over $1000'
            ],
            'standard' => [
                'threshold' => 500.00,
                'rate' => 0.030,
                'fixed_fee' => 2.50,
                'description' => 'Standard tier - 3% + $2.50 for orders $500-$999'
            ],
            'basic' => [
                'threshold' => 0.00,
                'rate' => 0.035,
                'minimum_fee' => 0.50,
                'description' => 'Basic tier - 3.5% with $0.50 minimum'
            ]
        ],
        'vip_qualification' => [
            'minimum_lifetime_spend' => 2500.00,
            'minimum_orders' => 5,
            'qualification_period_months' => 12
        ]
    ];
    
    public function getFeeConfiguration(): array {
        $saved_config = get_option(self::CONFIG_KEY, []);
        return array_merge($this->default_config, $saved_config);
    }
    
    public function getVIPQualificationThreshold(): float {
        $config = $this->getFeeConfiguration();
        return $config['vip_qualification']['minimum_lifetime_spend'];
    }
    
    public function getFeeTiers(): array {
        $config = $this->getFeeConfiguration();
        return $config['fee_tiers'];
    }
}

class PaymentFeeCalculator {
    private PaymentConfiguration $config;
    
    public function __construct(PaymentConfiguration $config) {
        $this->config = $config;
    }
    
    public function calculateProcessingFee(float $order_amount): float {
        $fee_tiers = $this->config->getFeeTiers();
        
        // Sort tiers by threshold descending to find applicable tier
        uasort($fee_tiers, fn($a, $b) => $b['threshold'] <=> $a['threshold']);
        
        foreach ($fee_tiers as $tier_name => $tier_config) {
            if ($order_amount >= $tier_config['threshold']) {
                return $this->calculateTierFee($order_amount, $tier_config);
            }
        }
        
        // Fallback to basic tier
        return $this->calculateTierFee($order_amount, $fee_tiers['basic']);
    }
    
    private function calculateTierFee(float $amount, array $tier_config): float {
        $fee = $amount * $tier_config['rate'];
        
        // Add fixed fee if configured
        if (isset($tier_config['fixed_fee'])) {
            $fee += $tier_config['fixed_fee'];
        }
        
        // Apply minimum fee if configured
        if (isset($tier_config['minimum_fee'])) {
            $fee = max($fee, $tier_config['minimum_fee']);
        }
        
        return $fee;
    }
}
```

### 5. Lack of Input Validation and Type Safety

#### 5.1 Current Problems
**Issues**:
- No type hints on function parameters
- Missing input validation
- Assumes data types without checking
- No sanitization of user inputs

**Example of Unsafe Code**:
```php
// PROBLEMATIC CODE - No validation or type safety
function update_customer_data($customer_id, $data) {
    // No validation of customer_id or data
    global $wpdb;
    
    // Direct database insertion without sanitization
    $wpdb->update(
        'wp_customers',
        $data, // Raw user data!
        array('id' => $customer_id)
    );
    
    return true;
}

function calculate_discount($amount, $rate) {
    // What if $amount is a string? What if $rate is null?
    return $amount * $rate;
}
```

#### 5.2 Type-Safe and Validated Solution
```php
<?php
namespace APW\WooPlugin\Services\Customer;

class CustomerDataService {
    private InputValidator $validator;
    private CustomerRepository $repository;
    
    public function __construct(InputValidator $validator, CustomerRepository $repository) {
        $this->validator = $validator;
        $this->repository = $repository;
    }
    
    public function updateCustomerData(int $customer_id, array $raw_data): CustomerUpdateResult {
        // Validate customer ID
        if ($customer_id <= 0) {
            throw new InvalidArgumentException('Customer ID must be a positive integer');
        }
        
        // Validate customer exists
        if (!$this->repository->exists($customer_id)) {
            throw new CustomerNotFoundException("Customer {$customer_id} not found");
        }
        
        // Validate and sanitize input data
        $validation_result = $this->validator->validate($raw_data, $this->getValidationRules());
        
        if (!$validation_result->isValid()) {
            throw new ValidationException(
                'Invalid customer data provided',
                'Please check your input and try again',
                ['errors' => $validation_result->getErrors()]
            );
        }
        
        // Update with sanitized data
        $sanitized_data = $validation_result->getData();
        $updated_customer = $this->repository->update($customer_id, $sanitized_data);
        
        return new CustomerUpdateResult($updated_customer, 'Customer updated successfully');
    }
    
    public function calculateDiscountAmount(float $order_amount, float $discount_rate): float {
        // Type validation
        if ($order_amount < 0) {
            throw new InvalidArgumentException('Order amount cannot be negative');
        }
        
        if ($discount_rate < 0 || $discount_rate > 1) {
            throw new InvalidArgumentException('Discount rate must be between 0 and 1');
        }
        
        return round($order_amount * $discount_rate, 2);
    }
    
    private function getValidationRules(): array {
        return [
            'first_name' => [
                'sanitizer' => 'text',
                'required' => true,
                'min_length' => 1,
                'max_length' => 50
            ],
            'last_name' => [
                'sanitizer' => 'text',
                'required' => true,
                'min_length' => 1,
                'max_length' => 50
            ],
            'email' => [
                'sanitizer' => 'email',
                'required' => true,
                'email' => true
            ],
            'phone' => [
                'sanitizer' => 'phone',
                'required' => false,
                'phone' => true
            ],
            'vip_discount_rate' => [
                'sanitizer' => 'float',
                'required' => false,
                'numeric' => true,
                'min_value' => 0,
                'max_value' => 1
            ]
        ];
    }
}

class CustomerUpdateResult {
    public function __construct(
        private Customer $customer,
        private string $message
    ) {}
    
    public function getCustomer(): Customer {
        return $this->customer;
    }
    
    public function getMessage(): string {
        return $this->message;
    }
}
```

### 6. Performance Anti-Patterns

#### 6.1 Current Problems
**Issues**:
- N+1 query problems
- Loading entire datasets when only small portions needed
- No caching of expensive operations
- Inefficient loops and algorithms

**Example of Performance Issues**:
```php
// PROBLEMATIC CODE - N+1 queries and inefficient loading
function display_customer_list() {
    $customers = get_all_customers(); // Loads ALL customers
    
    foreach ($customers as $customer) {
        // N+1 query problem - one query per customer
        $order_count = count(get_customer_orders($customer->id));
        $total_spent = get_customer_total_spent($customer->id);
        $vip_status = get_customer_vip_status($customer->id);
        
        echo "<tr>
            <td>{$customer->name}</td>
            <td>{$order_count}</td>
            <td>{$total_spent}</td>
            <td>{$vip_status}</td>
        </tr>";
    }
}
```

#### 6.2 Performance-Optimized Solution
```php
<?php
namespace APW\WooPlugin\Services\Customer;

class CustomerListService {
    private CustomerRepository $repository;
    private CacheServiceInterface $cache;
    
    public function __construct(CustomerRepository $repository, CacheServiceInterface $cache) {
        $this->repository = $repository;
        $this->cache = $cache;
    }
    
    public function getCustomerListData(int $page = 1, int $per_page = 50): CustomerListResult {
        $cache_key = "customer_list_data_{$page}_{$per_page}";
        
        $cached_result = $this->cache->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }
        
        // Single optimized query instead of N+1
        $customers = $this->repository->getCustomersWithAggregateData(
            offset: ($page - 1) * $per_page,
            limit: $per_page
        );
        
        $total_count = $this->repository->getTotalCustomerCount();
        
        $result = new CustomerListResult($customers, $total_count, $page, $per_page);
        
        // Cache for 5 minutes
        $this->cache->set($cache_key, $result, 300);
        
        return $result;
    }
}

class CustomerRepository extends BaseRepository {
    public function getCustomersWithAggregateData(int $offset = 0, int $limit = 50): array {
        $sql = "
            SELECT 
                c.id,
                c.first_name,
                c.last_name,
                c.email,
                c.is_vip,
                c.created_at,
                COALESCE(order_stats.order_count, 0) as order_count,
                COALESCE(order_stats.total_spent, 0) as total_spent,
                COALESCE(order_stats.last_order_date, NULL) as last_order_date
            FROM {$this->table_name} c
            LEFT JOIN (
                SELECT 
                    customer_id,
                    COUNT(*) as order_count,
                    SUM(total) as total_spent,
                    MAX(created_at) as last_order_date
                FROM {$this->wpdb->prefix}woocommerce_orders
                WHERE status IN ('completed', 'processing')
                GROUP BY customer_id
            ) order_stats ON c.id = order_stats.customer_id
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d
        ";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit, $offset),
            ARRAY_A
        );
    }
    
    public function getTotalCustomerCount(): int {
        $cache_key = 'total_customer_count';
        $cached_count = $this->cache->get($cache_key);
        
        if ($cached_count !== null) {
            return $cached_count;
        }
        
        $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Cache for 10 minutes
        $this->cache->set($cache_key, $count, 600);
        
        return $count;
    }
}
```

### 7. Security Vulnerabilities

#### 7.1 Current Problems
**Issues**:
- SQL injection vulnerabilities
- XSS vulnerabilities in output
- Missing nonce verification
- Unescaped user inputs

**Example of Security Issues**:
```php
// PROBLEMATIC CODE - Multiple security vulnerabilities
function search_customers($search_term) {
    global $wpdb;
    
    // SQL injection vulnerability!
    $results = $wpdb->get_results("
        SELECT * FROM wp_customers 
        WHERE name LIKE '%{$search_term}%'
    ");
    
    foreach ($results as $customer) {
        // XSS vulnerability!
        echo "<div>{$customer->name}</div>";
    }
}

function update_customer_ajax() {
    // No nonce verification!
    // No capability check!
    
    $customer_id = $_POST['customer_id']; // No sanitization!
    $data = $_POST; // Raw POST data!
    
    update_customer($customer_id, $data);
}
```

#### 7.2 Security-Hardened Solution
```php
<?php
namespace APW\WooPlugin\Controllers\Ajax;

class CustomerSearchController extends SecureAjaxController {
    private CustomerSearchService $search_service;
    
    public function __construct(
        NonceValidator $nonce_validator,
        CapabilityChecker $capability_checker,
        CustomerSearchService $search_service
    ) {
        parent::__construct($nonce_validator, $capability_checker);
        $this->search_service = $search_service;
    }
    
    public function handleCustomerSearch(): void {
        $this->handleAjaxRequest('search_customers', 'list_users');
    }
    
    protected function processRequest(): array {
        $request_validator = new RequestValidator();
        
        $validation_result = $request_validator->validateRequest([
            'nonce' => 'search_customers',
            'method' => 'POST',
            'rules' => [
                'search_term' => [
                    'sanitizer' => 'text',
                    'required' => true,
                    'min_length' => 2,
                    'max_length' => 100
                ],
                'page' => [
                    'sanitizer' => 'int',
                    'required' => false,
                    'numeric' => true,
                    'min_value' => 1
                ]
            ]
        ]);
        
        if (!$validation_result->isValid()) {
            throw new ValidationException(
                'Invalid search parameters',
                'Please check your search terms and try again',
                ['errors' => $validation_result->getErrors()]
            );
        }
        
        $sanitized_data = $validation_result->getData();
        $search_results = $this->search_service->searchCustomers(
            $sanitized_data['search_term'],
            $sanitized_data['page'] ?? 1
        );
        
        return [
            'customers' => $this->formatCustomersForOutput($search_results->getCustomers()),
            'pagination' => $search_results->getPaginationData()
        ];
    }
    
    private function formatCustomersForOutput(array $customers): array {
        return array_map(function($customer) {
            return [
                'id' => (int) $customer['id'],
                'name' => esc_html($customer['first_name'] . ' ' . $customer['last_name']),
                'email' => esc_html($customer['email']),
                'is_vip' => (bool) $customer['is_vip'],
                'order_count' => (int) $customer['order_count'],
                'total_spent' => wc_price($customer['total_spent'])
            ];
        }, $customers);
    }
}

class CustomerSearchService {
    private CustomerRepository $repository;
    
    public function __construct(CustomerRepository $repository) {
        $this->repository = $repository;
    }
    
    public function searchCustomers(string $search_term, int $page = 1): CustomerSearchResult {
        // Repository handles secure database queries
        $customers = $this->repository->searchByName($search_term, $page);
        $total_count = $this->repository->getSearchCount($search_term);
        
        return new CustomerSearchResult($customers, $total_count, $page);
    }
}
```

## Code Quality Automation

### 8. Quality Assurance Tools

#### 8.1 Automated Code Analysis
```bash
#!/bin/bash
# scripts/quality-check.sh

echo "=== APW WooCommerce Plugin Quality Check ==="

# PHP Code Sniffer - WordPress Coding Standards
echo "1. Checking WordPress coding standards..."
phpcs --standard=WordPress --extensions=php --ignore=vendor/,node_modules/ src/

# PHP Mess Detector - Code quality
echo "2. Analyzing code complexity and design..."
phpmd src/ text cleancode,codesize,controversial,design,naming,unusedcode

# PHPStan - Static analysis
echo "3. Running static analysis..."
phpstan analyse src/ --level=7

# Security scanner
echo "4. Scanning for security vulnerabilities..."
psalm --security-analysis src/

# Duplicate code detection
echo "5. Checking for duplicate code..."
phpcpd src/ --min-lines=5 --min-tokens=50

# Dependency analysis
echo "6. Analyzing dependencies..."
deptrac analyze

echo "Quality check complete!"
```

#### 8.2 Code Quality Metrics
```php
<?php
namespace APW\WooPlugin\Quality;

class QualityMetrics {
    public function generateMetricsReport(string $source_dir): array {
        return [
            'complexity' => $this->calculateCyclomaticComplexity($source_dir),
            'duplication' => $this->calculateCodeDuplication($source_dir),
            'coverage' => $this->getTestCoverage(),
            'maintainability' => $this->calculateMaintainabilityIndex($source_dir),
            'security' => $this->getSecurityScore($source_dir)
        ];
    }
    
    private function calculateCyclomaticComplexity(string $dir): array {
        // Use AST parsing to calculate complexity
        $files = glob($dir . '/**/*.php');
        $total_complexity = 0;
        $method_count = 0;
        $high_complexity_methods = [];
        
        foreach ($files as $file) {
            $parser = new \PhpParser\Parser\Php7($this->getLexer());
            $ast = $parser->parse(file_get_contents($file));
            
            $visitor = new ComplexityVisitor();
            $traverser = new \PhpParser\NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);
            
            foreach ($visitor->getComplexities() as $method => $complexity) {
                $total_complexity += $complexity;
                $method_count++;
                
                if ($complexity > 10) {
                    $high_complexity_methods[] = [
                        'file' => $file,
                        'method' => $method,
                        'complexity' => $complexity
                    ];
                }
            }
        }
        
        return [
            'average_complexity' => $method_count > 0 ? $total_complexity / $method_count : 0,
            'total_complexity' => $total_complexity,
            'method_count' => $method_count,
            'high_complexity_methods' => $high_complexity_methods
        ];
    }
}
```

## Success Criteria

### 9. Code Quality Gates
- [ ] **Duplication**: Less than 5% code duplication
- [ ] **Complexity**: Average cyclomatic complexity < 5
- [ ] **Standards**: 100% WordPress coding standards compliance
- [ ] **Type Safety**: All public methods have type hints
- [ ] **Validation**: All user inputs validated and sanitized
- [ ] **Error Handling**: Consistent error handling throughout
- [ ] **Security**: Zero critical or high security vulnerabilities
- [ ] **Performance**: No N+1 queries or major performance issues

### 10. Quality Metrics Targets
- [ ] **Maintainability Index**: > 80
- [ ] **Test Coverage**: > 90%
- [ ] **Documentation Coverage**: > 95%
- [ ] **Security Score**: A grade
- [ ] **Performance Score**: < 100ms average response time

## Next Steps
Upon completion:
1. Proceed to Success Metrics specification
2. Implement automated quality checks in CI/CD
3. Create code review guidelines and checklists
4. Establish quality monitoring and reporting