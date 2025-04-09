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
 * adds a new Short Description tab, and ensures Flatsome's custom tab remains (if it has content).
 * Includes safety check for non-array input.
 *
 * @param array|mixed $tabs The original array of tabs (or potentially other types if filtered incorrectly elsewhere).
 * @return array The modified array of tabs (guaranteed to be an array).
 */
function apw_woo_filter_product_tabs($tabs)
{
    // *** ADD THIS LINE ***
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Tab filter INPUT type: ' . gettype($tabs) . ', value: ' . print_r($tabs, true));
    }
    // *** END ADDED LINE ***

    // --- Input Validation ---
    // Ensure $tabs is an array before proceeding. If not, log and return an empty array.
    if (!is_array($tabs)) {
        if (APW_WOO_DEBUG_MODE) {
            $debug_input = is_null($tabs) ? 'NULL' : gettype($tabs);
            apw_woo_log('Filtering product tabs: Received invalid input type (' . $debug_input . ') instead of array. Returning empty array.', 'warning');
        }
        // Return empty array immediately to prevent further processing on invalid input
        return array();
    }
    // --- End Input Validation ---


    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Filtering product tabs...');
        apw_woo_log('Original tabs: ' . print_r(array_keys($tabs), true));
    }

    // --- Get Product Object Safely ---
    global $product;
    $product_id = 0;
    $is_valid_product = ($product && is_a($product, 'WC_Product'));
    if ($is_valid_product) {
        $product_id = $product->get_id();
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Filtering product tabs: Global $product invalid or not WC_Product.', 'warning');
        }
    }
    // --- End Get Product Object ---


    // 1. Add Short Description Tab (using post_excerpt)
    // Check if the product is valid and has a short description before adding the tab.
    if ($is_valid_product && $product->get_short_description()) {
        $tabs['short_description'] = array(
            'title' => __('Details', 'apw-woo-plugin'), // Changed title to 'Details'
            'priority' => 10, // Display it first
            'callback' => 'apw_woo_short_description_tab_content' // Use our custom callback
        );
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Added Short Description tab (Details).');
        }
    } elseif (APW_WOO_DEBUG_MODE && $is_valid_product) {
        // Log only if we had a product but it lacked the description
        apw_woo_log('Skipped adding Short Description tab (no content for product ID: ' . $product_id . ').');
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
    $flatsome_options = $product_id ? get_post_meta($product_id, 'wc_productdata_options', true) : null;

    $custom_tab_title = '';
    $custom_tab_content = '';

    // Safely access nested data (check only one level deep)
    if (is_array($flatsome_options) && isset($flatsome_options[0]) && is_array($flatsome_options[0])) {
        $nested_options = $flatsome_options[0];
        $custom_tab_title = isset($nested_options['_custom_tab_title']) ? $nested_options['_custom_tab_title'] : '';
        $custom_tab_content = isset($nested_options['_custom_tab']) ? $nested_options['_custom_tab'] : '';
    }

    // --- Logging for Flatsome Tab Check ---
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Flatsome Tab Check: Product ID: " . $product_id);
        // Optional: Log raw array if needed for deep debug, but can be large
        // apw_woo_log("Flatsome Tab Check: Raw 'wc_productdata_options': " . print_r($flatsome_options, true));
        apw_woo_log("Flatsome Tab Check: Extracted Nested '_custom_tab_title': " . $custom_tab_title);
        apw_woo_log("Flatsome Tab Check: Extracted Nested '_custom_tab' (has content?): " . (!empty($custom_tab_content) ? 'Yes' : 'No'));
        apw_woo_log("Flatsome Tab Check: Does 'ux_custom_tab' key exist in ORIGINAL \$tabs array? " . (isset($tabs['ux_custom_tab']) ? 'Yes' : 'No'));
    }
    // --- End Logging ---

    // Conditional logic for Flatsome tab
    if (isset($tabs['ux_custom_tab'])) {
        if (!empty($custom_tab_content)) {
            // Content exists, ensure tab remains and update title if needed

            // Update the tab title directly from our extracted meta if it differs or is empty
            if (!empty($custom_tab_title) && (!isset($tabs['ux_custom_tab']['title']) || $tabs['ux_custom_tab']['title'] !== $custom_tab_title)) {
                $tabs['ux_custom_tab']['title'] = esc_html($custom_tab_title);
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Flatsome Tab Check: Content exists, key found. UPDATED tab title to: "' . $custom_tab_title . '"');
                }
            } elseif (empty($tabs['ux_custom_tab']['title']) && !empty($custom_tab_title)) {
                $tabs['ux_custom_tab']['title'] = esc_html($custom_tab_title);
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Flatsome Tab Check: Content exists, key found. SETTING missing tab title to: "' . $custom_tab_title . '"');
                }
            }
            // Ensure it has a reasonable priority if needed
            $tabs['ux_custom_tab']['priority'] = isset($tabs['ux_custom_tab']['priority']) ? $tabs['ux_custom_tab']['priority'] : 20;

        } else {
            // No content, remove the tab
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Flatsome Tab Check: REMOVING "ux_custom_tab" because extracted _custom_tab content is empty.');
            }
            unset($tabs['ux_custom_tab']);
        }
    } elseif (!empty($custom_tab_content)) {
        // Log if content exists but Flatsome didn't add the tab initially
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Flatsome Tab Check: Warning! Custom tab content EXISTS, but "ux_custom_tab" key was NOT found in the original tabs array.');
        }
        // Note: We are NOT manually adding the tab here, assuming Flatsome should handle its own tab creation.
    }

    // (Optional) Remove Reviews Tab if not needed
    // if (isset($tabs['reviews'])) {
    //     unset($tabs['reviews']);
    //     if (APW_WOO_DEBUG_MODE) {
    //         apw_woo_log('Removed Reviews tab.');
    //     }
    // }


    // 5. Sort tabs by priority
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Final tabs before sort: ' . print_r(array_keys($tabs), true));
    }
    if (!empty($tabs)) { // Only sort if there are tabs left
        if (function_exists('wc_product_tabs_sort')) {
            uasort($tabs, 'wc_product_tabs_sort');
        } else {
            // Fallback: Basic sort if the WC function isn't available
            uasort($tabs, function ($a, $b) {
                $priorityA = isset($a['priority']) ? (int)$a['priority'] : 50;
                $priorityB = isset($b['priority']) ? (int)$b['priority'] : 50;
                return $priorityA <=> $priorityB; // Spaceship operator for comparison
            });
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Warning: wc_product_tabs_sort() function not found. Using basic priority sort.', 'warning');
            }
        }
    }
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Final tabs after sort: ' . print_r(array_keys($tabs), true));
    }

    // *** ADD THIS LOG LINE ***
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Tab filter FINAL RETURN: Returning tabs: ' . print_r(array_keys($tabs), true));
    }
    // *** END ADDED LOG LINE ***

    return $tabs; // Return the filtered (and now guaranteed array) $tabs
}

// Apply the filter
add_filter('woocommerce_product_tabs', 'apw_woo_filter_product_tabs', 98);


/**
 * Callback function to display the content for the Short Description tab.
 * Incorporates plugin-specific class naming.
 */
function apw_woo_short_description_tab_content()
{
    global $product;

    // Ensure product is valid before trying to access methods
    if (!$product || !is_a($product, 'WC_Product')) {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Short description tab callback: Invalid global $product object.');
        }
        return;
    }

    $short_description = $product->get_short_description();

    if ($short_description) {
        // Add our plugin-specific wrapper class
        echo '<div class="apw-woo-tab-content apw-woo-short-description-content">'; // Added apw-woo- prefix
        // Use apply_filters for standard output of short description
        echo apply_filters('woocommerce_short_description', $short_description);
        echo '</div>'; // Close plugin-specific wrapper
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Short description tab callback: No short description found for product ID ' . $product->get_id());
        }
    }
}


// Remove default related products output from the standard tab/content hook location
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);

?>