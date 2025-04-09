<?php
/**
 * WooCommerce Product Tab Customization Functions
 *
 * Handles filtering and modifying the product tabs displayed on the single product page.
 *
 * @package APW_Woo_Plugin
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filters the WooCommerce product tabs.
 *
 * Removes default Description and Additional Information tabs,
 * adds a new Short Description tab, and ensures Flatsome's custom tab remains.
 *
 * @param array $tabs The original array of tabs.
 * @return array The modified array of tabs.
 */
function apw_woo_filter_product_tabs($tabs)
{

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Filtering product tabs...');
        apw_woo_log('Original tabs: ' . print_r(array_keys($tabs), true));
    }

    // 1. Add Short Description Tab (using post_excerpt)
    global $product;
    if ($product && $product->get_short_description()) {
        $tabs['short_description'] = array(
            'title' => __('Details', 'apw-woo-plugin'), // Changed title to 'Details'
            'priority' => 10, // Display it first
            'callback' => 'apw_woo_short_description_tab_content' // Use our custom callback
        );
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Added Short Description tab (Details).');
        }
    } elseif (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Skipped adding Short Description tab (no content).');
    }


    // 2. Remove Default Description Tab
    if (isset($tabs['description'])) {
        unset($tabs['description']);
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Removed default Description tab.');
        }
    }

    // 3. Remove Default Additional Information Tab
    if (isset($tabs['additional_information'])) {
        unset($tabs['additional_information']);
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Removed default Additional Information tab.');
        }
    }

    // 4. Handle Flatsome's Custom Tab (ux_custom_tab) using wc_productdata_options
    $product_id = $product ? $product->get_id() : 0;
    $flatsome_options = $product_id ? get_post_meta($product_id, 'wc_productdata_options', true) : null;

    // Initialize variables
    $custom_tab_title = '';
    $custom_tab_content = '';

    // *** CORRECTED ACCESS: Use $flatsome_options[0] ***
    // Safely access nested data (check only one level deep)
    if (is_array($flatsome_options) && isset($flatsome_options[0]) && is_array($flatsome_options[0])) {
        $nested_options = $flatsome_options[0]; // <-- Access the first element
        $custom_tab_title = isset($nested_options['_custom_tab_title']) ? $nested_options['_custom_tab_title'] : '';
        $custom_tab_content = isset($nested_options['_custom_tab']) ? $nested_options['_custom_tab'] : '';
    }
    // *** END CORRECTION ***


    // *** Logging remains the same (keep it uncommented for now) ***
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Flatsome Tab Check: Product ID: " . $product_id);
        apw_woo_log("Flatsome Tab Check: Raw 'wc_productdata_options': " . print_r($flatsome_options, true)); // Keep this uncommented
        apw_woo_log("Flatsome Tab Check: Extracted Nested '_custom_tab_title': " . $custom_tab_title);
        apw_woo_log("Flatsome Tab Check: Extracted Nested '_custom_tab' (has content?): " . (!empty($custom_tab_content) ? 'Yes' : 'No'));
        apw_woo_log("Flatsome Tab Check: Does 'ux_custom_tab' key exist in ORIGINAL \$tabs array? " . (isset($tabs['ux_custom_tab']) ? 'Yes' : 'No'));
    }
    // *** END Logging ***

    // Conditional logic remains similar, but uses the correctly extracted variables
    if (isset($tabs['ux_custom_tab'])) {
        if (!empty($custom_tab_content)) {
            // Content exists, ensure tab remains and has reasonable priority

            // Update the tab title directly from our extracted meta if it differs or is empty
            if (!empty($custom_tab_title) && (!isset($tabs['ux_custom_tab']['title']) || $tabs['ux_custom_tab']['title'] !== $custom_tab_title)) {
                $tabs['ux_custom_tab']['title'] = esc_html($custom_tab_title); // Set the correct title
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Flatsome Tab Check: Content exists, key found. UPDATED tab title to: "' . $custom_tab_title . '"');
                }
            } elseif (empty($tabs['ux_custom_tab']['title']) && !empty($custom_tab_title)) {
                // Handle case where Flatsome might not have set a title
                $tabs['ux_custom_tab']['title'] = esc_html($custom_tab_title);
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Flatsome Tab Check: Content exists, key found. SETTING missing tab title to: "' . $custom_tab_title . '"');
                }
            }

            $tabs['ux_custom_tab']['priority'] = isset($tabs['ux_custom_tab']['priority']) ? $tabs['ux_custom_tab']['priority'] : 20; // Give it a priority after 'Details'

        } else {
            // No content, remove the tab
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Flatsome Tab Check: REMOVING "ux_custom_tab" because extracted _custom_tab content is empty.');
            }
            unset($tabs['ux_custom_tab']);
        }
    } elseif (!empty($custom_tab_content)) {
        // If content exists but Flatsome didn't add the tab... (logging remains)
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Flatsome Tab Check: Warning! Custom tab content EXISTS, but "ux_custom_tab" key was NOT found in the original tabs array.');
        }
    }


    // (Optional) Remove Reviews Tab
    // if (isset($tabs['reviews'])) {
    //     unset($tabs['reviews']);
    // }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Final tabs: ' . print_r(array_keys($tabs), true));
    }

    // Sort tabs by priority
    if (function_exists('wc_product_tabs_sort')) {
        uasort($tabs, 'wc_product_tabs_sort');
    } else {
        // Fallback: Basic sort if the WC function isn't available (should normally exist)
        uasort($tabs, function ($a, $b) {
            $priorityA = isset($a['priority']) ? (int)$a['priority'] : 50;
            $priorityB = isset($b['priority']) ? (int)$b['priority'] : 50;
            return $priorityA <=> $priorityB; // Spaceship operator for comparison
        });
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Warning: wc_product_tabs_sort() function not found. Using basic priority sort.', 'warning');
        }
    }

    return $tabs;
}

// Apply the filter
add_filter('woocommerce_product_tabs', 'apw_woo_filter_product_tabs', 98);
// Remove default related products output from the standard tab/content hook location
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);

/**
 * Callback function to display the content for the Short Description tab.
 * Incorporates plugin-specific class naming.
 */
function apw_woo_short_description_tab_content()
{
    global $product;

    if (!$product) {
        return;
    }

    $short_description = $product->get_short_description();

    if ($short_description) {
        // Add our plugin-specific wrapper class
        echo '<div class="apw-woo-tab-content apw-woo-short-description-content">'; // Added apw-woo- prefix

        // Optional: Keep original WC class if needed for base styling
        // echo '<div class="woocommerce-product-details__short-description">';

        // Use wc_format_content for proper formatting and apply standard filters
        echo apply_filters('woocommerce_short_description', $short_description);

        // Close original WC class if used
        // echo '</div>';

        echo '</div>'; // Close plugin-specific wrapper
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Short description tab callback: No short description found for product ID ' . $product->get_id());
        }
    }
}

// Note: Flatsome's custom tab content display is likely handled internally by Flatsome's
// own callback associated with the 'ux_custom_tab' key. We just ensure the tab exists
// or not based on content presence. No specific class needs adding here unless we override Flatsome's callback.
