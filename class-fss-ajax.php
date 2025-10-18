<?php
/**
 * Financial Summary System - AJAX Handler
 * Handles all AJAX requests for the financial summary system
 */

if (!defined('ABSPATH')) {
    exit;
}

class FSS_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_fss_submit_summary', array($this, 'submit_summary'));
        add_action('wp_ajax_fss_get_history', array($this, 'get_history'));
        add_action('wp_ajax_fss_get_history_table', array($this, 'get_history_table'));
        add_action('wp_ajax_fss_get_analytics', array($this, 'get_analytics'));
        add_action('wp_ajax_fss_save_draft', array($this, 'save_draft'));
        add_action('wp_ajax_fss_show_reconciliation', array($this, 'show_reconciliation'));
        add_action('wp_ajax_fss_submit_reconciliation', array($this, 'submit_reconciliation'));
        add_action('wp_ajax_fss_export_day', array($this, 'export_day'));
        add_action('wp_ajax_fss_get_detailed_history', array($this, 'get_detailed_history'));
        add_action('wp_ajax_nopriv_fss_get_detailed_history', array($this, 'get_detailed_history'));
        add_action('wp_ajax_nopriv_fss_submit_summary', array($this, 'check_permissions'));
        add_action('wp_ajax_fss_edit_history_date', array($this, 'edit_history_date'));
        add_action('wp_ajax_fss_update_history_date', array($this, 'update_history_date'));
        add_action('wp_ajax_fss_delete_history_date', array($this, 'delete_history_date'));
        add_action('wp_ajax_fss_fix_negative_values', array($this, 'fix_negative_values'));
    }
    
    public function check_permissions() {
        wp_die('Unauthorized access', 'Error', array('response' => 403));
    }

    /**
     * Detailed history for modal (FIXED - Shows correct data from summary)
     */
    public function get_detailed_history() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_send_json_error('Security check failed');
        }
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
        }
        
        $date = sanitize_text_field($_POST['date']);
        $is_admin = current_user_can('manage_options');
        
        // Get the daily summary (this contains the actual stored data)
        $summary = FSS_Database::get_daily_summary($date);
        if (!$summary) {
            wp_send_json_error('No data found for this date');
        }
        
        // Get history entries
        $history_entries = FSS_Database::get_history_entries($date);
        
        // Get integration status
        $integration_status = $is_admin ? FSS_Database::get_integration_status() : null;
        
        // CRITICAL FIX: Cast summary object to array and ensure all fields exist
        $summary_array = array(
            'id' => isset($summary->id) ? $summary->id : 0,
            'date' => isset($summary->date) ? $summary->date : $date,
            'total_sales' => isset($summary->total_sales) ? floatval($summary->total_sales) : 0,
            'transfer_card' => isset($summary->transfer_card) ? floatval($summary->transfer_card) : 0,
            'cash' => isset($summary->cash) ? floatval($summary->cash) : 0,
            'delivery' => isset($summary->delivery) ? floatval($summary->delivery) : 0,
            'extras' => isset($summary->extras) ? floatval($summary->extras) : 0,
            'extras_remark' => isset($summary->extras_remark) ? $summary->extras_remark : '',
            'expense' => isset($summary->expense) ? floatval($summary->expense) : 0,
            'expense_remark' => isset($summary->expense_remark) ? $summary->expense_remark : '',
            'old_cash' => isset($summary->old_cash) ? floatval($summary->old_cash) : 0,
            'cash_left' => isset($summary->cash_left) ? floatval($summary->cash_left) : 0,
            'cash_left_market_card' => isset($summary->cash_left_market_card) ? floatval($summary->cash_left_market_card) : 0,
            'created_by' => isset($summary->created_by) ? $summary->created_by : '',
            'created_at' => isset($summary->created_at) ? $summary->created_at : '',
            'updated_at' => isset($summary->updated_at) ? $summary->updated_at : ''
        );
        
        // Use summary data as orders_data (the aggregated values from database)
        $orders_data = array(
            'total_sales' => $summary_array['total_sales'],
            'transfer_card' => $summary_array['transfer_card'],
            'cash' => $summary_array['cash'],
            'delivery' => $summary_array['delivery'],
            'order_count' => 0,
            'avg_order_value' => 0.00
        );
        
        // Optionally get fresh orders data for count/average
        $fresh_orders_data = FSS_Database::get_orders_data_for_date($date);
        if ($fresh_orders_data && !empty($fresh_orders_data)) {
            $orders_data['order_count'] = isset($fresh_orders_data['order_count']) ? intval($fresh_orders_data['order_count']) : 0;
            $orders_data['avg_order_value'] = isset($fresh_orders_data['avg_order_value']) ? floatval($fresh_orders_data['avg_order_value']) : 0;
        }
        
        // Format history entries properly
        $formatted_history = array();
        if (!empty($history_entries)) {
            foreach ($history_entries as $entry) {
                $formatted_history[] = array(
                    'id' => isset($entry->id) ? $entry->id : 0,
                    'date' => isset($entry->date) ? $entry->date : '',
                    'extras' => isset($entry->extras) ? floatval($entry->extras) : 0,
                    'extras_remark' => isset($entry->extras_remark) ? $entry->extras_remark : '',
                    'expense' => isset($entry->expense) ? floatval($entry->expense) : 0,
                    'expense_remark' => isset($entry->expense_remark) ? $entry->expense_remark : '',
                    'created_by' => isset($entry->created_by) ? $entry->created_by : '',
                    'created_at' => isset($entry->created_at) ? $entry->created_at : ''
                );
            }
        }
        
        $response_data = array(
            'summary' => $summary_array,
            'history_entries' => $formatted_history,
            'orders_data' => $orders_data,
            'is_admin' => $is_admin,
            'integration_status' => $integration_status,
            'formatted_date' => wp_date('F d, Y', strtotime($date), new DateTimeZone('Africa/Lagos'))
        );
        
        // Debug log (remove in production if needed)
        error_log('FSS Modal Data for ' . $date . ': Total Sales = ' . $summary_array['total_sales']);
        
        wp_send_json_success($response_data);
    }
    
    /**
     * SUBMIT SUMMARY (FIXED with Market Card)
     */
    public function submit_summary() {
        if (!wp_verify_nonce($_POST['fss_nonce'], 'fss_submit_nonce')) {
            wp_die('Security check failed');
        }
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please log in'));
        }
        
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $current_date = $lagos_time->format('Y-m-d');

        $delta_extras  = floatval($_POST['extras'] ?? 0);
        $delta_extras_remark  = sanitize_textarea_field($_POST['extras_remark'] ?? '');
        $delta_expense = floatval($_POST['expense'] ?? 0);
        $delta_expense_remark = sanitize_textarea_field($_POST['expense_remark'] ?? '');
        $market_card = floatval($_POST['cash_left_market_card'] ?? 0);

        if ($delta_extras == 0 && $delta_expense == 0 && $delta_extras_remark === '' && $delta_expense_remark === '') {
            wp_send_json_error(array('message' => 'Please enter some values before submitting'));
        }

        $old_cash = get_option('fss_old_cash_' . $current_date, 0);

        $summary_data = array(
            'extras' => $delta_extras,
            'extras_remark' => $delta_extras_remark,
            'expense' => $delta_expense,
            'expense_remark' => $delta_expense_remark,
            'old_cash' => $old_cash,
            'cash_left_market_card' => $market_card,
            'submission_type' => 'manual_entry_delta'
        );

        $summary_id = FSS_Database::create_or_update_daily_summary($current_date, $summary_data);

        if ($summary_id) {
            $updated_summary = FSS_Database::get_daily_summary($current_date);
            $tomorrow = date('Y-m-d', strtotime($current_date . ' +1 day'));
            update_option('fss_old_cash_' . $tomorrow, $updated_summary->cash_left);

            if (class_exists('FSS_Notifications')) {
                FSS_Notifications::check_and_send_alerts($current_date, array(
                    'extras_delta' => $delta_extras,
                    'expense_delta' => $delta_expense,
                    'cash' => $updated_summary->cash,
                    'cash_left' => $updated_summary->cash_left
                ));
            }

            wp_send_json_success(array(
                'message' => 'Summary submitted successfully',
                'updated_values' => array(
                    'total_sales'   => $updated_summary->total_sales,
                    'transfer_card' => $updated_summary->transfer_card,
                    'cash'          => $updated_summary->cash,
                    'delivery'      => $updated_summary->delivery,
                    'old_cash'      => $updated_summary->old_cash,
                    'cash_left'     => $updated_summary->cash_left,
                    'extras'        => $updated_summary->extras,
                    'expense'       => $updated_summary->expense,
                    'cash_left_market_card' => isset($updated_summary->cash_left_market_card) ? $updated_summary->cash_left_market_card : 0
                ),
                'deltas' => array(
                    'extras'  => $delta_extras,
                    'expense' => $delta_expense
                )
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save summary'));
        }
    }

    /**
     * Basic history summary
     */
    public function get_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        $date = sanitize_text_field($_POST['date']);
        $summary = FSS_Database::get_daily_summary($date);
        $history_entries = FSS_Database::get_history_entries($date);
        
        if (!$summary) {
            wp_send_json_error(array('message' => 'No data found for this date'));
        }

        ob_start(); ?>
        <div class="fss-history-details">
            <h3>Summary for <?php echo date('F d, Y', strtotime($date)); ?></h3>
            <div class="fss-summary-overview">
                <div class="fss-summary-item"><strong>Total Sales:</strong> ₦<?php echo number_format($summary->total_sales, 2); ?></div>
                <div class="fss-summary-item"><strong>Transfer/Card:</strong> ₦<?php echo number_format($summary->transfer_card, 2); ?></div>
                <div class="fss-summary-item"><strong>Cash:</strong> ₦<?php echo number_format($summary->cash, 2); ?></div>
                <div class="fss-summary-item"><strong>Delivery:</strong> ₦<?php echo number_format($summary->delivery, 2); ?></div>
                <div class="fss-summary-item"><strong>Total Extras:</strong> ₦<?php echo number_format($summary->extras, 2); ?></div>
                <div class="fss-summary-item"><strong>Total Expenses:</strong> ₦<?php echo number_format($summary->expense, 2); ?></div>
                <div class="fss-summary-item"><strong>Market Card:</strong> ₦<?php echo number_format(isset($summary->cash_left_market_card) ? $summary->cash_left_market_card : 0, 2); ?></div>
                <div class="fss-summary-item"><strong>Cash Left:</strong> ₦<?php echo number_format($summary->cash_left, 2); ?></div>
            </div>
            <?php if (!empty($history_entries)): ?>
                <h4>Individual Entries</h4>
                <div class="fss-history-entries">
                    <?php foreach ($history_entries as $entry): ?>
                        <div class="fss-history-entry">
                            <div class="fss-entry-header">
                                <strong><?php echo esc_html($entry->created_by); ?></strong>
                                <span class="fss-entry-time"><?php echo wp_date('H:i:s', strtotime($entry->created_at)); ?></span>
                            </div>
                            <div class="fss-entry-details">
                                <?php if ($entry->extras > 0): ?>
                                    <div>Extras: ₦<?php echo number_format($entry->extras, 2); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($entry->extras_remark)): ?>
                                    <div>Extras Remark: <?php echo esc_html($entry->extras_remark); ?></div>
                                <?php endif; ?>
                                <?php if ($entry->expense > 0): ?>
                                    <div>Expense: ₦<?php echo number_format($entry->expense, 2); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($entry->expense_remark)): ?>
                                    <div>Expense Remark: <?php echo esc_html($entry->expense_remark); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Paginated history table (FIXED with buttons)
     */
    public function get_history_table() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        if ($page < 1) {
            $page = 1;
        }
        
        $results = FSS_Database::get_paginated_summaries($page, 20, $filters);

        ob_start(); 
        ?>
        <table class="fss-history-table">
            <thead>
            <tr>
                <th>Date</th>
                <th>Total Sales</th>
                <th>Transfer/Card</th>
                <th>Cash</th>
                <th>Delivery</th>
                <th>Extras</th>
                <th>Expenses</th>
                <th>Market Card</th>
                <th>Cash Left</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($results['results'])): ?>
                <?php foreach ($results['results'] as $row): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($row->date)); ?></td>
                    <td>₦<?php echo number_format($row->total_sales, 2); ?></td>
                    <td>₦<?php echo number_format($row->transfer_card, 2); ?></td>
                    <td>₦<?php echo number_format($row->cash, 2); ?></td>
                    <td>₦<?php echo number_format($row->delivery, 2); ?></td>
                    <td>₦<?php echo number_format($row->extras, 2); ?></td>
                    <td>₦<?php echo number_format($row->expense, 2); ?></td>
                    <td>₦<?php echo number_format(isset($row->cash_left_market_card) ? $row->cash_left_market_card : 0, 2); ?></td>
                    <td>₦<?php echo number_format($row->cash_left, 2); ?></td>
                    <td><?php echo esc_html($row->created_by); ?></td>
                    <td>
                        <button type="button" class="fss-btn fss-btn-small view-details-btn" data-date="<?php echo esc_attr($row->date); ?>">
                            <span class="dashicons dashicons-visibility"></span> View
                        </button>
                        <?php if (current_user_can('manage_options')): ?>
                        <button type="button" class="fss-btn fss-btn-small fss-btn-edit edit-history-btn" data-date="<?php echo esc_attr($row->date); ?>" title="Edit History">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </button>
                        <button type="button" class="fss-btn fss-btn-small fss-btn-delete delete-history-btn" data-date="<?php echo esc_attr($row->date); ?>" title="Delete History">
                            <span class="dashicons dashicons-trash"></span> Delete
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 20px;">No records found</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
        $table_html = ob_get_clean();

        ob_start(); 
        ?>
        <div class="fss-pagination">
            <?php if ($page > 1): ?>
                <button type="button" class="fss-page-btn" data-page="<?php echo ($page - 1); ?>">← Previous</button>
            <?php endif; ?>
            
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($results['pages'], $page + 2);
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
                <button type="button" class="fss-page-btn <?php echo $i == $page ? 'active' : ''; ?>" data-page="<?php echo $i; ?>">
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            
            <?php if ($page < $results['pages']): ?>
                <button type="button" class="fss-page-btn" data-page="<?php echo ($page + 1); ?>">Next →</button>
            <?php endif; ?>
        </div>
        <div class="fss-pagination-info">
            Page <?php echo $page; ?> of <?php echo $results['pages']; ?> (<?php echo $results['total']; ?> total records)
        </div>
        <?php
        $pagination_html = ob_get_clean();

        wp_send_json_success(array(
            'table' => $table_html,
            'pagination' => $pagination_html
        ));
    }

    /**
     * Analytics
     */
    public function get_analytics() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        $period = isset($_POST['period']) ? intval($_POST['period']) : 7;
        $end_date = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $start_date = clone $end_date;
        $start_date->modify("-{$period} days");
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        $analytics_data = $wpdb->get_results($wpdb->prepare("
            SELECT date,total_sales,transfer_card,cash,delivery,extras,expense,cash_left
            FROM $table_name 
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        ", $start_date->format('Y-m-d'), $end_date->format('Y-m-d')));

        $dates = array();
        $sales = array();
        $cash_in = array();
        $cash_out = array();
        $total_transfer_card = 0;
        $total_cash = 0;
        $total_expenses = 0;
        $total_period_sales = 0;

        foreach ($analytics_data as $d) {
            $dates[] = date('M d', strtotime($d->date));
            $sales[] = (float)$d->total_sales;
            $cash_in[] = (float)$d->total_sales + (float)$d->extras;
            $cash_out[] = (float)$d->expense;
            $total_transfer_card += (float)$d->transfer_card;
            $total_cash += (float)$d->cash;
            $total_expenses += (float)$d->expense;
            $total_period_sales += (float)$d->total_sales;
        }

        wp_send_json_success(array(
            'dates' => $dates,
            'sales' => $sales,
            'cash_in' => $cash_in,
            'cash_out' => $cash_out,
            'total_transfer_card' => $total_transfer_card,
            'total_cash' => $total_cash,
            'total_expenses' => $total_expenses,
            'total_period_sales' => $total_period_sales,
            'avg_daily_sales' => count($sales) ? $total_period_sales / count($sales) : 0
        ));
    }

    /**
     * Save Draft
     */
    public function save_draft() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        $user_id = get_current_user_id();
        $draft_data = array(
            'extras' => sanitize_text_field($_POST['extras'] ?? ''),
            'extras_remark' => sanitize_textarea_field($_POST['extras_remark'] ?? ''),
            'expense' => sanitize_text_field($_POST['expense'] ?? ''),
            'expense_remark' => sanitize_textarea_field($_POST['expense_remark'] ?? '')
        );
        update_user_meta($user_id, 'fss_draft_data', $draft_data);
        wp_send_json_success(array('message' => 'Draft saved'));
    }

    /**
     * Show reconciliation form
     */
    public function show_reconciliation() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $today = $lagos_time->format('Y-m-d');
        $summary = FSS_Database::get_daily_summary($today);
        $expected_cash = $summary ? $summary->cash_left : 0;

        ob_start(); ?>
        <form id="reconciliation-form">
            <div class="fss-reconciliation-field">
                <label>Expected Cash Left:</label>
                <input type="number" id="expected-cash" value="<?php echo $expected_cash; ?>" step="0.01" readonly>
            </div>
            <div class="fss-reconciliation-field">
                <label>Actual Cash Count:</label>
                <input type="number" id="actual-cash" step="0.01" required>
            </div>
            <div class="fss-reconciliation-field">
                <label>Discrepancy:</label>
                <input type="number" id="discrepancy" step="0.01" readonly>
            </div>
            <div class="fss-reconciliation-field">
                <label>Notes:</label>
                <textarea id="reconciliation-notes" rows="3"></textarea>
            </div>
            <div class="fss-reconciliation-actions">
                <button type="submit" class="fss-btn">Submit Reconciliation</button>
            </div>
        </form>
        <script>
        jQuery('#actual-cash').on('input', function(){
            const expected = parseFloat(jQuery('#expected-cash').val())||0;
            const actual = parseFloat(jQuery(this).val())||0;
            jQuery('#discrepancy').val((actual-expected).toFixed(2));
        });
        jQuery('#reconciliation-form').on('submit', function(e){
            e.preventDefault();
            jQuery.ajax({
                url: fss_ajax.ajax_url, type: 'POST',
                data: {
                    action: 'fss_submit_reconciliation',
                    nonce: fss_ajax.nonce,
                    expected_cash: jQuery('#expected-cash').val(),
                    actual_cash: jQuery('#actual-cash').val(),
                    discrepancy: jQuery('#discrepancy').val(),
                    notes: jQuery('#reconciliation-notes').val()
                },
                success: function(r){
                    if(r.success){
                        if (typeof closeReconciliation === 'function') {
                            closeReconciliation();
                        }
                        if (typeof showNotification === 'function') {
                            showNotification('Reconciliation submitted successfully','success');
                        }
                    } else {
                        if (typeof showNotification === 'function') {
                            showNotification(r.data.message,'error');
                        }
                    }
                }
            });
        });
        </script>
        <?php
        wp_send_json_success(array('html' => ob_get_clean()));
    }

    /**
     * Persist reconciliation
     */
    public function submit_reconciliation() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $today = $lagos_time->format('Y-m-d');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_reconciliations';
        $result = $wpdb->insert($table_name, array(
            'date' => $today,
            'expected_cash' => floatval($_POST['expected_cash']),
            'actual_cash' => floatval($_POST['actual_cash']),
            'discrepancy' => floatval($_POST['discrepancy']),
            'notes' => sanitize_textarea_field($_POST['notes']),
            'status' => 'completed',
            'reconciled_by' => wp_get_current_user()->display_name,
            'reconciled_at' => current_time('mysql')
        ));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Reconciliation saved successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save reconciliation'));
        }
    }

    /**
     * Export day CSV
     */
    public function export_day() {
        if (!wp_verify_nonce($_GET['nonce'], 'fss_nonce')) {
            wp_die('Security check failed');
        }
        
        $date = sanitize_text_field($_GET['date']);
        $summary = FSS_Database::get_daily_summary($date);
        $history_entries = FSS_Database::get_history_entries($date);
        
        if (!$summary) {
            wp_die('No data found for this date');
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="financial_summary_' . $date . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Financial Summary for ' . date('F d, Y', strtotime($date))));
        fputcsv($output, array(''));
        fputcsv($output, array('Field', 'Amount'));
        fputcsv($output, array('Total Sales', number_format($summary->total_sales, 2)));
        fputcsv($output, array('Transfer/Card', number_format($summary->transfer_card, 2)));
        fputcsv($output, array('Cash', number_format($summary->cash, 2)));
        fputcsv($output, array('Delivery', number_format($summary->delivery, 2)));
        fputcsv($output, array('Extras', number_format($summary->extras, 2)));
        fputcsv($output, array('Expenses', number_format($summary->expense, 2)));
        fputcsv($output, array('Market Card', number_format(isset($summary->cash_left_market_card) ? $summary->cash_left_market_card : 0, 2)));
        fputcsv($output, array('Cash Left', number_format($summary->cash_left, 2)));
        
        if (!empty($history_entries)) {
            fputcsv($output, array(''));
            fputcsv($output, array('Individual Entries'));
            fputcsv($output, array('Time', 'Created By', 'Extras', 'Extras Remark', 'Expense', 'Expense Remark'));
            foreach ($history_entries as $entry) {
                fputcsv($output, array(
                    wp_date('H:i:s', strtotime($entry->created_at)),
                    $entry->created_by,
                    number_format($entry->extras, 2),
                    $entry->extras_remark,
                    number_format($entry->expense, 2),
                    $entry->expense_remark
                ));
            }
        }
        fclose($output);
        exit;
    }
    
    /**
     * Edit history date - Get data for editing
     */
    public function edit_history_date() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $date = sanitize_text_field($_POST['date']);
        $summary = FSS_Database::get_daily_summary($date);
        
        if (!$summary) {
            wp_send_json_error('No data found for this date');
        }
        
        // Get history entries
        $history_entries = FSS_Database::get_history_entries($date);
        
        wp_send_json_success(array(
            'summary' => $summary,
            'history_entries' => $history_entries
        ));
    }
    
    /**
     * Update history date - Save edited data
     */
    public function update_history_date() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        // Validate and sanitize input values
        $total_sales = max(0, floatval($_POST['total_sales']));
        $transfer_card = max(0, floatval($_POST['transfer_card']));
        $cash = max(0, floatval($_POST['cash']));
        $delivery = max(0, floatval($_POST['delivery']));
        $extras = max(0, floatval($_POST['extras']));
        $extras_remark = sanitize_textarea_field($_POST['extras_remark']);
        $expense = max(0, floatval($_POST['expense']));
        $expense_remark = sanitize_textarea_field($_POST['expense_remark']);
        $old_cash = max(0, floatval($_POST['old_cash']));
        $cash_left_market_card = max(0, floatval($_POST['cash_left_market_card']));
        
        // Calculate cash left using the correct formula
        // Formula: cash_left = cash_sales + extras + old_cash + market_card - expenses
        $cash_left = ($cash + $old_cash + $extras + $cash_left_market_card) - $expense;
        $cash_left = max(0, $cash_left); // Ensure no negative value
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fss_daily_summaries';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'total_sales' => $total_sales,
                'transfer_card' => $transfer_card,
                'cash' => $cash,
                'delivery' => $delivery,
                'extras' => $extras,
                'extras_remark' => $extras_remark,
                'expense' => $expense,
                'expense_remark' => $expense_remark,
                'old_cash' => $old_cash,
                'cash_left' => $cash_left,
                'cash_left_market_card' => $cash_left_market_card,
                'updated_at' => current_time('mysql')
            ),
            array('date' => $date),
            array('%f', '%f', '%f', '%f', '%f', '%s', '%f', '%s', '%f', '%f', '%f', '%s'),
            array('%s')
        );
        
        if ($result !== false) {
            // Update old_cash for the next day
            $next_date = date('Y-m-d', strtotime($date . ' +1 day'));
            update_option('fss_old_cash_' . $next_date, $cash_left);
            
            wp_send_json_success(array(
                'message' => 'History updated successfully',
                'cash_left' => $cash_left
            ));
        } else {
            wp_send_json_error('Failed to update history');
        }
    }
    
    /**
     * Delete history date
     */
    public function delete_history_date() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        global $wpdb;
        
        // Delete history entries first
        $history_table = $wpdb->prefix . 'fss_history';
        $wpdb->delete($history_table, array('date' => $date), array('%s'));
        
        // Delete summary
        $summary_table = $wpdb->prefix . 'fss_daily_summaries';
        $result = $wpdb->delete($summary_table, array('date' => $date), array('%s'));
        
        if ($result) {
            wp_send_json_success('History deleted successfully');
        } else {
            wp_send_json_error('Failed to delete history');
        }
    }
    
    /**
     * Fix negative values in database
     */
    public function fix_negative_values() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = FSS_Database::fix_negative_values();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Failed to fix negative values');
        }
    }
}