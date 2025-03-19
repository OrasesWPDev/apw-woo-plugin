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

        // Add test notice to admin to show plugin is working
        add_action('admin_notices', [$this, 'display_test_notice']);
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