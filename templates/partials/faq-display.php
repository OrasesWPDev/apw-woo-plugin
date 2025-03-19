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
    $faqs = get_field('faqs', $faq_page_id);
} elseif (!empty($faq_category) && function_exists('get_field')) {
    // Get FAQs from category
    apw_woo_log('Getting FAQs from category: ' . $faq_category->name);
    $faqs = get_field('faqs', $faq_category);
} elseif (!empty($faq_product) && function_exists('get_field')) {
    // Get FAQs from product
    apw_woo_log('Getting FAQs from product: ' . $faq_product->get_name());
    $faqs = get_field('faqs', $faq_product->get_id());
} else {
    apw_woo_log('No valid source provided for FAQs');
}

// Only display if we have FAQs
if (!empty($faqs)) {
    apw_woo_log('Found ' . count($faqs) . ' FAQs to display');
    ?>
    <!-- FAQ Section -->
    <div class="apw-woo-faq-section">
        <!-- FAQ Title -->
        <h2 class="apw-woo-faq-title">Frequently asked question<?php echo (count($faqs) > 1) ? 's' : ''; ?></h2>

        <!-- FAQ Items Container -->
        <div class="apw-woo-faq-container">
            <?php foreach ($faqs as $index => $faq) : ?>
                <!-- Single FAQ Item -->
                <div class="apw-woo-faq-item" id="faq-item-<?php echo esc_attr($index); ?>">
                    <!-- Question with Q icon -->
                    <div class="apw-woo-faq-question">
                        <div class="apw-woo-faq-q-icon">
                            <img src="<?php echo esc_url(APW_WOO_PLUGIN_URL . 'assets/images/faq-q.svg'); ?>"
                                 alt="Q"
                                 class="apw-woo-faq-q-image" />
                        </div>
                        <div class="apw-woo-faq-question-text">
                            <?php echo wp_kses_post($faq['question']); ?>
                        </div>
                    </div>

                    <!-- Answer section -->
                    <div class="apw-woo-faq-answer">
                        <!-- Answer Label -->
                        <div class="apw-woo-faq-answer-label">ANSWER</div>

                        <!-- Answer Content -->
                        <div class="apw-woo-faq-answer-content">
                            <?php echo wp_kses_post($faq['answer']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
} else {
    apw_woo_log('No FAQs found to display');
}
?>