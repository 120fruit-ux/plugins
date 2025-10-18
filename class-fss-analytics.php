<?php

class FSS_Analytics {
    
    public function __construct() {
        add_action('wp_ajax_fss_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_fss_get_performance_metrics', array($this, 'get_performance_metrics'));
        add_action('wp_ajax_fss_export_analytics', array($this, 'export_analytics'));
    }
    
    public function get_analytics_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        $period = intval($_POST['period'] ?? 30);
        $end_date = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $start_date = clone $end_date;
        $start_date->modify("-{$period} days");
        
        $analytics = array(
            'sales_data' => $this->get_sales_analytics($start_date->format('Y-m-d'), $end_date->format('Y-m-d')),
            'cash_flow' => $this->get_cash_flow_analytics($start_date->format('Y-m-d'), $end_date->format('Y-m-d')),
            'payment_methods' => $this->get_payment_method_analytics($start_date->format('Y-m-d'), $end_date->format('Y-m-d')),
            'expense_breakdown' => $this->get_expense_analytics($start_date->format('Y-m-d'), $end_date->format('Y-m-d')),
            'trends' => $this->get_trend_analytics($start_date->format('Y-m-d'), $end_date->format('Y-m-d'))
        );
        
        wp_send_json_success($analytics);
    }
    
    private function get_sales_analytics($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date,
                total_sales,
                transfer_card,
                cash,
                delivery
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        ", $start_date, $end_date));
        
        $dates = array();
        $sales = array();
        $transfer_card = array();
        $cash = array();
        $delivery = array();
        
        foreach ($results as $row) {
            $dates[] = date('M d', strtotime($row->date));
            $sales[] = floatval($row->total_sales);
            $transfer_card[] = floatval($row->transfer_card);
            $cash[] = floatval($row->cash);
            $delivery[] = floatval($row->delivery);
        }
        
        return array(
            'dates' => $dates,
            'total_sales' => $sales,
            'transfer_card' => $transfer_card,
            'cash' => $cash,
            'delivery' => $delivery
        );
    }
    
    private function get_cash_flow_analytics($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date,
                (cash + extras) as cash_in,
                expense as cash_out,
                cash_left
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        ", $start_date, $end_date));
        
        $dates = array();
        $cash_in = array();
        $cash_out = array();
        $net_flow = array();
        
        foreach ($results as $row) {
            $dates[] = date('M d', strtotime($row->date));
            $cash_in[] = floatval($row->cash_in);
            $cash_out[] = floatval($row->cash_out);
            $net_flow[] = floatval($row->cash_in) - floatval($row->cash_out);
        }
        
        return array(
            'dates' => $dates,
            'cash_in' => $cash_in,
            'cash_out' => $cash_out,
            'net_flow' => $net_flow
        );
    }
    
    private function get_payment_method_analytics($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        $results = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(transfer_card) as total_transfer_card,
                SUM(cash) as total_cash
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
        
        return array(
            'transfer_card' => floatval($results->total_transfer_card ?? 0),
            'cash' => floatval($results->total_cash ?? 0)
        );
    }
    
    private function get_expense_analytics($start_date, $end_date) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'fss_history_entries';
        
        // Get expense breakdown by remarks/categories
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                expense_remark,
                SUM(expense) as total_expense,
                COUNT(*) as frequency
            FROM $history_table 
            WHERE date BETWEEN %s AND %s 
            AND expense > 0
            AND expense_remark != ''
            GROUP BY expense_remark
            ORDER BY total_expense DESC
            LIMIT 10
        ", $start_date, $end_date));
        
        $categories = array();
        $amounts = array();
        $frequencies = array();
        
        foreach ($results as $row) {
            $categories[] = $row->expense_remark;
            $amounts[] = floatval($row->total_expense);
            $frequencies[] = intval($row->frequency);
        }
        
        return array(
            'categories' => $categories,
            'amounts' => $amounts,
            'frequencies' => $frequencies
        );
    }
    
    private function get_trend_analytics($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        // Calculate growth trends
        $current_period = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(total_sales) as avg_sales,
                SUM(total_sales) as total_sales,
                AVG(expense) as avg_expense,
                SUM(expense) as total_expense
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
        
        // Previous period for comparison
        $period_days = (new DateTime($end_date))->diff(new DateTime($start_date))->days;
        $prev_start = date('Y-m-d', strtotime($start_date . " -{$period_days} days"));
        $prev_end = date('Y-m-d', strtotime($start_date . " -1 day"));
        
        $previous_period = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(total_sales) as avg_sales,
                SUM(total_sales) as total_sales,
                AVG(expense) as avg_expense,
                SUM(expense) as total_expense
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
        ", $prev_start, $prev_end));
        
        // Calculate growth percentages
        $sales_growth = $previous_period && $previous_period->total_sales > 0 
            ? (($current_period->total_sales - $previous_period->total_sales) / $previous_period->total_sales) * 100 
            : 0;
            
        $expense_growth = $previous_period && $previous_period->total_expense > 0 
            ? (($current_period->total_expense - $previous_period->total_expense) / $previous_period->total_expense) * 100 
            : 0;
        
        return array(
            'sales_growth' => round($sales_growth, 2),
            'expense_growth' => round($expense_growth, 2),
            'avg_daily_sales' => floatval($current_period->avg_sales ?? 0),
            'avg_daily_expense' => floatval($current_period->avg_expense ?? 0),
            'total_sales' => floatval($current_period->total_sales ?? 0),
            'total_expense' => floatval($current_period->total_expense ?? 0)
        );
    }
    
    public function get_performance_metrics() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        $metrics = array(
            'today' => $this->get_today_metrics(),
            'week' => $this->get_week_metrics(),
            'month' => $this->get_month_metrics(),
            'reconciliation' => $this->get_reconciliation_metrics()
        );
        
        wp_send_json_success($metrics);
    }
    
    private function get_today_metrics() {
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $today = $lagos_time->format('Y-m-d');
        
        $summary = FSS_Database::get_daily_summary($today);
        $orders_data = FSS_Database::get_orders_data_for_date($today);
        
        return array(
            'sales' => ($summary->total_sales ?? 0) + $orders_data['total_sales'],
            'expense' => $summary->expense ?? 0,
            'cash_left' => $summary->cash_left ?? 0,
            'entries_count' => count(FSS_Database::get_history_entries($today))
        );
    }
    
    private function get_week_metrics() {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-6 days'));
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(total_sales) as sales,
                SUM(expense) as expense,
                AVG(cash_left) as avg_cash_left,
                COUNT(*) as days_count
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
    }
    
    private function get_month_metrics() {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-29 days'));
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(total_sales) as sales,
                SUM(expense) as expense,
                AVG(cash_left) as avg_cash_left,
                COUNT(*) as days_count
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
    }
    
    private function get_reconciliation_metrics() {
        global $wpdb;
        $reconciliation_table = $wpdb->prefix . 'fss_reconciliations';
        
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-29 days'));
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_reconciliations,
                SUM(ABS(discrepancy)) as total_discrepancy,
                AVG(ABS(discrepancy)) as avg_discrepancy,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
            FROM $reconciliation_table 
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
    }
    
    public function export_analytics() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $period = intval($_POST['period'] ?? 30);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$period} days"));
        
        if ($format === 'csv') {
            $this->export_analytics_csv($start_date, $end_date);
        } else {
            $this->export_analytics_pdf($start_date, $end_date);
        }
    }
    
    private function export_analytics_csv($start_date, $end_date) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="financial_analytics_' . $start_date . '_to_' . $end_date . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Analytics summary
        fputcsv($output, array('Financial Analytics Report'));
        fputcsv($output, array('Period: ' . $start_date . ' to ' . $end_date));
        fputcsv($output, array('Generated: ' . current_time('Y-m-d H:i:s')));
        fputcsv($output, array(''));
        
        // Daily breakdown
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        ", $start_date, $end_date));
        
        fputcsv($output, array('Date', 'Total Sales', 'Transfer/Card', 'Cash', 'Delivery', 'Extras', 'Expense', 'Cash Left'));
        
        foreach ($results as $row) {
            fputcsv($output, array(
                $row->date,
                number_format($row->total_sales, 2),
                number_format($row->transfer_card, 2),
                number_format($row->cash, 2),
                number_format($row->delivery, 2),
                number_format($row->extras, 2),
                number_format($row->expense, 2),
                number_format($row->cash_left, 2)
            ));
        }
        
        fclose($output);
        exit;
    }
    
    private function export_analytics_pdf($start_date, $end_date) {
        // Basic PDF export - in production, use a proper PDF library
        wp_send_json_error(array('message' => 'PDF export not implemented yet'));
    }
}