<?php
/**
 * Logger functionality for APW WooCommerce Plugin
 *
 * @package APW_Woo_Plugin
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * APW_Woo_Logger Class
 *
 * Handles all logging functionality for the plugin with enhanced
 * security and performance features.
 *
 * @since 1.0.0
 */
class APW_Woo_Logger {

    /**
     * Flag to track if logs directory is set up
     *
     * @var boolean
     */
    private static $logs_setup = false;

    /**
     * Create log directory and security files if they don't exist
     *
     * Sets up the logs directory with proper security measures to
     * prevent unauthorized access to potentially sensitive debug information.
     *
     * @since 1.0.0
     * @return void
     */
    public static function setup_logs() {
        if (!APW_WOO_DEBUG_MODE) {
            return;
        }

        if (self::$logs_setup) {
            return;
        }

        $log_dir = APW_WOO_PLUGIN_DIR . 'logs';

        // Only proceed if directory doesn't exist
        if (!file_exists($log_dir)) {
            // Create logs directory
            if (!wp_mkdir_p($log_dir)) {
                // Log to PHP error log if we can't create directory
                error_log('APW Woo Plugin: Failed to create logs directory at ' . $log_dir);
                return;
            }

            // Create .htaccess file with strict access restrictions
            $htaccess_content = "# Deny access to all files in this directory
# Enhanced security for sensitive log files

<Files \"*\">
    # Apache 2.4+
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    
    # Apache 2.2
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</Files>

# Disable script execution
<IfModule mod_php.c>
    php_flag engine off
</IfModule>

# Prevent directory listing
Options -Indexes
";
            if (!file_put_contents($log_dir . '/.htaccess', $htaccess_content)) {
                error_log('APW Woo Plugin: Failed to create .htaccess in logs directory');
            }

            // Create index.php to prevent directory listing (fallback security)
            if (!file_put_contents($log_dir . '/index.php', '<?php // Silence is golden.')) {
                error_log('APW Woo Plugin: Failed to create index.php in logs directory');
            }

            // Add a readme file to explain purpose of directory
            $readme_content = "This directory contains debug logs for the APW WooCommerce Plugin.\n";
            $readme_content .= "These files contain debugging information and should be kept secure.\n";
            $readme_content .= "In production environments, disable debug mode in the plugin settings.\n";

            file_put_contents($log_dir . '/README.txt', $readme_content);
        }

        self::$logs_setup = true;
    }

    /**
     * Log messages when debug mode is enabled
     *
     * Creates detailed log entries with timestamps, handles various data types,
     * and manages log rotation to prevent excessive disk usage.
     *
     * @since 1.0.0
     * @param mixed $message The message or data to log
     * @param string $level Optional. Log level (info, warning, error). Default 'info'.
     * @return void
     */
    public static function log($message, $level = 'info') {
        // Only log if debug mode is enabled
        if (!APW_WOO_DEBUG_MODE) {
            return;
        }

        // Create logs directory if it doesn't exist
        self::setup_logs();

        // Set timezone to EST (New York)
        $timezone = new DateTimeZone('America/New_York');
        $date = new DateTime('now', $timezone);

        // Format the date for file name and timestamp
        $log_file = APW_WOO_PLUGIN_DIR . 'logs/debug-' . $date->format('Y-m-d') . '.log';

        // Format message
        if (is_array($message) || is_object($message)) {
            // For arrays and objects, use print_r with indentation for readability
            $formatted_data = print_r($message, true);
            // Add indentation for better readability in logs
            $formatted_data = preg_replace('/^/m', '    ', $formatted_data);
            $message = "Data:\n" . $formatted_data;
        }

        // Normalize log level
        $level = strtoupper($level);
        $allowed_levels = array('INFO', 'WARNING', 'ERROR', 'DEBUG');
        if (!in_array($level, $allowed_levels)) {
            $level = 'INFO';
        }

        // Format timestamp with level information
        $timestamp = $date->format('[Y-m-d H:i:s T]');
        $formatted_message = $timestamp . ' [' . $level . '] ' . $message . PHP_EOL;

        // Write to log file, using error_log function with mode 3 (append to file)
        if (!error_log($formatted_message, 3, $log_file)) {
            // If logging fails, try to log to WP debug.log as fallback
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('APW Woo Plugin: ' . $message);
            }
        }

        // Implement basic log rotation - check file size
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) { // 5MB limit
            $archive_file = $log_file . '.1';
            // Move current log to archive if it doesn't already exist
            if (!file_exists($archive_file)) {
                rename($log_file, $archive_file);
            }
        }
    }
}