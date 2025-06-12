<?php
/**
 * APW WooCommerce Plugin - GitHub Auto-Updater
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple Auto-Updater for APW WooCommerce Plugin
 * 
 * Handles GitHub-based automatic updates with environment detection
 * for staging and production environments.
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
     * Current environment (staging or production)
     *
     * @var string
     */
    private $environment;
    
    /**
     * Constructor
     *
     * @param string $plugin_file Full path to main plugin file
     * @param string $github_repo_url GitHub repository URL
     */
    public function __construct($plugin_file, $github_repo_url) {
        $this->plugin_file = $plugin_file;
        $this->github_repo_url = $github_repo_url;
        $this->environment = $this->detect_environment();
        
        // Only initialize in admin context
        if (is_admin()) {
            $this->init_update_checker();
            
            // Log initialization
            apw_woo_log("Auto-updater initialized for {$this->environment} environment");
        }
    }
    
    /**
     * Detect current environment based on site URL
     *
     * @return string 'staging' or 'production'
     */
    private function detect_environment() {
        $site_url = get_site_url();
        
        if (strpos($site_url, 'allpointstage.wpenginepowered.com') !== false) {
            return 'staging';
        } elseif (strpos($site_url, 'allpointwireless.com') !== false) {
            return 'production';
        }
        
        // Default to production for safety
        return 'production';
    }
    
    /**
     * Initialize the update checker
     */
    private function init_update_checker() {
        // Load the Plugin Update Checker library
        require_once APW_WOO_PLUGIN_DIR . 'includes/vendor/plugin-update-checker/plugin-update-checker.php';
        
        // Create update checker instance
        $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $this->github_repo_url,
            $this->plugin_file,
            'apw-woo-plugin'
        );
        
        // Set check period to 1 minute for both environments (as requested)
        $this->update_checker->setCheckPeriod(1/60);
        
        // Enable release assets
        $this->update_checker->enableReleaseAssets();
        
        // Add environment-specific logging
        if ($this->environment === 'staging') {
            $this->setup_staging_features();
        }
        
        apw_woo_log("Update checker configured for {$this->environment} environment with 1-minute check period");
    }
    
    /**
     * Setup additional features for staging environment
     */
    private function setup_staging_features() {
        // Add more verbose logging for staging
        add_action('admin_notices', [$this, 'display_staging_update_notice']);
        
        apw_woo_log("Enhanced staging features enabled for auto-updater");
    }
    
    /**
     * Display staging-specific update notices
     */
    public function display_staging_update_notice() {
        if (APW_WOO_DEBUG_MODE && $this->environment === 'staging') {
            $last_check = $this->get_last_check_time();
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>APW Auto-Updater (Staging):</strong> 
                    Environment: <?php echo esc_html($this->environment); ?> | 
                    Last Check: <?php echo esc_html($last_check); ?> |
                    <a href="<?php echo esc_url(add_query_arg('apw_force_update_check', '1')); ?>">Force Check</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get update checker status information
     *
     * @return array Status information
     */
    public function get_update_checker_status() {
        if (!$this->update_checker) {
            return [
                'status' => 'not_initialized',
                'environment' => $this->environment,
                'message' => 'Update checker not initialized'
            ];
        }
        
        $state = $this->update_checker->getState();
        
        return [
            'status' => 'active',
            'environment' => $this->environment,
            'github_repo' => $this->github_repo_url,
            'last_check' => $this->get_last_check_time(),
            'check_period' => '1 minute',
            'plugin_slug' => 'apw-woo-plugin',
            'update_available' => $this->update_checker->getUpdate() !== null,
            'state' => $state
        ];
    }
    
    /**
     * Force an update check
     *
     * @return object|null Update information if available
     */
    public function force_update_check() {
        if (!$this->update_checker) {
            apw_woo_log('Cannot force update check - updater not initialized');
            return null;
        }
        
        apw_woo_log("Forcing update check for {$this->environment} environment");
        
        // Clear cached update data
        $this->update_checker->resetUpdateState();
        
        // Perform immediate check
        $update = $this->update_checker->checkForUpdates();
        
        apw_woo_log("Forced update check completed - Update available: " . ($update ? 'Yes' : 'No'));
        
        return $update;
    }
    
    /**
     * Get the last check time in human-readable format
     *
     * @return string Last check time
     */
    private function get_last_check_time() {
        if (!$this->update_checker) {
            return 'Never';
        }
        
        $state = $this->update_checker->getState();
        $last_check = isset($state->lastCheck) ? $state->lastCheck : null;
        
        if ($last_check) {
            return human_time_diff($last_check, time()) . ' ago';
        }
        
        return 'Never';
    }
    
    /**
     * Get the update checker instance (for testing)
     *
     * @return object|null Update checker instance
     */
    public function get_update_checker() {
        return $this->update_checker;
    }
    
    /**
     * Get current environment
     *
     * @return string Current environment
     */
    public function get_environment() {
        return $this->environment;
    }
}