// Financial Summary System - Frontend JavaScript (Complete Fixed Version)

jQuery(document).ready(function($) {
    console.log('FSS: Initializing Financial Summary System');
    initializeRealTimeClock();
    initializeCumulativeHiddenFields();
    initializeFormCalculations();
    handleFormSubmission();
    if (typeof Chart !== 'undefined') {
        initializeAnalytics();
    }
    initializeHistory();
    initializeAutoSave();
    injectNotificationStyles();
    initializeDetailModalHandlers();
    initializeTouchGestures();
    initializeKeyboardShortcuts();
    enforceReadOnlyFields();
});

/* ------------------------- Enforce Read-Only Fields ------------------------- */
function enforceReadOnlyFields() {
    const $ = jQuery;
    
    const readOnlyFields = [
        'total_sales',
        'transfer_card',
        'cash',
        'delivery',
        'old_cash',
        'cash_left'
    ];
    
    readOnlyFields.forEach(function(fieldName) {
        const field = $('#' + fieldName);
        if (field.length) {
            field.prop('readonly', true);
            field.addClass('fss-readonly-field');
            field.attr('tabindex', '-1');
            
            field.on('keydown keypress keyup paste cut', function(e) {
                e.preventDefault();
                return false;
            });
            
            field.on('focus', function() {
                $(this).blur();
            });
        }
    });
}

/* ------------------------- Hidden cumulative fields ------------------------- */
function initializeCumulativeHiddenFields() {
    const $ = jQuery;
    const existingExtras = parseFloat($('#extras').data('cumulative')) || 0;
    const existingExpense = parseFloat($('#expense').data('cumulative')) || 0;

    if (!$('#cumulative_extras').length) {
        $('<input type="hidden" id="cumulative_extras" value="' + existingExtras + '">').appendTo('#fss-summary-form');
    }
    if (!$('#cumulative_expense').length) {
        $('<input type="hidden" id="cumulative_expense" value="' + existingExpense + '">').appendTo('#fss-summary-form');
    }
}

/* ------------------------- Real-Time Clock ------------------------- */
function initializeRealTimeClock() {
    function updateTime() {
        const now = new Date();
        const lagosTime = new Date(now.toLocaleString("en-US", {timeZone: "Africa/Lagos"}));
        
        jQuery('#fss-current-date').text(lagosTime.toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        }));
        jQuery('#fss-current-time').text(lagosTime.toLocaleTimeString('en-US', {
            hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
        }));
    }
    updateTime();
    setInterval(updateTime, 1000);
}

/* ------------------------- Form Calculations ------------------------- */
function initializeFormCalculations() {
    const $ = jQuery;

    function projectCashLeft() {
        const cash = parseFloat($('#cash').val()) || 0;
        const oldCash = parseFloat($('#old_cash').val()) || 0;
        const marketCard = parseFloat($('#cash_left_market_card').val()) || 0;

        const cumulativeExtras = parseFloat($('#cumulative_extras').val()) || 0;
        const cumulativeExpense = parseFloat($('#cumulative_expense').val()) || 0;

        const deltaExtras = parseFloat($('#extras').val()) || 0;
        const deltaExpense = parseFloat($('#expense').val()) || 0;

        const projectedExtrasTotal = cumulativeExtras + deltaExtras;
        const projectedExpenseTotal = cumulativeExpense + deltaExpense;

        const projectedCashLeft = cash + oldCash + projectedExtrasTotal + marketCard - projectedExpenseTotal;
        $('#cash_left').val(projectedCashLeft.toFixed(2));
    }

    $('#cash, #old_cash, #cash_left_market_card, #extras, #expense').on('input', projectCashLeft);

    window.fssProjectCashLeft = projectCashLeft;
    projectCashLeft();
}

/* ------------------------- Form Submission ------------------------- */
let isSubmitting = false;

function handleFormSubmission() {
    const $ = jQuery;

    $('#fss-summary-form').on('submit', function(e) {
        e.preventDefault();

        if (isSubmitting) {
            showNotification('Please wait, submission in progress...', 'warning');
            return false;
        }

        const deltaExtras = $('#extras').val();
        const deltaExpense = $('#expense').val();
        const extrasRemark = $('#extras_remark').val();
        const expenseRemark = $('#expense_remark').val();

        if (
            (parseFloat(deltaExtras) || 0) === 0 &&
            (parseFloat(deltaExpense) || 0) === 0 &&
            !extrasRemark.trim() &&
            !expenseRemark.trim()
        ) {
            showNotification('Enter a value or a remark before submitting', 'error');
            return false;
        }

        const marketCard = parseFloat($('#cash_left_market_card').val()) || 0;

        const formData = {
            action: 'fss_submit_summary',
            fss_nonce: $('input[name="fss_nonce"]').val(),
            extras: deltaExtras,
            extras_remark: extrasRemark,
            expense: deltaExpense,
            expense_remark: expenseRemark,
            cash_left_market_card: marketCard
        };

        isSubmitting = true;
        $('#fss-loading').show();
        $('.fss-submit-btn').prop('disabled', true).text('Submitting...');

        $.ajax({
            url: fss_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('Summary submitted successfully!', 'success');

                    const updated = response.data.updated_values || {};
                    if (typeof updated.extras !== 'undefined') {
                        $('#cumulative_extras').val(updated.extras);
                    }
                    if (typeof updated.expense !== 'undefined') {
                        $('#cumulative_expense').val(updated.expense);
                    }

                    Object.keys(updated).forEach(k => {
                        const $field = $('#' + k);
                        if ($field.length) {
                            $field.val(updated[k]);
                        }
                    });

                    $('#extras, #expense, #extras_remark, #expense_remark').val('');

                    if (typeof window.fssProjectCashLeft === 'function') {
                        window.fssProjectCashLeft();
                    }

                    if ($('.fss-history-btn').length) {
                        setTimeout(function() {
                            if (typeof window.loadHistoryTable === 'function') {
                                window.loadHistoryTable(1);
                            }
                        }, 500);
                    }
                } else {
                    showNotification(response.data.message || 'Submission failed', 'error');
                }
            },
            error: function() {
                showNotification('Network error occurred', 'error');
            },
            complete: function() {
                isSubmitting = false;
                $('#fss-loading').hide();
                $('.fss-submit-btn').prop('disabled', false).text('Save Summary');
            }
        });

        return false;
    });
}

/* ------------------------- History Table with Fixed Pagination ------------------------- */
function initializeHistory() {
    const $ = jQuery;
    
    function loadHistoryTable(page) {
        page = parseInt(page);
        if (isNaN(page) || page < 1) {
            page = 1;
        }
        
        const filters = {
            date_from: $('#filter-date-from').val() || '',
            date_to: $('#filter-date-to').val() || '',
            created_by: $('#filter-created-by').val() || ''
        };
        
        console.log('FSS: Loading history page:', page, 'Filters:', filters);
        
        $('#fss-history-table-container').html('<div class="fss-loading-spinner">Loading history data...</div>');
        $('#fss-history-pagination').html('');
        
        $.ajax({
            url: fss_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fss_get_history_table',
                nonce: fss_ajax.nonce,
                page: page,
                filters: filters
            },
            success: function(response) {
                console.log('FSS: History response:', response);
                
                if (response.success) {
                    $('#fss-history-table-container').html(response.data.table);
                    $('#fss-history-pagination').html(response.data.pagination);
                    
                    if ($('#fss-history-table-container').length) {
                        $('html, body').animate({
                            scrollTop: $('#fss-history-table-container').offset().top - 100
                        }, 300);
                    }
                } else {
                    showNotification('Failed to load history', 'error');
                    $('#fss-history-table-container').html('<div class="error-message">Failed to load history data.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('FSS: History AJAX error:', status, error);
                showNotification('Network error occurred', 'error');
                $('#fss-history-table-container').html('<div class="error-message">Network error occurred. Please try again.</div>');
            }
        });
    }
    
    window.loadHistoryTable = loadHistoryTable;
    
    $(document).on('click', '#apply-filters', function(e) {
        e.preventDefault();
        console.log('FSS: Apply filters clicked');
        loadHistoryTable(1);
        return false;
    });
    
    $(document).on('click', '#clear-filters', function(e) {
        e.preventDefault();
        console.log('FSS: Clear filters clicked');
        $('#filter-date-from, #filter-date-to, #filter-created-by').val('');
        loadHistoryTable(1);
        return false;
    });
    
    // CRITICAL FIX: Multiple event handlers for pagination
    $(document).on('click', '.fss-page-btn', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const $btn = $(this);
        const page = parseInt($btn.attr('data-page'));
        
        console.log('FSS: Pagination button clicked - Page:', page);
        
        if (!isNaN(page) && page > 0) {
            loadHistoryTable(page);
        } else {
            console.error('FSS: Invalid page number:', $btn.attr('data-page'));
        }
        
        return false;
    });
    
    $(document).on('click', '.fss-pagination button', function(e) {
        if (!$(this).hasClass('fss-page-btn')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const page = parseInt($(this).data('page'));
        if (!isNaN(page) && page > 0) {
            loadHistoryTable(page);
        }
        return false;
    });
    
    if ($('#fss-history-table-container').length) {
        console.log('FSS: Initial history load');
        loadHistoryTable(1);
    }
}

/* ------------------------- Notifications ------------------------- */
function showNotification(message, type) {
    const $ = jQuery;
    $('.fss-notification').remove();
    const n = $('<div class="fss-notification fss-notification-' + type + '">' + message + '</div>');
    $('body').append(n);
    setTimeout(() => n.fadeOut(() => n.remove()), 5000);
    n.on('click', () => n.fadeOut(() => n.remove()));
}

function injectNotificationStyles() {
    if (!jQuery('#fss-notification-styles').length) {
        jQuery('<style id="fss-notification-styles">').text(`
            .fss-notification{position:fixed;top:20px;right:20px;padding:15px 20px;border-radius:8px;font-weight:600;z-index:10001;cursor:pointer;box-shadow:0 4px 6px rgba(0,0,0,0.1);animation:fssSlideIn .3s ease-out;}
            .fss-notification-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
            .fss-notification-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
            .fss-notification-warning{background:#fff3cd;color:#856404;border:1px solid #ffeaa7;}
            .fss-autosave-indicator{position:fixed;bottom:20px;right:20px;background:#28a745;color:#fff;padding:8px 15px;border-radius:6px;font-size:12px;z-index:10001;}
            @keyframes fssSlideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}
            @media (max-width:480px){.fss-notification{top:10px;right:10px;left:10px;}}
        `).appendTo('head');
    }
}

/* ------------------------- Analytics ------------------------- */
function initializeAnalytics() {
    const $ = jQuery;
    function loadAnalytics(period){
        period = period || 7;
        $.ajax({
            url:fss_ajax.ajax_url,type:'POST',
            data:{action:'fss_get_analytics',nonce:fss_ajax.nonce,period:period},
            success:function(r){ if(r.success){ renderCharts(r.data); } }
        });
    }
    function formatN(v){ return '₦'+(parseFloat(v)||0).toLocaleString(); }
    function renderCharts(data){
        if(document.getElementById('sales-trend-chart')){
            const ctx1=document.getElementById('sales-trend-chart').getContext('2d');
            new Chart(ctx1,{type:'line',data:{labels:data.dates,datasets:[{label:'Total Sales',data:data.sales,borderColor:'#FF0000',backgroundColor:'rgba(255,0,0,0.1)',tension:.4}]},
                options:{responsive:true,scales:{y:{beginAtZero:true,ticks:{callback:v=>formatN(v)}}}}});
        }
        if(document.getElementById('cash-flow-chart')){
            const ctx2=document.getElementById('cash-flow-chart').getContext('2d');
            new Chart(ctx2,{type:'bar',data:{labels:data.dates,datasets:[
                {label:'Cash In',data:data.cash_in,backgroundColor:'rgba(0,170,0,0.7)'},
                {label:'Cash Out',data:data.cash_out,backgroundColor:'rgba(220,0,0,0.7)'}
            ]},options:{responsive:true,scales:{y:{beginAtZero:true,ticks:{callback:v=>formatN(v)}}}}});
        }
        if(document.getElementById('payment-methods-chart')){
            const ctx3=document.getElementById('payment-methods-chart').getContext('2d');
            new Chart(ctx3,{type:'doughnut',data:{labels:['Transfer/Card','Cash'],datasets:[{data:[data.total_transfer_card,data.total_cash],backgroundColor:['#FF0000','#00AA00']}]},
                options:{responsive:true,plugins:{legend:{position:'bottom'}}}});
        }
        if(document.getElementById('key-metrics')){
            jQuery('#key-metrics').html(`
                <div class="fss-metric"><span class="fss-metric-label">Average Daily Sales</span><span class="fss-metric-value">${formatN(data.avg_daily_sales)}</span></div>
                <div class="fss-metric"><span class="fss-metric-label">Total Period Sales</span><span class="fss-metric-value">${formatN(data.total_period_sales)}</span></div>
                <div class="fss-metric"><span class="fss-metric-label">Total Expenses</span><span class="fss-metric-value">${formatN(data.total_expenses)}</span></div>
                <div class="fss-metric"><span class="fss-metric-label">Net Cash Flow</span><span class="fss-metric-value">${formatN(data.total_period_sales - data.total_expenses)}</span></div>
            `);
        }
    }
    jQuery('#analytics-period').on('change',function(){loadAnalytics(jQuery(this).val());});
    jQuery('#refresh-analytics').on('click',function(){loadAnalytics(jQuery('#analytics-period').val());});
    loadAnalytics(7);
}

/* ------------------------- Auto Save ------------------------- */
function initializeAutoSave() {
    const $ = jQuery;
    let t;
    $('#extras,#extras_remark,#expense,#expense_remark').on('input',function(){
        clearTimeout(t);
        t=setTimeout(function(){
            $.ajax({
                url:fss_ajax.ajax_url,type:'POST',
                data:{
                    action:'fss_save_draft',nonce:fss_ajax.nonce,
                    extras:$('#extras').val(),extras_remark:$('#extras_remark').val(),
                    expense:$('#expense').val(),expense_remark:$('#expense_remark').val()
                },
                success:function(r){ if(r.success) showAutoSaveIndicator(); }
            });
        },30000);
    });
}

function showAutoSaveIndicator(){
    const $ = jQuery;
    const i=$('<div class="fss-autosave-indicator">Draft saved</div>');
    $('body').append(i);
    setTimeout(()=>i.fadeOut(()=>i.remove()),2000);
}

/* ------------------------- Quick Actions ------------------------- */
function exportToday(){
    const today=new Date().toISOString().split('T')[0];
    window.open(fss_ajax.ajax_url+'?action=fss_export_day&date='+today+'&nonce='+fss_ajax.nonce,'_blank');
}

function showReconciliation(){
    const $=jQuery;
    $.ajax({
        url:fss_ajax.ajax_url,type:'POST',
        data:{action:'fss_show_reconciliation',nonce:fss_ajax.nonce},
        success:function(r){
            if(r.success){
                const modal=$(`
                    <div class="fss-modal" id="reconciliation-modal">
                        <div class="fss-modal-content">
                            <span class="fss-close" onclick="closeReconciliation()">&times;</span>
                            <h2>Daily Reconciliation</h2>
                            ${r.data.html}
                        </div>
                    </div>
                `);
                $('body').append(modal); modal.show();
            }
        }
    });
}

function closeReconciliation(){ 
    jQuery('#reconciliation-modal').remove(); 
}

function showHistory(date) {
    const $ = jQuery;
    $.ajax({
        url: fss_ajax.ajax_url,
        type: 'POST',
        data: { action: 'fss_get_history', nonce: fss_ajax.nonce, date: date },
        success: function(r) {
            if (r.success) {
                $('#fss-history-content').html(r.data.html);
                $('#fss-history-modal').show();
            } else {
                showNotification('Failed to load history', 'error');
            }
        }
    });
}

function closeHistory() { 
    jQuery('#fss-history-modal').hide(); 
}

/* ------------------------- Touch Gestures ------------------------- */
function initializeTouchGestures(){
    if(!('ontouchstart' in window)) return;
    let sx=0, sy=0;
    jQuery('.fss-history-container')
        .on('touchstart',e=>{
            sx=e.originalEvent.touches[0].pageX;
            sy=e.originalEvent.touches[0].pageY;
        })
        .on('touchmove',e=>e.preventDefault())
        .on('touchend',e=>{
            const ex=e.originalEvent.changedTouches[0].pageX;
            const ey=e.originalEvent.changedTouches[0].pageY;
            const dx=sx-ex, dy=sy-ey;
            if(Math.abs(dx)>Math.abs(dy) && Math.abs(dx)>50){
                const $active=jQuery('.fss-page-btn.active'); if(!$active.length) return;
                const cur=parseInt($active.data('page'));
                if(dx>0){
                    const next=jQuery(`.fss-page-btn[data-page="${cur+1}"]`); if(next.length) next.click();
                } else {
                    const prev=jQuery(`.fss-page-btn[data-page="${cur-1}"]`); if(prev.length) prev.click();
                }
            }
        });
}

/* ------------------------- Keyboard Shortcuts ------------------------- */
function initializeKeyboardShortcuts(){
    jQuery(document).on('keydown',function(e){
        if((e.ctrlKey||e.metaKey)&&e.key==='s'){ 
            e.preventDefault(); 
            if (!isSubmitting) {
                jQuery('#fss-summary-form').submit(); 
            }
        }
        if((e.ctrlKey||e.metaKey)&&e.key==='h'){ 
            e.preventDefault(); 
            showHistory(new Date().toISOString().split('T')[0]); 
        }
        if(e.key==='Escape'){ 
            jQuery('.fss-modal').hide(); 
        }
    });
}

/* ------------------------- Detail Modal (Fixed View Button) ------------------------- */
function initializeDetailModalHandlers(){
    const $=jQuery;
    
    $(document).on('click', '.view-details-btn, .admin-view-details, button[onclick^="showHistory"]', function(e){
        e.preventDefault();
        const date = $(this).data('date') || $(this).attr('onclick')?.match(/'([^']+)'/)?.[1];
        console.log('FSS: View details clicked for date:', date);
        if(date) showDetailModal(date);
        return false;
    });
    
    // Edit history button handler
    $(document).on('click', '.edit-history-btn', function(e){
        e.preventDefault();
        const date = $(this).data('date');
        console.log('FSS: Edit history clicked for date:', date);
        if(date) showEditModal(date);
        return false;
    });
    
    // Delete history button handler
    $(document).on('click', '.delete-history-btn', function(e){
        e.preventDefault();
        const date = $(this).data('date');
        console.log('FSS: Delete history clicked for date:', date);
        if(date && confirm('Are you sure you want to delete the history for ' + date + '? This action cannot be undone.')) {
            deleteHistory(date);
        }
        return false;
    });
    
    $(document).on('click','.fss-modal-close,.fss-modal',function(e){
        if(e.target===this){ closeDetailModal(); closeEditModal(); }
    });
    
    $(document).on('keydown',function(e){
        if(e.key==='Escape'){ closeDetailModal(); closeEditModal(); }
    });

    function showDetailModal(date){
        if(!$('#fss-detail-modal').length){
            $('body').append(`
                <div id="fss-detail-modal" class="fss-modal" style="display:none;">
                    <div class="fss-modal-content">
                        <div class="fss-modal-header">
                            <h2 id="modal-title">Financial Details</h2>
                            <span class="fss-modal-close">&times;</span>
                        </div>
                        <div class="fss-modal-body" id="modal-body-content">
                            <div class="loading-spinner">Loading detailed information...</div>
                        </div>
                    </div>
                </div>
            `);
        }
        $('#fss-detail-modal').show();
        $('#modal-body-content').html('<div class="loading-spinner">Loading detailed information...</div>');
        $('#modal-title').text('Loading...');
        
        console.log('FSS: Fetching modal data for date:', date);
        
        $.ajax({
            url:fss_ajax.ajax_url,
            type:'POST',
            data:{action:'fss_get_detailed_history',nonce:fss_ajax.nonce,date:date},
            success:function(r){
                console.log('FSS: Modal data received:', r);
                if(r.success){ 
                    renderDetailModal(r.data); 
                }
                else { 
                    $('#modal-body-content').html('<div class="error-message">Failed to load data: ' + (r.data || 'Unknown error') + '</div>'); 
                }
            },
            error:function(xhr, status, error){ 
                console.error('FSS: Modal AJAX error:', status, error);
                $('#modal-body-content').html('<div class="error-message">Network error: ' + error + '</div>'); 
            }
        });
    }

    function renderDetailModal(data){
        console.log('FSS: Rendering modal with data:', data);
        
        $('#modal-title').text('Financial Details - ' + data.formatted_date);
        let html = '';
        const o = data.orders_data;
        const s = data.summary;
        
        if (!s) {
            html = '<div class="error-message">No summary data available for this date.</div>';
            $('#modal-body-content').html(html);
            return;
        }
        
        // Order Data Section
        html += '<div class="detail-section order-data"><h3>Order Data</h3><div class="detail-grid">';
        html += createDetailItem('Total Sales', '₦' + numberFormat(parseFloat(s.total_sales) || 0), '');
        html += createDetailItem('Transfer/Card', '₦' + numberFormat(parseFloat(s.transfer_card) || 0), '');
        html += createDetailItem('Cash Sales', '₦' + numberFormat(parseFloat(s.cash) || 0), '');
        html += createDetailItem('Delivery Fees', '₦' + numberFormat(parseFloat(s.delivery) || 0), '');
        
        if (o && o.order_count && o.order_count > 0) { 
            html += createDetailItem('Total Orders', numberFormat(o.order_count, 0), ''); 
        }
        if (o && o.avg_order_value && o.avg_order_value > 0) { 
            html += createDetailItem('Avg Order Value', '₦' + numberFormat(o.avg_order_value), ''); 
        }
        html += '</div>';
        
        if (data.integration_status && data.is_admin) {
            html += '<p class="meta-note"><strong>Source:</strong> ' + (data.integration_status.table_name || 'Manual') + '</p>';
        }
        html += '</div>';

        // Manual Entries Section
        html += '<div class="detail-section manual-entries"><h3>Manual Entries</h3><div class="detail-grid">';
        html += createDetailItem('Extras Total', '₦' + numberFormat(parseFloat(s.extras) || 0), s.extras_remark || 'No remark');
        html += createDetailItem('Expenses Total', '₦' + numberFormat(parseFloat(s.expense) || 0), s.expense_remark || 'No remark');
        html += '</div></div>';

        // Cash Flow Section
        const netProfit = (parseFloat(s.total_sales) || 0) - (parseFloat(s.expense) || 0);
        html += '<div class="detail-section cash-flow"><h3>Cash Flow</h3><div class="detail-grid">';
        html += createDetailItem('Opening Cash', '₦' + numberFormat(parseFloat(s.old_cash) || 0), 'Previous day balance');
        html += createDetailItem('Market Card Cash', '₦' + numberFormat(parseFloat(s.cash_left_market_card) || 0), '');
        html += createDetailItem('Cash Left', '₦' + numberFormat(parseFloat(s.cash_left) || 0), 'Closing balance');
        html += createDetailItem('Net Profit', '₦' + numberFormat(netProfit), netProfit >= 0 ? 'Profit' : 'Loss');
        html += '</div></div>';

        // History Entries Section
        if (data.history_entries && data.history_entries.length > 0) {
            html += '<div class="detail-section history-entries"><h3>Individual Entries (' + data.history_entries.length + ')</h3>';
            data.history_entries.forEach(entry => {
                html += '<div class="history-entry">';
                html += '<div class="entry-header">';
                html += '<span class="entry-user">' + escapeHtml(entry.created_by || 'Unknown') + '</span>';
                html += '<span class="entry-time">' + formatDateTime(entry.created_at) + '</span>';
                html += '</div>';
                
                if (entry.extras > 0 || entry.expense > 0 || entry.extras_remark || entry.expense_remark) {
                    html += '<div class="entry-details">';
                    if (entry.extras > 0) {
                        html += '<div class="entry-detail"><strong>Extras:</strong> ₦' + numberFormat(entry.extras) + '</div>';
                        if (entry.extras_remark) {
                            html += '<div class="entry-detail"><strong>Extras Remark:</strong> ' + escapeHtml(entry.extras_remark) + '</div>';
                        }
                    }
                    if (entry.expense > 0) {
                        html += '<div class="entry-detail"><strong>Expense:</strong> ₦' + numberFormat(entry.expense) + '</div>';
                        if (entry.expense_remark) {
                            html += '<div class="entry-detail"><strong>Expense Remark:</strong> ' + escapeHtml(entry.expense_remark) + '</div>';
                        }
                    }
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<div class="detail-section history-entries"><h3>Individual Entries</h3><p>No manual entries recorded for this date.</p></div>';
        }

        $('#modal-body-content').html(html);
    }

    function closeDetailModal(){ 
        $('#fss-detail-modal').hide(); 
    }
    
    window.closeDetailModal = closeDetailModal;
    window.showDetailModal = showDetailModal;
    
    // Edit Modal Functions
    function showEditModal(date){
        console.log('FSS: Loading edit modal for date:', date);
        
        $.ajax({
            url: fss_ajax.ajax_url,
            type: 'POST',
            data: {action: 'fss_edit_history_date', nonce: fss_ajax.nonce, date: date},
            success: function(r){
                if(r.success){ 
                    renderEditModal(date, r.data); 
                } else { 
                    showNotification('Failed to load edit data: ' + (r.data || 'Unknown error'), 'error'); 
                }
            },
            error: function(xhr, status, error){ 
                showNotification('Network error while loading edit data', 'error'); 
            }
        });
    }
    
    function renderEditModal(date, data){
        const s = data.summary;
        
        if(!$('#fss-edit-modal').length){
            $('body').append(`
                <div id="fss-edit-modal" class="fss-modal" style="display:none;">
                    <div class="fss-modal-content fss-edit-modal-content">
                        <div class="fss-modal-header">
                            <h2 id="edit-modal-title">Edit Financial History</h2>
                            <span class="fss-modal-close">&times;</span>
                        </div>
                        <div class="fss-modal-body" id="edit-modal-body">
                        </div>
                    </div>
                </div>
            `);
        }
        
        let html = '<form id="edit-history-form" class="fss-edit-form">';
        html += '<input type="hidden" id="edit-date" value="' + date + '">';
        
        html += '<div class="edit-form-section">';
        html += '<h3>Order Data</h3>';
        html += '<div class="form-row">';
        html += '<label>Total Sales (₦):</label>';
        html += '<input type="number" id="edit-total-sales" value="' + (s.total_sales || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Transfer/Card (₦):</label>';
        html += '<input type="number" id="edit-transfer-card" value="' + (s.transfer_card || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Cash Sales (₦):</label>';
        html += '<input type="number" id="edit-cash" value="' + (s.cash || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Delivery (₦):</label>';
        html += '<input type="number" id="edit-delivery" value="' + (s.delivery || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="edit-form-section">';
        html += '<h3>Manual Entries</h3>';
        html += '<div class="form-row">';
        html += '<label>Extras (₦):</label>';
        html += '<input type="number" id="edit-extras" value="' + (s.extras || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Extras Remark:</label>';
        html += '<textarea id="edit-extras-remark" rows="2">' + (s.extras_remark || '') + '</textarea>';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Expenses (₦):</label>';
        html += '<input type="number" id="edit-expense" value="' + (s.expense || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Expense Remark:</label>';
        html += '<textarea id="edit-expense-remark" rows="2">' + (s.expense_remark || '') + '</textarea>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="edit-form-section">';
        html += '<h3>Cash Flow</h3>';
        html += '<div class="form-row">';
        html += '<label>Old Cash (Opening Balance) (₦):</label>';
        html += '<input type="number" id="edit-old-cash" value="' + (s.old_cash || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Market Card Cash (₦):</label>';
        html += '<input type="number" id="edit-cash-left-market-card" value="' + (s.cash_left_market_card || 0) + '" step="0.01" min="0">';
        html += '</div>';
        html += '<div class="form-row">';
        html += '<label>Cash Left (Auto-calculated):</label>';
        html += '<input type="number" id="edit-cash-left-display" value="' + (s.cash_left || 0) + '" step="0.01" readonly>';
        html += '<small class="form-help">Formula: Cash Sales + Extras + Old Cash + Market Card - Expenses</small>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="form-actions">';
        html += '<button type="submit" class="fss-btn fss-btn-primary">Save Changes</button>';
        html += '<button type="button" class="fss-btn fss-btn-secondary" onclick="closeEditModal()">Cancel</button>';
        html += '</div>';
        html += '</form>';
        
        $('#edit-modal-body').html(html);
        $('#edit-modal-title').text('Edit Financial History - ' + date);
        $('#fss-edit-modal').show();
        
        // Auto-calculate cash left on input change
        $('#edit-cash, #edit-old-cash, #edit-extras, #edit-cash-left-market-card, #edit-expense').on('input', function(){
            calculateEditCashLeft();
        });
        
        // Form submission
        $('#edit-history-form').on('submit', function(e){
            e.preventDefault();
            saveEditedHistory();
        });
    }
    
    function calculateEditCashLeft(){
        const $ = jQuery;
        const cash = parseFloat($('#edit-cash').val()) || 0;
        const oldCash = parseFloat($('#edit-old-cash').val()) || 0;
        const extras = parseFloat($('#edit-extras').val()) || 0;
        const marketCard = parseFloat($('#edit-cash-left-market-card').val()) || 0;
        const expense = parseFloat($('#edit-expense').val()) || 0;
        
        // Formula: cash_left = cash_sales + extras + old_cash + market_card - expenses
        let cashLeft = (cash + oldCash + extras + marketCard) - expense;
        cashLeft = Math.max(0, cashLeft); // Ensure no negative value
        
        $('#edit-cash-left-display').val(cashLeft.toFixed(2));
    }
    
    function saveEditedHistory(){
        const $ = jQuery;
        const date = $('#edit-date').val();
        
        const formData = {
            action: 'fss_update_history_date',
            nonce: fss_ajax.nonce,
            date: date,
            total_sales: $('#edit-total-sales').val(),
            transfer_card: $('#edit-transfer-card').val(),
            cash: $('#edit-cash').val(),
            delivery: $('#edit-delivery').val(),
            extras: $('#edit-extras').val(),
            extras_remark: $('#edit-extras-remark').val(),
            expense: $('#edit-expense').val(),
            expense_remark: $('#edit-expense-remark').val(),
            old_cash: $('#edit-old-cash').val(),
            cash_left_market_card: $('#edit-cash-left-market-card').val()
        };
        
        $.ajax({
            url: fss_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(r){
                if(r.success){
                    showNotification('History updated successfully!', 'success');
                    closeEditModal();
                    // Reload history table
                    if(typeof loadHistoryTable === 'function'){
                        loadHistoryTable(1);
                    }
                } else {
                    showNotification('Failed to update history: ' + (r.data || 'Unknown error'), 'error');
                }
            },
            error: function(){
                showNotification('Network error while saving changes', 'error');
            }
        });
    }
    
    function closeEditModal(){
        $('#fss-edit-modal').hide();
    }
    
    function deleteHistory(date){
        const $ = jQuery;
        
        $.ajax({
            url: fss_ajax.ajax_url,
            type: 'POST',
            data: {action: 'fss_delete_history_date', nonce: fss_ajax.nonce, date: date},
            success: function(r){
                if(r.success){
                    showNotification('History deleted successfully!', 'success');
                    // Reload history table
                    if(typeof loadHistoryTable === 'function'){
                        loadHistoryTable(1);
                    }
                } else {
                    showNotification('Failed to delete history: ' + (r.data || 'Unknown error'), 'error');
                }
            },
            error: function(){
                showNotification('Network error while deleting history', 'error');
            }
        });
    }
    
    window.closeEditModal = closeEditModal;
    window.showEditModal = showEditModal;
    window.deleteHistory = deleteHistory;
}

/* ------------------------- Helpers ------------------------- */
function numberFormat(val, dec){
    dec = dec !== undefined ? dec : 2;
    const n = parseFloat(val);
    if(isNaN(n)) return (0).toFixed(dec);
    return n.toLocaleString('en-US',{minimumFractionDigits:dec,maximumFractionDigits:dec});
}

function escapeHtml(txt){ 
    const d = document.createElement('div'); 
    d.textContent = txt || ''; 
    return d.innerHTML; 
}

function formatDateTime(ds){
    const d = new Date(ds);
    if(isNaN(d.getTime())) return ds;
    return d.toLocaleString('en-US',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',hour12:true});
}

function createDetailItem(label, value, remark){
    let h = '<div class="detail-item">';
    h += '<div class="detail-item-label">' + escapeHtml(label) + '</div>';
    h += '<div class="detail-item-value">' + escapeHtml(value) + '</div>';
    if(remark) h += '<div class="detail-item-remark">' + escapeHtml(remark) + '</div>';
    h += '</div>';
    return h;
}

/* ------------------------- Global Exports ------------------------- */
window.exportToday = exportToday;
window.showHistory = showHistory;
window.closeHistory = closeHistory;
window.showReconciliation = showReconciliation;
window.closeReconciliation = closeReconciliation;
window.loadHistoryTable = window.loadHistoryTable || function(){};
window.closeDetailModal = window.closeDetailModal || function(){};
window.showDetailModal = window.showDetailModal || function(){};
window.fssNumberFormat = numberFormat;
window.fssProjectCashLeft = window.fssProjectCashLeft || function(){};

window.FSS = {
    exportToday: exportToday,
    showHistory: showHistory,
    closeHistory: closeHistory,
    showReconciliation: showReconciliation,
    closeReconciliation: closeReconciliation,
    closeDetailModal: window.closeDetailModal,
    showDetailModal: window.showDetailModal,
    numberFormat: numberFormat,
    projectCashLeft: window.fssProjectCashLeft
};