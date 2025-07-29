<?php
/**
 * Recurring Order Notice Template
 *
 * This template is displayed on the thank you page for orders containing
 * products with the 'recurring' tag, prompting customers to complete
 * their registration at Allpoint Command.
 *
 * Available variables:
 * - $order (WC_Order): The order object
 * - $token (string): Generated MD5 token for registration
 * - $registration_url (string): Complete registration URL with token
 * - $environment_name (string): Current environment name for display
 *
 * @package APW_Woo_Plugin
 * @since   2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Verify required variables are available
if (!isset($order, $token, $registration_url)) {
    return;
}
?>

<div class="apw-recurring-order-notice-container" style="margin: 30px 0;">
    <div class="apw-recurring-order-notice" style="
        background: linear-gradient(135deg, #147185 0%, #0f5d6b 100%);
        color: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        text-align: center;
        position: relative;
        overflow: hidden;
    ">
        <!-- Animated background elements -->
        <div class="apw-bg-animation" style="
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: apw-shimmer 3s infinite;
            pointer-events: none;
        "></div>
        
        <div class="apw-notice-content" style="position: relative; z-index: 2;">
            <h2 style="
                margin: 0 0 15px 0;
                font-size: 24px;
                font-weight: bold;
                text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            ">
                <span class="dashicons dashicons-yes-alt" style="
                    font-size: 28px;
                    vertical-align: middle;
                    margin-right: 10px;
                    color: #4CAF50;
                "></span>
                <?php esc_html_e('Complete Your Registration', 'apw-woo-plugin'); ?>
            </h2>
            
            <p style="
                margin: 0 0 20px 0;
                font-size: 16px;
                line-height: 1.5;
                opacity: 0.95;
            ">
                <?php esc_html_e('Your order contains recurring products that require registration to activate your services.', 'apw-woo-plugin'); ?>
            </p>
            
            <p style="
                margin: 0 0 25px 0;
                font-size: 14px;
                opacity: 0.85;
            ">
                <?php esc_html_e('Click the button below to complete your registration at Allpoint Command and get started immediately.', 'apw-woo-plugin'); ?>
            </p>
            
            <div class="apw-registration-button-container">
                <a href="<?php echo esc_url($registration_url); ?>" 
                   class="apw-registration-button pizzazz" 
                   target="_blank" 
                   rel="noopener"
                   style="
                       display: inline-block;
                       padding: 15px 30px;
                       background: linear-gradient(45deg, #27ae60, #2ecc71);
                       color: white;
                       text-decoration: none;
                       border-radius: 50px;
                       font-weight: bold;
                       font-size: 16px;
                       text-transform: uppercase;
                       letter-spacing: 1px;
                       transition: all 0.3s ease;
                       box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                       position: relative;
                       overflow: hidden;
                   "
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.3)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)';">
                    
                    <span class="button-text" style="position: relative; z-index: 2;">
                        <?php esc_html_e('Complete Registration Now', 'apw-woo-plugin'); ?>
                    </span>
                    
                    <!-- Button animation overlay -->
                    <div class="button-overlay" style="
                        position: absolute;
                        top: 0;
                        left: -100%;
                        width: 100%;
                        height: 100%;
                        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                        transition: left 0.5s ease;
                    "></div>
                </a>
            </div>
            
            <!-- What happens next section -->
            <div class="apw-next-steps" style="
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid rgba(255,255,255,0.2);
                font-size: 14px;
                opacity: 0.9;
            ">
                <h3 style="
                    margin: 0 0 10px 0;
                    color: white;
                    font-size: 16px;
                    font-weight: bold;
                ">
                    <?php esc_html_e('What happens next?', 'apw-woo-plugin'); ?>
                </h3>
                
                <ul style="
                    margin: 0 0 15px 0;
                    padding: 0;
                    line-height: 1.6;
                    color: rgba(255,255,255,0.9);
                    text-align: center;
                    list-style: none;
                ">
                    <li><?php esc_html_e('Complete your registration using the button above', 'apw-woo-plugin'); ?></li>
                    <li><?php esc_html_e('Set up your account preferences and billing information', 'apw-woo-plugin'); ?></li>
                    <li><?php esc_html_e('Your recurring services will be activated automatically', 'apw-woo-plugin'); ?></li>
                    <li><?php esc_html_e('You will receive confirmation emails for each step', 'apw-woo-plugin'); ?></li>
                </ul>
                
                <p style="
                    margin: 0;
                    font-size: 13px;
                    color: rgba(255,255,255,0.8);
                ">
                    <strong><?php esc_html_e('Need help?', 'apw-woo-plugin'); ?></strong>
                    <a href="/contact" style="color: rgba(255,255,255,0.9); text-decoration: underline;"><?php esc_html_e('Contact our support team', 'apw-woo-plugin'); ?></a> <?php esc_html_e('if you have any questions about the registration process.', 'apw-woo-plugin'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes apw-shimmer {
    0% {
        transform: translateX(-100%) translateY(-100%) rotate(45deg);
    }
    100% {
        transform: translateX(100%) translateY(100%) rotate(45deg);
    }
}

.apw-registration-button:hover .button-overlay {
    left: 0;
}

/* Responsive design */
@media (max-width: 768px) {
    .apw-recurring-order-notice {
        margin: 20px 10px !important;
        padding: 20px !important;
    }
    
    .apw-recurring-order-notice h2 {
        font-size: 20px !important;
    }
    
    .apw-registration-button {
        padding: 12px 24px !important;
        font-size: 14px !important;
    }
}

/* Print styles */
@media print {
    .apw-recurring-order-notice-container {
        background: white !important;
        color: black !important;
        border: 2px solid #ccc !important;
    }
    
    .apw-bg-animation,
    .button-overlay {
        display: none !important;
    }
    
    .apw-registration-button {
        background: #007cba !important;
        color: white !important;
    }
}
</style>

<!-- Optional: Add some JavaScript for enhanced interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const button = document.querySelector('.apw-registration-button');
    if (button) {
        // Track button clicks for analytics
        button.addEventListener('click', function() {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    'event_category': 'Registration',
                    'event_label': 'Allpoint Command Registration',
                    'value': 1
                });
            }
            
            // Optional: Add loading state
            this.style.opacity = '0.8';
            this.innerHTML = '<span class="button-text"><?php esc_html_e('Opening Registration...', 'apw-woo-plugin'); ?></span>';
        });
        
        // Reset button state if user comes back to tab
        window.addEventListener('focus', function() {
            if (button) {
                button.style.opacity = '1';
                button.innerHTML = '<span class="button-text"><?php esc_html_e('Complete Registration Now', 'apw-woo-plugin'); ?></span><div class="button-overlay" style="position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s ease;"></div>';
            }
        });
    }
});
</script>