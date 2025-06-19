<?php
/**
 * Main Plugin Class
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class APW_Woo_Plugin {
    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Auto-updater instance
     *
     * @var APW_Woo_Simple_Updater
     */
    private $updater;

    /**
     * Constructor
     */
    private function __construct() {
        apw_woo_log('Main plugin class constructed');
    }

    /**
     * Get instance
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        apw_woo_log('Initializing main plugin class');
        // Initialize template loader
        $template_loader = APW_Woo_Template_Loader::get_instance();

        // Initialize auto-updater in admin context only
        if (is_admin()) {
            $this->init_updater();
        }

        // Only add test notice when debug mode is enabled
        if (APW_WOO_DEBUG_MODE) {
            add_action('admin_notices', [$this, 'display_test_notice']);
        }
    }

    /**
     * Initialize the auto-updater
     */
    private function init_updater() {
        // Double-check admin context for security
        if (!is_admin()) {
            return;
        }

        // Initialize updater with GitHub repository using Plugin Update Checker library
        $this->updater = new APW_Woo_Simple_Updater(
            APW_WOO_PLUGIN_FILE,
            'https://github.com/OrasesWPDev/apw-woo-plugin/'
        );

        apw_woo_log('Auto-updater initialized in main plugin class');
    }

    /**
     * Get the updater instance (for testing)
     *
     * @return APW_Woo_Simple_Updater|null
     */
    public function get_updater() {
        return $this->updater;
    }

    /**
     * Display a test notice in admin to confirm plugin is working
     */
    public function display_test_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('APW WooCommerce Plugin is active and working! (This is a test notice for Sprint 1)', 'apw-woo-plugin'); ?></p>
        </div>
        <?php
    }
}