/**
* Register Product Add-ons hooks for visualization
*/
public function register_visualization_hooks() {
// Define the hooks we want to visualize
$addon_hooks = array(
'apw_woo_before_product_addons',
'apw_woo_after_product_addons',
'woocommerce_product_addons_start',
'woocommerce_product_addons_end',
'woocommerce_product_addons_option',
'woocommerce_product_addons_option_price'
);

// Check if we have access to the hook visualizer function
if (function_exists('apw_woo_hook_visualizer')) {
// Register each hook with the visualizer
foreach ($addon_hooks as $hook) {
add_action($hook, apw_woo_hook_visualizer($hook), 999);
}

if (APW_WOO_DEBUG_MODE) {
apw_woo_log('Product Add-ons hooks registered for visualization');
}
} else {
if (APW_WOO_DEBUG_MODE) {
apw_woo_log('Hook visualizer function not found - visualization skipped');
}
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

// Get product add-ons
$product_addons = array();
if (function_exists('get_product_addons')) {
$product_addons = get_product_addons($product->get_id());
}

// Log add-ons information for debugging
if (APW_WOO_DEBUG_MODE) {
apw_woo_log('Processing add-ons for product ID: ' . $product->get_id());
}

// Custom hook before add-ons (for visualization)
do_action('apw_woo_before_product_addons', $product);

echo '<div class="apw-woo-product-addons">';
    echo '<h3 class="apw-woo-product-addons-title">' . esc_html__('Product Options', 'apw-woo-plugin') . '</h3>';

    // Custom action to mark the start of add-ons
    do_action('woocommerce_product_addons_start', $product);

    // Display the add-ons
    if (class_exists('WC_Product_Addons_Display') && method_exists('WC_Product_Addons_Display', 'display')) {
    WC_Product_Addons_Display::display();
    }

    // Custom action to mark the end of add-ons
    do_action('woocommerce_product_addons_end', $product);

    echo '</div>'; // .apw-woo-product-addons

// Custom hook after add-ons (for visualization)
do_action('apw_woo_after_product_addons', $product);
}