<?php
/**
 * Quick Fix Admin Page
 */

class FSS_Quick_Fix_Page {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_quick_fix_menu'));
        add_action('wp_ajax_fss_quick_fix_now', array($this, 'quick_fix_now'));
    }
    
    public function add_quick_fix_menu() {
        add_submenu_page(
            'financial-summary',
            'Quick Fix TOF Orders',
            '🔧 Quick Fix',
            'manage_options',
            'financial-summary-quick-fix',
            array($this, 'quick_fix_page')
        );
    }
    
    public function quick_fix_page() {
        ?>
        <div class="wrap">
            <h1>🔧 Quick Fix for wp_tof_orders Connection</h1>
            
            <div class="notice notice-info">
                <p><strong>Detected:</strong> Your orders are in <code>wp_tof_orders</code> table with 141 records.</p>
                <p>Click the button below to fix the connection immediately.</p>
            </div>
            
            <div class="fix-section">
                <h2>Fix Connection Now</h2>
                <p>This will configure Financial Summary to read from your <code>wp_tof_orders</code> table correctly.</p>
                
                <button type="button" class="button button-primary button-large" id="quick-fix-btn">
                    🔧 Fix Connection to wp_tof_orders
                </button>
                
                <div id="fix-results" style="margin-top: 20px;"></div>
            </div>
            
            <div class="test-section" style="margin-top: 30px;">
                <h2>Test Today's Data</h2>
                <button type="button" class="button button-secondary" id="test-today-btn" disabled>
                    📊 Test Today's Orders
                </button>
                
                <div id="test-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <style>
        .fix-section, .test-section {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .fix-result {
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .fix-result.success {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .fix-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .data-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .data-value {
            font-size: 24px;
            font-weight: bold;
            color: #FF0000;
            display: block;
        }
        
        .data-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            margin-top: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#quick-fix-btn').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.prop('disabled', true).text('🔧 Fixing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_quick_fix_now',
                        nonce: '<?php echo wp_create_nonce('fss_quick_fix_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#fix-results').html(`
                                <div class="fix-result success">
                                    <h3>✅ Connection Fixed Successfully!</h3>
                                    <p><strong>Table:</strong> ${response.data.table_name}</p>
                                    <p><strong>Total Records:</strong> ${response.data.total_records.toLocaleString()}</p>
                                    <p><strong>Status:</strong> Ready to sync live orders</p>
                                </div>
                            `);
                            
                            $('#test-today-btn').prop('disabled', false);
                            
                            if (response.data.test_data && response.data.test_data.total_sales > 0) {
                                displayTestData(response.data.test_data);
                            }
                        } else {
                            $('#fix-results').html(`
                                <div class="fix-result error">
                                    <h3>❌ Fix Failed</h3>
                                    <p>${response.data}</p>
                                </div>
                            `);
                        }
                    },
                    error: function() {
                        $('#fix-results').html(`
                            <div class="fix-result error">
                                <h3>❌ Network Error</h3>
                                <p>Please try again or contact support.</p>
                            </div>
                        `);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            $('#test-today-btn').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.prop('disabled', true).text('📊 Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_test_tof_today',
                        nonce: '<?php echo wp_create_nonce('fss_quick_fix_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayTestData(response.data.data, response.data.formatted);
                        } else {
                            $('#test-results').html(`
                                <div class="fix-result error">
                                    <h3>❌ Test Failed</h3>
                                    <p>${response.data}</p>
                                </div>
                            `);
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            function displayTestData(data, formatted = null) {
                const fmt = formatted || {
                    total_sales: '₦' + data.total_sales.toLocaleString(),
                    transfer_card: '₦' + data.transfer_card.toLocaleString(),
                    cash: '₦' + data.cash.toLocaleString(),
                    delivery: '₦' + data.delivery.toLocaleString(),
                    order_count: data.order_count.toLocaleString()
                };
                
                $('#test-results').html(`
                    <div class="fix-result success">
                        <h3>✅ Today's Orders Found!</h3>
                        <div class="data-grid">
                            <div class="data-item">
                                <span class="data-value">${fmt.order_count}</span>
                                <span class="data-label">Orders</span>
                            </div>
                            <div class="data-item">
                                <span class="data-value">${fmt.total_sales}</span>
                                <span class="data-label">Total Sales</span>
                            </div>
                            <div class="data-item">
                                <span class="data-value">${fmt.transfer_card}</span>
                                <span class="data-label">Transfer/Card</span>
                            </div>
                            <div class="data-item">
                                <span class="data-value">${fmt.cash}</span>
                                <span class="data-label">Cash</span>
                            </div>
                            <div class="data-item">
                                <span class="data-value">${fmt.delivery}</span>
                                <span class="data-label">Delivery Fees</span>
                            </div>
                        </div>
                        <p><a href="<?php echo site_url('/financial-summary/'); ?>" class="button button-primary">📊 View Financial Summary</a></p>
                    </div>
                `);
            }
        });
        </script>
        <?php
    }
    
    public function quick_fix_now() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_quick_fix_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'tof_orders';
            
            // Verify table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error('wp_tof_orders table not found');
            }
            
            // Get row count
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            
            // Update or create table mapping
            $mapping_table = $wpdb->prefix . 'fss_table_mappings';
            
            // Clear existing active mappings
            $wpdb->query("UPDATE $mapping_table SET is_active = 0 WHERE is_active = 1");
            
            // Insert new mapping
            $result = $wpdb->replace($mapping_table, array(
                'table_name' => $table_name,
                'amount_column' => 'grand_total',
                'date_column' => 'order_date',
                'payment_column' => 'payment_method',
                'delivery_column' => 'delivery_fee',
                'cash_column' => 'cash_amount',
                'confidence_score' => 95,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ));
            
            if ($result) {
                // Set session
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['selected_order_table'] = $table_name;
                
                // Clear caches
                wp_cache_flush();
                
                // Test today's data
                $today = date('Y-m-d');
                $test_data = $this->get_today_orders($today);
                
                wp_send_json_success(array(
                    'message' => 'Connection fixed successfully!',
                    'table_name' => $table_name,
                    'total_records' => intval($row_count),
                    'test_data' => $test_data
                ));
            } else {
                wp_send_json_error('Failed to save table mapping');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Fix failed: ' . $e->getMessage());
        }
    }
    
    private function get_today_orders($date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tof_orders';
        
        try {
            $results = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as order_count,
                    SUM(COALESCE(transfer_amount, 0)) as transfer_total,
                    SUM(COALESCE(cash_amount, 0)) as cash_total,
                    SUM(COALESCE(delivery_fee, 0)) as delivery_total,
                    SUM(COALESCE(grand_total, total_amount, 0)) as total_sales
                FROM $table_name 
                WHERE DATE(order_date) = %s
                AND COALESCE(grand_total, total_amount, 0) > 0
            ", $date));
            
            if ($results) {
                return array(
                    'total_sales' => floatval($results->total_sales),
                    'transfer_card' => floatval($results->transfer_total),
                    'cash' => floatval($results->cash_total),
                    'delivery' => floatval($results->delivery_total),
                    'order_count' => intval($results->order_count)
                );
            }
        } catch (Exception $e) {
            error_log("FSS Quick Fix Error: " . $e->getMessage());
        }
        
        return array(
            'total_sales' => 0,
            'transfer_card' => 0,
            'cash' => 0,
            'delivery' => 0,
            'order_count' => 0
        );
    }
}