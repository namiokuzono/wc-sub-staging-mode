<?php
/**
 * Plugin Name: WooCommerce Subscriptions Staging Controller
 * Description: Tool to check, fix, and control WooCommerce Subscriptions staging mode
 * Version: 1.2
 * Author: Woo Nami
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WCS_Staging_Controller {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Subscriptions Staging Controller',
            'Staging Controller',
            'manage_options',
            'wcs-staging-controller',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        // Safe way to check if WCS_Staging class exists
        $is_staging = false;
        if (class_exists('WCS_Staging')) {
            $is_staging = WCS_Staging::is_duplicate_site();
        }
        
        $current_url = get_option('wc_subscriptions_siteurl');
        $site_url = get_site_url();
        
        ?>
        <div class="wrap">
            <h1>WooCommerce Subscriptions Staging Controller</h1>
            
            <!-- Status Display -->
            <div class="notice notice-<?php echo $is_staging ? 'error' : 'success'; ?>">
                <p><strong>Current Status:</strong> 
                    <?php echo $is_staging ? '<span style="color: red; font-weight: bold;">STAGING MODE</span>' : '<span style="color: green; font-weight: bold;">LIVE MODE</span>'; ?>
                </p>
                <p><strong>Effect:</strong> 
                    <?php echo $is_staging ? 'Automatic payments disabled, manual renewals only' : 'Automatic payments enabled'; ?>
                </p>
            </div>
            
            <!-- Current Settings -->
            <div class="card">
                <h2>Current Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Current Site URL:</th>
                        <td><?php echo esc_html($site_url); ?></td>
                    </tr>
                    <tr>
                        <th>Stored Subscriptions URL:</th>
                        <td><?php echo esc_html($current_url ?: 'Not set'); ?></td>
                    </tr>
                    <tr>
                        <th>URLs Match:</th>
                        <td><?php echo ($site_url === $current_url) ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Staging Mode Controls -->
            <div class="card">
                <h2>Staging Mode Controls</h2>
                
                <?php if ($is_staging): ?>
                    <!-- Fix Staging Mode -->
                    <div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 15px;">
                        <h3>Enable Live Mode</h3>
                        <p>Click below to enable automatic payments and switch to live mode:</p>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('wcs_staging_control', 'wcs_staging_nonce'); ?>
                            <input type="hidden" name="action" value="enable_live">
                            <input type="submit" class="button button-primary" value="Enable Live Mode" 
                                   onclick="return confirm('Are you sure you want to enable automatic payments on this site?')">
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Enable Staging Mode -->
                    <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 15px;">
                        <h3>Enable Staging Mode</h3>
                        <p>Click below to disable automatic payments and switch to staging mode:</p>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('wcs_staging_control', 'wcs_staging_nonce'); ?>
                            <input type="hidden" name="action" value="enable_staging">
                            <input type="submit" class="button button-warning" value="Enable Staging Mode" 
                                   onclick="return confirm('This will disable automatic payments. Are you sure?')">
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Manual URL Control -->
                <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #6c757d;">
                    <h3>Manual URL Control</h3>
                    <p>Set a specific URL for WooCommerce Subscriptions:</p>
                    <form method="post">
                        <?php wp_nonce_field('wcs_staging_control', 'wcs_staging_nonce'); ?>
                        <input type="hidden" name="action" value="update_url">
                        <table class="form-table">
                            <tr>
                                <th>Set URL to:</th>
                                <td>
                                    <input type="url" name="new_url" value="<?php echo esc_attr($site_url); ?>" 
                                           class="regular-text" required>
                                    <p class="description">This will determine if the site is in staging or live mode</p>
                                </td>
                            </tr>
                        </table>
                        <input type="submit" class="button button-secondary" value="Update URL">
                    </form>
                </div>
            </div>
            
            <!-- Information -->
            <div class="card">
                <h2>How It Works</h2>
                <ul>
                    <li><strong>Live Mode:</strong> Automatic payments enabled, subscription emails sent</li>
                    <li><strong>Staging Mode:</strong> Manual renewals only, no automatic payments, subscription emails disabled</li>
                    <li><strong>URL Comparison:</strong> WooCommerce Subscriptions compares the current site URL with the stored URL</li>
                    <li><strong>Safety Feature:</strong> Staging mode prevents accidental payments on test sites</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function handle_form_submission() {
        if (!isset($_POST['wcs_staging_nonce']) || !wp_verify_nonce($_POST['wcs_staging_nonce'], 'wcs_staging_control')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'enable_live':
                    $this->enable_live_mode();
                    break;
                case 'enable_staging':
                    $this->enable_staging_mode();
                    break;
                case 'update_url':
                    $this->update_url();
                    break;
            }
        }
    }
    
    private function enable_live_mode() {
        $site_url = get_site_url();
        update_option('wc_subscriptions_siteurl', $site_url);
        delete_option('wcs_ignore_duplicate_siteurl_notice');
        
        wp_redirect(admin_url('admin.php?page=wcs-staging-controller&mode=live'));
        exit;
    }
    
    private function enable_staging_mode() {
        // Set a different URL to trigger staging mode
        $parsed_url = parse_url(get_site_url());
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : 'example.com';
        $staging_url = 'https://staging.' . $host;
        update_option('wc_subscriptions_siteurl', $staging_url);
        
        wp_redirect(admin_url('admin.php?page=wcs-staging-controller&mode=staging'));
        exit;
    }
    
    private function update_url() {
        if (isset($_POST['new_url']) && !empty($_POST['new_url'])) {
            $new_url = esc_url_raw($_POST['new_url']);
            update_option('wc_subscriptions_siteurl', $new_url);
            delete_option('wcs_ignore_duplicate_siteurl_notice');
            
            wp_redirect(admin_url('admin.php?page=wcs-staging-controller&updated=1'));
            exit;
        }
    }
    
    public function show_admin_notices() {
        if (isset($_GET['page']) && $_GET['page'] === 'wcs-staging-controller') {
            if (isset($_GET['mode'])) {
                $message = ($_GET['mode'] === 'live') ? 
                    'Live mode enabled! Automatic payments are now active.' : 
                    'Staging mode enabled! Automatic payments are now disabled.';
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            }
            
            if (isset($_GET['updated'])) {
                echo '<div class="notice notice-success"><p>URL updated successfully!</p></div>';
            }
        }
    }
}

// Initialize the plugin only if WooCommerce is active
function wcs_staging_controller_init() {
    if (class_exists('WooCommerce')) {
        new WCS_Staging_Controller();
    }
}
add_action('plugins_loaded', 'wcs_staging_controller_init');
