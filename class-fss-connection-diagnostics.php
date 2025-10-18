<?php
/**
 * Financial Summary System - Connection Diagnostics and Repair Tool
 */

class FSS_Connection_Diagnostics {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_diagnostics_menu'));
        add_action('wp_ajax_fss_run_diagnostics', array($this, 'run_diagnostics'));
        add_action('wp_ajax_fss_fix_connection', array($this, 'fix_connection'));
        add_action('wp_ajax_fss_test_table_query', array($this, 'test_table_query'));
    }
    
    public function add_diagnostics_menu() {
        add_submenu_page(
            'financial-summary',
            'Connection Diagnostics',
            'Fix Connection',
            'manage_options',
            'financial-summary-diagnostics',
            array($this, 'diagnostics_page')
        );
    }
    
    public function diagnostics_page() {
        ?>
        <div class="wrap">
            <h1>🔧 Take Order Form Connection Diagnostics</h1>
            <p>Fix the connection between Financial Summary and Take Order Form</p>
            
            <div class="fss-diagnostics-container">
                <!-- Step 1: Run Diagnostics -->
                <div class="diagnostics-section">
                    <h2>Step 1: Scan for Order Data</h2>
                    <p>Let's find your order data and identify the correct table structure.</p>
                    <button type="button" class="button button-primary button-large" id="run-diagnostics">
                        🔍 Run Full Diagnostics
                    </button>
                    <div id="diagnostics-results"></div>
                </div>
                
                <!-- Step 2: Fix Connection -->
                <div class="diagnostics-section">
                    <h2>Step 2: Fix Connection</h2>
                    <div id="fix-connection-section" style="display: none;">
                        <p>Based on diagnostics, we'll configure the correct connection.</p>
                        <button type="button" class="button button-primary" id="fix-connection">
                            🔧 Fix Connection Now
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Test Connection -->
                <div class="diagnostics-section">
                    <h2>Step 3: Test & Verify</h2>
                    <div id="test-section" style="display: none;">
                        <button type="button" class="button button-secondary" id="test-connection">
                            ✅ Test Connection
                        </button>
                        <div id="test-results"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .fss-diagnostics-container {
            max-width: 900px;
        }
        
        .diagnostics-section {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .diagnostics-section h2 {
            margin-top: 0;
            color: #1e1e1e;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 8px;
        }
        
        .diagnostic-result {
            background: #f6f7f7;
            border-left: 4px solid #72aee6;
            padding: 15px;
            margin: 15px 0;
        }
        
        .diagnostic-result.success {
            border-left-color: #00ba37;
            background: #edfaef;
        }
        
        .diagnostic-result.error {
            border-left-color: #d63638;
            background: #fcf0f1;
        }
        
        .diagnostic-result.warning {
            border-left-color: #dba617;
            background: #fcf9e8;
        }
        
        .table-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        
        .table-stat {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .table-stat strong {
            display: block;
            font-size: 18px;
            color: #2271b1;
        }
        
        .column-mapping {
            background: #f0f6fc;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
        }
        
        .column-mapping h4 {
            margin: 0 0 10px 0;
            color: #0a4b78;
        }
        
        .spinner {
            background: url('/wp-admin/images/spinner.gif') no-repeat;
            background-size: 20px 20px;
            display: inline-block;
            width: 20px;
            height: 20px;
            vertical-align: middle;
            margin-left: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let diagnosticsData = null;
            
            $('#run-diagnostics').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.prop('disabled', true).html('🔍 Scanning... <span class="spinner"></span>');
                $('#diagnostics-results').html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_run_diagnostics',
                        nonce: '<?php echo wp_create_nonce('fss_diagnostics_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            diagnosticsData = response.data;
                            displayDiagnostics(response.data);
                            $('#fix-connection-section').show();
                        } else {
                            $('#diagnostics-results').html('<div class="diagnostic-result error"><h4>❌ Diagnostics Failed</h4><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#diagnostics-results').html('<div class="diagnostic-result error"><h4>❌ Network Error</h4><p>Failed to run diagnostics. Please try again.</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            $('#fix-connection').on('click', function() {
                if (!diagnosticsData) {
                    alert('Please run diagnostics first');
                    return;
                }
                
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.prop('disabled', true).html('🔧 Fixing... <span class="spinner"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_fix_connection',
                        nonce: '<?php echo wp_create_nonce('fss_diagnostics_nonce'); ?>',
                        diagnostics_data: diagnosticsData
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#fix-connection-section').after('<div class="diagnostic-result success"><h4>✅ Connection Fixed!</h4><p>' + response.data.message + '</p></div>');
                            $('#test-section').show();
                        } else {
                            $('#fix-connection-section').after('<div class="diagnostic-result error"><h4>❌ Fix Failed</h4><p>' + response.data.message + '</p></div>');
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            $('#test-connection').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.prop('disabled', true).html('✅ Testing... <span class="spinner"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_test_table_query',
                        nonce: '<?php echo wp_create_nonce('fss_diagnostics_nonce'); ?>',
                        date: '<?php echo date('Y-m-d'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            $('#test-results').html(`
                                <div class="diagnostic-result success">
                                    <h4>✅ Connection Test Successful!</h4>
                                    <div class="table-info">
                                        <div class="table-stat">
                                            <strong>${data.order_count}</strong>
                                            <span>Orders Today</span>
                                        </div>
                                        <div class="table-stat">
                                            <strong>₦${data.total_sales.toLocaleString()}</strong>
                                            <span>Total Sales</span>
                                        </div>
                                        <div class="table-stat">
                                            <strong>₦${data.cash_sales.toLocaleString()}</strong>
                                            <span>Cash Sales</span>
                                        </div>
                                        <div class="table-stat">
                                            <strong>₦${data.card_sales.toLocaleString()}</strong>
                                            <span>Card/Transfer</span>
                                        </div>
                                    </div>
                                    <p><strong>🎉 Your Financial Summary should now show live data!</strong></p>
                                    <p><a href="<?php echo site_url('/financial-summary/'); ?>" class="button button-primary">View Financial Summary</a></p>
                                </div>
                            `);
                        } else {
                            $('#test-results').html('<div class="diagnostic-result error"><h4>❌ Test Failed</h4><p>' + response.data.message + '</p></div>');
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            function displayDiagnostics(data) {
                let html = '<h3>📋 Diagnostics Results</h3>';
                
                // Current connection status
                html += '<div class="diagnostic-result ' + (data.current_status.connected ? 'warning' : 'error') + '">';
                html += '<h4>Current Connection Status</h4>';
                html += '<p>Table: <strong>' + (data.current_status.table_name || 'None') + '</strong></p>';
                html += '<p>Connected: <strong>' + (data.current_status.connected ? 'Yes (but not working correctly)' : 'No') + '</strong></p>';
                html += '</div>';
                
                // Found tables
                if (data.found_tables && data.found_tables.length > 0) {
                    html += '<div class="diagnostic-result success">';
                    html += '<h4>✅ Found Order Tables (' + data.found_tables.length + ')</h4>';
                    
                    data.found_tables.forEach(function(table) {
                        html += '<div class="column-mapping">';
                        html += '<h4>📊 ' + table.name + ' (Score: ' + table.score + '/100)</h4>';
                        html += '<div class="table-info">';
                        html += '<div class="table-stat"><strong>' + table.total_rows.toLocaleString() + '</strong><span>Total Records</span></div>';
                        html += '<div class="table-stat"><strong>' + table.today_rows + '</strong><span>Today\'s Orders</span></div>';
                        html += '<div class="table-stat"><strong>₦' + table.today_sales.toLocaleString() + '</strong><span>Today\'s Sales</span></div>';
                        html += '</div>';
                        
                        if (table.columns) {
                            html += '<p><strong>Key Columns:</strong> ' + Object.values(table.columns).filter(c => c).join(', ') + '</p>';
                        }
                        
                        if (table.score >= 80) {
                            html += '<p style="color: #00ba37; font-weight: bold;">🎯 Recommended for connection</p>';
                        }
                        html += '</div>';
                    });
                    
                    html += '</div>';
                } else {
                    html += '<div class="diagnostic-result error">';
                    html += '<h4>❌ No Compatible Order Tables Found</h4>';
                    html += '<p>We couldn\'t find any tables that match the expected order structure.</p>';
                    html += '</div>';
                }
                
                $('#diagnostics-results').html(html);
            }
        });
        </script>
        <?php
    }
    
    public function run_diagnostics() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_diagnostics_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        global $wpdb;
        
        try {
            // Get current connection status
            $current_status = FSS_Database::get_integration_status();
            
            // Scan all tables for order-like structures
            $all_tables = $wpdb->get_col("SHOW TABLES");
            $found_tables = array();
            $today = date('Y-m-d');
            
            foreach ($all_tables as $table_name) {
                try {
                    // Skip non-relevant tables
                    if (strpos($table_name, 'posts') !== false || 
                        strpos($table_name, 'options') !== false ||
                        strpos($table_name, 'users') !== false) {
                        continue;
                    }
                    
                    $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table_name`");
                    $analysis = $this->analyze_table_for_orders($table_name, $columns, $today);
                    
                    if ($analysis['score'] >= 30) { // Lower threshold for diagnostics
                        $found_tables[] = $analysis;
                    }
                    
                } catch (Exception $e) {
                    continue; // Skip inaccessible tables
                }
            }
            
            // Sort by score
            usort($found_tables, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            wp_send_json_success(array(
                'current_status' => $current_status,
                'found_tables' => $found_tables,
                'scan_date' => $today,
                'total_tables_scanned' => count($all_tables)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Diagnostics failed: ' . $e->getMessage()));
        }
    }
    
    private function analyze_table_for_orders($table_name, $columns, $today) {
        global $wpdb;
        
        $analysis = array(
            'name' => $table_name,
            'columns' => array(),
            'score' => 0,
            'total_rows' => 0,
            'today_rows' => 0,
            'today_sales' => 0,
            'reasons' => array()
        );
        
        // Table name scoring
        if (preg_match('/order/i', $table_name)) {
            $analysis['score'] += 40;
            $analysis['reasons'][] = 'Table name contains "order"';
        }
        
        if (preg_match('/tof|take.*order/i', $table_name)) {
            $analysis['score'] += 50;
            $analysis['reasons'][] = 'Table name suggests Take Order Form';
        }
        
        // Column analysis
        $has_amount = false;
        $has_date = false;
        $amount_column = null;
        $date_column = null;
        
        foreach ($columns as $column) {
            $column_lower = strtolower($column);
            
            // Look for amount/price columns
            if (preg_match('/total|amount|price|grand.*total/i', $column)) {
                $has_amount = true;
                $amount_column = $column;
                $analysis['columns']['amount'] = $column;
                $analysis['score'] += 25;
                $analysis['reasons'][] = "Found amount column: $column";
                break;
            }
        }
        
        foreach ($columns as $column) {
            // Look for date columns
            if (preg_match('/date|created|timestamp/i', $column)) {
                $has_date = true;
                $date_column = $column;
                $analysis['columns']['date'] = $column;
                $analysis['score'] += 20;
                $analysis['reasons'][] = "Found date column: $column";
                break;
            }
        }
        
        // Other important columns
        foreach ($columns as $column) {
            if (preg_match('/payment.*method/i', $column)) {
                $analysis['columns']['payment'] = $column;
                $analysis['score'] += 15;
                $analysis['reasons'][] = "Found payment method column: $column";
            }
            
            if (preg_match('/cash.*amount/i', $column)) {
                $analysis['columns']['cash'] = $column;
                $analysis['score'] += 10;
                $analysis['reasons'][] = "Found cash amount column: $column";
            }
        }
        
        // Data analysis
        if ($has_amount && $has_date) {
            try {
                // Get total rows
                $analysis['total_rows'] = intval($wpdb->get_var("SELECT COUNT(*) FROM `$table_name`"));
                
                if ($analysis['total_rows'] > 0) {
                    $analysis['score'] += 10;
                    $analysis['reasons'][] = "Table has {$analysis['total_rows']} records";
                    
                    // Get today's data
                    $today_query = "SELECT COUNT(*) as count, SUM($amount_column) as total 
                                   FROM `$table_name` 
                                   WHERE DATE($date_column) = %s";
                    
                    $today_data = $wpdb->get_row($wpdb->prepare($today_query, $today));
                    
                    if ($today_data) {
                        $analysis['today_rows'] = intval($today_data->count);
                        $analysis['today_sales'] = floatval($today_data->total ?: 0);
                        
                        if ($analysis['today_rows'] > 0) {
                            $analysis['score'] += 30;
                            $analysis['reasons'][] = "Has {$analysis['today_rows']} orders today";
                        }
                        
                        if ($analysis['today_sales'] > 0) {
                            $analysis['score'] += 20;
                            $analysis['reasons'][] = "Has ₦{$analysis['today_sales']} sales today";
                        }
                    }
                }
                
            } catch (Exception $e) {
                $analysis['reasons'][] = "Error analyzing data: " . $e->getMessage();
            }
        }
        
        return $analysis;
    }
    
    public function fix_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_diagnostics_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $diagnostics_data = $_POST['diagnostics_data'];
        
        if (empty($diagnostics_data['found_tables'])) {
            wp_send_json_error(array('message' => 'No suitable tables found to connect'));
        }
        
        // Get the best table (highest score)
        $best_table = $diagnostics_data['found_tables'][0];
        
        if ($best_table['score'] < 50) {
            wp_send_json_error(array('message' => 'No table has sufficient compatibility score'));
        }
        
        try {
            global $wpdb;
            
            // Update table mapping
            $mapping_table = $wpdb->prefix . 'fss_table_mappings';
            
            // Clear existing mappings
            $wpdb->query("UPDATE $mapping_table SET is_active = 0");
            
            // Create new mapping
            $mapping_data = array(
                'table_name' => $best_table['name'],
                'amount_column' => $best_table['columns']['amount'] ?? null,
                'date_column' => $best_table['columns']['date'] ?? null,
                'payment_column' => $best_table['columns']['payment'] ?? null,
                'cash_column' => $best_table['columns']['cash'] ?? null,
                'confidence_score' => $best_table['score'],
                'is_active' => 1,
                'updated_at' => current_time('mysql')
            );
            
            $result = $wpdb->replace($mapping_table, $mapping_data);
            
            if ($result) {
                // Clear any cached data
                wp_cache_flush();
                
                // Set session variable for immediate use
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['selected_order_table'] = $best_table['name'];
                
                wp_send_json_success(array(
                    'message' => "Successfully connected to {$best_table['name']} with {$best_table['score']}/100 compatibility score",
                    'table_name' => $best_table['name'],
                    'score' => $best_table['score']
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to save table mapping'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Fix failed: ' . $e->getMessage()));
        }
    }
    
    public function test_table_query() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_diagnostics_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        try {
            // Clear cache first
            wp_cache_flush();
            
            // Get fresh orders data
            $orders_data = FSS_Database::get_orders_data_for_date($date);
            
            wp_send_json_success(array(
                'order_count' => $orders_data['order_count'],
                'total_sales' => $orders_data['total_sales'],
                'cash_sales' => $orders_data['cash'],
                'card_sales' => $orders_data['transfer_card'],
                'delivery_fees' => $orders_data['delivery'],
                'data_quality' => $orders_data['data_quality'],
                'source_table' => $orders_data['source_table']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Test failed: ' . $e->getMessage()));
        }
    }
}