<?php
/**
 * RMA Form Functions for APW WooCommerce Plugin
 *
 * Handles display, validation, cart integration, and order storage
 * for products tagged with "rma".
 *
 * @package APW_Woo_Plugin
 * @since   1.15.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * APW_Woo_RMA_Form Class
 *
 * Manages the display, validation, and saving of RMA form fields
 * for products tagged as 'rma'.
 */
class APW_Woo_RMA_Form
{
    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Product tag slug that triggers the RMA form display.
     *
     * @var string
     */
    private const RMA_TAG_SLUG = 'rma';

    /**
     * Meta key prefix for storing RMA form data.
     *
     * @var string
     */
    private const META_KEY_PREFIX = '_apw_woo_rma_';

    /**
     * Get instance.
     *
     * @return self
     */
    public static function get_instance()
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to prevent direct instantiation.
     */
    private function __construct()
    {
        if ( function_exists( 'apw_woo_log' ) ) {
            apw_woo_log( 'RMA Form class constructed' );
        }
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     * Hooks for display, validation, cart, and order integration are
     * added in subsequent implementation steps.
     */
    private function init_hooks()
    {
        // Step 2–4 hooks will be registered here
    }

    /**
     * Check if the current product has the RMA tag.
     *
     * @param int|WC_Product $product Product ID or product object
     * @return bool True if product has RMA tag
     */
    public function has_rma_tag( $product )
    {
        $product_id = 0;

        if ( is_numeric( $product ) ) {
            $product_id = (int) $product;
        } elseif ( is_object( $product ) && is_a( $product, 'WC_Product' ) ) {
            $product_id = $product->get_id();
        }

        if ( ! $product_id ) {
            return false;
        }

        return has_term( self::RMA_TAG_SLUG, 'product_tag', $product_id );
    }
}

/**
 * Initialize the RMA Form class.
 * Called during plugin initialization.
 */
function apw_woo_initialize_rma_form()
{
    APW_Woo_RMA_Form::get_instance();
}
