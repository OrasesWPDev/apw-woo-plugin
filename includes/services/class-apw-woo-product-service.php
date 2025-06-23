<?php
/**
 * Product Service
 *
 * Consolidates product enhancement functionality including add-ons integration,
 * dynamic pricing, cross-sells display, product tabs, and shipping rules.
 *
 * @package APW_Woo_Plugin
 * @since 1.24.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Service Class
 *
 * Handles product add-ons, dynamic pricing, cross-sells, tabs customization,
 * and product-based shipping rules.
 */
class APW_Woo_Product_Service {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache for product add-ons data
     */
    private $addons_cache = [];
    
    /**
     * Cache for pricing rules data
     */
    private $pricing_cache = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->init_hooks();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Service initialized');
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Product add-ons integration
        if ($this->is_product_addons_active()) {
            add_action('woocommerce_product_add_to_cart', [$this, 'display_product_addons'], 15);
        }
        
        // Cross-sells display
        add_action('apw_woo_after_product_faqs', [$this, 'display_cross_sells'], 15);
        
        // Product tabs customization
        add_filter('woocommerce_product_tabs', [$this, 'customize_product_tabs'], 999);
        
        // Shipping rate filtering
        add_filter('woocommerce_package_rates', [$this, 'filter_shipping_rates_by_product'], 10, 2);
        
        // Dynamic pricing AJAX
        add_action('wp_ajax_apw_get_dynamic_price', [$this, 'ajax_get_dynamic_price']);
        add_action('wp_ajax_nopriv_apw_get_dynamic_price', [$this, 'ajax_get_dynamic_price']);
        
        // Cart item price filtering
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 10, 3);
    }
    
    /**
     * Check if WooCommerce Product Add-ons plugin is active
     */
    public function is_product_addons_active() {
        return class_exists('WC_Product_Addons');
    }
    
    /**
     * Check if WooCommerce Dynamic Pricing plugin is active
     */
    public function is_dynamic_pricing_active() {
        return class_exists('WC_Dynamic_Pricing');
    }
    
    /**
     * Get product add-ons for a specific product
     */
    public function get_product_addons($product) {
        $product_id = 0;
        
        if (is_numeric($product)) {
            $product_id = $product;
        } elseif (is_object($product) && is_a($product, 'WC_Product')) {
            $product_id = $product->get_id();
        }
        
        if (!$product_id) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Product Service: Invalid product passed to get_product_addons');
            }
            return array();
        }
        
        // Check cache first
        if (isset($this->addons_cache[$product_id])) {
            return $this->addons_cache[$product_id];
        }
        
        // Make sure Product Add-ons is active
        if (!function_exists('get_product_addons')) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Product Service: Product Add-ons function not available');
            }
            return array();
        }
        
        // Get the add-ons
        $addons = get_product_addons($product_id);
        
        // Cache the result
        $this->addons_cache[$product_id] = $addons;
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log(sprintf('Product Service: Found %d product add-on groups for product #%d', count($addons), $product_id));
        }
        
        return $addons;
    }
    
    /**
     * Check if a product has any add-ons
     */
    public function product_has_addons($product) {
        $addons = $this->get_product_addons($product);
        return !empty($addons);
    }
    
    /**
     * Display product add-ons on product page
     */
    public function display_product_addons() {
        global $product;
        
        if (!$product || !$this->product_has_addons($product)) {
            return;
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Service: Displaying product add-ons for product ID: ' . $product->get_id());
        }
        
        // Use WooCommerce Product Add-ons display functionality
        if (class_exists('WC_Product_Addons_Display')) {
            $display = new WC_Product_Addons_Display();
            $display->display();
        }
    }
    
    /**
     * Get dynamic pricing rules for a product
     */
    public function get_product_pricing_rules($product) {
        $product_id = 0;
        
        if (is_numeric($product)) {
            $product_id = $product;
        } elseif (is_object($product) && is_a($product, 'WC_Product')) {
            $product_id = $product->get_id();
        }
        
        if (!$product_id) {
            return array();
        }
        
        // Check cache first
        if (isset($this->pricing_cache[$product_id])) {
            return $this->pricing_cache[$product_id];
        }
        
        if (!$this->is_dynamic_pricing_active()) {
            return array();
        }
        
        // Get product-specific pricing rules from post meta
        $product_pricing_rules = get_post_meta($product_id, '_pricing_rules', true);
        $pricing_rules = array();
        
        if (!empty($product_pricing_rules) && is_array($product_pricing_rules)) {
            foreach ($product_pricing_rules as $rule_set) {
                if (isset($rule_set['rules']) && is_array($rule_set['rules'])) {
                    $pricing_rules[] = $rule_set;
                }
            }
        }
        
        // Cache the result
        $this->pricing_cache[$product_id] = $pricing_rules;
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log("Product Service: Found " . count($pricing_rules) . " pricing rule sets for product #{$product_id}");
        }
        
        return $pricing_rules;
    }
    
    /**
     * Check if product has pricing rules
     */
    public function product_has_pricing_rules($product) {
        $rules = $this->get_product_pricing_rules($product);
        return !empty($rules);
    }
    
    /**
     * Get price by quantity for dynamic pricing
     */
    public function get_price_by_quantity($product, $quantity) {
        if (!$this->is_dynamic_pricing_active()) {
            if (is_object($product)) {
                return $product->get_price();
            }
            $product_obj = wc_get_product($product);
            return $product_obj ? $product_obj->get_price() : 0;
        }
        
        $rules = $this->get_product_pricing_rules($product);
        
        if (empty($rules)) {
            if (is_object($product)) {
                return $product->get_price();
            }
            $product_obj = wc_get_product($product);
            return $product_obj ? $product_obj->get_price() : 0;
        }
        
        // Find applicable rule based on quantity
        $product_obj = is_object($product) ? $product : wc_get_product($product);
        $base_price = $product_obj ? $product_obj->get_price() : 0;
        
        foreach ($rules as $rule_set) {
            if (!isset($rule_set['rules'])) continue;
            
            foreach ($rule_set['rules'] as $rule) {
                if (isset($rule['from'], $rule['to']) && 
                    $quantity >= $rule['from'] && 
                    ($rule['to'] == '*' || $quantity <= $rule['to'])) {
                    
                    $discount_amount = isset($rule['amount']) ? floatval($rule['amount']) : 0;
                    $discount_type = isset($rule['type']) ? $rule['type'] : 'fixed_discount';
                    
                    if ($discount_type === 'percentage_discount') {
                        return $base_price * (1 - ($discount_amount / 100));
                    } else {
                        return max(0, $base_price - $discount_amount);
                    }
                }
            }
        }
        
        return $base_price;
    }
    
    /**
     * AJAX handler for dynamic pricing
     */
    public function ajax_get_dynamic_price() {
        check_ajax_referer('apw_dynamic_pricing_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }
        
        $price = $this->get_price_by_quantity($product_id, $quantity);
        $formatted_price = wc_price($price);
        
        wp_send_json_success(array(
            'price' => $price,
            'formatted_price' => $formatted_price,
            'quantity' => $quantity
        ));
    }
    
    /**
     * Display cross-sells on product page
     */
    public function display_cross_sells() {
        global $product;
        
        if (!is_a($product, 'WC_Product')) {
            $product = wc_get_product(get_queried_object_id());
            if (!is_a($product, 'WC_Product')) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Product Service: Could not retrieve valid product for cross-sells');
                }
                return;
            }
        }
        
        $product_id = $product->get_id();
        $cross_sell_ids = $product->get_cross_sell_ids();
        
        if (empty($cross_sell_ids) || !is_array($cross_sell_ids)) {
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Product Service: No cross-sell IDs found for product ID: ' . $product_id);
            }
            return;
        }
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Service: Found ' . count($cross_sell_ids) . ' cross-sell IDs for product: ' . $product_id);
        }
        
        ob_start();
        ?>
        <div class="apw-woo-cross-sells-section">
            <h2 class="apw-woo-cross-sells-title">
                <?php echo esc_html(apply_filters('apw_woo_cross_sells_title', __('You may be interested in...', 'apw-woo-plugin'))); ?>
            </h2>
            <div class="apw-woo-cross-sells-grid">
                <?php
                $cross_sells_displayed = 0;
                foreach ($cross_sell_ids as $cross_sell_id) :
                    $cs_product = wc_get_product($cross_sell_id);
                    
                    if (!$cs_product || !is_a($cs_product, 'WC_Product') || $cs_product->get_status() !== 'publish') {
                        continue;
                    }
                    
                    $cs_permalink = $cs_product->get_permalink();
                    $cs_image = $cs_product->get_image('woocommerce_thumbnail', array('class' => 'apw-woo-cross-sell-img'), true);
                    $cs_name = $cs_product->get_name();
                    ?>
                    <div class="apw-woo-cross-sell-item">
                        <a href="<?php echo esc_url($cs_permalink); ?>" class="apw-woo-cross-sell-link">
                            <div class="apw-woo-cross-sell-image-wrapper">
                                <?php echo $cs_image; ?>
                            </div>
                            <h3 class="apw-woo-cross-sell-name">
                                <?php echo esc_html($cs_name); ?>
                            </h3>
                        </a>
                    </div>
                    <?php
                    $cross_sells_displayed++;
                endforeach;
                ?>
            </div>
        </div>
        <?php
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Service: Displayed ' . $cross_sells_displayed . ' cross-sell products');
        }
        
        echo ob_get_clean();
    }
    
    /**
     * Customize WooCommerce product tabs
     */
    public function customize_product_tabs($tabs) {
        // Remove default tabs
        unset($tabs['description']);
        unset($tabs['additional_information']);
        unset($tabs['reviews']);
        
        // Add custom short description tab
        $tabs['short_description'] = array(
            'title' => __('Overview', 'apw-woo-plugin'),
            'priority' => 5,
            'callback' => [$this, 'short_description_tab_content']
        );
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Product Service: Customized product tabs');
        }
        
        return $tabs;
    }
    
    /**
     * Display short description tab content
     */
    public function short_description_tab_content() {
        global $product;
        
        if ($product && $product->get_short_description()) {
            echo '<div class="apw-woo-short-description-tab">';
            echo wp_kses_post($product->get_short_description());
            echo '</div>';
        }
    }
    
    /**
     * Filter shipping rates based on product quantity
     */
    public function filter_shipping_rates_by_product($rates, $package) {
        if (empty($rates) || !isset($package['contents'])) {
            return $rates;
        }
        
        // Check for specific products that qualify for free shipping
        $free_shipping_products = apply_filters('apw_free_shipping_products', array());
        $free_shipping_min_qty = apply_filters('apw_free_shipping_min_quantity', 1);
        
        $qualifies_for_free_shipping = false;
        
        foreach ($package['contents'] as $cart_item) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            if (in_array($product_id, $free_shipping_products) && $quantity >= $free_shipping_min_qty) {
                $qualifies_for_free_shipping = true;
                break;
            }
        }
        
        if ($qualifies_for_free_shipping) {
            // Remove non-free shipping methods
            foreach ($rates as $rate_id => $rate) {
                if (strpos($rate->method_id, 'free_shipping') === false) {
                    unset($rates[$rate_id]);
                }
            }
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Product Service: Applied free shipping based on product quantity rules');
            }
        }
        
        return $rates;
    }
    
    /**
     * Filter cart item price display
     */
    public function filter_cart_item_price($price_html, $cart_item, $cart_item_key) {
        if (!$this->is_dynamic_pricing_active()) {
            return $price_html;
        }
        
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        
        if ($this->product_has_pricing_rules($product_id)) {
            $unit_price = $this->get_price_by_quantity($product_id, $quantity);
            $price_html = wc_price($unit_price);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log("Product Service: Applied dynamic pricing for product {$product_id}, quantity {$quantity}");
            }
        }
        
        return $price_html;
    }
}

/**
 * Initialize Product Service
 * 
 * @return void
 * @since 1.24.1
 */
function apw_woo_initialize_product_service()
{
    if (class_exists('APW_Woo_Product_Service')) {
        APW_Woo_Product_Service::get_instance();
        
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('PHASE 2: Product Service initialized (add-ons, dynamic pricing, cross-sells, tabs, shipping)');
        }
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('APW_Woo_Product_Service class not found', 'warning');
        }
    }
}