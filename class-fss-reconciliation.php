<?php

class FSS_Reconciliation {
    
    public function __construct() {
        add_action('wp_ajax_fss_start_reconciliation', array($this, 'start_reconciliation'));
        add_action('wp_ajax_fss_save_reconciliation', array($this, 'save_reconciliation'));
        add_action('wp_ajax_fss_get_reconciliation_data', array($this, 'get_reconciliation_data'));
        add_action('wp_ajax_fss_approve_reconciliation', array($this, 'approve_reconciliation'));
    }
    
    public function start_reconciliation() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $date = sanitize_text_field($_POST['date'] ?? $lagos_time->format('Y-m-d'));
        
        $summary = FSS_Database::get_daily_summary($date);
        
        if (!$summary) {
            wp_send_json_error(array('message' => 'No summary found for this date'));
        }
        
        $reconciliation_data = array(
            'date' => $date,
            'expected_cash' => $summary->cash_left,
            'summary_data' => $summary,
            'discrepancy_threshold' => get_option('fss_discrepancy_threshold', 1000)
        );
        
        wp_send_json_success($reconciliation_data);
    }
    
    public function save_reconciliation() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $expected_cash = floatval($_POST['expected_cash']);
        $actual_cash = floatval($_POST['actual_cash']);
        $discrepancy = $actual_cash - $expected_cash;
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_reconciliations';
        
        $result = $wpdb->insert($table_name, array(
            'date' => $date,
            'expected_cash' => $expected_cash,
            'actual_cash' => $actual_cash,
            'discrepancy' => $discrepancy,
            'notes' => $notes,
            'status' => abs($discrepancy) > get_option('fss_discrepancy_threshold', 1000) ? 'pending_approval' : 'completed',
            'reconciled_by' => wp_get_current_user()->display_name,
            'reconciled_at' => current_time('mysql')
        ));
        
        if ($result) {
            // Send notification if large discrepancy
            if (abs($discrepancy) > get_option('fss_discrepancy_threshold', 1000)) {
                FSS_Notifications::send_push_notification(
                    'Large Discrepancy Detected',
                    'Reconciliation shows discrepancy of ₦' . number_format(abs($discrepancy), 2),
                    FSS_PLUGIN_URL . 'assets/images/alert-icon.png',
                    array(
                        'type' => 'large_discrepancy',
                        'discrepancy' => $discrepancy,
                        'date' => $date
                    )
                );
            }
            
            wp_send_json_success(array(
                'message' => 'Reconciliation saved successfully',
                'discrepancy' => $discrepancy,
                'status' => abs($discrepancy) > get_option('fss_discrepancy_threshold', 1000) ? 'pending_approval' : 'completed'
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save reconciliation'));
        }
    }
    
    public function get_reconciliation_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_reconciliations';
        
        $reconciliation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE date = %s",
            $date
        ));
        
        if ($reconciliation) {
            wp_send_json_success($reconciliation);
        } else {
            wp_send_json_error(array('message' => 'No reconciliation found'));
        }
    }
    
    public function approve_reconciliation() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $reconciliation_id = intval($_POST['reconciliation_id']);
        $approval_notes = sanitize_textarea_field($_POST['approval_notes'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_reconciliations';
        
        $result = $wpdb->update($table_name, array(
            'status' => 'approved',
            'approval_notes' => $approval_notes,
            'approved_by' => wp_get_current_user()->display_name,
            'approved_at' => current_time('mysql')
        ), array('id' => $reconciliation_id));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Reconciliation approved'));
        } else {
            wp_send_json_error(array('message' => 'Failed to approve reconciliation'));
        }
    }
    
    public static function get_pending_reconciliations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_reconciliations';
        
        return $wpdb->get_results("
            SELECT * FROM $table_name 
            WHERE status = 'pending_approval' 
            ORDER BY created_at DESC
        ");
    }
    
    public static function get_reconciliation_summary($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_reconciliations';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_reconciliations,
                SUM(ABS(discrepancy)) as total_discrepancy,
                AVG(ABS(discrepancy)) as avg_discrepancy,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'pending_approval' THEN 1 END) as pending_count
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
    }
}