<?php
// DEBUGGING: Add this at the very top of single-product.php, before any other code
error_log('DIRECT TEST: single-product.php is being accessed directly');
apw_woo_log('DIRECT TEST: single-product.php is being accessed directly');
// Regular template code follows...
/**
 * Template for displaying single product pages
 *
 * @package APW_Woo_Plugin
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
apw_woo_log('Loading single product template');
// Helper function to create visualizer callbacks
function apw_woo_hook_visualizer($hook_name) {
    return function() use ($hook_name) {
        $args = func_get_args();
        apw_woo_visualize_hook($hook_name, $args);
    };
}
// Helper function to visualize hook data
function apw_woo_visualize_hook($hook_name, $params = array()) {
    echo '<div style="margin: 10px 0; padding: 10px; border: 2px dashed #ff6b6b; background-color: #fff; color: #333; font-family: monospace;">';
    echo '<h4 style="margin: 0 0 5px 0; color: #ff6b6b;">HOOK: ' . esc_html($hook_name) . '</h4>';
    if (!empty($params)) {
        echo '<p>Available Parameters:</p>';
        echo '<ul style="margin: 0; padding-left: 20px;">';
        foreach ($params as $key => $value) {
            echo '<li>';
            if (is_object($value)) {
                echo 'Object: ' . esc_html(get_class($value));
                // Show basic info for WC_Product objects
                if ($value instanceof WC_Product) {
                    echo ' (ID: ' . esc_html($value->get_id()) . ', Name: ' . esc_html($value->get_name()) . ')';
                }
            } elseif (is_array($value)) {
                echo 'Array: ' . count($value) . ' items';
            } else {
                echo gettype($value) . ': ' . esc_html(var_export($value, true));
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No parameters available for this hook.</p>';
    }
    echo '</div>';
}
// Register visualizers for all common WooCommerce single product hooks
$hooks_to_visualize = array(
    'woocommerce_before_single_product',
    'woocommerce_before_single_product_summary',
    'woocommerce_product_thumbnails',
    'woocommerce_single_product_summary',
    'woocommerce_before_add_to_cart_form',
    'woocommerce_before_variations_form',
    'woocommerce_before_add_to_cart_button',
    'woocommerce_before_single_variation',
    'woocommerce_single_variation',
    'woocommerce_after_single_variation',
    'woocommerce_after_add_to_cart_button',
    'woocommerce_after_variations_form',
    'woocommerce_after_add_to_cart_form',
    'woocommerce_product_meta_start',
    'woocommerce_product_meta_end',
    'woocommerce_share',
    'woocommerce_after_single_product_summary',
    'woocommerce_after_single_product',
    // Custom hooks for our plugin
    'apw_woo_before_product_faqs',
    'apw_woo_after_product_faqs'
);
// Add visualizers to all hooks
foreach ($hooks_to_visualize as $hook) {
    add_action($hook, apw_woo_hook_visualizer($hook), 999);
}

// Remove Product Add-Ons from default location and add to our location
add_action('init', 'apw_woo_move_product_addons');

function apw_woo_move_product_addons() {
    // Remove from default location
    if (class_exists('WC_Product_Addons_Display')) {
        remove_action('woocommerce_before_add_to_cart_button', array('WC_Product_Addons_Display', 'display'), 10);
    }
    
    // Add to our desired location
    add_action('woocommerce_product_meta_end', 'apw_woo_display_product_addons', 15);
}

// Add our custom action to display Product Add-Ons
function apw_woo_display_product_addons() {
    global $product;
    
    if (!is_a($product, 'WC_Product')) {
        return;
    }
    
    // Check if Product Add-Ons are available for this product
    if (function_exists('get_product_addons')) {
        $product_addons = get_product_addons($product->get_id());
        
        if (!empty($product_addons)) {
            echo '<div class="apw-woo-product-addons">';
            echo '<h3 class="apw-woo-product-addons-title">' . esc_html__('Product Options', 'apw-woo-plugin') . '</h3>';
            
            // Display the actual add-ons form
            if (class_exists('WC_Product_Addons_Display') && method_exists('WC_Product_Addons_Display', 'display')) {
                // This will display the actual form fields customers interact with
                WC_Product_Addons_Display::display();
            } else {
                // If the display class isn't available, try to render the fields manually
                foreach ($product_addons as $addon) {
                    echo '<div class="product-addon product-addon-' . esc_attr($addon['field_name']) . '">';
                    echo '<h3 class="addon-name">' . esc_html($addon['name']) . '</h3>';
                    
                    if (!empty($addon['description'])) {
                        echo '<div class="addon-description">' . wp_kses_post($addon['description']) . '</div>';
                    }
                    
                    echo '<div class="addon-options">';
                    
                    // Display different field types
                    switch ($addon['type']) {
                        case 'checkbox':
                            foreach ($addon['options'] as $option) {
                                echo '<label>';
                                echo '<input type="checkbox" name="addon-' . esc_attr($addon['field_name']) . '[]" value="' . esc_attr($option['label']) . '" /> ';
                                echo esc_html($option['label']);
                                if (!empty($option['price'])) {
                                    echo ' (' . wc_price($option['price']) . ')';
                                }
                                echo '</label><br>';
                            }
                            break;
                            
                        case 'select':
                            echo '<select name="addon-' . esc_attr($addon['field_name']) . '">';
                            echo '<option value="">' . esc_html__('Choose an option...', 'apw-woo-plugin') . '</option>';
                            foreach ($addon['options'] as $option) {
                                echo '<option value="' . esc_attr($option['label']) . '">';
                                echo esc_html($option['label']);
                                if (!empty($option['price'])) {
                                    echo ' (' . wc_price($option['price']) . ')';
                                }
                                echo '</option>';
                            }
                            echo '</select>';
                            break;
                            
                        case 'radio':
                            foreach ($addon['options'] as $option) {
                                echo '<label>';
                                echo '<input type="radio" name="addon-' . esc_attr($addon['field_name']) . '" value="' . esc_attr($option['label']) . '" /> ';
                                echo esc_html($option['label']);
                                if (!empty($option['price'])) {
                                    echo ' (' . wc_price($option['price']) . ')';
                                }
                                echo '</label><br>';
                            }
                            break;
                            
                        case 'custom_text':
                        case 'custom_textarea':
                            $field_type = ($addon['type'] === 'custom_textarea') ? 'textarea' : 'text';
                            $price_display = !empty($addon['price']) ? ' (' . wc_price($addon['price']) . ')' : '';
                            
                            if ($field_type === 'textarea') {
                                echo '<textarea name="addon-' . esc_attr($addon['field_name']) . '" placeholder="' . esc_attr($addon['placeholder']) . '"></textarea>';
                            } else {
                                echo '<input type="text" name="addon-' . esc_attr($addon['field_name']) . '" placeholder="' . esc_attr($addon['placeholder']) . '" />';
                            }
                            
                            if (!empty($price_display)) {
                                echo '<span class="addon-price">' . $price_display . '</span>';
                            }
                            break;
                    }
                    
                    echo '</div>'; // .addon-options
                    echo '</div>'; // .product-addon
                }
            }
            
            // Also display the raw data for debugging if needed
            if (APW_WOO_DEBUG_MODE) {
                echo '<div class="apw-woo-debug-section">';
                echo '<h4>Debug: Raw Add-Ons Data</h4>';
                echo '<pre class="apw-woo-debug-addons">';
                echo esc_html(print_r($product_addons, true));
                echo '</pre>';
                echo '</div>';
            }
            
            echo '</div>'; // .apw-woo-product-addons
        }
    }
}

get_header();
// Get current product
global $product;
if (!is_a($product, 'WC_Product')) {
    $product = wc_get_product(get_the_ID());
}
if ($product) :
    ?>
    <main id="main" class="site-main apw-woo-single-product-main" role="main">
        <!-- APW-WOO-TEMPLATE: single-product.php is loaded -->
        <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
        <div class="apw-woo-section-wrapper apw-woo-header-block">
            <?php
            /**
             * Hook: apw_woo_before_product_header
             */
            do_action('apw_woo_before_product_header', $product);
            apw_woo_log('Rendering product page header');
            if (shortcode_exists('block')) {
                echo do_shortcode('[block id="fourth-level-page-header"]');
            } else {
                // Fallback if shortcode doesn't exist
                apw_woo_log('Block shortcode not available, using fallback header');
                echo '<h1 class="apw-woo-page-title">' . esc_html($product->get_name()) . '</h1>';
            }
            /**
             * Hook: apw_woo_after_product_header
             */
            do_action('apw_woo_after_product_header', $product);
            ?>
        </div>
        <!-- Use Flatsome's container while keeping our plugin-specific classes -->
        <div class="container">
            <div class="row">
                <div class="col apw-woo-content-wrapper">
                    <?php do_action('woocommerce_before_single_product'); ?>
                    <div id="product-<?php the_ID(); ?>" <?php wc_product_class('', get_the_ID()); ?>>
                        <!-- Product Content Section -->
                        <div class="row apw-woo-row">
                            <div class="col-md-6 apw-woo-product-gallery-col">
                                <?php do_action('apw_woo_before_product_gallery', $product); ?>
                                <div class="apw-woo-product-gallery-wrapper">
                                    <?php do_action('woocommerce_before_single_product_summary'); ?>
                                </div>
                                <?php do_action('apw_woo_after_product_gallery', $product); ?>
                            </div>
                            <div class="col-md-6 apw-woo-product-summary-col">
                                <?php do_action('apw_woo_before_product_summary', $product); ?>
                                <div class="apw-woo-product-summary">
                                    <?php do_action('woocommerce_single_product_summary'); ?>
                                </div>
                                <?php do_action('apw_woo_after_product_summary', $product); ?>
                            </div>
                        </div>
                        <?php do_action('woocommerce_after_single_product_summary'); ?>
                    </div>
                    <?php do_action('woocommerce_after_single_product'); ?>
                    <!-- Product Description -->
                    <?php if ($product->get_description()) : ?>
                        <div class="row apw-woo-row">
                            <div class="col apw-woo-product-description-section">
                                <?php do_action('apw_woo_before_product_description', $product); ?>
                                <div class="apw-woo-product-description">
                                    <h2 class="apw-woo-product-description-title">
                                        <?php esc_html_e('Product Description', 'apw-woo-plugin'); ?>
                                    </h2>
                                    <?php echo apply_filters('the_content', $product->get_description()); ?>
                                </div>
                                <?php do_action('apw_woo_after_product_description', $product); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <!-- FAQ Section -->
                    <div class="row apw-woo-row">
                        <div class="col apw-woo-faq-section-container">
                            <?php
                            // Add visualizer before the hook
                            apw_woo_visualize_hook('apw_woo_before_product_faqs', array($product));
                            /**
                            Hook: apw_woo_before_product_faqs
                             */
                            do_action('apw_woo_before_product_faqs', $product);
                            // Include the FAQ display partial
                            if (file_exists(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php')) {
                                $faq_product = $product;
                                include(APW_WOO_PLUGIN_DIR . 'templates/partials/faq-display.php');
                            }
                            // Add visualizer before the hook
                            apw_woo_visualize_hook('apw_woo_after_product_faqs', array($product));
                            /**
                            Hook: apw_woo_after_product_faqs
                             */
                            do_action('apw_woo_after_product_faqs', $product);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
<?php
else:
    ?>
    <div class="container">
        <div class="row">
            <div class="col">
                <p><?php esc_html_e('Product not found.', 'apw-woo-plugin'); ?></p>
            </div>
        </div>
    </div>
<?php
endif;
get_footer();
?>
