<?php
/**
 * Unified, early, safe registration for [financial_dashboard_widgets]
 *
 * Ensures the shortcode is always available and rendered even if:
 * - Other classes registering it load too late
 * - Theme/page builder outputs raw text
 * - Another plugin removed the tag from $shortcode_tags
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('fss_register_dashboard_widgets_shortcode')) {

    /**
     * Core render callback (function-based for reliability)
     */
    function fss_render_dashboard_widgets_shortcode($atts = array()) {

        $atts = shortcode_atts(array(
            'layout' => 'grid'
        ), $atts, 'financial_dashboard_widgets');

        // Today (Lagos)
        $today     = wp_date('Y-m-d', null, new DateTimeZone('Africa/Lagos'));
        $summary   = function_exists('FSS_Database::get_daily_summary')
            ? FSS_Database::get_daily_summary($today)
            : null;

        // Try direct TOF helper if available, else fallback to DB method
        if (function_exists('fss_get_tof_orders_direct')) {
            $orders_data = fss_get_tof_orders_direct($today);
        } else {
            $orders_data = class_exists('FSS_Database')
                ? FSS_Database::get_orders_data_for_date($today)
                : array(
                    'total_sales'   => 0,
                    'order_count'   => 0,
                    'cash'          => 0,
                    'transfer_card' => 0,
                    'delivery'      => 0
                );
        }

        ob_start(); ?>
        <div class="fss-dashboard-widgets fss-dashboard-widgets-<?php echo esc_attr($atts['layout']); ?>">
            <div class="widget-grid">
                <!-- Today's Summary -->
                <div class="dashboard-widget">
                    <h4>📊 Today's Summary</h4>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <span class="stat-value">₦<?php echo number_format($orders_data['total_sales'] ?? 0, 0); ?></span>
                            <span class="stat-label">Total Sales</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($orders_data['order_count'] ?? 0); ?></span>
                            <span class="stat-label">Orders</span>
                        </div>
                    </div>
                </div>

                <!-- Cash Flow -->
                <div class="dashboard-widget">
                    <h4>💰 Cash Flow</h4>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <span class="stat-value">₦<?php echo number_format($orders_data['cash'] ?? 0, 0); ?></span>
                            <span class="stat-label">Cash Sales</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">₦<?php echo number_format(($summary ? $summary->cash_left : 0), 0); ?></span>
                            <span class="stat-label">Cash Left</span>
                        </div>
                    </div>
                </div>

                <!-- Performance -->
                <div class="dashboard-widget">
                    <h4>📈 Performance</h4>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <span class="stat-value">
                                ₦<?php
                                $avg_order = (!empty($orders_data['order_count']))
                                    ? ($orders_data['total_sales'] / max(1, $orders_data['order_count']))
                                    : 0;
                                echo number_format($avg_order, 0);
                                ?>
                            </span>
                            <span class="stat-label">Avg Order</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">
                                <?php
                                $cash_ratio = (!empty($orders_data['total_sales']))
                                    ? (($orders_data['cash'] / max(1, $orders_data['total_sales'])) * 100)
                                    : 0;
                                echo round($cash_ratio, 1); ?>%
                            </span>
                            <span class="stat-label">Cash Ratio</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .fss-dashboard-widgets .widget-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px,1fr));
                gap: 20px;
            }
            .fss-dashboard-widgets .dashboard-widget {
                background:#fff;
                padding:18px 20px;
                border-radius:12px;
                border:1px solid #e0e0e0;
                box-shadow:0 2px 6px rgba(0,0,0,.06);
            }
            .fss-dashboard-widgets .dashboard-widget h4 {
                margin:0 0 14px;
                font-size:15px;
                font-weight:700;
                color:#333;
            }
            .fss-dashboard-widgets .widget-stats {
                display:flex;
                justify-content:space-between;
                gap:16px;
            }
            .fss-dashboard-widgets .stat-item {
                flex:1;
                text-align:center;
            }
            .fss-dashboard-widgets .stat-value {
                display:block;
                font-size:20px;
                font-weight:700;
                color:#FF0000;
                line-height:1.1;
            }
            .fss-dashboard-widgets .stat-label {
                font-size:11px;
                text-transform:uppercase;
                letter-spacing:.5px;
                color:#666;
                margin-top:4px;
                font-weight:500;
            }
            @media (max-width:600px){
                .fss-dashboard-widgets .widget-stats {
                    flex-direction:column;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Register shortcode if not already registered
     */
    function fss_register_dashboard_widgets_shortcode() {
        global $shortcode_tags;
        if (empty($shortcode_tags['financial_dashboard_widgets'])) {
            add_shortcode('financial_dashboard_widgets', 'fss_render_dashboard_widgets_shortcode');
        }
    }

    // Early registration (init priority 5 so it's there before content runs)
    add_action('init', 'fss_register_dashboard_widgets_shortcode', 5);

    /**
     * Fallback filter: if raw shortcode text slipped through, render it.
     * This handles themes or builders echoing the literal text without do_shortcode().
     */
    function fss_force_render_dashboard_widgets_shortcode($content) {
        if (strpos($content, '[financial_dashboard_widgets') === false) {
            return $content;
        }

        // Ensure it's registered
        fss_register_dashboard_widgets_shortcode();

        // Replace each occurrence safely
        $content = preg_replace_callback(
            '/\[financial_dashboard_widgets([^\]]*)\]/',
            function ($m) {
                // Reconstruct original (with attrs) and run do_shortcode
                return do_shortcode($m[0]);
            },
            $content
        );
        return $content;
    }
    add_filter('the_content', 'fss_force_render_dashboard_widgets_shortcode', 12);
}