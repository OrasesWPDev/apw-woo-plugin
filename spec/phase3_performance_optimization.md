# Phase 3: Performance Optimization Implementation Specification

## Overview
Implement comprehensive performance optimizations including lazy loading, caching strategies, database query optimization, and conditional asset loading to improve plugin efficiency and user experience.

## Performance Objectives

### 3.1 Performance Goals
- **Page Load Time**: Reduce initial page load by 40%
- **Database Queries**: Minimize redundant database calls
- **Memory Usage**: Reduce memory footprint by 30%
- **Asset Loading**: Load only necessary assets per page
- **Cache Utilization**: Implement strategic caching for expensive operations

## Lazy Loading Implementation

### 3.2 Component Lazy Loading

#### 3.2.1 Service Lazy Loading
```php
<?php
namespace APW\WooPlugin\Core;

class LazyServiceContainer extends ServiceContainer {
    private array $lazy_bindings = [];

    public function lazy(string $abstract, callable $factory): void {
        $this->lazy_bindings[$abstract] = $factory;
    }

    public function make(string $abstract, array $parameters = []) {
        // Check if service is lazily bound
        if (isset($this->lazy_bindings[$abstract])) {
            // Only instantiate when actually needed
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = $this->lazy_bindings[$abstract]();
            }
            return $this->instances[$abstract];
        }

        return parent::make($abstract, $parameters);
    }
}
```

#### 3.2.2 Integration Lazy Loading
```php
<?php
namespace APW\WooPlugin\Core;

class LazyIntegrationManager {
    private ServiceContainer $container;
    private array $integration_loaders = [];
    private array $loaded_integrations = [];

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
        $this->setupLazyLoaders();
    }

    private function setupLazyLoaders(): void {
        $this->integration_loaders = [
            'dynamic_pricing' => function() {
                return $this->loadDynamicPricingIntegration();
            },
            'product_addons' => function() {
                return $this->loadProductAddonsIntegration();
            },
            'intuit_payment' => function() {
                return $this->loadIntuitIntegration();
            }
        ];
    }

    public function getIntegration(string $integration_name) {
        if (!isset($this->loaded_integrations[$integration_name])) {
            if (!isset($this->integration_loaders[$integration_name])) {
                throw new \InvalidArgumentException("Unknown integration: {$integration_name}");
            }

            $this->loaded_integrations[$integration_name] = $this->integration_loaders[$integration_name]();
        }

        return $this->loaded_integrations[$integration_name];
    }

    private function loadDynamicPricingIntegration() {
        if (!class_exists('WC_Dynamic_Pricing')) {
            return new NullIntegration('Dynamic Pricing plugin not active');
        }

        return $this->container->make('APW\WooPlugin\Integrations\ThirdParty\DynamicPricingAdapter');
    }
}
```

#### 3.2.3 Template Lazy Loading
```php
<?php
namespace APW\WooPlugin\Services\Template;

class LazyTemplateService implements TemplateServiceInterface {
    private ConfigInterface $config;
    private array $template_cache = [];
    private array $compiled_templates = [];

    public function render(string $template, array $data = []): string {
        // Use compiled templates when available
        if ($this->hasCompiledTemplate($template)) {
            return $this->renderCompiled($template, $data);
        }

        // Lazy load and compile template
        return $this->compileAndRender($template, $data);
    }

    private function hasCompiledTemplate(string $template): bool {
        $cache_key = md5($template);
        $cache_file = $this->getCacheDir() . '/' . $cache_key . '.php';
        
        if (!file_exists($cache_file)) {
            return false;
        }

        // Check if template source is newer than cache
        $template_file = $this->findTemplate($template);
        if ($template_file && filemtime($template_file) > filemtime($cache_file)) {
            return false;
        }

        return true;
    }

    private function compileAndRender(string $template, array $data): string {
        $template_file = $this->findTemplate($template);
        
        if (!$template_file) {
            throw new \Exception("Template not found: {$template}");
        }

        // Compile template for future use
        $compiled = $this->compileTemplate($template_file);
        $this->saveCacheFile($template, $compiled);

        // Render with data
        return $this->executeTemplate($compiled, $data);
    }

    private function compileTemplate(string $template_file): string {
        $content = file_get_contents($template_file);
        
        // Simple template compilation (can be extended)
        $compiled = $content;
        
        // Replace common patterns for better performance
        $compiled = str_replace('<?php echo', '<?=', $compiled);
        
        return $compiled;
    }
}
```

## Caching Strategy Implementation

### 3.3 Multi-Level Caching

#### 3.3.1 Object Caching
```php
<?php
namespace APW\WooPlugin\Services\Cache;

class CacheService implements CacheServiceInterface {
    private string $cache_group;
    private int $default_expiration;

    public function __construct(string $cache_group = 'apw_woo', int $default_expiration = 3600) {
        $this->cache_group = $cache_group;
        $this->default_expiration = $default_expiration;
    }

    public function get(string $key, $default = null) {
        $cached = wp_cache_get($key, $this->cache_group);
        
        if ($cached === false) {
            return $default;
        }

        return $cached;
    }

    public function set(string $key, $value, int $expiration = null): bool {
        $expiration = $expiration ?? $this->default_expiration;
        
        return wp_cache_set($key, $value, $this->cache_group, $expiration);
    }

    public function remember(string $key, callable $callback, int $expiration = null) {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $expiration);
        
        return $value;
    }

    public function forget(string $key): bool {
        return wp_cache_delete($key, $this->cache_group);
    }

    public function flush(): bool {
        return wp_cache_flush_group($this->cache_group);
    }
}
```

#### 3.3.2 Transient Caching for Expensive Operations
```php
<?php
namespace APW\WooPlugin\Services\Cache;

class TransientCacheService {
    private string $prefix;

    public function __construct(string $prefix = 'apw_woo_') {
        $this->prefix = $prefix;
    }

    public function cachePricingRules(int $product_id, array $rules): void {
        $key = $this->prefix . 'pricing_rules_' . $product_id;
        set_transient($key, $rules, HOUR_IN_SECONDS);
    }

    public function getCachedPricingRules(int $product_id): ?array {
        $key = $this->prefix . 'pricing_rules_' . $product_id;
        $cached = get_transient($key);
        
        return $cached === false ? null : $cached;
    }

    public function cacheCustomerReferrals(array $referrals): void {
        $key = $this->prefix . 'customer_referrals';
        set_transient($key, $referrals, 30 * MINUTE_IN_SECONDS);
    }

    public function getCachedCustomerReferrals(): ?array {
        $key = $this->prefix . 'customer_referrals';
        $cached = get_transient($key);
        
        return $cached === false ? null : $cached;
    }

    public function invalidateProductCache(int $product_id): void {
        $keys = [
            $this->prefix . 'pricing_rules_' . $product_id,
            $this->prefix . 'product_addons_' . $product_id,
            $this->prefix . 'product_data_' . $product_id
        ];

        foreach ($keys as $key) {
            delete_transient($key);
        }
    }
}
```

#### 3.3.3 Database Query Result Caching
```php
<?php
namespace APW\WooPlugin\Models;

class CachedCustomerModel extends BaseModel {
    private CacheServiceInterface $cache;

    public function __construct(CacheServiceInterface $cache) {
        parent::__construct();
        $this->cache = $cache;
    }

    public function getReferralCustomers(string $referrer_name = ''): array {
        $cache_key = 'referral_customers_' . md5($referrer_name);
        
        return $this->cache->remember($cache_key, function() use ($referrer_name) {
            return $this->queryReferralCustomers($referrer_name);
        }, 15 * MINUTE_IN_SECONDS);
    }

    private function queryReferralCustomers(string $referrer_name): array {
        $sql = "
            SELECT u.ID, u.user_login, u.user_email, u.user_registered,
                   um1.meta_value as first_name,
                   um2.meta_value as last_name,
                   um3.meta_value as company_name,
                   um4.meta_value as phone_number,
                   um5.meta_value as referred_by
            FROM {$this->wpdb->users} u
            LEFT JOIN {$this->wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
            LEFT JOIN {$this->wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
            LEFT JOIN {$this->wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = 'company_name'
            LEFT JOIN {$this->wpdb->usermeta} um4 ON u.ID = um4.user_id AND um4.meta_key = 'phone_number'
            LEFT JOIN {$this->wpdb->usermeta} um5 ON u.ID = um5.user_id AND um5.meta_key = 'referred_by'
            WHERE um5.meta_value IS NOT NULL AND um5.meta_value != ''
        ";

        if (!empty($referrer_name)) {
            $sql .= $this->wpdb->prepare(" AND um5.meta_value = %s", $referrer_name);
        }

        $sql .= " ORDER BY u.user_registered DESC";

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function invalidateReferralCache(): void {
        $this->cache->forget('referral_customers_*');
    }
}
```

## Database Query Optimization

### 3.4 Query Performance Improvements

#### 3.4.1 Optimized Customer Queries
```php
<?php
namespace APW\WooPlugin\Services\Database;

class OptimizedQueryService {
    private \wpdb $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function getCustomersWithMetaBatch(array $meta_keys, array $user_ids = []): array {
        // Single query instead of multiple get_user_meta calls
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        
        $sql = "
            SELECT um.user_id, um.meta_key, um.meta_value
            FROM {$this->wpdb->usermeta} um
            WHERE um.meta_key IN ({$placeholders})
        ";

        $params = $meta_keys;

        if (!empty($user_ids)) {
            $user_placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
            $sql .= " AND um.user_id IN ({$user_placeholders})";
            $params = array_merge($params, $user_ids);
        }

        $prepared = $this->wpdb->prepare($sql, $params);
        $results = $this->wpdb->get_results($prepared, ARRAY_A);

        // Organize results by user_id
        $organized = [];
        foreach ($results as $row) {
            $organized[$row['user_id']][$row['meta_key']] = $row['meta_value'];
        }

        return $organized;
    }

    public function getProductPricingDataBatch(array $product_ids): array {
        if (empty($product_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        
        $sql = "
            SELECT p.ID, p.post_title,
                   pm1.meta_value as regular_price,
                   pm2.meta_value as sale_price,
                   pm3.meta_value as pricing_rules
            FROM {$this->wpdb->posts} p
            LEFT JOIN {$this->wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_regular_price'
            LEFT JOIN {$this->wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_sale_price'
            LEFT JOIN {$this->wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_pricing_rules'
            WHERE p.ID IN ({$placeholders})
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
        ";

        $prepared = $this->wpdb->prepare($sql, $product_ids);
        
        return $this->wpdb->get_results($prepared, ARRAY_A) ?: [];
    }

    public function getOrderStatsBatch(array $customer_ids): array {
        if (empty($customer_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($customer_ids), '%d'));
        
        $sql = "
            SELECT pm.meta_value as customer_id,
                   COUNT(p.ID) as order_count,
                   SUM(CAST(pm2.meta_value AS DECIMAL(10,2))) as total_spent
            FROM {$this->wpdb->posts} p
            INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
            INNER JOIN {$this->wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
            WHERE pm.meta_value IN ({$placeholders})
            AND p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            GROUP BY pm.meta_value
        ";

        $prepared = $this->wpdb->prepare($sql, $customer_ids);
        $results = $this->wpdb->get_results($prepared, ARRAY_A);

        // Index by customer_id
        $indexed = [];
        foreach ($results as $row) {
            $indexed[$row['customer_id']] = [
                'order_count' => (int) $row['order_count'],
                'total_spent' => (float) $row['total_spent']
            ];
        }

        return $indexed;
    }
}
```

#### 3.4.2 Database Index Optimization
```php
<?php
namespace APW\WooPlugin\Database;

class IndexManager {
    private \wpdb $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function createOptimizationIndexes(): void {
        $indexes = [
            // Optimize referral queries
            [
                'table' => $this->wpdb->usermeta,
                'name' => 'idx_apw_referred_by',
                'columns' => ['meta_key', 'meta_value(50)'],
                'condition' => "meta_key = 'referred_by'"
            ],
            // Optimize customer meta queries
            [
                'table' => $this->wpdb->usermeta,
                'name' => 'idx_apw_customer_meta',
                'columns' => ['user_id', 'meta_key'],
                'condition' => "meta_key IN ('first_name', 'last_name', 'company_name', 'phone_number')"
            ],
            // Optimize product pricing queries
            [
                'table' => $this->wpdb->postmeta,
                'name' => 'idx_apw_pricing_rules',
                'columns' => ['post_id', 'meta_key'],
                'condition' => "meta_key = '_pricing_rules'"
            ]
        ];

        foreach ($indexes as $index) {
            $this->createIndexIfNotExists($index);
        }
    }

    private function createIndexIfNotExists(array $index_config): void {
        $table = $index_config['table'];
        $index_name = $index_config['name'];
        
        // Check if index exists
        $existing_indexes = $this->wpdb->get_col("SHOW INDEXES FROM {$table} WHERE Key_name = '{$index_name}'");
        
        if (!empty($existing_indexes)) {
            return;
        }

        $columns = implode(', ', $index_config['columns']);
        $sql = "CREATE INDEX {$index_name} ON {$table} ({$columns})";
        
        $this->wpdb->query($sql);
    }
}
```

## Asset Loading Optimization

### 3.5 Conditional Asset Loading

#### 3.5.1 Smart Asset Manager
```php
<?php
namespace APW\WooPlugin\Services\Assets;

class SmartAssetManager {
    private ConfigInterface $config;
    private array $page_assets = [];

    public function __construct(ConfigInterface $config) {
        $this->config = $config;
        $this->definePageAssets();
    }

    public function enqueueAssets(): void {
        $current_context = $this->getCurrentContext();
        $assets = $this->getAssetsForContext($current_context);

        foreach ($assets as $asset) {
            $this->enqueueAsset($asset);
        }
    }

    private function definePageAssets(): void {
        $this->page_assets = [
            'checkout' => [
                'css' => ['woocommerce-custom.css', 'apw-registration-fields.css'],
                'js' => ['apw-woo-checkout.js', 'apw-woo-intuit-integration.js']
            ],
            'cart' => [
                'css' => ['woocommerce-custom.css'],
                'js' => ['apw-woo-public.js', 'apw-woo-dynamic-pricing.js']
            ],
            'product' => [
                'css' => ['faq-styles.css', 'woocommerce-custom.css'],
                'js' => ['apw-woo-public.js']
            ],
            'admin_users' => [
                'css' => ['apw-referral-export-admin.css'],
                'js' => ['apw-referral-export.js']
            ],
            'admin_orders' => [
                'css' => ['woocommerce-custom.css'],
                'js' => []
            ]
        ];
    }

    private function getCurrentContext(): string {
        if (is_admin()) {
            return $this->getAdminContext();
        }

        if (is_checkout()) {
            return 'checkout';
        }

        if (is_cart()) {
            return 'cart';
        }

        if (is_product()) {
            return 'product';
        }

        if (is_shop() || is_product_category()) {
            return 'shop';
        }

        return 'default';
    }

    private function getAdminContext(): string {
        $screen = get_current_screen();
        
        if (!$screen) {
            return 'admin_default';
        }

        switch ($screen->id) {
            case 'users':
            case 'user-edit':
                return 'admin_users';
            case 'shop_order':
            case 'edit-shop_order':
                return 'admin_orders';
            default:
                return 'admin_default';
        }
    }

    private function getAssetsForContext(string $context): array {
        return $this->page_assets[$context] ?? [];
    }

    private function enqueueAsset(array $asset_config): void {
        $type = $asset_config['type'] ?? 'css';
        $handle = $asset_config['handle'];
        $file = $asset_config['file'];
        
        $file_path = $this->config->get('plugin_dir') . "assets/{$type}/{$file}";
        $file_url = $this->config->get('plugin_url') . "assets/{$type}/{$file}";
        
        if (!file_exists($file_path)) {
            return;
        }

        $version = filemtime($file_path);
        
        if ($type === 'css') {
            wp_enqueue_style($handle, $file_url, $asset_config['deps'] ?? [], $version);
        } else {
            wp_enqueue_script(
                $handle, 
                $file_url, 
                $asset_config['deps'] ?? ['jquery'], 
                $version, 
                $asset_config['in_footer'] ?? true
            );
        }
    }
}
```

#### 3.5.2 Asset Minification and Compression
```php
<?php
namespace APW\WooPlugin\Services\Assets;

class AssetOptimizer {
    private string $assets_dir;
    private string $cache_dir;

    public function __construct(string $assets_dir, string $cache_dir) {
        $this->assets_dir = $assets_dir;
        $this->cache_dir = $cache_dir;
    }

    public function getOptimizedAsset(string $asset_path): string {
        $optimized_path = $this->getOptimizedPath($asset_path);
        
        if ($this->needsOptimization($asset_path, $optimized_path)) {
            $this->createOptimizedAsset($asset_path, $optimized_path);
        }

        return $optimized_path;
    }

    private function needsOptimization(string $source, string $optimized): bool {
        if (!file_exists($optimized)) {
            return true;
        }

        return filemtime($source) > filemtime($optimized);
    }

    private function createOptimizedAsset(string $source, string $optimized): void {
        $content = file_get_contents($source);
        $extension = pathinfo($source, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'css':
                $content = $this->minifyCSS($content);
                break;
            case 'js':
                $content = $this->minifyJS($content);
                break;
        }

        wp_mkdir_p(dirname($optimized));
        file_put_contents($optimized, $content);
    }

    private function minifyCSS(string $css): string {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove unnecessary spaces
        $css = str_replace(['; ', ' {', '{ ', ' }', '} ', ': ', ', '], [';', '{', '{', '}', '}', ':', ','], $css);
        
        return trim($css);
    }

    private function minifyJS(string $js): string {
        // Basic JS minification (for production, consider using a proper minifier)
        $js = preg_replace('/\/\*.*?\*\//s', '', $js); // Remove /* */ comments
        $js = preg_replace('/\/\/.*$/m', '', $js); // Remove // comments
        $js = preg_replace('/\s+/', ' ', $js); // Collapse whitespace
        
        return trim($js);
    }

    private function getOptimizedPath(string $asset_path): string {
        $relative_path = str_replace($this->assets_dir, '', $asset_path);
        $cache_path = $this->cache_dir . '/optimized' . $relative_path;
        
        return str_replace('.', '.min.', $cache_path);
    }
}
```

## Memory Usage Optimization

### 3.6 Memory Management

#### 3.6.1 Memory-Efficient Data Processing
```php
<?php
namespace APW\WooPlugin\Services\Export;

class MemoryEfficientExportService {
    private const BATCH_SIZE = 100;
    private OptimizedQueryService $query_service;

    public function __construct(OptimizedQueryService $query_service) {
        $this->query_service = $query_service;
    }

    public function exportLargeDataset(array $criteria): \Generator {
        $offset = 0;
        
        do {
            $batch = $this->query_service->getCustomersBatch($criteria, $offset, self::BATCH_SIZE);
            
            foreach ($batch as $customer) {
                yield $this->formatCustomerForExport($customer);
            }
            
            $offset += self::BATCH_SIZE;
            
            // Free memory after each batch
            if ($offset % (self::BATCH_SIZE * 10) === 0) {
                $this->freeMemory();
            }
            
        } while (count($batch) === self::BATCH_SIZE);
    }

    public function createCSVFromGenerator(\Generator $data_generator, string $filename): string {
        $file_path = $this->getExportPath($filename);
        $handle = fopen($file_path, 'w');
        
        if (!$handle) {
            throw new \Exception("Cannot create export file: {$filename}");
        }

        $headers_written = false;
        
        foreach ($data_generator as $row) {
            if (!$headers_written) {
                fputcsv($handle, array_keys($row));
                $headers_written = true;
            }
            
            fputcsv($handle, array_values($row));
        }

        fclose($handle);
        
        return $file_path;
    }

    private function freeMemory(): void {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
```

#### 3.6.2 Object Pool for Frequently Created Objects
```php
<?php
namespace APW\WooPlugin\Common;

class ObjectPool {
    private array $pools = [];

    public function get(string $class_name, array $constructor_args = []) {
        if (!isset($this->pools[$class_name])) {
            $this->pools[$class_name] = [];
        }

        if (!empty($this->pools[$class_name])) {
            $object = array_pop($this->pools[$class_name]);
            $object->reset($constructor_args);
            return $object;
        }

        return new $class_name(...$constructor_args);
    }

    public function release(string $class_name, $object): void {
        if (!isset($this->pools[$class_name])) {
            $this->pools[$class_name] = [];
        }

        if (count($this->pools[$class_name]) < 10) { // Limit pool size
            $this->pools[$class_name][] = $object;
        }
    }

    public function clear(): void {
        $this->pools = [];
    }
}
```

## Performance Monitoring

### 3.7 Performance Metrics Collection

#### 3.7.1 Performance Monitor Service
```php
<?php
namespace APW\WooPlugin\Services\Performance;

class PerformanceMonitor {
    private array $timers = [];
    private array $memory_usage = [];
    private array $query_counts = [];
    private LoggerServiceInterface $logger;

    public function __construct(LoggerServiceInterface $logger) {
        $this->logger = $logger;
    }

    public function startTimer(string $operation): void {
        $this->timers[$operation] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    public function endTimer(string $operation): array {
        if (!isset($this->timers[$operation])) {
            return [];
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $metrics = [
            'operation' => $operation,
            'duration' => $end_time - $this->timers[$operation]['start'],
            'memory_used' => $end_memory - $this->timers[$operation]['memory_start'],
            'peak_memory' => memory_get_peak_usage(true)
        ];

        unset($this->timers[$operation]);

        $this->logPerformanceMetrics($metrics);
        
        return $metrics;
    }

    public function trackDatabaseQueries(callable $callback) {
        global $wpdb;
        
        $query_count_before = $wpdb->num_queries;
        $result = $callback();
        $query_count_after = $wpdb->num_queries;
        
        $queries_executed = $query_count_after - $query_count_before;
        
        $this->logger->debug("Database queries executed: {$queries_executed}");
        
        return $result;
    }

    private function logPerformanceMetrics(array $metrics): void {
        $this->logger->info("Performance metrics", $metrics);
        
        // Alert on slow operations
        if ($metrics['duration'] > 1.0) {
            $this->logger->warning("Slow operation detected", $metrics);
        }
    }
}
```

#### 3.7.2 Performance Benchmarking
```php
<?php
namespace APW\WooPlugin\Tests\Performance;

class PerformanceBenchmarkTest extends TestCase {
    private PerformanceMonitor $monitor;

    protected function setUp(): void {
        $logger = $this->createMock(LoggerServiceInterface::class);
        $this->monitor = new PerformanceMonitor($logger);
    }

    public function testCartCalculationPerformance(): void {
        $this->monitor->startTimer('cart_calculation');
        
        // Simulate cart calculation
        $cart_service = $this->getCartService();
        $cart_service->calculateTotal();
        
        $metrics = $this->monitor->endTimer('cart_calculation');
        
        // Assert performance requirements
        $this->assertLessThan(0.5, $metrics['duration'], 'Cart calculation should complete in under 500ms');
        $this->assertLessThan(5 * 1024 * 1024, $metrics['memory_used'], 'Memory usage should be under 5MB');
    }

    public function testPricingRulesPerformance(): void {
        $this->monitor->startTimer('pricing_rules');
        
        $pricing_service = $this->getPricingService();
        
        // Test with multiple products
        for ($i = 1; $i <= 100; $i++) {
            $pricing_service->getProductPricingRules($i);
        }
        
        $metrics = $this->monitor->endTimer('pricing_rules');
        
        $this->assertLessThan(2.0, $metrics['duration'], 'Pricing rules lookup should complete in under 2s for 100 products');
    }
}
```

## Success Criteria

### 3.8 Performance Benchmarks
- [ ] **Page Load Time**: <3 seconds for product pages
- [ ] **Database Queries**: <10 queries per page load
- [ ] **Memory Usage**: <16MB peak memory usage
- [ ] **Asset Size**: 50% reduction in total asset size
- [ ] **Cache Hit Rate**: >80% for frequently accessed data
- [ ] **API Response Time**: <200ms for AJAX requests

### 3.9 Monitoring and Alerts
- [ ] Performance monitoring dashboard
- [ ] Automated alerts for performance regressions
- [ ] Memory usage tracking
- [ ] Database query optimization reports
- [ ] Cache performance metrics

## Next Steps
Upon completion:
1. Proceed to Phase 3: Security & Standards Implementation
2. Establish continuous performance monitoring
3. Implement automated performance testing
4. Create performance optimization documentation