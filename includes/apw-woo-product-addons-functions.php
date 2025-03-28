/**
* Register Product Add-ons hooks for visualization
*/
public function register_visualization_hooks() {
// Add these hooks to the visualization system
$addon_hooks = array(
'woocommerce_product_addons_start',
'woocommerce_product_addons_option',
'woocommerce_product_addons_end',
'woocommerce_product_addons_option_price',
'apw_woo_before_product_addons',
'apw_woo_after_product_addons'
);

// Loop through hooks and add the visualizer
global $hooks_to_visualize;
if (is_array($hooks_to_visualize)) {
$hooks_to_visualize = array_merge($hooks_to_visualize, $addon_hooks);
}

// Register our own visualization directly
foreach ($addon_hooks as $hook) {
add_action($hook, apw_woo_hook_visualizer($hook), 999);
}

if (APW_WOO_DEBUG_MODE) {
apw_woo_log('Registered Product Add-ons hooks for visualization');
}
}

/**
* Remove Product Add-ons from default location
*/
public function remove_default_addons_location() {
// Check if Product Add-ons plugin is active
if (!class_exists('WC_Product_Addons')) {
return;
}

// Remove from default location
remove_action('woocommerce_before_add_to_cart_button', array('WC_Product_Addons_Display', 'display'), 10);

if (APW_WOO_DEBUG_MODE) {
apw_woo_log('Removed Product Add-ons from default location');
}
}

/**
* Display product add-ons between product meta and sharing
*/
public function display_product_addons() {
// Check if Product Add-ons plugin is active
if (!class_exists('WC_Product_Addons')) {
if (APW_WOO_DEBUG_MODE) {
apw_woo_log('Product Add-ons plugin not active');
}
return;
}

global $product;

// Verify we have a valid product
if (!is_a($product, 'WC_Product')) {
if (APW_WOO_DEBUG_MODE) {
apw_woo_log('Invalid product object when displaying product add-ons');
}
return;
}

// Custom hook before add-ons (for visualization)
do_action('apw_woo_before_product_addons', $product);

echo '<div class="apw-woo-product-addons">';
    echo '<h3 class="apw-woo-product-addons-title">' . esc_html__('Product Options', 'apw-woo-plugin') . '</h3>';

    // Instead of just calling the action, call the actual method
    // This gives us more control over visualization
    if (class_exists('WC_Product_Addons_Display') && method_exists('WC_Product_Addons_Display', 'display')) {
    WC_Product_Addons_Display::display();
    }

    echo '</div>'; // .apw-woo-product-addons

// Custom hook after add-ons (for visualization)
do_action('apw_woo_after_product_addons', $product);
}