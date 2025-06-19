# Phase 2: Modern Architecture Implementation Specification

## Overview
Transform the APW WooCommerce Plugin from a collection of procedural functions into a modern, object-oriented architecture using industry best practices.

## Architecture Goals

### 2.1 Design Principles
- **Single Responsibility Principle**: Each class has one reason to change
- **Open/Closed Principle**: Open for extension, closed for modification
- **Dependency Inversion**: Depend on abstractions, not concretions
- **Interface Segregation**: Many specific interfaces vs. one general interface
- **Don't Repeat Yourself**: Eliminate code duplication

### 2.2 Architecture Patterns
- **Service Container**: Centralized dependency management
- **Factory Pattern**: Create objects without specifying exact classes
- **Observer Pattern**: Event-driven communications
- **Strategy Pattern**: Interchangeable algorithms
- **Template Method**: Define algorithm skeleton, subclasses implement steps

## Implementation Specifications

### 2.3 PSR-4 Autoloading Implementation

#### 2.3.1 Directory Structure
```
includes/
├── src/                          # PSR-4 compliant source code
│   ├── APW/                      # Vendor namespace
│   │   ├── WooPlugin/            # Package namespace
│   │   │   ├── Core/             # Core functionality
│   │   │   ├── Services/         # Business logic services
│   │   │   ├── Integrations/     # Third-party integrations
│   │   │   ├── Controllers/      # Request handling
│   │   │   ├── Models/           # Data models
│   │   │   ├── Views/            # Presentation layer
│   │   │   └── Interfaces/       # Contracts
│   │   └── Common/               # Shared utilities
├── vendor/                       # Composer dependencies
└── autoload.php                 # PSR-4 autoloader
```

#### 2.3.2 Autoloader Implementation
```php
<?php
// includes/autoload.php

/**
 * PSR-4 Autoloader for APW WooCommerce Plugin
 */
class APW_Autoloader {
    private static $namespace_map = [
        'APW\\WooPlugin\\' => __DIR__ . '/src/APW/WooPlugin/',
        'APW\\Common\\' => __DIR__ . '/src/APW/Common/'
    ];

    public static function register() {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload($class) {
        foreach (self::$namespace_map as $namespace => $directory) {
            if (strpos($class, $namespace) === 0) {
                $relative_class = substr($class, strlen($namespace));
                $file = $directory . str_replace('\\', '/', $relative_class) . '.php';
                
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }
        return false;
    }
}

APW_Autoloader::register();
```

#### 2.3.3 Namespace Conventions
```php
<?php
// Core Services
namespace APW\WooPlugin\Services;

// Integration Classes  
namespace APW\WooPlugin\Integrations\WooCommerce;
namespace APW\WooPlugin\Integrations\ACF;

// Controllers
namespace APW\WooPlugin\Controllers\Admin;
namespace APW\WooPlugin\Controllers\Frontend;

// Models
namespace APW\WooPlugin\Models\Customer;
namespace APW\WooPlugin\Models\Payment;
```

### 2.4 Service Container Implementation

#### 2.4.1 Container Interface
```php
<?php
namespace APW\WooPlugin\Core;

interface ContainerInterface {
    public function bind(string $abstract, $concrete = null): void;
    public function singleton(string $abstract, $concrete = null): void;
    public function make(string $abstract, array $parameters = []);
    public function instance(string $abstract, $instance): void;
    public function has(string $abstract): bool;
}
```

#### 2.4.2 Service Container Implementation
```php
<?php
namespace APW\WooPlugin\Core;

class ServiceContainer implements ContainerInterface {
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];

    public function bind(string $abstract, $concrete = null): void {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => false
        ];
    }

    public function singleton(string $abstract, $concrete = null): void {
        $this->bind($abstract, $concrete);
        $this->bindings[$abstract]['shared'] = true;
    }

    public function make(string $abstract, array $parameters = []) {
        // Return existing instance for singletons
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);
        $object = $this->build($concrete, $parameters);

        // Store singleton instances
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    private function build($concrete, array $parameters = []) {
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }

        $reflection = new \ReflectionClass($concrete);
        
        if (!$reflection->isInstantiable()) {
            throw new \Exception("Class {$concrete} is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        
        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());
        
        return $reflection->newInstanceArgs($dependencies);
    }

    private function resolveDependencies(array $parameters): array {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve dependency {$parameter->getName()}");
            }
        }
        
        return $dependencies;
    }
}
```

#### 2.4.3 Service Registration
```php
<?php
namespace APW\WooPlugin\Core;

class ServiceProvider {
    protected ServiceContainer $container;

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    public function register(): void {
        // Core Services
        $this->container->singleton(
            'APW\WooPlugin\Services\LoggerService',
            function($container) {
                return new \APW\WooPlugin\Services\LoggerService(
                    APW_WOO_PLUGIN_DIR . 'logs/'
                );
            }
        );

        $this->container->singleton(
            'APW\WooPlugin\Services\PaymentGatewayService',
            \APW\WooPlugin\Services\PaymentGatewayService::class
        );

        $this->container->singleton(
            'APW\WooPlugin\Services\PricingService',
            \APW\WooPlugin\Services\PricingService::class
        );

        // Integration Services
        $this->container->singleton(
            'APW\WooPlugin\Integrations\WooCommerce\HookManager',
            \APW\WooPlugin\Integrations\WooCommerce\HookManager::class
        );
    }
}
```

### 2.5 MVC Pattern Implementation

#### 2.5.1 Controller Base Class
```php
<?php
namespace APW\WooPlugin\Controllers;

abstract class BaseController {
    protected ServiceContainer $container;

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    protected function service(string $service) {
        return $this->container->make($service);
    }

    protected function view(string $template, array $data = []): string {
        return $this->service('APW\WooPlugin\Services\TemplateService')
            ->render($template, $data);
    }

    protected function redirect(string $url): void {
        wp_redirect($url);
        exit;
    }

    protected function json_response($data, int $status = 200): void {
        wp_send_json($data, $status);
    }
}
```

#### 2.5.2 Admin Controller Example
```php
<?php
namespace APW\WooPlugin\Controllers\Admin;

use APW\WooPlugin\Controllers\BaseController;

class ReferralExportController extends BaseController {
    public function index(): void {
        $data = [
            'total_referrals' => $this->service('APW\WooPlugin\Services\CustomerService')
                ->getTotalReferrals(),
            'recent_exports' => $this->service('APW\WooPlugin\Services\ExportService')
                ->getRecentExports()
        ];

        echo $this->view('admin/referral-export/index', $data);
    }

    public function export(): void {
        $export_type = sanitize_text_field($_POST['export_type'] ?? '');
        
        $result = $this->service('APW\WooPlugin\Services\ExportService')
            ->exportReferrals($export_type);
            
        $this->json_response($result);
    }
}
```

#### 2.5.3 Model Base Class
```php
<?php
namespace APW\WooPlugin\Models;

abstract class BaseModel {
    protected string $table_name;
    protected string $primary_key = 'id';
    protected array $fillable = [];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function find(int $id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = %d",
                $id
            )
        );
    }

    public function create(array $data) {
        $data = $this->filterFillable($data);
        
        $this->wpdb->insert($this->table_name, $data);
        
        return $this->find($this->wpdb->insert_id);
    }

    protected function filterFillable(array $data): array {
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
```

### 2.6 Factory Pattern Implementation

#### 2.6.1 Integration Factory
```php
<?php
namespace APW\WooPlugin\Factories;

use APW\WooPlugin\Integrations\IntegrationInterface;

class IntegrationFactory {
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    public function create(string $type): IntegrationInterface {
        switch ($type) {
            case 'woocommerce':
                return $this->container->make('APW\WooPlugin\Integrations\WooCommerceIntegration');
            case 'acf':
                return $this->container->make('APW\WooPlugin\Integrations\ACFIntegration');
            case 'dynamic_pricing':
                return $this->container->make('APW\WooPlugin\Integrations\DynamicPricingIntegration');
            default:
                throw new \InvalidArgumentException("Unknown integration type: {$type}");
        }
    }
}
```

#### 2.6.2 Payment Gateway Factory
```php
<?php
namespace APW\WooPlugin\Factories;

use APW\WooPlugin\Services\Payment\PaymentGatewayInterface;

class PaymentGatewayFactory {
    public function create(string $gateway_id): PaymentGatewayInterface {
        switch ($gateway_id) {
            case 'intuit_qbms_credit_card':
                return new \APW\WooPlugin\Services\Payment\IntuitGateway();
            case 'paypal':
                return new \APW\WooPlugin\Services\Payment\PayPalGateway();
            default:
                return new \APW\WooPlugin\Services\Payment\DefaultGateway();
        }
    }
}
```

### 2.7 Configuration Management

#### 2.7.1 Configuration Interface
```php
<?php
namespace APW\WooPlugin\Core;

interface ConfigInterface {
    public function get(string $key, $default = null);
    public function set(string $key, $value): void;
    public function has(string $key): bool;
    public function all(): array;
}
```

#### 2.7.2 Configuration Implementation
```php
<?php
namespace APW\WooPlugin\Core;

class Configuration implements ConfigInterface {
    private array $config = [];

    public function __construct(array $config = []) {
        $this->config = array_merge($this->getDefaults(), $config);
    }

    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void {
        $this->config[$key] = $value;
    }

    private function getDefaults(): array {
        return [
            'debug_mode' => defined('APW_WOO_DEBUG_MODE') ? APW_WOO_DEBUG_MODE : false,
            'plugin_version' => APW_WOO_VERSION,
            'plugin_dir' => APW_WOO_PLUGIN_DIR,
            'plugin_url' => APW_WOO_PLUGIN_URL,
            'surcharge_rate' => 0.03,
            'log_retention_days' => 30
        ];
    }
}
```

## Migration Strategy

### 2.8 Gradual Migration Approach

#### Phase 2A: Core Infrastructure
1. **Implement autoloader** - Replace manual file inclusion
2. **Create service container** - Central dependency management
3. **Establish configuration system** - Centralized settings
4. **Implement logging service** - Replace scattered logging

#### Phase 2B: Service Layer
1. **Create base services** - Logger, Config, Template
2. **Implement core services** - Payment, Pricing, Customer
3. **Develop integration services** - WooCommerce, ACF, third-party

#### Phase 2C: Controller Layer
1. **Create base controller** - Common functionality
2. **Implement admin controllers** - Backend management
3. **Develop frontend controllers** - Customer-facing features

#### Phase 2D: Model Layer
1. **Create base model** - Database abstraction
2. **Implement domain models** - Customer, Order, Product
3. **Add validation layer** - Data integrity

### 2.9 Backward Compatibility

#### Legacy Function Wrappers
```php
<?php
// Maintain backward compatibility during transition

function apw_woo_log($message, $level = 'info') {
    static $logger = null;
    
    if ($logger === null) {
        $container = apw_get_container();
        $logger = $container->make('APW\WooPlugin\Services\LoggerService');
    }
    
    $logger->log($message, $level);
}

function apw_get_container(): ServiceContainer {
    return APW_WooPlugin::getInstance()->getContainer();
}
```

## Testing Strategy

### 2.10 Unit Testing Framework
```php
<?php
namespace APW\WooPlugin\Tests;

use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase {
    private ServiceContainer $container;

    protected function setUp(): void {
        $this->container = new ServiceContainer();
    }

    public function testBindingResolution(): void {
        $this->container->bind('test', function() {
            return 'resolved';
        });

        $this->assertEquals('resolved', $this->container->make('test'));
    }

    public function testSingletonInstance(): void {
        $this->container->singleton('singleton_test', function() {
            return new \stdClass();
        });

        $instance1 = $this->container->make('singleton_test');
        $instance2 = $this->container->make('singleton_test');

        $this->assertSame($instance1, $instance2);
    }
}
```

## Success Criteria

### 2.11 Implementation Checklist
- [ ] PSR-4 autoloader implemented and tested
- [ ] Service container functional with dependency injection
- [ ] MVC pattern established with clear separation
- [ ] Factory patterns implemented for object creation
- [ ] Configuration management system operational
- [ ] Backward compatibility maintained
- [ ] Unit tests passing for core components
- [ ] Documentation updated for new architecture

### 2.12 Quality Gates
- **Code Coverage**: >80% for core components
- **Performance**: No degradation from current implementation
- **Maintainability**: Reduced cyclomatic complexity
- **Extensibility**: Easy to add new features without modifying existing code

## Next Steps
Upon completion:
1. Proceed to Phase 2: Core Services Implementation
2. Begin migration of existing functionality to new architecture
3. Establish continuous integration for quality assurance
4. Update development documentation