<?php

class FSS_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Financial Summary',
            'Financial Summary',
            'manage_options',
            'financial-summary',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'financial-summary',
            'Summary Dashboard',
            'Dashboard',
            'manage_options',
            'financial-summary',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'financial-summary',
            'History & Reports',
            'History',
            'manage_options',
            'financial-summary-history',
            array($this, 'history_page')
        );
        
        add_submenu_page(
            'financial-summary',
            'Analytics',
            'Analytics',
            'manage_options',
            'financial-summary-analytics',
            array($this, 'analytics_page')
        );
        
        add_submenu_page(
            'financial-summary',
            'Settings',
            'Settings',
            'manage_options',
            'financial-summary-settings',
            array($this, 'settings_page')
        );
    }
    
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'fss_today_summary',
            'Today\'s Financial Summary',
            array($this, 'today_summary_widget')
        );
        
        wp_add_dashboard_widget(
            'fss_weekly_overview',
            'Weekly Financial Overview',
            array($this, 'weekly_overview_widget')
        );
        
        wp_add_dashboard_widget(
            'fss_alerts',
            'Financial Alerts',
            array($this, 'alerts_widget')
        );
    }
    
    public function show_admin_notices() {
        $unread_notices = FSS_Notifications::get_unread_notices();
        
        foreach ($unread_notices as $index => $notice) {
            echo '<div class="notice notice-warning is-dismissible" data-notice-index="' . $index . '">';
            echo '<p><strong>' . esc_html($notice['subject']) . '</strong></p>';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '<p><small>' . wp_date('F d, Y H:i:s', strtotime($notice['time'])) . '</small></p>';
            echo '</div>';
        }
        
        if (!empty($unread_notices)) {
            ?>
            <script>
            jQuery(document).on('click', '.notice-dismiss', function() {
                var noticeIndex = jQuery(this).closest('.notice').data('notice-index');
                if (noticeIndex !== undefined) {
                    jQuery.post(ajaxurl, {
                        action: 'fss_mark_notice_read',
                        notice_index: noticeIndex,
                        nonce: '<?php echo wp_create_nonce('fss_admin_nonce'); ?>'
                    });
                }
            });
            </script>
            <?php
        }
    }
    
    public function handle_admin_actions() {
        if (isset($_POST['action']) && $_POST['action'] === 'fss_mark_notice_read') {
            if (wp_verify_nonce($_POST['nonce'], 'fss_admin_nonce')) {
                FSS_Notifications::mark_notice_read(intval($_POST['notice_index']));
            }
        }
    }
    
    public function admin_page() {
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $today = $lagos_time->format('Y-m-d');
        
        $summary = FSS_Database::get_daily_summary($today);
        $orders_data = FSS_Database::get_orders_data_for_date($today);
        
        ?>
        <div class="wrap">
            <h1>Financial Summary Dashboard</h1>
            
            <div class="fss-admin-dashboard">
                <!-- Quick Stats -->
                <div class="fss-admin-stats">
                    <div class="fss-stat-card">
                        <h3>Today's Sales</h3>
                        <div class="fss-stat-number">₦<?php echo number_format($orders_data['total_sales'] + ($summary->total_sales ?? 0), 2); ?></div>
                    </div>
                    
                    <div class="fss-stat-card">
                        <h3>Cash Left</h3>
                        <div class="fss-stat-number">₦<?php echo number_format($summary->cash_left ?? 0, 2); ?></div>
                    </div>
                    
                    <div class="fss-stat-card">
                        <h3>Today's Expenses</h3>
                        <div class="fss-stat-number">₦<?php echo number_format($summary->expense ?? 0, 2); ?></div>
                    </div>
                    
                    <div class="fss-stat-card">
                        <h3>Net Flow</h3>
                        <div class="fss-stat-number">₦<?php echo number_format(($orders_data['total_sales'] + ($summary->total_sales ?? 0)) - ($summary->expense ?? 0), 2); ?></div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="fss-admin-section">
                    <h2>Recent Activity</h2>
                    <?php $this->render_recent_activity(); ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="fss-admin-section">
                    <h2>Quick Actions</h2>
                    <div class="fss-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=financial-summary-history'); ?>" class="button button-primary">View History</a>
                        <a href="<?php echo admin_url('admin.php?page=financial-summary-analytics'); ?>" class="button">Analytics</a>
                        <button class="button" onclick="exportTodayAdmin()">Export Today</button>
                        <button class="button" onclick="reconcileToday()">Reconcile</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function exportTodayAdmin() {
            window.open('<?php echo admin_url('admin-ajax.php'); ?>?action=fss_export_day&date=<?php echo $today; ?>&nonce=<?php echo wp_create_nonce('fss_nonce'); ?>', '_blank');
        }
        
        function reconcileToday() {
            // Implement reconciliation modal for admin
            alert('Reconciliation feature - to be implemented');
        }
        </script>
        <?php
    }
    
    public function history_page() {
        ?>
        <div class="wrap">
            <h1>Financial History & Reports</h1>
            
            <div class="fss-admin-history">
                <!-- Filters -->
                <div class="fss-admin-filters">
                    <input type="date" id="filter-date-from" placeholder="From Date">
                    <input type="date" id="filter-date-to" placeholder="To Date">
                    <input type="text" id="filter-created-by" placeholder="Created By">
                    <button class="button button-primary" id="apply-filters">Apply Filters</button>
                    <button class="button" id="clear-filters">Clear Filters</button>
                    <button class="button" id="admin-export-filtered">Export Results</button>
                    <button class="button button-secondary" id="admin-fix-negatives" style="margin-left: 10px;">🔧 Fix Negative Values</button>
                </div>
                
                <!-- History Table Container -->
                <div id="fss-history-table-container">
                    <!-- Table loaded via AJAX -->
                </div>
                
                <!-- Pagination -->
                <div id="fss-history-pagination">
                    <!-- Pagination loaded via AJAX -->
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // The loadHistoryTable function is defined in frontend.js and will be available
            // It uses the filter IDs: filter-date-from, filter-date-to, filter-created-by
            
            $('#admin-fix-negatives').on('click', function() {
                if (!confirm('This will fix all negative values in the database. Continue?')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('Fixing...');
                
                $.post(ajaxurl, {
                    action: 'fss_fix_negative_values',
                    nonce: '<?php echo wp_create_nonce('fss_nonce'); ?>'
                }, function(response) {
                    $('#admin-fix-negatives').prop('disabled', false).text('🔧 Fix Negative Values');
                    
                    if (response.success) {
                        alert(response.data.message);
                        if (typeof loadHistoryTable === 'function') {
                            loadHistoryTable(1);
                        }
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function analytics_page() {
        ?>
        <div class="wrap">
            <h1>Financial Analytics</h1>
            
            <div class="fss-admin-analytics">
                <!-- Period Selector -->
                <div class="fss-analytics-controls">
                    <select id="admin-analytics-period">
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="365">Last Year</option>
                    </select>
                    <button class="button" id="admin-refresh-analytics">Refresh</button>
                </div>
                
                <!-- Charts Grid -->
                <div class="fss-admin-charts">
                    <div class="fss-chart-container">
                        <h3>Sales Trend</h3>
                        <canvas id="admin-sales-chart"></canvas>
                    </div>
                    
                    <div class="fss-chart-container">
                        <h3>Cash Flow Analysis</h3>
                        <canvas id="admin-cashflow-chart"></canvas>
                    </div>
                    
                    <div class="fss-chart-container">
                        <h3>Expense Breakdown</h3>
                        <canvas id="admin-expense-chart"></canvas>
                    </div>
                    
                    <div class="fss-chart-container">
                        <h3>Payment Method Distribution</h3>
                        <canvas id="admin-payment-chart"></canvas>
                    </div>
                </div>
                
                <!-- Key Metrics -->
                <div class="fss-admin-metrics">
                    <h2>Key Performance Indicators</h2>
                    <div id="admin-kpis"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            loadAdminAnalytics();
            
            $('#admin-refresh-analytics').on('click', function() {
                loadAdminAnalytics();
            });
            
            $('#admin-analytics-period').on('change', function() {
                loadAdminAnalytics();
            });
            
            function loadAdminAnalytics() {
                // Implementation placeholder
            }
        });
        </script>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            update_option('fss_discrepancy_threshold', floatval($_POST['discrepancy_threshold']));
            update_option('fss_notification_emails', array_map('sanitize_email', explode(',', $_POST['notification_emails'])));
            update_option('fss_auto_reconcile', isset($_POST['auto_reconcile']));
            update_option('fss_daily_reminder_time', sanitize_text_field($_POST['daily_reminder_time']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $discrepancy_threshold = get_option('fss_discrepancy_threshold', 10000);
        $notification_emails = implode(',', get_option('fss_notification_emails', array(get_option('admin_email'))));
        $auto_reconcile = get_option('fss_auto_reconcile', false);
        $daily_reminder_time = get_option('fss_daily_reminder_time', '18:00');
        
        ?>
        <div class="wrap">
            <h1>Financial Summary Settings</h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Discrepancy Alert Threshold</th>
                        <td>
                            <input type="number" name="discrepancy_threshold" value="<?php echo $discrepancy_threshold; ?>" step="0.01">
                            <p class="description">Send alert when cash discrepancy exceeds this amount (₦)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Notification Emails</th>
                        <td>
                            <input type="text" name="notification_emails" value="<?php echo esc_attr($notification_emails); ?>" class="regular-text">
                            <p class="description">Comma-separated list of email addresses for notifications</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Auto Reconciliation</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_reconcile" <?php checked($auto_reconcile); ?>>
                                Enable automatic end-of-day reconciliation reminders
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Daily Reminder Time</th>
                        <td>
                            <input type="time" name="daily_reminder_time" value="<?php echo esc_attr($daily_reminder_time); ?>">
                            <p class="description">Time to send daily summary reminders (Lagos timezone)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function today_summary_widget() {
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $today = $lagos_time->format('Y-m-d');
        
        $summary = FSS_Database::get_daily_summary($today);
        $orders_data = FSS_Database::get_orders_data_for_date($today);
        
        ?>
        <div class="fss-widget-content">
            <div class="fss-widget-stat">
                <span class="fss-widget-label">Total Sales:</span>
                <span class="fss-widget-value">₦<?php echo number_format($orders_data['total_sales'] + ($summary->total_sales ?? 0), 2); ?></span>
            </div>
            
            <div class="fss-widget-stat">
                <span class="fss-widget-label">Cash Left:</span>
                <span class="fss-widget-value">₦<?php echo number_format($summary->cash_left ?? 0, 2); ?></span>
            </div>
            
            <div class="fss-widget-stat">
                <span class="fss-widget-label">Expenses:</span>
                <span class="fss-widget-value">₦<?php echo number_format($summary->expense ?? 0, 2); ?></span>
            </div>
            
            <div class="fss-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=financial-summary'); ?>" class="button button-small">View Details</a>
            </div>
        </div>
        <?php
    }
    
    public function weekly_overview_widget() {
        $lagos_time = new DateTime('now', new DateTimeZone('Africa/Lagos'));
        $end_date = $lagos_time->format('Y-m-d');
        $start_date = $lagos_time->modify('-6 days')->format('Y-m-d');
        
        global $wpdb;
        $weekly_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(total_sales) as weekly_sales,
                SUM(expense) as weekly_expenses,
                AVG(total_sales) as avg_daily_sales
            FROM {$wpdb->prefix}fss_daily_summaries 
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
        
        ?>
        <div class="fss-widget-content">
            <div class="fss-widget-stat">
                <span class="fss-widget-label">Weekly Sales:</span>
                <span class="fss-widget-value">₦<?php echo number_format($weekly_data->weekly_sales ?? 0, 2); ?></span>
            </div>
            
            <div class="fss-widget-stat">
                <span class="fss-widget-label">Weekly Expenses:</span>
                <span class="fss-widget-value">₦<?php echo number_format($weekly_data->weekly_expenses ?? 0, 2); ?></span>
            </div>
            
            <div class="fss-widget-stat">
                <span class="fss-widget-label">Daily Average:</span>
                <span class="fss-widget-value">₦<?php echo number_format($weekly_data->avg_daily_sales ?? 0, 2); ?></span>
            </div>
            
            <div class="fss-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=financial-summary-analytics'); ?>" class="button button-small">View Analytics</a>
            </div>
        </div>
        <?php
    }
    
    public function alerts_widget() {
        $unread_notices = FSS_Notifications::get_unread_notices();
        
        if (empty($unread_notices)) {
            echo '<p>No active alerts.</p>';
            return;
        }
        
        ?>
        <div class="fss-alerts-list">
            <?php foreach (array_slice($unread_notices, 0, 3) as $notice): ?>
            <div class="fss-alert-item">
                <strong><?php echo esc_html($notice['subject']); ?></strong>
                <p><?php echo esc_html(substr($notice['message'], 0, 100) . '...'); ?></p>
                <small><?php echo wp_date('M d, H:i', strtotime($notice['time'])); ?></small>
            </div>
            <?php endforeach; ?>
            
            <?php if (count($unread_notices) > 3): ?>
            <p><a href="<?php echo admin_url('admin.php?page=financial-summary'); ?>">View all <?php echo count($unread_notices); ?> alerts</a></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_recent_activity() {
        global $wpdb;
        $recent_entries = $wpdb->get_results("
            SELECT h.*, s.date, s.total_sales, s.cash_left
            FROM {$wpdb->prefix}fss_history_entries h
            JOIN {$wpdb->prefix}fss_daily_summaries s ON h.summary_id = s.id
            ORDER BY h.created_at DESC 
            LIMIT 10
        ");
        
        if (empty($recent_entries)) {
            echo '<p>No recent activity.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped fss-admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Activity</th>
                    <th>Amount</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_entries as $entry): ?>
                <tr>
                    <td><?php echo date('M d', strtotime($entry->date)); ?></td>
                    <td><?php echo esc_html($entry->created_by); ?></td>
                    <td>
                        <?php if ($entry->extras > 0): ?>Added Extras<?php endif; ?>
                        <?php if ($entry->expense > 0): ?><?php echo ($entry->extras > 0 ? ' / ' : ''); ?>Added Expense<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entry->extras > 0): ?>+₦<?php echo number_format($entry->extras, 2); ?><?php endif; ?>
                        <?php if ($entry->expense > 0): ?><?php echo ($entry->extras > 0 ? ' ' : ''); ?>-₦<?php echo number_format($entry->expense, 2); ?><?php endif; ?>
                    </td>
                    <td><?php echo wp_date('H:i', strtotime($entry->created_at)); ?></td>
                    <td>
                        <!-- Added view-details-btn for unified frontend handler while keeping existing class -->
                        <button class="button button-small admin-view-details view-details-btn" 
                                data-date="<?php echo esc_attr($entry->date); ?>"
                                title="View full details">
                            <span class="dashicons dashicons-visibility"></span> View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Admin Detail Modal -->
        <div id="fss-admin-detail-modal" class="fss-modal" style="display: none;">
            <div class="fss-modal-content">
                <div class="fss-modal-header">
                    <h2 id="admin-modal-title">Financial Details</h2>
                    <span class="fss-modal-close">&times;</span>
                </div>
                <div class="fss-modal-body" id="admin-modal-body-content">
                    <div class="loading-spinner">Loading...</div>
                </div>
            </div>
        </div>
        <?php
    }
}