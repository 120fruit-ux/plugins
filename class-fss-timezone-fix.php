<?php
/**
 * Fix Lagos Timezone Display
 */

class FSS_Timezone_Fix {
    
    public function __construct() {
        // Set WordPress timezone if not already set
        add_action('init', array($this, 'ensure_lagos_timezone'));
        
        // Override time functions to use Lagos timezone
        add_filter('wp_date', array($this, 'use_lagos_time'), 10, 4);
        
        // Fix time display in frontend
        add_action('wp_footer', array($this, 'fix_frontend_time_display'));
    }
    
    /**
     * Ensure Lagos timezone is set
     */
    public function ensure_lagos_timezone() {
        // Set default timezone for the plugin
        if (get_option('timezone_string') !== 'Africa/Lagos') {
            // Don't override WordPress setting, just ensure our plugin uses Lagos time
            date_default_timezone_set('Africa/Lagos');
        }
    }
    
    /**
     * Use Lagos time for wp_date calls
     */
    public function use_lagos_time($the_date, $format, $timestamp, $timezone) {
        // Only override if no specific timezone was requested and we're in FSS context
        if (is_null($timezone) && $this->is_fss_context()) {
            $lagos_timezone = new DateTimeZone('Africa/Lagos');
            $datetime = new DateTime('@' . $timestamp);
            $datetime->setTimezone($lagos_timezone);
            return $datetime->format($format);
        }
        
        return $the_date;
    }
    
    /**
     * Check if we're in FSS context
     */
    private function is_fss_context() {
        // Check if we're on FSS pages or handling FSS requests
        $is_fss_page = (
            strpos($_SERVER['REQUEST_URI'] ?? '', 'financial-summary') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', 'fss_') !== false ||
            isset($_POST['action']) && strpos($_POST['action'], 'fss_') === 0
        );
        
        return $is_fss_page;
    }
    
    /**
     * Fix frontend time display with JavaScript
     */
    public function fix_frontend_time_display() {
        global $post;
        
        if ($post && has_shortcode($post->post_content, 'financial_summary_form')) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Fix time display to show Lagos time
                function updateLagosTime() {
                    const now = new Date();
                    
                    // Convert to Lagos time (UTC+1)
                    const lagosTime = new Date(now.getTime() + (60 * 60 * 1000)); // Add 1 hour to UTC
                    
                    // Format time as HH:MM:SS AM/PM
                    const timeString = lagosTime.toLocaleTimeString('en-US', {
                        hour12: true,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        timeZone: 'Africa/Lagos'
                    });
                    
                    // Update time display elements
                    const timeElements = document.querySelectorAll('#fss-current-time, .fss-current-time, #last-refresh-time');
                    timeElements.forEach(function(element) {
                        if (element.id === 'last-refresh-time') {
                            // For refresh time, just show HH:MM:SS
                            element.textContent = lagosTime.toLocaleTimeString('en-US', {
                                hour12: false,
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit',
                                timeZone: 'Africa/Lagos'
                            });
                        } else {
                            element.textContent = timeString;
                        }
                    });
                }
                
                // Update time immediately and then every second
                updateLagosTime();
                setInterval(updateLagosTime, 1000);
                
                // Also fix any timestamps in AJAX responses
                const originalSend = XMLHttpRequest.prototype.send;
                XMLHttpRequest.prototype.send = function(data) {
                    this.addEventListener('load', function() {
                        if (this.responseURL && this.responseURL.includes('fss_')) {
                            setTimeout(updateLagosTime, 100);
                        }
                    });
                    originalSend.call(this, data);
                };
            });
            </script>
            <?php
        }
    }
}

// Initialize timezone fix
new FSS_Timezone_Fix();

/**
 * Helper function to get Lagos time
 */
function fss_lagos_time($format = 'Y-m-d H:i:s') {
    $lagos_timezone = new DateTimeZone('Africa/Lagos');
    $datetime = new DateTime('now', $lagos_timezone);
    return $datetime->format($format);
}

/**
 * Helper function to get Lagos date
 */
function fss_lagos_date($format = 'Y-m-d') {
    return fss_lagos_time($format);
}