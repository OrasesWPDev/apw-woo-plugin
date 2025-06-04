<?php
/**
 * Shipping Functions for APW WooCommerce Plugin
 *
 * Custom shipping rules and modifications for WooCommerce shipping functionality.
 * Includes product-based shipping rate filtering and quantity-based free shipping rules.
 *
 * @package APW_Woo_Plugin
 * @since 1.17.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter shipping rates based on product quantities and eligibility
 * 
 * Enables free shipping for qualified products when minimum quantities are met.
 * Only applies when cart contains ONLY eligible products meeting their quantity requirements.
 *
 * @param array $rates Available shipping rates
 * @param array $package Shipping package data
 * @return array Modified shipping rates
 */
function apw_woo_filter_shipping_rates_by_product_quantity($rates, $package) {
    $free_shipping_instance_id = 'free_shipping:6';

    // Product ID => Minimum quantity required for free shipping
    $eligible_products = array(
        80 => 10,   // Product 80 requires 10+ quantity
        634 => 10,  // Product 634 requires 10+ quantity  
        647 => 5,   // Product 647 requires 5+ quantity
    );

    /**
     * Filter the eligible products for quantity-based free shipping
     *
     * @param array $eligible_products Array of product_id => min_quantity pairs
     */
    $eligible_products = apply_filters('apw_woo_shipping_eligible_products', $eligible_products);

    $product_quantities = array_fill_keys(array_keys($eligible_products), 0);
    $other_products_present = false;

    // Check cart contents
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];

        if (array_key_exists($product_id, $eligible_products)) {
            $product_quantities[$product_id] += $quantity;
        } else {
            $other_products_present = true;
            break;
        }
    }

    $eligible = false;
    foreach ($product_quantities as $id => $qty) {
        if ($qty > 0 && $qty < $eligible_products[$id]) {
            $eligible = false;
            break;
        } elseif ($qty >= $eligible_products[$id]) {
            $eligible = true;
        }
    }

    // Remove free shipping if not eligible or other products present
    if (!$eligible || $other_products_present) {
        if (isset($rates[$free_shipping_instance_id])) {
            unset($rates[$free_shipping_instance_id]);
            
            if (APW_WOO_DEBUG_MODE) {
                apw_woo_log('Free shipping removed - eligibility: ' . ($eligible ? 'true' : 'false') . ', other products: ' . ($other_products_present ? 'true' : 'false'));
            }
        }
    } else {
        if (APW_WOO_DEBUG_MODE) {
            apw_woo_log('Free shipping eligible - quantities met for all products');
        }
    }

    return $rates;
}

// Hook into WooCommerce shipping rate filtering
add_filter('woocommerce_package_rates', 'apw_woo_filter_shipping_rates_by_product_quantity', 10, 2);