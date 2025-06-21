# Security Standards Specification

## Overview
This specification defines comprehensive security standards for the APW WooCommerce Plugin refactor, emphasizing WordPress security best practices, data protection, and secure coding patterns.

## WordPress Security Foundation

### 1. Input Validation and Sanitization

#### All User Input Must Be Validated
```php
// GOOD: Proper input validation and sanitization
function apw_woo_handle_customer_registration() {
    // Verify nonce for CSRF protection
    if (!isset($_POST['apw_woo_nonce']) || !wp_verify_nonce($_POST['apw_woo_nonce'], 'apw_woo_register_customer')) {
        wp_die(__('Security check failed. Please refresh the page and try again.', 'apw-woo-plugin'));
    }
    
    // Check user capabilities
    if (!current_user_can('edit_users') && !is_user_logged_in()) {
        wp_die(__('Insufficient permissions.', 'apw-woo-plugin'));
    }
    
    // Validate and sanitize each field
    $customer_data = [
        'first_name'   => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name'    => sanitize_text_field($_POST['last_name'] ?? ''),
        'email'        => sanitize_email($_POST['email'] ?? ''),
        'phone'        => sanitize_text_field($_POST['phone'] ?? ''),
        'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
        'referred_by'  => sanitize_text_field($_POST['referred_by'] ?? '')
    ];
    
    // Validate email format
    if (!is_email($customer_data['email'])) {
        wp_die(__('Please enter a valid email address.', 'apw-woo-plugin'));
    }
    
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'company_name'];
    foreach ($required_fields as $field) {
        if (empty($customer_data[$field])) {
            wp_die(sprintf(__('The %s field is required.', 'apw-woo-plugin'), $field));
        }
    }
    
    // Process the validated data
    apw_woo_create_customer($customer_data);
}

// BAD: No validation or sanitization
function apw_woo_bad_registration() {
    // NEVER DO THIS - Direct use of $_POST without validation
    $name = $_POST['name'];
    $email = $_POST['email'];
    wp_create_user($name, 'password', $email);
}
```

#### Sanitization Functions for Different Data Types
```php
/**
 * Sanitize customer input data
 */
function apw_woo_sanitize_customer_data($data) {
    return [
        'first_name'   => sanitize_text_field($data['first_name'] ?? ''),
        'last_name'    => sanitize_text_field($data['last_name'] ?? ''),
        'email'        => sanitize_email($data['email'] ?? ''),
        'phone'        => preg_replace('/[^0-9+\-\(\)\s]/', '', $data['phone'] ?? ''),
        'company_name' => sanitize_text_field($data['company_name'] ?? ''),
        'website'      => esc_url_raw($data['website'] ?? ''),
        'notes'        => sanitize_textarea_field($data['notes'] ?? ''),
        'product_ids'  => array_map('absint', $data['product_ids'] ?? []),
        'amount'       => floatval($data['amount'] ?? 0)
    ];
}

/**
 * Validate product ID
 */
function apw_woo_validate_product_id($product_id) {
    $product_id = absint($product_id);
    
    if (!$product_id) {
        return new WP_Error('invalid_product_id', __('Invalid product ID.', 'apw-woo-plugin'));
    }
    
    $product = wc_get_product($product_id);
    if (!$product || !$product->exists()) {
        return new WP_Error('product_not_found', __('Product not found.', 'apw-woo-plugin'));
    }
    
    return $product_id;
}
```

### 2. Output Escaping

#### Always Escape Output
```php
// Template files must escape all output
<?php
// GOOD: Proper output escaping
?>
<div class="customer-info">
    <h3><?php echo esc_html($customer->get_display_name()); ?></h3>
    <p><?php echo esc_html__('Email:', 'apw-woo-plugin'); ?> 
       <a href="mailto:<?php echo esc_attr($customer->get_email()); ?>">
           <?php echo esc_html($customer->get_email()); ?>
       </a>
    </p>
    <p><?php echo esc_html__('Company:', 'apw-woo-plugin'); ?> 
       <?php echo esc_html($customer->get_meta('company_name')); ?>
    </p>
    
    <?php if ($customer->get_website()): ?>
        <p><?php echo esc_html__('Website:', 'apw-woo-plugin'); ?> 
           <a href="<?php echo esc_url($customer->get_website()); ?>" target="_blank" rel="noopener">
               <?php echo esc_html($customer->get_website()); ?>
           </a>
        </p>
    <?php endif; ?>
</div>

<?php
// BAD: No output escaping
?>
<div class="customer-info">
    <h3><?php echo $customer->get_display_name(); ?></h3>
    <p>Email: <a href="mailto:<?php echo $customer->get_email(); ?>"><?php echo $customer->get_email(); ?></a></p>
</div>
```

#### Escaping Functions Reference
```php
/**
 * Security escaping helper functions
 */
function apw_woo_escape_output($data, $context = 'html') {
    switch ($context) {
        case 'html':
            return esc_html($data);
        case 'attr':
            return esc_attr($data);
        case 'url':
            return esc_url($data);
        case 'js':
            return esc_js($data);
        case 'textarea':
            return esc_textarea($data);
        default:
            return esc_html($data);
    }
}

// Usage in templates
echo apw_woo_escape_output($customer_name, 'html');
echo '<input value="' . apw_woo_escape_output($form_value, 'attr') . '">';
echo '<a href="' . apw_woo_escape_output($website_url, 'url') . '">';
```

### 3. Authentication and Authorization

#### Capability Checks
```php
/**
 * Admin functionality requires proper capabilities
 */
function apw_woo_export_customer_data() {
    // Check if user can manage WooCommerce
    if (!current_user_can('manage_woocommerce')) {
        wp_die(
            __('You do not have sufficient permissions to access this page.', 'apw-woo-plugin'),
            __('Insufficient Permissions', 'apw-woo-plugin'),
            ['response' => 403]
        );
    }
    
    // Additional capability check for sensitive operations
    if (!current_user_can('export_customer_data')) {
        wp_die(
            __('You do not have permission to export customer data.', 'apw-woo-plugin'),
            __('Export Permission Required', 'apw-woo-plugin'),
            ['response' => 403]
        );
    }
    
    // Proceed with export
    apw_woo_generate_customer_export();
}

/**
 * Customer-specific data access
 */
function apw_woo_get_customer_orders($customer_id) {
    $current_user_id = get_current_user_id();
    
    // Allow access if:
    // 1. Current user is the customer
    // 2. Current user has manage_woocommerce capability
    if ($current_user_id !== $customer_id && !current_user_can('manage_woocommerce')) {
        return new WP_Error(
            'access_denied',
            __('You do not have permission to view this customer data.', 'apw-woo-plugin')
        );
    }
    
    return wc_get_orders(['customer_id' => $customer_id]);
}
```

#### Session and Token Security
```php
/**
 * Secure session handling for payment processing
 */
function apw_woo_handle_payment_token() {
    // Verify AJAX request
    if (!wp_doing_ajax()) {
        wp_die(__('Invalid request method.', 'apw-woo-plugin'));
    }
    
    // Verify nonce
    if (!check_ajax_referer('apw_woo_payment_nonce', 'nonce', false)) {
        wp_send_json_error(__('Security check failed.', 'apw-woo-plugin'));
    }
    
    // Verify user session
    if (!is_user_logged_in() && !WC()->session->has_session()) {
        wp_send_json_error(__('Session expired. Please refresh the page.', 'apw-woo-plugin'));
    }
    
    // Process payment token securely
    $token = sanitize_text_field($_POST['payment_token'] ?? '');
    
    if (empty($token) || strlen($token) < 32) {
        wp_send_json_error(__('Invalid payment token.', 'apw-woo-plugin'));
    }
    
    // Store token securely in session (not in database)
    WC()->session->set('apw_payment_token', $token);
    
    wp_send_json_success(__('Payment token validated.', 'apw-woo-plugin'));
}
```

### 4. Database Security

#### Prepared Statements
```php
/**
 * GOOD: Use prepared statements for all database queries
 */
function apw_woo_get_customer_referrals($referrer_name) {
    global $wpdb;
    
    // Use $wpdb->prepare for dynamic queries
    $query = $wpdb->prepare("
        SELECT u.ID, u.user_login, u.user_email, u.user_registered,
               um.meta_value as referred_by
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
        WHERE um.meta_key = 'referred_by'
        AND um.meta_value LIKE %s
        ORDER BY u.user_registered DESC
        LIMIT %d
    ", '%' . $wpdb->esc_like($referrer_name) . '%', 100);
    
    return $wpdb->get_results($query);
}

/**
 * BAD: SQL injection vulnerability
 */
function apw_woo_bad_database_query($referrer_name) {
    global $wpdb;
    
    // NEVER DO THIS - Direct variable interpolation
    $query = "SELECT * FROM {$wpdb->users} WHERE user_login = '$referrer_name'";
    return $wpdb->get_results($query);
}

/**
 * Secure meta data handling
 */
function apw_woo_update_customer_meta($customer_id, $meta_key, $meta_value) {
    // Validate customer ID
    $customer_id = absint($customer_id);
    if (!$customer_id) {
        return false;
    }
    
    // Validate meta key (whitelist approach)
    $allowed_meta_keys = [
        'company_name',
        'phone_number',
        'referred_by',
        'vip_status',
        'discount_tier'
    ];
    
    if (!in_array($meta_key, $allowed_meta_keys, true)) {
        return false;
    }
    
    // Sanitize meta value based on key
    switch ($meta_key) {
        case 'company_name':
        case 'referred_by':
            $meta_value = sanitize_text_field($meta_value);
            break;
        case 'phone_number':
            $meta_value = preg_replace('/[^0-9+\-\(\)\s]/', '', $meta_value);
            break;
        case 'vip_status':
            $meta_value = (bool) $meta_value;
            break;
        case 'discount_tier':
            $meta_value = absint($meta_value);
            break;
    }
    
    return update_user_meta($customer_id, $meta_key, $meta_value);
}
```

### 5. File Upload and Template Security

#### Secure Template Loading
```php
/**
 * Secure template loading with path validation
 */
function apw_woo_load_template($template_name, $args = []) {
    // Validate template name - prevent directory traversal
    $template_name = ltrim($template_name, '/');
    if (strpos($template_name, '../') !== false || strpos($template_name, '..\\') !== false) {
        wp_die(__('Invalid template name.', 'apw-woo-plugin'));
    }
    
    // Only allow specific file extensions
    $allowed_extensions = ['php'];
    $template_extension = pathinfo($template_name, PATHINFO_EXTENSION);
    if (!in_array($template_extension, $allowed_extensions, true)) {
        wp_die(__('Invalid template file type.', 'apw-woo-plugin'));
    }
    
    // Define allowed template directories
    $template_dirs = [
        get_stylesheet_directory() . '/apw-woo-plugin/',
        get_template_directory() . '/apw-woo-plugin/',
        APW_WOO_PLUGIN_DIR . 'templates/'
    ];
    
    foreach ($template_dirs as $template_dir) {
        $template_path = $template_dir . $template_name;
        
        // Ensure the resolved path is within allowed directory
        $real_template_path = realpath($template_path);
        $real_template_dir = realpath($template_dir);
        
        if ($real_template_path && 
            $real_template_dir && 
            strpos($real_template_path, $real_template_dir) === 0 && 
            file_exists($real_template_path)) {
            
            // Extract args for template use
            if (!empty($args) && is_array($args)) {
                extract($args, EXTR_SKIP);
            }
            
            include $real_template_path;
            return;
        }
    }
    
    // Template not found - log in debug mode
    if (APW_WOO_DEBUG_MODE) {
        apw_woo_log("Template not found: {$template_name}", 'warning');
    }
}
```

#### File Upload Security (if needed)
```php
/**
 * Secure file upload handling
 */
function apw_woo_handle_file_upload($file_data) {
    // Check upload capabilities
    if (!current_user_can('upload_files')) {
        return new WP_Error('upload_permission', __('You do not have permission to upload files.', 'apw-woo-plugin'));
    }
    
    // Validate file size
    $max_size = 2 * MB_IN_BYTES; // 2MB limit
    if ($file_data['size'] > $max_size) {
        return new WP_Error('file_too_large', __('File size exceeds the maximum allowed limit.', 'apw-woo-plugin'));
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($file_data['type'], $allowed_types, true)) {
        return new WP_Error('invalid_file_type', __('File type not allowed.', 'apw-woo-plugin'));
    }
    
    // Use WordPress upload handling
    $upload = wp_handle_upload($file_data, ['test_form' => false]);
    
    if (isset($upload['error'])) {
        return new WP_Error('upload_failed', $upload['error']);
    }
    
    return $upload;
}
```

### 6. AJAX Security

#### Secure AJAX Handlers
```php
/**
 * Secure AJAX endpoint for cart updates
 */
function apw_woo_ajax_update_cart() {
    // Verify AJAX request
    if (!wp_doing_ajax()) {
        wp_die(__('Invalid request.', 'apw-woo-plugin'));
    }
    
    // Verify nonce
    if (!check_ajax_referer('apw_woo_cart_nonce', 'nonce', false)) {
        wp_send_json_error([
            'message' => __('Security check failed.', 'apw-woo-plugin')
        ]);
    }
    
    // Validate and sanitize input
    $product_id = absint($_POST['product_id'] ?? 0);
    $quantity = absint($_POST['quantity'] ?? 0);
    
    if (!$product_id || !$quantity) {
        wp_send_json_error([
            'message' => __('Invalid product or quantity.', 'apw-woo-plugin')
        ]);
    }
    
    // Validate product exists
    $product = wc_get_product($product_id);
    if (!$product || !$product->exists()) {
        wp_send_json_error([
            'message' => __('Product not found.', 'apw-woo-plugin')
        ]);
    }
    
    // Process cart update
    $result = WC()->cart->add_to_cart($product_id, $quantity);
    
    if ($result) {
        wp_send_json_success([
            'message' => __('Product added to cart.', 'apw-woo-plugin'),
            'cart_count' => WC()->cart->get_cart_contents_count()
        ]);
    } else {
        wp_send_json_error([
            'message' => __('Failed to add product to cart.', 'apw-woo-plugin')
        ]);
    }
}

// Register AJAX handlers
add_action('wp_ajax_apw_woo_update_cart', 'apw_woo_ajax_update_cart');
add_action('wp_ajax_nopriv_apw_woo_update_cart', 'apw_woo_ajax_update_cart');
```

### 7. Logging and Error Handling Security

#### Secure Debug Logging
```php
/**
 * Secure logging that doesn't expose sensitive data
 */
function apw_woo_secure_log($message, $level = 'info', $context = []) {
    if (!APW_WOO_DEBUG_MODE) {
        return;
    }
    
    // Remove sensitive data from context
    $safe_context = apw_woo_sanitize_log_context($context);
    
    // Use WordPress logging if available
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $logger->log($level, $message, ['source' => 'apw-woo-plugin', 'context' => $safe_context]);
    } else {
        // Fallback to error_log
        error_log("[APW-WOO] [{$level}] {$message} " . json_encode($safe_context));
    }
}

/**
 * Remove sensitive data from log context
 */
function apw_woo_sanitize_log_context($context) {
    $sensitive_keys = [
        'password',
        'credit_card',
        'cvv',
        'ssn',
        'payment_token',
        'api_key',
        'secret'
    ];
    
    foreach ($sensitive_keys as $key) {
        if (isset($context[$key])) {
            $context[$key] = '[REDACTED]';
        }
    }
    
    return $context;
}
```

### 8. Configuration Security

#### Secure Settings Storage
```php
/**
 * Secure plugin settings management
 */
function apw_woo_get_secure_setting($setting_key, $default = null) {
    // Validate setting key
    $allowed_settings = [
        'surcharge_rate',
        'vip_discount_tiers',
        'debug_mode',
        'github_token',
        'payment_gateway_settings'
    ];
    
    if (!in_array($setting_key, $allowed_settings, true)) {
        return $default;
    }
    
    $settings = get_option('apw_woo_settings', []);
    return isset($settings[$setting_key]) ? $settings[$setting_key] : $default;
}

/**
 * Update settings with validation
 */
function apw_woo_update_secure_setting($setting_key, $value) {
    // Check permissions
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    // Validate and sanitize based on setting type
    switch ($setting_key) {
        case 'surcharge_rate':
            $value = max(0, min(0.1, floatval($value))); // 0-10%
            break;
        case 'debug_mode':
            $value = (bool) $value;
            break;
        case 'github_token':
            $value = sanitize_text_field($value);
            break;
        default:
            return false;
    }
    
    $settings = get_option('apw_woo_settings', []);
    $settings[$setting_key] = $value;
    
    return update_option('apw_woo_settings', $settings);
}
```

## Security Checklist

### Pre-Deployment Security Review
- [ ] All user input validated and sanitized
- [ ] All output properly escaped
- [ ] Database queries use prepared statements
- [ ] Capability checks in place for privileged operations
- [ ] Nonce verification for all forms and AJAX requests
- [ ] File upload restrictions implemented
- [ ] Template loading secured against path traversal
- [ ] Sensitive data excluded from logs
- [ ] Error messages don't expose system information
- [ ] Settings validation and sanitization implemented

### Ongoing Security Monitoring
- [ ] Regular security audit of new code
- [ ] Dependency vulnerability scanning
- [ ] Error log monitoring for security issues
- [ ] User capability review
- [ ] Settings configuration review

This comprehensive security standard ensures the plugin follows WordPress security best practices while protecting sensitive customer and payment data.