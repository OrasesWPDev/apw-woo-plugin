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
if (APW_WOO_DEBUG_MODE) {
    apw_woo_log('FAQ display partial loaded');
}

/**
 * Verify ACF FAQ fields are being retrieved properly
 *
 * @param array|false $faqs The FAQs array or false if not available
 * @param string $source The source of the FAQs (page, category, product)
 * @param mixed $source_object The object containing the FAQs
 * @return array|false The original FAQs array or false if not available
 */
function apw_woo_verify_faq_fields($faqs, $source, $source_object) {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("FAQ Verification: Checking {$source} FAQs");

        if (false === $faqs) {
            apw_woo_log("FAQ Verification ERROR: No FAQs found for {$source}");
        } elseif (empty($faqs)) {
            apw_woo_log("FAQ Verification WARNING: Empty FAQs array for {$source}");
        } else {
            apw_woo_log("FAQ Verification SUCCESS: Found " . count($faqs) . " FAQs for {$source}");

            // Log first FAQ as sample data
            if (isset($faqs[0])) {
                $sample = $faqs[0];
                $question = isset($sample['question']) ? substr($sample['question'], 0, 50) . '...' : 'NO QUESTION FIELD';
                $answer = isset($sample['answer']) ? substr($sample['answer'], 0, 50) . '...' : 'NO ANSWER FIELD';
                apw_woo_log("FAQ Sample - Q: {$question} A: {$answer}");
            }
        }
    }

    return $faqs;
}

// Initialize FAQs array
$faqs = null;

// Check which context variable is provided and get the appropriate FAQs
if (!empty($faq_page_id) && function_exists('get_field')) {
    // Get FAQs from specific page ID
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Getting FAQs from page ID: ' . $faq_page_id);
    }
    $faqs = get_field('faqs', $faq_page_id);
    $faqs = apply_filters('apw_woo_page_faqs', $faqs, $faq_page_id);
    $faqs = apw_woo_verify_faq_fields($faqs, 'page', $faq_page_id);
} elseif (!empty($faq_category) && function_exists('get_field')) {
    // Get FAQs from category
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Getting FAQs from category: ' . $faq_category->name);
    }
    $faqs = get_field('faqs', $faq_category);
    $faqs = apply_filters('apw_woo_category_faqs', $faqs, $faq_category);
    $faqs = apw_woo_verify_faq_fields($faqs, 'category', $faq_category);
} elseif (!empty($faq_product) && function_exists('get_field')) {
    // Get FAQs from product
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Getting FAQs from product: ' . $faq_product->get_name());
    }
    $faqs = get_field('faqs', $faq_product->get_id());
    $faqs = apply_filters('apw_woo_product_faqs', $faqs, $faq_product);
    $faqs = apw_woo_verify_faq_fields($faqs, 'product', $faq_product);
} else {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('No valid source provided for FAQs');
    }
}

// Only display if we have FAQs
if (!empty($faqs)) {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Found ' . count($faqs) . ' FAQs to display');
        // Check if we're in output buffering context
        apw_woo_log("FAQ Rendering: Output buffering " . (ob_get_level() > 0 ? "is active (Level: " . ob_get_level() . ")" : "is NOT active"));
    }

    /**
     * Hook: apw_woo_before_faq_section
     * @param array $faqs The array of FAQs to be displayed
     */
    do_action('apw_woo_before_faq_section', $faqs);
    ?>
    <!-- FAQ Section -->
    <div class="apw-woo-faq-section">
        <!-- APW-WOO-TEMPLATE: faq-display.php is loaded -->
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
                // Validate FAQ structure
                if (!isset($faq['question']) || !isset($faq['answer'])) {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("FAQ Structure Error: Missing question or answer in FAQ #{$index}");
                    }
                    continue;
                }

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
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('No FAQs found to display');
    }
    /**
     * Hook: apw_woo_no_faqs_found
     */
    do_action('apw_woo_no_faqs_found');
}