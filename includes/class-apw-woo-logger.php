<?php
/**
 * Logger functionality for APW WooCommerce Plugin
 *
 * @package APW_Woo_Plugin
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Logger Class
 *
 * Handles all logging functionality for the plugin with enhanced
 * security and performance features. Logs are strictly contained within the plugin's /logs directory.
 *
 * @since 1.0.0
 */
class APW_Woo_Logger
{

    /**
     * Flag to track if logs directory is set up
     *
     * @var boolean
     */
    private static $logs_setup = false;

    /**
     * Create log directory and security files if they don't exist
     *
     * Sets up the logs directory with proper security measures.
     * Errors during setup are no longer logged to prevent hitting standard debug.log.
     *
     * @return bool True if setup is complete (or already done), false on failure.
     * @since 1.0.0
     */
    public static function setup_logs()
    {
        if (!APW_WOO_DEBUG_MODE) {
            return true; // Not in debug mode, setup is irrelevant but not failed.
        }

        if (self::$logs_setup) {
            return true;
        }

        $log_dir = APW_WOO_PLUGIN_DIR . 'logs';

        // Only proceed if directory doesn't exist or isn't writable initially
        if (!is_dir($log_dir) || !is_writable($log_dir)) {
            // Ensure parent directory is writable before attempting to create
            if (!is_writable(APW_WOO_PLUGIN_DIR)) {
                // Cannot create logs directory if plugin directory itself isn't writable.
                // Fail silently as per requirement.
                return false;
            }

            // Create logs directory if it doesn't exist
            if (!is_dir($log_dir)) {
                if (!wp_mkdir_p($log_dir)) {
                    // Log creation failure silently.
                    return false;
                }
            }

            // Check writability again after creation attempt
            if (!is_writable($log_dir)) {
                // Directory exists but still not writable.
                return false;
            }


            // --- Security file creation attempts (Fail silently on error) ---

            // Create .htaccess file with strict access restrictions
            $htaccess_content = "# Deny access to all files in this directory\n";
            $htaccess_content .= "# Enhanced security for sensitive log files\n\n";
            $htaccess_content .= "<Files \"*\">\n";
            $htaccess_content .= "    # Apache 2.4+\n";
            $htaccess_content .= "    <IfModule mod_authz_core.c>\n";
            $htaccess_content .= "        Require all denied\n";
            $htaccess_content .= "    </IfModule>\n";
            $htaccess_content .= "    # Apache 2.2\n";
            $htaccess_content .= "    <IfModule !mod_authz_core.c>\n";
            $htaccess_content .= "        Order deny,allow\n";
            $htaccess_content .= "        Deny from all\n";
            $htaccess_content .= "    </IfModule>\n";
            $htaccess_content .= "</Files>\n\n";
            $htaccess_content .= "# Disable script execution\n";
            $htaccess_content .= "<IfModule mod_php.c>\n";
            $htaccess_content .= "    php_flag engine off\n";
            $htaccess_content .= "</IfModule>\n\n";
            $htaccess_content .= "# Prevent directory listing\n";
            $htaccess_content .= "Options -Indexes\n";
            @file_put_contents($log_dir . '/.htaccess', $htaccess_content); // Use @ to suppress PHP warnings on failure

            // Create index.php to prevent directory listing (fallback security)
            @file_put_contents($log_dir . '/index.php', '<?php // Silence is golden.'); // Use @

            // Add a readme file to explain purpose of directory
            $readme_content = "This directory contains debug logs for the APW WooCommerce Plugin.\n";
            $readme_content .= "These files contain debugging information and should be kept secure.\n";
            $readme_content .= "In production environments, disable debug mode in the plugin settings.\n";
            @file_put_contents($log_dir . '/README.txt', $readme_content); // Use @
        }

        // If we reached here, the directory should exist and be writable (or setup wasn't needed)
        self::$logs_setup = true;
        return true;
    }

    /**
     * Log messages when debug mode is enabled
     *
     * Creates detailed log entries with timestamps, handles various data types,
     * manages log rotation, and writes *only* to the plugin's logs directory.
     *
     * @param mixed $message The message or data to log
     * @param string $level Optional. Log level (info, warning, error, debug). Default 'info'.
     * @return void
     * @since 1.0.0
     */
    public static function log($message, $level = 'info')
    {
        // Only log if debug mode is enabled
        if (!APW_WOO_DEBUG_MODE) {
            return;
        }

        // Ensure logs directory is set up and writable first
        if (!self::setup_logs()) {
            // Setup failed (e.g., directory not writable), cannot log. Fail silently.
            return;
        }

        // Set timezone to EST (New York) - Consider using WordPress timezone setting?
        // $timezone = new DateTimeZone('America/New_York');
        // $date = new DateTime('now', $timezone);
        // Using WP time for consistency
        $timestamp_gmt = time();
        $date_format_file = gmdate('Y-m-d', $timestamp_gmt);
        $date_format_log = date('Y-m-d H:i:s T', $timestamp_gmt + (get_option('gmt_offset') * HOUR_IN_SECONDS));


        // Format the date for file name and timestamp
        $log_file = APW_WOO_PLUGIN_DIR . 'logs/debug-' . $date_format_file . '.log';

        // Format message
        if (is_array($message) || is_object($message)) {
            // For arrays and objects, use print_r with indentation for readability
            $formatted_data = print_r($message, true);
            // Add indentation for better readability in logs
            $formatted_data = preg_replace('/^/m', '    ', $formatted_data);
            $message = "Data:\n" . $formatted_data;
        } elseif (is_bool($message)) {
            $message = $message ? 'true' : 'false';
        } elseif (is_null($message)) {
            $message = 'NULL';
        }


        // Normalize log level
        $level = strtoupper($level);
        $allowed_levels = array('INFO', 'WARNING', 'ERROR', 'DEBUG');
        if (!in_array($level, $allowed_levels)) {
            $level = 'INFO';
        }

        // Format timestamp with level information
        $formatted_message = '[' . $date_format_log . '] [' . $level . '] ' . (string)$message . PHP_EOL; // Cast message to string

        // --- Write to log file ---
        // Use file_put_contents with LOCK_EX for slightly better concurrency handling
        // and FILE_APPEND to add to the file. Use @ to suppress PHP warnings on failure.
        $write_success = @file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);

        // --- REMOVED FALLBACK LOGIC ---
        // if (!$write_success) {
        //     // Fallback removed as per requirement. If logging fails, it fails silently.
        // }


        // --- Basic log rotation ---
        // Check rotation only if the write was likely successful or file exists
        // Use clearstatcache to ensure filesize is fresh
        clearstatcache(true, $log_file);
        if ($write_success !== false && file_exists($log_file) && filesize($log_file) > (5 * 1024 * 1024)) { // 5MB limit
            $archive_file = $log_file . '.1';
            // Rotate: Rename current log to .1 (overwrite old .1 if it exists)
            @rename($log_file, $archive_file); // Use @ to suppress PHP warnings
        }
    }

    /**
     * Log a performance metric with timing information
     *
     * @param string $operation The operation being timed
     * @param float $start_time The start time from microtime(true)
     * @param array $context Optional. Additional context information
     * @return void
     * @since 1.0.0
     */
    public static function log_performance($operation, $start_time, $context = array())
    {
        // Skip if debug mode is disabled
        if (!APW_WOO_DEBUG_MODE) {
            return;
        }

        // Skip if performance tracking is disabled via filter
        if (!apply_filters('apw_woo_enable_performance_tracking', true)) { // Default to true if debug is on
            return;
        }

        // Calculate execution time
        $execution_time = microtime(true) - $start_time;
        $execution_ms = round($execution_time * 1000, 2); // Convert to milliseconds

        // Build performance message
        $message = sprintf(
            "Performance: %s completed in %sms",
            $operation,
            $execution_ms
        );

        // Add context if provided
        if (!empty($context)) {
            // Sanitize context for logging if needed, json_encode is usually safe enough for logs
            $context_str = json_encode($context);
            $message .= " | Context: " . $context_str;
        }

        // Log with special performance level
        self::log($message, 'DEBUG');
    }
}