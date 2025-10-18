<?php
/**
 * Enhanced Admin History Editor with Full Editing Capabilities
 */

class FSS_Admin_History_Editor {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_history_editor_menu'));
        add_action('wp_ajax_fss_get_history_for_edit', array($this, 'get_history_for_edit'));
        add_action('wp_ajax_fss_update_full_history', array($this, 'update_full_history'));
        add_action('wp_ajax_fss_delete_full_history', array($this, 'delete_full_history'));
        add_action('wp_ajax_fss_recalculate_summary', array($this, 'recalculate_summary'));
    }
    
    public function add_history_editor_menu() {
        add_submenu_page(
            'financial-summary',
            'Advanced History Editor',
            '📝 Edit History',
            'manage_options',
            'fss-history-editor',
            array($this, 'history_editor_page')
        );
    }
    
    public function history_editor_page() {
        ?>
        <div class="wrap">
            <h1>📝 Advanced Financial History Editor</h1>
            <p class="description">Comprehensive editing tools for financial summaries and history entries</p>
            
            <div class="fss-editor-container">
                <!-- Date Selection -->
                <div class="fss-editor-section">
                    <h2>Select Date to Edit</h2>
                    <div class="date-selector">
                        <input type="date" id="edit-date-selector" value="<?php echo date('Y-m-d'); ?>">
                        <button type="button" class="button button-primary" id="load-date-history">
                            📅 Load Date History
                        </button>
                    </div>
                </div>
                
                <!-- Summary Editor -->
                <div class="fss-editor-section" id="summary-editor" style="display: none;">
                    <h2>📊 Daily Summary Editor</h2>
                    <form id="summary-edit-form">
                        <input type="hidden" id="edit-summary-id" name="summary_id">
                        <input type="hidden" id="edit-summary-date" name="date">
                        
                        <div class="form-grid">
                            <!-- Sales Data -->
                            <div class="form-group">
                                <label>Total Sales (₦)</label>
                                <input type="number" name="total_sales" id="edit-total-sales" step="0.01">
                                <small>Auto-calculated from order data</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Transfer/Card Sales (₦)</label>
                                <input type="number" name="transfer_card" id="edit-transfer-card" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label>Cash Sales (₦)</label>
                                <input type="number" name="cash" id="edit-cash" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label>Delivery Fees (₦)</label>
                                <input type="number" name="delivery" id="edit-delivery" step="0.01">
                            </div>
                            
                            <!-- Manual Entries -->
                            <div class="form-group">
                                <label>Total Extras (₦)</label>
                                <input type="number" name="extras" id="edit-extras" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label>Extras Remark</label>
                                <textarea name="extras_remark" id="edit-extras-remark" rows="2"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Total Expenses (₦)</label>
                                <input type="number" name="expense" id="edit-expense" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label>Expense Remark</label>
                                <textarea name="expense_remark" id="edit-expense-remark" rows="2"></textarea>
                            </div>
                            
                            <!-- Cash Flow -->
                            <div class="form-group">
                                <label>Old Cash (₦)</label>
                                <input type="number" name="old_cash" id="edit-old-cash" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label>Cash Left Market Card (₦)</label>
                                <input type="number" name="cash_left_market_card" id="edit-cash-left-market-card" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label>Cash Left (₦)</label>
                                <input type="number" name="cash_left" id="edit-cash-left" step="0.01" readonly>
                                <small>Auto-calculated</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Created By</label>
                                <input type="text" name="created_by" id="edit-created-by">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="button button-primary button-large">
                                💾 Update Summary
                            </button>
                            <button type="button" class="button button-secondary" id="recalculate-btn">
                                🔄 Recalculate Cash Left
                            </button>
                            <button type="button" class="button button-danger" id="delete-summary-btn">
                                🗑️ Delete Summary
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Individual History Entries -->
                <div class="fss-editor-section" id="history-entries-editor" style="display: none;">
                    <h2>📋 Individual History Entries</h2>
                    <div id="history-entries-container">
                        <!-- Loaded dynamically -->
                    </div>
                    
                    <div class="add-entry-section">
                        <h3>➕ Add New Entry</h3>
                        <form id="add-entry-form">
                            <div class="form-row">
                                <input type="number" placeholder="Extras Amount" name="new_extras" step="0.01">
                                <input type="text" placeholder="Extras Remark" name="new_extras_remark">
                                <input type="number" placeholder="Expense Amount" name="new_expense" step="0.01">
                                <input type="text" placeholder="Expense Remark" name="new_expense_remark">
                                <button type="submit" class="button button-primary">Add Entry</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Action Results -->
                <div id="editor-messages"></div>
            </div>
        </div>
        
        <style>
        .fss-editor-container {
            max-width: 1200px;
        }
        
        .fss-editor-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .fss-editor-section h2 {
            margin-top: 0;
            border-bottom: 2px solid #FF0000;
            padding-bottom: 10px;
        }
        
        .date-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-selector input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input[readonly] {
            background: #f5f5f5;
            color: #666;
        }
        
        .form-group small {
            color: #666;
            font-size: 12px;
            margin-top: 3px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .button-danger {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .button-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }
        
        .history-entry-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .history-entry-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .history-entry-meta {
            font-size: 12px;
            color: #666;
        }
        
        .history-entry-actions {
            display: flex;
            gap: 5px;
        }
        
        .form-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .form-row input {
            flex: 1;
            min-width: 150px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .add-entry-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Load date history
            $('#load-date-history').on('click', function() {
                const date = $('#edit-date-selector').val();
                if (!date) {
                    alert('Please select a date');
                    return;
                }
                loadDateHistory(date);
            });
            
            // Auto-calculate cash left
            $('#edit-cash, #edit-old-cash, #edit-extras, #edit-cash-left-market-card, #edit-expense').on('input', function() {
                calculateCashLeft();
            });
            
            // Recalculate button
            $('#recalculate-btn').on('click', function() {
                calculateCashLeft();
                showMessage('Cash left recalculated!', 'success');
            });
            
            // Update summary form
            $('#summary-edit-form').on('submit', function(e) {
                e.preventDefault();
                updateSummary();
            });
            
            // Delete summary
            $('#delete-summary-btn').on('click', function() {
                if (confirm('Are you sure you want to delete this entire summary? This action cannot be undone.')) {
                    deleteSummary();
                }
            });
            
            function loadDateHistory(date) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_get_history_for_edit',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        date: date
                    },
                    success: function(response) {
                        if (response.success) {
                            populateEditor(response.data);
                            $('#summary-editor, #history-entries-editor').show();
                        } else {
                            showMessage(response.data.message || 'No data found for this date', 'error');
                            $('#summary-editor, #history-entries-editor').hide();
                        }
                    },
                    error: function() {
                        showMessage('Failed to load history data', 'error');
                    }
                });
            }
            
            function populateEditor(data) {
                const summary = data.summary;
                const entries = data.entries;
                
                // Populate summary form
                $('#edit-summary-id').val(summary.id);
                $('#edit-summary-date').val(summary.date);
                $('#edit-total-sales').val(summary.total_sales);
                $('#edit-transfer-card').val(summary.transfer_card);
                $('#edit-cash').val(summary.cash);
                $('#edit-delivery').val(summary.delivery);
                $('#edit-extras').val(summary.extras);
                $('#edit-extras-remark').val(summary.extras_remark);
                $('#edit-expense').val(summary.expense);
                $('#edit-expense-remark').val(summary.expense_remark);
                $('#edit-old-cash').val(summary.old_cash);
                $('#edit-cash-left-market-card').val(summary.cash_left_market_card);
                $('#edit-cash-left').val(summary.cash_left);
                $('#edit-created-by').val(summary.created_by);
                
                // Populate history entries
                let entriesHtml = '';
                entries.forEach(function(entry) {
                    entriesHtml += `
                        <div class="history-entry-card" data-entry-id="${entry.id}">
                            <div class="history-entry-header">
                                <strong>Entry #${entry.id}</strong>
                                <div class="history-entry-actions">
                                    <button class="button button-small edit-entry-btn" data-id="${entry.id}">Edit</button>
                                    <button class="button button-small button-danger delete-entry-btn" data-id="${entry.id}">Delete</button>
                                </div>
                            </div>
                            <div class="history-entry-meta">
                                Created: ${entry.created_at} by ${entry.created_by}
                                ${entry.updated_at ? ` | Updated: ${entry.updated_at} by ${entry.updated_by}` : ''}
                            </div>
                            <div class="history-entry-content">
                                <div>Extras: ₦${parseFloat(entry.extras).toLocaleString()} - ${entry.extras_remark}</div>
                                <div>Expense: ₦${parseFloat(entry.expense).toLocaleString()} - ${entry.expense_remark}</div>
                            </div>
                        </div>
                    `;
                });
                
                $('#history-entries-container').html(entriesHtml);
            }
            
            function calculateCashLeft() {
                const cash = parseFloat($('#edit-cash').val()) || 0;
                const oldCash = parseFloat($('#edit-old-cash').val()) || 0;
                const extras = parseFloat($('#edit-extras').val()) || 0;
                const marketCard = parseFloat($('#edit-cash-left-market-card').val()) || 0;
                const expenses = parseFloat($('#edit-expense').val()) || 0;
                
                const cashLeft = cash + oldCash + extras + marketCard - expenses;
                $('#edit-cash-left').val(cashLeft.toFixed(2));
            }
            
            function updateSummary() {
                const formData = $('#summary-edit-form').serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=fss_update_full_history&nonce=<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                    success: function(response) {
                        if (response.success) {
                            showMessage('Summary updated successfully!', 'success');
                        } else {
                            showMessage(response.data.message || 'Update failed', 'error');
                        }
                    },
                    error: function() {
                        showMessage('Network error during update', 'error');
                    }
                });
            }
            
            function deleteSummary() {
                const summaryId = $('#edit-summary-id').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_delete_full_history',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        summary_id: summaryId
                    },
                    success: function(response) {
                        if (response.success) {
                            showMessage('Summary deleted successfully!', 'success');
                            $('#summary-editor, #history-entries-editor').hide();
                        } else {
                            showMessage(response.data.message || 'Delete failed', 'error');
                        }
                    },
                    error: function() {
                        showMessage('Network error during delete', 'error');
                    }
                });
            }
            
            function showMessage(message, type) {
                const messageClass = type === 'success' ? 'success-message' : 'error-message';
                const messageHtml = `<div class="${messageClass}">${message}</div>`;
                $('#editor-messages').html(messageHtml);
                
                setTimeout(function() {
                    $('#editor-messages').fadeOut(function() {
                        $(this).empty().show();
                    });
                }, 5000);
            }
            
            // Entry editing handlers
            $(document).on('click', '.edit-entry-btn', function() {
                const entryId = $(this).data('id');
                // Implement inline editing
                editHistoryEntry(entryId);
            });
            
            $(document).on('click', '.delete-entry-btn', function() {
                const entryId = $(this).data('id');
                if (confirm('Delete this history entry?')) {
                    deleteHistoryEntry(entryId);
                }
            });
            
            function editHistoryEntry(entryId) {
                // Convert entry to editable form
                const entryCard = $(`.history-entry-card[data-entry-id="${entryId}"]`);
                // Implementation for inline editing
            }
            
            function deleteHistoryEntry(entryId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_delete_history_entry',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        entry_id: entryId
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`.history-entry-card[data-entry-id="${entryId}"]`).fadeOut(function() {
                                $(this).remove();
                            });
                            showMessage('Entry deleted successfully!', 'success');
                        } else {
                            showMessage('Failed to delete entry', 'error');
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    public function get_history_for_edit() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        // Get summary
        $summary = FSS_Database::get_daily_summary($date);
        if (!$summary) {
            wp_send_json_error(array('message' => 'No summary found for this date'));
        }
        
        // Get history entries
        $entries = FSS_Database::get_history_entries($date);
        
        wp_send_json_success(array(
            'summary' => $summary,
            'entries' => $entries
        ));
    }
    
    public function update_full_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        $summary_table = $wpdb->prefix . 'fss_daily_summaries';
        
        $summary_id = intval($_POST['summary_id']);
        $update_data = array(
            'total_sales' => floatval($_POST['total_sales']),
            'transfer_card' => floatval($_POST['transfer_card']),
            'cash' => floatval($_POST['cash']),
            'delivery' => floatval($_POST['delivery']),
            'extras' => floatval($_POST['extras']),
            'extras_remark' => sanitize_textarea_field($_POST['extras_remark']),
            'expense' => floatval($_POST['expense']),
            'expense_remark' => sanitize_textarea_field($_POST['expense_remark']),
            'old_cash' => floatval($_POST['old_cash']),
            'cash_left_market_card' => floatval($_POST['cash_left_market_card']),
            'cash_left' => floatval($_POST['cash_left']),
            'created_by' => sanitize_text_field($_POST['created_by']),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->update($summary_table, $update_data, array('id' => $summary_id));
        
        if ($result !== false) {
            wp_send_json_success('Summary updated successfully');
        } else {
            wp_send_json_error(array('message' => 'Failed to update summary'));
        }
    }
    
    public function delete_full_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        $summary_id = intval($_POST['summary_id']);
        
        // Delete history entries first
        $history_table = $wpdb->prefix . 'fss_history_entries';
        $wpdb->delete($history_table, array('summary_id' => $summary_id));
        
        // Delete summary
        $summary_table = $wpdb->prefix . 'fss_daily_summaries';
        $result = $wpdb->delete($summary_table, array('id' => $summary_id));
        
        if ($result) {
            wp_send_json_success('Summary and all related entries deleted successfully');
        } else {
            wp_send_json_error(array('message' => 'Failed to delete summary'));
        }
    }
}

new FSS_Admin_History_Editor();