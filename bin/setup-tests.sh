#!/bin/bash

echo "🚀 Setting up APW WooCommerce Plugin testing environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if composer.json exists
if [ ! -f "composer.json" ]; then
    echo -e "${RED}❌ composer.json not found!${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Installing Composer dependencies...${NC}"
composer install --prefer-dist --no-progress

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Composer install failed!${NC}"
    exit 1
fi

# Install WordPress test suite (only if not already installed)
if [ ! -d "/tmp/wordpress-tests-lib" ]; then
    echo -e "${YELLOW}📝 Installing WordPress test suite...${NC}"
    bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
    
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ WordPress test suite installation failed!${NC}"
        echo "You may need to create the database manually:"
        echo "mysql -u root -e \"CREATE DATABASE wordpress_test;\""
        exit 1
    fi
else
    echo -e "${GREEN}✅ WordPress test suite already installed${NC}"
fi

# Create test database if it doesn't exist
echo -e "${YELLOW}🗄️ Setting up test database...${NC}"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS wordpress_test;" 2>/dev/null || echo -e "${YELLOW}⚠️ Database creation skipped (may already exist or need manual setup)${NC}"

# Set up test directories (already created by mkdir command earlier)
echo -e "${YELLOW}📁 Verifying test directory structure...${NC}"
mkdir -p tests/{phase1,phase2,phase3,integration,utilities,fixtures,stubs}

# Create basic test bootstrap if it doesn't exist
if [ ! -f "tests/bootstrap.php" ]; then
    echo -e "${YELLOW}🔧 Creating test bootstrap...${NC}"
    cat > tests/bootstrap.php << 'EOF'
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

// Load WordPress test suite
require $wp_tests_dir . '/includes/bootstrap.php';
EOF
fi

# Create WordPress stubs for PHPStan
if [ ! -f "tests/stubs/wordpress-stubs.php" ]; then
    echo -e "${YELLOW}🔧 Creating WordPress stubs for static analysis...${NC}"
    cat > tests/stubs/wordpress-stubs.php << 'EOF'
<?php
/**
 * WordPress function stubs for PHPStan
 */

function wp_verify_nonce($nonce, $action) { return true; }
function sanitize_text_field($str) { return $str; }
function esc_html($text) { return $text; }
function esc_attr($text) { return $text; }
function esc_url($url) { return $url; }
function wp_die($message) { exit($message); }
function current_user_can($capability) { return true; }
function get_current_user_id() { return 1; }
function is_admin() { return false; }
function wp_create_user($username, $password, $email) { return 1; }
function update_user_meta($user_id, $meta_key, $meta_value) { return true; }
function get_user_meta($user_id, $key, $single = false) { return ''; }
function wp_cache_get($key, $group = '') { return false; }
function wp_cache_set($key, $data, $group = '', $expire = 0) { return true; }
function apply_filters($tag, $value) { return $value; }
function do_action($tag) { return null; }
function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) { return true; }
function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) { return true; }
EOF
fi

# Create WooCommerce stubs for PHPStan
if [ ! -f "tests/stubs/woocommerce-stubs.php" ]; then
    echo -e "${YELLOW}🔧 Creating WooCommerce stubs for static analysis...${NC}"
    cat > tests/stubs/woocommerce-stubs.php << 'EOF'
<?php
/**
 * WooCommerce function stubs for PHPStan
 */

function WC() { return new stdClass(); }
function wc_get_product($product_id) { return new WC_Product(); }
function wc_get_cart_url() { return 'http://example.com/cart'; }
function wc_price($price) { return '$' . number_format($price, 2); }
function is_checkout() { return false; }
function is_product() { return false; }

class WC_Product {
    public function get_id() { return 1; }
    public function get_name() { return 'Product'; }
    public function get_price() { return 10.00; }
    public function exists() { return true; }
}

class WC_Customer {
    public function __construct($customer_id = 0) {}
    public function get_total_spent() { return 100.00; }
    public function get_order_count() { return 5; }
}

class WC_Cart {
    public function get_cart_contents_count() { return 1; }
    public function get_subtotal() { return 100.00; }
    public function get_shipping_total() { return 10.00; }
    public function get_fees() { return []; }
    public function add_fee($name, $amount, $taxable = false) { return true; }
    public function calculate_totals() { return true; }
    public function empty_cart() { return true; }
}
EOF
fi

echo -e "${GREEN}✅ Test environment setup complete!${NC}"
echo ""
echo -e "${GREEN}🎯 Available testing commands:${NC}"
echo "  composer run test:phase1     - Test Phase 1 (Critical Payment Processing)"
echo "  composer run test:phase2     - Test Phase 2 (Service Consolidation)"
echo "  composer run test:phase3     - Test Phase 3 (Code Optimization)"
echo "  composer run test:payment    - Test payment processing specifically"
echo "  composer run test:customer   - Test customer functionality"
echo "  composer run test:all        - Run all tests with quality checks"
echo "  composer run lint            - Code style checking"
echo "  composer run analyze         - Static analysis with PHPStan"
echo "  composer run quality         - Run all quality checks"
echo ""
echo -e "${YELLOW}📋 Next steps:${NC}"
echo "1. Run 'composer run test' to verify the testing environment"
echo "2. Begin Phase 1 development with payment processing fixes"
echo "3. Use 'composer run test:phase1' to validate Phase 1 completion"
echo ""
echo -e "${GREEN}🎉 Ready to begin iterative refactor development!${NC}"