<?php
/**
 * Direct Fix for wp_tof_orders - No database changes needed
 */

// Hook into WordPress after plugins are loaded
add_action('plugins_loaded', function() {
    // Override the get_orders_data_for_date method
    add_filter('fss_override_orders_data', '__return_true');
}, 20);

// Add our custom handler
add_action('init', function() {
    if (class_exists('FSS_Database')) {
        // Remove the existing method behavior and add our custom one
        add_action('wp_ajax_fss_get_live_orders', 'fss_custom_get_live_orders', 5);
        add_action('wp_ajax_nopriv_fss_get_live_orders', 'fss_custom_get_live_orders', 5);
    }
});

/**
 * Custom function to get orders from wp_tof_orders
 */
function fss_get_tof_orders_direct($date) {
    global $wpdb;
    
    $tof_table = $wpdb->prefix . 'tof_orders';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$tof_table'") != $tof_table) {
        return array(
            'total_sales' => 0,
            'transfer_card' => 0,
            'cash' => 0,
            'delivery' => 0,
            'order_count' => 0,
            'data_quality' => 'no_table'
        );
    }
    
    try {
        // Query your specific table structure
        $results = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as order_count,
                COALESCE(SUM(transfer_amount), 0) as transfer_total,
                COALESCE(SUM(cash_amount), 0) as cash_total,
                COALESCE(SUM(delivery_fee), 0) as delivery_total,
                COALESCE(SUM(grand_total), 0) as total_sales,
                AVG(grand_total) as avg_order
            FROM $tof_table 
            WHERE DATE(order_date) = %s
            AND grand_total > 0
        ", $date));
        
        if ($results) {
            return array(
                'total_sales' => floatval($results->total_sales),
                'transfer_card' => floatval($results->transfer_total),
                'cash' => floatval($results->cash_total),
                'delivery' => floatval($results->delivery_total),
                'order_count' => intval($results->order_count),
                'avg_order_value' => floatval($results->avg_order),
                'data_quality' => $results->order_count > 5 ? 'good' : 'fair',
                'source_table' => $tof_table
            );
        }
    } catch (Exception $e) {
        error_log("TOF Orders Error: " . $e->getMessage());
    }
    
    return array(
        'total_sales' => 0,
        'transfer_card' => 0,
        'cash' => 0,
        'delivery' => 0,
        'order_count' => 0,
        'data_quality' => 'error'
    );
}

/**
 * Override the AJAX handler
 */
function fss_custom_get_live_orders() {
    // Only run our version
    remove_action('wp_ajax_fss_get_live_orders', array('FSS_Frontend', 'get_live_orders'));
    remove_action('wp_ajax_nopriv_fss_get_live_orders', array('FSS_Frontend', 'get_live_orders'));
    
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'fss_live_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
    
    // Get fresh orders data using our direct method
    $orders_data = fss_get_tof_orders_direct($date);
    
    // Calculate additional metrics
    $avg_order = $orders_data['order_count'] > 0 ? $orders_data['total_sales'] / $orders_data['order_count'] : 0;
    $cash_percentage = $orders_data['total_sales'] > 0 ? ($orders_data['cash'] / $orders_data['total_sales']) * 100 : 0;
    $card_percentage = $orders_data['total_sales'] > 0 ? ($orders_data['transfer_card'] / $orders_data['total_sales']) * 100 : 0;
    
    wp_send_json_success(array(
        'orders_data' => $orders_data,
        'integration_status' => array(
            'connected' => $orders_data['total_sales'] > 0 || $orders_data['order_count'] > 0,
            'table_name' => 'wp_tof_orders',
            'data_quality' => $orders_data['data_quality']
        ),
        'metrics' => array(
            'avg_order' => $avg_order,
            'cash_percentage' => $cash_percentage,
            'card_percentage' => $card_percentage
        ),
        'timestamp' => date('H:i:s'),
        'formatted' => array(
            'total_sales' => '₦' . number_format($orders_data['total_sales'], 0),
            'transfer_card' => '₦' . number_format($orders_data['transfer_card'], 0),
            'cash' => '₦' . number_format($orders_data['cash'], 0),
            'delivery' => '₦' . number_format($orders_data['delivery'], 0),
            'avg_order' => '₦' . number_format($avg_order, 0),
            'order_count' => number_format($orders_data['order_count'])
        )
    ));
}

/**
 * Also override the database method directly
 */
add_filter('pre_transient_fss_orders_' . date('Y-m-d'), function($value) {
    if ($value === false) {
        $today_data = fss_get_tof_orders_direct(date('Y-m-d'));
        set_transient('fss_orders_' . date('Y-m-d'), $today_data, 300); // Cache for 5 minutes
        return $today_data;
    }
    return $value;
});

// Override the main database method
add_action('wp_loaded', function() {
    if (class_exists('FSS_Database')) {
        // Hook into the method call
        add_filter('fss_orders_data_override', function($default, $date) {
            return fss_get_tof_orders_direct($date);
        }, 10, 2);
    }
});