# Critical Architecture Problems Specification

## Overview
Address fundamental architectural issues in the current APW WooCommerce Plugin that prevent proper maintainability, scalability, and extensibility. These are structural problems that require immediate refactoring to ensure long-term viability.

## Critical Architecture Issues Identified

### 1. Monolithic Function-Based Architecture

#### 1.1 Current Problems
**Files**: 
- `apw-woo-plugin.php` (1,200+ lines)
- `includes/apw-woo-functions.php` (800+ lines)
- `includes/apw-woo-intuit-payment-functions.php` (600+ lines)

**Issues**:
- Single file contains multiple unrelated responsibilities
- Functions tightly coupled without clear separation of concerns
- No dependency injection or service container
- Global state management throughout
- Impossible to unit test individual components

#### 1.2 Root Cause Analysis
```php
// PROBLEMATIC CODE - Current Monolithic Structure
function apw_woo_calculate_pricing() {
    // Direct database access
    global $wpdb;
    $customer_data = $wpdb->get_results("SELECT * FROM ...");
    
    // Payment processing mixed with pricing
    if ($_POST['payment_method'] === 'credit_card') {
        $surcharge = calculate_surcharge();
        // More payment logic...
    }
    
    // VIP discount calculation mixed in
    if (is_vip_customer()) {
        $discount = calculate_vip_discount();
        // More discount logic...
    }
    
    // Cart manipulation
    WC()->cart->add_fee('Processing Fee', $surcharge);
    
    // Customer registration logic?!
    if (!is_user_logged_in()) {
        register_new_customer();
    }
}
```

**Problems**:
1. **Single Responsibility Violation**: Function handles pricing, payments, discounts, cart, and registration
2. **Hard Dependencies**: Direct global access, no injection
3. **Untestable**: Cannot mock dependencies or isolate functionality
4. **Inflexible**: Adding new features requires modifying existing functions

#### 1.3 Architectural Solution: Service-Oriented Design
```php
<?php
namespace APW\WooPlugin\Services\Pricing;

class PricingOrchestrator {
    private PricingServiceInterface $pricing_service;
    private PaymentServiceInterface $payment_service;
    private VIPDiscountServiceInterface $vip_service;
    private CartServiceInterface $cart_service;
    
    public function __construct(
        PricingServiceInterface $pricing_service,
        PaymentServiceInterface $payment_service,
        VIPDiscountServiceInterface $vip_service,
        CartServiceInterface $cart_service
    ) {
        $this->pricing_service = $pricing_service;
        $this->payment_service = $payment_service;
        $this->vip_service = $vip_service;
        $this->cart_service = $cart_service;
    }
    
    public function calculatePricing(PricingContext $context): PricingResult {
        // Each service handles its specific responsibility
        $base_pricing = $this->pricing_service->calculateBasePricing($context);
        $vip_adjustments = $this->vip_service->calculateVIPDiscount($context, $base_pricing);
        $payment_adjustments = $this->payment_service->calculateSurcharges($context, $base_pricing);
        
        return new PricingResult($base_pricing, $vip_adjustments, $payment_adjustments);
    }
}
```

### 2. No Dependency Injection Container

#### 2.1 Current Problems
**Issues**:
- Services instantiated with `new` keyword throughout codebase
- No way to replace implementations for testing
- Circular dependencies not detected
- Configuration scattered across multiple files

#### 2.2 Service Container Implementation
```php
<?php
namespace APW\WooPlugin\Core;

class ServiceContainer {
    private array $services = [];
    private array $instances = [];
    private array $singletons = [];
    
    public function bind(string $abstract, $concrete = null, bool $singleton = false): void {
        $this->services[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'singleton' => $singleton
        ];
    }
    
    public function singleton(string $abstract, $concrete = null): void {
        $this->bind($abstract, $concrete, true);
    }
    
    public function make(string $abstract) {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        $service_config = $this->services[$abstract] ?? null;
        
        if (!$service_config) {
            throw new ContainerException("Service {$abstract} not found");
        }
        
        $instance = $this->resolve($service_config['concrete']);
        
        if ($service_config['singleton']) {
            $this->instances[$abstract] = $instance;
        }
        
        return $instance;
    }
    
    private function resolve($concrete) {
        if (is_callable($concrete)) {
            return $concrete($this);
        }
        
        if (is_string($concrete)) {
            return $this->build($concrete);
        }
        
        return $concrete;
    }
    
    private function build(string $class) {
        $reflection = new \ReflectionClass($class);
        
        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class {$class} is not instantiable");
        }
        
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return new $class;
        }
        
        $dependencies = $this->resolveDependencies($constructor->getParameters());
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    private function resolveDependencies(array $parameters): array {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if (!$type || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException("Cannot resolve parameter {$parameter->getName()}");
                }
            } else {
                $dependencies[] = $this->make($type->getName());
            }
        }
        
        return $dependencies;
    }
}

class ContainerException extends \Exception {}
```

### 3. Global State Pollution

#### 3.1 Current Problems
**Issues**:
- Heavy reliance on global variables
- Session data stored in WordPress options
- No state encapsulation
- Race conditions in multi-user environments

#### 3.2 State Management Solution
```php
<?php
namespace APW\WooPlugin\State;

class StateManager {
    private array $state = [];
    private CacheServiceInterface $cache;
    
    public function __construct(CacheServiceInterface $cache) {
        $this->cache = $cache;
    }
    
    public function get(string $key, $default = null) {
        if (isset($this->state[$key])) {
            return $this->state[$key];
        }
        
        $cached_value = $this->cache->get($this->getCacheKey($key));
        
        if ($cached_value !== null) {
            $this->state[$key] = $cached_value;
            return $cached_value;
        }
        
        return $default;
    }
    
    public function set(string $key, $value, int $ttl = 3600): void {
        $this->state[$key] = $value;
        $this->cache->set($this->getCacheKey($key), $value, $ttl);
    }
    
    public function forget(string $key): void {
        unset($this->state[$key]);
        $this->cache->delete($this->getCacheKey($key));
    }
    
    private function getCacheKey(string $key): string {
        return 'apw_state_' . md5($key);
    }
}
```

### 4. Poor Database Abstraction

#### 4.1 Current Problems
**Issues**:
- Direct `$wpdb` usage throughout codebase
- Raw SQL queries with string concatenation
- No query caching or optimization
- No database transaction management

#### 4.2 Repository Pattern Implementation
```php
<?php
namespace APW\WooPlugin\Repositories;

abstract class BaseRepository {
    protected \wpdb $wpdb;
    protected CacheServiceInterface $cache;
    protected string $table_name;
    
    public function __construct(\wpdb $wpdb, CacheServiceInterface $cache) {
        $this->wpdb = $wpdb;
        $this->cache = $cache;
        $this->table_name = $this->getTableName();
    }
    
    abstract protected function getTableName(): string;
    
    protected function find(int $id): ?array {
        $cache_key = $this->getCacheKey('find', $id);
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );
        
        $this->cache->set($cache_key, $result, 3600);
        
        return $result;
    }
    
    protected function findBy(array $criteria, array $order_by = [], int $limit = null): array {
        $where_clauses = [];
        $values = [];
        
        foreach ($criteria as $column => $value) {
            $where_clauses[] = "{$column} = %s";
            $values[] = $value;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $order_sql = $this->buildOrderClause($order_by);
        $limit_sql = $limit ? "LIMIT {$limit}" : '';
        
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} {$order_sql} {$limit_sql}";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$values),
            ARRAY_A
        );
    }
    
    protected function create(array $data): int {
        $result = $this->wpdb->insert($this->table_name, $data);
        
        if ($result === false) {
            throw new DatabaseException("Failed to create record: " . $this->wpdb->last_error);
        }
        
        $this->invalidateCache();
        
        return $this->wpdb->insert_id;
    }
    
    protected function update(int $id, array $data): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id]
        );
        
        if ($result === false) {
            throw new DatabaseException("Failed to update record: " . $this->wpdb->last_error);
        }
        
        $this->invalidateCache();
        
        return true;
    }
    
    protected function delete(int $id): bool {
        $result = $this->wpdb->delete($this->table_name, ['id' => $id]);
        
        if ($result === false) {
            throw new DatabaseException("Failed to delete record: " . $this->wpdb->last_error);
        }
        
        $this->invalidateCache();
        
        return true;
    }
    
    private function buildOrderClause(array $order_by): string {
        if (empty($order_by)) {
            return '';
        }
        
        $clauses = [];
        foreach ($order_by as $column => $direction) {
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $clauses[] = "{$column} {$direction}";
        }
        
        return 'ORDER BY ' . implode(', ', $clauses);
    }
    
    private function getCacheKey(string $operation, ...$params): string {
        return 'apw_repo_' . $this->table_name . '_' . $operation . '_' . md5(serialize($params));
    }
    
    private function invalidateCache(): void {
        $this->cache->flush("apw_repo_{$this->table_name}_*");
    }
}

class CustomerRepository extends BaseRepository {
    protected function getTableName(): string {
        return $this->wpdb->prefix . 'apw_customers';
    }
    
    public function findByEmail(string $email): ?array {
        return $this->findBy(['email' => $email])[0] ?? null;
    }
    
    public function getVIPCustomers(): array {
        return $this->findBy(['is_vip' => 1], ['created_at' => 'DESC']);
    }
}
```

### 5. No Event System

#### 5.1 Current Problems
**Issues**:
- Direct function calls between unrelated components
- No way to extend functionality without modifying core code
- Tight coupling between features

#### 5.2 Event-Driven Architecture
```php
<?php
namespace APW\WooPlugin\Events;

class EventDispatcher {
    private array $listeners = [];
    
    public function listen(string $event, callable $listener, int $priority = 10): void {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        $this->listeners[$event][] = [
            'callback' => $listener,
            'priority' => $priority
        ];
        
        // Sort by priority
        usort($this->listeners[$event], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }
    
    public function dispatch(string $event, $payload = null) {
        if (!isset($this->listeners[$event])) {
            return $payload;
        }
        
        foreach ($this->listeners[$event] as $listener) {
            $result = call_user_func($listener['callback'], $payload);
            
            if ($result !== null) {
                $payload = $result;
            }
        }
        
        return $payload;
    }
}

// Example Usage
class CustomerService {
    private EventDispatcher $events;
    
    public function createCustomer(array $data): Customer {
        $customer = new Customer($data);
        
        // Dispatch event so other services can react
        $this->events->dispatch('customer.created', $customer);
        
        return $customer;
    }
}

// VIP Service can listen for customer creation
class VIPService {
    public function __construct(EventDispatcher $events) {
        $events->listen('customer.created', [$this, 'checkVIPEligibility']);
    }
    
    public function checkVIPEligibility(Customer $customer): void {
        // Check if customer qualifies for VIP status
    }
}
```

### 6. Configuration Management Issues

#### 6.1 Current Problems
**Issues**:
- Configuration scattered across multiple files
- Hard-coded values throughout codebase
- No environment-specific configuration
- No configuration validation

#### 6.2 Centralized Configuration System
```php
<?php
namespace APW\WooPlugin\Config;

class ConfigManager implements ConfigInterface {
    private array $config = [];
    private array $env_config = [];
    
    public function __construct() {
        $this->loadConfiguration();
    }
    
    public function get(string $key, $default = null) {
        return $this->getNestedValue($this->config, $key, $default);
    }
    
    public function set(string $key, $value): void {
        $this->setNestedValue($this->config, $key, $value);
    }
    
    public function has(string $key): bool {
        return $this->getNestedValue($this->config, $key) !== null;
    }
    
    public function all(): array {
        return $this->config;
    }
    
    private function loadConfiguration(): void {
        // Load default configuration
        $this->config = $this->getDefaultConfig();
        
        // Override with environment-specific config
        $env = $this->getEnvironment();
        $env_config_file = APW_WOO_PLUGIN_DIR . "config/{$env}.php";
        
        if (file_exists($env_config_file)) {
            $env_config = include $env_config_file;
            $this->config = array_merge_recursive($this->config, $env_config);
        }
        
        // Override with WordPress options
        $saved_config = get_option('apw_woo_config', []);
        $this->config = array_merge_recursive($this->config, $saved_config);
    }
    
    private function getDefaultConfig(): array {
        return [
            'payment' => [
                'surcharges' => [
                    'intuit_qbms_credit_card' => 0.03,
                    'stripe' => 0.029,
                    'paypal' => 0.035
                ],
                'minimum_order' => 10.00,
                'maximum_surcharge' => 50.00
            ],
            'vip' => [
                'qualification_thresholds' => [
                    'bronze' => ['spent' => 1000, 'orders' => 3],
                    'silver' => ['spent' => 2500, 'orders' => 5],
                    'gold' => ['spent' => 5000, 'orders' => 10]
                ],
                'discount_rates' => [
                    'bronze' => [100 => 0.05, 0 => 0.03],
                    'silver' => [300 => 0.08, 100 => 0.05],
                    'gold' => [500 => 0.12, 300 => 0.10]
                ]
            ],
            'cache' => [
                'default_ttl' => 3600,
                'customer_data_ttl' => 1800,
                'pricing_ttl' => 900
            ],
            'logging' => [
                'level' => 'info',
                'max_files' => 10,
                'max_file_size' => '10MB'
            ]
        ];
    }
    
    private function getEnvironment(): string {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }
        
        return wp_get_environment_type();
    }
    
    private function getNestedValue(array $array, string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $nested_key) {
            if (!is_array($value) || !array_key_exists($nested_key, $value)) {
                return $default;
            }
            $value = $value[$nested_key];
        }
        
        return $value;
    }
    
    private function setNestedValue(array &$array, string $key, $value): void {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $nested_key) {
            if (!isset($current[$nested_key]) || !is_array($current[$nested_key])) {
                $current[$nested_key] = [];
            }
            $current = &$current[$nested_key];
        }
        
        $current = $value;
    }
}
```

## Migration Strategy

### 7. Phased Refactoring Approach

#### 7.1 Phase 1: Service Layer Introduction
1. **Create Service Interfaces** - Define contracts for all major services
2. **Implement Core Services** - Payment, Pricing, Customer services
3. **Service Container Setup** - Dependency injection infrastructure
4. **Backward Compatibility** - Maintain existing function-based API

#### 7.2 Phase 2: Repository Pattern Implementation
1. **Database Abstraction** - Create repository classes
2. **Query Optimization** - Add caching and query improvements
3. **Migration Scripts** - Safely move from direct $wpdb usage

#### 7.3 Phase 3: Event System Integration
1. **Event Infrastructure** - Event dispatcher and listener system
2. **Decouple Components** - Replace direct calls with events
3. **Extension Points** - Create hooks for third-party extensions

#### 7.4 Phase 4: Legacy Cleanup
1. **Remove Old Functions** - Phase out function-based architecture
2. **Code Cleanup** - Remove unused code and consolidate duplicates
3. **Performance Optimization** - Optimize based on new architecture

## Testing Strategy

### 8. Architecture Testing

#### 8.1 Dependency Testing
```php
<?php
namespace APW\WooPlugin\Tests\Architecture;

class DependencyTest extends TestCase {
    public function testServiceContainerResolvesDependencies(): void {
        $container = new ServiceContainer();
        
        // Register services
        $container->singleton(ConfigInterface::class, ConfigManager::class);
        $container->singleton(LoggerServiceInterface::class, LoggerService::class);
        $container->bind(PricingServiceInterface::class, PricingService::class);
        
        // Test resolution
        $pricing_service = $container->make(PricingServiceInterface::class);
        
        $this->assertInstanceOf(PricingService::class, $pricing_service);
    }
    
    public function testCircularDependencyDetection(): void {
        $container = new ServiceContainer();
        
        // This should throw an exception
        $this->expectException(ContainerException::class);
        $container->make(CircularDependencyA::class);
    }
}
```

#### 8.2 Event System Testing
```php
<?php
class EventSystemTest extends TestCase {
    public function testEventDispatchingAndListening(): void {
        $dispatcher = new EventDispatcher();
        $called = false;
        
        $dispatcher->listen('test.event', function() use (&$called) {
            $called = true;
        });
        
        $dispatcher->dispatch('test.event');
        
        $this->assertTrue($called);
    }
}
```

## Success Criteria

### 9. Architecture Quality Metrics
- [ ] **Service Isolation**: All major functionality in separate services
- [ ] **Dependency Injection**: No direct instantiation with `new` keyword
- [ ] **Event Decoupling**: Components communicate through events
- [ ] **Repository Pattern**: All database access through repositories
- [ ] **Configuration Management**: Centralized, environment-aware configuration
- [ ] **Testability**: 100% unit test coverage of new architecture
- [ ] **Performance**: No degradation from architectural changes
- [ ] **Backward Compatibility**: Existing function-based API still works

### 10. Code Quality Gates
- [ ] **Cyclomatic Complexity**: Maximum complexity of 10 per method
- [ ] **Class Size**: Maximum 300 lines per class
- [ ] **Method Size**: Maximum 50 lines per method
- [ ] **Coupling**: Low coupling between modules
- [ ] **Cohesion**: High cohesion within modules

## Next Steps
Upon completion:
1. Proceed to Critical Code Quality specification
2. Implement migration scripts for gradual refactoring
3. Create architectural documentation and diagrams
4. Establish code review process for architectural compliance