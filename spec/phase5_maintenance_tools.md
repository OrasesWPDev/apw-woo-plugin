# Phase 5: Maintenance Tools Implementation Specification

## Overview
Develop comprehensive maintenance tools for monitoring, debugging, performance optimization, and automated maintenance tasks to ensure long-term plugin health and reliability.

## Maintenance Objectives

### 5.1 Maintenance Goals
- **Proactive Monitoring**: Early detection of issues and performance problems
- **Automated Maintenance**: Self-healing and automated optimization
- **Debug Capabilities**: Comprehensive debugging and troubleshooting tools
- **Performance Monitoring**: Real-time performance tracking and optimization
- **Health Checks**: Regular system health assessments
- **Data Integrity**: Automated data validation and cleanup

## Maintenance Architecture

### 5.2 Maintenance System Structure
```
src/APW/WooPlugin/Maintenance/
├── Core/
│   ├── MaintenanceManager.php
│   ├── ScheduledTask.php
│   ├── TaskScheduler.php
│   └── HealthChecker.php
├── Monitoring/
│   ├── PerformanceMonitor.php
│   ├── ErrorTracker.php
│   ├── UsageAnalytics.php
│   └── SystemHealthMonitor.php
├── Debug/
│   ├── DebugManager.php
│   ├── LogAnalyzer.php
│   ├── QueryProfiler.php
│   └── MemoryProfiler.php
├── Cleanup/
│   ├── DataCleanup.php
│   ├── LogRotation.php
│   ├── CacheManager.php
│   └── DatabaseOptimizer.php
├── Tools/
│   ├── DiagnosticTool.php
│   ├── ConfigurationValidator.php
│   ├── IntegrityChecker.php
│   └── RepairTool.php
└── Admin/
    ├── MaintenanceDashboard.php
    ├── DebugPanel.php
    └── ToolsPage.php
```

## Monitoring System

### 5.3 Performance Monitoring

#### 5.3.1 Performance Monitor
```php
<?php
namespace APW\WooPlugin\Maintenance\Monitoring;

use APW\WooPlugin\Services\LoggerServiceInterface;
use APW\WooPlugin\Core\ConfigInterface;

class PerformanceMonitor {
    private LoggerServiceInterface $logger;
    private ConfigInterface $config;
    private array $metrics = [];
    private array $thresholds = [
        'page_load_time' => 3.0,
        'database_queries' => 50,
        'memory_usage' => 64 * 1024 * 1024, // 64MB
        'cart_calculation_time' => 1.0
    ];
    
    public function __construct(LoggerServiceInterface $logger, ConfigInterface $config) {
        $this->logger = $logger;
        $this->config = $config;
    }
    
    public function startTracking(string $operation): void {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_queries' => $this->getDatabaseQueryCount()
        ];
    }
    
    public function endTracking(string $operation): array {
        if (!isset($this->metrics[$operation])) {
            return [];
        }
        
        $start_data = $this->metrics[$operation];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $end_queries = $this->getDatabaseQueryCount();
        
        $performance_data = [
            'operation' => $operation,
            'duration' => $end_time - $start_data['start_time'],
            'memory_used' => $end_memory - $start_data['start_memory'],
            'queries_executed' => $end_queries - $start_data['start_queries'],
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => current_time('Y-m-d H:i:s')
        ];
        
        $this->analyzePerformance($performance_data);
        $this->logPerformanceData($performance_data);
        
        unset($this->metrics[$operation]);
        
        return $performance_data;
    }
    
    public function trackPageLoad(): void {
        if (!$this->config->get('performance_monitoring', false)) {
            return;
        }
        
        add_action('init', [$this, 'startPageTracking']);
        add_action('wp_footer', [$this, 'endPageTracking'], 9999);
    }
    
    public function startPageTracking(): void {
        $this->startTracking('page_load');
    }
    
    public function endPageTracking(): void {
        $data = $this->endTracking('page_load');
        
        if (!empty($data) && $this->config->get('debug_mode')) {
            echo "<!-- APW Performance: {$data['duration']}s, {$data['queries_executed']} queries, " . 
                 number_format($data['memory_used'] / 1024) . "KB -->";
        }
    }
    
    public function getPerformanceReport(int $days = 7): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'apw_performance_logs';
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT operation, 
                   AVG(duration) as avg_duration,
                   MAX(duration) as max_duration,
                   AVG(memory_used) as avg_memory,
                   AVG(queries_executed) as avg_queries,
                   COUNT(*) as total_executions
            FROM {$table_name}
            WHERE timestamp >= %s
            GROUP BY operation
            ORDER BY avg_duration DESC
        ", $since_date), ARRAY_A);
        
        return $results ?: [];
    }
    
    private function analyzePerformance(array $data): void {
        $alerts = [];
        
        // Check duration threshold
        if (isset($this->thresholds[$data['operation'] . '_time'])) {
            $threshold = $this->thresholds[$data['operation'] . '_time'];
            if ($data['duration'] > $threshold) {
                $alerts[] = "Slow {$data['operation']}: {$data['duration']}s (threshold: {$threshold}s)";
            }
        }
        
        // Check memory usage
        if ($data['memory_used'] > $this->thresholds['memory_usage']) {
            $memory_mb = round($data['memory_used'] / 1024 / 1024, 2);
            $alerts[] = "High memory usage: {$memory_mb}MB";
        }
        
        // Check query count
        if ($data['queries_executed'] > $this->thresholds['database_queries']) {
            $alerts[] = "High query count: {$data['queries_executed']} queries";
        }
        
        if (!empty($alerts)) {
            $this->logger->warning('Performance threshold exceeded', [
                'operation' => $data['operation'],
                'alerts' => $alerts,
                'performance_data' => $data
            ]);
        }
    }
    
    private function logPerformanceData(array $data): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'apw_performance_logs';
        
        $wpdb->insert($table_name, [
            'operation' => $data['operation'],
            'duration' => $data['duration'],
            'memory_used' => $data['memory_used'],
            'queries_executed' => $data['queries_executed'],
            'peak_memory' => $data['peak_memory'],
            'timestamp' => $data['timestamp']
        ]);
    }
    
    private function getDatabaseQueryCount(): int {
        global $wpdb;
        return $wpdb->num_queries;
    }
}
```

#### 5.3.2 Error Tracking System
```php
<?php
namespace APW\WooPlugin\Maintenance\Monitoring;

class ErrorTracker {
    private LoggerServiceInterface $logger;
    private array $error_counts = [];
    private array $recent_errors = [];
    
    public function __construct(LoggerServiceInterface $logger) {
        $this->logger = $logger;
        $this->setupErrorHandling();
    }
    
    public function setupErrorHandling(): void {
        // Register error handlers
        set_error_handler([$this, 'handleError'], E_ALL);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
        
        // WordPress error handling
        add_action('wp_die_handler', [$this, 'handleWPDie']);
    }
    
    public function handleError(int $severity, string $message, string $file, int $line): bool {
        $error_data = [
            'type' => 'error',
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => time(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $this->recordError($error_data);
        
        // Don't interfere with normal error handling
        return false;
    }
    
    public function handleException(\Throwable $exception): void {
        $error_data = [
            'type' => 'exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => time(),
            'url' => $_SERVER['REQUEST_URI'] ?? ''
        ];
        
        $this->recordError($error_data);
        
        $this->logger->critical('Uncaught exception', $error_data);
    }
    
    public function handleShutdown(): void {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->recordError([
                'type' => 'fatal_error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => time()
            ]);
        }
    }
    
    public function getErrorSummary(int $hours = 24): array {
        $since = time() - ($hours * 3600);
        $filtered_errors = array_filter($this->recent_errors, function($error) use ($since) {
            return $error['timestamp'] >= $since;
        });
        
        $summary = [
            'total_errors' => count($filtered_errors),
            'error_types' => [],
            'most_frequent' => [],
            'recent_critical' => []
        ];
        
        foreach ($filtered_errors as $error) {
            $type = $error['type'];
            $summary['error_types'][$type] = ($summary['error_types'][$type] ?? 0) + 1;
            
            $error_key = $error['file'] . ':' . $error['line'];
            $summary['most_frequent'][$error_key] = ($summary['most_frequent'][$error_key] ?? 0) + 1;
            
            if (in_array($error['type'], ['exception', 'fatal_error'])) {
                $summary['recent_critical'][] = $error;
            }
        }
        
        arsort($summary['most_frequent']);
        $summary['most_frequent'] = array_slice($summary['most_frequent'], 0, 5, true);
        
        return $summary;
    }
    
    private function recordError(array $error_data): void {
        $this->recent_errors[] = $error_data;
        
        // Keep only last 100 errors in memory
        if (count($this->recent_errors) > 100) {
            array_shift($this->recent_errors);
        }
        
        // Log to database for persistence
        $this->logErrorToDatabase($error_data);
    }
    
    private function logErrorToDatabase(array $error_data): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'apw_error_logs';
        
        $wpdb->insert($table_name, [
            'error_type' => $error_data['type'],
            'message' => $error_data['message'],
            'file_path' => $error_data['file'] ?? '',
            'line_number' => $error_data['line'] ?? 0,
            'stack_trace' => $error_data['trace'] ?? '',
            'request_url' => $error_data['url'] ?? '',
            'user_agent' => $error_data['user_agent'] ?? '',
            'timestamp' => date('Y-m-d H:i:s', $error_data['timestamp'])
        ]);
    }
}
```

### 5.4 System Health Monitoring

#### 5.4.1 Health Checker
```php
<?php
namespace APW\WooPlugin\Maintenance\Core;

class HealthChecker {
    private LoggerServiceInterface $logger;
    private ConfigInterface $config;
    
    public function __construct(LoggerServiceInterface $logger, ConfigInterface $config) {
        $this->logger = $logger;
        $this->config = $config;
    }
    
    public function runHealthCheck(): array {
        $checks = [
            'database' => $this->checkDatabase(),
            'file_permissions' => $this->checkFilePermissions(),
            'plugin_dependencies' => $this->checkPluginDependencies(),
            'configuration' => $this->checkConfiguration(),
            'performance' => $this->checkPerformance(),
            'data_integrity' => $this->checkDataIntegrity()
        ];
        
        $overall_status = $this->calculateOverallHealth($checks);
        
        $result = [
            'overall_status' => $overall_status,
            'timestamp' => current_time('Y-m-d H:i:s'),
            'checks' => $checks
        ];
        
        $this->logHealthCheck($result);
        
        return $result;
    }
    
    private function checkDatabase(): array {
        global $wpdb;
        
        $checks = [];
        
        // Check database connection
        $checks['connection'] = [
            'status' => $wpdb->db_connect() ? 'pass' : 'fail',
            'message' => $wpdb->db_connect() ? 'Database connected' : 'Database connection failed'
        ];
        
        // Check required tables exist
        $required_tables = [
            $wpdb->prefix . 'apw_performance_logs',
            $wpdb->prefix . 'apw_error_logs'
        ];
        
        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            $checks['table_' . basename($table)] = [
                'status' => $exists ? 'pass' : 'fail',
                'message' => $exists ? "Table {$table} exists" : "Table {$table} missing"
            ];
        }
        
        // Check database size
        $db_size = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        
        $checks['database_size'] = [
            'status' => $db_size < 1000 ? 'pass' : 'warning',
            'message' => "Database size: {$db_size}MB",
            'value' => $db_size
        ];
        
        return $checks;
    }
    
    private function checkFilePermissions(): array {
        $checks = [];
        
        $paths_to_check = [
            'plugin_dir' => APW_WOO_PLUGIN_DIR,
            'uploads_dir' => wp_upload_dir()['basedir'] . '/apw-exports/',
            'logs_dir' => APW_WOO_PLUGIN_DIR . 'logs/'
        ];
        
        foreach ($paths_to_check as $name => $path) {
            if (!file_exists($path)) {
                $checks[$name] = [
                    'status' => 'fail',
                    'message' => "Path does not exist: {$path}"
                ];
                continue;
            }
            
            $is_writable = is_writable($path);
            $checks[$name] = [
                'status' => $is_writable ? 'pass' : 'fail',
                'message' => $is_writable ? "Writable: {$path}" : "Not writable: {$path}",
                'permissions' => substr(sprintf('%o', fileperms($path)), -4)
            ];
        }
        
        return $checks;
    }
    
    private function checkPluginDependencies(): array {
        $dependencies = [
            'WooCommerce' => class_exists('WooCommerce'),
            'ACF' => function_exists('get_field'),
            'Dynamic Pricing' => class_exists('WC_Dynamic_Pricing'),
            'Product Add-ons' => class_exists('WC_Product_Addons')
        ];
        
        $checks = [];
        foreach ($dependencies as $plugin => $is_active) {
            $checks[sanitize_key($plugin)] = [
                'status' => $is_active ? 'pass' : 'warning',
                'message' => $is_active ? "{$plugin} is active" : "{$plugin} is not active"
            ];
        }
        
        return $checks;
    }
    
    private function checkConfiguration(): array {
        $checks = [];
        
        // Check required configuration values
        $required_configs = [
            'payment_surcharges',
            'vip_discount_thresholds'
        ];
        
        foreach ($required_configs as $config_key) {
            $value = $this->config->get($config_key);
            $checks[$config_key] = [
                'status' => !empty($value) ? 'pass' : 'fail',
                'message' => !empty($value) ? "Configuration {$config_key} is set" : "Configuration {$config_key} is missing"
            ];
        }
        
        // Check log directory
        $log_dir = $this->config->get('log_directory');
        $checks['log_directory'] = [
            'status' => (is_dir($log_dir) && is_writable($log_dir)) ? 'pass' : 'fail',
            'message' => (is_dir($log_dir) && is_writable($log_dir)) ? 'Log directory accessible' : 'Log directory not accessible'
        ];
        
        return $checks;
    }
    
    private function checkPerformance(): array {
        $checks = [];
        
        // Memory limit check
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $checks['memory_limit'] = [
            'status' => $memory_limit >= (128 * 1024 * 1024) ? 'pass' : 'warning',
            'message' => 'Memory limit: ' . size_format($memory_limit),
            'value' => $memory_limit
        ];
        
        // Max execution time
        $max_execution = ini_get('max_execution_time');
        $checks['execution_time'] = [
            'status' => $max_execution >= 30 ? 'pass' : 'warning',
            'message' => "Max execution time: {$max_execution}s",
            'value' => $max_execution
        ];
        
        return $checks;
    }
    
    private function checkDataIntegrity(): array {
        global $wpdb;
        
        $checks = [];
        
        // Check for orphaned customer data
        $orphaned_meta = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} um 
            LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID 
            WHERE u.ID IS NULL 
            AND um.meta_key LIKE 'apw_%'
        ");
        
        $checks['orphaned_customer_data'] = [
            'status' => $orphaned_meta == 0 ? 'pass' : 'warning',
            'message' => $orphaned_meta == 0 ? 'No orphaned customer data' : "{$orphaned_meta} orphaned customer records",
            'value' => $orphaned_meta
        ];
        
        return $checks;
    }
    
    private function calculateOverallHealth(array $checks): string {
        $total_checks = 0;
        $failed_checks = 0;
        $warning_checks = 0;
        
        foreach ($checks as $category => $category_checks) {
            foreach ($category_checks as $check) {
                $total_checks++;
                if ($check['status'] === 'fail') {
                    $failed_checks++;
                } elseif ($check['status'] === 'warning') {
                    $warning_checks++;
                }
            }
        }
        
        if ($failed_checks > 0) {
            return 'critical';
        } elseif ($warning_checks > ($total_checks * 0.3)) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }
    
    private function logHealthCheck(array $result): void {
        $this->logger->info('System health check completed', [
            'overall_status' => $result['overall_status'],
            'total_checks' => count($result['checks']),
            'timestamp' => $result['timestamp']
        ]);
    }
}
```

## Automated Maintenance

### 5.5 Scheduled Maintenance Tasks

#### 5.5.1 Task Scheduler
```php
<?php
namespace APW\WooPlugin\Maintenance\Core;

class TaskScheduler {
    private array $scheduled_tasks = [];
    
    public function __construct() {
        $this->registerTasks();
        $this->scheduleRecurringTasks();
    }
    
    public function registerTasks(): void {
        // Daily maintenance tasks
        $this->addTask('apw_daily_cleanup', [
            'callback' => [$this, 'runDailyCleanup'],
            'schedule' => 'daily',
            'description' => 'Daily data cleanup and optimization'
        ]);
        
        // Weekly health checks
        $this->addTask('apw_weekly_health_check', [
            'callback' => [$this, 'runWeeklyHealthCheck'],
            'schedule' => 'weekly',
            'description' => 'Comprehensive system health check'
        ]);
        
        // Monthly database optimization
        $this->addTask('apw_monthly_db_optimization', [
            'callback' => [$this, 'runDatabaseOptimization'],
            'schedule' => 'monthly',
            'description' => 'Database cleanup and optimization'
        ]);
        
        // Hourly performance monitoring
        $this->addTask('apw_hourly_monitoring', [
            'callback' => [$this, 'runPerformanceCheck'],
            'schedule' => 'hourly',
            'description' => 'Performance monitoring and alerts'
        ]);
    }
    
    public function scheduleRecurringTasks(): void {
        foreach ($this->scheduled_tasks as $task_name => $task) {
            if (!wp_next_scheduled($task_name)) {
                wp_schedule_event(time(), $task['schedule'], $task_name);
            }
            
            add_action($task_name, $task['callback']);
        }
    }
    
    public function addTask(string $name, array $config): void {
        $this->scheduled_tasks[$name] = $config;
    }
    
    public function runDailyCleanup(): void {
        $cleanup = new DataCleanup();
        
        // Clean old performance logs
        $cleanup->cleanPerformanceLogs(7); // Keep 7 days
        
        // Clean old error logs
        $cleanup->cleanErrorLogs(30); // Keep 30 days
        
        // Clean old export files
        $cleanup->cleanExportFiles(7); // Keep 7 days
        
        // Optimize transients
        $cleanup->optimizeTransients();
        
        wp_mail(get_option('admin_email'), 'APW Daily Cleanup Complete', 
                'Daily maintenance tasks completed successfully.');
    }
    
    public function runWeeklyHealthCheck(): void {
        $health_checker = new HealthChecker();
        $health_report = $health_checker->runHealthCheck();
        
        if ($health_report['overall_status'] !== 'healthy') {
            $this->sendHealthAlert($health_report);
        }
    }
    
    public function runDatabaseOptimization(): void {
        $optimizer = new DatabaseOptimizer();
        
        $results = [
            'tables_optimized' => $optimizer->optimizeTables(),
            'indexes_created' => $optimizer->createMissingIndexes(),
            'orphaned_data_cleaned' => $optimizer->cleanOrphanedData()
        ];
        
        wp_mail(get_option('admin_email'), 'APW Database Optimization Complete',
                'Monthly database optimization results: ' . json_encode($results));
    }
    
    public function runPerformanceCheck(): void {
        $monitor = new PerformanceMonitor();
        $report = $monitor->getPerformanceReport(1); // Last hour
        
        // Check for performance issues
        foreach ($report as $operation) {
            if ($operation['avg_duration'] > 2.0) { // 2 second threshold
                wp_mail(get_option('admin_email'), 'APW Performance Alert',
                        "Slow operation detected: {$operation['operation']} averaging {$operation['avg_duration']}s");
            }
        }
    }
    
    private function sendHealthAlert(array $health_report): void {
        $message = "APW System Health Alert\n\n";
        $message .= "Overall Status: " . strtoupper($health_report['overall_status']) . "\n";
        $message .= "Timestamp: {$health_report['timestamp']}\n\n";
        
        foreach ($health_report['checks'] as $category => $checks) {
            $message .= strtoupper($category) . ":\n";
            foreach ($checks as $check_name => $check) {
                if ($check['status'] !== 'pass') {
                    $message .= "  - {$check_name}: {$check['status']} - {$check['message']}\n";
                }
            }
            $message .= "\n";
        }
        
        wp_mail(get_option('admin_email'), 'APW System Health Alert', $message);
    }
}
```

#### 5.5.2 Data Cleanup Service
```php
<?php
namespace APW\WooPlugin\Maintenance\Cleanup;

class DataCleanup {
    public function cleanPerformanceLogs(int $days_to_keep = 7): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'apw_performance_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %s",
            $cutoff_date
        ));
        
        return $deleted ?: 0;
    }
    
    public function cleanErrorLogs(int $days_to_keep = 30): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'apw_error_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %s",
            $cutoff_date
        ));
        
        return $deleted ?: 0;
    }
    
    public function cleanExportFiles(int $days_to_keep = 7): int {
        $export_dir = wp_upload_dir()['basedir'] . '/apw-exports/';
        
        if (!is_dir($export_dir)) {
            return 0;
        }
        
        $cutoff_time = time() - ($days_to_keep * 24 * 3600);
        $files = glob($export_dir . '*.csv');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    public function optimizeTransients(): array {
        global $wpdb;
        
        // Clean expired transients
        $expired = $wpdb->query("
            DELETE a, b FROM {$wpdb->options} a 
            LEFT JOIN {$wpdb->options} b ON a.option_name = REPLACE(b.option_name, '_timeout', '')
            WHERE a.option_name LIKE '_transient_timeout_%' 
            AND a.option_value < UNIX_TIMESTAMP()
        ");
        
        // Clean orphaned transient options
        $orphaned = $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%' 
            AND option_name NOT LIKE '_transient_timeout_%'
            AND option_name NOT IN (
                SELECT REPLACE(option_name, '_timeout', '') 
                FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_%'
            )
        ");
        
        return [
            'expired_cleaned' => $expired ?: 0,
            'orphaned_cleaned' => $orphaned ?: 0
        ];
    }
    
    public function cleanOrphanedCustomerData(): int {
        global $wpdb;
        
        // Clean orphaned user meta
        $deleted = $wpdb->query("
            DELETE um FROM {$wpdb->usermeta} um 
            LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID 
            WHERE u.ID IS NULL 
            AND um.meta_key LIKE 'apw_%'
        ");
        
        return $deleted ?: 0;
    }
}
```

## Debug Tools

### 5.6 Advanced Debugging

#### 5.6.1 Debug Manager
```php
<?php
namespace APW\WooPlugin\Maintenance\Debug;

class DebugManager {
    private LoggerServiceInterface $logger;
    private array $debug_sessions = [];
    
    public function __construct(LoggerServiceInterface $logger) {
        $this->logger = $logger;
    }
    
    public function startDebugSession(string $session_name): string {
        $session_id = uniqid($session_name . '_');
        
        $this->debug_sessions[$session_id] = [
            'name' => $session_name,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'checkpoints' => [],
            'variables' => []
        ];
        
        return $session_id;
    }
    
    public function addCheckpoint(string $session_id, string $checkpoint_name, array $context = []): void {
        if (!isset($this->debug_sessions[$session_id])) {
            return;
        }
        
        $checkpoint = [
            'name' => $checkpoint_name,
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'context' => $context
        ];
        
        $this->debug_sessions[$session_id]['checkpoints'][] = $checkpoint;
    }
    
    public function captureVariable(string $session_id, string $var_name, $value): void {
        if (!isset($this->debug_sessions[$session_id])) {
            return;
        }
        
        $this->debug_sessions[$session_id]['variables'][$var_name] = [
            'value' => $value,
            'type' => gettype($value),
            'memory_usage' => strlen(serialize($value)),
            'timestamp' => microtime(true)
        ];
    }
    
    public function endDebugSession(string $session_id): array {
        if (!isset($this->debug_sessions[$session_id])) {
            return [];
        }
        
        $session = $this->debug_sessions[$session_id];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $report = [
            'session_name' => $session['name'],
            'total_duration' => $end_time - $session['start_time'],
            'memory_used' => $end_memory - $session['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'checkpoints' => $this->analyzeCheckpoints($session['checkpoints']),
            'variables' => $session['variables']
        ];
        
        unset($this->debug_sessions[$session_id]);
        
        $this->logger->debug("Debug session '{$session['name']}' completed", $report);
        
        return $report;
    }
    
    public function dumpCurrentState(): array {
        global $wpdb;
        
        return [
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => wp_convert_hr_to_bytes(ini_get('memory_limit'))
            ],
            'database' => [
                'queries' => $wpdb->num_queries,
                'last_query' => $wpdb->last_query,
                'last_error' => $wpdb->last_error
            ],
            'wordpress' => [
                'version' => get_bloginfo('version'),
                'theme' => get_template(),
                'active_plugins' => get_option('active_plugins')
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        ];
    }
    
    private function analyzeCheckpoints(array $checkpoints): array {
        $analyzed = [];
        $previous_time = null;
        $previous_memory = null;
        
        foreach ($checkpoints as $checkpoint) {
            $analysis = [
                'name' => $checkpoint['name'],
                'timestamp' => $checkpoint['time'],
                'memory_usage' => $checkpoint['memory'],
                'context' => $checkpoint['context']
            ];
            
            if ($previous_time !== null) {
                $analysis['duration_since_last'] = $checkpoint['time'] - $previous_time;
                $analysis['memory_change'] = $checkpoint['memory'] - $previous_memory;
            }
            
            $analyzed[] = $analysis;
            $previous_time = $checkpoint['time'];
            $previous_memory = $checkpoint['memory'];
        }
        
        return $analyzed;
    }
}
```

## Admin Dashboard

### 5.7 Maintenance Dashboard

#### 5.7.1 Admin Dashboard
```php
<?php
namespace APW\WooPlugin\Maintenance\Admin;

class MaintenanceDashboard {
    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_apw_run_health_check', [$this, 'ajaxRunHealthCheck']);
        add_action('wp_ajax_apw_get_performance_data', [$this, 'ajaxGetPerformanceData']);
    }
    
    public function addAdminMenu(): void {
        add_submenu_page(
            'woocommerce',
            'APW Maintenance',
            'APW Maintenance',
            'manage_woocommerce',
            'apw-maintenance',
            [$this, 'renderDashboard']
        );
    }
    
    public function renderDashboard(): void {
        $health_checker = new HealthChecker();
        $performance_monitor = new PerformanceMonitor();
        
        $health_status = $this->getLatestHealthCheck();
        $performance_summary = $performance_monitor->getPerformanceReport(24);
        
        include APW_WOO_PLUGIN_DIR . 'templates/admin/maintenance-dashboard.php';
    }
    
    public function ajaxRunHealthCheck(): void {
        check_ajax_referer('apw_maintenance_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        $health_checker = new HealthChecker();
        $result = $health_checker->runHealthCheck();
        
        wp_send_json_success($result);
    }
    
    public function ajaxGetPerformanceData(): void {
        check_ajax_referer('apw_maintenance_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        $days = intval($_POST['days'] ?? 7);
        $performance_monitor = new PerformanceMonitor();
        $data = $performance_monitor->getPerformanceReport($days);
        
        wp_send_json_success($data);
    }
    
    private function getLatestHealthCheck(): ?array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'apw_health_checks';
        $latest = $wpdb->get_row(
            "SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT 1",
            ARRAY_A
        );
        
        return $latest ? json_decode($latest['check_data'], true) : null;
    }
}
```

## Database Schema

### 5.8 Maintenance Tables

#### 5.8.1 Database Setup
```php
<?php
namespace APW\WooPlugin\Maintenance\Database;

class MaintenanceSchema {
    public function createTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Performance logs table
        $performance_table = $wpdb->prefix . 'apw_performance_logs';
        $performance_sql = "CREATE TABLE {$performance_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            operation varchar(100) NOT NULL,
            duration decimal(10,6) NOT NULL,
            memory_used bigint(20) unsigned NOT NULL,
            queries_executed int(11) NOT NULL,
            peak_memory bigint(20) unsigned NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY operation_timestamp (operation, timestamp),
            KEY timestamp (timestamp)
        ) {$charset_collate};";
        
        // Error logs table
        $error_table = $wpdb->prefix . 'apw_error_logs';
        $error_sql = "CREATE TABLE {$error_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            error_type varchar(50) NOT NULL,
            message text NOT NULL,
            file_path varchar(500),
            line_number int(11),
            stack_trace longtext,
            request_url varchar(500),
            user_agent varchar(500),
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY error_type_timestamp (error_type, timestamp),
            KEY timestamp (timestamp)
        ) {$charset_collate};";
        
        // Health checks table
        $health_table = $wpdb->prefix . 'apw_health_checks';
        $health_sql = "CREATE TABLE {$health_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            overall_status varchar(20) NOT NULL,
            check_data longtext NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY status_timestamp (overall_status, timestamp)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($performance_sql);
        dbDelta($error_sql);
        dbDelta($health_sql);
    }
}
```

## Success Criteria

### 5.9 Maintenance System Metrics
- [ ] **Monitoring Coverage**: All critical operations monitored
- [ ] **Health Checks**: Comprehensive system health monitoring
- [ ] **Automated Cleanup**: Scheduled maintenance tasks running
- [ ] **Performance Tracking**: Real-time performance monitoring
- [ ] **Error Tracking**: Complete error logging and analysis
- [ ] **Debug Tools**: Advanced debugging capabilities
- [ ] **Admin Dashboard**: User-friendly maintenance interface
- [ ] **Alerting System**: Automated issue notifications

### 5.10 Reliability Targets
- [ ] **Uptime Monitoring**: 99.9% system availability
- [ ] **Performance Baselines**: Consistent performance metrics
- [ ] **Error Rate**: <1% error rate for critical operations
- [ ] **Recovery Time**: <5 minutes for automatic issue resolution
- [ ] **Data Integrity**: Zero data loss incidents

## Next Steps
Upon completion:
1. Proceed to Critical Issues specifications
2. Implement monitoring alerting system
3. Create maintenance runbooks and procedures
4. Establish performance baselines and SLAs