/**
 * Live Orders Integration JavaScript
 */

(function($) {
    'use strict';
    
    const FSS_LiveOrders = {
        refreshInterval: 30000, // 30 seconds
        refreshTimer: null,
        isRefreshing: false,
        
        init: function() {
            this.bindEvents();
            this.startAutoRefresh();
            this.setupVisibilityHandling();
            
            // Initial refresh after 2 seconds
            setTimeout(() => {
                this.refreshOrders(false);
            }, 2000);
        },
        
        bindEvents: function() {
            // Manual refresh buttons
            $(document).on('click', '#refresh-orders-btn, #manual-refresh-btn', (e) => {
                e.preventDefault();
                this.refreshOrders(true);
            });
            
            // Auto-calculate cash left
            $(document).on('input', '#cash, #old_cash, #extras, #cash_left_market_card, #expense', () => {
                this.calculateCashLeft();
            });
            
            // Form submission
            $(document).on('submit', '#fss-summary-form', (e) => {
                e.preventDefault();
                this.submitForm();
            });
        },
        
        startAutoRefresh: function() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
            }
            
            const container = $('.fss-container[data-auto-refresh="true"]');
            if (container.length === 0) return;
            
            this.refreshTimer = setInterval(() => {
                if (!this.isRefreshing && !document.hidden) {
                    this.refreshOrders(false);
                }
            }, this.refreshInterval);
        },
        
        stopAutoRefresh: function() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        },
        
        setupVisibilityHandling: function() {
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopAutoRefresh();
                } else {
                    this.startAutoRefresh();
                    // Refresh immediately when page becomes visible
                    setTimeout(() => {
                        this.refreshOrders(false);
                    }, 1000);
                }
            });
        },
        
        refreshOrders: function(showSpinner = true) {
            if (this.isRefreshing) return;
            
            this.isRefreshing = true;
            const date = $('.fss-container').data('date') || fss_live.current_date;
            
            if (showSpinner) {
                $('#refresh-spinner').show();
                this.showNotification(fss_live.strings.refreshing, 'info', 2000);
            }
            
            $.ajax({
                url: fss_live.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fss_get_live_orders',
                    nonce: fss_live.nonce,
                    date: date
                },
                timeout: 10000,
                success: (response) => {
                    if (response.success) {
                        this.updateDisplay(response.data);
                        $('#last-refresh-time').text(response.data.timestamp);
                        
                        if (showSpinner) {
                            this.showNotification(fss_live.strings.updated, 'success');
                        }
                    } else {
                        if (showSpinner) {
                            this.showNotification(response.data || fss_live.strings.error, 'error');
                        }
                    }
                },
                error: () => {
                    if (showSpinner) {
                        this.showNotification(fss_live.strings.error, 'error');
                    }
                },
                complete: () => {
                    this.isRefreshing = false;
                    if (showSpinner) {
                        $('#refresh-spinner').hide();
                    }
                }
            });
        },
        
        updateDisplay: function(data) {
            // Update live statistics
            $('#live-total-orders').text(data.formatted.order_count);
            $('#live-total-sales').text(data.formatted.total_sales);
            $('#live-card-sales').text(data.formatted.transfer_card);
            $('#live-cash-sales').text(data.formatted.cash);
            $('#live-delivery-fees').text(data.formatted.delivery);
            $('#live-avg-order').text(data.formatted.avg_order);
            
            // Update form fields (only if they're auto-fields)
            $('.auto-field').each(function() {
                const fieldName = $(this).attr('name');
                if (data.orders_data[fieldName] !== undefined) {
                    $(this).val(data.orders_data[fieldName]);
                }
            });
            
            // Update status indicator
            const indicator = $('.status-indicator');
            const statusText = $('.status-text');
            
            if (data.orders_data.order_count > 0) {
                indicator.addClass('active');
                statusText.text('Live data updating every 30 seconds');
            } else {
                indicator.removeClass('active');
                statusText.text(fss_live.strings.no_orders);
            }
            
            // Recalculate cash left
            this.calculateCashLeft();
            
            // Add visual feedback for updated fields
            $('.auto-field').addClass('field-updated');
            setTimeout(() => {
                $('.auto-field').removeClass('field-updated');
            }, 1000);
        },
        
        calculateCashLeft: function() {
            const cash = parseFloat($('#cash').val()) || 0;
            const oldCash = parseFloat($('#old_cash').val()) || 0;
            const extras = parseFloat($('#extras').val()) || 0;
            const marketCard = parseFloat($('#cash_left_market_card').val()) || 0;
            const expenses = parseFloat($('#expense').val()) || 0;
            
            const cashLeft = cash + oldCash + extras + marketCard - expenses;
            $('#cash_left').val(cashLeft.toFixed(2));
            
            // Visual indication of calculation
            $('#cash_left').addClass('field-calculated');
            setTimeout(() => {
                $('#cash_left').removeClass('field-calculated');
            }, 500);
        },
        
        submitForm: function() {
            const form = $('#fss-summary-form');
            const submitBtn = form.find('.fss-submit-btn');
            const originalText = submitBtn.html();
            
            // Disable submit button
            submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
            
            const formData = form.serialize();
            const integrationConnected = form.data('integration') === 'true';
            
            $.ajax({
                url: fss_live.ajaxurl,
                type: 'POST',
                data: formData + '&integration_connected=' + integrationConnected,
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                        
                        // Update form with fresh data if available
                        if (response.data.orders_data) {
                            this.updateFormFields(response.data.orders_data);
                        }
                        
                        // Trigger a refresh to ensure data is current
                        setTimeout(() => {
                            this.refreshOrders(false);
                        }, 1000);
                        
                    } else {
                        this.showNotification(response.data || 'Failed to save summary', 'error');
                    }
                },
                error: () => {
                    this.showNotification('Network error occurred', 'error');
                },
                complete: () => {
                    // Re-enable submit button
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        },
        
        updateFormFields: function(ordersData) {
            Object.keys(ordersData).forEach(key => {
                const field = $(`#${key}`);
                if (field.length && field.hasClass('auto-field')) {
                    field.val(ordersData[key]);
                }
            });
            
            this.calculateCashLeft();
        },
        
        showNotification: function(message, type = 'info', duration = 3000) {
            // Remove existing notifications
            $('.fss-notification').remove();
            
            const notification = $(`
                <div class="fss-notification fss-notification-${type}">
                    <span class="notification-icon"></span>
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">&times;</button>
                </div>
            `);
            
            $('body').append(notification);
            
            // Auto-remove after duration
            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, duration);
            
            // Manual close
            notification.find('.notification-close').on('click', () => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(() => {
        FSS_LiveOrders.init();
    });
    
    // Expose to global scope for external access
    window.FSS_LiveOrders = FSS_LiveOrders;
    
})(jQuery);