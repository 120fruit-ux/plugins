<?php
/**
 * History Management Class
 */

class FSS_History_Manager {
    
    public function __construct() {
        add_action('wp_ajax_fss_get_history_entry', array($this, 'get_history_entry'));
        add_action('wp_ajax_fss_update_history_entry', array($this, 'update_history_entry'));
        add_action('wp_ajax_fss_delete_history_entry', array($this, 'delete_history_entry'));
        add_action('wp_ajax_fss_delete_history_bulk', array($this, 'delete_history_bulk'));
        add_action('wp_ajax_fss_get_history_management', array($this, 'get_history_management'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_history_menu'));
    }
    
    public function add_history_menu() {
        add_submenu_page(
            'financial-summary',
            'History Management',
            'Manage History',
            'manage_options',
            'financial-summary-history-management',
            array($this, 'history_management_page')
        );
    }
    
    public function history_management_page() {
        ?>
        <div class="wrap">
            <h1>History Management</h1>
            
            <div class="fss-history-management">
                <!-- Filters Section -->
                <div class="fss-management-section">
                    <h2>Filter History Entries</h2>
                    <div class="fss-management-filters">
                        <div class="filter-group">
                            <label>Date Range:</label>
                            <input type="date" id="mgmt-date-from" placeholder="From Date">
                            <input type="date" id="mgmt-date-to" placeholder="To Date">
                        </div>
                        <div class="filter-group">
                            <label>Created By:</label>
                            <input type="text" id="mgmt-created-by" placeholder="Username">
                        </div>
                        <div class="filter-group">
                            <label>Entry Type:</label>
                            <select id="mgmt-entry-type">
                                <option value="">All Types</option>
                                <option value="extras">Extras Only</option>
                                <option value="expenses">Expenses Only</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="button" class="button button-primary" id="apply-mgmt-filters">
                                Apply Filters
                            </button>
                            <button type="button" class="button" id="clear-mgmt-filters">
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Actions Section -->
                <div class="fss-management-section">
                    <h2>Bulk Actions</h2>
                    <div class="fss-bulk-actions">
                        <button type="button" class="button button-secondary" id="select-all-entries">
                            Select All Visible
                        </button>
                        <button type="button" class="button button-secondary" id="deselect-all-entries">
                            Deselect All
                        </button>
                        <button type="button" class="button button-danger" id="delete-selected-entries">
                            Delete Selected
                        </button>
                        <button type="button" class="button button-danger" id="clear-all-history">
                            Clear All History
                        </button>
                    </div>
                    <div id="bulk-selection-info">
                        <span id="selected-count">0</span> entries selected
                    </div>
                </div>
                
                <!-- History Table -->
                <div class="fss-management-section">
                    <h2>History Entries</h2>
                    <div id="history-management-table">
                        <!-- Loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Modal -->
        <div id="edit-history-modal" class="fss-modal" style="display: none;">
            <div class="fss-modal-content">
                <span class="fss-close">&times;</span>
                <h2>Edit History Entry</h2>
                <form id="edit-history-form">
                    <input type="hidden" id="edit-entry-id">
                    <div class="form-row">
                        <label>Date:</label>
                        <input type="text" id="edit-entry-date" readonly>
                    </div>
                    <div class="form-row">
                        <label>Created By:</label>
                        <input type="text" id="edit-entry-created-by" readonly>
                    </div>
                    <div class="form-row">
                        <label>Extras Amount:</label>
                        <input type="number" id="edit-entry-extras" step="0.01" min="0">
                    </div>
                    <div class="form-row">
                        <label>Extras Remark:</label>
                        <textarea id="edit-entry-extras-remark" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <label>Expense Amount:</label>
                        <input type="number" id="edit-entry-expense" step="0.01" min="0">
                    </div>
                    <div class="form-row">
                        <label>Expense Remark:</label>
                        <textarea id="edit-entry-expense-remark" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button button-primary">Update Entry</button>
                        <button type="button" class="button" onclick="closeEditModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .fss-history-management {
            max-width: 1200px;
        }
        
        .fss-management-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .fss-management-section h2 {
            margin-top: 0;
            border-bottom: 2px solid #FF0000;
            padding-bottom: 10px;
        }
        
        .fss-management-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #333;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
        .fss-bulk-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
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
        
        #bulk-selection-info {
            font-weight: 600;
            color: #666;
        }
        
        .history-management-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .history-management-table th,
        .history-management-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        
        .history-management-table th {
            background: #f9f9f9;
            font-weight: 600;
        }
        
        .history-management-table tr:hover {
            background: #f5f5f5;
        }
        
        .entry-actions {
            display: flex;
            gap: 5px;
        }
        
        .entry-actions button {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 3px;
        }
        
        .edit-btn {
            background: #007cba;
            color: white;
            border: none;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-row input,
        .form-row textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let selectedEntries = new Set();
            
            // Load initial data
            loadHistoryManagement();
            
            // Filter actions
            $('#apply-mgmt-filters').on('click', function() {
                loadHistoryManagement();
            });
            
            $('#clear-mgmt-filters').on('click', function() {
                $('#mgmt-date-from, #mgmt-date-to, #mgmt-created-by').val('');
                $('#mgmt-entry-type').val('');
                loadHistoryManagement();
            });
            
            // Bulk selection
            $('#select-all-entries').on('click', function() {
                $('.entry-checkbox:visible').prop('checked', true).trigger('change');
            });
            
            $('#deselect-all-entries').on('click', function() {
                $('.entry-checkbox').prop('checked', false).trigger('change');
            });
            
            // Entry selection tracking
            $(document).on('change', '.entry-checkbox', function() {
                const entryId = $(this).val();
                if ($(this).is(':checked')) {
                    selectedEntries.add(entryId);
                } else {
                    selectedEntries.delete(entryId);
                }
                updateSelectionInfo();
            });
            
            // Delete selected entries
            $('#delete-selected-entries').on('click', function() {
                if (selectedEntries.size === 0) {
                    alert('Please select entries to delete');
                    return;
                }
                
                if (!confirm(`Are you sure you want to delete ${selectedEntries.size} selected entries? This action cannot be undone.`)) {
                    return;
                }
                
                deleteSelectedEntries();
            });
            
            // Clear all history
            $('#clear-all-history').on('click', function() {
                if (!confirm('Are you sure you want to clear ALL history entries? This action cannot be undone and will affect all financial summaries.')) {
                    return;
                }
                
                if (!confirm('This is your final warning. Clearing all history will permanently delete all individual entries and recalculate all summaries. Continue?')) {
                    return;
                }
                
                clearAllHistory();
            });
            
            // Edit entry modal
            $(document).on('click', '.edit-entry', function() {
                const entryId = $(this).data('entry-id');
                editHistoryEntry(entryId);
            });
            
            // Delete single entry
            $(document).on('click', '.delete-entry', function() {
                const entryId = $(this).data('entry-id');
                if (confirm('Are you sure you want to delete this entry?')) {
                    deleteSingleEntry(entryId);
                }
            });
            
            // Edit form submission
            $('#edit-history-form').on('submit', function(e) {
                e.preventDefault();
                updateHistoryEntry();
            });
            
            // Close modal
            $('.fss-close').on('click', function() {
                closeEditModal();
            });
            
            function loadHistoryManagement(page = 1) {
                const filters = {
                    date_from: $('#mgmt-date-from').val(),
                    date_to: $('#mgmt-date-to').val(),
                    created_by: $('#mgmt-created-by').val(),
                    entry_type: $('#mgmt-entry-type').val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_get_history_management',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        page: page,
                        filters: filters
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#history-management-table').html(response.data.html);
                            selectedEntries.clear();
                            updateSelectionInfo();
                        } else {
                            alert('Failed to load history: ' + response.data.message);
                        }
                    }
                });
            }
            
            function updateSelectionInfo() {
                $('#selected-count').text(selectedEntries.size);
            }
            
            function deleteSelectedEntries() {
                const entryIds = Array.from(selectedEntries);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_delete_history_bulk',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        entry_ids: entryIds
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(`Successfully deleted ${response.data.deleted_count} entries`);
                            loadHistoryManagement();
                        } else {
                            alert('Failed to delete entries: ' + response.data.message);
                        }
                    }
                });
            }
            
            function clearAllHistory() {
                const filters = {
                    date_from: $('#mgmt-date-from').val(),
                    date_to: $('#mgmt-date-to').val(),
                    created_by: $('#mgmt-created-by').val(),
                    entry_type: $('#mgmt-entry-type').val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_delete_history_bulk',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        clear_all: true,
                        filters: filters
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(`Successfully cleared ${response.data.deleted_count} history entries`);
                            loadHistoryManagement();
                        } else {
                            alert('Failed to clear history: ' + response.data.message);
                        }
                    }
                });
            }
            
            function editHistoryEntry(entryId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_get_history_entry',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        entry_id: entryId
                    },
                    success: function(response) {
                        if (response.success) {
                            const entry = response.data;
                            $('#edit-entry-id').val(entry.id);
                            $('#edit-entry-date').val(entry.summary_date);
                            $('#edit-entry-created-by').val(entry.created_by);
                            $('#edit-entry-extras').val(entry.extras);
                            $('#edit-entry-extras-remark').val(entry.extras_remark);
                            $('#edit-entry-expense').val(entry.expense);
                            $('#edit-entry-expense-remark').val(entry.expense_remark);
                            
                            $('#edit-history-modal').show();
                        } else {
                            alert('Failed to load entry: ' + response.data.message);
                        }
                    }
                });
            }
            
            function updateHistoryEntry() {
                const entryId = $('#edit-entry-id').val();
                const updateData = {
                    extras: $('#edit-entry-extras').val(),
                    extras_remark: $('#edit-entry-extras-remark').val(),
                    expense: $('#edit-entry-expense').val(),
                    expense_remark: $('#edit-entry-expense-remark').val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_update_history_entry',
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>',
                        entry_id: entryId,
                        update_data: updateData
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Entry updated successfully');
                            closeEditModal();
                            loadHistoryManagement();
                        } else {
                            alert('Failed to update entry: ' + response.data.message);
                        }
                    }
                });
            }
            
            function deleteSingleEntry(entryId) {
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
                            alert('Entry deleted successfully');
                            loadHistoryManagement();
                        } else {
                            alert('Failed to delete entry: ' + response.data.message);
                        }
                    }
                });
            }
            
            function closeEditModal() {
                $('#edit-history-modal').hide();
            }
            
            // Pagination handling
            $(document).on('click', '.mgmt-page-btn', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) {
                    loadHistoryManagement(page);
                }
            });
        });
        </script>
        <?php
    }
    
    public function get_history_entry() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $entry_id = intval($_POST['entry_id']);
        $entry = FSS_Database::get_history_entry($entry_id);
        
        if ($entry) {
            // Get summary date
            global $wpdb;
            $summary_table = $wpdb->prefix . 'fss_daily_summaries';
            $summary = $wpdb->get_row($wpdb->prepare(
                "SELECT date FROM $summary_table WHERE id = %d",
                $entry->summary_id
            ));
            
            $entry->summary_date = $summary ? $summary->date : '';
            
            wp_send_json_success($entry);
        } else {
            wp_send_json_error('Entry not found');
        }
    }
    
    public function update_history_entry() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $entry_id = intval($_POST['entry_id']);
        $update_data = $_POST['update_data'];
        
        $result = FSS_Database::update_history_entry($entry_id, $update_data);
        
        if ($result !== false) {
            wp_send_json_success('Entry updated successfully');
        } else {
            wp_send_json_error('Failed to update entry');
        }
    }
    
    public function delete_history_entry() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $entry_id = intval($_POST['entry_id']);
        $result = FSS_Database::delete_history_entry($entry_id);
        
        if ($result) {
            wp_send_json_success('Entry deleted successfully');
        } else {
            wp_send_json_error('Failed to delete entry');
        }
    }
    
    public function delete_history_bulk() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (isset($_POST['clear_all']) && $_POST['clear_all']) {
            // Clear all with filters
            $filters = $_POST['filters'] ?? array();
            $deleted_count = FSS_Database::delete_history_entries_bulk($filters);
        } else {
            // Delete specific entries
            $entry_ids = $_POST['entry_ids'] ?? array();
            $deleted_count = 0;
            
            foreach ($entry_ids as $entry_id) {
                if (FSS_Database::delete_history_entry(intval($entry_id))) {
                    $deleted_count++;
                }
            }
        }
        
        wp_send_json_success(array('deleted_count' => $deleted_count));
    }
    
    public function get_history_management() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $filters = $_POST['filters'] ?? array();
        
        $results = FSS_Database::get_history_entries_paginated($page, 20, $filters);
        
        ob_start();
        ?>
        <table class="history-management-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-checkbox"></th>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Extras</th>
                    <th>Extras Remark</th>
                    <th>Expense</th>
                    <th>Expense Remark</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['results'] as $entry): ?>
                <tr>
                    <td><input type="checkbox" class="entry-checkbox" value="<?php echo $entry->id; ?>"></td>
                    <td><?php echo $entry->id; ?></td>
                    <td><?php echo date('M d, Y', strtotime($entry->summary_date)); ?></td>
                    <td><?php echo esc_html($entry->created_by); ?></td>
                    <td><?php echo wp_date('M d, Y H:i', strtotime($entry->created_at)); ?></td>
                    <td>₦<?php echo number_format($entry->extras, 2); ?></td>
                    <td><?php echo esc_html($entry->extras_remark); ?></td>
                    <td>₦<?php echo number_format($entry->expense, 2); ?></td>
                    <td><?php echo esc_html($entry->expense_remark); ?></td>
                    <td>
                        <div class="entry-actions">
                            <button class="edit-btn edit-entry" data-entry-id="<?php echo $entry->id; ?>">Edit</button>
                            <button class="delete-btn delete-entry" data-entry-id="<?php echo $entry->id; ?>">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($results['pages'] > 1): ?>
        <div class="fss-pagination">
            <?php if ($results['current_page'] > 1): ?>
            <a href="#" class="mgmt-page-btn" data-page="<?php echo $results['current_page'] - 1; ?>">Previous</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $results['current_page'] - 2); $i <= min($results['pages'], $results['current_page'] + 2); $i++): ?>
            <a href="#" class="mgmt-page-btn <?php echo $i == $results['current_page'] ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($results['current_page'] < $results['pages']): ?>
            <a href="#" class="mgmt-page-btn" data-page="<?php echo $results['current_page'] + 1; ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="results-info">
            Showing <?php echo count($results['results']); ?> of <?php echo $results['total']; ?> entries
        </div>
        
        <script>
        jQuery('#select-all-checkbox').on('change', function() {
            jQuery('.entry-checkbox').prop('checked', jQuery(this).is(':checked')).trigger('change');
        });
        </script>
        <?php
        
        wp_send_json_success(array('html' => ob_get_clean()));
    }
}