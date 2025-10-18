<?php
/**
 * Specific Fix for wp_tof_orders Table Integration
 */

class FSS_TOF_Orders_Fix {
    
    public function __construct() {
        add_action('wp_ajax_fss_fix_tof_connection', array($this, 'fix_tof_connection'));
        add_action('wp_ajax_fss_test_tof_today', array($this, 'test_tof_today'));
        
        // Override the get_orders_data_for_date method
        add_filter('fss_get_orders_data', array($this, 'get_tof_orders_data'), 10, 2);
    }
    
    /**
     * Get orders data specifically for wp_tof_orders table
     */
    public function get_tof_orders_data($default_data, $date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tof_orders';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $default_data;
        }
        
        try {
            // Query specifically designed for your table structure
            $results = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as order_count,
                    SUM(COALESCE(transfer_amount, 0)) as transfer_total,
                    SUM(COALESCE(cash_amount, 0)) as cash_total,
                    SUM(COALESCE(delivery_fee, 0)) as delivery_total,
                    SUM(COALESCE(grand_total, total_amount, 0)) as total_sales,
                    AVG(COALESCE(grand_total, total_amount, 0)) as avg_order,
                    COUNT(CASE WHEN payment_confirmed = 1 THEN 1 END) as confirmed_orders
                FROM $table_name 
                WHERE DATE(order_date) = %s
                AND COALESCE(grand_total, total_amount, 0) > 0
            ", $date));
            
            if ($results && $results->total_sales > 0) {
                return array(
                    'total_sales' => floatval($results->total_sales),
                    'transfer_card' => floatval($results->transfer_total),
                    'cash' => floatval($results->cash_total),
                    'delivery' => floatval($results->delivery_total),
                    'order_count' => intval($results->order_count),
                    'avg_order_value' => floatval($results->avg_order),
                    'confirmed_orders' => intval($results->confirmed_orders),
                    'data_quality' => $this->assess_data_quality($results),
                    'source_table' => $table_name
                );
            }
            
        } catch (Exception $e) {
            error_log("FSS TOF Orders Error: " . $e->getMessage());
        }
        
        return $default_data;
    }
    
    private function assess_data_quality($results) {
        $score = 0;
        
        if ($results->order_count > 10) $score += 30;
        elseif ($results->order_count > 5) $score += 20;
        elseif ($results->order_count > 0) $score += 10;
        
        if ($results->avg_order > 2000) $score += 30;
        elseif ($results->avg_order > 1000) $score += 20;
        elseif ($results->avg_order > 500) $score += 10;
        
        if ($results->confirmed_orders == $results->order_count) $score += 20;
        elseif ($results->confirmed_orders > 0) $score += 10;
        
        if ($score >= 70) return 'excellent';
        if ($score >= 50) return 'good';
        if ($score >= 30) return 'fair';
        return 'poor';
    }
    
    public function fix_tof_connection() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'fss_tof_fix_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'tof_orders';
            
            // Verify table exists and has data
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error('wp_tof_orders table not found');
            }
            
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            if ($row_count == 0) {
                wp_send_json_error('wp_tof_orders table is empty');
            }
            
            // Update table mapping
            $mapping_table = $wpdb->prefix . 'fss_table_mappings';
            
            // Clear existing mappings
            $wpdb->query("UPDATE $mapping_table SET is_active = 0 WHERE is_active = 1");
            
            // Create new mapping for wp_tof_orders
            $mapping_data = array(
                'table_name' => $table_name,
                'amount_column' => 'grand_total',
                'date_column' => 'order_date',
                'payment_column' => 'payment_method',
                'delivery_column' => 'delivery_fee',
                'cash_column' => 'cash_amount',
                'transfer_column' => 'transfer_amount',
                'confidence_score' => 95,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            $result = $wpdb->replace($mapping_table, $mapping_data);
            
            if ($result) {
                // Set session variable
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['selected_order_table'] = $table_name;
                
                // Clear all caches
                wp_cache_flush();
                
                // Test the connection
                $today = date('Y-m-d');
                $test_data = $this->get_tof_orders_data(array(), $today);
                
                wp_send_json_success(array(
                    'message' => 'Successfully connected to wp_tof_orders table!',
                    'table_name' => $table_name,
                    'total_records' => $row_count,
                    'test_data' => $test_data
                ));
            } else {
                wp_send_json_error('Failed to save table mapping');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Connection fix failed: ' . $e->getMessage());
        }
    }
    
    public function test_tof_today() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'fss_tof_fix_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $today = date('Y-m-d');
        $orders_data = $this->get_tof_orders_data(array(), $today);
        
        if ($orders_data['total_sales'] > 0) {
            wp_send_json_success(array(
                'message' => 'Connection test successful!',
                'data' => $orders_data,
                'formatted' => array(
                    'total_sales' => '₦' . number_format($orders_data['total_sales'], 2),
                    'transfer_card' => '₦' . number_format($orders_data['transfer_card'], 2),
                    'cash' => '₦' . number_format($orders_data['cash'], 2),
                    'delivery' => '₦' . number_format($orders_data['delivery'], 2),
                    'order_count' => number_format($orders_data['order_count'])
                )
            ));
        } else {
            wp_send_json_error('No orders found for today in wp_tof_orders table');
        }
    }
}