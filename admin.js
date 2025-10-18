// Financial Summary System - Admin JavaScript

jQuery(document).ready(function($) {
    // Initialize admin functionality
    initializeAdminDashboard();
    initializeAnalytics();
    initializeHistoryManagement();
    initializeSettings();
    initializeReconciliation();
    
    // Auto-refresh dashboard every 5 minutes
    setInterval(function() {
        refreshDashboardWidgets();
    }, 300000); // 5 minutes
});

function initializeAdminDashboard() {
    const $ = jQuery;
    
    // Load dashboard data
    loadDashboardStats();
    
    // Refresh button functionality
    $('.fss-refresh-dashboard').on('click', function() {
        loadDashboardStats();
        showAdminMessage('Dashboard refreshed', 'success');
    });
    
    // Quick export functionality
    $('.fss-quick-export').on('click', function() {
        const period = $(this).data('period') || 'today';
        exportData(period);
    });
}

function loadDashboardStats() {
    const $ = jQuery;
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'fss_get_dashboard_stats',
            nonce: fss_admin.nonce
        },
        success: function(response) {
            if (response.success) {
                updateDashboardStats(response.data);
            }
        },
        error: function() {
            showAdminMessage('Failed to load dashboard stats', 'error');
        }
    });
}

function updateDashboardStats(data) {
    const $ = jQuery;
    
    // Update stat cards
    $('.fss-today-sales').text('₦' + number_format(data.today.sales, 2));
    $('.fss-today-expenses').text('₦' + number_format(data.today.expenses, 2));
    $('.fss-today-cash-left').text('₦' + number_format(data.today.cash_left, 2));
    $('.fss-today-net').text('₦' + number_format(data.today.sales - data.today.expenses, 2));
    
    // Update trends
    updateTrendIndicators(data.trends);
    
    // Update recent activity
    updateRecentActivity(data.recent_activity);
}

function updateTrendIndicators(trends) {
    const $ = jQuery;
    
    Object.keys(trends).forEach(function(key) {
        const trend = trends[key];
        const element = $('.fss-trend-' + key);
        
        if (element.length) {
            element.removeClass('positive negative neutral');
            element.addClass(trend > 0 ? 'positive' : trend < 0 ? 'negative' : 'neutral');
            element.text((trend > 0 ? '+' : '') + trend.toFixed(1) + '%');
        }
    });
}

function updateRecentActivity(activities) {
    const $ = jQuery;
    const container = $('.fss-recent-activity');
    
    if (!container.length) return;
    
    let html = '';
    activities.forEach(function(activity) {
        html += `
            <div class="fss-activity-item">
                <div class="fss-activity-icon">${getActivityIcon(activity.type)}</div>
                <div class="fss-activity-content">
                    <div class="fss-activity-title">${activity.title}</div>
                    <div class="fss-activity-time">${activity.time}</div>
                </div>
                <div class="fss-activity-amount">₦${number_format(activity.amount, 2)}</div>
            </div>
        `;
    });
    
    container.html(html);
}

function getActivityIcon(type) {
    const icons = {
        'summary': '📊',
        'expense': '💸',
        'extra': '💰',
        'reconciliation': '⚖️'
    };
    return icons[type] || '📝';
}

function initializeAnalytics() {
    const $ = jQuery;
    
    // Initialize charts if Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        loadAnalyticsCharts();
    }
    
    // Period selector
    $('#admin-analytics-period').on('change', function() {
        const period = $(this).val();
        loadAnalyticsCharts(period);
    });
    
    // Refresh analytics
    $('#admin-refresh-analytics').on('click', function() {
        loadAnalyticsCharts();
        showAdminMessage('Analytics refreshed', 'success');
    });
    
    // Export analytics
    $('.fss-export-analytics').on('click', function() {
        const format = $(this).data('format') || 'csv';
        const period = $('#admin-analytics-period').val() || 30;
        exportAnalytics(period, format);
    });
}

function loadAnalyticsCharts(period = 30) {
    const $ = jQuery;
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'fss_get_analytics_data',
            nonce: fss_admin.nonce,
            period: period
        },
        success: function(response) {
            if (response.success) {
                renderAnalyticsCharts(response.data);
                updateKPIs(response.data.trends);
            } else {
                showAdminMessage('Failed to load analytics data', 'error');
            }
        },
        error: function() {
            showAdminMessage('Network error while loading analytics', 'error');
        }
    });
}

function renderAnalyticsCharts(data) {
    // Sales Trend Chart
    if (document.getElementById('admin-sales-chart')) {
        const ctx1 = document.getElementById('admin-sales-chart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (window.adminSalesChart) {
            window.adminSalesChart.destroy();
        }
        
        window.adminSalesChart = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: data.sales_data.dates,
                datasets: [{
                    label: 'Total Sales',
                    data: data.sales_data.total_sales,
                    borderColor: '#FF0000',
                    backgroundColor: 'rgba(255, 0, 0, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Sales Trend'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + number_format(value, 0);
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Cash Flow Chart
    if (document.getElementById('admin-cashflow-chart')) {
        const ctx2 = document.getElementById('admin-cashflow-chart').getContext('2d');
        
        if (window.adminCashflowChart) {
            window.adminCashflowChart.destroy();
        }
        
        window.adminCashflowChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: data.cash_flow.dates,
                datasets: [{
                    label: 'Cash In',
                    data: data.cash_flow.cash_in,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)'
                }, {
                    label: 'Cash Out',
                    data: data.cash_flow.cash_out,
                    backgroundColor: 'rgba(220, 53, 69, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Cash Flow'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + number_format(value, 0);
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Expense Breakdown Chart
    if (document.getElementById('admin-expense-chart')) {
        const ctx3 = document.getElementById('admin-expense-chart').getContext('2d');
        
        if (window.adminExpenseChart) {
            window.adminExpenseChart.destroy();
        }
        
        window.adminExpenseChart = new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: data.expense_breakdown.categories,
                datasets: [{
                    data: data.expense_breakdown.amounts,
                    backgroundColor: [
                        '#FF0000', '#FF6B6B', '#FF9999', '#FFCCCC',
                        '#FFA500', '#FFD700', '#90EE90', '#87CEEB',
                        '#DDA0DD', '#F0E68C'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Expense Categories'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Payment Methods Chart
    if (document.getElementById('admin-payment-chart')) {
        const ctx4 = document.getElementById('admin-payment-chart').getContext('2d');
        
        if (window.adminPaymentChart) {
            window.adminPaymentChart.destroy();
        }
        
        window.adminPaymentChart = new Chart(ctx4, {
            type: 'pie',
            data: {
                labels: ['Transfer/Card', 'Cash'],
                datasets: [{
                    data: [data.payment_methods.transfer_card, data.payment_methods.cash],
                    backgroundColor: ['#FF0000', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Payment Method Distribution'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

function updateKPIs(trends) {
    const $ = jQuery;
    
    const kpisHtml = `
        <div class="fss-metric">
            <span class="fss-metric-label">Average Daily Sales</span>
            <span class="fss-metric-value">₦${number_format(trends.avg_daily_sales, 2)}</span>
        </div>
        <div class="fss-metric">
            <span class="fss-metric-label">Total Period Sales</span>
            <span class="fss-metric-value">₦${number_format(trends.total_sales, 2)}</span>
        </div>
        <div class="fss-metric">
            <span class="fss-metric-label">Total Expenses</span>
            <span class="fss-metric-value">₦${number_format(trends.total_expense, 2)}</span>
        </div>
        <div class="fss-metric">
            <span class="fss-metric-label">Net Profit</span>
            <span class="fss-metric-value">₦${number_format(trends.total_sales - trends.total_expense, 2)}</span>
        </div>
        <div class="fss-metric">
            <span class="fss-metric-label">Sales Growth</span>
            <span class="fss-metric-value ${trends.sales_growth >= 0 ? 'positive' : 'negative'}">
                ${trends.sales_growth >= 0 ? '+' : ''}${trends.sales_growth}%
            </span>
        </div>
        <div class="fss-metric">
            <span class="fss-metric-label">Expense Growth</span>
            <span class="fss-metric-value ${trends.expense_growth >= 0 ? 'negative' : 'positive'}">
                ${trends.expense_growth >= 0 ? '+' : ''}${trends.expense_growth}%
            </span>
        </div>
    `;
    
    $('#admin-kpis').html(kpisHtml);
}

function initializeHistoryManagement() {
    const $ = jQuery;
    
    // Load history table
    loadHistoryTable();
    
    // Filter functionality
    $('#admin-apply-filters').on('click', function() {
        loadHistoryTable(1);
    });
    
    $('#admin-clear-filters').on('click', function() {
        $('#admin-filter-from, #admin-filter-to, #admin-filter-user').val('');
        loadHistoryTable(1);
    });
    
    // Export filtered results
    $('#admin-export-filtered').on('click', function() {
        exportFilteredHistory();
    });
    
    // Pagination handling
    $(document).on('click', '.fss-admin-pagination a', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            loadHistoryTable(page);
        }
    });
}

function loadHistoryTable(page = 1) {
    const $ = jQuery;
    
    const filters = {
        date_from: $('#admin-filter-from').val(),
        date_to: $('#admin-filter-to').val(),
        created_by: $('#admin-filter-user').val()
    };
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'fss_get_admin_history',
            nonce: fss_admin.nonce,
            page: page,
            filters: filters
        },
        success: function(response) {
            if (response.success) {
                $('#admin-history-table').html(response.data.html);
            } else {
                showAdminMessage('Failed to load history', 'error');
            }
        },
        error: function() {
            showAdminMessage('Network error while loading history', 'error');
        }
    });
}

function initializeSettings() {
    const $ = jQuery;
    
    // Test notification functionality
    $('.fss-test-notification').on('click', function() {
        sendTestNotification();
    });
    
    // Generate new VAPID keys
    $('.fss-generate-vapid').on('click', function() {
        generateVapidKeys();
    });
    
    // Save settings
    $('.fss-save-settings').on('click', function() {
        saveSettings();
    });
    
    // Import/Export settings
    $('.fss-export-settings').on('click', function() {
        exportSettings();
    });
    
    $('.fss-import-settings').on('change', function() {
        importSettings(this.files[0]);
    });
}

function sendTestNotification() {
    const $ = jQuery;
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'fss_send_test_notification',
            nonce: fss_admin.nonce
        },
        success: function(response) {
            if (response.success) {
                showAdminMessage('Test notification sent successfully', 'success');
            } else {
                showAdminMessage('Failed to send test notification: ' + response.data, 'error');
            }
        },
        error: function() {
            showAdminMessage('Network error while sending test notification', 'error');
        }
    });
}

function initializeReconciliation() {
    const $ = jQuery;
    
    // Quick reconciliation button
    $('.fss-quick-reconcile').on('click', function() {
        startQuickReconciliation();
    });
    
    // Approve reconciliation
    $(document).on('click', '.fss-approve-reconciliation', function() {
        const reconciliationId = $(this).data('id');
        approveReconciliation(reconciliationId);
    });
    
    // Reject reconciliation
    $(document).on('click', '.fss-reject-reconciliation', function() {
        const reconciliationId = $(this).data('id');
        rejectReconciliation(reconciliationId);
    });
}

function startQuickReconciliation() {
    const $ = jQuery;
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'fss_start_reconciliation',
            nonce: fss_admin.nonce,
            date: new Date().toISOString().split('T')[0]
        },
        success: function(response) {
            if (response.success) {
                showReconciliationModal(response.data);
            } else {
                showAdminMessage('Failed to start reconciliation: ' + response.data.message, 'error');
            }
        },
        error: function() {
            showAdminMessage('Network error while starting reconciliation', 'error');
        }
    });
}

function showReconciliationModal(data) {
    const $ = jQuery;
    
    const modalHtml = `
        <div class="fss-admin-modal" id="reconciliation-modal">
            <div class="fss-admin-modal-content">
                <div class="fss-admin-modal-header">
                    <h3>Daily Reconciliation - ${data.date}</h3>
                    <span class="fss-admin-modal-close">&times;</span>
                </div>
                <div class="fss-admin-modal-body">
                    <form id="admin-reconciliation-form">
                        <div class="fss-form-group">
                            <label>Expected Cash Left:</label>
                            <input type="number" id="admin-expected-cash" value="${data.expected_cash}" readonly>
                        </div>
                        <div class="fss-form-group">
                            <label>Actual Cash Count:</label>
                            <input type="number" id="admin-actual-cash" step="0.01" required>
                        </div>
                        <div class="fss-form-group">
                            <label>Discrepancy:</label>
                            <input type="number" id="admin-discrepancy" readonly>
                        </div>
                        <div class="fss-form-group">
                            <label>Notes:</label>
                            <textarea id="admin-reconciliation-notes" rows="3"></textarea>
                        </div>
                        <div class="fss-form-actions">
                            <button type="submit" class="button button-primary">Save Reconciliation</button>
                            <button type="button" class="button" onclick="closeReconciliationModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modalHtml);
    $('#reconciliation-modal').show();
    
    // Calculate discrepancy on input
    $('#admin-actual-cash').on('input', function() {
        const expected = parseFloat($('#admin-expected-cash').val()) || 0;
        const actual = parseFloat($(this).val()) || 0;
        const discrepancy = actual - expected;
        $('#admin-discrepancy').val(discrepancy.toFixed(2));
    });
    
    // Handle form submission
    $('#admin-reconciliation-form').on('submit', function(e) {
        e.preventDefault();
        saveAdminReconciliation(data.date);
    });
    
    // Close modal functionality
    $('.fss-admin-modal-close, .fss-admin-modal').on('click', function(e) {
        if (e.target === this) {
            closeReconciliationModal();
        }
    });
}

function closeReconciliationModal() {
    jQuery('#reconciliation-modal').remove();
}

function saveAdminReconciliation(date) {
    const $ = jQuery;
    
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'fss_save_reconciliation',
            nonce: fss_admin.nonce,
            date: date,
            expected_cash: $('#admin-expected-cash').val(),
            actual_cash: $('#admin-actual-cash').val(),
            notes: $('#admin-reconciliation-notes').val()
        },
        success: function(response) {
            if (response.success) {
                showAdminMessage('Reconciliation saved successfully', 'success');
                closeReconciliationModal();
                refreshDashboardWidgets();
            } else {
                showAdminMessage('Failed to save reconciliation: ' + response.data.message, 'error');
            }
        },
        error: function() {
            showAdminMessage('Network error while saving reconciliation', 'error');
        }
    });
}

// Utility functions
function exportData(period) {
    const date = new Date().toISOString().split('T')[0];
    const url = `${ajaxurl}?action=fss_export_day&date=${date}&period=${period}&nonce=${fss_admin.nonce}`;
    window.open(url, '_blank');
}

function exportAnalytics(period, format) {
    const url = `${ajaxurl}?action=fss_export_analytics&period=${period}&format=${format}&nonce=${fss_admin.nonce}`;
    window.open(url, '_blank');
}

function exportFilteredHistory() {
    const $ = jQuery;
    const filters = {
        date_from: $('#admin-filter-from').val(),
        date_to: $('#admin-filter-to').val(),
        created_by: $('#admin-filter-user').val()
    };
    
    const params = new URLSearchParams({
        action: 'fss_export_filtered_history',
        nonce: fss_admin.nonce,
        ...filters
    });
    
    window.open(`${ajaxurl}?${params.toString()}`, '_blank');
}

function refreshDashboardWidgets() {
    // Trigger WordPress dashboard widget refresh
    if (typeof postboxes !== 'undefined') {
        jQuery('.fss-dashboard-widget').each(function() {
            const widgetId = jQuery(this).attr('id');
            if (widgetId) {
                jQuery('#' + widgetId + ' .hndle').trigger('click').trigger('click');
            }
        });
    }
}

function showAdminMessage(message, type = 'info') {
    const $ = jQuery;
    
    const alertClass = type === 'success' ? 'notice-success' : 
                     type === 'error' ? 'notice-error' : 
                     type === 'warning' ? 'notice-warning' : 'notice-info';
    
    const notice = $(`
        <div class="notice ${alertClass} is-dismissible fss-admin-notice-dynamic">
            <p>${message}</p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        </div>
    `);
    
    $('.wrap h1').after(notice);
    
    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        notice.fadeOut(function() {
            notice.remove();
        });
    }, 5000);
    
    // Manual dismiss
    notice.on('click', '.notice-dismiss', function() {
        notice.fadeOut(function() {
            notice.remove();
        });
    });
}

function number_format(number, decimals = 2, dec_point = '.', thousands_sep = ',') {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    const n = !isFinite(+number) ? 0 : +number;
    const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
    const sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep;
    const dec = (typeof dec_point === 'undefined') ? '.' : dec_point;
    let s = '';
    
    const toFixedFix = function(n, prec) {
        const k = Math.pow(10, prec);
        return '' + (Math.round(n * k) / k)
            .toFixed(prec);
    };
    
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    
    return s.join(dec);
}

// Initialize tooltips and help text
function initializeTooltips() {
    const $ = jQuery;
    
    // Add tooltips to metric cards
    $('.fss-stat-card').each(function() {
        const title = $(this).find('h3').text();
        const tooltips = {
            'Today\'s Sales': 'Total sales amount for today including all payment methods',
            'Cash Left': 'Remaining cash after all expenses and calculations',
            'Today\'s Expenses': 'Total expenses recorded for today',
            'Net Flow': 'Sales minus expenses (profit/loss for today)'
        };
        
        if (tooltips[title]) {
            $(this).attr('title', tooltips[title]);
        }
    });
}

// Initialize on document ready
jQuery(document).ready(function() {
    initializeTooltips();
});
    