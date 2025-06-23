<?php
/**
 * WooCommerce Mock Classes for Testing
 * Provides basic WooCommerce functionality for testing payment surcharge logic
 */

if (!class_exists('WooCommerce')) {
    class WooCommerce {
        public $cart;
        public $customer;
        public $session;
        
        private static $instance = null;
        
        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            $this->cart = new WC_Cart();
            $this->customer = new WC_Customer();
            $this->session = new WC_Session_Handler();
        }
        
        public function init() {
            // Initialize WC components
        }
    }
    
    // Global WC() function
    function WC() {
        return WooCommerce::instance();
    }
}

if (!class_exists('WC_Cart')) {
    class WC_Cart {
        private $cart_contents = [];
        private $fees = [];
        private $totals = [
            'subtotal' => 0,
            'shipping_total' => 0,
            'total' => 0
        ];
        
        public function add_to_cart($product_id, $quantity = 1) {
            $this->cart_contents[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'line_total' => $quantity * 109.00 // Mock price for Product #80
            ];
            
            $this->calculate_totals();
            return true;
        }
        
        public function empty_cart() {
            $this->cart_contents = [];
            $this->fees = [];
            $this->totals = ['subtotal' => 0, 'shipping_total' => 0, 'total' => 0];
        }
        
        public function add_fee($name, $amount, $taxable = false) {
            $fee = new stdClass();
            $fee->name = $name;
            $fee->amount = $amount;
            $fee->taxable = $taxable;
            
            $this->fees[] = $fee;
            $this->calculate_totals();
        }
        
        public function get_fees() {
            return $this->fees;
        }
        
        public function get_subtotal() {
            return $this->totals['subtotal'];
        }
        
        public function get_shipping_total() {
            return $this->totals['shipping_total'];
        }
        
        public function get_totals() {
            return $this->totals;
        }
        
        public function calculate_totals() {
            // Calculate subtotal from cart contents
            $subtotal = 0;
            foreach ($this->cart_contents as $item) {
                $subtotal += $item['line_total'];
            }
            $this->totals['subtotal'] = $subtotal;
            
            // Calculate shipping and other fees
            $shipping = 0;
            $other_fees = 0;
            
            foreach ($this->fees as $fee) {
                if (strpos($fee->name, 'Shipping') !== false) {
                    $shipping += $fee->amount;
                } else {
                    $other_fees += $fee->amount;
                }
            }
            
            $this->totals['shipping_total'] = $shipping;
            $this->totals['total'] = $subtotal + $shipping + $other_fees;
            
            // Trigger WooCommerce hooks for surcharge calculation
            do_action('woocommerce_cart_calculate_fees');
        }
    }
}

if (!class_exists('WC_Customer')) {
    class WC_Customer {
        private $shipping_address = [];
        
        public function set_shipping_address($address) {
            $this->shipping_address['address_1'] = $address;
        }
        
        public function set_shipping_city($city) {
            $this->shipping_address['city'] = $city;
        }
        
        public function set_shipping_state($state) {
            $this->shipping_address['state'] = $state;
        }
        
        public function set_shipping_postcode($postcode) {
            $this->shipping_address['postcode'] = $postcode;
        }
        
        public function set_shipping_country($country) {
            $this->shipping_address['country'] = $country;
        }
        
        public function get_shipping_address() {
            return $this->shipping_address;
        }
    }
}

if (!class_exists('WC_Session_Handler')) {
    class WC_Session_Handler {
        private $session_data = [];
        
        public function init() {
            // Initialize session
        }
        
        public function set($key, $value) {
            $this->session_data[$key] = $value;
        }
        
        public function get($key, $default = null) {
            return isset($this->session_data[$key]) ? $this->session_data[$key] : $default;
        }
    }
}

if (!class_exists('WC_Product_Simple')) {
    class WC_Product_Simple {
        private $id;
        private $name;
        private $price;
        private $stock_quantity;
        private $manage_stock = false;
        
        public function set_name($name) {
            $this->name = $name;
        }
        
        public function set_regular_price($price) {
            $this->price = $price;
        }
        
        public function set_manage_stock($manage) {
            $this->manage_stock = $manage;
        }
        
        public function set_stock_quantity($quantity) {
            $this->stock_quantity = $quantity;
        }
        
        public function save() {
            // Mock save - assign random ID
            $this->id = rand(1000, 9999);
            return $this->id;
        }
        
        public function get_id() {
            return $this->id;
        }
    }
}

// Mock WordPress functions if not available
if (!function_exists('is_checkout')) {
    function is_checkout() {
        return true; // Always return true for testing
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false; // Return false for frontend testing
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('wp_create_user')) {
    function wp_create_user($username, $password, $email) {
        return rand(1, 1000); // Return random user ID
    }
}

if (!function_exists('wp_delete_user')) {
    function wp_delete_user($id) {
        return true;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($id, $force_delete = false) {
        return true;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value) {
        return true;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('number_format')) {
    // number_format should exist, but just in case
}
?>