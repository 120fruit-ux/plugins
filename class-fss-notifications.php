<?php

class FSS_Notifications {
    
    public function __construct() {
        add_action('wp_ajax_fss_register_push_subscription', array($this, 'register_push_subscription'));
        add_action('wp_ajax_fss_send_test_notification', array($this, 'send_test_notification'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_push_scripts'));
        add_action('init', array($this, 'handle_service_worker'));
    }
    
    public function enqueue_push_scripts() {
        wp_enqueue_script('fss-push-notifications', FSS_PLUGIN_URL . 'assets/js/push-notifications.js', array('jquery'), FSS_VERSION, true);
        
        wp_localize_script('fss-push-notifications', 'fss_push', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fss_push_nonce'),
            'vapid_public_key' => get_option('fss_vapid_public_key', ''),
            'service_worker_url' => FSS_PLUGIN_URL . 'assets/js/sw.js'
        ));
    }
    
    public function handle_service_worker() {
        if (isset($_GET['fss_sw']) && $_GET['fss_sw'] === '1') {
            header('Content-Type: application/javascript');
            header('Service-Worker-Allowed: /');
            
            readfile(FSS_PLUGIN_PATH . 'assets/js/sw.js');
            exit;
        }
    }
    
    public function register_push_subscription() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_push_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }
        
        $subscription = json_decode(stripslashes($_POST['subscription']), true);
        
        if (!$subscription || !isset($subscription['endpoint'])) {
            wp_send_json_error('Invalid subscription data');
        }
        
        // Store subscription in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_push_subscriptions';
        
        // Create table if it doesn't exist
        $this->create_subscriptions_table();
        
        $result = $wpdb->replace($table_name, array(
            'user_id' => $user_id,
            'endpoint' => $subscription['endpoint'],
            'p256dh_key' => $subscription['keys']['p256dh'],
            'auth_key' => $subscription['keys']['auth'],
            'created_at' => current_time('mysql'),
            'is_active' => 1
        ));
        
        if ($result) {
            wp_send_json_success('Subscription registered successfully');
        } else {
            wp_send_json_error('Failed to register subscription');
        }
    }
    
    private function create_subscriptions_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_push_subscriptions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            endpoint text NOT NULL,
            p256dh_key varchar(255) NOT NULL,
            auth_key varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY user_endpoint (user_id, endpoint(100))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function check_and_send_alerts($date, $summary_data) {
        // Check for large discrepancies
        self::check_discrepancy_alert($summary_data);
        
        // Check for missing daily summaries
        self::check_missing_summary_alert();
        
        // Check for end-of-day reminders
        self::check_end_of_day_reminder();
    }
    
    private static function check_discrepancy_alert($summary_data) {
        $threshold = get_option('fss_discrepancy_threshold', 10000); // Default ₦10,000
        
        $expected_cash = $summary_data['cash'] + $summary_data['old_cash'] + $summary_data['extras'] - $summary_data['expense'];
        $discrepancy = abs($expected_cash - $summary_data['cash_left']);
        
        if ($discrepancy > $threshold) {
            $title = 'Large Cash Discrepancy Alert';
            $message = "Discrepancy of ₦" . number_format($discrepancy, 2) . " detected for " . date('F d, Y');
            $icon = FSS_PLUGIN_URL . 'assets/images/alert-icon.png';
            
            // Send email
            self::send_notification_email($title, $message . "\n\nExpected: ₦" . number_format($expected_cash, 2) . "\nReported: ₦" . number_format($summary_data['cash_left'], 2));
            
            // Send push notification
            self::send_push_notification($title, $message, $icon, array(
                'type' => 'discrepancy_alert',
                'discrepancy' => $discrepancy,
                'expected_cash' => $expected_cash,
                'reported_cash' => $summary_data['cash_left']
            ));
        }
    }
    
    private static function check_missing_summary_alert() {
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $hour = $lagos_time->format('H');
        
        // Send reminder at 6 PM if no summary submitted
        if ($hour == 18) {
            $today = $lagos_time->format('Y-m-d');
            $summary = FSS_Database::get_daily_summary($today);
            
            if (!$summary) {
                $title = 'Daily Summary Reminder';
                $message = "No financial summary submitted for today. Please complete before end of business.";
                $icon = FSS_PLUGIN_URL . 'assets/images/reminder-icon.png';
                
                self::send_notification_email($title, $message);
                self::send_push_notification($title, $message, $icon, array(
                    'type' => 'missing_summary',
                    'date' => $today
                ));
            }
        }
    }
    
    private static function check_end_of_day_reminder() {
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $hour = $lagos_time->format('H');
        
        // Send end-of-day reminder at 8 PM
        if ($hour == 20) {
            $today = $lagos_time->format('Y-m-d');
            
            global $wpdb;
            $reconciliation_table = $wpdb->prefix . 'fss_reconciliations';
            
            $reconciliation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $reconciliation_table WHERE date = %s",
                $today
            ));
            
            if (!$reconciliation) {
                $title = 'End of Day Reconciliation';
                $message = "Please complete cash reconciliation before closing.";
                $icon = FSS_PLUGIN_URL . 'assets/images/reconcile-icon.png';
                
                self::send_notification_email($title, $message);
                self::send_push_notification($title, $message, $icon, array(
                    'type' => 'reconciliation_reminder',
                    'date' => $today
                ));
            }
        }
    }
    
    public static function send_push_notification($title, $message, $icon = '', $data = array()) {
        // Only send to admin users or users with manage_options capability
        $admin_users = get_users(array('role' => 'administrator'));
        
        foreach ($admin_users as $user) {
            self::send_push_to_user($user->ID, $title, $message, $icon, $data);
        }
        
        // Also send to current user if they have submitted something
        $current_user_id = get_current_user_id();
        if ($current_user_id && !in_array($current_user_id, wp_list_pluck($admin_users, 'ID'))) {
            self::send_push_to_user($current_user_id, $title, $message, $icon, $data);
        }
    }
    
    private static function send_push_to_user($user_id, $title, $message, $icon = '', $data = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_push_subscriptions';
        
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
        
        if (empty($subscriptions)) {
            return false;
        }
        
        $vapid_public_key = get_option('fss_vapid_public_key');
        $vapid_private_key = get_option('fss_vapid_private_key');
        
        if (!$vapid_public_key || !$vapid_private_key) {
            // Generate VAPID keys if they don't exist
            self::generate_vapid_keys();
            $vapid_public_key = get_option('fss_vapid_public_key');
            $vapid_private_key = get_option('fss_vapid_private_key');
        }
        
        $payload = json_encode(array(
            'title' => $title,
            'body' => $message,
            'icon' => $icon ?: FSS_PLUGIN_URL . 'assets/images/default-icon.png',
            'badge' => FSS_PLUGIN_URL . 'assets/images/badge-icon.png',
            'data' => array_merge($data, array(
                'url' => home_url(),
                'timestamp' => time()
            )),
            'actions' => array(
                array(
                    'action' => 'view',
                    'title' => 'View Details'
                ),
                array(
                    'action' => 'dismiss',
                    'title' => 'Dismiss'
                )
            ),
            'requireInteraction' => true,
            'vibrate' => array(200, 100, 200)
        ));
        
        foreach ($subscriptions as $subscription) {
            self::send_web_push($subscription, $payload, $vapid_public_key, $vapid_private_key);
        }
    }
    
    private static function send_web_push($subscription, $payload, $vapid_public_key, $vapid_private_key) {
        // Use Web Push library or implement the Web Push protocol
        // For this example, I'll provide a simplified implementation
        
        $endpoint = $subscription->endpoint;
        $p256dh = $subscription->p256dh_key;
        $auth = $subscription->auth_key;
        
        // You would typically use a library like web-push-php
        // For demonstration, here's a basic implementation
        
        $headers = array(
            'Content-Type' => 'application/octet-stream',
            'TTL' => '2419200', // 4 weeks
            'Urgency' => 'high'
        );
        
        // Add VAPID headers
        $headers['Authorization'] = 'vapid t=' . self::generate_jwt($vapid_private_key, $endpoint) . ', k=' . $vapid_public_key;
        
        // Encrypt payload
        $encrypted_payload = self::encrypt_payload($payload, $p256dh, $auth);
        
        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => $encrypted_payload,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Push notification failed: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code >= 400) {
            // Handle errors - subscription might be invalid
            if ($response_code == 410 || $response_code == 404) {
                // Remove invalid subscription
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'fss_push_subscriptions',
                    array('is_active' => 0),
                    array('id' => $subscription->id)
                );
            }
            return false;
        }
        
        return true;
    }
    
    private static function generate_vapid_keys() {
        // Generate VAPID key pair (simplified - you'd use proper cryptographic functions)
        $private_key = base64_encode(random_bytes(32));
        $public_key = base64_encode(random_bytes(65));
        
        update_option('fss_vapid_private_key', $private_key);
        update_option('fss_vapid_public_key', $public_key);
    }
    
    private static function generate_jwt($private_key, $endpoint) {
        // Simplified JWT generation - use proper JWT library in production
        $header = json_encode(array('typ' => 'JWT', 'alg' => 'ES256'));
        $payload = json_encode(array(
            'aud' => parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST),
            'exp' => time() + (12 * 60 * 60), // 12 hours
            'sub' => 'mailto:' . get_option('admin_email')
        ));
        
        $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        
        return $header_encoded . '.' . $payload_encoded . '.signature';
    }
    
    private static function encrypt_payload($payload, $p256dh, $auth) {
        // Simplified encryption - use proper Web Push encryption in production
        return $payload; // This should be properly encrypted
    }
    
    private static function send_notification_email($subject, $message) {
        $admin_email = get_option('admin_email');
        $notification_emails = get_option('fss_notification_emails', array($admin_email));
        
        foreach ($notification_emails as $email) {
            wp_mail($email, '[Financial Summary] ' . $subject, $message);
        }
        
        // Also save as WordPress admin notice
        self::save_admin_notice($subject, $message);
    }
    
    private static function save_admin_notice($subject, $message) {
        $notices = get_option('fss_admin_notices', array());
        $notices[] = array(
            'subject' => $subject,
            'message' => $message,
            'time' => current_time('mysql'),
            'read' => false
        );
        
        // Keep only last 20 notices
        if (count($notices) > 20) {
            $notices = array_slice($notices, -20);
        }
        
        update_option('fss_admin_notices', $notices);
    }
    
    public static function get_unread_notices() {
        $notices = get_option('fss_admin_notices', array());
        return array_filter($notices, function($notice) {
            return !$notice['read'];
        });
    }
    
    public static function mark_notice_read($index) {
        $notices = get_option('fss_admin_notices', array());
        if (isset($notices[$index])) {
            $notices[$index]['read'] = true;
            update_option('fss_admin_notices', $notices);
        }
    }
    
    public function send_test_notification() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_push_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = get_current_user_id();
        $title = 'Test Notification';
        $message = 'This is a test push notification from Financial Summary System';
        $icon = FSS_PLUGIN_URL . 'assets/images/test-icon.png';
        
        $result = self::send_push_to_user($user_id, $title, $message, $icon, array('type' => 'test'));
        
        if ($result) {
            wp_send_json_success('Test notification sent');
        } else {
            wp_send_json_error('Failed to send test notification');
        }
    }
}