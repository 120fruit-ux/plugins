<?php
/**
 * Fix for Missing History and Analytics Shortcodes
 */

class FSS_Frontend_Shortcodes_Fix {
    
    public function __construct() {
        // Register the missing shortcodes
        add_shortcode('financial_history', array($this, 'render_history'));
        add_shortcode('financial_analytics', array($this, 'render_analytics'));
        add_shortcode('financial_dashboard_widgets', array($this, 'render_dashboard_widgets'));
        
        // Enqueue required scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_assets'));
    }
    
    /**
     * Render financial history shortcode
     */
    public function render_history($atts) {
        $atts = shortcode_atts(array(
            'days' => 30,
            'per_page' => 10,
            'show_filters' => 'true'
        ), $atts);
        
        $days = intval($atts['days']);
        $per_page = intval($atts['per_page']);
        $show_filters = $atts['show_filters'] === 'true';
        
        // Get history data
        $end_date = wp_date('Y-m-d', null, new DateTimeZone('Africa/Lagos'));
        $start_date = wp_date('Y-m-d', strtotime("-{$days} days"), new DateTimeZone('Africa/Lagos'));
        
        $filters = array(
            'date_from' => $start_date,
            'date_to' => $end_date
        );
        
        $history_data = FSS_Database::get_paginated_summaries(1, $per_page, $filters);
        
        ob_start();
        ?>
        <div class="fss-history-container">
            <h3>📊 Financial History</h3>
            
            <?php if ($show_filters): ?>
            <div class="fss-history-filters">
                <form class="fss-filter-form">
                    <div class="filter-row">
                        <label>From Date:</label>
                        <input type="date" name="date_from" value="<?php echo $start_date; ?>">
                        
                        <label>To Date:</label>
                        <input type="date" name="date_to" value="<?php echo $end_date; ?>">
                        
                        <button type="button" class="fss-btn fss-btn-secondary" onclick="filterHistory()">
                            Filter
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="fss-history-table">
                <?php if (!empty($history_data['results'])): ?>
                <table class="fss-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Total Sales</th>
            <th>Orders</th>
            <th>Cash</th>
            <th>Transfer/Card</th>
            <th>Expenses</th>
            <th>Net Profit</th>
            <th>Cash Left</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($history_data['results'] as $row): ?>
        <tr>
            <td><?php echo wp_date('M d, Y', strtotime($row->date), new DateTimeZone('Africa/Lagos')); ?></td>
            <td>₦<?php echo number_format($row->total_sales, 2); ?></td>
            <td><?php echo number_format($row->order_count ?: 0); ?></td>
            <td>₦<?php echo number_format($row->cash, 2); ?></td>
            <td>₦<?php echo number_format($row->transfer_card, 2); ?></td>
            <td>₦<?php echo number_format($row->expense, 2); ?></td>
            <td class="<?php echo $row->net_profit >= 0 ? 'profit' : 'loss'; ?>">
                ₦<?php echo number_format($row->net_profit, 2); ?>
            </td>
            <td>₦<?php echo number_format($row->cash_left, 2); ?></td>
            <td>
                <button class="fss-btn fss-btn-small view-details-btn" 
                        data-date="<?php echo $row->date; ?>" 
                        title="View detailed information">
                    <span class="dashicons dashicons-visibility"></span> View
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Detailed View Modal -->
<div id="fss-detail-modal" class="fss-modal" style="display: none;">
    <div class="fss-modal-content">
        <div class="fss-modal-header">
            <h2 id="modal-title">Financial Details</h2>
            <span class="fss-modal-close">&times;</span>
        </div>
        <div class="fss-modal-body" id="modal-body-content">
            <div class="loading-spinner">Loading...</div>
        </div>
    </div>
</div>

                
                <?php if ($history_data['pages'] > 1): ?>
                <div class="fss-pagination">
                    <?php for ($i = 1; $i <= $history_data['pages']; $i++): ?>
                    <a href="#" class="page-btn <?php echo $i == 1 ? 'active' : ''; ?>" data-page="<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <p class="no-data">No financial data found for the selected period.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .fss-history-container {
            max-width: 100%;
            margin: 20px 0;
        }
        
        .fss-history-filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-row label {
            font-weight: 600;
            color: #333;
        }
        
        .filter-row input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .fss-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .fss-table th,
        .fss-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .fss-table th {
            background: #FF0000;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        .fss-table tr:hover {
            background: #f8f9fa;
        }
        
        .profit {
            color: #28a745;
            font-weight: 600;
        }
        
        .loss {
            color: #dc3545;
            font-weight: 600;
        }
        
        .fss-pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .page-btn {
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            text-decoration: none;
            border-radius: 4px;
            color: #333;
        }
        
        .page-btn.active,
        .page-btn:hover {
            background: #FF0000;
            color: white;
            border-color: #FF0000;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .fss-table {
                font-size: 14px;
            }
            
            .fss-table th,
            .fss-table td {
                padding: 8px;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        </style>
        
        <script>
        function filterHistory() {
            // Get filter values
            const dateFrom = document.querySelector('input[name="date_from"]').value;
            const dateTo = document.querySelector('input[name="date_to"]').value;
            
            // Reload page with new parameters
            const url = new URL(window.location);
            url.searchParams.set('date_from', dateFrom);
            url.searchParams.set('date_to', dateTo);
            window.location.href = url.toString();
        }
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render financial analytics shortcode
     */
    public function render_analytics($atts) {
        $atts = shortcode_atts(array(
            'period' => 30,
            'chart_type' => 'line'
        ), $atts);
        
        $period = intval($atts['period']);
        $chart_type = sanitize_text_field($atts['chart_type']);
        
        // Get analytics data
        $end_date = wp_date('Y-m-d', null, new DateTimeZone('Africa/Lagos'));
        $start_date = wp_date('Y-m-d', strtotime("-{$period} days"), new DateTimeZone('Africa/Lagos'));
        
        $analytics_data = $this->get_analytics_data($start_date, $end_date);
        
        ob_start();
        ?>
        <div class="fss-analytics-container">
            <h3>📈 Financial Analytics</h3>
            
            <!-- Summary Cards -->
            <div class="analytics-cards">
                <div class="analytics-card">
                    <div class="card-icon">💰</div>
                    <div class="card-content">
                        <div class="card-value">₦<?php echo number_format($analytics_data['total_sales'], 0); ?></div>
                        <div class="card-label">Total Sales</div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-icon">📊</div>
                    <div class="card-content">
                        <div class="card-value">₦<?php echo number_format($analytics_data['avg_daily_sales'], 0); ?></div>
                        <div class="card-label">Avg Daily Sales</div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-icon">🛒</div>
                    <div class="card-content">
                        <div class="card-value"><?php echo number_format($analytics_data['total_orders']); ?></div>
                        <div class="card-label">Total Orders</div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-icon">💵</div>
                    <div class="card-content">
                        <div class="card-value">₦<?php echo number_format($analytics_data['avg_order_value'], 0); ?></div>
                        <div class="card-label">Avg Order Value</div>
                    </div>
                </div>
            </div>
            
            <!-- Chart Container -->
            <div class="chart-container">
                <canvas id="fss-analytics-chart" width="400" height="200"></canvas>
            </div>
            
            <!-- Data Table -->
            <div class="analytics-breakdown">
                <h4>📋 Daily Breakdown</h4>
                <table class="fss-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sales</th>
                            <th>Orders</th>
                            <th>Avg Order</th>
                            <th>Cash %</th>
                            <th>Card %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics_data['daily_data'] as $day): ?>
                        <tr>
                            <td><?php echo wp_date('M d', strtotime($day->date), new DateTimeZone('Africa/Lagos')); ?></td>
                            <td>₦<?php echo number_format($day->total_sales, 0); ?></td>
                            <td><?php echo $day->order_count ?: 0; ?></td>
                            <td>₦<?php echo $day->order_count > 0 ? number_format($day->total_sales / $day->order_count, 0) : '0'; ?></td>
                            <td><?php echo $day->total_sales > 0 ? round(($day->cash / $day->total_sales) * 100, 1) : 0; ?>%</td>
                            <td><?php echo $day->total_sales > 0 ? round(($day->transfer_card / $day->total_sales) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .fss-analytics-container {
            max-width: 100%;
            margin: 20px 0;
        }
        
        .analytics-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .analytics-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .card-icon {
            font-size: 24px;
            margin-right: 15px;
        }
        
        .card-value {
            font-size: 24px;
            font-weight: 700;
            color: #FF0000;
            line-height: 1.2;
        }
        
        .card-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .analytics-breakdown {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .analytics-breakdown h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        </style>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('fss-analytics-chart').getContext('2d');
            const dailyData = <?php echo json_encode(array_values($analytics_data['daily_data'])); ?>;
            
            new Chart(ctx, {
                type: '<?php echo $chart_type; ?>',
                data: {
                    labels: dailyData.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                    datasets: [{
                        label: 'Daily Sales',
                        data: dailyData.map(d => d.total_sales),
                        borderColor: '#FF0000',
                        backgroundColor: 'rgba(255, 0, 0, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₦' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Sales: ₦' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render dashboard widgets shortcode
     */
    public function render_dashboard_widgets($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'grid'
        ), $atts);
        
        // Get today's data
        $today = wp_date('Y-m-d', null, new DateTimeZone('Africa/Lagos'));
        $summary = FSS_Database::get_daily_summary($today);
        $orders_data = fss_get_tof_orders_direct($today);
        
        ob_start();
        ?>
        <div class="fss-dashboard-widgets">
            <div class="widget-grid">
                <!-- Today's Summary Widget -->
                <div class="dashboard-widget">
                    <h4>📊 Today's Summary</h4>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <span class="stat-value">₦<?php echo number_format($orders_data['total_sales'], 0); ?></span>
                            <span class="stat-label">Total Sales</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $orders_data['order_count']; ?></span>
                            <span class="stat-label">Orders</span>
                        </div>
                    </div>
                </div>
                
                <!-- Cash Flow Widget -->
                <div class="dashboard-widget">
                    <h4>💰 Cash Flow</h4>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <span class="stat-value">₦<?php echo number_format($orders_data['cash'], 0); ?></span>
                            <span class="stat-label">Cash Sales</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">₦<?php echo number_format($summary ? $summary->cash_left : 0, 0); ?></span>
                            <span class="stat-label">Cash Left</span>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Widget -->
                <div class="dashboard-widget">
                    <h4>📈 Performance</h4>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <span class="stat-value">₦<?php echo $orders_data['order_count'] > 0 ? number_format($orders_data['total_sales'] / $orders_data['order_count'], 0) : '0'; ?></span>
                            <span class="stat-label">Avg Order</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $orders_data['total_sales'] > 0 ? round(($orders_data['cash'] / $orders_data['total_sales']) * 100, 1) : 0; ?>%</span>
                            <span class="stat-label">Cash Ratio</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .fss-dashboard-widgets {
            margin: 20px 0;
        }
        
        .widget-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .dashboard-widget {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .dashboard-widget h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
        }
        
        .widget-stats {
            display: flex;
            justify-content: space-between;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: #FF0000;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get analytics data for specified period
     */
    private function get_analytics_data($start_date, $end_date) {
        global $wpdb;
        
        $summary_table = $wpdb->prefix . 'fss_daily_summaries';
        
        // Get daily data
        $daily_data = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM $summary_table
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        ", $start_date, $end_date));
        
        // Calculate totals
        $totals = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(total_sales) as total_sales,
                SUM(order_count) as total_orders,
                AVG(total_sales) as avg_daily_sales,
                COUNT(*) as active_days
            FROM $summary_table
            WHERE date BETWEEN %s AND %s
            AND total_sales > 0
        ", $start_date, $end_date));
        
        $avg_order_value = $totals->total_orders > 0 ? $totals->total_sales / $totals->total_orders : 0;
        
        return array(
            'daily_data' => $daily_data,
            'total_sales' => floatval($totals->total_sales ?: 0),
            'total_orders' => intval($totals->total_orders ?: 0),
            'avg_daily_sales' => floatval($totals->avg_daily_sales ?: 0),
            'avg_order_value' => $avg_order_value,
            'active_days' => intval($totals->active_days ?: 0)
        );
    }
    
    /**
     * Enqueue assets for shortcodes
     */
    public function enqueue_shortcode_assets() {
        global $post;
        
        if ($post && (
            has_shortcode($post->post_content, 'financial_history') ||
            has_shortcode($post->post_content, 'financial_analytics') ||
            has_shortcode($post->post_content, 'financial_dashboard_widgets')
        )) {
            wp_enqueue_style(
                'fss-shortcodes',
                FSS_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                FSS_VERSION
            );
        }
    }
}

// Initialize the shortcodes fix
new FSS_Frontend_Shortcodes_Fix();