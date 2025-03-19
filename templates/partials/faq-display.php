<?php
/**
 * FAQ Display Partial
 *
 * This template displays FAQs from ACF field groups and can be included from
 * various template files, accepting different context variables:
 * - $faq_page_id: ID of a page containing FAQs (for shop page)
 * - $faq_category: Category object containing FAQs (for category pages)
 * - $faq_product: Product object containing FAQs (for single product pages)
 *
 * @package APW_Woo_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Debugging
apw_woo_log('FAQ display partial loaded');

// Initialize FAQs array
$faqs = null;

// Check which context variable is provided and get the appropriate FAQs
if (!empty($faq_page_id) && function_exists('get_field')) {
    // Get FAQs from specific page ID
    apw_woo_log('Getting FAQs from page ID: ' . $faq_page_id);
    $faqs = apply_filters('apw_woo_page_faqs', get_field('faqs', $faq_page_id), $faq_page_id);
} elseif (!empty($faq_category) && function_exists('get_field')) {
    // Get FAQs from category
    apw_woo_log('Getting FAQs from category: ' . $faq_category->name);
    $faqs = apply_filters('apw_woo_category_faqs', get_field('faqs', $faq_category), $faq_category);
} elseif (!empty($faq_product) && function_exists('get_field')) {
    // Get FAQs from product
    apw_woo_log('Getting FAQs from product: ' . $faq_product->get_name());
    $faqs = apply_filters('apw_woo_product_faqs', get_field('faqs', $faq_product->get_id()), $faq_product);
} else {
    apw_woo_log('No valid source provided for FAQs');
}

// Only display if we have FAQs
if (!empty($faqs)) {
    apw_woo_log('Found ' . count($faqs) . ' FAQs to display');

    /**
     * Hook: apw_woo_before_faq_section
     * @param array $faqs The array of FAQs to be displayed
     */
    do_action('apw_woo_before_faq_section', $faqs);
    ?>

    <!-- FAQ Section -->
    <div class="apw-woo-faq-section">
        <?php
        /**
         * Hook: apw_woo_before_faq_title
         * @param array $faqs The array of FAQs to be displayed
         */
        do_action('apw_woo_before_faq_title', $faqs);
        ?>

        <!-- FAQ Title -->
        <h2 class="apw-woo-faq-title">
            <?php
            $faq_title = (count($faqs) > 1)
                ? __('Frequently asked questions', 'apw-woo-plugin')
                : __('Frequently asked question', 'apw-woo-plugin');
            echo esc_html(apply_filters('apw_woo_faq_title', $faq_title, count($faqs)));
            ?>
        </h2>

        <?php
        /**
         * Hook: apw_woo_after_faq_title
         * @param array $faqs The array of FAQs to be displayed
         */
        do_action('apw_woo_after_faq_title', $faqs);
        ?>

        <!-- FAQ Items Container -->
        <div class="apw-woo-faq-container">
            <?php
            foreach ($faqs as $index => $faq) :
                /**
                 * Hook: apw_woo_before_faq_item
                 * @param array $faq The current FAQ item
                 * @param int $index The index of the current FAQ
                 * @param array $faqs The complete array of FAQs
                 */
                do_action('apw_woo_before_faq_item', $faq, $index, $faqs);
                ?>
                <!-- Single FAQ Item -->
                <div class="apw-woo-faq-item" id="faq-item-<?php echo esc_attr($index); ?>">
                    <div class="row apw-woo-faq-row">
                        <div class="col apw-woo-faq-col">
                            <!-- Question with Q icon -->
                            <div class="apw-woo-faq-question">
                                <div class="apw-woo-faq-q-icon">
                                    <img src="<?php echo esc_url(apply_filters('apw_woo_faq_q_icon', APW_WOO_PLUGIN_URL . 'assets/images/faq-q.svg')); ?>"
                                         alt="<?php echo esc_attr__('Q', 'apw-woo-plugin'); ?>"
                                         class="apw-woo-faq-q-image" />
                                </div>
                                <div class="apw-woo-faq-question-text">
                                    <?php echo wp_kses_post(apply_filters('apw_woo_faq_question', $faq['question'], $index)); ?>
                                </div>
                            </div>

                            <!-- Answer section -->
                            <div class="apw-woo-faq-answer">
                                <!-- Answer Label -->
                                <div class="apw-woo-faq-answer-label">
                                    <?php echo esc_html(apply_filters('apw_woo_faq_answer_label', __('ANSWER', 'apw-woo-plugin'), $index)); ?>
                                </div>
                                <!-- Answer Content -->
                                <div class="apw-woo-faq-answer-content">
                                    <?php echo wp_kses_post(apply_filters('apw_woo_faq_answer', $faq['answer'], $index)); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                /**
                 * Hook: apw_woo_after_faq_item
                 * @param array $faq The current FAQ item
                 * @param int $index The index of the current FAQ
                 * @param array $faqs The complete array of FAQs
                 */
                do_action('apw_woo_after_faq_item', $faq, $index, $faqs);
            endforeach;
            ?>
        </div>
    </div>

    <?php
    /**
     * Hook: apw_woo_after_faq_section
     * @param array $faqs The array of FAQs that were displayed
     */
    do_action('apw_woo_after_faq_section', $faqs);

} else {
    apw_woo_log('No FAQs found to display');

    /**
     * Hook: apw_woo_no_faqs_found
     */
    do_action('apw_woo_no_faqs_found');
}