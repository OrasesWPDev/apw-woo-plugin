<?php
/**
 * Environment Manager Class
 *
 * Handles environment detection and API endpoint configuration for
 * Allpoint Command integration across different deployment environments.
 *
 * @package APW_Woo_Plugin
 * @since   2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Environment_Manager Class
 *
 * Manages environment detection and provides appropriate API endpoints
 * for the Allpoint Command integration system.
 */
class APW_Woo_Environment_Manager
{
    /**
     * Instance of this class
     * @var self
     */
    private static $instance = null;

    /**
     * Current environment identifier
     * @var string
     */
    private $current_environment;

    /**
     * Environment configuration mapping
     * @var array
     */
    private $environments = [
        'production' => [
            'name' => 'Production',
            'base_url' => 'https://allpointcommand.com',
            'api_endpoint' => 'https://allpointcommand.com/api/woocommerce',
            'registration_url' => 'https://allpointcommand.com/create-company',
            'detection_patterns' => [
                'allpointwireless.com',
                'allpointcommand.com'
            ]
        ],
        'staging' => [
            'name' => 'Staging',
            'base_url' => 'https://watm.beta.orases.dev',
            'api_endpoint' => 'https://watm.beta.orases.dev/api/woocommerce',
            'registration_url' => 'https://watm.beta.orases.dev/create-company',
            'detection_patterns' => [
                'watm.beta.orases.dev'
            ]
        ],
        'review' => [
            'name' => 'Review',
            'base_url' => 'https://watm.review.orases.dev',
            'api_endpoint' => 'https://watm.review.orases.dev/api/woocommerce',
            'registration_url' => 'https://watm.review.orases.dev/create-company',
            'detection_patterns' => [
                'watm.review.orases.dev'
            ]
        ],
        'wp_staging' => [
            'name' => 'WordPress Staging',
            'base_url' => 'http://allpointstage.wpenginepowered.com/',
            'api_endpoint' => 'https://watm.beta.orases.dev/api/woocommerce',
            'registration_url' => 'https://watm.beta.orases.dev/create-company',
            'detection_patterns' => [
                'allpointstage.wpenginepowered.com'
            ]
        ],
        'wp_development' => [
            'name' => 'WordPress Development',
            'base_url' => 'http://allpointwi1dev.wpenginepowered.com/',
            'api_endpoint' => 'https://watm.review.orases.dev/api/woocommerce',
            'registration_url' => 'https://watm.review.orases.dev/create-company',
            'detection_patterns' => [
                'allpointwi1dev.wpenginepowered.com'
            ]
        ],
        'local_development' => [
            'name' => 'Local Development',
            'base_url' => 'http://localhost:10013/',
            'api_endpoint' => 'https://watm.review.orases.dev/api/woocommerce',
            'registration_url' => 'https://watm.review.orases.dev/create-company',
            'detection_patterns' => [
                'localhost:10013',
                '127.0.0.1:10013'
            ]
        ]
    ];

    /**
     * Get instance
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to prevent direct instantiation.
     */
    private function __construct()
    {
        $this->detect_environment();
        
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Environment Manager initialized. Current environment: ' . $this->current_environment);
        }
    }

    /**
     * Detect current environment based on site URL and constants
     */
    private function detect_environment()
    {
        $site_url = get_site_url();
        $this->current_environment = 'production'; // Default fallback

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Environment Manager: Detecting environment for site URL: ' . $site_url);
        }

        // Check for WordPress environment constants first
        if (defined('WP_ENVIRONMENT_TYPE')) {
            $wp_env = WP_ENVIRONMENT_TYPE;
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Environment Manager: WP_ENVIRONMENT_TYPE defined as: ' . $wp_env);
            }
            
            // Map WordPress environment types to our environments
            switch ($wp_env) {
                case 'local':
                case 'development':
                    $this->current_environment = 'local_development';
                    return;
                case 'staging':
                    $this->current_environment = 'staging';
                    return;
            }
        }

        // Check site URL against detection patterns
        foreach ($this->environments as $env_key => $env_config) {
            foreach ($env_config['detection_patterns'] as $pattern) {
                if (strpos($site_url, $pattern) !== false) {
                    $this->current_environment = $env_key;
                    if (function_exists('apw_woo_log')) {
                        apw_woo_log('Environment Manager: Matched pattern "' . $pattern . '" for environment: ' . $env_key);
                    }
                    return;
                }
            }
        }

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Environment Manager: No specific environment detected, using production as default');
        }
    }

    /**
     * Get current environment identifier
     * 
     * @return string Current environment key
     */
    public function get_current_environment()
    {
        return $this->current_environment;
    }

    /**
     * Get current environment configuration
     * 
     * @return array Current environment configuration
     */
    public function get_current_config()
    {
        return $this->environments[$this->current_environment] ?? $this->environments['production'];
    }

    /**
     * Get API endpoint for current environment
     * 
     * @return string API endpoint URL
     */
    public function get_api_endpoint()
    {
        $config = $this->get_current_config();
        return $config['api_endpoint'];
    }

    /**
     * Get registration URL for current environment
     * 
     * @param string $token Optional token to append as query parameter
     * @return string Registration URL
     */
    public function get_registration_url($token = '')
    {
        $config = $this->get_current_config();
        $url = $config['registration_url'];
        
        if (!empty($token)) {
            $url .= '?token=' . urlencode($token);
        }
        
        return $url;
    }

    /**
     * Get base URL for current environment
     * 
     * @return string Base URL
     */
    public function get_base_url()
    {
        $config = $this->get_current_config();
        return $config['base_url'];
    }

    /**
     * Get environment name for display
     * 
     * @return string Environment display name
     */
    public function get_environment_name()
    {
        $config = $this->get_current_config();
        return $config['name'];
    }

    /**
     * Check if current environment is production
     * 
     * @return bool True if production environment
     */
    public function is_production()
    {
        return $this->current_environment === 'production';
    }

    /**
     * Check if current environment is development/staging
     * 
     * @return bool True if non-production environment
     */
    public function is_development()
    {
        return !$this->is_production();
    }

    /**
     * Get all available environments (for testing/debugging)
     * 
     * @return array All environment configurations
     */
    public function get_all_environments()
    {
        return $this->environments;
    }

    /**
     * Override environment (for testing purposes)
     * 
     * @param string $environment Environment key to set
     * @return bool True if environment exists and was set
     */
    public function set_environment($environment)
    {
        if (isset($this->environments[$environment])) {
            $this->current_environment = $environment;
            
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Environment Manager: Environment manually set to: ' . $environment);
            }
            
            return true;
        }
        
        return false;
    }
}

/**
 * Function to initialize the Environment Manager.
 * To be called from the main plugin file.
 */
function apw_woo_initialize_environment_manager()
{
    return APW_Woo_Environment_Manager::get_instance();
}