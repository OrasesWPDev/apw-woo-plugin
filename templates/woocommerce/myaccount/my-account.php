<?php
/**
 * My Account page - APW WooCommerce Plugin Override
 *
 * This template overrides the default WooCommerce My Account page template,
 * applying the standard page structure and header block from the plugin
 * using the direct shortcode method, matching single-product.php logic.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package APW_Woo_Plugin/Templates
 * @version 3.5.0-apw.2
 *
 * Original WooCommerce template version: 3.5.0 (Ensure compatibility or update based on your WC version)
 */

defined('ABSPATH') || exit;

// APW Woo Plugin: Log My Account template loading if debug mode is on
$apw_debug_mode = defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE;
$apw_log_exists = function_exists('apw_woo_log');

if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('MY ACCOUNT TEMPLATE: Loading custom My Account template: templates/woocommerce/myaccount/my-account.php with theme structure');
}

get_header();

$target_block_id = 'fourth-level-page-header'; // Consistent block ID
$account_page_id = wc_get_page_id('myaccount');
// Define the correct title ONLY for the fallback scenario
$correct_account_title = $account_page_id ? get_the_title($account_page_id) : __('My account', 'woocommerce');

?>
<main id="main" class="apw-woo-myaccount-main">
    <!-- APW-WOO-TEMPLATE: my-account.php (structured) is loaded -->

    <!-- Header Block - Contains hero image, page title, and breadcrumbs -->
    <div class="apw-woo-section-wrapper apw-woo-header-block">
        <?php
        /**
         * Hook: apw_woo_before_myaccount_header
         */
        do_action('apw_woo_before_myaccount_header');

        if ($apw_debug_mode && $apw_log_exists) {
            apw_woo_log('MY ACCOUNT TEMPLATE: Rendering header block section using direct do_shortcode. Block ID: ' . $target_block_id);
        }

        // Replicate the exact logic from single-product.php / final cart.php for rendering the block
        if (shortcode_exists('block')) {
            echo do_shortcode('[block id="' . esc_attr($target_block_id) . '"]'); // Direct echo
        } else {
            // Fallback if shortcode doesn't exist
            if ($apw_debug_mode && $apw_log_exists) {
                apw_woo_log('MY ACCOUNT TEMPLATE WARNING: Shortcode [block] does not exist.');
            }
            echo '<div class="container page-title-container-fallback"><h1 class="apw-woo-page-title entry-title">' . esc_html($correct_account_title) . '</h1></div>';
        }

        /**
         * Hook: apw_woo_after_myaccount_header
         */
        do_action('apw_woo_after_myaccount_header');
        ?>
    </div><!-- /.apw-woo-header-block -->

    <!-- Main Content Container -->
    <div class="container">
        <div class="row">
            <div class="col apw-woo-content-wrapper">
                <?php
                /**
                 * Hook: apw_woo_before_myaccount_content
                 */
                do_action('apw_woo_before_myaccount_content');

                /**
                 * My Account navigation.
                 * @since 2.6.0
                 */
                do_action('woocommerce_account_navigation'); ?>

                <div class="woocommerce-MyAccount-content">
                    <?php
                    /**
                     * My Account content.
                     * @since 2.6.0
                     */
                    do_action('woocommerce_account_content');
                    ?>
                </div>

                <?php
                /**
                 * Hook: apw_woo_after_myaccount_content
                 */
                do_action('apw_woo_after_myaccount_content');
                ?>
            </div> <!-- /.col.apw-woo-content-wrapper -->
        </div> <!-- /.row -->
    </div> <!-- /.container -->
</main><!-- /#main -->
<?php

// APW Woo Plugin: Log end of My Account template if debug mode is on
if ($apw_debug_mode && $apw_log_exists) {
    apw_woo_log('MY ACCOUNT TEMPLATE: Finished rendering custom My Account template with theme structure.');
}

get_footer();
?>
<!-- APW-WOO-TEMPLATE: Custom my-account.php (structured) is loaded -->