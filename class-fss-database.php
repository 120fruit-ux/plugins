<?php
/**
 * Financial Summary System - Database Class
 * Handles all database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class FSS_Database {
    
    const DAILY_SUMMARIES_TABLE = 'fss_daily_summaries';
    const HISTORY_TABLE = 'fss_history';
    const RECONCILIATIONS_TABLE = 'fss_reconciliations';
    
    /**
     * Initialize database tables
     */
    public static function init() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Daily Summaries Table (with market card field)
        $summaries_table = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        $sql_summaries = "CREATE TABLE $summaries_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            total_sales decimal(10,2) DEFAULT 0.00,
            transfer_card decimal(10,2) DEFAULT 0.00,
            cash decimal(10,2) DEFAULT 0.00,
            delivery decimal(10,2) DEFAULT 0.00,
            extras decimal(10,2) DEFAULT 0.00,
            extras_remark text,
            expense decimal(10,2) DEFAULT 0.00,
            expense_remark text,
            old_cash decimal(10,2) DEFAULT 0.00,
            cash_left decimal(10,2) DEFAULT 0.00,
            cash_left_market_card decimal(10,2) DEFAULT 0.00,
            created_by varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date (date),
            KEY created_by (created_by)
        ) $charset_collate;";
        dbDelta($sql_summaries);
        
        // History Table
        $history_table = $wpdb->prefix . self::HISTORY_TABLE;
        $sql_history = "CREATE TABLE $history_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            extras decimal(10,2) DEFAULT 0.00,
            extras_remark text,
            expense decimal(10,2) DEFAULT 0.00,
            expense_remark text,
            created_by varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date (date),
            KEY created_by (created_by)
        ) $charset_collate;";
        dbDelta($sql_history);
        
        // Reconciliations Table
        $reconciliations_table = $wpdb->prefix . self::RECONCILIATIONS_TABLE;
        $sql_reconciliations = "CREATE TABLE $reconciliations_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            expected_cash decimal(10,2) DEFAULT 0.00,
            actual_cash decimal(10,2) DEFAULT 0.00,
            discrepancy decimal(10,2) DEFAULT 0.00,
            notes text,
            status varchar(50) DEFAULT 'pending',
            reconciled_by varchar(100),
            reconciled_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date (date)
        ) $charset_collate;";
        dbDelta($sql_reconciliations);
    }
    
    /**
     * Get integration status
     */
    public static function get_integration_status() {
        global $wpdb;
        
        // Check if WooCommerce is active
        $woo_active = class_exists('WooCommerce');
        
        // Check for custom orders tables
        $tables = array(
            'wp_wc_orders',
            'wc_orders',
            'wp_orders',
            'orders'
        );
        
        $found_table = null;
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $check = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            if ($check) {
                $found_table = $full_table_name;
                break;
            }
        }
        
        // Also check for standard posts table
        if (!$found_table) {
            $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'");
            if ($post_count > 0) {
                $found_table = $wpdb->posts;
            }
        }
        
        return array(
            'woocommerce_active' => $woo_active,
            'table_name' => $found_table,
            'integration_type' => $found_table ? 'auto' : 'manual'
        );
    }
    
    /**
     * Get orders data for a specific date (FIXED with proper timezone)
     */
    public static function get_orders_data_for_date($date) {
        global $wpdb;
        
        $integration = self::get_integration_status();
        
        // Default values
        $default_data = array(
            'total_sales' => 0.00,
            'transfer_card' => 0.00,
            'cash' => 0.00,
            'delivery' => 0.00,
            'order_count' => 0,
            'avg_order_value' => 0.00
        );
        
        if (!$integration['table_name']) {
            return $default_data;
        }
        
        // Convert date to Lagos timezone boundaries
        try {
            $lagos_tz = new DateTimeZone('Africa/Lagos');
            $start_dt = new DateTime($date . ' 00:00:00', $lagos_tz);
            $end_dt = new DateTime($date . ' 23:59:59', $lagos_tz);
            
            // Convert to UTC for database query
            $utc_tz = new DateTimeZone('UTC');
            $start_dt->setTimezone($utc_tz);
            $end_dt->setTimezone($utc_tz);
            
            $start_time = $start_dt->format('Y-m-d H:i:s');
            $end_time = $end_dt->format('Y-m-d H:i:s');
            
        } catch (Exception $e) {
            error_log('FSS Date conversion error: ' . $e->getMessage());
            return $default_data;
        }
        
        // Check if using WooCommerce custom orders table
        if (strpos($integration['table_name'], 'wc_orders') !== false) {
            return self::get_wc_orders_data($start_time, $end_time, $integration['table_name']);
        }
        
        // Use WordPress posts table (legacy WooCommerce)
        return self::get_posts_orders_data($start_time, $end_time);
    }
    
    /**
     * Get data from WooCommerce custom orders table
     */
    private static function get_wc_orders_data($start_time, $end_time, $table_name) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT 
                COUNT(*) as order_count,
                SUM(total_amount) as total_sales,
                SUM(CASE WHEN payment_method IN ('bacs', 'cheque', 'stripe', 'paypal', 'card') THEN total_amount ELSE 0 END) as transfer_card,
                SUM(CASE WHEN payment_method = 'cod' THEN total_amount ELSE 0 END) as cash
            FROM $table_name
            WHERE status IN ('wc-completed', 'wc-processing', 'completed', 'processing')
            AND date_created_gmt BETWEEN %s AND %s
        ", $start_time, $end_time);
        
        $result = $wpdb->get_row($query);
        
        if (!$result) {
            return array(
                'total_sales' => 0.00,
                'transfer_card' => 0.00,
                'cash' => 0.00,
                'delivery' => 0.00,
                'order_count' => 0,
                'avg_order_value' => 0.00
            );
        }
        
        $total_sales = floatval($result->total_sales);
        $order_count = intval($result->order_count);
        
        return array(
            'total_sales' => $total_sales,
            'transfer_card' => floatval($result->transfer_card),
            'cash' => floatval($result->cash),
            'delivery' => 0.00, // Can be enhanced with shipping data
            'order_count' => $order_count,
            'avg_order_value' => $order_count > 0 ? ($total_sales / $order_count) : 0.00
        );
    }
    
    /**
     * Get data from WordPress posts table (legacy WooCommerce)
     */
    private static function get_posts_orders_data($start_time, $end_time) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT 
                COUNT(DISTINCT p.ID) as order_count,
                SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as total_sales
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date_gmt BETWEEN %s AND %s
        ", $start_time, $end_time);
        
        $result = $wpdb->get_row($query);
        
        if (!$result) {
            return array(
                'total_sales' => 0.00,
                'transfer_card' => 0.00,
                'cash' => 0.00,
                'delivery' => 0.00,
                'order_count' => 0,
                'avg_order_value' => 0.00
            );
        }
        
        $total_sales = floatval($result->total_sales);
        $order_count = intval($result->order_count);
        
        // Get payment method breakdown
        $payment_query = $wpdb->prepare("
            SELECT 
                pm_payment.meta_value as payment_method,
                SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) as amount
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            INNER JOIN {$wpdb->postmeta} pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_payment_method'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date_gmt BETWEEN %s AND %s
            GROUP BY pm_payment.meta_value
        ", $start_time, $end_time);
        
        $payment_results = $wpdb->get_results($payment_query);
        
        $transfer_card = 0.00;
        $cash = 0.00;
        
        foreach ($payment_results as $payment) {
            if (in_array($payment->payment_method, array('cod', 'cash'))) {
                $cash += floatval($payment->amount);
            } else {
                $transfer_card += floatval($payment->amount);
            }
        }
        
        return array(
            'total_sales' => $total_sales,
            'transfer_card' => $transfer_card,
            'cash' => $cash,
            'delivery' => 0.00,
            'order_count' => $order_count,
            'avg_order_value' => $order_count > 0 ? ($total_sales / $order_count) : 0.00
        );
    }
    
    /**
     * Create or update daily summary (FIXED with market card calculation)
     */
    public static function create_or_update_daily_summary($date, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        
        // Get current user
        $current_user = wp_get_current_user();
        $created_by = $current_user->display_name ?: $current_user->user_login;
        
        // Check if record exists
        $existing = self::get_daily_summary($date);
        
        // Get orders data
        $orders_data = self::get_orders_data_for_date($date);
        
        // Get old cash
        $old_cash = isset($data['old_cash']) ? floatval($data['old_cash']) : 0;
        if (!isset($data['old_cash']) && !$existing) {
            $old_cash = floatval(get_option('fss_old_cash_' . $date, 0));
        } elseif ($existing) {
            $old_cash = floatval($existing->old_cash);
        }
        
        // Handle cumulative extras and expense
        $new_extras = 0;
        $new_extras_remark = '';
        $new_expense = 0;
        $new_expense_remark = '';
        
        if (isset($data['submission_type']) && $data['submission_type'] === 'manual_entry_delta') {
            // This is a delta submission
            $delta_extras = floatval($data['extras'] ?? 0);
            $delta_expense = floatval($data['expense'] ?? 0);
            $delta_extras_remark = sanitize_textarea_field($data['extras_remark'] ?? '');
            $delta_expense_remark = sanitize_textarea_field($data['expense_remark'] ?? '');
            
            // Add to history table
            if ($delta_extras > 0 || $delta_expense > 0 || $delta_extras_remark || $delta_expense_remark) {
                self::add_history_entry($date, array(
                    'extras' => $delta_extras,
                    'extras_remark' => $delta_extras_remark,
                    'expense' => $delta_expense,
                    'expense_remark' => $delta_expense_remark,
                    'created_by' => $created_by
                ));
            }
            
            // Calculate new cumulative values
            $existing_extras = $existing ? floatval($existing->extras) : 0;
            $existing_expense = $existing ? floatval($existing->expense) : 0;
            
            $new_extras = $existing_extras + $delta_extras;
            $new_expense = $existing_expense + $delta_expense;
            
            // Combine remarks
            $existing_extras_remark = $existing ? $existing->extras_remark : '';
            $existing_expense_remark = $existing ? $existing->expense_remark : '';
            
            if ($delta_extras_remark) {
                $new_extras_remark = $existing_extras_remark 
                    ? $existing_extras_remark . "\n" . $delta_extras_remark 
                    : $delta_extras_remark;
            } else {
                $new_extras_remark = $existing_extras_remark;
            }
            
            if ($delta_expense_remark) {
                $new_expense_remark = $existing_expense_remark 
                    ? $existing_expense_remark . "\n" . $delta_expense_remark 
                    : $delta_expense_remark;
            } else {
                $new_expense_remark = $existing_expense_remark;
            }
            
        } else {
            // Direct value submission
            $new_extras = isset($data['extras']) ? floatval($data['extras']) : ($existing ? $existing->extras : 0);
            $new_extras_remark = isset($data['extras_remark']) ? sanitize_textarea_field($data['extras_remark']) : ($existing ? $existing->extras_remark : '');
            $new_expense = isset($data['expense']) ? floatval($data['expense']) : ($existing ? $existing->expense : 0);
            $new_expense_remark = isset($data['expense_remark']) ? sanitize_textarea_field($data['expense_remark']) : ($existing ? $existing->expense_remark : '');
        }
        
        // Get market card value (NEW)
        $cash_left_market_card = isset($data['cash_left_market_card']) 
            ? floatval($data['cash_left_market_card']) 
            : ($existing ? floatval($existing->cash_left_market_card) : 0);
        
        // Ensure no negative values are used in calculations
        $cash_left_market_card = max(0, $cash_left_market_card);
        $old_cash = max(0, $old_cash);
        $new_extras = max(0, $new_extras);
        $new_expense = max(0, $new_expense);
        
        // Calculate cash left using the correct formula
        // Formula: cash_left = cash_sales + extras + old_cash + market_card - expenses
        if (isset($data['cash_left'])) {
            $cash_left = max(0, floatval($data['cash_left'])); // Ensure cash_left is not negative
        } else {
            $cash_left = ($orders_data['cash'] + $old_cash + $new_extras + $cash_left_market_card) - $new_expense;
            // Ensure cash left is not negative
            $cash_left = max(0, $cash_left);
        }
        
        // Prepare update data
        $update_data = array(
            'total_sales' => $orders_data['total_sales'],
            'transfer_card' => $orders_data['transfer_card'],
            'cash' => $orders_data['cash'],
            'delivery' => $orders_data['delivery'],
            'extras' => $new_extras,
            'extras_remark' => $new_extras_remark,
            'expense' => $new_expense,
            'expense_remark' => $new_expense_remark,
            'old_cash' => $old_cash,
            'cash_left' => $cash_left,
            'cash_left_market_card' => $cash_left_market_card, // ADD THIS
            'updated_at' => current_time('mysql')
        );
        
        $format = array('%f', '%f', '%f', '%f', '%f', '%s', '%f', '%s', '%f', '%f', '%f', '%s');
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                $update_data,
                array('date' => $date),
                $format,
                array('%s')
            );
            return $result !== false ? $existing->id : false;
        } else {
            // Insert new record
            $update_data['date'] = $date;
            $update_data['created_by'] = $created_by;
            $update_data['created_at'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $table_name,
                $update_data,
                array_merge(array('%s', '%s', '%s'), $format)
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Get daily summary
     */
    public static function get_daily_summary($date) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE date = %s",
            $date
        ));
    }
    
    /**
     * Add history entry
     */
    public static function add_history_entry($date, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::HISTORY_TABLE;
        
        $current_user = wp_get_current_user();
        $created_by = isset($data['created_by']) ? $data['created_by'] : ($current_user->display_name ?: $current_user->user_login);
        
        return $wpdb->insert(
            $table_name,
            array(
                'date' => $date,
                'extras' => floatval($data['extras'] ?? 0),
                'extras_remark' => sanitize_textarea_field($data['extras_remark'] ?? ''),
                'expense' => floatval($data['expense'] ?? 0),
                'expense_remark' => sanitize_textarea_field($data['expense_remark'] ?? ''),
                'created_by' => $created_by,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%f', '%s', '%f', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get history entries for a date
     */
    public static function get_history_entries($date) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::HISTORY_TABLE;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE date = %s ORDER BY created_at DESC",
            $date
        ));
    }
    
    /**
     * Get paginated summaries with filters
     */
    public static function get_paginated_summaries($page = 1, $per_page = 20, $filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "date >= %s";
            $where_values[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "date <= %s";
            $where_values[] = sanitize_text_field($filters['date_to']);
        }
        
        if (!empty($filters['created_by'])) {
            $where_clauses[] = "created_by = %s";
            $where_values[] = sanitize_text_field($filters['created_by']);
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table_name $where_sql";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total = $wpdb->get_var($count_query);
        
        // Calculate pagination
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get results
        $results_query = "SELECT * FROM $table_name $where_sql ORDER BY date DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $results = $wpdb->get_results($wpdb->prepare($results_query, $query_values));
        
        return array(
            'results' => $results,
            'total' => $total,
            'pages' => $total_pages,
            'current_page' => $page
        );
    }
    
    /**
     * Get all unique creators (for filters)
     */
    public static function get_all_creators() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        
        return $wpdb->get_col("SELECT DISTINCT created_by FROM $table_name WHERE created_by IS NOT NULL ORDER BY created_by ASC");
    }
    
    /**
     * Get analytics data for period
     */
    public static function get_analytics_data($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                date,
                total_sales,
                transfer_card,
                cash,
                delivery,
                extras,
                expense,
                cash_left,
                cash_left_market_card
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        ", $start_date, $end_date));
    }
    
    /**
     * Get reconciliation for date
     */
    public static function get_reconciliation($date) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::RECONCILIATIONS_TABLE;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE date = %s ORDER BY created_at DESC LIMIT 1",
            $date
        ));
    }
    
    /**
     * Clean up old data (optional maintenance)
     */
    public static function cleanup_old_data($days_to_keep = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days_to_keep} days"));
        
        $history_table = $wpdb->prefix . self::HISTORY_TABLE;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $history_table WHERE date < %s",
            $cutoff_date
        ));
        
        return true;
    }
    
    /**
     * Fix negative values in database - Admin utility function
     */
    public static function fix_negative_values() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        
        // Get all records with negative cash_left
        $negative_records = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE cash_left < 0 OR old_cash < 0 OR extras < 0 OR expense < 0 OR cash_left_market_card < 0 ORDER BY date ASC"
        );
        
        $fixed_count = 0;
        
        foreach ($negative_records as $record) {
            $date = $record->date;
            
            // Fix all negative values to 0
            $old_cash = max(0, floatval($record->old_cash));
            $extras = max(0, floatval($record->extras));
            $expense = max(0, floatval($record->expense));
            $cash_left_market_card = max(0, floatval($record->cash_left_market_card));
            $cash = max(0, floatval($record->cash));
            
            // Recalculate cash_left with correct formula
            $cash_left = ($cash + $old_cash + $extras + $cash_left_market_card) - $expense;
            $cash_left = max(0, $cash_left);
            
            // Update the record
            $wpdb->update(
                $table_name,
                array(
                    'old_cash' => $old_cash,
                    'extras' => $extras,
                    'expense' => $expense,
                    'cash_left_market_card' => $cash_left_market_card,
                    'cash_left' => $cash_left,
                    'updated_at' => current_time('mysql')
                ),
                array('date' => $date),
                array('%f', '%f', '%f', '%f', '%f', '%s'),
                array('%s')
            );
            
            // Update old_cash for next day
            $next_date = date('Y-m-d', strtotime($date . ' +1 day'));
            update_option('fss_old_cash_' . $next_date, $cash_left);
            
            $fixed_count++;
        }
        
        return array(
            'success' => true,
            'fixed_count' => $fixed_count,
            'message' => "Fixed {$fixed_count} records with negative values"
        );
    }
    
    /**
     * Backup data to JSON
     */
    public static function export_backup($start_date, $end_date) {
        global $wpdb;
        $summaries_table = $wpdb->prefix . self::DAILY_SUMMARIES_TABLE;
        $history_table = $wpdb->prefix . self::HISTORY_TABLE;
        
        $summaries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $summaries_table WHERE date BETWEEN %s AND %s ORDER BY date ASC",
            $start_date, $end_date
        ), ARRAY_A);
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $history_table WHERE date BETWEEN %s AND %s ORDER BY date ASC, created_at ASC",
            $start_date, $end_date
        ), ARRAY_A);
        
        return array(
            'export_date' => current_time('mysql'),
            'date_range' => array('start' => $start_date, 'end' => $end_date),
            'summaries' => $summaries,
            'history' => $history
        );
    }
}