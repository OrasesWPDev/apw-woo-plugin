<?php
/**
 * APW WooCommerce Referral Export Class
 *
 * Handles exporting users who have a "Referred By" field value.
 * Provides CSV export functionality for referral tracking and analysis.
 *
 * @package APW_Woo_Plugin
 * @since 1.18.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APW WooCommerce Referral Export Class
 */
class APW_Woo_Referral_Export {
    /**
     * Instance of this class
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Export directory within WordPress uploads
     *
     * @var string
     */
    private $export_dir = 'apw-referral-exports';

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log('APW Referral Export class constructed');
        }
    }

    /**
     * Get instance
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add admin menu item
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add bulk action to users list
        add_filter('bulk_actions-users', array($this, 'add_bulk_action'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_action'), 10, 3);
        
        // Add export button to users list
        add_action('manage_users_extra_tablenav', array($this, 'add_export_button'));
        
        // Handle AJAX requests
        add_action('wp_ajax_apw_export_referrals', array($this, 'handle_ajax_export'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add user list filters
        add_action('restrict_manage_users', array($this, 'add_user_filters'));
        add_filter('pre_get_users', array($this, 'filter_users_by_referral'));
        
        // Setup export directory on init
        add_action('admin_init', array($this, 'setup_export_directory'));
    }

    /**
     * Add admin menu for referral exports
     */
    public function add_admin_menu() {
        add_submenu_page(
            'users.php',
            __('Referral Export', 'apw-woo-plugin'),
            __('Referral Export', 'apw-woo-plugin'),
            'manage_woocommerce',
            'apw-referral-export',
            array($this, 'render_export_page')
        );
    }

    /**
     * Render the export page
     */
    public function render_export_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'apw-woo-plugin'));
        }

        // Handle form submission
        if (isset($_POST['export_referrals']) && wp_verify_nonce($_POST['apw_export_nonce'], 'apw_export_referrals')) {
            $this->process_export_request();
        }

        $referred_users_count = $this->get_referred_users_count();
        $recent_exports = $this->get_recent_exports();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Referral Export', 'apw-woo-plugin'); ?></h1>
            
            <div class="apw-export-stats">
                <div class="apw-stat-box">
                    <h3><?php echo esc_html($referred_users_count); ?></h3>
                    <p><?php esc_html_e('Users with Referrals', 'apw-woo-plugin'); ?></p>
                </div>
            </div>

            <div class="apw-export-options">
                <h2><?php esc_html_e('Export Options', 'apw-woo-plugin'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('apw_export_referrals', 'apw_export_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="export_type"><?php esc_html_e('Export Type', 'apw-woo-plugin'); ?></label>
                            </th>
                            <td>
                                <select name="export_type" id="export_type">
                                    <option value="all"><?php esc_html_e('All Users with Referrals', 'apw-woo-plugin'); ?></option>
                                    <option value="by_referrer"><?php esc_html_e('Filter by Specific Referrer', 'apw-woo-plugin'); ?></option>
                                    <option value="date_range"><?php esc_html_e('Filter by Registration Date', 'apw-woo-plugin'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr class="filter-option" id="referrer-filter" style="display: none;">
                            <th scope="row">
                                <label for="referrer_name"><?php esc_html_e('Referrer Name', 'apw-woo-plugin'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="referrer_name" id="referrer_name" class="regular-text" />
                                <p class="description"><?php esc_html_e('Enter the name of the referrer to filter by', 'apw-woo-plugin'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="filter-option" id="date-filter" style="display: none;">
                            <th scope="row">
                                <label><?php esc_html_e('Date Range', 'apw-woo-plugin'); ?></label>
                            </th>
                            <td>
                                <label for="start_date"><?php esc_html_e('From:', 'apw-woo-plugin'); ?></label>
                                <input type="date" name="start_date" id="start_date" />
                                
                                <label for="end_date" style="margin-left: 20px;"><?php esc_html_e('To:', 'apw-woo-plugin'); ?></label>
                                <input type="date" name="end_date" id="end_date" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="include_order_data"><?php esc_html_e('Include Order Data', 'apw-woo-plugin'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="include_order_data" id="include_order_data" value="1" checked />
                                <label for="include_order_data"><?php esc_html_e('Include total orders and spent amount', 'apw-woo-plugin'); ?></label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Export to CSV', 'apw-woo-plugin'), 'primary', 'export_referrals'); ?>
                </form>
            </div>

            <?php if (!empty($recent_exports)) : ?>
            <div class="apw-recent-exports">
                <h2><?php esc_html_e('Recent Exports', 'apw-woo-plugin'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('File Name', 'apw-woo-plugin'); ?></th>
                            <th><?php esc_html_e('Created', 'apw-woo-plugin'); ?></th>
                            <th><?php esc_html_e('Size', 'apw-woo-plugin'); ?></th>
                            <th><?php esc_html_e('Action', 'apw-woo-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_exports as $export) : ?>
                        <tr>
                            <td><?php echo esc_html($export['name']); ?></td>
                            <td><?php echo esc_html($export['date']); ?></td>
                            <td><?php echo esc_html($export['size']); ?></td>
                            <td>
                                <a href="<?php echo esc_url($export['url']); ?>" class="button button-small">
                                    <?php esc_html_e('Download', 'apw-woo-plugin'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add bulk action to users list
     */
    public function add_bulk_action($bulk_actions) {
        $bulk_actions['apw_export_selected'] = __('Export Selected (Referrals Only)', 'apw-woo-plugin');
        return $bulk_actions;
    }

    /**
     * Handle bulk action
     */
    public function handle_bulk_action($redirect_url, $action, $user_ids) {
        if ($action !== 'apw_export_selected') {
            return $redirect_url;
        }

        if (!current_user_can('manage_woocommerce')) {
            return $redirect_url;
        }

        // Filter users to only those with referrals
        $referred_users = array();
        foreach ($user_ids as $user_id) {
            $referred_by = get_user_meta($user_id, 'apw_referred_by', true);
            if (!empty($referred_by)) {
                $referred_users[] = $user_id;
            }
        }

        if (empty($referred_users)) {
            $redirect_url = add_query_arg('apw_export_message', 'no_referrals', $redirect_url);
            return $redirect_url;
        }

        // Generate export
        $export_file = $this->generate_csv_export($referred_users, 'Selected Users');
        
        if ($export_file) {
            $redirect_url = add_query_arg(array(
                'apw_export_message' => 'success',
                'exported_count' => count($referred_users)
            ), $redirect_url);
        } else {
            $redirect_url = add_query_arg('apw_export_message', 'error', $redirect_url);
        }

        return $redirect_url;
    }

    /**
     * Add export button to users list
     */
    public function add_export_button($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            echo '<a href="' . esc_url(admin_url('users.php?page=apw-referral-export')) . '" class="button">';
            esc_html_e('Export All Referrals', 'apw-woo-plugin');
            echo '</a>';
            echo '</div>';
        }
    }

    /**
     * Add user filters
     */
    public function add_user_filters() {
        $current_filter = isset($_GET['apw_referral_filter']) ? sanitize_text_field($_GET['apw_referral_filter']) : '';
        
        echo '<select name="apw_referral_filter">';
        echo '<option value="">' . esc_html__('All Users', 'apw-woo-plugin') . '</option>';
        echo '<option value="with_referrals"' . selected($current_filter, 'with_referrals', false) . '>' . esc_html__('Users with Referrals', 'apw-woo-plugin') . '</option>';
        echo '<option value="no_referrals"' . selected($current_filter, 'no_referrals', false) . '>' . esc_html__('Users without Referrals', 'apw-woo-plugin') . '</option>';
        echo '</select>';
    }

    /**
     * Filter users by referral status
     */
    public function filter_users_by_referral($query) {
        if (!is_admin() || !isset($_GET['apw_referral_filter'])) {
            return;
        }

        $filter = sanitize_text_field($_GET['apw_referral_filter']);
        
        if ($filter === 'with_referrals') {
            $query->set('meta_query', array(
                array(
                    'key' => 'apw_referred_by',
                    'value' => '',
                    'compare' => '!='
                )
            ));
        } elseif ($filter === 'no_referrals') {
            $query->set('meta_query', array(
                'relation' => 'OR',
                array(
                    'key' => 'apw_referred_by',
                    'value' => '',
                    'compare' => '='
                ),
                array(
                    'key' => 'apw_referred_by',
                    'compare' => 'NOT EXISTS'
                )
            ));
        }
    }

    /**
     * Handle AJAX export request
     */
    public function handle_ajax_export() {
        check_ajax_referer('apw_export_referrals', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions', 'apw-woo-plugin'));
        }

        $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');
        $user_ids = $this->get_referred_users($export_type);
        
        if (empty($user_ids)) {
            wp_send_json_error(__('No users found with referrals', 'apw-woo-plugin'));
        }

        $export_file = $this->generate_csv_export($user_ids, ucfirst($export_type));
        
        if ($export_file) {
            wp_send_json_success(array(
                'download_url' => $export_file['url'],
                'file_name' => $export_file['name'],
                'count' => count($user_ids)
            ));
        } else {
            wp_send_json_error(__('Failed to generate export file', 'apw-woo-plugin'));
        }
    }

    /**
     * Process export request from form
     */
    private function process_export_request() {
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');
        $filters = array();
        
        if ($export_type === 'by_referrer' && !empty($_POST['referrer_name'])) {
            $filters['referrer_name'] = sanitize_text_field($_POST['referrer_name']);
        }
        
        if ($export_type === 'date_range') {
            if (!empty($_POST['start_date'])) {
                $filters['start_date'] = sanitize_text_field($_POST['start_date']);
            }
            if (!empty($_POST['end_date'])) {
                $filters['end_date'] = sanitize_text_field($_POST['end_date']);
            }
        }
        
        $include_order_data = !empty($_POST['include_order_data']);
        
        $user_ids = $this->get_referred_users($export_type, $filters);
        
        if (empty($user_ids)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No users found matching your criteria.', 'apw-woo-plugin') . '</p></div>';
            return;
        }

        $export_file = $this->generate_csv_export($user_ids, ucfirst($export_type), $include_order_data);
        
        if ($export_file) {
            echo '<div class="notice notice-success"><p>';
            printf(
                esc_html__('Export completed! %1$s users exported. %2$s', 'apw-woo-plugin'),
                count($user_ids),
                '<a href="' . esc_url($export_file['url']) . '" class="button button-primary">' . esc_html__('Download CSV', 'apw-woo-plugin') . '</a>'
            );
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to generate export file.', 'apw-woo-plugin') . '</p></div>';
        }
    }

    /**
     * Get referred users based on filters
     */
    private function get_referred_users($type = 'all', $filters = array()) {
        $args = array(
            'meta_query' => array(
                array(
                    'key' => 'apw_referred_by',
                    'value' => '',
                    'compare' => '!='
                )
            ),
            'fields' => 'ID'
        );

        if ($type === 'by_referrer' && !empty($filters['referrer_name'])) {
            $args['meta_query'][0]['value'] = $filters['referrer_name'];
            $args['meta_query'][0]['compare'] = 'LIKE';
        }

        if ($type === 'date_range') {
            $date_query = array();
            
            if (!empty($filters['start_date'])) {
                $date_query['after'] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $date_query['before'] = $filters['end_date'];
            }
            
            if (!empty($date_query)) {
                $args['date_query'] = array($date_query);
            }
        }

        $users = get_users($args);
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log("Found " . count($users) . " referred users for export type: {$type}");
        }
        
        return $users;
    }

    /**
     * Generate CSV export file
     */
    private function generate_csv_export($user_ids, $export_name = 'Referral Export', $include_order_data = true) {
        if (empty($user_ids)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $export_path = $upload_dir['basedir'] . '/' . $this->export_dir;
        
        // Ensure export directory exists
        if (!file_exists($export_path)) {
            wp_mkdir_p($export_path);
            $this->secure_export_directory($export_path);
        }

        $filename = 'referral-export-' . sanitize_file_name(strtolower($export_name)) . '-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = $export_path . '/' . $filename;
        
        // Open file for writing
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            return false;
        }

        // Write CSV headers
        $headers = array(
            'User ID',
            'Username',
            'Email',
            'First Name',
            'Last Name',
            'Company',
            'Phone',
            'Referred By',
            'Registration Date',
            'Last Login'
        );
        
        if ($include_order_data && function_exists('wc_get_customer_order_count')) {
            $headers[] = 'Total Orders';
            $headers[] = 'Total Spent';
        }
        
        fputcsv($file, $headers);

        // Write user data
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) {
                continue;
            }

            $row = array(
                $user->ID,
                $user->user_login,
                $user->user_email,
                get_user_meta($user_id, 'apw_first_name', true),
                get_user_meta($user_id, 'apw_last_name', true),
                get_user_meta($user_id, 'apw_company', true),
                get_user_meta($user_id, 'apw_phone', true),
                get_user_meta($user_id, 'apw_referred_by', true),
                $user->user_registered,
                get_user_meta($user_id, 'last_activity', true) ?: 'Never'
            );
            
            if ($include_order_data && function_exists('wc_get_customer_order_count')) {
                $customer = new WC_Customer($user_id);
                $row[] = wc_get_customer_order_count($user_id);
                $row[] = $this->format_price_for_csv($customer->get_total_spent());
            }
            
            fputcsv($file, $row);
        }

        fclose($file);

        // Return file info
        $file_url = $upload_dir['baseurl'] . '/' . $this->export_dir . '/' . $filename;
        
        if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
            apw_woo_log("Generated CSV export: {$filename} with " . count($user_ids) . " users");
        }
        
        return array(
            'name' => $filename,
            'path' => $filepath,
            'url' => $file_url,
            'size' => filesize($filepath)
        );
    }

    /**
     * Get count of users with referrals
     */
    private function get_referred_users_count() {
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'apw_referred_by',
                    'value' => '',
                    'compare' => '!='
                )
            ),
            'count_total' => true,
            'fields' => 'ID'
        ));
        
        return count($users);
    }

    /**
     * Get recent export files
     */
    private function get_recent_exports() {
        $upload_dir = wp_upload_dir();
        $export_path = $upload_dir['basedir'] . '/' . $this->export_dir;
        
        if (!file_exists($export_path)) {
            return array();
        }

        $files = glob($export_path . '/*.csv');
        $exports = array();
        
        foreach ($files as $file) {
            $filename = basename($file);
            $file_url = $upload_dir['baseurl'] . '/' . $this->export_dir . '/' . $filename;
            
            $exports[] = array(
                'name' => $filename,
                'path' => $file,
                'url' => $file_url,
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => size_format(filesize($file))
            );
        }

        // Sort by date (newest first)
        usort($exports, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Return only the 10 most recent
        return array_slice($exports, 0, 10);
    }

    /**
     * Setup export directory with security
     */
    public function setup_export_directory() {
        $upload_dir = wp_upload_dir();
        $export_path = $upload_dir['basedir'] . '/' . $this->export_dir;
        
        if (!file_exists($export_path)) {
            wp_mkdir_p($export_path);
        }
        
        $this->secure_export_directory($export_path);
        $this->cleanup_old_exports($export_path);
    }

    /**
     * Secure export directory
     */
    private function secure_export_directory($export_path) {
        // Create .htaccess file to prevent direct access
        $htaccess_file = $export_path . '/.htaccess';
        $htaccess_content = "Options -Indexes\nRequire all denied";
        
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php to prevent directory listing
        $index_file = $export_path . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * Cleanup old export files (older than 7 days)
     */
    private function cleanup_old_exports($export_path) {
        $files = glob($export_path . '/*.csv');
        $cutoff_time = time() - (7 * 24 * 60 * 60); // 7 days ago
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
                
                if (defined('APW_WOO_DEBUG_MODE') && APW_WOO_DEBUG_MODE && function_exists('apw_woo_log')) {
                    apw_woo_log("Cleaned up old export file: " . basename($file));
                }
            }
        }
    }

    /**
     * Format price for CSV export (remove HTML formatting)
     *
     * @param float $amount Raw price amount
     * @return string Formatted price for CSV
     */
    private function format_price_for_csv($amount) {
        if (empty($amount) || !is_numeric($amount)) {
            $amount = 0;
        }
        
        // Format as decimal with 2 places and add currency symbol
        $currency_symbol = get_woocommerce_currency_symbol();
        $formatted_amount = number_format((float)$amount, 2, '.', '');
        
        return $currency_symbol . $formatted_amount;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'users_page_apw-referral-export') {
            // Enqueue CSS
            wp_enqueue_style(
                'apw-referral-export-admin',
                APW_WOO_PLUGIN_URL . 'assets/css/apw-referral-export-admin.css',
                array(),
                APW_WOO_VERSION
            );
            
            // Enqueue JavaScript
            wp_enqueue_script(
                'apw-referral-export',
                APW_WOO_PLUGIN_URL . 'assets/js/apw-referral-export.js',
                array('jquery'),
                APW_WOO_VERSION,
                true
            );
            
            wp_localize_script('apw-referral-export', 'apwReferralExport', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('apw_export_referrals')
            ));
        }
    }
}