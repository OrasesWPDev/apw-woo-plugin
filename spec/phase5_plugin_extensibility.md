# Phase 5: Plugin Extensibility Implementation Specification

## Overview
Design and implement a flexible plugin architecture that allows for easy extension, customization, and future feature additions without modifying core plugin code.

## Extensibility Objectives

### 5.1 Extensibility Goals
- **Plugin-Based Architecture**: Modular components that can be extended
- **Hook System**: Comprehensive WordPress hooks for customization
- **Service Container**: Dependency injection for easy service replacement
- **Event System**: Decoupled event-driven architecture
- **Theme Integration**: Seamless theme compatibility and customization
- **Developer-Friendly**: Clear APIs for third-party developers

## Extension Architecture

### 5.2 Extension System Structure
```
src/APW/WooPlugin/Extensions/
├── Core/
│   ├── ExtensionManager.php
│   ├── ExtensionInterface.php
│   ├── AbstractExtension.php
│   └── ExtensionRegistry.php
├── Hooks/
│   ├── HookManager.php
│   ├── ActionRegistry.php
│   └── FilterRegistry.php
├── Events/
│   ├── EventDispatcher.php
│   ├── EventSubscriberInterface.php
│   └── Events/
│       ├── CustomerRegisteredEvent.php
│       ├── VIPDiscountAppliedEvent.php
│       └── SurchargeCalculatedEvent.php
└── Examples/
    ├── CustomPricingExtension.php
    ├── NotificationExtension.php
    └── ReportingExtension.php
```

## Extension Manager

### 5.3 Core Extension System

#### 5.3.1 Extension Interface
```php
<?php
namespace APW\WooPlugin\Extensions\Core;

interface ExtensionInterface {
    public function getName(): string;
    public function getVersion(): string;
    public function getDescription(): string;
    public function getDependencies(): array;
    public function isCompatible(): bool;
    public function register(): void;
    public function activate(): void;
    public function deactivate(): void;
}
```

#### 5.3.2 Abstract Extension Base
```php
<?php
namespace APW\WooPlugin\Extensions\Core;

use APW\WooPlugin\Core\ServiceContainer;
use APW\WooPlugin\Services\LoggerServiceInterface;

abstract class AbstractExtension implements ExtensionInterface {
    protected ServiceContainer $container;
    protected LoggerServiceInterface $logger;
    protected array $config = [];
    
    public function __construct(ServiceContainer $container) {
        $this->container = $container;
        $this->logger = $container->make('LoggerServiceInterface');
        $this->config = $this->getDefaultConfig();
    }
    
    public function isCompatible(): bool {
        $dependencies = $this->getDependencies();
        
        foreach ($dependencies as $dependency) {
            if (!$this->checkDependency($dependency)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function register(): void {
        if (!$this->isCompatible()) {
            $this->logger->warning("Extension {$this->getName()} dependencies not met");
            return;
        }
        
        $this->registerServices();
        $this->registerHooks();
        $this->registerEventListeners();
    }
    
    protected function registerServices(): void {
        // Override in concrete extensions
    }
    
    protected function registerHooks(): void {
        // Override in concrete extensions
    }
    
    protected function registerEventListeners(): void {
        // Override in concrete extensions
    }
    
    protected function getDefaultConfig(): array {
        return [];
    }
    
    private function checkDependency(array $dependency): bool {
        switch ($dependency['type']) {
            case 'plugin':
                return class_exists($dependency['class']);
            case 'service':
                return $this->container->has($dependency['service']);
            case 'php_version':
                return version_compare(PHP_VERSION, $dependency['version'], '>=');
            default:
                return false;
        }
    }
}
```

#### 5.3.3 Extension Manager
```php
<?php
namespace APW\WooPlugin\Extensions\Core;

use APW\WooPlugin\Core\ServiceContainer;
use APW\WooPlugin\Services\LoggerServiceInterface;

class ExtensionManager {
    private ServiceContainer $container;
    private LoggerServiceInterface $logger;
    private ExtensionRegistry $registry;
    private array $loaded_extensions = [];
    
    public function __construct(ServiceContainer $container, ExtensionRegistry $registry) {
        $this->container = $container;
        $this->logger = $container->make('LoggerServiceInterface');
        $this->registry = $registry;
    }
    
    public function discoverExtensions(): void {
        // Auto-discover extensions in extensions directory
        $extension_dirs = [
            APW_WOO_PLUGIN_DIR . 'extensions/',
            WP_CONTENT_DIR . '/apw-extensions/',
            get_template_directory() . '/apw-extensions/'
        ];
        
        foreach ($extension_dirs as $dir) {
            if (is_dir($dir)) {
                $this->scanDirectory($dir);
            }
        }
        
        // Allow themes and plugins to register extensions
        do_action('apw_woo_register_extensions', $this);
    }
    
    public function registerExtension(string $extension_class): void {
        if (!class_exists($extension_class)) {
            $this->logger->error("Extension class not found: {$extension_class}");
            return;
        }
        
        if (!is_subclass_of($extension_class, ExtensionInterface::class)) {
            $this->logger->error("Invalid extension class: {$extension_class}");
            return;
        }
        
        $this->registry->register($extension_class);
    }
    
    public function loadExtensions(): void {
        $extensions = $this->registry->getExtensions();
        
        // Sort by dependencies
        $sorted_extensions = $this->sortByDependencies($extensions);
        
        foreach ($sorted_extensions as $extension_class) {
            $this->loadExtension($extension_class);
        }
    }
    
    private function loadExtension(string $extension_class): void {
        try {
            $extension = new $extension_class($this->container);
            
            if (!$extension->isCompatible()) {
                $this->logger->warning("Extension {$extension->getName()} not compatible");
                return;
            }
            
            $extension->register();
            $this->loaded_extensions[$extension->getName()] = $extension;
            
            $this->logger->info("Loaded extension: {$extension->getName()}");
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to load extension {$extension_class}: {$e->getMessage()}");
        }
    }
    
    public function getLoadedExtensions(): array {
        return $this->loaded_extensions;
    }
    
    public function isExtensionLoaded(string $name): bool {
        return isset($this->loaded_extensions[$name]);
    }
    
    private function scanDirectory(string $dir): void {
        $files = glob($dir . '*/extension.php');
        
        foreach ($files as $file) {
            include_once $file;
        }
    }
    
    private function sortByDependencies(array $extensions): array {
        // Simple topological sort for dependency order
        $sorted = [];
        $visited = [];
        
        foreach ($extensions as $extension) {
            $this->visitExtension($extension, $extensions, $visited, $sorted);
        }
        
        return $sorted;
    }
    
    private function visitExtension(string $extension, array $all_extensions, array &$visited, array &$sorted): void {
        if (isset($visited[$extension])) {
            return;
        }
        
        $visited[$extension] = true;
        
        // Visit dependencies first
        $instance = new $extension($this->container);
        foreach ($instance->getDependencies() as $dependency) {
            if ($dependency['type'] === 'extension' && in_array($dependency['name'], $all_extensions)) {
                $this->visitExtension($dependency['name'], $all_extensions, $visited, $sorted);
            }
        }
        
        $sorted[] = $extension;
    }
}
```

## Hook System Enhancement

### 5.4 Enhanced Hook Management

#### 5.4.1 Hook Manager with Priority Management
```php
<?php
namespace APW\WooPlugin\Extensions\Hooks;

class HookManager {
    private ActionRegistry $action_registry;
    private FilterRegistry $filter_registry;
    
    public function __construct(ActionRegistry $action_registry, FilterRegistry $filter_registry) {
        $this->action_registry = $action_registry;
        $this->filter_registry = $filter_registry;
    }
    
    public function registerExtensionHooks(): void {
        // Core pricing hooks
        $this->registerPricingHooks();
        
        // Customer management hooks
        $this->registerCustomerHooks();
        
        // Payment processing hooks
        $this->registerPaymentHooks();
        
        // Admin interface hooks
        $this->registerAdminHooks();
        
        // Extension system hooks
        $this->registerExtensionHooks();
    }
    
    private function registerPricingHooks(): void {
        // Allow extensions to modify VIP discount calculation
        add_filter('apw_woo_vip_discount_calculation', function($discount_data, $customer_id, $cart_total) {
            return apply_filters('apw_woo_extension_vip_discount', $discount_data, $customer_id, $cart_total);
        }, 10, 3);
        
        // Allow custom pricing rules
        add_filter('apw_woo_product_pricing_rules', function($rules, $product_id) {
            return apply_filters('apw_woo_extension_pricing_rules', $rules, $product_id);
        }, 10, 2);
        
        // Price modification hook
        add_filter('apw_woo_calculated_price', function($price, $product_id, $context) {
            return apply_filters('apw_woo_extension_price_modifier', $price, $product_id, $context);
        }, 10, 3);
    }
    
    private function registerCustomerHooks(): void {
        // Customer registration extension points
        add_action('apw_woo_customer_registered', function($customer_id, $registration_data) {
            do_action('apw_woo_extension_customer_registered', $customer_id, $registration_data);
        }, 10, 2);
        
        // VIP status change notifications
        add_action('apw_woo_vip_status_changed', function($customer_id, $old_status, $new_status) {
            do_action('apw_woo_extension_vip_status_changed', $customer_id, $old_status, $new_status);
        }, 10, 3);
        
        // Customer data export hooks
        add_filter('apw_woo_customer_export_data', function($data, $customer_id) {
            return apply_filters('apw_woo_extension_export_data', $data, $customer_id);
        }, 10, 2);
    }
}
```

#### 5.4.2 Custom Hook Registry
```php
<?php
namespace APW\WooPlugin\Extensions\Hooks;

class ActionRegistry {
    private array $registered_actions = [];
    
    public function registerAction(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->registered_actions[] = [
            'hook' => $hook_name,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $accepted_args
        ];
        
        add_action($hook_name, $callback, $priority, $accepted_args);
    }
    
    public function getRegisteredActions(): array {
        return $this->registered_actions;
    }
    
    public function hasAction(string $hook_name): bool {
        foreach ($this->registered_actions as $action) {
            if ($action['hook'] === $hook_name) {
                return true;
            }
        }
        return false;
    }
}
```

## Event System

### 5.5 Event-Driven Architecture

#### 5.5.1 Event Dispatcher
```php
<?php
namespace APW\WooPlugin\Extensions\Events;

class EventDispatcher {
    private array $listeners = [];
    private array $subscribers = [];
    
    public function addListener(string $event_name, callable $listener, int $priority = 10): void {
        if (!isset($this->listeners[$event_name])) {
            $this->listeners[$event_name] = [];
        }
        
        $this->listeners[$event_name][] = [
            'callback' => $listener,
            'priority' => $priority
        ];
        
        // Sort by priority
        usort($this->listeners[$event_name], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }
    
    public function addSubscriber(EventSubscriberInterface $subscriber): void {
        $this->subscribers[] = $subscriber;
        
        foreach ($subscriber->getSubscribedEvents() as $event_name => $config) {
            if (is_string($config)) {
                $this->addListener($event_name, [$subscriber, $config]);
            } elseif (is_array($config)) {
                $this->addListener(
                    $event_name, 
                    [$subscriber, $config['method']], 
                    $config['priority'] ?? 10
                );
            }
        }
    }
    
    public function dispatch(string $event_name, $event = null) {
        if (!isset($this->listeners[$event_name])) {
            return $event;
        }
        
        foreach ($this->listeners[$event_name] as $listener) {
            $result = call_user_func($listener['callback'], $event);
            
            // If event is stoppable and stopped, break
            if ($event && method_exists($event, 'isPropagationStopped') && $event->isPropagationStopped()) {
                break;
            }
            
            // If listener returns something, use it as the event
            if ($result !== null) {
                $event = $result;
            }
        }
        
        return $event;
    }
}
```

#### 5.5.2 Event Classes
```php
<?php
namespace APW\WooPlugin\Extensions\Events\Events;

class CustomerRegisteredEvent {
    private int $customer_id;
    private array $registration_data;
    private bool $propagation_stopped = false;
    
    public function __construct(int $customer_id, array $registration_data) {
        $this->customer_id = $customer_id;
        $this->registration_data = $registration_data;
    }
    
    public function getCustomerId(): int {
        return $this->customer_id;
    }
    
    public function getRegistrationData(): array {
        return $this->registration_data;
    }
    
    public function setRegistrationData(array $data): void {
        $this->registration_data = $data;
    }
    
    public function stopPropagation(): void {
        $this->propagation_stopped = true;
    }
    
    public function isPropagationStopped(): bool {
        return $this->propagation_stopped;
    }
}
```

```php
<?php
namespace APW\WooPlugin\Extensions\Events\Events;

class VIPDiscountAppliedEvent {
    private int $customer_id;
    private float $discount_amount;
    private float $cart_total;
    private string $discount_type;
    
    public function __construct(int $customer_id, float $discount_amount, float $cart_total, string $discount_type = 'vip') {
        $this->customer_id = $customer_id;
        $this->discount_amount = $discount_amount;
        $this->cart_total = $cart_total;
        $this->discount_type = $discount_type;
    }
    
    public function getCustomerId(): int {
        return $this->customer_id;
    }
    
    public function getDiscountAmount(): float {
        return $this->discount_amount;
    }
    
    public function getCartTotal(): float {
        return $this->cart_total;
    }
    
    public function getDiscountType(): string {
        return $this->discount_type;
    }
}
```

## Example Extensions

### 5.6 Custom Pricing Extension Example

#### 5.6.1 Bulk Order Discount Extension
```php
<?php
namespace APW\WooPlugin\Extensions\Examples;

use APW\WooPlugin\Extensions\Core\AbstractExtension;
use APW\WooPlugin\Extensions\Events\EventSubscriberInterface;
use APW\WooPlugin\Extensions\Events\Events\VIPDiscountAppliedEvent;

class BulkOrderDiscountExtension extends AbstractExtension implements EventSubscriberInterface {
    public function getName(): string {
        return 'Bulk Order Discount';
    }
    
    public function getVersion(): string {
        return '1.0.0';
    }
    
    public function getDescription(): string {
        return 'Provides additional discounts for bulk orders over specified quantities';
    }
    
    public function getDependencies(): array {
        return [
            ['type' => 'service', 'service' => 'PricingServiceInterface'],
            ['type' => 'php_version', 'version' => '8.1']
        ];
    }
    
    protected function getDefaultConfig(): array {
        return [
            'bulk_thresholds' => [
                50 => 0.05,   // 5% discount for 50+ items
                100 => 0.10,  // 10% discount for 100+ items
                200 => 0.15   // 15% discount for 200+ items
            ],
            'excluded_categories' => []
        ];
    }
    
    protected function registerHooks(): void {
        add_action('woocommerce_before_calculate_totals', [$this, 'applyBulkDiscounts'], 12);
        add_filter('apw_woo_extension_pricing_rules', [$this, 'addBulkPricingRules'], 10, 2);
    }
    
    public function getSubscribedEvents(): array {
        return [
            'apw_woo_cart_calculation_complete' => [
                'method' => 'onCartCalculationComplete',
                'priority' => 5
            ]
        ];
    }
    
    public function applyBulkDiscounts($cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $total_quantity = 0;
        $eligible_items = [];
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            
            if (!$this->isProductEligible($product_id)) {
                continue;
            }
            
            $total_quantity += $cart_item['quantity'];
            $eligible_items[] = $cart_item_key;
        }
        
        $discount_rate = $this->getBulkDiscountRate($total_quantity);
        
        if ($discount_rate > 0 && !empty($eligible_items)) {
            $this->applyDiscountToItems($cart, $eligible_items, $discount_rate);
        }
    }
    
    public function addBulkPricingRules(array $rules, int $product_id): array {
        if ($this->isProductEligible($product_id)) {
            $rules['bulk_discount'] = [
                'type' => 'bulk_quantity',
                'thresholds' => $this->config['bulk_thresholds']
            ];
        }
        
        return $rules;
    }
    
    public function onCartCalculationComplete($event): void {
        // Log bulk discount application for analytics
        $this->logger->info('Bulk discount calculation completed', [
            'cart_total' => WC()->cart->get_total(),
            'item_count' => WC()->cart->get_cart_contents_count()
        ]);
    }
    
    private function isProductEligible(int $product_id): bool {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $excluded = $this->config['excluded_categories'];
        
        return empty(array_intersect($categories, $excluded));
    }
    
    private function getBulkDiscountRate(int $quantity): float {
        $thresholds = $this->config['bulk_thresholds'];
        krsort($thresholds); // Sort descending
        
        foreach ($thresholds as $min_qty => $rate) {
            if ($quantity >= $min_qty) {
                return $rate;
            }
        }
        
        return 0.0;
    }
    
    private function applyDiscountToItems($cart, array $item_keys, float $rate): void {
        $discount_amount = 0;
        
        foreach ($item_keys as $key) {
            $cart_item = $cart->get_cart()[$key];
            $line_total = $cart_item['line_total'];
            $item_discount = $line_total * $rate;
            
            $cart->cart_contents[$key]['line_total'] -= $item_discount;
            $discount_amount += $item_discount;
        }
        
        // Add discount fee (negative amount)
        $cart->add_fee('Bulk Order Discount', -$discount_amount);
    }
}
```

### 5.7 Notification Extension Example

#### 5.7.1 Email Notification Extension
```php
<?php
namespace APW\WooPlugin\Extensions\Examples;

use APW\WooPlugin\Extensions\Core\AbstractExtension;
use APW\WooPlugin\Extensions\Events\EventSubscriberInterface;

class EmailNotificationExtension extends AbstractExtension implements EventSubscriberInterface {
    public function getName(): string {
        return 'Email Notifications';
    }
    
    public function getVersion(): string {
        return '1.0.0';
    }
    
    public function getDescription(): string {
        return 'Sends email notifications for various customer events';
    }
    
    public function getDependencies(): array {
        return [
            ['type' => 'service', 'service' => 'LoggerServiceInterface']
        ];
    }
    
    protected function getDefaultConfig(): array {
        return [
            'admin_email' => get_option('admin_email'),
            'notifications' => [
                'vip_upgrade' => true,
                'bulk_order' => true,
                'high_value_order' => true
            ],
            'templates' => [
                'vip_upgrade' => 'emails/vip-upgrade.php',
                'bulk_order' => 'emails/bulk-order.php'
            ]
        ];
    }
    
    public function getSubscribedEvents(): array {
        return [
            'apw_woo_vip_status_changed' => 'onVIPStatusChanged',
            'apw_woo_bulk_discount_applied' => 'onBulkDiscountApplied',
            'woocommerce_order_status_completed' => 'onOrderCompleted'
        ];
    }
    
    public function onVIPStatusChanged($customer_id, $old_status, $new_status): void {
        if (!$this->config['notifications']['vip_upgrade'] || $old_status === $new_status) {
            return;
        }
        
        $customer = get_userdata($customer_id);
        
        if (!$customer) {
            return;
        }
        
        $this->sendEmail([
            'to' => $customer->user_email,
            'subject' => 'Your VIP Status Has Changed',
            'template' => 'vip_upgrade',
            'data' => [
                'customer_name' => $customer->display_name,
                'old_status' => $old_status,
                'new_status' => $new_status
            ]
        ]);
    }
    
    public function onBulkDiscountApplied($discount_amount, $quantity): void {
        if (!$this->config['notifications']['bulk_order']) {
            return;
        }
        
        $this->sendEmail([
            'to' => $this->config['admin_email'],
            'subject' => 'Bulk Order Discount Applied',
            'template' => 'bulk_order',
            'data' => [
                'discount_amount' => $discount_amount,
                'quantity' => $quantity,
                'order_time' => current_time('Y-m-d H:i:s')
            ]
        ]);
    }
    
    private function sendEmail(array $email_config): bool {
        $template_path = $this->getTemplatePath($email_config['template']);
        
        if (!file_exists($template_path)) {
            $this->logger->error("Email template not found: {$template_path}");
            return false;
        }
        
        ob_start();
        extract($email_config['data']);
        include $template_path;
        $message = ob_get_clean();
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        return wp_mail($email_config['to'], $email_config['subject'], $message, $headers);
    }
    
    private function getTemplatePath(string $template): string {
        $template_file = $this->config['templates'][$template] ?? '';
        
        // Check theme directory first
        $theme_path = get_template_directory() . '/apw-templates/' . $template_file;
        if (file_exists($theme_path)) {
            return $theme_path;
        }
        
        // Fall back to plugin templates
        return APW_WOO_PLUGIN_DIR . 'templates/' . $template_file;
    }
}
```

## Theme Integration

### 5.8 Theme Extension Support

#### 5.8.1 Theme Integration Manager
```php
<?php
namespace APW\WooPlugin\Extensions\Theme;

class ThemeIntegrationManager {
    private string $theme_extensions_dir;
    
    public function __construct() {
        $this->theme_extensions_dir = get_template_directory() . '/apw-extensions/';
    }
    
    public function loadThemeExtensions(): void {
        if (!is_dir($this->theme_extensions_dir)) {
            return;
        }
        
        $extension_files = glob($this->theme_extensions_dir . '*/extension.php');
        
        foreach ($extension_files as $file) {
            include_once $file;
        }
        
        do_action('apw_woo_theme_extensions_loaded');
    }
    
    public function registerThemeHooks(): void {
        // Allow themes to modify templates
        add_filter('apw_woo_template_path', [$this, 'checkThemeTemplates'], 10, 2);
        
        // Theme-specific styling hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueThemeAssets']);
        
        // Template override system
        add_filter('apw_woo_locate_template', [$this, 'locateThemeTemplate'], 10, 3);
    }
    
    public function checkThemeTemplates(string $template_path, string $template_name): string {
        $theme_template = get_template_directory() . '/apw-templates/' . $template_name;
        
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        return $template_path;
    }
    
    public function enqueueThemeAssets(): void {
        $theme_css = get_template_directory_uri() . '/apw-styles.css';
        $theme_js = get_template_directory_uri() . '/apw-scripts.js';
        
        if (file_exists(get_template_directory() . '/apw-styles.css')) {
            wp_enqueue_style('apw-theme-styles', $theme_css, ['apw-woo-styles']);
        }
        
        if (file_exists(get_template_directory() . '/apw-scripts.js')) {
            wp_enqueue_script('apw-theme-scripts', $theme_js, ['apw-woo-scripts']);
        }
    }
}
```

## Developer Tools

### 5.9 Extension Development Tools

#### 5.9.1 Extension Generator
```bash
#!/bin/bash
# bin/generate-extension.sh

EXTENSION_NAME=$1
EXTENSION_DIR="extensions/${EXTENSION_NAME}"

if [ -z "$EXTENSION_NAME" ]; then
    echo "Usage: $0 <extension-name>"
    exit 1
fi

echo "Creating extension: $EXTENSION_NAME"

# Create directory structure
mkdir -p "$EXTENSION_DIR/src"
mkdir -p "$EXTENSION_DIR/templates"
mkdir -p "$EXTENSION_DIR/tests"

# Generate extension.php
cat > "$EXTENSION_DIR/extension.php" << EOF
<?php
/**
 * Extension Name: $EXTENSION_NAME
 * Description: Custom extension for APW WooCommerce Plugin
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register the extension
add_action('apw_woo_register_extensions', function(\$extension_manager) {
    \$extension_manager->registerExtension('${EXTENSION_NAME}Extension');
});

// Autoload extension classes
spl_autoload_register(function(\$class) {
    if (strpos(\$class, '${EXTENSION_NAME}Extension') === 0) {
        \$file = __DIR__ . '/src/' . str_replace('\\\\', '/', \$class) . '.php';
        if (file_exists(\$file)) {
            require_once \$file;
        }
    }
});
EOF

# Generate main extension class
cat > "$EXTENSION_DIR/src/${EXTENSION_NAME}Extension.php" << EOF
<?php

use APW\\WooPlugin\\Extensions\\Core\\AbstractExtension;

class ${EXTENSION_NAME}Extension extends AbstractExtension {
    public function getName(): string {
        return '$EXTENSION_NAME';
    }
    
    public function getVersion(): string {
        return '1.0.0';
    }
    
    public function getDescription(): string {
        return 'Custom extension for APW WooCommerce Plugin';
    }
    
    public function getDependencies(): array {
        return [];
    }
    
    protected function registerHooks(): void {
        // Register your hooks here
    }
    
    protected function registerServices(): void {
        // Register custom services here
    }
}
EOF

echo "Extension '$EXTENSION_NAME' created successfully!"
echo "Edit $EXTENSION_DIR/src/${EXTENSION_NAME}Extension.php to implement your functionality"
```

## Success Criteria

### 5.10 Extensibility Metrics
- [ ] **Extension System**: Fully functional extension management
- [ ] **Hook Coverage**: Comprehensive hooks for all major functionality
- [ ] **Event System**: Event-driven architecture implemented
- [ ] **Theme Integration**: Seamless theme compatibility
- [ ] **Developer Tools**: Extension generator and development tools
- [ ] **Documentation**: Complete extension development guide
- [ ] **Examples**: Working example extensions provided
- [ ] **Backward Compatibility**: Existing functionality preserved

### 5.11 Developer Experience
- [ ] **Easy Extension Creation**: Simple extension development process
- [ ] **Clear APIs**: Well-documented extension interfaces
- [ ] **Debugging Tools**: Extension debugging and development tools
- [ ] **Template System**: Flexible template override system
- [ ] **Service Injection**: Easy service container integration

## Next Steps
Upon completion:
1. Proceed to Phase 5: Maintenance Tools
2. Create extension development documentation
3. Build example extensions for common use cases
4. Establish extension review and testing process