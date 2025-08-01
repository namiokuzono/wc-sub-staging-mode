<?php
/**
 * Plugin Name: WooCommerce Subscriptions Staging Controller
 * Description: Tool to check, fix, and control WooCommerce Subscriptions staging mode
 * Version: 1.3
 * Author: Woo Nami
 * Requires at least: 5.0
 * Tested up to: 6.6
 * WC requires at least: 3.0
 * WC tested up to: 9.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if class already exists to prevent redeclaration
if (!class_exists('WCS_Staging_Controller')) {

class WCS_Staging_Controller {
    
    private $plugin_version = '1.3';
    
    public function __construct() {
        // Use try-catch to prevent fatal errors
        try {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'handle_form_submission'));
            add_action('admin_notices', array($this, 'show_admin_notices'));
        } catch (Exception $e) {
            error_log('WCS Staging Controller Error: ' . $e->getMessage());
        }
    }
    
    private function repair_corrupted_url() {
        try {
            $site_url = get_site_url();
            
            // Clear the corrupted option completely
            delete_option('wc_subscriptions_siteurl');
            
            // Set it to the current site URL (this will put it in live mode)
            update_option('wc_subscriptions_siteurl', $site_url);
            
            // Also clear the notice option
            delete_option('wcs_ignore_duplicate_siteurl_notice');
            
            // Clear any caches
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            $redirect_url = add_query_arg(
                array('page' => 'wcs-staging-controller', 'repaired' => '1'),
                admin_url('admin.php')
            );
            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            error_log('Repair URL Error: ' . $e->getMessage());
            wp_die(esc_html__('Error repairing URL.'));
        }
    }
    
    public function add_admin_menu() {
        try {
            add_submenu_page(
                'woocommerce',
                'Subscriptions Staging Controller',
                'Staging Controller',
                'manage_options',
                'wcs-staging-controller',
                array($this, 'admin_page')
            );
        } catch (Exception $e) {
            error_log('WCS Staging Controller Menu Error: ' . $e->getMessage());
        }
    }
    
    public function admin_page() {
        try {
            // Check if WooCommerce Subscriptions is active
            if (!$this->is_wcs_active()) {
                $this->show_error_page('WooCommerce Subscriptions is not active!');
                return;
            }
            
            // Safe way to check if WCS_Staging class exists
            $is_staging = $this->check_staging_mode();
            $current_url = get_option('wc_subscriptions_siteurl', '');
            $site_url = get_site_url();
            
            $this->render_admin_page($is_staging, $current_url, $site_url);
            
        } catch (Exception $e) {
            error_log('WCS Staging Controller Admin Page Error: ' . $e->getMessage());
            $this->show_error_page('An error occurred while loading the page.');
        }
    }
    
    private function show_error_page($message) {
        echo '<div class="wrap">';
        echo '<h1>WooCommerce Subscriptions Staging Controller</h1>';
        echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . esc_html($message) . '</p></div>';
        echo '</div>';
    }
    
    private function check_staging_mode() {
        try {
            if (class_exists('WCS_Staging') && method_exists('WCS_Staging', 'is_duplicate_site')) {
                return WCS_Staging::is_duplicate_site();
            }
            return false;
        } catch (Exception $e) {
            error_log('WCS Staging Mode Check Error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function render_admin_page($is_staging, $current_url, $site_url) {
        ?>
        <div class="wrap">
            <h1>WooCommerce Subscriptions Staging Controller</h1>
            
            <!-- Status Display -->
            <div class="notice notice-<?php echo $is_staging ? 'error' : 'success'; ?>">
                <p><strong>Current Status:</strong> 
                    <?php if ($is_staging): ?>
                        <span style="color: red; font-weight: bold;">STAGING MODE</span>
                    <?php else: ?>
                        <span style="color: green; font-weight: bold;">LIVE MODE</span>
                    <?php endif; ?>
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
                        <td>
                            <?php 
                            echo esc_html($current_url ?: 'Not set'); 
                            if (strpos($current_url, '[wc_subscriptions_siteurl]') !== false) {
                                echo '<br><span style="color: red; font-weight: bold;">⚠️ CORRUPTED URL DETECTED!</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>URLs Match:</th>
                        <td><?php echo ($site_url === $current_url) ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                </table>
                
                <?php if (strpos($current_url, '[wc_subscriptions_siteurl]') !== false): ?>
                <div style="background: #ffebee; padding: 15px; border-left: 4px solid #f44336; margin-top: 15px;">
                    <h3>🔧 Corrupted URL Detected</h3>
                    <p>The stored URL contains a placeholder token that wasn't replaced properly. This will cause issues with staging mode detection.</p>
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('wcs_staging_control', 'wcs_staging_nonce'); ?>
                        <input type="hidden" name="action" value="repair_url">
                        <input type="submit" class="button button-secondary" value="🔧 Repair Corrupted URL" 
                               onclick="return confirm('This will fix the corrupted URL by setting it to your current site URL. Continue?')">
                    </form>
                </div>
                <?php endif; ?>
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
            
            <!-- Debug Information -->
            <div class="card">
                <h2>Debug Information</h2>
                <table class="form-table">
                    <tr>
                        <th>Plugin Version:</th>
                        <td><?php echo esc_html($this->plugin_version); ?></td>
                    </tr>
                    <tr>
                        <th>WooCommerce Version:</th>
                        <td><?php echo defined('WC_VERSION') ? esc_html(WC_VERSION) : 'Not detected'; ?></td>
                    </tr>
                    <tr>
                        <th>WCS Version:</th>
                        <td><?php echo defined('WCS_VERSION') ? esc_html(WCS_VERSION) : 'Not detected'; ?></td>
                    </tr>
                    <tr>
                        <th>WCS_Staging Class:</th>
                        <td><?php echo class_exists('WCS_Staging') ? '✅ Available' : '❌ Not found'; ?></td>
                    </tr>
                    <tr>
                        <th>Site URL:</th>
                        <td><?php echo esc_html(get_site_url()); ?></td>
                    </tr>
                    <tr>
                        <th>Raw Option Value:</th>
                        <td>
                            <code><?php echo esc_html(get_option('wc_subscriptions_siteurl', 'NOT_SET')); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th>Option Status:</th>
                        <td>
                            <?php 
                            $raw_option = get_option('wc_subscriptions_siteurl', '');
                            if (empty($raw_option)) {
                                echo '🔵 Not set';
                            } elseif (strpos($raw_option, '[wc_subscriptions_siteurl]') !== false) {
                                echo '🔴 Corrupted (contains placeholder)';
                            } elseif (filter_var($raw_option, FILTER_VALIDATE_URL)) {
                                echo '🟢 Valid URL';
                            } else {
                                echo '🟡 Invalid format';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    public function handle_form_submission() {
        try {
            // Check if this is our page and we have a form submission
            if (!isset($_GET['page']) || $_GET['page'] !== 'wcs-staging-controller') {
                return;
            }
            
            if (!isset($_POST['wcs_staging_nonce']) || !wp_verify_nonce($_POST['wcs_staging_nonce'], 'wcs_staging_control')) {
                return;
            }
            
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
            }
            
            if (!$this->is_wcs_active()) {
                wp_die(esc_html__('WooCommerce Subscriptions is not active.'));
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
                    case 'repair_url':
                        $this->repair_corrupted_url();
                        break;
                }
            }
        } catch (Exception $e) {
            error_log('WCS Staging Controller Form Error: ' . $e->getMessage());
            wp_die(esc_html__('An error occurred while processing your request.'));
        }
    }
    
    private function enable_live_mode() {
        try {
            $site_url = get_site_url();
            update_option('wc_subscriptions_siteurl', $site_url);
            delete_option('wcs_ignore_duplicate_siteurl_notice');
            
            // Clear any caches that might interfere
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            $redirect_url = add_query_arg(
                array('page' => 'wcs-staging-controller', 'mode' => 'live'),
                admin_url('admin.php')
            );
            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            error_log('Enable Live Mode Error: ' . $e->getMessage());
            wp_die(esc_html__('Error enabling live mode.'));
        }
    }
    
    private function enable_staging_mode() {
        try {
            // Set a different URL to trigger staging mode
            $parsed_url = parse_url(get_site_url());
            $host = isset($parsed_url['host']) ? $parsed_url['host'] : 'example.com';
            
            // Create a staging URL that's different from the current site
            $staging_url = 'https://staging.' . $host;
            
            // If current URL already contains staging, use different approach
            if (strpos($host, 'staging') !== false) {
                $staging_url = 'https://live.' . str_replace('staging.', '', $host);
            }
            
            update_option('wc_subscriptions_siteurl', $staging_url);
            
            // Clear any caches that might interfere
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            $redirect_url = add_query_arg(
                array('page' => 'wcs-staging-controller', 'mode' => 'staging'),
                admin_url('admin.php')
            );
            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            error_log('Enable Staging Mode Error: ' . $e->getMessage());
            wp_die(esc_html__('Error enabling staging mode.'));
        }
    }
    
    private function update_url() {
        try {
            if (isset($_POST['new_url']) && !empty($_POST['new_url'])) {
                $new_url = esc_url_raw($_POST['new_url']);
                
                // Validate URL format
                if (!filter_var($new_url, FILTER_VALIDATE_URL)) {
                    $redirect_url = add_query_arg(
                        array('page' => 'wcs-staging-controller', 'error' => 'invalid_url'),
                        admin_url('admin.php')
                    );
                    wp_redirect($redirect_url);
                    exit;
                }
                
                update_option('wc_subscriptions_siteurl', $new_url);
                delete_option('wcs_ignore_duplicate_siteurl_notice');
                
                // Clear any caches that might interfere
                if (function_exists('wp_cache_flush')) {
                    wp_cache_flush();
                }
                
                $redirect_url = add_query_arg(
                    array('page' => 'wcs-staging-controller', 'updated' => '1'),
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
        } catch (Exception $e) {
            error_log('Update URL Error: ' . $e->getMessage());
            wp_die(esc_html__('Error updating URL.'));
        }
    }
    
    public function show_admin_notices() {
        try {
            if (isset($_GET['page']) && $_GET['page'] === 'wcs-staging-controller') {
                if (isset($_GET['mode'])) {
                    $message = ($_GET['mode'] === 'live') ? 
                        'Live mode enabled! Automatic payments are now active.' : 
                        'Staging mode enabled! Automatic payments are now disabled.';
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
                }
                
                if (isset($_GET['updated'])) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('URL updated successfully!') . '</p></div>';
                }
                
                if (isset($_GET['repaired'])) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Corrupted URL has been repaired and set to live mode!') . '</p></div>';
                }
                
                if (isset($_GET['error'])) {
                    $error_message = __('An error occurred.');
                    if ($_GET['error'] === 'invalid_url') {
                        $error_message = __('Invalid URL format provided.');
                    }
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
                }
            }
        } catch (Exception $e) {
            error_log('Admin Notices Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if WooCommerce Subscriptions is active
     */
    private function is_wcs_active() {
        return class_exists('WC_Subscriptions') || class_exists('WCS_Staging');
    }
}

} // End class_exists check

// Initialize the plugin safely
function wcs_staging_controller_init() {
    try {
        if (class_exists('WooCommerce')) {
            if (class_exists('WCS_Staging_Controller')) {
                new WCS_Staging_Controller();
            }
        } else {
            // Show admin notice if WooCommerce is not active
            add_action('admin_notices', 'wcs_staging_controller_wc_missing_notice');
        }
    } catch (Exception $e) {
        error_log('WCS Staging Controller Init Error: ' . $e->getMessage());
    }
}

function wcs_staging_controller_wc_missing_notice() {
    try {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('WooCommerce Subscriptions Staging Controller:') . '</strong> ';
        echo esc_html__('WooCommerce is required for this plugin to work.');
        echo '</p></div>';
    } catch (Exception $e) {
        error_log('WCS Missing Notice Error: ' . $e->getMessage());
    }
}

// Hook initialization
add_action('plugins_loaded', 'wcs_staging_controller_init', 20);
