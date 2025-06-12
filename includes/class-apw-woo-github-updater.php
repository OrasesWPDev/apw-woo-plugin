<?php
/**
 * APW WooCommerce Plugin - GitHub Auto-Updater (Standalone)
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Standalone GitHub Auto-Updater for APW WooCommerce Plugin
 * 
 * Direct GitHub API integration without external dependencies.
 * Handles automatic updates for WordPress plugins via GitHub releases.
 */
class APW_Woo_GitHub_Updater {
    
    /**
     * Plugin file path
     *
     * @var string
     */
    private $plugin_file;
    
    /**
     * GitHub repository info
     *
     * @var array
     */
    private $github_repo;
    
    
    /**
     * Plugin data
     *
     * @var array
     */
    private $plugin_data;
    
    /**
     * GitHub API cache key
     *
     * @var string
     */
    private $cache_key;
    
    /**
     * Constructor
     *
     * @param string $plugin_file Full path to main plugin file
     * @param string $github_repo_url GitHub repository URL
     */
    public function __construct($plugin_file, $github_repo_url) {
        $this->plugin_file = $plugin_file;
        $this->plugin_data = get_plugin_data($plugin_file);
        
        // Parse GitHub repository info
        $this->github_repo = $this->parse_github_url($github_repo_url);
        $this->cache_key = 'apw_woo_github_update_' . md5($github_repo_url);
        
        // Only initialize in admin context
        if (is_admin()) {
            // Add custom cron schedule for 1-minute checks
            add_filter('cron_schedules', [$this, 'add_minute_cron_schedule']);
            $this->init_hooks();
            apw_woo_log("GitHub auto-updater initialized with 1-minute check interval");
        }
    }
    
    /**
     * Parse GitHub repository URL
     *
     * @param string $url GitHub repository URL
     * @return array Repository info
     */
    private function parse_github_url($url) {
        $url = rtrim($url, '/');
        $parts = parse_url($url);
        $path_parts = explode('/', trim($parts['path'], '/'));
        
        return [
            'owner' => $path_parts[0],
            'repo' => $path_parts[1],
            'api_url' => 'https://api.github.com/repos/' . $path_parts[0] . '/' . $path_parts[1]
        ];
    }
    
    /**
     * Add custom cron schedule for 1-minute checks
     *
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules array
     */
    public function add_minute_cron_schedule($schedules) {
        $schedules['apw_woo_minute_check'] = array(
            'interval' => 60, // 1 minute in seconds
            'display' => __('Every Minute (APW Auto-Updater Testing)')
        );
        return $schedules;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('plugins_api', [$this, 'plugin_api_call'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'download_package'], 10, 3);
        
        // Add admin notices when debug mode is enabled
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE) {
            add_action('admin_notices', [$this, 'display_update_notice']);
        }
        
        // Handle force update check
        if (isset($_GET['apw_force_update_check']) && current_user_can('manage_options')) {
            $this->force_update_check();
            wp_redirect(remove_query_arg('apw_force_update_check'));
            exit;
        }
        
        // Handle debug test (shows results instead of redirecting)
        if (isset($_GET['apw_debug_updater']) && current_user_can('manage_options')) {
            add_action('admin_notices', [$this, 'display_debug_results']);
        }
        
        // Set up check interval - 1 minute for testing
        if (!wp_next_scheduled('apw_woo_update_check')) {
            // Clear any existing scheduled event first
            wp_clear_scheduled_hook('apw_woo_update_check');
            // Schedule every minute for faster testing
            wp_schedule_event(time(), 'apw_woo_minute_check', 'apw_woo_update_check');
        }
        add_action('apw_woo_update_check', [$this, 'scheduled_update_check']);
    }
    
    /**
     * Check for plugin updates
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_updates($transient) {
        apw_woo_log('check_for_updates called');
        
        if (empty($transient->checked)) {
            apw_woo_log('No checked plugins in transient');
            return $transient;
        }
        
        $plugin_slug = plugin_basename($this->plugin_file);
        apw_woo_log('Plugin slug: ' . $plugin_slug);
        apw_woo_log('Current version: ' . $this->plugin_data['Version']);
        
        // Get remote version
        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            apw_woo_log('No remote version found');
            return $transient;
        }
        
        apw_woo_log('Remote version found: ' . $remote_version['version']);
        
        // Compare versions
        if (version_compare($this->plugin_data['Version'], $remote_version['version'], '<')) {
            apw_woo_log("Update available: {$this->plugin_data['Version']} → {$remote_version['version']}");
            
            $update_info = (object) [
                'id' => 'w.org/plugins/' . dirname($plugin_slug),
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $remote_version['version'],
                'url' => $this->plugin_data['PluginURI'] ?? 'https://github.com/OrasesWPDev/apw-woo-plugin',
                'package' => $remote_version['download_url'],
                'icons' => [
                    'default' => $this->plugin_data['PluginURI'] . '/assets/icon-256x256.png'
                ],
                'banners' => [],
                'banners_rtl' => [],
                'tested' => $this->plugin_data['TestedUpTo'] ?? '6.4',
                'requires_php' => $this->plugin_data['RequiresPHP'] ?? '7.2',
                'compatibility' => []
            ];
            
            apw_woo_log('Setting update info with package URL: ' . $remote_version['download_url']);
            $transient->response[$plugin_slug] = $update_info;
        }
        
        return $transient;
    }
    
    /**
     * Get remote version from GitHub API
     *
     * @return array|false Remote version info or false on failure
     */
    private function get_remote_version() {
        // Temporarily disable cache for debugging
        // $cached = get_transient($this->cache_key);
        // if ($cached !== false) {
        //     return $cached;
        // }
        
        apw_woo_log('Starting get_remote_version check...');
        
        // Try /releases/latest first, then fallback to /releases for private repos
        $url = $this->github_repo['api_url'] . '/releases/latest';
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'APW-WooCommerce-Plugin-Updater'
        ];
        
        // Add GitHub token if available (for private repos)
        $github_token = defined('APW_GITHUB_TOKEN') ? APW_GITHUB_TOKEN : null;
        apw_woo_log('GitHub token available: ' . ($github_token ? 'YES' : 'NO'));
        
        if ($github_token) {
            $headers['Authorization'] = 'token ' . $github_token;
        }
        
        apw_woo_log('Making request to: ' . $url);
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => $headers
        ]);
        
        if (is_wp_error($response)) {
            apw_woo_log('GitHub API error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $release_data = json_decode($body, true);
        
        apw_woo_log('Response code: ' . $response_code);
        apw_woo_log('Response body (first 200 chars): ' . substr($body, 0, 200));
        
        // If latest endpoint fails (404 for private repos without auth), try releases endpoint
        if ($response_code === 404 || (isset($release_data['message']) && $release_data['message'] === 'Not Found')) {
            apw_woo_log('Latest release endpoint failed, trying releases list', 'warning');
            return $this->get_remote_version_from_releases();
        }
        
        if (!$release_data || isset($release_data['message'])) {
            apw_woo_log('GitHub API response error: ' . ($release_data['message'] ?? 'Unknown error'), 'error');
            return false;
        }
        
        $version_data = [
            'version' => ltrim($release_data['tag_name'], 'v'),
            'download_url' => $release_data['zipball_url'],
            'details_url' => $release_data['html_url'],
            'release_notes' => $release_data['body'] ?? ''
        ];
        
        // Cache for 1 minute
        set_transient($this->cache_key, $version_data, MINUTE_IN_SECONDS);
        
        return $version_data;
    }
    
    /**
     * Get remote version from releases list (fallback for private repos)
     *
     * @return array|false Remote version info or false on failure
     */
    private function get_remote_version_from_releases() {
        apw_woo_log('Trying fallback releases endpoint...');
        
        $url = $this->github_repo['api_url'] . '/releases';
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'APW-WooCommerce-Plugin-Updater'
        ];
        
        // Add GitHub token if available
        $github_token = defined('APW_GITHUB_TOKEN') ? APW_GITHUB_TOKEN : null;
        if ($github_token) {
            $headers['Authorization'] = 'token ' . $github_token;
        }
        
        apw_woo_log('Making fallback request to: ' . $url);
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => $headers
        ]);
        
        if (is_wp_error($response)) {
            apw_woo_log('GitHub releases API error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $releases = json_decode($body, true);
        
        apw_woo_log('Fallback response code: ' . $response_code);
        apw_woo_log('Fallback response body (first 200 chars): ' . substr($body, 0, 200));
        
        if (!$releases || !is_array($releases) || empty($releases)) {
            apw_woo_log('No releases found or invalid response', 'error');
            return false;
        }
        
        // Get the latest non-draft, non-prerelease
        $latest_release = null;
        foreach ($releases as $release) {
            // Ensure $release is an array before accessing its properties
            if (!is_array($release)) {
                apw_woo_log('Invalid release data format (not array): ' . print_r($release, true), 'warning');
                continue;
            }
            
            // Check if required keys exist
            if (!isset($release['draft']) || !isset($release['prerelease'])) {
                apw_woo_log('Release missing draft/prerelease keys: ' . print_r($release, true), 'warning');
                continue;
            }
            
            if (!$release['draft'] && !$release['prerelease']) {
                $latest_release = $release;
                break;
            }
        }
        
        if (!$latest_release) {
            apw_woo_log('No published releases found', 'warning');
            return false;
        }
        
        $version_data = [
            'version' => ltrim($latest_release['tag_name'], 'v'),
            'download_url' => $latest_release['zipball_url'],
            'details_url' => $latest_release['html_url'],
            'release_notes' => $latest_release['body'] ?? ''
        ];
        
        // Cache for 1 minute
        set_transient($this->cache_key, $version_data, MINUTE_IN_SECONDS);
        apw_woo_log('Successfully retrieved version from releases list: ' . $version_data['version']);
        
        return $version_data;
    }
    
    /**
     * Handle plugin API calls
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object|array
     */
    public function plugin_api_call($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        $plugin_slug = dirname(plugin_basename($this->plugin_file));
        if ($args->slug !== $plugin_slug) {
            return $result;
        }
        
        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            return $result;
        }
        
        $plugin_info = (object) [
            'name' => $this->plugin_data['Name'] ?? 'APW WooCommerce Plugin',
            'slug' => $plugin_slug,
            'version' => $remote_version['version'],
            'author' => '<a href="' . ($this->plugin_data['AuthorURI'] ?? 'https://orases.com') . '">' . ($this->plugin_data['Author'] ?? 'Orases') . '</a>',
            'author_profile' => $this->plugin_data['AuthorURI'] ?? 'https://orases.com',
            'contributors' => [
                'orases' => [
                    'profile' => 'https://orases.com',
                    'avatar' => 'https://secure.gravatar.com/avatar/?s=96&d=monsterid&r=g',
                    'display_name' => 'Orases'
                ]
            ],
            'homepage' => $this->plugin_data['PluginURI'] ?? 'https://github.com/OrasesWPDev/apw-woo-plugin',
            'short_description' => $this->plugin_data['Description'] ?? 'Custom WooCommerce enhancements for displaying products across shop, category, and product pages.',
            'description' => $this->plugin_data['Description'] ?? 'Custom WooCommerce enhancements for displaying products across shop, category, and product pages.',
            'requires' => $this->plugin_data['RequiresAtLeast'] ?? '5.3',
            'tested' => $this->plugin_data['TestedUpTo'] ?? '6.4',
            'requires_php' => $this->plugin_data['RequiresPHP'] ?? '7.2',
            'rating' => 100,
            'ratings' => [5 => 1, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            'num_ratings' => 1,
            'support_threads' => 0,
            'support_threads_resolved' => 0,
            'downloaded' => 0,
            'active_installs' => 1,
            'last_updated' => date('Y-m-d H:i:s'),
            'added' => date('Y-m-d'),
            'sections' => [
                'description' => $this->plugin_data['Description'] ?? 'Custom WooCommerce enhancements for displaying products across shop, category, and product pages.',
                'changelog' => $remote_version['release_notes'] ?? 'No changelog available.',
                'installation' => 'Upload the plugin files to the `/wp-content/plugins/apw-woo-plugin` directory, or install the plugin through the WordPress plugins screen directly. Activate the plugin through the \'Plugins\' screen in WordPress.'
            ],
            'download_link' => $remote_version['download_url'],
            'trunk' => $remote_version['download_url'],
            'donate_link' => '',
            'banners' => [
                'low' => '',
                'high' => ''
            ],
            'icons' => [
                '1x' => '',
                '2x' => '',
                'svg' => ''
            ],
            'screenshots' => [],
            'tags' => [
                'woocommerce' => 'WooCommerce',
                'ecommerce' => 'E-Commerce',
                'shop' => 'Shop'
            ],
            'versions' => [
                $remote_version['version'] => $remote_version['download_url']
            ],
            'business_model' => false,
            'repository_url' => '',
            'commercial_support_url' => '',
            'donate_link_2' => '',
            'compatibility' => []
        ];
        
        apw_woo_log('Plugin API response prepared: ' . print_r($plugin_info, true));
        return $plugin_info;
    }
    
    /**
     * Download package from GitHub
     *
     * @param bool $reply
     * @param string $package
     * @param object $upgrader
     * @return bool|string
     */
    public function download_package($reply, $package, $upgrader) {
        apw_woo_log('DOWNLOAD HANDLER CALLED - Package: ' . $package);
        apw_woo_log('Repository identifier: ' . $this->github_repo['owner'] . '/' . $this->github_repo['repo']);
        
        // Only handle our plugin updates (check for both github.com and api.github.com URLs)
        $repo_identifier = $this->github_repo['owner'] . '/' . $this->github_repo['repo'];
        if (strpos($package, $repo_identifier) === false) {
            apw_woo_log('Skipping download - not our plugin: ' . $package);
            return $reply;
        }
        
        apw_woo_log('=== Starting custom download handler ===');
        apw_woo_log('Package URL: ' . $package);
        apw_woo_log('Upgrader type: ' . get_class($upgrader));
        
        // For private repos, we need to add authentication headers
        $github_token = defined('APW_GITHUB_TOKEN') ? APW_GITHUB_TOKEN : null;
        if (!$github_token) {
            apw_woo_log('ERROR: No GitHub token available for private repository', 'error');
            return new WP_Error('no_github_token', 'GitHub token required for private repository access.');
        }
        
        apw_woo_log('Using GitHub token for authenticated download');
        
        // Download with authentication headers - we need to use wp_remote_get instead
        $args = [
            'timeout' => 300,
            'headers' => [
                'Authorization' => 'token ' . $github_token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'APW-WooCommerce-Plugin-Updater'
            ]
        ];
        
        apw_woo_log('Download arguments: ' . print_r($args, true));
        
        // Use wp_remote_get for proper header support
        $response = wp_remote_get($package, $args);
        
        if (is_wp_error($response)) {
            apw_woo_log('Download request failed: ' . $response->get_error_message(), 'error');
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            apw_woo_log('Download failed with HTTP ' . $response_code, 'error');
            return new WP_Error('download_failed', 'Download failed with HTTP ' . $response_code);
        }
        
        // Create temporary file
        $tmp_file = wp_tempnam($package);
        if (!$tmp_file) {
            apw_woo_log('Failed to create temporary file', 'error');
            return new WP_Error('temp_file_failed', 'Failed to create temporary file');
        }
        
        // Write the response body to temp file
        $body = wp_remote_retrieve_body($response);
        $bytes_written = file_put_contents($tmp_file, $body);
        
        if ($bytes_written === false) {
            unlink($tmp_file);
            apw_woo_log('Failed to write download to temporary file', 'error');
            return new WP_Error('write_failed', 'Failed to write download to temporary file');
        }
        
        $download_file = $tmp_file;
        
        apw_woo_log('Download successful to: ' . $download_file);
        apw_woo_log('File size: ' . (file_exists($download_file) ? filesize($download_file) . ' bytes' : 'File not found'));
        apw_woo_log('=== Download handler complete ===');
        
        return $download_file;
    }
    
    /**
     * Force an update check
     *
     * @return bool Success status
     */
    public function force_update_check() {
        delete_transient($this->cache_key);
        apw_woo_log("Forcing update check");
        
        // Trigger WordPress update check
        wp_update_plugins();
        
        return true;
    }
    
    /**
     * Scheduled update check
     */
    public function scheduled_update_check() {
        if (!is_admin()) {
            return;
        }
        
        // Clear cache periodically to ensure fresh checks
        delete_transient($this->cache_key);
        wp_update_plugins();
    }
    
    /**
     * Display update notices when debug mode is enabled
     */
    public function display_update_notice() {
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE) {
            $last_check = get_option('_transient_timeout_' . $this->cache_key);
            $last_check_time = $last_check ? human_time_diff($last_check - MINUTE_IN_SECONDS, time()) . ' ago' : 'Never';
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>APW Auto-Updater:</strong> 
                    Last Check: <?php echo esc_html($last_check_time); ?> |
                    <a href="<?php echo esc_url(add_query_arg('apw_force_update_check', '1')); ?>">Force Check</a> |
                    <a href="<?php echo esc_url(add_query_arg('apw_debug_updater', '1')); ?>">Debug Test</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Display debug results for updater testing
     */
    public function display_debug_results() {
        $remote_version = $this->get_remote_version();
        $github_token = defined('APW_GITHUB_TOKEN') ? 'Configured' : 'Not configured';
        ?>
        <div class="notice notice-warning">
            <h3>APW Auto-Updater Debug Results</h3>
            <p><strong>Current Version:</strong> <?php echo esc_html($this->plugin_data['Version']); ?></p>
            <p><strong>GitHub Token:</strong> <?php echo esc_html($github_token); ?></p>
            <p><strong>Repository:</strong> <?php echo esc_html($this->github_repo['api_url']); ?></p>
            <?php if ($remote_version): ?>
                <p><strong>Remote Version Found:</strong> <?php echo esc_html($remote_version['version']); ?></p>
                <p><strong>Download URL:</strong> <?php echo esc_html($remote_version['download_url']); ?></p>
                <p><strong>Update Available:</strong> <?php echo version_compare($this->plugin_data['Version'], $remote_version['version'], '<') ? 'YES' : 'NO'; ?></p>
            <?php else: ?>
                <p><strong>Remote Version:</strong> <span style="color: red;">Failed to retrieve</span></p>
                <p><em>Check debug logs for details</em></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get update checker status information
     *
     * @return array Status information
     */
    public function get_update_checker_status() {
        $remote_version = $this->get_remote_version();
        
        return [
            'status' => 'active',
            'github_repo' => $this->github_repo['api_url'],
            'current_version' => $this->plugin_data['Version'],
            'remote_version' => $remote_version ? $remote_version['version'] : 'Unknown',
            'update_available' => $remote_version ? version_compare($this->plugin_data['Version'], $remote_version['version'], '<') : false,
            'check_period' => 'every minute (testing)',
            'cache_key' => $this->cache_key
        ];
    }
    
}