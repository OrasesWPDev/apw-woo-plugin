<?php
/**
 * Order Notifications Class
 *
 * Handles email notifications for orders containing recurring or rental products.
 * Updates email content for proper Allpoint Wireless branding and attaches
 * rental agreements when applicable.
 *
 * @package APW_Woo_Plugin
 * @since   2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Order_Notifications Class
 *
 * Manages email notifications for recurring and rental product orders,
 * including content updates and document attachments.
 */
class APW_Woo_Order_Notifications
{
    /**
     * Instance of this class
     * @var self
     */
    private static $instance = null;

    /**
     * Environment Manager instance
     * @var APW_Woo_Environment_Manager
     */
    private $environment_manager;

    /**
     * Product tag slugs for email triggering
     */
    private const RECURRING_TAG_SLUG = 'recurring';
    private const RENTAL_TAG_SLUG = 'rental';

    /**
     * Rental agreement file path
     * @var string
     */
    private const RENTAL_AGREEMENT_FILE = 'Allpoint Wireless Rental Agreement.pdf';

    /**
     * Get instance
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to prevent direct instantiation.
     */
    private function __construct()
    {
        // Ensure Environment Manager is available
        if (class_exists('APW_Woo_Environment_Manager')) {
            $this->environment_manager = APW_Woo_Environment_Manager::get_instance();
        }

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Order Notifications initialized');
        }
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Hook into email content filtering
        add_filter('woocommerce_email_subject_customer_processing_order', array($this, 'filter_email_subject'), 10, 2);
        add_filter('woocommerce_email_subject_customer_completed_order', array($this, 'filter_email_subject'), 10, 2);
        add_filter('woocommerce_email_subject_new_order', array($this, 'filter_email_subject'), 10, 2);

        add_filter('woocommerce_email_heading_customer_processing_order', array($this, 'filter_email_content'), 10, 2);
        add_filter('woocommerce_email_heading_customer_completed_order', array($this, 'filter_email_content'), 10, 2);
        add_filter('woocommerce_email_heading_new_order', array($this, 'filter_email_content'), 10, 2);

        // Hook into email content before sending
        add_action('woocommerce_email_before_order_table', array($this, 'add_recurring_notice'), 10, 4);
        add_action('woocommerce_email_after_order_table', array($this, 'add_rental_notice'), 10, 4);

        // Hook into email attachments
        add_filter('woocommerce_email_attachments', array($this, 'add_rental_attachment'), 10, 3);

        // Hook into general email content filtering
        add_filter('wp_mail_content_type', array($this, 'set_email_content_type'));
        add_filter('wp_mail', array($this, 'filter_all_email_content'), 10, 1);

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Order Notifications hooks initialized');
        }
    }

    /**
     * Filter email subjects for branding updates
     * 
     * @param string $subject Email subject
     * @param WC_Order $order Order object
     * @return string Updated subject
     */
    public function filter_email_subject($subject, $order)
    {
        if (!$order) {
            return $subject;
        }

        // Check if order contains recurring or rental products
        if ($this->order_contains_special_products($order)) {
            $subject = $this->update_branding_content($subject);
            
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Order Notifications: Updated email subject for order ' . $order->get_id());
            }
        }

        return $subject;
    }

    /**
     * Filter email content for branding updates
     * 
     * @param string $content Email content
     * @param WC_Order $order Order object
     * @return string Updated content
     */
    public function filter_email_content($content, $order)
    {
        if (!$order) {
            return $content;
        }

        // Check if order contains recurring or rental products
        if ($this->order_contains_special_products($order)) {
            $content = $this->update_branding_content($content);
            
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Order Notifications: Updated email content for order ' . $order->get_id());
            }
        }

        return $content;
    }

    /**
     * Add recurring product notice to email
     * 
     * @param WC_Order $order Order object
     * @param bool $sent_to_admin Whether sent to admin
     * @param bool $plain_text Whether plain text email
     * @param WC_Email $email Email object
     */
    public function add_recurring_notice($order, $sent_to_admin, $plain_text, $email)
    {
        if (!$order || !$this->order_contains_recurring_products($order)) {
            return;
        }

        $token = $order->get_meta('_apw_allpoint_command_token');
        if (!$token || !$this->environment_manager) {
            return;
        }

        $registration_url = $this->environment_manager->get_registration_url($token);
        $environment_name = $this->environment_manager->get_environment_name();

        if ($plain_text) {
            echo "\n" . __('IMPORTANT: Complete Your Registration', 'apw-woo-plugin') . "\n";
            echo str_repeat('-', 40) . "\n";
            echo __('Your order contains recurring products that require registration.', 'apw-woo-plugin') . "\n";
            echo __('Please complete your registration at:', 'apw-woo-plugin') . "\n";
            echo $registration_url . "\n\n";
            if (!$this->environment_manager->is_production()) {
                echo sprintf(__('Environment: %s', 'apw-woo-plugin'), $environment_name) . "\n\n";
            }
        } else {
            ?>
            <div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007cba;">
                <h3 style="color: #007cba; margin-top: 0;"><?php esc_html_e('Complete Your Registration', 'apw-woo-plugin'); ?></h3>
                <p><?php esc_html_e('Your order contains recurring products that require registration to activate your services.', 'apw-woo-plugin'); ?></p>
                <p>
                    <a href="<?php echo esc_url($registration_url); ?>" 
                       style="display: inline-block; padding: 10px 20px; background-color: #007cba; color: white; text-decoration: none; border-radius: 4px;">
                        <?php esc_html_e('Complete Registration at Allpoint Command', 'apw-woo-plugin'); ?>
                    </a>
                </p>
                <?php if (!$this->environment_manager->is_production()): ?>
                    <p style="font-size: 12px; color: #666;">
                        <?php echo esc_html(sprintf(__('Environment: %s', 'apw-woo-plugin'), $environment_name)); ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Order Notifications: Added recurring notice to email for order ' . $order->get_id());
        }
    }

    /**
     * Add rental product notice to email
     * 
     * @param WC_Order $order Order object
     * @param bool $sent_to_admin Whether sent to admin
     * @param bool $plain_text Whether plain text email
     * @param WC_Email $email Email object
     */
    public function add_rental_notice($order, $sent_to_admin, $plain_text, $email)
    {
        if (!$order || !$this->order_contains_rental_products($order)) {
            return;
        }

        if ($plain_text) {
            echo "\n" . __('RENTAL AGREEMENT INFORMATION', 'apw-woo-plugin') . "\n";
            echo str_repeat('-', 40) . "\n";
            echo __('Your order contains rental products.', 'apw-woo-plugin') . "\n";
            echo __('Please review the attached rental agreement.', 'apw-woo-plugin') . "\n";
            echo __('IMPORTANT: Rental products will be held for shipping until rental agreement is processed.', 'apw-woo-plugin') . "\n\n";
        } else {
            ?>
            <div style="margin: 20px 0; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
                <h3 style="color: #856404; margin-top: 0;"><?php esc_html_e('Rental Agreement Information', 'apw-woo-plugin'); ?></h3>
                <p><?php esc_html_e('Your order contains rental products. Please review the attached rental agreement.', 'apw-woo-plugin'); ?></p>
                <p><strong><?php esc_html_e('IMPORTANT:', 'apw-woo-plugin'); ?></strong> 
                   <?php esc_html_e('Rental products will be held for shipping until the rental agreement is processed.', 'apw-woo-plugin'); ?>
                </p>
            </div>
            <?php
        }

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Order Notifications: Added rental notice to email for order ' . $order->get_id());
        }
    }

    /**
     * Add rental agreement attachment to email
     * 
     * @param array $attachments Current attachments
     * @param string $email_id Email ID
     * @param WC_Order $order Order object
     * @return array Updated attachments
     */
    public function add_rental_attachment($attachments, $email_id, $order)
    {
        // Only add attachment for order-related emails
        $order_email_ids = array(
            'customer_processing_order',
            'customer_completed_order',
            'new_order'
        );

        if (!in_array($email_id, $order_email_ids) || !$order) {
            return $attachments;
        }

        if (!$this->order_contains_rental_products($order)) {
            return $attachments;
        }

        $pdf_path = APW_WOO_PLUGIN_DIR . 'assets/pdf/' . self::RENTAL_AGREEMENT_FILE;
        
        if (file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
            
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Order Notifications: Added rental agreement attachment for order ' . $order->get_id());
            }
        } else {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Order Notifications: Rental agreement file not found: ' . $pdf_path, 'warning');
            }
        }

        return $attachments;
    }

    /**
     * Set email content type to HTML
     * 
     * @return string Content type
     */
    public function set_email_content_type()
    {
        return 'text/html';
    }

    /**
     * Filter all email content for branding updates
     * 
     * @param array $atts Email attributes
     * @return array Updated attributes
     */
    public function filter_all_email_content($atts)
    {
        // Check if this is a WooCommerce email
        if (isset($atts['subject']) && $this->is_woocommerce_email($atts)) {
            $atts['subject'] = $this->update_branding_content($atts['subject']);
            $atts['message'] = $this->update_branding_content($atts['message']);
            
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Order Notifications: Applied branding updates to email');
            }
        }

        return $atts;
    }

    /**
     * Update content with proper Allpoint Wireless branding
     * 
     * @param string $content Content to update
     * @return string Updated content
     */
    private function update_branding_content($content)
    {
        // Replace "The Wireless Box" with "Allpoint Wireless"
        $content = str_replace('The Wireless Box', 'Allpoint Wireless', $content);
        $content = str_replace('the wireless box', 'Allpoint Wireless', $content);
        $content = str_replace('THE WIRELESS BOX', 'ALLPOINT WIRELESS', $content);

        // Replace checkurbox.com references with appropriate Allpoint Command URL
        if ($this->environment_manager) {
            $allpoint_url = parse_url($this->environment_manager->get_base_url(), PHP_URL_HOST);
            $content = str_replace('checkurbox.com', $allpoint_url, $content);
            $content = str_replace('www.checkurbox.com', $allpoint_url, $content);
        }

        // Remove ATM-related content
        $content = preg_replace('/\bATM[^.]*\./i', '', $content);
        $content = preg_replace('/\bautomated teller machine[^.]*\./i', '', $content);
        $content = preg_replace('/\bcash machine[^.]*\./i', '', $content);

        // Clean up any double spaces or periods
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\.+/', '.', $content);
        $content = trim($content);

        return $content;
    }

    /**
     * Check if order contains recurring products
     * 
     * @param WC_Order $order Order object
     * @return bool True if recurring products found
     */
    private function order_contains_recurring_products($order)
    {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (has_term(self::RECURRING_TAG_SLUG, 'product_tag', $product_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if order contains rental products
     * 
     * @param WC_Order $order Order object
     * @return bool True if rental products found
     */
    private function order_contains_rental_products($order)
    {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (has_term(self::RENTAL_TAG_SLUG, 'product_tag', $product_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if order contains any special products (recurring or rental)
     * 
     * @param WC_Order $order Order object
     * @return bool True if special products found
     */
    private function order_contains_special_products($order)
    {
        return $this->order_contains_recurring_products($order) || $this->order_contains_rental_products($order);
    }

    /**
     * Check if email is from WooCommerce
     * 
     * @param array $atts Email attributes
     * @return bool True if WooCommerce email
     */
    private function is_woocommerce_email($atts)
    {
        $woocommerce_indicators = array(
            'order',
            'woocommerce',
            'purchase',
            'receipt',
            'invoice'
        );

        $subject_lower = strtolower($atts['subject'] ?? '');
        $message_lower = strtolower($atts['message'] ?? '');

        foreach ($woocommerce_indicators as $indicator) {
            if (strpos($subject_lower, $indicator) !== false || 
                strpos($message_lower, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Function to initialize the Order Notifications.
 * To be called from the main plugin file.
 */
function apw_woo_initialize_order_notifications()
{
    return APW_Woo_Order_Notifications::get_instance();
}