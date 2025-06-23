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
