<?php
/**
 * PHPUnit bootstrap file for APW WooCommerce Plugin
 */

// Composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Load WordPress test functions
$wp_tests_dir = getenv('WP_TESTS_DIR');
if (!$wp_tests_dir) {
    $wp_tests_dir = '/tmp/wordpress-tests-lib';
}

if (file_exists($wp_tests_dir . '/includes/functions.php')) {
    require_once $wp_tests_dir . '/includes/functions.php';
} else {
    echo "WordPress test suite not found. Please run bin/install-wp-tests.sh\n";
    exit(1);
}

// Manually load our plugin
function _manually_load_apw_plugin() {
    require dirname(__DIR__) . '/apw-woo-plugin.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_apw_plugin');

// Mock WooCommerce for testing
function _setup_mock_woocommerce() {
    // Create basic WooCommerce mock classes if WooCommerce isn't available
    if (!class_exists('WooCommerce')) {
        require_once dirname(__DIR__) . '/tests/utilities/woocommerce-mocks.php';
    }
}
tests_add_filter('plugins_loaded', '_setup_mock_woocommerce', 5);

// Load WordPress test suite
require $wp_tests_dir . '/includes/bootstrap.php';
