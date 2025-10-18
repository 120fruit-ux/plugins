<?php
/**
 * Financial Summary System - Enhanced Frontend with Take Order Form Integration
 */

class FSS_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('financial_summary_form', array($this, 'render_summary_form'));
        add_shortcode('financial_dashboard_widgets', array($this, 'render_dashboard_widgets'));
        
        // Real-time order integration hooks
        add_action('wp_ajax_fss_get_live_orders', array($this, 'get_live_orders'));
        add_action('wp_ajax_nopriv_fss_get_live_orders', array($this, 'get_live_orders'));
        add_action('wp_ajax_fss_refresh_order_data', array($this, 'refresh_order_data'));
        add_action('wp_ajax_fss_submit_summary', array($this, 'submit_summary'));
        
        // Hook into Take Order Form submissions (if available)
        add_action('take_order_form_submitted', array($this, 'on_order_submitted'), 10, 2);
        add_action('wp_insert_post', array($this, 'check_order_submission'), 10, 2);
        
        // Auto-refresh setup
        add_action('wp_footer', array($this, 'add_auto_refresh_script'));
    }
    
    /**
     * Check if current user is staff (not admin)
     */
    private function is_staff_user() {
        return current_user_can('edit_posts') && !current_user_can('manage_options');
    }
    
    /**
     * Get readonly attributes for staff users
     */
    private function get_readonly_attr($field_name) {
        $readonly_fields = array(
            'total_sales',
            'transfer_card', 
            'cash',
            'delivery',
            'cash_left',
            'old_cash',
            'cash_left_market_card'
        );
        
        if ($this->is_staff_user() && in_array($field_name, $readonly_fields)) {
            return 'readonly="readonly" disabled="disabled" tabindex="-1"';
        }
        
        return '';
    }
    
    /**
     * Get CSS classes for staff readonly fields
     */
    private function get_readonly_class($field_name) {
        $readonly_fields = array(
            'total_sales',
            'transfer_card', 
            'cash',
            'delivery',
            'cash_left',
            'old_cash',
            'cash_left_market_card'
        );
        
        if ($this->is_staff_user() && in_array($field_name, $readonly_fields)) {
            return 'fss-readonly-field';
        }
        
        return '';
    }
    
    /**
     * Get integration status with staff override
     */
    private function get_integration_status_for_display() {
        $status = FSS_Database::get_integration_status();
        
        // If user is staff and we have live data, show as connected
        if ($this->is_staff_user()) {
            $today = wp_date('Y-m-d', null, new DateTimeZone('Africa/Lagos'));
            $orders_data = fss_get_tof_orders_direct($today);
            
            if ($orders_data['total_sales'] > 0 || $orders_data['order_count'] > 0) {
                return array(
                    'connected' => true,
                    'table_name' => 'wp_tof_orders',
                    'data_quality' => 'good',
                    'staff_view' => true
                );
            }
        }
        
        return $status;
    }
    
    public function enqueue_scripts() {
        if ($this->should_load_assets()) {
            wp_enqueue_script(
                'fss-live-orders',
                FSS_PLUGIN_URL . 'assets/js/live-orders.js',
                array('jquery'),
                FSS_VERSION,
                true
            );
            
            wp_localize_script('fss-live-orders', 'fss_live', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fss_live_nonce'),
                'refresh_interval' => 30000, // 30 seconds
                'current_date' => wp_date('Y-m-d'),
                'is_staff' => $this->is_staff_user(),
                'strings' => array(
                    'refreshing' => __('Refreshing order data...', 'financial-summary-system'),
                    'updated' => __('Order data updated!', 'financial-summary-system'),
                    'error' => __('Failed to refresh order data', 'financial-summary-system'),
                    'no_orders' => __('No orders found for today', 'financial-summary-system'),
                    'readonly_message' => __('This field is read-only for staff users', 'financial-summary-system')
                )
            ));
        }
    }
    
    public function render_summary_form($atts) {
        $atts = shortcode_atts(array(
            'date' => wp_date('Y-m-d'),
            'auto_refresh' => 'true',
            'show_live_orders' => 'true'
        ), $atts);
        
        $date = sanitize_text_field($atts['date']);
        $auto_refresh = $atts['auto_refresh'] === 'true';
        $show_live_orders = $atts['show_live_orders'] === 'true';
        
        // Get existing summary
        $summary = FSS_Database::get_daily_summary($date);
        
        // Get live orders data
        $orders_data = FSS_Database::get_orders_data_for_date($date);
        
        // Get integration status (with staff override)
        $integration_status = $this->get_integration_status_for_display();
        
        ob_start();
        ?>
        <div class="fss-container" data-date="<?php echo $date; ?>" data-auto-refresh="<?php echo $auto_refresh ? 'true' : 'false'; ?>" data-is-staff="<?php echo $this->is_staff_user() ? 'true' : 'false'; ?>">
            
            <!-- Integration Status Banner -->
            <?php if ($show_live_orders): ?>
            <div class="fss-integration-status">
                <div class="fss-status-card status-<?php echo $integration_status['connected'] ? 'connected' : 'disconnected'; ?>">
                    <div class="status-icon">
                        <?php if ($integration_status['connected']): ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-warning"></span>
                        <?php endif; ?>
                    </div>
                    <div class="status-info">
                        <h4>Take Order Form Integration</h4>
                        <?php if ($integration_status['connected']): ?>
                            <p>✅ Connected to: <strong><?php echo esc_html($integration_status['table_name']); ?></strong></p>
                            <p>📊 Data Quality: <span class="quality-<?php echo $integration_status['data_quality']; ?>"><?php echo ucfirst($integration_status['data_quality']); ?></span></p>
                            <p>📈 Today's Orders: <strong><?php echo $orders_data['order_count']; ?></strong> | Total: ₦<?php echo number_format($orders_data['total_sales'], 2); ?></p>
                            <?php if (isset($integration_status['staff_view']) && $integration_status['staff_view']): ?>
                                <p>👤 Staff View: Live data available</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>⚠️ Not connected to Take Order Form plugin</p>
                            <p>Orders must be entered manually</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($integration_status['connected']): ?>
                    <div class="status-actions">
                        <button type="button" class="fss-btn fss-btn-sm" id="refresh-orders-btn">
                            <span class="dashicons dashicons-update"></span> Refresh Orders
                        </button>
                        <span class="last-updated">Last updated: <span id="last-refresh-time"><?php echo wp_date('H:i:s', null, new DateTimeZone('Africa/Lagos')); ?></span></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Header with Live Data -->
            <div class="fss-header">
                <div class="fss-header-content">
                    <div class="fss-user-info">
                        <h1 class="fss-welcome">Financial Summary</h1>
                        <p class="fss-date">📅 <?php echo wp_date('l, F j, Y', strtotime($date), new DateTimeZone('Africa/Lagos')); ?></p>
                    </div>
                    <div class="fss-datetime">
                        <div id="fss-current-date"><?php echo wp_date('M d, Y', null, new DateTimeZone('Africa/Lagos')); ?></div>
                        <div id="fss-current-time"><?php echo wp_date('H:i:s', null, new DateTimeZone('Africa/Lagos')); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Live Orders Summary -->
            <?php if ($show_live_orders && $integration_status['connected']): ?>
            <div class="fss-live-orders" id="live-orders-summary">
                <h3>📋 Today's Live Orders</h3>
                <div class="live-orders-grid">
                    <div class="live-stat">
                        <div class="stat-icon">🛒</div>
                        <div class="stat-content">
                            <div class="stat-value" id="live-total-orders"><?php echo $orders_data['order_count']; ?></div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                    </div>
                    <div class="live-stat">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <div class="stat-value" id="live-total-sales">₦<?php echo number_format($orders_data['total_sales'], 0); ?></div>
                            <div class="stat-label">Total Sales</div>
                        </div>
                    </div>
                    <div class="live-stat">
                        <div class="stat-icon">💳</div>
                        <div class="stat-content">
                            <div class="stat-value" id="live-card-sales">₦<?php echo number_format($orders_data['transfer_card'], 0); ?></div>
                            <div class="stat-label">Card/Transfer</div>
                        </div>
                    </div>
                    <div class="live-stat">
                        <div class="stat-icon">💵</div>
                        <div class="stat-content">
                            <div class="stat-value" id="live-cash-sales">₦<?php echo number_format($orders_data['cash'], 0); ?></div>
                            <div class="stat-label">Cash Sales</div>
                        </div>
                    </div>
                    <div class="live-stat">
                        <div class="stat-icon">🚚</div>
                        <div class="stat-content">
                            <div class="stat-value" id="live-delivery-fees">₦<?php echo number_format($orders_data['delivery'], 0); ?></div>
                            <div class="stat-label">Delivery Fees</div>
                        </div>
                    </div>
                    <div class="live-stat">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <div class="stat-value" id="live-avg-order">₦<?php echo $orders_data['order_count'] > 0 ? number_format($orders_data['total_sales'] / $orders_data['order_count'], 0) : '0'; ?></div>
                            <div class="stat-label">Avg Order</div>
                        </div>
                    </div>
                </div>
                <div class="live-orders-status">
                    <span class="status-indicator <?php echo $orders_data['order_count'] > 0 ? 'active' : 'inactive'; ?>"></span>
                    <span class="status-text">
                        <?php if ($orders_data['order_count'] > 0): ?>
                            Live data updating every 30 seconds
                        <?php else: ?>
                            No orders yet today
                        <?php endif; ?>
                    </span>
                    <span class="refresh-spinner" id="refresh-spinner" style="display: none;">
                        <span class="dashicons dashicons-update spin"></span>
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Financial Summary Form -->
            <form id="fss-summary-form" class="fss-form" data-integration="<?php echo $integration_status['connected'] ? 'true' : 'false'; ?>">
                <?php wp_nonce_field('fss_submit_nonce', 'fss_nonce'); ?>
                <input type="hidden" name="date" value="<?php echo $date; ?>">
                <input type="hidden" name="action" value="fss_submit_summary">
                
                <!-- Order Data Section (Auto-populated from Take Order Form) -->
                <div class="fss-section-header">
                    <h3>📊 Sales Data 
                        <?php if ($integration_status['connected']): ?>
                            <span class="auto-badge">Auto-Updated</span>
                        <?php endif; ?>
                        <?php if ($this->is_staff_user()): ?>
                            <span class="staff-badge">Staff View</span>
                        <?php endif; ?>
                    </h3>
                </div>
                
                <div class="fss-form-row">
                    <label>Total Sales</label>
                    <input type="number" 
                           name="total_sales" 
                           id="total_sales" 
                           value="<?php echo $orders_data['total_sales']; ?>" 
                           step="0.01" 
                           <?php echo $this->get_readonly_attr('total_sales'); ?>
                           <?php echo $integration_status['connected'] ? 'readonly' : ''; ?>
                           class="<?php echo $integration_status['connected'] ? 'auto-field' : ''; ?> <?php echo $this->get_readonly_class('total_sales'); ?>">
                    <?php if ($integration_status['connected']): ?>
                        <small class="field-note">🔄 Auto-updated from Take Order Form</small>
                    <?php endif; ?>
                    <?php if ($this->is_staff_user()): ?>
                        <span class="readonly-indicator">🔒 Read Only</span>
                    <?php endif; ?>
                </div>
                
                <div class="fss-form-row">
                    <label>Transfer/Card Sales</label>
                    <input type="number" 
                           name="transfer_card" 
                           id="transfer_card" 
                           value="<?php echo $orders_data['transfer_card']; ?>" 
                           step="0.01" 
                           <?php echo $this->get_readonly_attr('transfer_card'); ?>
                           <?php echo $integration_status['connected'] ? 'readonly' : ''; ?>
                           class="<?php echo $integration_status['connected'] ? 'auto-field' : ''; ?> <?php echo $this->get_readonly_class('transfer_card'); ?>">
                    <?php if ($this->is_staff_user()): ?>
                        <span class="readonly-indicator">🔒 Read Only</span>
                    <?php endif; ?>
                </div>
                
                <div class="fss-form-row">
                    <label>Cash Sales</label>
                    <input type="number" 
                           name="cash" 
                           id="cash" 
                           value="<?php echo $orders_data['cash']; ?>" 
                           step="0.01" 
                           <?php echo $this->get_readonly_attr('cash'); ?>
                           <?php echo $integration_status['connected'] ? 'readonly' : ''; ?>
                           class="<?php echo $integration_status['connected'] ? 'auto-field' : ''; ?> <?php echo $this->get_readonly_class('cash'); ?>">
                    <?php if ($this->is_staff_user()): ?>
                        <span class="readonly-indicator">🔒 Read Only</span>
                    <?php endif; ?>
                </div>
                
                <div class="fss-form-row">
                    <label>Delivery Fees</label>
                    <input type="number" 
                           name="delivery" 
                           id="delivery" 
                           value="<?php echo $orders_data['delivery']; ?>" 
                           step="0.01" 
                           <?php echo $this->get_readonly_attr('delivery'); ?>
                           <?php echo $integration_status['connected'] ? 'readonly' : ''; ?>
                           class="<?php echo $integration_status['connected'] ? 'auto-field' : ''; ?> <?php echo $this->get_readonly_class('delivery'); ?>">
                    <?php if ($this->is_staff_user()): ?>
                        <span class="readonly-indicator">🔒 Read Only</span>
                    <?php endif; ?>
                </div>
                
                <!-- Manual Entry Section -->
                <div class="fss-section-header">
                    <h3>✏️ Manual Entries</h3>
                </div>
                
                <div class="fss-form-row">
                    <label>Extras Amount</label>
                    <input type="number" name="extras" id="extras" value="<?php echo $summary ? $summary->extras : ''; ?>" step="0.01" min="0">
                </div>
                
                <div class="fss-form-row">
                    <label>Extras Remark</label>
                    <input type="text" name="extras_remark" id="extras_remark" value="<?php echo $summary ? esc_attr($summary->extras_remark) : ''; ?>" placeholder="e.g., Tips, bonuses">
                </div>
                
                <div class="fss-form-row">
                    <label>Expenses Amount</label>
                    <input type="number" name="expense" id="expense" value="<?php echo $summary ? $summary->expense : ''; ?>" step="0.01" min="0">
                </div>
                
                <div class="fss-form-row">
                    <label>Expense Remark</label>
                    <input type="text" name="expense_remark" id="expense_remark" value="<?php echo $summary ? esc_attr($summary->expense_remark) : ''; ?>" placeholder="e.g., Fuel, supplies">
                </div>
                
                <!-- Cash Flow Section -->
                <div class="fss-section-header">
                    <h3>💰 Cash Flow</h3>
                </div>
                
                <div class="fss-form-row">
                    <label>Old Cash (Opening Balance)</label>
                    <input type="number" 
                           name="old_cash" 
                           id="old_cash" 
                           value="<?php echo $summary ? $summary->old_cash : get_option('fss_old_cash_' . $date, 0); ?>" 
                           step="0.01"
                           <?php echo $this->get_readonly_attr('old_cash'); ?>
                           class="<?php echo $this->get_readonly_class('old_cash'); ?>">
                    <?php if ($this->is_staff_user()): ?>
                        <span class="readonly-indicator">🔒 Read Only</span>
                    <?php endif; ?>
                </div>
                
                <div class="fss-form-row">
                    <label>Cash Left with Market Card</label>
                    <input type="number" 
                           name="cash_left_market_card" 
                           id="cash_left_market_card" 
                           value="<?php echo $summary ? $summary->cash_left_market_card : '0'; ?>" 
                           step="0.01"
                           <?php echo $this->get_readonly_attr('cash_left_market_card'); ?>
                           class="<?php echo $this->get_readonly_class('cash_left_market_card'); ?>">
                    <?php if ($this->is_staff_user()): ?>
                        <span class="readonly-indicator">🔒 Read Only</span>
                    <?php endif; ?>
                </div>
                
                <div class="fss-form-row">
                    <label>Cash Left (Calculated)</label>
                    <input type="number" 
                           name="cash_left" 
                           id="cash_left" 
                           value="<?php echo $summary ? $summary->cash_left : '0'; ?>" 
                           step="0.01" 
                           readonly 
                           class="calculated-field fss-readonly-field">
                    <small class="field-note">📊 Auto-calculated: (Cash Sales + Old Cash + Extras + Market Card Cash) - Expenses</small>
                    <span class="readonly-indicator">🔒 Calculated</span>
                </div>
                
                <!-- Form Actions -->
                <div class="fss-form-actions">
                    <?php if ($integration_status['connected']): ?>
                        <button type="button" class="fss-btn fss-btn-secondary" id="manual-refresh-btn">
                            <span class="dashicons dashicons-update"></span> Refresh Order Data
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="fss-submit-btn">
                        <span class="dashicons dashicons-saved"></span> 
                        <?php echo $summary ? 'Update Summary' : 'Save Summary'; ?>
                    </button>
                </div>
            </form>
            
            <!-- Success/Error Messages -->
            <div id="fss-messages"></div>
        </div>
        
        <style>
        .fss-integration-status {
            margin-bottom: 24px;
        }
        
        .fss-status-card {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-radius: 12px;
            border: 2px solid;
            background: white;
        }
        
        .fss-status-card.status-connected {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);
        }
        
        .fss-status-card.status-disconnected {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
        }
        
        .status-icon {
            font-size: 24px;
            margin-right: 16px;
            color: #28a745;
        }
        
        .status-disconnected .status-icon {
            color: #ffc107;
        }
        
        .status-info {
            flex: 1;
        }
        
        .status-info h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 700;
        }
        
        .status-info p {
            margin: 4px 0;
            font-size: 14px;
        }
        
        .quality-excellent { color: #28a745; font-weight: 600; }
        .quality-good { color: #007bff; font-weight: 600; }
        .quality-fair { color: #ffc107; font-weight: 600; }
        .quality-poor { color: #dc3545; font-weight: 600; }
        
        .status-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        
        .last-updated {
            font-size: 12px;
            color: #666;
        }
        
        .fss-live-orders {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }
        
        .fss-live-orders h3 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 18px;
            font-weight: 700;
        }
        
        .live-orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .live-stat {
            display: flex;
            align-items: center;
            padding: 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .live-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 24px;
            margin-right: 12px;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #FF0000;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .live-orders-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #dc3545;
        }
        
        .status-indicator.active {
            background: #28a745;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .fss-section-header {
            grid-column: 1 / -1;
            margin: 24px 0 16px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #FF0000;
        }
        
        .fss-section-header:first-child {
            margin-top: 0;
        }
        
        .fss-section-header h3 {
            margin: 0;
            color: #333;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .auto-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .staff-badge {
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .auto-field {
            background: #f8f9fa !important;
            border-color: #28a745 !important;
            position: relative;
        }
        
        .auto-field::after {
            content: '🔄';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .field-note {
            display: block;
            margin-top: 4px;
            color: #666;
            font-size: 12px;
            font-style: italic;
        }
        
        .calculated-field {
            background: #e9ecef !important;
            color: #495057 !important;
            font-weight: 600;
        }
        
        /* Staff Readonly Field Styles */
        .fss-readonly-field {
            background-color: #f8f9fa !important;
            cursor: not-allowed !important;
            border: 2px solid #6c757d !important;
            opacity: 0.8 !important;
            color: #495057 !important;
            pointer-events: none !important;
        }
        
        .fss-readonly-field:focus,
        .fss-readonly-field:active,
        .fss-readonly-field:hover {
            outline: none !important;
            box-shadow: none !important;
            border-color: #6c757d !important;
        }
        
        .fss-form-row {
            position: relative;
        }
        
        .readonly-indicator {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            color: #6c757d;
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            pointer-events: none;
            z-index: 1000;
            font-weight: 600;
        }
        
        .fss-btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .fss-btn-secondary:hover {
            background: #5a6268;
        }
        
        .fss-btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Remove all outlines and red borders */
        input {
            outline: none !important;
        }
        
        input:focus {
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25) !important;
        }
        
        input[readonly]:focus,
        input[disabled]:focus {
            box-shadow: none !important;
        }
        
        /* Staff message styles */
        .staff-readonly-message {
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @media (max-width: 768px) {
            .fss-status-card {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
            
            .live-orders-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .live-stat {
                padding: 12px;
            }
            
            .stat-value {
                font-size: 16px;
            }
            
            .readonly-indicator {
                font-size: 9px;
                padding: 1px 4px;
            }
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * AJAX handler to get live orders data
     */
    public function get_live_orders() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_live_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $date = sanitize_text_field($_POST['date'] ?? wp_date('Y-m-d'));
        
        // Get fresh orders data
        $orders_data = FSS_Database::get_orders_data_for_date($date);
        
        // Get integration status (with staff override)
        $integration_status = $this->get_integration_status_for_display();
        
        // Calculate additional metrics
        $avg_order = $orders_data['order_count'] > 0 ? $orders_data['total_sales'] / $orders_data['order_count'] : 0;
        $cash_percentage = $orders_data['total_sales'] > 0 ? ($orders_data['cash'] / $orders_data['total_sales']) * 100 : 0;
        $card_percentage = $orders_data['total_sales'] > 0 ? ($orders_data['transfer_card'] / $orders_data['total_sales']) * 100 : 0;
        
        wp_send_json_success(array(
            'orders_data' => $orders_data,
            'integration_status' => $integration_status,
            'metrics' => array(
                'avg_order' => $avg_order,
                'cash_percentage' => $cash_percentage,
                'card_percentage' => $card_percentage
            ),
            'timestamp' => wp_date('H:i:s', null, new DateTimeZone('Africa/Lagos')),
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
     * AJAX handler to refresh order data
     */
    public function refresh_order_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_live_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $date = sanitize_text_field($_POST['date'] ?? wp_date('Y-m-d'));
        
        // Force refresh by clearing any caches
        wp_cache_delete('fss_orders_' . $date);
        
        // Get fresh data
        $orders_data = FSS_Database::get_orders_data_for_date($date);
        
        // Update existing summary if it exists
        $existing_summary = FSS_Database::get_daily_summary($date);
        if ($existing_summary && $orders_data['total_sales'] > 0) {
            // Update only the order-related fields
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'fss_daily_summaries',
                array(
                    'total_sales' => $orders_data['total_sales'],
                    'transfer_card' => $orders_data['transfer_card'],
                    'cash' => $orders_data['cash'],
                    'delivery' => $orders_data['delivery'],
                    'order_count' => $orders_data['order_count'],
                    'data_quality' => $orders_data['data_quality'],
                    'updated_at' => current_time('mysql')
                ),
                array('date' => $date)
            );
            
            // Recalculate cash left
            $cash_in = $orders_data['cash'] + $existing_summary->old_cash + $existing_summary->extras + $existing_summary->cash_left_market_card;
            $new_cash_left = $cash_in - $existing_summary->expense;
            
            $wpdb->update(
                $wpdb->prefix . 'fss_daily_summaries',
                array('cash_left' => $new_cash_left),
                array('date' => $date)
            );
            
            $orders_data['cash_left'] = $new_cash_left;
        }
        
        wp_send_json_success(array(
            'message' => 'Order data refreshed successfully',
            'orders_data' => $orders_data,
            'timestamp' => wp_date('H:i:s', null, new DateTimeZone('Africa/Lagos')),
            'updated_summary' => $existing_summary ? true : false
        ));
    }
    
    /**
     * Enhanced submit summary with real-time integration
     */
    public function submit_summary() {
        if (!wp_verify_nonce($_POST['fss_nonce'], 'fss_submit_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $date = sanitize_text_field($_POST['date']);
        $integration_connected = isset($_POST['integration_connected']) && $_POST['integration_connected'] === 'true';
        
        // Get fresh orders data if integration is connected
        if ($integration_connected) {
            $fresh_orders = FSS_Database::get_orders_data_for_date($date);
            
            // Override form data with fresh orders data
            $_POST['total_sales'] = $fresh_orders['total_sales'];
            $_POST['transfer_card'] = $fresh_orders['transfer_card'];
            $_POST['cash'] = $fresh_orders['cash'];
            $_POST['delivery'] = $fresh_orders['delivery'];
        }
        
        // Prepare data
        $data = array(
            'extras' => floatval($_POST['extras'] ?? 0),
            'extras_remark' => sanitize_textarea_field($_POST['extras_remark'] ?? ''),
            'expense' => floatval($_POST['expense'] ?? 0),
            'expense_remark' => sanitize_textarea_field($_POST['expense_remark'] ?? ''),
            'old_cash' => floatval($_POST['old_cash'] ?? 0),
            'cash_left_market_card' => floatval($_POST['cash_left_market_card'] ?? 0),
            'entry_source' => $integration_connected ? 'live_integration' : 'manual'
        );
        
        // Calculate cash left
        $orders_data = FSS_Database::get_orders_data_for_date($date);
        $cash_in = $orders_data['cash'] + $data['old_cash'] + $data['extras'] + $data['cash_left_market_card'];
        $cash_left = $cash_in - $data['expense'];
        $data['cash_left'] = $cash_left;
        
        // Save summary
        $summary_id = FSS_Database::create_or_update_daily_summary($date, $data);
        
        if ($summary_id) {
            // Set old cash for tomorrow
            $tomorrow = date('Y-m-d', strtotime($date . ' +1 day'));
            update_option('fss_old_cash_' . $tomorrow, $cash_left);
            
            wp_send_json_success(array(
                'message' => 'Financial summary saved successfully!',
                'summary_id' => $summary_id,
                'cash_left' => $cash_left,
                'orders_data' => $orders_data,
                'integration_status' => $integration_connected
            ));
        } else {
            wp_send_json_error('Failed to save financial summary');
        }
    }
    
    /**
     * Hook into Take Order Form submissions
     */
    public function on_order_submitted($order_id, $order_data) {
        // This hook will be called when a new order is submitted
        // Trigger real-time update of financial summary
        
        $today = wp_date('Y-m-d');
        
        // Clear any cached data
        wp_cache_delete('fss_orders_' . $today);
        
        // Optionally trigger a notification or update
        do_action('fss_orders_updated', $today, $order_data);
    }
    
    /**
     * Check for order submissions via post insertion
     */
    public function check_order_submission($post_id, $post) {
        // Check if this might be an order submission
        if ($post->post_type === 'take_order_submission' || 
            (isset($_POST['action']) && strpos($_POST['action'], 'order') !== false)) {
            
            $today = wp_date('Y-m-d');
            wp_cache_delete('fss_orders_' . $today);
        }
    }
    
    /**
     * Add auto-refresh JavaScript with staff readonly enforcement
     */
    public function add_auto_refresh_script() {
        if ($this->should_load_assets()) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Auto-refresh functionality
                const autoRefreshInterval = 30000; // 30 seconds
                let refreshTimer;
                const isStaff = fss_live.is_staff;
                
                // Staff readonly enforcement
                if (isStaff) {
                    console.log('🔒 Applying staff readonly enforcement...');
                    enforceStaffReadonly();
                    updateLagosTime();
                    setInterval(updateLagosTime, 1000); // Update every second
                }
                
                function enforceStaffReadonly() {
                    const readonlyFields = [
                        'total_sales',
                        'transfer_card', 
                        'cash',
                        'delivery',
                        'cash_left',
                        'old_cash',
                        'cash_left_market_card'
                    ];
                    
                    readonlyFields.forEach(function(fieldName) {
                        const field = document.querySelector('input[name="' + fieldName + '"]');
                        if (field) {
                            // Multiple layers of protection
                            field.setAttribute('readonly', 'readonly');
                            field.setAttribute('disabled', 'disabled');
                            field.style.pointerEvents = 'none';
                            field.tabIndex = -1;
                            
                            // Block all events
                            const events = ['input', 'change', 'keydown', 'keyup', 'keypress', 'paste', 'drop', 'focus', 'click', 'mousedown', 'mouseup', 'contextmenu', 'selectstart'];
                            
                            events.forEach(function(eventType) {
                                field.addEventListener(eventType, function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    e.stopImmediatePropagation();
                                    
                                    showStaffMessage('🔒 Field "' + fieldName + '" is read-only for staff users');
                                    return false;
                                }, true);
                            });
                            
                            console.log('🔒 Protected field:', fieldName);
                        }
                    });
                }
                
                function updateLagosTime() {
                    const now = new Date();
                    const lagosTime = new Date(now.toLocaleString("en-US", {timeZone: "Africa/Lagos"}));
                    
                    const timeString = lagosTime.toLocaleTimeString('en-US', {
                        hour12: true,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    
                    const dateString = lagosTime.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    
                    // Update time elements
                    $('#fss-current-time').text(timeString);
                    $('#fss-current-date').text(dateString);
                    $('#last-refresh-time').text(lagosTime.toLocaleTimeString('en-US', {
                        hour12: false,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    }));
                }
                
                function showStaffMessage(message) {
                    // Remove existing messages
                    $('.staff-readonly-message').remove();
                    
                    // Create new message
                    const msgDiv = $('<div class="staff-readonly-message"></div>');
                    msgDiv.html(message);
                    msgDiv.css({
                        position: 'fixed',
                        top: '20px',
                        right: '20px',
                        background: '#dc3545',
                        color: 'white',
                        padding: '15px 20px',
                        borderRadius: '8px',
                        boxShadow: '0 4px 20px rgba(0,0,0,0.3)',
                        zIndex: 999999,
                        fontSize: '14px',
                        fontWeight: '600',
                        border: '2px solid #c82333',
                        maxWidth: '300px',
                        textAlign: 'center'
                    });
                    
                    $('body').append(msgDiv);
                    
                    // Auto-remove after 4 seconds
                    setTimeout(function() {
                        msgDiv.fadeOut(300, function() {
                            msgDiv.remove();
                        });
                    }, 4000);
                }
                
                function startAutoRefresh() {
                    const container = $('.fss-container[data-auto-refresh="true"]');
                    if (container.length === 0) return;
                    
                    refreshTimer = setInterval(function() {
                        refreshLiveOrders(false); // Silent refresh
                    }, autoRefreshInterval);
                }
                
                function stopAutoRefresh() {
                    if (refreshTimer) {
                        clearInterval(refreshTimer);
                    }
                }
                
                function refreshLiveOrders(showSpinner = true) {
                    const date = $('.fss-container').data('date') || fss_live.current_date;
                    
                    if (showSpinner) {
                        $('#refresh-spinner').show();
                    }
                    
                    $.ajax({
                        url: fss_live.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fss_get_live_orders',
                            nonce: fss_live.nonce,
                            date: date
                        },
                        success: function(response) {
                            if (response.success) {
                                updateLiveOrdersDisplay(response.data);
                                updateFormFields(response.data.orders_data);
                                
                                if (showSpinner) {
                                    showNotification('Orders updated successfully!', 'success');
                                }
                            }
                        },
                        error: function() {
                            if (showSpinner) {
                                showNotification('Failed to refresh orders', 'error');
                            }
                        },
                        complete: function() {
                            if (showSpinner) {
                                $('#refresh-spinner').hide();
                            }
                        }
                    });
                }
                
                function updateLiveOrdersDisplay(data) {
                    $('#live-total-orders').text(data.formatted.order_count);
                    $('#live-total-sales').text(data.formatted.total_sales);
                    $('#live-card-sales').text(data.formatted.transfer_card);
                    $('#live-cash-sales').text(data.formatted.cash);
                    $('#live-delivery-fees').text(data.formatted.delivery);
                    $('#live-avg-order').text(data.formatted.avg_order);
                    
                    // Update status indicator
                    const indicator = $('.status-indicator');
                    if (data.orders_data.order_count > 0) {
                        indicator.addClass('active');
                        $('.status-text').text('Live data updating every 30 seconds');
                    } else {
                        indicator.removeClass('active');
                        $('.status-text').text('No orders yet today');
                    }
                }
                
                function updateFormFields(ordersData) {
                    // Only update if not readonly for staff
                    if (!isStaff) {
                        $('#total_sales').val(ordersData.total_sales);
                        $('#transfer_card').val(ordersData.transfer_card);
                        $('#cash').val(ordersData.cash);
                        $('#delivery').val(ordersData.delivery);
                    }
                    
                    // Recalculate cash left
                    calculateCashLeft();
                }
                
                function calculateCashLeft() {
                    const cash = parseFloat($('#cash').val()) || 0;
                    const oldCash = parseFloat($('#old_cash').val()) || 0;
                    const extras = parseFloat($('#extras').val()) || 0;
                    const marketCard = parseFloat($('#cash_left_market_card').val()) || 0;
                    const expenses = parseFloat($('#expense').val()) || 0;
                    
                    const cashLeft = cash + oldCash + extras + marketCard - expenses;
                    $('#cash_left').val(cashLeft.toFixed(2));
                }
                
                function showNotification(message, type) {
                    const notification = $('<div class="fss-notification fss-notification-' + type + '">' + message + '</div>');
                    $('body').append(notification);
                    
                    setTimeout(function() {
                        notification.fadeOut(function() {
                            notification.remove();
                        });
                    }, 3000);
                }
                
                // Event handlers
                $('#refresh-orders-btn, #manual-refresh-btn').on('click', function() {
                    refreshLiveOrders(true);
                });
                
                // Auto-calculate cash left when values change (for editable fields only)
                if (!isStaff) {
                    $('#cash, #old_cash, #extras, #cash_left_market_card, #expense').on('input', calculateCashLeft);
                } else {
                    $('#extras, #expense').on('input', calculateCashLeft); // Only manual fields for staff
                }
                
                // Form submission
                $('#fss-summary-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = $(this).serialize();
                    const integrationConnected = $(this).data('integration') === 'true';
                    
                    $.ajax({
                        url: fss_live.ajaxurl,
                        type: 'POST',
                        data: formData + '&integration_connected=' + integrationConnected,
                        success: function(response) {
                            if (response.success) {
                                showNotification(response.data.message, 'success');
                                
                                // Update form with fresh data
                                if (response.data.orders_data && !isStaff) {
                                    updateFormFields(response.data.orders_data);
                                }
                            } else {
                                showNotification(response.data || 'Failed to save summary', 'error');
                            }
                        },
                        error: function() {
                            showNotification('Network error occurred', 'error');
                        }
                    });
                });
                
                // Start auto-refresh
                startAutoRefresh();
                
                // Stop auto-refresh when page becomes hidden
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden) {
                        stopAutoRefresh();
                    } else {
                        startAutoRefresh();
                    }
                });
                
                // Initial calculation
                calculateCashLeft();
                
                // Initial refresh
                setTimeout(function() {
                    refreshLiveOrders(false);
                }, 2000);
                
                console.log('FSS Frontend initialized', isStaff ? '(Staff Mode)' : '(Admin Mode)');
            });
            </script>
            <?php
        }
    }
    
    private function should_load_assets() {
        global $post;
        
        if (is_admin()) {
            return false;
        }
        
        if ($post && (
            has_shortcode($post->post_content, 'financial_summary_form') ||
            has_shortcode($post->post_content, 'financial_dashboard_widgets')
        )) {
            return true;
        }
        
        return false;
    }
}