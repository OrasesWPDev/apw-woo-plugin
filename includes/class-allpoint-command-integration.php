<?php
/**
 * Allpoint Command Integration Class
 *
 * Handles integration with Allpoint Command backend for orders containing
 * recurring products. Includes API posting, token generation, and order
 * data synchronization.
 *
 * @package APW_Woo_Plugin
 * @since   2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW_Woo_Allpoint_Command_Integration Class
 *
 * Main integration class for communicating with Allpoint Command backend
 * when orders contain products with the 'recurring' tag.
 */
class APW_Woo_Allpoint_Command_Integration
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
     * Product tag slug that triggers integration
     * @var string
     */
    private const RECURRING_TAG_SLUG = 'recurring';

    /**
     * Product tag slug for rental products
     * @var string
     */
    private const RENTAL_TAG_SLUG = 'rental';

    /**
     * Meta keys for order data storage
     */
    private const META_KEYS = [
        'token' => '_apw_allpoint_command_token',
        'contains_recurring' => '_apw_contains_recurring_products',
        'contains_rental' => '_apw_contains_rental_products',
        'api_post_status' => '_apw_api_post_status',
        'api_post_response' => '_apw_api_post_response',
        'api_post_timestamp' => '_apw_api_post_timestamp'
    ];

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
            apw_woo_log('Allpoint Command Integration initialized');
        }
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Hook into multiple order status changes to catch all payment methods
        add_action('woocommerce_order_status_processing', array($this, 'handle_order_processing'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this, 'handle_order_processing'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_processing'), 10, 1);
        
        // Hook into thank you page for post-checkout display
        add_action('woocommerce_thankyou', array($this, 'handle_thankyou_display'), 10, 1);
        
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration hooks initialized for multiple order statuses');
        }
    }

    /**
     * Handle order when it moves to processing status
     * 
     * @param int $order_id Order ID
     */
    public function handle_order_processing($order_id)
    {
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: Processing order ID ' . $order_id);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Invalid order ID ' . $order_id, 'error');
            }
            return;
        }

        // Safety check: Skip if already processed to prevent duplicates
        $existing_meta = $order->get_meta(self::META_KEYS['contains_recurring']);
        if ($existing_meta !== '') {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Order ' . $order_id . ' already processed (meta: ' . $existing_meta . '), skipping');
            }
            return;
        }

        // Check if order contains recurring products
        $contains_recurring = $this->order_contains_recurring_products($order);
        $contains_rental = $this->order_contains_rental_products($order);

        // Store flags on order
        $order->update_meta_data(self::META_KEYS['contains_recurring'], $contains_recurring);
        $order->update_meta_data(self::META_KEYS['contains_rental'], $contains_rental);

        if ($contains_recurring) {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Order contains recurring products, processing API integration');
            }

            // Generate and store token
            $token = $this->generate_token($order_id, $order->get_customer_id());
            $order->update_meta_data(self::META_KEYS['token'], $token);

            // Post to Allpoint Command API
            $this->post_to_api($order);
        } else {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Order does not contain recurring products, skipping integration');
            }
        }

        $order->save();
    }

    /**
     * Handle thank you page display for recurring orders
     * 
     * @param int $order_id Order ID
     */
    public function handle_thankyou_display($order_id)
    {
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: handle_thankyou_display called with order ID: ' . $order_id);
        }
        
        if (!$order_id) {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: No order ID provided to thankyou display');
            }
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Could not load order ' . $order_id);
            }
            return;
        }

        $contains_recurring = $order->get_meta(self::META_KEYS['contains_recurring']);
        
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: Order ' . $order_id . ' contains_recurring meta: ' . ($contains_recurring ? 'true' : 'false'));
            
            // Also check all order meta for debugging
            $all_meta = $order->get_meta_data();
            $meta_keys = array();
            foreach ($all_meta as $meta) {
                $meta_keys[] = $meta->key;
            }
            apw_woo_log('Allpoint Command Integration: Order ' . $order_id . ' all meta keys: ' . implode(', ', $meta_keys));
        }
        
        if ($contains_recurring) {
            $token = $order->get_meta(self::META_KEYS['token']);
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Displaying registration notice for order ' . $order_id . ' with token: ' . substr($token, 0, 8) . '...');
            }
            $this->display_registration_notice($order, $token);
        } else {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Order ' . $order_id . ' does not contain recurring products, skipping notice display');
            }
        }
    }

    /**
     * Check if order contains products with recurring tag
     * 
     * @param WC_Order $order Order object
     * @return bool True if recurring products found
     */
    private function order_contains_recurring_products($order)
    {
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: Checking order ' . $order->get_id() . ' for recurring products');
        }
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product_name = $item->get_name();
            
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Checking product ID ' . $product_id . ' (' . $product_name . ')');
                
                // Get all tags for this product
                $tags = get_the_terms($product_id, 'product_tag');
                if ($tags && !is_wp_error($tags)) {
                    $tag_names = array_map(function($tag) { return $tag->slug; }, $tags);
                    apw_woo_log('Allpoint Command Integration: Product ' . $product_id . ' has tags: ' . implode(', ', $tag_names));
                } else {
                    apw_woo_log('Allpoint Command Integration: Product ' . $product_id . ' has no tags or error getting tags');
                }
            }
            
            if (has_term(self::RECURRING_TAG_SLUG, 'product_tag', $product_id)) {
                if (function_exists('apw_woo_log')) {
                    apw_woo_log('Allpoint Command Integration: Found recurring product ID ' . $product_id . ' with tag "' . self::RECURRING_TAG_SLUG . '"');
                }
                return true;
            }
        }
        
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: No recurring products found in order ' . $order->get_id());
        }
        return false;
    }

    /**
     * Check if order contains products with rental tag
     * 
     * @param WC_Order $order Order object
     * @return bool True if rental products found
     */
    private function order_contains_rental_products($order)
    {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (has_term(self::RENTAL_TAG_SLUG, 'product_tag', $product_id)) {
                if (function_exists('apw_woo_log')) {
                    apw_woo_log('Allpoint Command Integration: Found rental product ID ' . $product_id);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Generate MD5 token from order ID and customer ID
     * 
     * @param int $order_id Order ID
     * @param int $customer_id Customer ID
     * @return string MD5 token
     */
    private function generate_token($order_id, $customer_id)
    {
        $token = md5($order_id . $customer_id);
        
        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: Generated token for order ' . $order_id . ', customer ' . $customer_id);
        }
        
        return $token;
    }

    /**
     * Post order data to Allpoint Command API
     * 
     * @param WC_Order $order Order object
     */
    private function post_to_api($order)
    {
        if (!$this->environment_manager) {
            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: Environment Manager not available', 'error');
            }
            return;
        }

        $api_endpoint = $this->environment_manager->get_api_endpoint();
        $payload = $this->build_api_payload($order);

        // Convert payload to form data format
        $form_data = array(
            'order_number' => $payload['order_number'],
            'woocommerce_user_id' => $payload['woocommerce_user_id'],
            'order_details' => json_encode($payload['order_details']),
            'order_date' => $payload['order_date'],
            'woocommerce_token' => $payload['woocommerce_token']
        );

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: Posting to API endpoint: ' . $api_endpoint);
            apw_woo_log('Allpoint Command Integration: Payload: ' . json_encode($payload, JSON_PRETTY_PRINT));
            apw_woo_log('Allpoint Command Integration: Form data: ' . print_r($form_data, true));
        }

        // Store timestamp
        $order->update_meta_data(self::META_KEYS['api_post_timestamp'], current_time('mysql'));

        // Make the API request with form data
        $response = wp_remote_post($api_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'APW-WooCommerce-Plugin/' . APW_WOO_VERSION
            ),
            'body' => $form_data,
            'timeout' => 30
        ));

        // Handle response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $order->update_meta_data(self::META_KEYS['api_post_status'], 'error');
            $order->update_meta_data(self::META_KEYS['api_post_response'], $error_message);

            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: API request failed: ' . $error_message, 'error');
            }
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            $order->update_meta_data(self::META_KEYS['api_post_status'], $response_code);
            $order->update_meta_data(self::META_KEYS['api_post_response'], $response_body);

            if (function_exists('apw_woo_log')) {
                apw_woo_log('Allpoint Command Integration: API response code: ' . $response_code);
                apw_woo_log('Allpoint Command Integration: API response body: ' . $response_body);
            }

            if ($response_code >= 200 && $response_code < 300) {
                if (function_exists('apw_woo_log')) {
                    apw_woo_log('Allpoint Command Integration: API request successful for order ' . $order->get_id());
                }
            } else {
                if (function_exists('apw_woo_log')) {
                    apw_woo_log('Allpoint Command Integration: API request returned error code ' . $response_code, 'warning');
                }
            }
        }

        $order->save();
    }

    /**
     * Build API payload for Allpoint Command
     * 
     * @param WC_Order $order Order object
     * @return array API payload data
     */
    private function build_api_payload($order)
    {
        $billing_address = $order->get_address('billing');
        $shipping_address = $order->get_address('shipping');

        // Build items array
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $items[$item->get_product_id()] = array(
                    'name' => $item->get_name(),
                    'slug' => $product->get_slug(),
                    'quantity' => $item->get_quantity()
                );
            }
        }

        $payload = array(
            'order_number' => $order->get_order_number(),
            'woocommerce_user_id' => $order->get_customer_id(),
            'order_details' => array(
                'addresses' => array(
                    'billing' => $billing_address,
                    'shipping' => $shipping_address
                ),
                'items' => $items
            ),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'woocommerce_token' => $order->get_meta(self::META_KEYS['token'])
        );

        return $payload;
    }

    /**
     * Display registration notice on thank you page
     * 
     * @param WC_Order $order Order object
     * @param string $token Generated token
     */
    private function display_registration_notice($order, $token)
    {
        if (!$this->environment_manager || empty($token)) {
            return;
        }

        $registration_url = $this->environment_manager->get_registration_url($token);
        $environment_name = $this->environment_manager->get_environment_name();

        // Load template if it exists, otherwise display inline
        $template_path = APW_WOO_PLUGIN_DIR . 'templates/checkout/recurring-order-notice.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback inline display
            ?>
            <div class="apw-recurring-order-notice">
                <h3><?php esc_html_e('Complete Your Registration', 'apw-woo-plugin'); ?></h3>
                <p><?php esc_html_e('Your order contains recurring products. Please complete your registration to activate your services:', 'apw-woo-plugin'); ?></p>
                <p>
                    <a href="<?php echo esc_url($registration_url); ?>" 
                       class="button pizzazz" 
                       target="_blank" 
                       rel="noopener">
                        <?php esc_html_e('Complete Registration at Allpoint Command', 'apw-woo-plugin'); ?>
                    </a>
                </p>
                <?php if (!$this->environment_manager->is_production()): ?>
                    <p class="apw-env-notice">
                        <small><?php echo esc_html(sprintf(__('Environment: %s', 'apw-woo-plugin'), $environment_name)); ?></small>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }

        if (function_exists('apw_woo_log')) {
            apw_woo_log('Allpoint Command Integration: Displayed registration notice for order ' . $order->get_id());
        }
    }

    /**
     * Get token for an order (public method for external access)
     * 
     * @param int $order_id Order ID
     * @return string|false Token or false if not found
     */
    public function get_order_token($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        return $order->get_meta(self::META_KEYS['token']);
    }

    /**
     * Check if order has been posted to API (public method for external access)
     * 
     * @param int $order_id Order ID
     * @return array|false API post status or false if not found
     */
    public function get_api_post_status($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        return array(
            'status' => $order->get_meta(self::META_KEYS['api_post_status']),
            'response' => $order->get_meta(self::META_KEYS['api_post_response']),
            'timestamp' => $order->get_meta(self::META_KEYS['api_post_timestamp'])
        );
    }
}

/**
 * Function to initialize the Allpoint Command Integration.
 * To be called from the main plugin file.
 */
function apw_woo_initialize_allpoint_command_integration()
{
    return APW_Woo_Allpoint_Command_Integration::get_instance();
}