<?php
/**
 * Financial Summary System - Enhanced Data Importer for Take Order Form Working Plugin
 */

class FSS_Importer {
    
    private $batch_size = 50; // Process in batches
    private $max_execution_time = 300; // 5 minutes max
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_import_menu'));
        add_action('wp_ajax_fss_import_historical_data', array($this, 'import_historical_data'));
        add_action('wp_ajax_fss_preview_import_data', array($this, 'preview_import_data'));
        add_action('wp_ajax_fss_detect_order_tables', array($this, 'detect_order_tables'));
        add_action('wp_ajax_fss_batch_import', array($this, 'batch_import'));
        add_action('wp_ajax_fss_get_import_progress', array($this, 'get_import_progress'));
        add_action('wp_ajax_fss_test_manual_table', array($this, 'test_manual_table'));
        add_action('wp_ajax_fss_debug_tables', array($this, 'debug_tables'));
        
        // Increase PHP limits for import
        add_action('wp_ajax_fss_prepare_import', array($this, 'prepare_import_environment'));
        
        // Start session for table selection
        if (!session_id()) {
            session_start();
        }
    }
    
    public function add_import_menu() {
        add_submenu_page(
            'financial-summary',
            'Import Historical Data',
            'Import Data',
            'manage_options',
            'financial-summary-import',
            array($this, 'import_page')
        );
    }
    
    public function import_page() {
        ?>
        <div class="wrap">
            <h1>⚡ Fast Historical Data Import</h1>
            <p class="description">Import historical data from your Take Order Form Working Plugin</p>
            
            <div class="fss-import-container">
                <!-- Step 1: Enhanced Detection -->
                <div class="fss-import-section">
                    <h2>🔍 Step 1: Smart Table Detection</h2>
                    <p>Automatically scan for your Take Order Form tables (optimized for speed).</p>
                    
                    <div id="detection-results">
                        <div class="detection-buttons">
                            <button type="button" class="button button-primary button-large" id="quick-detect-tables">
                                <span class="dashicons dashicons-search"></span> Smart Scan Tables
                            </button>
                            <button type="button" class="button" id="debug-tables">
                                <span class="dashicons dashicons-admin-tools"></span> Debug: Show All Tables
                            </button>
                        </div>
                        <div id="detection-status" style="margin-top: 15px;"></div>
                    </div>
                </div>
                
                <!-- Step 2: Smart Date Range -->
                <div class="fss-import-section">
                    <h2>📅 Step 2: Smart Date Selection</h2>
                    <div class="date-selection-grid">
                        <div class="quick-ranges">
                            <h4>Quick Ranges:</h4>
                            <button type="button" class="button range-btn" data-range="7">Last 7 Days</button>
                            <button type="button" class="button range-btn" data-range="30">Last 30 Days</button>
                            <button type="button" class="button range-btn" data-range="90">Last 3 Months</button>
                            <button type="button" class="button range-btn" data-range="365">Last Year</button>
                        </div>
                        <div class="custom-range">
                            <h4>Custom Range:</h4>
                            <label>Start: <input type="date" id="import-start-date" value="<?php echo date('Y-m-01', strtotime('-1 month')); ?>"></label>
                            <label>End: <input type="date" id="import-end-date" value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>"></label>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Fast Preview -->
                <div class="fss-import-section">
                    <h2>⚡ Step 3: Lightning Preview</h2>
                    <button type="button" class="button button-primary" id="fast-preview" disabled>
                        <span class="dashicons dashicons-visibility"></span> Quick Preview
                    </button>
                    <p class="description">Configure a table first to enable preview</p>
                    
                    <div id="preview-results" style="margin-top: 20px;"></div>
                </div>
                
                <!-- Step 4: Turbo Import -->
                <div class="fss-import-section">
                    <h2>🚀 Step 4: Turbo Import</h2>
                    <div class="import-options-grid">
                        <div class="import-settings">
                            <h4>Import Settings:</h4>
                            <label><input type="radio" name="import_mode" value="smart_merge" checked> Smart Merge (Skip Existing)</label><br>
                            <label><input type="radio" name="import_mode" value="force_replace"> Force Replace All</label><br>
                            <label><input type="checkbox" id="skip-weekends" checked> Skip Weekends</label><br>
                            <label><input type="checkbox" id="bulk-mode" checked> Bulk Mode (Faster)</label>
                        </div>
                        <div class="default-values">
                            <h4>Default Values:</h4>
                            <label>Starting Cash: ₦<input type="number" id="default-old-cash" value="0" step="100"></label><br>
                            <label>Daily Expenses: ₦<input type="number" id="default-expenses" value="500" step="100"></label>
                        </div>
                    </div>
                    
                    <div class="import-actions">
                        <button type="button" class="button button-primary button-hero" id="turbo-import" disabled>
                            <span class="dashicons dashicons-download"></span> Start Turbo Import
                        </button>
                        <p class="description">Configure a table first to enable import</p>
                    </div>
                    
                    <!-- Progress Section -->
                    <div id="import-progress-section" style="display: none;">
                        <div class="progress-container">
                            <div class="progress-header">
                                <h3>Import Progress</h3>
                                <span id="progress-percentage">0%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 0%;"></div>
                                </div>
                            </div>
                            <div class="progress-details">
                                <div class="progress-stats">
                                    <span id="processed-count">0</span> / <span id="total-count">0</span> days processed
                                </div>
                                <div class="progress-status">Status: <span id="import-status">Preparing...</span></div>
                                <div class="progress-eta">ETA: <span id="import-eta">Calculating...</span></div>
                            </div>
                        </div>
                        
                        <div id="import-log" class="import-log">
                            <h4>Import Log:</h4>
                            <div id="log-content"></div>
                        </div>
                    </div>
                    
                    <div id="import-results" style="margin-top: 20px;"></div>
                </div>
            </div>
        </div>
        
        <style>
        .fss-import-container {
            max-width: 1000px;
        }
        
        .fss-import-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .fss-import-section h2 {
            margin-top: 0;
            border-bottom: 3px solid #FF0000;
            padding-bottom: 12px;
            color: #333;
        }
        
        .detection-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .date-selection-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 16px;
        }
        
        .quick-ranges h4,
        .custom-range h4 {
            margin-bottom: 12px;
            color: #666;
        }
        
        .range-btn {
            margin: 4px 8px 4px 0;
            padding: 8px 16px;
        }
        
        .range-btn.active {
            background: #FF0000;
            color: white;
            border-color: #FF0000;
        }
        
        .custom-range label {
            display: block;
            margin-bottom: 8px;
        }
        
        .custom-range input {
            margin-left: 8px;
            padding: 6px 10px;
        }
        
        .import-options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin: 16px 0;
        }
        
        .import-settings label,
        .default-values label {
            display: block;
            margin-bottom: 8px;
        }
        
        .import-actions {
            text-align: center;
            margin: 24px 0;
        }
        
        .button-hero {
            padding: 12px 24px !important;
            font-size: 16px !important;
            height: auto !important;
        }
        
        .progress-container {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .progress-header h3 {
            margin: 0;
        }
        
        #progress-percentage {
            font-size: 24px;
            font-weight: bold;
            color: #FF0000;
        }
        
        .progress-bar-container {
            margin-bottom: 16px;
        }
        
        .progress-bar {
            width: 100%;
            height: 24px;
            background: #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(45deg, #FF0000, #ff3333);
            transition: width 0.3s ease;
            position: relative;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(
                -45deg,
                rgba(255,255,255,.2) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255,255,255,.2) 50%,
                rgba(255,255,255,.2) 75%,
                transparent 75%,
                transparent
            );
            animation: move 2s linear infinite;
        }
        
        @keyframes move {
            0% { background-position: 0 0; }
            100% { background-position: 40px 40px; }
        }
        
        .progress-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            font-size: 14px;
        }
        
        .import-log {
            background: #000;
            color: #0f0;
            padding: 16px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 16px;
        }
        
        #log-content {
            white-space: pre-wrap;
        }
        
        .detection-result {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            padding: 16px;
            border-radius: 8px;
            margin: 12px 0;
        }
        
        .detection-result.error {
            background: #ffeaa7;
            border-color: #fdcb6e;
        }
        
        .detection-result.warning {
            background: #fff3cd;
            border-color: #ffc107;
        }
        
        .table-details {
            margin: 15px 0;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid;
        }
        
        .table-details.high-confidence {
            border-color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }
        
        .table-details.medium-confidence {
            border-color: #ff9800;
            background: rgba(255, 152, 0, 0.1);
        }
        
        .table-details.low-confidence {
            border-color: #f44336;
            background: rgba(244, 67, 54, 0.1);
        }
        
        .sample-data {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 120px;
            overflow-y: auto;
            margin: 10px 0;
        }
        
        .sample-data div {
            margin-bottom: 4px;
        }
        
        .manual-config {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .manual-config label {
            display: block;
            margin-bottom: 12px;
        }
        
        .manual-config input[type="text"] {
            width: 400px;
            margin-left: 10px;
            padding: 8px 12px;
        }
        
        .preview-summary {
            background: #f0f8ff;
            border: 1px solid #007cba;
            padding: 20px;
            border-radius: 8px;
            margin: 16px 0;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .preview-stat {
            text-align: center;
            padding: 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        .preview-stat h4 {
            margin: 0 0 8px 0;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .preview-stat .value {
            font-size: 20px;
            font-weight: bold;
            color: #FF0000;
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .date-selection-grid,
            .import-options-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-details {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .detection-buttons {
                flex-direction: column;
            }
            
            .manual-config input[type="text"] {
                width: 100%;
                margin-left: 0;
                margin-top: 8px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let importInProgress = false;
            let startTime = 0;
            window.selectedOrderTable = null;
            
            // Quick range selection
            $('.range-btn').on('click', function() {
                $('.range-btn').removeClass('active');
                $(this).addClass('active');
                
                const days = $(this).data('range');
                const endDate = new Date();
                endDate.setDate(endDate.getDate() - 1); // Yesterday
                
                const startDate = new Date();
                startDate.setDate(startDate.getDate() - days);
                
                $('#import-start-date').val(startDate.toISOString().split('T')[0]);
                $('#import-end-date').val(endDate.toISOString().split('T')[0]);
            });
            
            // Enhanced table detection
            $('#quick-detect-tables').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.html();
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scanning...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_detect_order_tables',
                        nonce: '<?php echo wp_create_nonce('fss_import_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showDetectionResults(response.data);
                        } else {
                            showDetectionError(response.data?.message || 'Detection failed');
                        }
                    },
                    error: function() {
                        showDetectionError('Network error during detection');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });
            
            // Debug tables
            $('#debug-tables').on('click', function() {
                const $btn = $(this);
                const originalText = $btn.html();
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Loading...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_debug_tables',
                        nonce: '<?php echo wp_create_nonce('fss_import_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showDebugResults(response.data);
                        } else {
                            alert('Debug failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });
            
            // Fast preview
            $('#fast-preview').on('click', function() {
                const startDate = $('#import-start-date').val();
                const endDate = $('#import-end-date').val();
                
                if (!startDate || !endDate) {
                    alert('Please select start and end dates');
                    return;
                }
                
                if (!window.selectedOrderTable) {
                    alert('Please configure a table first');
                    return;
                }
                
                const $btn = $(this);
                const originalText = $btn.html();
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Loading...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_preview_import_data',
                        nonce: '<?php echo wp_create_nonce('fss_import_nonce'); ?>',
                        start_date: startDate,
                        end_date: endDate,
                        skip_weekends: $('#skip-weekends').is(':checked'),
                        fast_mode: true,
                        selected_table: window.selectedOrderTable
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#preview-results').html(response.data.html);
                        } else {
                            alert('Preview failed: ' + response.data.message);
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });
            
            // Turbo import
            $('#turbo-import').on('click', function() {
                if (importInProgress) {
                    alert('Import already in progress');
                    return;
                }
                
                const startDate = $('#import-start-date').val();
                const endDate = $('#import-end-date').val();
                
                if (!startDate || !endDate) {
                    alert('Please select start and end dates');
                    return;
                }
                
                if (!window.selectedOrderTable) {
                    alert('Please configure a table first');
                    return;
                }
                
                if (!confirm('Start the turbo import? This will process your historical data quickly.')) {
                    return;
                }
                
                startTurboImport();
            });
            
            function showDetectionResults(data) {
                let html = '<div class="detection-result">';
                html += '<h4>🎯 Smart Detection Complete!</h4>';
                
                if (data.tables && data.tables.length > 0) {
                    html += `<p><strong>Found ${data.tables.length} potential table(s) for Take Order Form:</strong></p>`;
                    
                    data.tables.forEach(function(table, index) {
                        const confidenceClass = table.confidence + '-confidence';
                        const confidenceIcon = table.confidence === 'high' ? '✅' : 
                                              table.confidence === 'medium' ? '⚠️' : '❌';
                        const confidenceText = table.confidence.charAt(0).toUpperCase() + table.confidence.slice(1);
                        
                        html += `<div class="table-details ${confidenceClass}">`;
                        html += `<h5 style="margin: 0 0 10px 0;">${confidenceIcon} <strong>${table.name}</strong> (${confidenceText} Confidence)</h5>`;
                        html += `<p><strong>Records:</strong> ${table.rows.toLocaleString()}</p>`;
                        html += `<p><strong>Has Payment Info:</strong> ${table.has_payment ? 'Yes ✅' : 'No ❌'}</p>`;
                        
                        if (table.columns && table.columns.length > 0) {
                            html += `<p><strong>Key Columns Found:</strong> ${table.key_columns ? table.key_columns.join(', ') : 'Standard columns detected'}</p>`;
                        }
                        
                        if (table.sample_data) {
                            html += '<p><strong>Sample Data:</strong></p>';
                            html += '<div class="sample-data">';
                            Object.keys(table.sample_data).slice(0, 6).forEach(key => {
                                const value = table.sample_data[key];
                                if (value !== null && value !== '') {
                                    html += `<div><strong>${key}:</strong> ${String(value).substring(0, 60)}${String(value).length > 60 ? '...' : ''}</div>`;
                                }
                            });
                            html += '</div>';
                        }
                        
                        if (table.confidence === 'high') {
                            html += `<button type="button" class="button button-primary" onclick="configureTable('${table.name}', '${table.confidence}')">✅ Use This Table</button>`;
                        } else {
                            html += `<button type="button" class="button" onclick="configureTable('${table.name}', '${table.confidence}')">⚙️ Configure This Table</button>`;
                        }
                        
                        html += '</div>';
                    });
                    
                    html += '<p style="color: #4caf50; font-weight: bold;">✓ Ready to configure for import!</p>';
                } else {
                    html += '<p style="color: #ff9800;">⚠️ No Take Order Form tables found automatically.</p>';
                    html += '<p>This might happen if:</p>';
                    html += '<ul>';
                    html += '<li>Your Take Order Form plugin uses custom table names</li>';
                    html += '<li>The plugin stores data differently than expected</li>';
                    html += '<li>No orders have been submitted yet</li>';
                    html += '</ul>';
                    html += '<button type="button" class="button button-primary" onclick="showManualConfig()">🔧 Manual Configuration</button>';
                }
                
                html += '</div>';
                $('#detection-results').append(html);
            }
            
            function showDebugResults(tables) {
                let html = '<div class="detection-result">';
                html += '<h4>🔧 All Database Tables</h4>';
                html += '<p>Found ' + tables.length + ' total tables:</p>';
                html += '<div class="sample-data" style="max-height: 200px;">';
                
                // Group tables by prefix
                const groupedTables = {};
                tables.forEach(table => {
                    const prefix = table.split('_')[0] + '_';
                    if (!groupedTables[prefix]) {
                        groupedTables[prefix] = [];
                    }
                    groupedTables[prefix].push(table);
                });
                
                Object.keys(groupedTables).forEach(prefix => {
                    html += `<div><strong>${prefix}*:</strong></div>`;
                    groupedTables[prefix].forEach(table => {
                        const isOrderRelated = /order|submission|form|purchase|transaction|sale/i.test(table);
                        html += `<div style="margin-left: 20px; ${isOrderRelated ? 'color: #FF0000; font-weight: bold;' : ''}">${table} ${isOrderRelated ? '← Potential match!' : ''}</div>`;
                    });
                    html += '<br>';
                });
                
                html += '</div>';
                html += '<p><strong>Red highlighted tables</strong> are potential order tables.</p>';
                html += '</div>';
                $('#detection-results').append(html);
            }
            
            function showDetectionError(message) {
                const html = `<div class="detection-result error">
                    <h4>❌ Detection Error</h4>
                    <p>${message}</p>
                    <button type="button" class="button" onclick="showManualConfig()">Try Manual Configuration</button>
                </div>`;
                $('#detection-results').append(html);
            }
            
            window.configureTable = function(tableName, confidence) {
                // Store the selected table
                window.selectedOrderTable = tableName;
                
                // Store in session for server-side use
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_set_selected_table',
                        nonce: '<?php echo wp_create_nonce('fss_import_nonce'); ?>',
                        table_name: tableName
                    }
                });
                
                // Enable buttons
                $('#fast-preview').prop('disabled', false);
                $('#turbo-import').prop('disabled', false);
                $('.import-actions .description').hide();
                
                // Show success message
                $('#detection-results').append(`
                    <div class="notice notice-success" style="margin: 10px 0; padding: 15px;">
                        <h4>✅ Table Configured Successfully!</h4>
                        <p><strong>Selected Table:</strong> ${tableName}</p>
                        <p><strong>Confidence Level:</strong> ${confidence}</p>
                        <p>You can now proceed to preview and import your historical data.</p>
                    </div>
                `);
                
                // Scroll to next section
                $('html, body').animate({
                    scrollTop: $('#fast-preview').offset().top - 100
                }, 500);
            };
            
            window.showManualConfig = function() {
                const manualHtml = `
                    <div class="manual-config">
                        <h4>🔧 Manual Table Configuration</h4>
                        <p>Enter your Take Order Form table name manually:</p>
                        <label>Table Name: 
                            <input type="text" id="manual-table-name" placeholder="e.g., wp_take_order_submissions" value="">
                        </label>
                        <p class="description">Common table names: wp_take_order_submissions, wp_order_submissions, wp_form_submissions</p>
                        <button type="button" class="button button-primary" onclick="testManualTable()">🧪 Test This Table</button>
                    </div>
                `;
                
                $('#detection-results').append(manualHtml);
            };
            
            window.testManualTable = function() {
                const tableName = $('#manual-table-name').val().trim();
                
                if (!tableName) {
                    alert('Please enter a table name');
                    return;
                }
                
                // Test the manual table
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fss_test_manual_table',
                        nonce: '<?php echo wp_create_nonce('fss_import_nonce'); ?>',
                        table_name: tableName
                    },
                    success: function(response) {
                        if (response.success) {
                            window.configureTable(tableName, 'manual');
                        } else {
                            alert('Table test failed: ' + response.data.message);
                        }
                    }
                });
            };
            
            function startTurboImport() {
                importInProgress = true;
                startTime = Date.now();
                
                $('#import-progress-section').show();
                $('#turbo-import').prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Importing...');
                
                logMessage('🚀 Starting turbo import...');
                logMessage('📋 Using table: ' + window.selectedOrderTable);
                logMessage('⚡ Optimizing database queries...');
                
                const importData = {
                    action: 'fss_batch_import',
                    nonce: '<?php echo wp_create_nonce('fss_import_nonce'); ?>',
                    start_date: $('#import-start-date').val(),
                    end_date: $('#import-end-date').val(),
                    import_mode: $('input[name="import_mode"]:checked').val(),
                    default_old_cash: $('#default-old-cash').val(),
                    default_expenses: $('#default-expenses').val(),
                    skip_weekends: $('#skip-weekends').is(':checked'),
                    bulk_mode: $('#bulk-mode').is(':checked'),
                    selected_table: window.selectedOrderTable,
                    batch: 0
                };
                
                processBatch(importData);
            }
            
            function processBatch(importData) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: importData,
                    timeout: 60000, // 1 minute timeout per batch
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            
                            updateProgress(data.progress, data.processed, data.total);
                            
                            if (data.completed) {
                                completeImport(data);
                            } else {
                                // Process next batch
                                logMessage(`✅ Batch ${data.batch + 1} complete - ${data.processed}/${data.total} days`);
                                importData.batch = data.batch + 1;
                                setTimeout(() => processBatch(importData), 100); // Small delay between batches
                            }
                        } else {
                            importError(response.data?.message || 'Batch processing failed');
                        }
                    },
                    error: function(xhr, status, error) {
                        importError(`Network error: ${error}`);
                    }
                });
            }
            
            function updateProgress(percentage, processed, total) {
                $('#progress-percentage').text(percentage + '%');
                $('.progress-fill').css('width', percentage + '%');
                $('#processed-count').text(processed);
                $('#total-count').text(total);
                
                // Calculate ETA
                const elapsed = (Date.now() - startTime) / 1000;
                const rate = processed / elapsed;
                const remaining = total - processed;
                const eta = remaining / rate;
                
                $('#import-eta').text(eta > 0 ? formatTime(eta) : 'Almost done!');
                $('#import-status').text(percentage < 100 ? 'Processing...' : 'Finalizing...');
            }
            
            function completeImport(data) {
                importInProgress = false;
                
                updateProgress(100, data.total, data.total);
                $('#import-status').text('Completed!');
                $('#import-eta').text('Done!');
                
                logMessage('🎉 Import completed successfully!');
                logMessage(`📊 Imported ${data.imported} days of financial data`);
                logMessage(`⏱️ Total time: ${formatTime((Date.now() - startTime) / 1000)}`);
                
                $('#import-results').html(`
                    <div class="notice notice-success">
                        <h3>🎉 Turbo Import Complete!</h3>
                        <p><strong>Successfully imported ${data.imported} days</strong> of financial data from your Take Order Form in ${formatTime((Date.now() - startTime) / 1000)}!</p>
                        <p>Skipped ${data.skipped} days with existing data.</p>
                        <p><a href="<?php echo admin_url('admin.php?page=financial-summary'); ?>" class="button button-primary">📊 View Financial Dashboard</a></p>
                    </div>
                `);
                
                $('#turbo-import').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Import Complete');
            }
            
            function importError(message) {
                importInProgress = false;
                
                logMessage(`❌ Error: ${message}`);
                $('#import-status').text('Error occurred');
                
                $('#import-results').html(`
                    <div class="notice notice-error">
                        <h3>❌ Import Failed</h3>
                        <p>${message}</p>
                        <p>Please check the log above for details.</p>
                    </div>
                `);
                
                $('#turbo-import').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Retry Import');
            }
            
            function logMessage(message) {
                const timestamp = new Date().toLocaleTimeString();
                const logEntry = `[${timestamp}] ${message}\n`;
                $('#log-content').append(logEntry);
                $('#log-content').scrollTop($('#log-content')[0].scrollHeight);
            }
            
            function formatTime(seconds) {
                if (seconds < 60) return Math.round(seconds) + 's';
                if (seconds < 3600) return Math.round(seconds / 60) + 'm ' + Math.round(seconds % 60) + 's';
                return Math.round(seconds / 3600) + 'h ' + Math.round((seconds % 3600) / 60) + 'm';
            }
        });
        </script>
        <?php
    }
    
    public function detect_order_tables() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_import_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        global $wpdb;
        $tables = array();
        
        // Get ALL tables in the database with optimized query
        $all_tables = $wpdb->get_col("SHOW TABLES");
        
        $potential_tables = array();
        
        // First pass: Look for Take Order Form specific tables
        $take_order_patterns = array(
            '/take.*order/i',
            '/order.*form/i',
            '/form.*submission/i',
            '/submission/i',
            '/orders/i'
        );
        
        foreach ($all_tables as $table_name) {
            $is_potential = false;
            
            // Check for Take Order Form specific patterns
            foreach ($take_order_patterns as $pattern) {
                if (preg_match($pattern, $table_name)) {
                    $is_potential = true;
                    break;
                }
            }
            
            // Also check for generic order/form patterns
            if (!$is_potential && preg_match('/order|submission|form|purchase|transaction|sale/i', $table_name)) {
                $is_potential = true;
            }
            
            if ($is_potential) {
                $potential_tables[] = $table_name;
            }
        }
        
        // Second pass: Analyze each potential table
        foreach ($potential_tables as $table) {
            try {
                $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`");
                
                // Analyze column structure for order-like patterns
                $analysis = $this->analyze_table_structure($table, $columns);
                
                if ($analysis['confidence'] !== 'none') {
                    // Get row count efficiently
                    $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table` LIMIT 1000");
                    
                    if ($row_count > 0) {
                        // Get sample data
                        $sample = $wpdb->get_row("SELECT * FROM `$table` ORDER BY id DESC LIMIT 1", ARRAY_A);
                        
                        $tables[] = array(
                            'name' => $table,
                            'rows' => intval($row_count),
                            'columns' => $columns,
                            'key_columns' => $analysis['key_columns'],
                            'has_payment' => $analysis['has_payment'],
                            'has_amount' => $analysis['has_amount'],
                            'has_date' => $analysis['has_date'],
                            'sample_data' => $sample,
                            'confidence' => $analysis['confidence'],
                            'score' => $analysis['score']
                        );
                    }
                }
            } catch (Exception $e) {
                // Skip tables we can't access
                continue;
            }
        }
        
        // Sort by confidence score and row count
        usort($tables, function($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] - $a['score'];
            }
            return $b['rows'] - $a['rows'];
        });
        
        wp_send_json_success(array('tables' => $tables));
    }
    
    private function analyze_table_structure($table_name, $columns) {
        $analysis = array(
            'has_amount' => false,
            'has_date' => false,
            'has_payment' => false,
            'key_columns' => array(),
            'confidence' => 'none',
            'score' => 0
        );
        
        $score = 0;
        
        // Check for Take Order Form specific patterns first
        if (preg_match('/take.*order/i', $table_name)) {
            $score += 30;
        }
        
        foreach ($columns as $column) {
            $column_lower = strtolower($column);
            
            // Amount/Price columns
            if (preg_match('/amount|total|price|cost|value|sum/i', $column)) {
                $analysis['has_amount'] = true;
                $analysis['key_columns'][] = $column;
                $score += 20;
            }
            
            // Date/Time columns
            if (preg_match('/date|time|created|submitted|timestamp/i', $column)) {
                $analysis['has_date'] = true;
                $analysis['key_columns'][] = $column;
                $score += 15;
            }
            
            // Payment method columns
            if (preg_match('/payment|method|type|mode/i', $column)) {
                $analysis['has_payment'] = true;
                $analysis['key_columns'][] = $column;
                $score += 15;
            }
            
            // Order specific columns
            if (preg_match('/order|customer|name|phone|email/i', $column)) {
                $analysis['key_columns'][] = $column;
                $score += 10;
            }
            
            // Take Order Form specific columns
            if (preg_match('/delivery|pickup|address|item|quantity/i', $column)) {
                $analysis['key_columns'][] = $column;
                $score += 8;
            }
        }
        
        // Determine confidence based on score and required fields
        if ($analysis['has_amount'] && $analysis['has_date']) {
            if ($score >= 50) {
                $analysis['confidence'] = 'high';
            } elseif ($score >= 30) {
                $analysis['confidence'] = 'medium';
            } else {
                $analysis['confidence'] = 'low';
            }
        } elseif ($analysis['has_amount'] || $analysis['has_date']) {
            $analysis['confidence'] = 'low';
        }
        
        $analysis['score'] = $score;
        return $analysis;
    }
    
    public function debug_tables() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_import_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        $tables = $wpdb->get_col("SHOW TABLES");
        
        wp_send_json_success($tables);
    }
    
    public function test_manual_table() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_import_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        global $wpdb;
        $table_name = sanitize_text_field($_POST['table_name']);
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            wp_send_json_error(array('message' => 'Table does not exist'));
        }
        
        try {
            // Get table structure
            $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table_name`");
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
            $sample = $wpdb->get_row("SELECT * FROM `$table_name` LIMIT 1", ARRAY_A);
            
            wp_send_json_success(array(
                'message' => 'Table found and accessible',
                'columns' => $columns,
                'rows' => $row_count,
                'sample' => $sample
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Cannot access table: ' . $e->getMessage()));
        }
    }
    
    public function preview_import_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_import_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $skip_weekends = isset($_POST['skip_weekends']) && $_POST['skip_weekends'] === 'true';
        $fast_mode = isset($_POST['fast_mode']) && $_POST['fast_mode'] === 'true';
        $selected_table = sanitize_text_field($_POST['selected_table'] ?? '');
        
        // Store selected table in session
        if ($selected_table) {
            $_SESSION['selected_order_table'] = $selected_table;
        }
        
        try {
            $preview_data = $this->get_optimized_preview($start_date, $end_date, $skip_weekends, $fast_mode);
            
            ob_start();
            ?>
            <div class="preview-summary">
                <h4>⚡ Lightning Preview Results</h4>
                <p class="description">Data from: <strong><?php echo $selected_table ?: 'Auto-detected table'; ?></strong></p>
                
                <div class="preview-grid">
                    <div class="preview-stat">
                        <h4>Days to Import</h4>
                        <div class="value"><?php echo number_format($preview_data['total_days']); ?></div>
                    </div>
                    <div class="preview-stat">
                        <h4>Total Sales</h4>
                        <div class="value">₦<?php echo number_format($preview_data['totals']['sales'], 0); ?></div>
                    </div>
                    <div class="preview-stat">
                        <h4>Total Orders</h4>
                        <div class="value"><?php echo number_format($preview_data['total_orders']); ?></div>
                    </div>
                    <div class="preview-stat">
                        <h4>Estimated Time</h4>
                        <div class="value"><?php echo $preview_data['estimated_time']; ?></div>
                    </div>
                </div>
                
                <div style="margin-top: 16px;">
                    <strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?><br>
                    <strong>Average Daily Sales:</strong> ₦<?php echo number_format($preview_data['avg_daily_sales'], 2); ?><br>
                    <strong>Peak Day:</strong> <?php echo $preview_data['peak_day']; ?> (₦<?php echo number_format($preview_data['peak_amount'], 2); ?>)<br>
                    <strong>Data Quality:</strong> <?php echo $preview_data['data_quality']; ?>
                </div>
                
                <?php if ($preview_data['total_days'] > 0): ?>
                <div style="margin-top: 16px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px;">
                    <strong>✅ Ready to Import!</strong> Click "Start Turbo Import" to proceed.
                </div>
                <?php else: ?>
                <div style="margin-top: 16px; padding: 12px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px;">
                    <strong>⚠️ No data found</strong> for the selected date range. Please check your date selection.
                </div>
                <?php endif; ?>
            </div>
            <?php
            
            wp_send_json_success(array('html' => ob_get_clean()));
            
        } catch (Exception $e) {
            wp_send_json_error('Preview failed: ' . $e->getMessage());
        }
    }
    
    public function batch_import() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_import_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Increase execution time and memory
        set_time_limit($this->max_execution_time);
        ini_set('memory_limit', '512M');
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $import_mode = sanitize_text_field($_POST['import_mode']);
        $default_old_cash = floatval($_POST['default_old_cash']);
        $default_expenses = floatval($_POST['default_expenses']);
        $skip_weekends = isset($_POST['skip_weekends']) && $_POST['skip_weekends'] === 'true';
        $bulk_mode = isset($_POST['bulk_mode']) && $_POST['bulk_mode'] === 'true';
        $selected_table = sanitize_text_field($_POST['selected_table'] ?? '');
        $batch = intval($_POST['batch']);
        
        // Store selected table in session
        if ($selected_table) {
            $_SESSION['selected_order_table'] = $selected_table;
        }
        
        try {
            $result = $this->process_import_batch(
                $start_date, 
                $end_date, 
                $import_mode, 
                $default_old_cash, 
                $default_expenses, 
                $skip_weekends, 
                $bulk_mode, 
                $batch
            );
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error('Batch import failed: ' . $e->getMessage());
        }
    }
    
    private function get_optimized_preview($start_date, $end_date, $skip_weekends = true, $fast_mode = true) {
        global $wpdb;
        
        $orders_table = $this->detect_order_table();
        if (!$orders_table) {
            throw new Exception('No order table configured. Please select a table first.');
        }
        
        $where_weekend = $skip_weekends ? "AND DAYOFWEEK(DATE(created_at)) NOT IN (1, 7)" : "";
        
        // Get column mapping for this table
        $column_mapping = $this->get_table_column_mapping($orders_table);
        
        $amount_column = $column_mapping['amount'];
        $date_column = $column_mapping['date'];
        
        if ($fast_mode) {
            // Use sampling for very large datasets
            $sample_clause = "";
            $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM `$orders_table` WHERE DATE($date_column) BETWEEN '$start_date' AND '$end_date'");
            
            if ($total_rows > 10000) {
                $sample_clause = "AND RAND() < 0.1"; // 10% sample for preview
            }
        }
        
        // Optimized aggregation query with dynamic columns
        $payment_column = $column_mapping['payment'] ?: "'unknown'";
        
        $results = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT DATE($date_column)) as total_days,
                COUNT(*) as total_orders,
                SUM($amount_column) as total_sales,
                AVG($amount_column) as avg_order,
                MAX($amount_column) as max_order,
                MIN(DATE($date_column)) as first_date,
                MAX(DATE($date_column)) as last_date
            FROM `$orders_table` 
            WHERE DATE($date_column) BETWEEN %s AND %s
            $where_weekend
            $sample_clause
        ", $start_date, $end_date));
        
        // Get peak day
        $peak_day_data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                DATE($date_column) as peak_date,
                SUM($amount_column) as peak_amount
            FROM `$orders_table` 
            WHERE DATE($date_column) BETWEEN %s AND %s
            $where_weekend
            GROUP BY DATE($date_column)
            ORDER BY peak_amount DESC
            LIMIT 1
        ", $start_date, $end_date));
        
        // Estimate processing time (roughly 10 days per second in bulk mode)
        $estimated_seconds = ceil(($results->total_days ?: 0) / 10);
        $estimated_time = $estimated_seconds < 60 ? $estimated_seconds . 's' : 
                         ($estimated_seconds < 3600 ? ceil($estimated_seconds / 60) . 'm' : 
                          ceil($estimated_seconds / 3600) . 'h');
        
        // Assess data quality
        $data_quality = 'Unknown';
        if ($results->total_orders > 0) {
            if ($results->avg_order > 100 && $results->total_days > 0) {
                $data_quality = 'Excellent';
            } elseif ($results->avg_order > 50) {
                $data_quality = 'Good';
            } else {
                $data_quality = 'Fair';
            }
        }
        
        return array(
            'total_days' => intval($results->total_days ?: 0),
            'total_orders' => intval($results->total_orders ?: 0),
            'totals' => array(
                'sales' => floatval($results->total_sales ?: 0)
            ),
            'avg_daily_sales' => ($results->total_days ?: 0) > 0 ? floatval($results->total_sales ?: 0) / $results->total_days : 0,
            'peak_day' => $peak_day_data ? date('M d, Y', strtotime($peak_day_data->peak_date)) : 'N/A',
            'peak_amount' => $peak_day_data ? floatval($peak_day_data->peak_amount) : 0,
            'estimated_time' => $estimated_time,
            'data_quality' => $data_quality
        );
    }
    
    private function get_table_column_mapping($table_name) {
        global $wpdb;
        
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table_name`");
        
        $mapping = array(
            'amount' => null,
            'date' => null,
            'payment' => null
        );
        
        foreach ($columns as $column) {
            // Amount column mapping
            if (!$mapping['amount'] && preg_match('/amount|total|price|cost|value/i', $column)) {
                $mapping['amount'] = $column;
            }
            
            // Date column mapping
            if (!$mapping['date'] && preg_match('/created|date|time|submitted|timestamp/i', $column)) {
                $mapping['date'] = $column;
            }
            
            // Payment method column mapping
            if (!$mapping['payment'] && preg_match('/payment|method|type|mode/i', $column)) {
                $mapping['payment'] = $column;
            }
        }
        
        // Set defaults if not found
        if (!$mapping['amount']) {
            // Try common names
            $common_amount_names = array('total_amount', 'amount', 'price', 'cost', 'total');
            foreach ($common_amount_names as $name) {
                if (in_array($name, $columns)) {
                    $mapping['amount'] = $name;
                    break;
                }
            }
        }
        
        if (!$mapping['date']) {
            $common_date_names = array('created_at', 'date_created', 'timestamp', 'date', 'created');
            foreach ($common_date_names as $name) {
                if (in_array($name, $columns)) {
                    $mapping['date'] = $name;
                    break;
                }
            }
        }
        
        // Final fallback - use first suitable column
        if (!$mapping['amount'] && !empty($columns)) {
            $mapping['amount'] = $columns[0]; // Assume first column might be ID, second might be amount
            if (count($columns) > 1) {
                $mapping['amount'] = $columns[1];
            }
        }
        
        if (!$mapping['date']) {
            $mapping['date'] = 'created_at'; // Common default
        }
        
        return $mapping;
    }
    
    private function process_import_batch($start_date, $end_date, $import_mode, $default_old_cash, $default_expenses, $skip_weekends, $bulk_mode, $batch) {
        global $wpdb;
        
        // Get all dates in range
        $dates = $this->get_date_range($start_date, $end_date, $skip_weekends);
        $total_dates = count($dates);
        
        // Calculate batch boundaries
        $batch_start = $batch * $this->batch_size;
        $batch_end = min($batch_start + $this->batch_size, $total_dates);
        $batch_dates = array_slice($dates, $batch_start, $this->batch_size);
        
        $imported = 0;
        $skipped = 0;
        $previous_cash_left = $default_old_cash;
        
        if ($bulk_mode) {
            // Ultra-fast bulk processing
            $result = $this->bulk_process_dates($batch_dates, $import_mode, $default_expenses, $previous_cash_left);
            $imported = $result['imported'];
            $skipped = $result['skipped'];
        } else {
            // Standard processing
            foreach ($batch_dates as $date) {
                $result = $this->process_single_date($date, $import_mode, $default_expenses, $previous_cash_left);
                if ($result['imported']) {
                    $imported++;
                    $previous_cash_left = $result['cash_left'];
                } else {
                    $skipped++;
                }
            }
        }
        
        $progress = min(100, round(($batch_end / $total_dates) * 100));
        $completed = $batch_end >= $total_dates;
        
        return array(
            'batch' => $batch,
            'progress' => $progress,
            'processed' => $batch_end,
            'total' => $total_dates,
            'imported' => $imported,
            'skipped' => $skipped,
            'completed' => $completed
        );
    }
    
    private function bulk_process_dates($dates, $import_mode, $default_expenses, $starting_cash) {
        global $wpdb;
        
        $orders_table = $this->detect_order_table();
        $summary_table = $wpdb->prefix . 'fss_daily_summaries';
        
        $imported = 0;
        $skipped = 0;
        $previous_cash_left = $starting_cash;
        
        // Get column mapping
        $column_mapping = $this->get_table_column_mapping($orders_table);
        $amount_column = $column_mapping['amount'];
        $date_column = $column_mapping['date'];
        $payment_column = $column_mapping['payment'];
        
        // Bulk fetch all order data for these dates
        $date_list = "'" . implode("','", $dates) . "'";
        
        $payment_case = $payment_column ? 
            "SUM(CASE WHEN $payment_column IN ('transfer', 'card', 'bank') THEN $amount_column ELSE 0 END) as transfer_card_total,
             SUM(CASE WHEN $payment_column = 'cash' THEN $amount_column ELSE 0 END) as cash_total," :
            "SUM($amount_column * 0.6) as transfer_card_total,
             SUM($amount_column * 0.4) as cash_total,";
        
        $orders_data = $wpdb->get_results("
            SELECT 
                DATE($date_column) as order_date,
                $payment_case
                SUM($amount_column) as total_sales,
                COUNT(*) as order_count
            FROM `$orders_table` 
            WHERE DATE($date_column) IN ($date_list)
            GROUP BY DATE($date_column)
            ORDER BY order_date ASC
        ");
        
        // Check existing summaries in bulk
        $existing_summaries = array();
        if ($import_mode === 'smart_merge') {
            $existing = $wpdb->get_results("
                SELECT date FROM $summary_table 
                WHERE date IN ($date_list)
            ");
            foreach ($existing as $row) {
                $existing_summaries[$row->date] = true;
            }
        }
        
        // Prepare bulk insert data
        $insert_values = array();
        $current_user = wp_get_current_user();
        $created_by = $current_user->display_name ?: 'System Import';
        
        foreach ($orders_data as $order_data) {
            $date = $order_data->order_date;
            
            // Skip if exists and in smart merge mode
            if ($import_mode === 'smart_merge' && isset($existing_summaries[$date])) {
                $skipped++;
                continue;
            }
            
            // Calculate cash left
            $total_cash_in = floatval($order_data->cash_total) + $previous_cash_left;
            $cash_left = $total_cash_in - $default_expenses;
            
            $insert_values[] = $wpdb->prepare("(%s, %f, %f, %f, %f, %f, %s, %f, %s, %f, %f, %f, %s, %s)",
                $date,
                floatval($order_data->total_sales),
                floatval($order_data->transfer_card_total),
                floatval($order_data->cash_total),
                0, // delivery (will be calculated separately if needed)
                0, // extras
                'Imported from Take Order Form',
                $default_expenses,
                'Default import expense',
                $previous_cash_left, // old_cash
                $cash_left,
                0, // cash_left_market_card
                $created_by,
                current_time('mysql')
            );
            
            $imported++;
            $previous_cash_left = $cash_left;
        }
        
        // Bulk insert if we have data
        if (!empty($insert_values)) {
            $values_string = implode(',', $insert_values);
            
            if ($import_mode === 'force_replace') {
                // Delete existing first
                $wpdb->query("DELETE FROM $summary_table WHERE date IN ($date_list)");
            }
            
            $sql = "INSERT INTO $summary_table 
                    (date, total_sales, transfer_card, cash, delivery, extras, extras_remark, 
                     expense, expense_remark, old_cash, cash_left, cash_left_market_card, created_by, created_at) 
                    VALUES $values_string";
            
            $wpdb->query($sql);
        }
        
        return array('imported' => $imported, 'skipped' => $skipped);
    }
    
    private function process_single_date($date, $import_mode, $default_expenses, $previous_cash_left) {
        // Check if summary already exists
        $existing = FSS_Database::get_daily_summary($date);
        
        if ($existing && $import_mode === 'smart_merge') {
            return array('imported' => false, 'cash_left' => $existing->cash_left);
        }
        
        // Get orders data using the selected table
        $orders_data = $this->get_orders_data_for_date_custom($date);
        
        if ($orders_data['total_sales'] == 0) {
            return array('imported' => false, 'cash_left' => $previous_cash_left);
        }
        
        // Calculate cash left
        $total_cash_in = $orders_data['cash'] + $previous_cash_left;
        $cash_left = $total_cash_in - $default_expenses;
        
        // Prepare summary data
        $summary_data = array(
            'total_sales' => $orders_data['total_sales'],
            'transfer_card' => $orders_data['transfer_card'],
            'cash' => $orders_data['cash'],
            'delivery' => $orders_data['delivery'],
            'extras' => 0,
            'extras_remark' => 'Imported from Take Order Form',
            'expense' => $default_expenses,
            'expense_remark' => 'Default import expense',
            'old_cash' => $previous_cash_left,
            'cash_left' => $cash_left,
            'created_by' => 'System Import'
        );
        
        if ($import_mode === 'force_replace' && $existing) {
            // Delete existing summary
            global $wpdb;
            $wpdb->delete($wpdb->prefix . 'fss_daily_summaries', array('id' => $existing->id));
            $wpdb->delete($wpdb->prefix . 'fss_history_entries', array('summary_id' => $existing->id));
        }
        
        // Create new summary
        $summary_id = FSS_Database::create_or_update_daily_summary($date, $summary_data);
        
        return array('imported' => true, 'cash_left' => $cash_left);
    }
    
    private function get_orders_data_for_date_custom($date) {
        global $wpdb;
        
        $orders_table = $this->detect_order_table();
        if (!$orders_table) {
            return array(
                'total_sales' => 0,
                'transfer_card' => 0,
                'cash' => 0,
                'delivery' => 0
            );
        }
        
        // Get column mapping
        $column_mapping = $this->get_table_column_mapping($orders_table);
        $amount_column = $column_mapping['amount'];
        $date_column = $column_mapping['date'];
        $payment_column = $column_mapping['payment'];
        
        // Build payment method query
        if ($payment_column) {
            $payment_query = "
                SUM(CASE WHEN $payment_column IN ('transfer', 'card', 'bank', 'online') THEN $amount_column ELSE 0 END) as transfer_card_total,
                SUM(CASE WHEN $payment_column = 'cash' THEN $amount_column ELSE 0 END) as cash_total,
            ";
        } else {
            // If no payment column, assume 60/40 split (common for many businesses)
            $payment_query = "
                SUM($amount_column * 0.6) as transfer_card_total,
                SUM($amount_column * 0.4) as cash_total,
            ";
        }
        
        // Get orders for the date
        $results = $wpdb->get_row($wpdb->prepare("
            SELECT 
                $payment_query
                SUM($amount_column) as total_sales,
                0 as delivery_total
            FROM `$orders_table` 
            WHERE DATE($date_column) = %s
        ", $date));
        
        if ($results) {
            return array(
                'total_sales' => floatval($results->total_sales ?? 0),
                'transfer_card' => floatval($results->transfer_card_total ?? 0),
                'cash' => floatval($results->cash_total ?? 0),
                'delivery' => floatval($results->delivery_total ?? 0)
            );
        }
        
        return array(
            'total_sales' => 0,
            'transfer_card' => 0,
            'cash' => 0,
            'delivery' => 0
        );
    }
    
    private function get_date_range($start_date, $end_date, $skip_weekends = true) {
        $dates = array();
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current <= $end) {
            $day_of_week = intval($current->format('w')); // 0 = Sunday, 6 = Saturday
            
            if (!$skip_weekends || ($day_of_week != 0 && $day_of_week != 6)) {
                $dates[] = $current->format('Y-m-d');
            }
            
            $current->add(new DateInterval('P1D'));
        }
        
        return $dates;
    }
    
    private function detect_order_table() {
        // Check if a table was manually selected
        if (isset($_SESSION['selected_order_table'])) {
            return $_SESSION['selected_order_table'];
        }
        
        global $wpdb;
        
        // Enhanced detection for Take Order Form plugin
        $possible_tables = array(
            $wpdb->prefix . 'take_order_submissions',
            $wpdb->prefix . 'takeorder_submissions',
            $wpdb->prefix . 'take_order_form_submissions',
            $wpdb->prefix . 'order_form_submissions',
            $wpdb->prefix . 'order_submissions',
            $wpdb->prefix . 'form_submissions',
            $wpdb->prefix . 'submissions',
            $wpdb->prefix . 'orders',
            $wpdb->prefix . 'contact_form_submissions'
        );
        
        foreach ($possible_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`");
                
                // More flexible column checking for Take Order Form
                $has_amount = false;
                $has_date = false;
                
                foreach ($columns as $column) {
                    if (preg_match('/amount|total|price|cost|value|sum/i', $column)) {
                        $has_amount = true;
                    }
                    if (preg_match('/date|time|created|submitted|timestamp/i', $column)) {
                        $has_date = true;
                    }
                }
                
                if ($has_amount && $has_date) {
                    return $table;
                }
            }
        }
        
        return false;
    }
    
    // Add method to set selected table via AJAX
    public function set_selected_table() {
        if (!wp_verify_nonce($_POST['nonce'], 'fss_import_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $table_name = sanitize_text_field($_POST['table_name']);
        $_SESSION['selected_order_table'] = $table_name;
        
        wp_send_json_success(array('message' => 'Table selected: ' . $table_name));
    }
}