<?php
/**
 * Permanent Lagos Timezone Fix
 */

class FSS_Timezone_Permanent_Fix {
    
    public function __construct() {
        // Hook early to set timezone
        add_action('init', array($this, 'set_lagos_timezone'), 1);
        
        // Fix AJAX responses
        add_action('wp_ajax_fss_get_live_orders', array($this, 'fix_ajax_timezone'), 1);
        add_action('wp_ajax_nopriv_fss_get_live_orders', array($this, 'fix_ajax_timezone'), 1);
        
        // Fix frontend display
        add_action('wp_footer', array($this, 'fix_frontend_timezone_permanently'));
        
        // Override WordPress current_time function for FSS
        add_filter('current_time', array($this, 'override_current_time'), 10, 2);
    }
    
    /**
     * Set Lagos timezone globally for FSS
     */
    public function set_lagos_timezone() {
        if ($this->is_fss_context()) {
            date_default_timezone_set('Africa/Lagos');
        }
    }
    
    /**
     * Fix AJAX timezone
     */
    public function fix_ajax_timezone() {
        date_default_timezone_set('Africa/Lagos');
    }
    
    /**
     * Override current_time for FSS pages
     */
    public function override_current_time($time, $type) {
        if ($this->is_fss_context()) {
            $lagos_timezone = new DateTimeZone('Africa/Lagos');
            $datetime = new DateTime('now', $lagos_timezone);
            
            if ($type === 'timestamp') {
                return $datetime->getTimestamp();
            }
            
            return $datetime->format('Y-m-d H:i:s');
        }
        
        return $time;
    }
    
    /**
     * Check if we're in FSS context
     */
    private function is_fss_context() {
        return (
            strpos($_SERVER['REQUEST_URI'] ?? '', 'financial-summary') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', 'fss_') !== false ||
            (isset($_POST['action']) && strpos($_POST['action'], 'fss_') === 0) ||
            (isset($_GET['action']) && strpos($_GET['action'], 'fss_') === 0)
        );
    }
    
    /**
     * Permanent frontend timezone fix
     */
    public function fix_frontend_timezone_permanently() {
        global $post;
        
        if ($post && has_shortcode($post->post_content, 'financial_summary_form')) {
            ?>
            <script>
            // Override JavaScript Date to always show Lagos time
            (function() {
                const originalDate = Date;
                const lagosOffset = 1 * 60; // Lagos is UTC+1 (60 minutes ahead)
                
                // Create Lagos Date constructor
                window.LagosDate = function(...args) {
                    if (args.length === 0) {
                        // Current time in Lagos
                        const utcTime = new originalDate();
                        return new originalDate(utcTime.getTime() + (lagosOffset * 60000));
                    }
                    return new originalDate(...args);
                };
                
                // Copy all Date methods
                Object.setPrototypeOf(LagosDate.prototype, originalDate.prototype);
                Object.setPrototypeOf(LagosDate, originalDate);
                
                // Function to get Lagos time
                function getLagosTime() {
                    const now = new originalDate();
                    const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
                    const lagos = new originalDate(utc + (1 * 3600000)); // UTC+1
                    return lagos;
                }
                
                // Update time display function
                function updateTimeDisplay() {
                    const lagosTime = getLagosTime();
                    
                    const timeString = lagosTime.toLocaleTimeString('en-US', {
                        hour12: true,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    
                    const timeElements = document.querySelectorAll('#fss-current-time, .fss-current-time, .current-time');
                    timeElements.forEach(function(element) {
                        if (element) {
                            element.textContent = timeString;
                        }
                    });
                    
                    // Also update any refresh timestamps
                    const refreshElements = document.querySelectorAll('#last-refresh-time, .last-refresh-time');
                    refreshElements.forEach(function(element) {
                        if (element) {
                            element.textContent = lagosTime.toLocaleTimeString('en-US', {
                                hour12: false,
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit'
                            });
                        }
                    });
                }
                
                // Update immediately and every second
                updateTimeDisplay();
                setInterval(updateTimeDisplay, 1000);
                
                // Override AJAX success handlers to maintain Lagos time
                const originalAjax = jQuery.ajax;
                jQuery.ajax = function(options) {
                    const originalSuccess = options.success;
                    options.success = function(data, textStatus, jqXHR) {
                        if (originalSuccess) {
                            originalSuccess.call(this, data, textStatus, jqXHR);
                        }
                        // Update time after AJAX
                        setTimeout(updateTimeDisplay, 100);
                    };
                    return originalAjax.call(this, options);
                };
                
                // Prevent any external time updates
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' || mutation.type === 'characterData') {
                            setTimeout(updateTimeDisplay, 50);
                        }
                    });
                });
                
                // Observe time elements for changes
                document.addEventListener('DOMContentLoaded', function() {
                    const timeElements = document.querySelectorAll('#fss-current-time, .fss-current-time, .current-time');
                    timeElements.forEach(function(element) {
                        observer.observe(element, {
                            childList: true,
                            characterData: true,
                            subtree: true
                        });
                    });
                });
            })();
            </script>
            <?php
        }
    }
}

// Initialize permanent timezone fix
new FSS_Timezone_Permanent_Fix();

/**
 * Helper functions for Lagos time
 */
function fss_lagos_current_time($format = 'Y-m-d H:i:s') {
    $lagos_timezone = new DateTimeZone('Africa/Lagos');
    $datetime = new DateTime('now', $lagos_timezone);
    return $datetime->format($format);
}

function fss_lagos_date($format = 'Y-m-d') {
    return fss_lagos_current_time($format);
}

function fss_lagos_time($format = 'H:i:s') {
    return fss_lagos_current_time($format);
}