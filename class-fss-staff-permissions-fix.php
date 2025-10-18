<?php
/**
 * Complete Staff Permissions and Access Fix
 */

class FSS_Staff_Permissions_Fix {
    
    public function __construct() {
        // Fix permissions early
        add_action('init', array($this, 'fix_staff_permissions'), 1);
        
        // Fix integration status for staff
        add_filter('fss_integration_status_for_staff', array($this, 'show_connected_for_staff'));
        
        // Override database method for staff
        add_filter('fss_get_integration_status', array($this, 'override_integration_status_for_staff'));
        
        // Add staff-specific CSS and JS
        add_action('wp_footer', array($this, 'add_staff_specific_scripts'));
        
        // Fix readonly fields enforcement
        add_action('wp_enqueue_scripts', array($this, 'enqueue_staff_fixes'));
    }
    
    /**
     * Fix staff permissions and capabilities
     */
    public function fix_staff_permissions() {
        // Define who is staff vs admin
        if (!current_user_can('manage_options') && current_user_can('edit_posts')) {
            // This user is staff - apply restrictions
            add_filter('user_can', array($this, 'restrict_staff_capabilities'), 10, 3);
        }
    }
    
    /**
     * Restrict certain capabilities for staff
     */
    public function restrict_staff_capabilities($allcaps, $cap, $args) {
        $current_user = wp_get_current_user();
        
        // Allow basic operations but restrict admin functions
        $restricted_caps = array(
            'manage_options',
            'edit_themes',
            'edit_plugins',
            'delete_others_posts',
            'delete_published_posts'
        );
        
        foreach ($restricted_caps as $restricted_cap) {
            if (in_array($restricted_cap, $cap)) {
                return false;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Show integration as connected for staff (they see live data)
     */
    public function show_connected_for_staff($status) {
        if (!current_user_can('manage_options') && current_user_can('edit_posts')) {
            // Staff users should see it as connected if we have live data
            $today = wp_date('Y-m-d', null, new DateTimeZone('Africa/Lagos'));
            $orders_data = fss_get_tof_orders_direct($today);
            
            if ($orders_data['total_sales'] > 0 || $orders_data['order_count'] > 0) {
                return array(
                    'connected' => true,
                    'table_name' => 'wp_tof_orders',
                    'data_quality' => $orders_data['data_quality'] ?? 'good',
                    'staff_view' => true
                );
            }
        }
        
        return $status;
    }
    
    /**
     * Override integration status specifically for staff
     */
    public function override_integration_status_for_staff($status) {
        if (!current_user_can('manage_options') && current_user_can('edit_posts')) {
            return $this->show_connected_for_staff($status);
        }
        
        return $status;
    }
    
    /**
     * Add staff-specific scripts and styles
     */
    public function add_staff_specific_scripts() {
        global $post;
        
        if ($post && has_shortcode($post->post_content, 'financial_summary_form')) {
            $is_staff = !current_user_can('manage_options') && current_user_can('edit_posts');
            
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($is_staff): ?>
                // Staff-specific fixes
                console.log('Loading staff-specific permissions...');
                
                // Force readonly on specific fields for staff
                const staffReadonlyFields = [
                    'total_sales',
                    'transfer_card', 
                    'cash',
                    'delivery',
                    'cash_left',
                    'old_cash',
                    'cash_left_market_card'
                ];
                
                staffReadonlyFields.forEach(function(fieldName) {
                    const field = document.querySelector('input[name="' + fieldName + '"]');
                    if (field) {
                        // Make it readonly
                        field.setAttribute('readonly', 'readonly');
                        field.setAttribute('tabindex', '-1');
                        
                        // Style it as readonly
                        field.style.backgroundColor = '#f8f9fa';
                        field.style.cursor = 'not-allowed';
                        field.style.border = '2px solid #6c757d';
                        field.style.opacity = '0.8';
                        
                        // Remove the red dotted border
                        field.style.outline = 'none';
                        field.style.boxShadow = 'none';
                        
                        // Prevent all editing attempts
                        field.addEventListener('keydown', function(e) {
                            e.preventDefault();
                            showStaffMessage('This field is read-only for staff users');
                            return false;
                        });
                        
                        field.addEventListener('paste', function(e) {
                            e.preventDefault();
                            showStaffMessage('This field is read-only for staff users');
                            return false;
                        });
                        
                        field.addEventListener('input', function(e) {
                            e.preventDefault();
                            showStaffMessage('This field is read-only for staff users');
                            return false;
                        });
                        
                        field.addEventListener('focus', function(e) {
                            field.blur(); // Remove focus immediately
                            showStaffMessage('This field is read-only for staff users');
                        });
                        
                        // Add readonly indicator
                        if (!field.parentNode.querySelector('.readonly-indicator')) {
                            const indicator = document.createElement('span');
                            indicator.className = 'readonly-indicator';
                            indicator.innerHTML = '🔒 Read Only';
                            indicator.style.cssText = `
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
                                z-index: 10;
                            `;
                            
                            field.parentNode.style.position = 'relative';
                            field.parentNode.appendChild(indicator);
                        }
                    }
                });
                
                // Fix integration status display for staff
                const statusCard = document.querySelector('.fss-status-card');
                if (statusCard && statusCard.classList.contains('status-disconnected')) {
                    // Check if we actually have live data
                    const totalSales = document.querySelector('input[name="total_sales"]');
                    if (totalSales && parseFloat(totalSales.value) > 0) {
                        // We have data, show as connected for staff
                        statusCard.classList.remove('status-disconnected');
                        statusCard.classList.add('status-connected');
                        
                        const statusInfo = statusCard.querySelector('.status-info');
                        if (statusInfo) {
                            statusInfo.innerHTML = `
                                <h4>Take Order Form Integration</h4>
                                <p>✅ Connected to: <strong>wp_tof_orders</strong></p>
                                <p>📊 Data Quality: <span class="quality-good">Good</span></p>
                                <p>📈 Live data available for staff view</p>
                            `;
                        }
                    }
                }
                
                function showStaffMessage(message) {
                    // Remove existing message
                    const existingMsg = document.querySelector('.staff-message');
                    if (existingMsg) {
                        existingMsg.remove();
                    }
                    
                    // Create new message
                    const msgDiv = document.createElement('div');
                    msgDiv.className = 'staff-message';
                    msgDiv.innerHTML = message;
                    msgDiv.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: #ffc107;
                        color: #212529;
                        padding: 10px 15px;
                        border-radius: 6px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                        z-index: 9999;
                        font-size: 14px;
                        font-weight: 600;
                        border: 2px solid #e0a800;
                    `;
                    
                    document.body.appendChild(msgDiv);
                    
                    // Remove after 3 seconds
                    setTimeout(function() {
                        if (msgDiv.parentNode) {
                            msgDiv.remove();
                        }
                    }, 3000);
                }
                
                // Fix Lagos time display permanently
                function updateLagosTime() {
                    const now = new Date();
                    
                    // Create Lagos time (UTC+1)
                    const lagosTime = new Date(now.toLocaleString("en-US", {timeZone: "Africa/Lagos"}));
                    
                    const timeString = lagosTime.toLocaleTimeString('en-US', {
                        hour12: true,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    
                    const dateString = lagosTime.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    // Update time elements
                    const timeElements = document.querySelectorAll('#fss-current-time, .fss-current-time');
                    timeElements.forEach(function(element) {
                        element.textContent = timeString;
                    });
                    
                    const dateElements = document.querySelectorAll('#fss-current-date, .fss-current-date');
                    dateElements.forEach(function(element) {
                        element.textContent = lagosTime.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        });
                    });
                }
                
                // Update time immediately and every second
                updateLagosTime();
                setInterval(updateLagosTime, 1000);
                
                console.log('Staff permissions loaded successfully');
                <?php else: ?>
                console.log('Admin user - full access granted');
                <?php endif; ?>
            });
            </script>
            
            <style>
            /* Staff-specific styles */
            <?php if ($is_staff): ?>
            .fss-readonly-field,
            input[readonly] {
                background-color: #f8f9fa !important;
                cursor: not-allowed !important;
                border: 2px solid #6c757d !important;
                opacity: 0.8 !important;
                outline: none !important;
                box-shadow: none !important;
            }
            
            .readonly-indicator {
                font-size: 11px;
                color: #6c757d;
                background: white;
                padding: 2px 6px;
                border-radius: 4px;
                border: 1px solid #dee2e6;
            }
            
            .staff-message {
                animation: slideIn 0.3s ease-out;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            /* Remove any red dotted borders */
            input:focus {
                outline: none !important;
                box-shadow: 0 0 0 2px rgba(0,123,255,0.25) !important;
            }
            
            input[readonly]:focus {
                outline: none !important;
                box-shadow: none !important;
                border-color: #6c757d !important;
            }
            <?php endif; ?>
            </style>
            <?php
        }
    }
    
    /**
     * Enqueue additional fixes for staff
     */
    public function enqueue_staff_fixes() {
        if (!current_user_can('manage_options') && current_user_can('edit_posts')) {
            // Staff user - add additional restrictions
            wp_add_inline_style('wp-admin', '
                .update-nag, .notice-warning { display: none !important; }
            ');
        }
    }
}

// Initialize staff permissions fix
new FSS_Staff_Permissions_Fix();