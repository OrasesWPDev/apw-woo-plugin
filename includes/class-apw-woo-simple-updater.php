<?php
/**
 * APW WooCommerce Plugin - Simple GitHub Auto-Updater
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple GitHub Auto-Updater using YahnisElsts Plugin Update Checker v5.6
 * 
 * Industry-standard library that doesn't interfere with WordPress core update processes.
 * Direct GitHub integration without requiring additional servers or infrastructure.
 * 
 * Based on proven implementation from another working plugin.
 */
class APW_Woo_Simple_Updater {
    
    /**
     * Plugin file path
     *
     * @var string
     */
    private $plugin_file;
    
    /**
     * GitHub repository URL
     *
     * @var string
     */
    private $github_repo_url;
    
    /**
     * Update checker instance
     *
     * @var object
     */
    private $update_checker;
    
    /**
     * Constructor
     *
     * @param string $plugin_file Full path to main plugin file
     * @param string $github_repo_url GitHub repository URL
     */
    public function __construct($plugin_file, $github_repo_url) {
        $this->plugin_file = $plugin_file;
        $this->github_repo_url = $github_repo_url;
        
        // Only initialize in admin context
        if (is_admin()) {
            $this->init_update_checker();
            apw_woo_log("Simple auto-updater initialized with Plugin Update Checker v5.6");
        }
    }
    
    /**
     * Initialize the Plugin Update Checker
     */
    private function init_update_checker() {
        // Load the Plugin Update Checker library
        require_once APW_WOO_PLUGIN_DIR . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';
        
        // Check if the class exists
        if (!class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            apw_woo_log('Plugin Update Checker library not found or incompatible version', 'error');
            return false;
        }
        
        try {
            // Initialize the update checker with 1 minute check period (1/60 hours)
            $this->update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                $this->github_repo_url,
                $this->plugin_file,
                'apw-woo-plugin',
                1/60  // Check period in hours (1 minute)
            );
            
            // Set branch (optional, defaults to 'main')
            $this->update_checker->setBranch('main');
            
            // Alternative: Set check period via scheduler property after initialization
            // $this->update_checker->scheduler->checkPeriod = 1/60;
            
            // Add GitHub token if available (for private repos)
            $github_token = defined('APW_GITHUB_TOKEN') ? APW_GITHUB_TOKEN : null;
            if ($github_token) {
                $this->update_checker->setAuthentication($github_token);
                apw_woo_log('GitHub authentication configured for private repository');
            }
            
            apw_woo_log('Plugin Update Checker initialized successfully');
            apw_woo_log('Repository: ' . $this->github_repo_url);
            apw_woo_log('Check period: 1 minute');
            apw_woo_log('Authentication: ' . ($github_token ? 'Enabled' : 'Disabled'));
            
            return true;
            
        } catch (Exception $e) {
            apw_woo_log('Failed to initialize Plugin Update Checker: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Get update checker instance
     *
     * @return object|null Update checker instance or null if not initialized
     */
    public function get_update_checker() {
        return $this->update_checker;
    }
    
    /**
     * Force an update check
     *
     * @return bool Success status
     */
    public function force_update_check() {
        if (!$this->update_checker) {
            return false;
        }
        
        try {
            $this->update_checker->checkForUpdates();
            apw_woo_log("Forced update check completed");
            return true;
        } catch (Exception $e) {
            apw_woo_log('Force update check failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Set the check period for updates
     *
     * @param float $hours Check period in hours (e.g., 1/60 for 1 minute)
     * @return bool Success status
     */
    public function set_check_period($hours) {
        if (!$this->update_checker || !isset($this->update_checker->scheduler)) {
            return false;
        }
        
        $this->update_checker->scheduler->checkPeriod = $hours;
        apw_woo_log("Update check period changed to " . ($hours * 60) . " minutes");
        return true;
    }
    
    /**
     * Get the current check period
     *
     * @return float|null Check period in hours, or null if not available
     */
    public function get_check_period() {
        if (!$this->update_checker || !isset($this->update_checker->scheduler)) {
            return null;
        }
        
        return $this->update_checker->scheduler->checkPeriod;
    }
    
    /**
     * Get update checker status information
     *
     * @return array Status information
     */
    public function get_update_checker_status() {
        if (!$this->update_checker) {
            return [
                'status' => 'inactive',
                'error' => 'Update checker not initialized'
            ];
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        
        $check_period_hours = isset($this->update_checker->scheduler) ? $this->update_checker->scheduler->checkPeriod : 'Unknown';
        $check_period_minutes = is_numeric($check_period_hours) ? round($check_period_hours * 60, 2) : 'Unknown';
        
        return [
            'status' => 'active',
            'library' => 'YahnisElsts Plugin Update Checker v5.6',
            'github_repo' => $this->github_repo_url,
            'current_version' => $plugin_data['Version'],
            'check_period' => $check_period_minutes . ' minutes (' . $check_period_hours . ' hours)',
            'authentication' => defined('APW_GITHUB_TOKEN') ? 'Enabled' : 'Disabled',
            'last_check' => 'Handled by library'
        ];
    }
    
}