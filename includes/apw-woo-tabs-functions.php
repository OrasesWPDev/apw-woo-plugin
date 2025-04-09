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

    // 4. Handle Flatsome's Custom Tab (ux_custom_tab)
    $custom_tab_title = get_post_meta($product->get_id(), 'custom_tab_title', true);
    $custom_tab_content = get_post_meta($product->get_id(), 'custom_tab_content', true);

    if (isset($tabs['ux_custom_tab'])) {
        if (!empty($custom_tab_content)) {
            // Content exists, ensure tab remains and has reasonable priority
            if (!empty($custom_tab_title) && $tabs['ux_custom_tab']['title'] !== $custom_tab_title) {
                if (APW_WOO_DEBUG_MODE) {
                    apw_woo_log('Flatsome custom tab found. Title: "' . $tabs['ux_custom_tab']['title'] . '". Content exists.');
                }
            }
            $tabs['ux_custom_tab']['priority'] = isset($tabs['ux_custom_tab']['priority']) ? $tabs['ux_custom_tab']['priority'] : 20; // Give it a priority after 'Details'
        } else {
            // No content, remove the tab
            unset($tabs['ux_custom_tab']);
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Flatsome custom tab ("ux_custom_tab") removed because content is empty.');
            }
        }
    } elseif (!empty($custom_tab_content) && APW_WOO_DEBUG_MODE) {
        apw_woo_log('Warning: Custom tab content exists, but "ux_custom_tab" key not found in tabs array.');
    }


    // (Optional) Remove Reviews Tab
    // if (isset($tabs['reviews'])) {
    //     unset($tabs['reviews']);
    // }

    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Final tabs: ' . print_r(array_keys($tabs), true));
    }

    // Sort tabs by priority
    uasort($tabs, 'wc_product_tabs_sort');

    return $tabs;
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
