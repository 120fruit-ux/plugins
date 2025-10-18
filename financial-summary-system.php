<?php
/**
 * Plugin Name: Financial Summary System
 * Description: Advanced financial summary system for take order form integration with push notifications
 * Version: 1.0.0
 * Author: Officialese
 * Text Domain: financial-summary
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FSS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FSS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FSS_VERSION', '1.0.0');

// Include required files
require_once FSS_PLUGIN_PATH . 'includes/class-fss-database.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-frontend.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-admin.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-ajax.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-notifications.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-reconciliation.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-analytics.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-importer.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-history-manager.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-order-sync.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-connection-diagnostics.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-tof-orders-fix.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-quick-fix-page.php';
require_once FSS_PLUGIN_PATH . 'tof-orders-fix.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-frontend-shortcodes-fix.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-frontend-readonly-fix.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-timezone-permanent-fix.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-frontend-form-defaults-fix.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-staff-permissions-fix.php';
require_once FSS_PLUGIN_PATH . 'includes/class-fss-dashboard-widgets-shortcode.php';



class Financial_Summary_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add VAPID key generation on plugin activation
        add_action('wp_ajax_fss_generate_vapid_keys', array($this, 'generate_vapid_keys'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('financial-summary', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize classes
        new FSS_Database();
        new FSS_Frontend();
        new FSS_Admin();
        new FSS_Ajax();
        new FSS_Notifications(); // Initialize notifications with push support
        new FSS_Reconciliation();
        new FSS_Analytics();
        new FSS_Importer();
        new FSS_History_Manager();
        new FSS_Order_Sync();
        new FSS_Connection_Diagnostics();
        new FSS_TOF_Orders_Fix();
        new FSS_Quick_Fix_Page();
        
        
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add daily reset cron job with Lagos timezone
        if (!wp_next_scheduled('fss_daily_reset')) {
            // Schedule for midnight Lagos time (UTC+1)
            $lagos_midnight = strtotime('tomorrow 00:00:00') - (1 * 60 * 60); // Subtract 1 hour for UTC+1
            wp_schedule_event($lagos_midnight, 'daily', 'fss_daily_reset');
        }
        add_action('fss_daily_reset', array($this, 'daily_reset_handler'));
        
        // Add hourly notification checks
        if (!wp_next_scheduled('fss_hourly_notifications')) {
            wp_schedule_event(time(), 'hourly', 'fss_hourly_notifications');
        }
        add_action('fss_hourly_notifications', array($this, 'hourly_notification_check'));
        
        // Handle service worker requests
        add_action('init', array($this, 'handle_service_worker_request'));
        
        // Add manifest.json support for PWA
        add_action('wp_head', array($this, 'add_manifest_link'));
        add_action('init', array($this, 'handle_manifest_request'));
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('fss-frontend-style', FSS_PLUGIN_URL . 'assets/css/frontend.css', array(), FSS_VERSION);
        wp_enqueue_style('fss-push-notifications', FSS_PLUGIN_URL . 'assets/css/push-notifications.css', array(), FSS_VERSION);
        
        wp_enqueue_script('fss-frontend-script', FSS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), FSS_VERSION, true);
        wp_enqueue_script('fss-push-notifications', FSS_PLUGIN_URL . 'assets/js/push-notifications.js', array('jquery'), FSS_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('fss-frontend-script', 'fss_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fss_nonce'),
            'timezone' => 'Africa/Lagos'
        ));
        
        // Localize script for Push Notifications
        wp_localize_script('fss-push-notifications', 'fss_push', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fss_push_nonce'),
            'vapid_public_key' => get_option('fss_vapid_public_key', ''),
            'service_worker_url' => home_url('/?fss_sw=1'),
            'manifest_url' => home_url('/?fss_manifest=1')
        ));
    }
    
    public function enqueue_admin_scripts() {
        wp_enqueue_style('fss-admin-style', FSS_PLUGIN_URL . 'assets/css/admin.css', array(), FSS_VERSION);
        wp_enqueue_style('fss-push-notifications', FSS_PLUGIN_URL . 'assets/css/push-notifications.css', array(), FSS_VERSION);
        
        wp_enqueue_script('fss-admin-script', FSS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), FSS_VERSION, true);
        wp_enqueue_script('fss-push-notifications', FSS_PLUGIN_URL . 'assets/js/push-notifications.js', array('jquery'), FSS_VERSION, true);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        // Localize admin scripts
        wp_localize_script('fss-admin-script', 'fss_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fss_admin_nonce')
        ));
        
        wp_localize_script('fss-push-notifications', 'fss_push', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fss_push_nonce'),
            'vapid_public_key' => get_option('fss_vapid_public_key', ''),
            'service_worker_url' => home_url('/?fss_sw=1'),
            'manifest_url' => home_url('/?fss_manifest=1')
        ));
    }
    
    public function activate() {
        // Create database tables
        FSS_Database::create_tables();
        
        // Create push subscriptions table
        $this->create_push_subscriptions_table();
        
        // Generate VAPID keys for push notifications
        $this->generate_vapid_keys();
        
        // Migrate historical data
        $this->migrate_historical_data();
        
        // Set default options
        $this->set_default_options();
        
        // Create necessary directories
        $this->create_directories();
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('fss_daily_reset');
        wp_clear_scheduled_hook('fss_hourly_notifications');
        
        // Optionally clean up push subscriptions
        // Note: We don't delete data on deactivation, only on uninstall
    }
    
    public function daily_reset_handler() {
        // This runs at midnight Lagos time daily
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $yesterday = clone $lagos_time;
        $yesterday->modify('-1 day');
        $yesterday_date = $yesterday->format('Y-m-d');
        $today_date = $lagos_time->format('Y-m-d');
        
        // Get yesterday's cash left to become today's old cash
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        $yesterday_cash_left = $wpdb->get_var($wpdb->prepare(
            "SELECT cash_left FROM $table_name WHERE date = %s ORDER BY created_at DESC LIMIT 1",
            $yesterday_date
        ));
        
        // Ensure yesterday's cash left is not negative
        $yesterday_cash_left = max(0, floatval($yesterday_cash_left ?: 0));
        
        // Store the old cash for today
        update_option('fss_old_cash_' . $today_date, $yesterday_cash_left);
        
        // Create automatic history record for today if it doesn't exist
        $today_summary = FSS_Database::get_daily_summary($today_date);
        if (!$today_summary) {
            // Create a new summary record for today with default values
            FSS_Database::create_or_update_daily_summary($today_date, array(
                'old_cash' => $yesterday_cash_left,
                'extras' => 0,
                'extras_remark' => '',
                'expense' => 0,
                'expense_remark' => '',
                'cash_left_market_card' => 0,
                'submission_type' => 'auto_daily_reset'
            ));
            
            error_log('FSS: Automatic history created for ' . $today_date);
        }
        
        // Send daily reset notification to admins
        FSS_Notifications::send_push_notification(
            'Daily Reset Complete',
            'Financial data has been reset for ' . $lagos_time->format('F d, Y') . '. Old Cash: ₦' . number_format($yesterday_cash_left, 2),
            FSS_PLUGIN_URL . 'assets/images/reset-icon.png',
            array(
                'type' => 'daily_reset',
                'date' => $today_date,
                'old_cash' => $yesterday_cash_left
            )
        );
        
        // Log the reset
        error_log('FSS Daily Reset: ' . $today_date . ' - Old Cash: ' . $yesterday_cash_left);
    }
    
    public function hourly_notification_check() {
        // Check and send time-based notifications
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $hour = intval($lagos_time->format('H'));
        $today = $lagos_time->format('Y-m-d');
        
        // 6 PM reminder for missing summary
        if ($hour == 18) {
            $this->check_missing_summary_notification($today);
        }
        
        // 8 PM reconciliation reminder
        if ($hour == 20) {
            $this->check_reconciliation_reminder($today);
        }
        
        // 10 AM morning summary reminder
        if ($hour == 10) {
            $this->check_morning_reminder($today);
        }
    }
    
    private function check_missing_summary_notification($date) {
        $summary = FSS_Database::get_daily_summary($date);
        
        if (!$summary) {
            FSS_Notifications::send_push_notification(
                'Daily Summary Reminder',
                'No financial summary submitted for today. Please complete before end of business.',
                FSS_PLUGIN_URL . 'assets/images/reminder-icon.png',
                array(
                    'type' => 'missing_summary',
                    'date' => $date
                )
            );
        }
    }
    
    private function check_reconciliation_reminder($date) {
        global $wpdb;
        $reconciliation_table = $wpdb->prefix . 'fss_reconciliations';
        
        $reconciliation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reconciliation_table WHERE date = %s",
            $date
        ));
        
        if (!$reconciliation) {
            FSS_Notifications::send_push_notification(
                'End of Day Reconciliation',
                'Please complete cash reconciliation before closing.',
                FSS_PLUGIN_URL . 'assets/images/reconcile-icon.png',
                array(
                    'type' => 'reconciliation_reminder',
                    'date' => $date
                )
            );
        }
    }
    
    private function check_morning_reminder($date) {
        // Check if yesterday's reconciliation was completed
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        
        global $wpdb;
        $reconciliation_table = $wpdb->prefix . 'fss_reconciliations';
        
        $yesterday_reconciliation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reconciliation_table WHERE date = %s",
            $yesterday
        ));
        
        if (!$yesterday_reconciliation) {
            FSS_Notifications::send_push_notification(
                'Previous Day Reconciliation Missing',
                'Yesterday\'s reconciliation was not completed. Please review.',
                FSS_PLUGIN_URL . 'assets/images/alert-icon.png',
                array(
                    'type' => 'missing_previous_reconciliation',
                    'date' => $yesterday
                )
            );
        }
    }
    
    public function handle_service_worker_request() {
        if (isset($_GET['fss_sw']) && $_GET['fss_sw'] === '1') {
            header('Content-Type: application/javascript');
            header('Service-Worker-Allowed: /');
            header('Cache-Control: no-cache');
            
            $sw_content = file_get_contents(FSS_PLUGIN_PATH . 'assets/js/sw.js');
            
            // Replace placeholders with actual values
            $sw_content = str_replace(
                '{{PLUGIN_URL}}', 
                FSS_PLUGIN_URL, 
                $sw_content
            );
            
            echo $sw_content;
            exit;
        }
    }
    
    public function add_manifest_link() {
        echo '<link rel="manifest" href="' . home_url('/?fss_manifest=1') . '">' . "\n";
        echo '<meta name="theme-color" content="#FF0000">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="Financial Summary">' . "\n";
    }
    
    public function handle_manifest_request() {
        if (isset($_GET['fss_manifest']) && $_GET['fss_manifest'] === '1') {
            header('Content-Type: application/json');
            
            $manifest = array(
                'name' => 'Financial Summary System',
                'short_name' => 'FinSummary',
                'description' => 'Advanced financial summary and tracking system',
                'start_url' => home_url('/?utm_source=pwa'),
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => '#FF0000',
                'icons' => array(
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-72x72.png',
                        'sizes' => '72x72',
                        'type' => 'image/png'
                    ),
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-96x96.png',
                        'sizes' => '96x96',
                        'type' => 'image/png'
                    ),
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-128x128.png',
                        'sizes' => '128x128',
                        'type' => 'image/png'
                    ),
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-144x144.png',
                        'sizes' => '144x144',
                        'type' => 'image/png'
                    ),
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-152x152.png',
                        'sizes' => '152x152',
                        'type' => 'image/png'
                    ),
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-192x192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png'
                    ),
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-384x384.png',
                        'sizes' => '384x384',
                        'type' => 'image/png'
                    ),
                    array(
                        'src' => FSS_PLUGIN_URL . 'assets/images/icon-512x512.png',
                        'sizes' => '512x512',
                        'type' => 'image/png'
                    )
                )
            );
            
            echo json_encode($manifest);
            exit;
        }
    }
    
    private function migrate_historical_data() {
        // Create financial summaries for previous orders
        global $wpdb;
        $orders_table = $wpdb->prefix . 'take_order_submissions';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$orders_table'") == $orders_table) {
            $historical_dates = $wpdb->get_results("
                SELECT DATE(created_at) as order_date,
                       SUM(CASE WHEN payment_method = 'transfer' OR payment_method = 'card' THEN total_amount ELSE 0 END) as transfer_card_total,
                       SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_total,
                       SUM(CASE WHEN order_type = 'delivery' THEN delivery_fee ELSE 0 END) as delivery_total
                FROM $orders_table 
                GROUP BY DATE(created_at)
                ORDER BY order_date ASC
            ");
            
            foreach ($historical_dates as $date_data) {
                FSS_Database::create_or_update_daily_summary($date_data->order_date, array(
                    'transfer_card' => $date_data->transfer_card_total,
                    'cash' => $date_data->cash_total,
                    'delivery' => $date_data->delivery_total,
                    'total_sales' => $date_data->transfer_card_total + $date_data->cash_total
                ));
            }
        }
    }
    
    private function create_push_subscriptions_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_push_subscriptions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            endpoint text NOT NULL,
            p256dh_key varchar(255) NOT NULL,
            auth_key varchar(255) NOT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY user_endpoint (user_id, endpoint(100)),
            KEY user_id (user_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function generate_vapid_keys() {
        // Check if VAPID keys already exist
        if (get_option('fss_vapid_public_key') && get_option('fss_vapid_private_key')) {
            return;
        }
        
        // Generate VAPID key pair using OpenSSL if available
        if (function_exists('openssl_pkey_new')) {
            $config = array(
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            );
            
            $private_key_resource = openssl_pkey_new($config);
            
            if ($private_key_resource) {
                // Export private key
                openssl_pkey_export($private_key_resource, $private_key_pem);
                
                // Get public key
                $public_key_details = openssl_pkey_get_details($private_key_resource);
                $public_key_pem = $public_key_details['key'];
                
                // Convert to base64url format for VAPID
                $private_key_base64 = $this->pem_to_base64url($private_key_pem, 'PRIVATE');
                $public_key_base64 = $this->pem_to_base64url($public_key_pem, 'PUBLIC');
                
                update_option('fss_vapid_private_key', $private_key_base64);
                update_option('fss_vapid_public_key', $public_key_base64);
            } else {
                // Fallback to random keys (less secure but functional)
                $this->generate_fallback_vapid_keys();
            }
        } else {
            // Fallback method
            $this->generate_fallback_vapid_keys();
        }
    }
    
    private function pem_to_base64url($pem, $type) {
        // Extract the key data from PEM format
        $pem = str_replace("-----BEGIN {$type} KEY-----", '', $pem);
        $pem = str_replace("-----END {$type} KEY-----", '', $pem);
        $pem = str_replace(array("\r", "\n", " "), '', $pem);
        
        // Convert to base64url
        return rtrim(strtr(base64_encode(base64_decode($pem)), '+/', '-_'), '=');
    }
    
    private function generate_fallback_vapid_keys() {
        // Fallback method using random bytes
        $private_key = base64_encode(random_bytes(32));
        $public_key = base64_encode(random_bytes(65));
        
        update_option('fss_vapid_private_key', $private_key);
        update_option('fss_vapid_public_key', $public_key);
    }
    
    private function set_default_options() {
        // Set default notification options
        if (!get_option('fss_discrepancy_threshold')) {
            update_option('fss_discrepancy_threshold', 10000);
        }
        
        if (!get_option('fss_notification_emails')) {
            update_option('fss_notification_emails', array(get_option('admin_email')));
        }
        
        if (!get_option('fss_daily_reminder_time')) {
            update_option('fss_daily_reminder_time', '18:00');
        }
        
        if (!get_option('fss_reconciliation_reminder_time')) {
            update_option('fss_reconciliation_reminder_time', '20:00');
        }
        
        if (!get_option('fss_enable_push_notifications')) {
            update_option('fss_enable_push_notifications', true);
        }
        
        if (!get_option('fss_notification_sound')) {
            update_option('fss_notification_sound', true);
        }
        
        if (!get_option('fss_notification_vibration')) {
            update_option('fss_notification_vibration', true);
        }
    }
    
    private function create_directories() {
        // Create uploads directory for exports
        $upload_dir = wp_upload_dir();
        $fss_dir = $upload_dir['basedir'] . '/financial-summary-exports';
        
        if (!file_exists($fss_dir)) {
            wp_mkdir_p($fss_dir);
            
            // Create .htaccess to protect the directory
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($fss_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    private function schedule_cron_jobs() {
        // Schedule daily reset at midnight Lagos time
        if (!wp_next_scheduled('fss_daily_reset')) {
            $lagos_midnight = strtotime('tomorrow 00:00:00') - (1 * 60 * 60); // UTC+1
            wp_schedule_event($lagos_midnight, 'daily', 'fss_daily_reset');
        }
        
        // Schedule hourly notification checks
        if (!wp_next_scheduled('fss_hourly_notifications')) {
            wp_schedule_event(time(), 'hourly', 'fss_hourly_notifications');
        }
        
        // Schedule weekly cleanup of old push subscriptions
        if (!wp_next_scheduled('fss_weekly_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'fss_weekly_cleanup');
        }
        add_action('fss_weekly_cleanup', array($this, 'cleanup_old_subscriptions'));
    }
    
    public function cleanup_old_subscriptions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_push_subscriptions';
        
        // Remove subscriptions older than 90 days that haven't been used
        $wpdb->query("
            DELETE FROM $table_name 
            WHERE last_used < DATE_SUB(NOW(), INTERVAL 90 DAY) 
            AND is_active = 0
        ");
    }
}

// Initialize the plugin
Financial_Summary_System::get_instance();

// Uninstall hook
register_uninstall_hook(__FILE__, 'fss_uninstall_plugin');

function fss_uninstall_plugin() {
    global $wpdb;
    
    // Only remove data if explicitly configured to do so
    if (get_option('fss_remove_data_on_uninstall')) {
        // Remove all plugin tables
        $tables = array(
            $wpdb->prefix . 'fss_daily_summaries',
            $wpdb->prefix . 'fss_history_entries',
            $wpdb->prefix . 'fss_reconciliations',
            $wpdb->prefix . 'fss_push_subscriptions'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove all plugin options
        $options = array(
            'fss_discrepancy_threshold',
            'fss_notification_emails',
            'fss_daily_reminder_time',
            'fss_reconciliation_reminder_time',
            'fss_enable_push_notifications',
            'fss_notification_sound',
            'fss_notification_vibration',
            'fss_vapid_public_key',
            'fss_vapid_private_key',
            'fss_admin_notices',
            'fss_remove_data_on_uninstall'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Clear cron jobs
        wp_clear_scheduled_hook('fss_daily_reset');
        wp_clear_scheduled_hook('fss_hourly_notifications');
        wp_clear_scheduled_hook('fss_weekly_cleanup');
        
        // Remove upload directory
        $upload_dir = wp_upload_dir();
        $fss_dir = $upload_dir['basedir'] . '/financial-summary-exports';
        if (file_exists($fss_dir)) {
            $files = glob($fss_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($fss_dir);
        }
    }
}