# Architecture Decisions - WordPress-Native Patterns

## Overview
This specification documents the architectural decisions for the APW WooCommerce Plugin refactor, emphasizing WordPress-native patterns over enterprise frameworks while maintaining simplicity, security, and performance.

## Rejected Over-Engineering Patterns

### Enterprise Patterns NOT Used
Based on analysis of the original 16 specification files, we explicitly reject these inappropriate patterns:

#### 1. Dependency Injection Containers
```php
// REJECTED: Enterprise DI Container
class ServiceContainer {
    private $bindings = [];
    public function singleton($abstract, $concrete) { /* ... */ }
    public function resolve($abstract) { /* complex resolution logic */ }
}
```
**Why Rejected**: WordPress has its own hook system for dependency management. DI containers add unnecessary complexity and violate WordPress conventions.

#### 2. Service-Oriented Architecture
```php
// REJECTED: Complex service interfaces
interface PaymentGatewayServiceInterface {
    public function calculateSurcharge(float $amount, string $gateway_id): float;
    public function applySurcharge(string $gateway_id): void;
    // 15+ more methods with type hints
}
```
**Why Rejected**: WordPress plugins should be simple. Over-abstraction makes code harder to maintain and debug.

#### 3. Event-Driven Architecture
```php
// REJECTED: Custom event system
class EventManager {
    private $listeners = [];
    public function addEventListener($event, $callback) { /* ... */ }
    public function dispatch(Event $event) { /* ... */ }
}
```
**Why Rejected**: WordPress already has a robust hook system (`do_action`, `apply_filters`). Custom event systems create parallel systems that confuse developers.

#### 4. Repository Patterns
```php
// REJECTED: Database abstraction layers
interface CustomerRepositoryInterface {
    public function findById(int $id): ?Customer;
    public function save(Customer $customer): bool;
}
```
**Why Rejected**: WordPress has `WP_Query`, `get_user_meta`, and WooCommerce's built-in data handling. Custom repositories duplicate existing functionality.

## WordPress-Native Architecture Decisions

### 1. Plugin Structure - WordPress Standards

#### Main Plugin File Pattern
```php
<?php
/**
 * Plugin Name: APW WooCommerce Plugin
 * Description: Custom WooCommerce enhancements
 * Version: 1.23.19
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Plugin constants
define('APW_WOO_VERSION', '1.23.19');
define('APW_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APW_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Simple autoload using WordPress conventions
spl_autoload_register('apw_woo_autoload');

// Initialize after WordPress and WooCommerce load
add_action('plugins_loaded', 'apw_woo_init');
```

**Decision Rationale**: 
- Follows WordPress plugin header standards
- Uses WordPress constants and functions
- Leverages WordPress action system for initialization
- No complex bootstrapping or container setup

### 2. Class Organization - WordPress Naming

#### File Naming Convention
```
includes/
├── class-apw-woo-plugin.php           # Main plugin orchestrator
├── class-apw-woo-payment-service.php  # Payment processing
├── class-apw-woo-customer-service.php # Customer management  
├── class-apw-woo-product-service.php  # Product/pricing logic
└── class-apw-woo-cart-service.php     # Cart functionality
```

#### Class Structure Pattern
```php
class APW_Woo_Payment_Service {
    
    // WordPress singleton pattern
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // WordPress-style hooks integration
    public function init() {
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_surcharge'], 20);
        add_filter('woocommerce_gateway_description', [$this, 'add_fee_notice'], 10, 2);
    }
    
    // Public methods using WordPress conventions
    public function apply_surcharge() {
        // WordPress security check
        if (is_admin() && !defined('DOING_AJAX')) return;
        
        // Use WordPress/WooCommerce APIs
        $chosen_gateway = WC()->session->get('chosen_payment_method');
        // Implementation using WC objects
    }
}
```

**Decision Rationale**:
- WordPress naming conventions (`Class_Name`, `snake_case` methods)
- Singleton pattern commonly used in WordPress plugins
- Direct integration with WordPress hook system
- No interfaces or complex inheritance hierarchies

### 3. Data Handling - WordPress/WooCommerce APIs

#### Use Native WordPress Functions
```php
// GOOD: WordPress-native data handling
function apw_woo_get_customer_vip_status($customer_id) {
    // Use WordPress user meta API
    $total_spent = get_user_meta($customer_id, '_money_spent', true);
    
    // Use WooCommerce customer object
    $customer = new WC_Customer($customer_id);
    $total_orders = $customer->get_order_count();
    
    return $total_spent >= 100 && $total_orders >= 3;
}

// Use WordPress caching
function apw_woo_get_cached_customer_data($customer_id) {
    $cache_key = 'apw_customer_' . $customer_id;
    $data = wp_cache_get($cache_key, 'apw_woo');
    
    if (false === $data) {
        $data = apw_woo_calculate_customer_data($customer_id);
        wp_cache_set($cache_key, $data, 'apw_woo', HOUR_IN_SECONDS);
    }
    
    return $data;
}
```

**Decision Rationale**:
- Leverages WordPress's built-in caching system
- Uses WooCommerce's established customer APIs
- Integrates seamlessly with existing WordPress installations
- No custom database abstraction needed

### 4. Hook System - WordPress Actions/Filters

#### Hook Registration Pattern
```php
function apw_woo_init_payment_hooks() {
    // Use WordPress hook priorities for proper timing
    add_action('woocommerce_cart_calculate_fees', 'apw_woo_apply_vip_discount', 10);
    add_action('woocommerce_cart_calculate_fees', 'apw_woo_apply_surcharge', 20);
    
    // Use WordPress conditional loading
    if (is_admin()) {
        add_action('admin_menu', 'apw_woo_add_admin_menu');
    }
    
    if (is_checkout()) {
        add_action('wp_enqueue_scripts', 'apw_woo_checkout_scripts');
    }
}
add_action('wp_loaded', 'apw_woo_init_payment_hooks');
```

**Decision Rationale**:
- Uses WordPress's established hook priority system
- Conditional hook registration for performance
- No custom event dispatching or complex listeners
- Clear hook timing and dependencies

### 5. Configuration - WordPress Options

#### Settings Management
```php
// Use WordPress options API
function apw_woo_get_setting($key, $default = null) {
    $options = get_option('apw_woo_settings', []);
    return isset($options[$key]) ? $options[$key] : $default;
}

function apw_woo_update_setting($key, $value) {
    $options = get_option('apw_woo_settings', []);
    $options[$key] = $value;
    update_option('apw_woo_settings', $options);
}

// WordPress admin settings page
function apw_woo_register_settings() {
    register_setting('apw_woo_settings', 'apw_woo_settings');
    
    add_settings_section(
        'apw_woo_general',
        __('General Settings', 'apw-woo-plugin'),
        'apw_woo_general_section_callback',
        'apw_woo_settings'
    );
}
```

**Decision Rationale**:
- Uses WordPress options API for settings storage
- Integrates with WordPress admin settings framework
- No custom configuration classes or complex setup
- Familiar to WordPress developers

### 6. Template System - WordPress Template Hierarchy

#### Template Loading
```php
function apw_woo_get_template($template_name, $args = []) {
    // WordPress template hierarchy
    $template_paths = [
        get_stylesheet_directory() . '/apw-woo-plugin/' . $template_name,
        get_template_directory() . '/apw-woo-plugin/' . $template_name,
        APW_WOO_PLUGIN_DIR . 'templates/' . $template_name
    ];
    
    foreach ($template_paths as $path) {
        if (file_exists($path)) {
            // WordPress-style template loading
            extract($args, EXTR_SKIP);
            include $path;
            return;
        }
    }
}

// Integration with WooCommerce template system
function apw_woo_override_woocommerce_template($template, $template_name, $template_path) {
    $plugin_template = APW_WOO_PLUGIN_DIR . 'templates/woocommerce/' . $template_name;
    
    if (file_exists($plugin_template)) {
        return $plugin_template;
    }
    
    return $template;
}
add_filter('woocommerce_locate_template', 'apw_woo_override_woocommerce_template', 10, 3);
```

**Decision Rationale**:
- Follows WordPress template hierarchy conventions
- Allows theme override capability
- Integrates with WooCommerce template system
- No custom template engines or complex rendering

### 7. Security - WordPress Security Functions

#### Input Validation and Sanitization
```php
function apw_woo_handle_form_submission() {
    // WordPress nonce verification
    if (!wp_verify_nonce($_POST['apw_woo_nonce'], 'apw_woo_action')) {
        wp_die(__('Security check failed', 'apw-woo-plugin'));
    }
    
    // WordPress capability check
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('Insufficient permissions', 'apw-woo-plugin'));
    }
    
    // WordPress sanitization functions
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    
    // WordPress validation
    if (!is_email($email)) {
        wp_die(__('Invalid email address', 'apw-woo-plugin'));
    }
}
```

**Decision Rationale**:
- Uses WordPress built-in security functions
- Leverages WordPress capability system
- No custom authentication or authorization
- Familiar security patterns for WordPress developers

### 8. Error Handling - WordPress Error System

#### Error Management
```php
function apw_woo_process_payment($payment_data) {
    // Use WordPress error handling
    $result = new WP_Error();
    
    if (empty($payment_data['amount'])) {
        $result->add('missing_amount', __('Payment amount is required', 'apw-woo-plugin'));
    }
    
    if (!apw_woo_validate_payment_method($payment_data['method'])) {
        $result->add('invalid_method', __('Invalid payment method', 'apw-woo-plugin'));
    }
    
    if ($result->has_errors()) {
        return $result;
    }
    
    // Process payment...
    return ['success' => true, 'transaction_id' => '12345'];
}

// WordPress-style error display
function apw_woo_display_errors($errors) {
    if (is_wp_error($errors)) {
        foreach ($errors->get_error_messages() as $message) {
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
```

**Decision Rationale**:
- Uses WordPress `WP_Error` class for consistent error handling
- Integrates with WordPress admin notice system
- No custom exception handling or complex error hierarchies
- Familiar patterns for WordPress developers

## Performance Considerations

### 1. WordPress Caching
```php
// Use WordPress transients for temporary data
function apw_woo_get_expensive_calculation($key) {
    $cache_key = 'apw_calc_' . md5($key);
    $result = get_transient($cache_key);
    
    if (false === $result) {
        $result = apw_woo_perform_calculation($key);
        set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);
    }
    
    return $result;
}

// Use WordPress object cache for request-level caching
function apw_woo_get_customer_data($customer_id) {
    $cache_key = 'customer_' . $customer_id;
    $data = wp_cache_get($cache_key, 'apw_woo');
    
    if (false === $data) {
        $data = apw_woo_fetch_customer_data($customer_id);
        wp_cache_set($cache_key, $data, 'apw_woo');
    }
    
    return $data;
}
```

### 2. Conditional Loading
```php
// Load functionality only when needed
function apw_woo_conditional_init() {
    if (is_checkout()) {
        require_once APW_WOO_PLUGIN_DIR . 'includes/checkout-specific-functions.php';
    }
    
    if (is_admin() && current_user_can('manage_woocommerce')) {
        require_once APW_WOO_PLUGIN_DIR . 'includes/admin-functions.php';
    }
}
add_action('wp', 'apw_woo_conditional_init');
```

## Testing Strategy - WordPress Compatible

### 1. Unit Testing with WordPress
```php
class APW_Woo_Payment_Test extends WP_UnitTestCase {
    
    public function setUp() {
        parent::setUp();
        // Use WordPress test factories
        $this->customer_id = $this->factory->user->create();
        $this->product_id = $this->factory->post->create(['post_type' => 'product']);
    }
    
    public function test_surcharge_calculation() {
        // Test using WordPress/WooCommerce test environment
        $payment_service = APW_Woo_Payment_Service::get_instance();
        $surcharge = $payment_service->calculate_surcharge(100.00, 'credit_card');
        
        $this->assertEquals(3.00, $surcharge);
    }
}
```

### 2. Integration Testing
```php
// Test WordPress hook integration
function test_payment_hooks_registered() {
    apw_woo_init_payment_hooks();
    
    $this->assertTrue(has_action('woocommerce_cart_calculate_fees', 'apw_woo_apply_surcharge'));
    $this->assertEquals(20, has_action('woocommerce_cart_calculate_fees', 'apw_woo_apply_surcharge'));
}
```

## Success Criteria

### Architecture Quality
- [ ] No custom dependency injection containers
- [ ] No enterprise design patterns inappropriate for WordPress
- [ ] All code follows WordPress coding standards
- [ ] Uses WordPress APIs exclusively for data operations

### Integration Quality  
- [ ] Seamless WooCommerce integration using official hooks
- [ ] Proper WordPress template hierarchy support
- [ ] WordPress multisite compatibility
- [ ] No conflicts with other WordPress plugins

### Performance Quality
- [ ] Uses WordPress caching mechanisms
- [ ] Conditional loading reduces overhead
- [ ] No unnecessary database queries
- [ ] Efficient hook usage with proper priorities

### Security Quality
- [ ] WordPress nonce verification for all forms
- [ ] Capability checks for privileged operations
- [ ] Input sanitization using WordPress functions
- [ ] Output escaping following WordPress standards

This architecture ensures the plugin remains maintainable, performant, and fully compatible with the WordPress ecosystem while avoiding unnecessary complexity.