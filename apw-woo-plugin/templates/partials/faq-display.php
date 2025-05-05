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
function apw_woo_verify_faq_fields($faqs, $source, $source_object)
{
    if (APW_WOO_DEBUG_MODE) {
        $context_id = 'N/A';
        if ($source === 'page' && is_numeric($source_object)) {
            $context_id = $source_object;
        } elseif ($source === 'category' && is_a($source_object, 'WP_Term')) {
            $context_id = $source_object->term_id;
        } elseif ($source === 'product' && is_a($source_object, 'WC_Product')) {
            $context_id = $source_object->get_id();
        }
        apw_woo_log("FAQ Verification: Checking {$source} (ID: {$context_id}) FAQs");

        if (false === $faqs) {
            apw_woo_log("FAQ Verification RESULT: No FAQs found (get_field returned false) for {$source} ID: {$context_id}");
        } elseif (empty($faqs)) {
            apw_woo_log("FAQ Verification RESULT: Empty FAQs array returned for {$source} ID: {$context_id}");
        } else {
            apw_woo_log("FAQ Verification RESULT: Found " . count($faqs) . " FAQs for {$source} ID: {$context_id}");
            // Log first FAQ as sample data
            if (isset($faqs[0])) {
                $sample = $faqs[0];
                $question = isset($sample['question']) ? substr(strip_tags($sample['question']), 0, 50) . '...' : 'NO QUESTION FIELD';
                $answer = isset($sample['answer']) ? substr(strip_tags($sample['answer']), 0, 50) . '...' : 'NO ANSWER FIELD';
                apw_woo_log("FAQ Sample [{$source} ID: {$context_id}] - Q: {$question} A: {$answer}");
            }
        }
    }
    return $faqs;
}

// Initialize FAQs array
$faqs = null;
$faq_source_type = 'none'; // Track where FAQs came from for debugging

// Check which context variable is provided and get the appropriate FAQs
if (!empty($faq_page_id) && function_exists('get_field')) {
    // Get FAQs from specific page ID
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Getting FAQs from page ID: ' . $faq_page_id);
    }
    $faq_source_type = 'page';
    $faqs = get_field('faqs', $faq_page_id);
    $faqs = apply_filters('apw_woo_page_faqs', $faqs, $faq_page_id);
    $faqs = apw_woo_verify_faq_fields($faqs, $faq_source_type, $faq_page_id);

} elseif (!empty($faq_category) && is_a($faq_category, 'WP_Term') && function_exists('get_field')) {
    // Get FAQs from category
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Getting FAQs from category: ' . $faq_category->name . ' (ID: ' . $faq_category->term_id . ')');
    }
    $faq_source_type = 'category';
    $faqs = get_field('faqs', $faq_category); // Pass term object directly
    $faqs = apply_filters('apw_woo_category_faqs', $faqs, $faq_category);
    $faqs = apw_woo_verify_faq_fields($faqs, $faq_source_type, $faq_category);

} elseif (!empty($faq_product) && is_a($faq_product, 'WC_Product') && function_exists('get_field')) {
    // Get FAQs from product
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Getting FAQs from product: ' . $faq_product->get_name() . ' (ID: ' . $faq_product->get_id() . ')');
    }
    $faq_source_type = 'product';
    $faqs = get_field('faqs', $faq_product->get_id());
    $faqs = apply_filters('apw_woo_product_faqs', $faqs, $faq_product);
    $faqs = apw_woo_verify_faq_fields($faqs, $faq_source_type, $faq_product);

} else {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('No valid source provided for FAQs (checked $faq_page_id, $faq_category, $faq_product)');
    }
}

// Only display if we have FAQs (and ensure it's an array)
if (!empty($faqs) && is_array($faqs)) {
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('Found ' . count($faqs) . ' valid FAQs to display from source: ' . $faq_source_type);
        // Check if we're in output buffering context
        apw_woo_log("FAQ Rendering: Output buffering " . (ob_get_level() > 0 ? "is active (Level: " . ob_get_level() . ")" : "is NOT active"));
    }

    /**
     * Hook: apw_woo_before_faq_section
     * @param array $faqs The array of FAQs to be displayed
     * @param string $faq_source_type The source type ('page', 'category', 'product')
     */
    do_action('apw_woo_before_faq_section', $faqs, $faq_source_type);
    ?>
    <!-- FAQ Section -->
    <div class="apw-woo-faq-section">
        <!-- APW-WOO-TEMPLATE: faq-display.php is loaded -->
        <?php
        /**
         * Hook: apw_woo_before_faq_title
         * @param array $faqs The array of FAQs to be displayed
         * @param string $faq_source_type The source type
         */
        do_action('apw_woo_before_faq_title', $faqs, $faq_source_type);
        ?>
        <!-- FAQ Title -->
        <h2 class="apw-woo-faq-title">
            <?php
            $faq_title = (count($faqs) > 1)
                ? __('Frequently asked questions', 'apw-woo-plugin')
                : __('Frequently asked question', 'apw-woo-plugin');
            echo esc_html(apply_filters('apw_woo_faq_title', $faq_title, count($faqs), $faq_source_type));
            ?>
        </h2>
        <?php
        /**
         * Hook: apw_woo_after_faq_title
         * @param array $faqs The array of FAQs to be displayed
         * @param string $faq_source_type The source type
         */
        do_action('apw_woo_after_faq_title', $faqs, $faq_source_type);
        ?>
        <!-- FAQ Items Container -->
        <div class="apw-woo-faq-container">
            <?php
            foreach ($faqs as $index => $faq) :
                // Validate FAQ structure for this specific item
                if (!is_array($faq) || empty($faq['question']) || empty($faq['answer'])) {
                    if (APW_WOO_DEBUG_MODE) {
                        apw_woo_log("FAQ Structure Error: Skipping FAQ #{$index} due to missing/empty question or answer, or not an array.");
                    }
                    continue; // Skip this iteration
                }

                /**
                 * Hook: apw_woo_before_faq_item
                 * @param array $faq The current FAQ item
                 * @param int $index The index of the current FAQ
                 * @param array $faqs The complete array of FAQs
                 * @param string $faq_source_type The source type
                 */
                do_action('apw_woo_before_faq_item', $faq, $index, $faqs, $faq_source_type);
                ?>
                <!-- Single FAQ Item -->
                <div class="apw-woo-faq-item" id="faq-item-<?php echo esc_attr($index); ?>">
                    <div class="apw-woo-faq-content-flex">
                        <!-- Q Icon Column -->
                        <div class="apw-woo-faq-q-icon">
                            <img src="<?php echo esc_url(apply_filters('apw_woo_faq_q_icon', APW_WOO_PLUGIN_URL . 'assets/images/apw-faq-q.webp')); ?>"
                                 alt="<?php echo esc_attr__('Q', 'apw-woo-plugin'); ?>"
                                 class="apw-woo-faq-q-image"/>
                        </div>
                        <!-- Text Block Column (Question + Answer) -->
                        <div class="apw-woo-faq-text-block">
                            <!-- Question Text -->
                            <div class="apw-woo-faq-question-text">
                                <?php echo wp_kses_post(apply_filters('apw_woo_faq_question', $faq['question'], $index)); ?>
                            </div>
                            <!-- Answer Label -->
                            <div class="apw-woo-faq-answer-label">
                                <?php echo esc_html(apply_filters('apw_woo_faq_answer_label', __('ANSWER', 'apw-woo-plugin'), $index)); ?>
                            </div>
                            <!-- Answer Content -->
                            <div class="apw-woo-faq-answer-content">
                                <?php echo wp_kses_post(apply_filters('apw_woo_faq_answer', $faq['answer'], $index)); ?>
                            </div>
                        </div> <!-- End Text Block Container -->
                    </div> <!-- End Flex Container -->
                </div> <!-- End FAQ Item -->
                <?php
                /**
                 * Hook: apw_woo_after_faq_item
                 * @param array $faq The current FAQ item
                 * @param int $index The index of the current FAQ
                 * @param array $faqs The complete array of FAQs
                 * @param string $faq_source_type The source type
                 */
                do_action('apw_woo_after_faq_item', $faq, $index, $faqs, $faq_source_type);
            endforeach; // End loop through $faqs
            ?>
        </div> <!-- End FAQ Container -->
    </div> <!-- End FAQ Section -->
    <?php
    /**
     * Hook: apw_woo_after_faq_section
     * @param array $faqs The array of FAQs that were displayed
     * @param string $faq_source_type The source type
     */
    do_action('apw_woo_after_faq_section', $faqs, $faq_source_type);

} else { // Case where $faqs is empty or not an array after checks
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log('No valid FAQs found to display for source: ' . $faq_source_type);
    }
    /**
     * Hook: apw_woo_no_faqs_found
     * @param string $faq_source_type The source type where no FAQs were found
     */
    do_action('apw_woo_no_faqs_found', $faq_source_type);
}
