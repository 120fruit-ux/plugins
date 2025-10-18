<?php
/**
 * Real-time synchronization with Take Order Form Working Plugin
 */

class FSS_Order_Sync {
    
    public function __construct() {
        // Hook into various order submission points
        add_action('wp_ajax_submit_order', array($this, 'sync_on_order_submit'));
        add_action('wp_ajax_nopriv_submit_order', array($this, 'sync_on_order_submit'));
        
        // Hook into form submissions
        add_action('gform_after_submission', array($this, 'sync_gravity_forms'), 10, 2);
        add_action('wpcf7_mail_sent', array($this, 'sync_contact_form_7'));
        
        // Database trigger simulation
        add_action('wp_insert_post', array($this, 'sync_on_post_insert'), 10, 2);
        
        // Scheduled sync for backup
        add_action('fss_hourly_order_sync', array($this, 'scheduled_sync'));
        
        if (!wp_next_scheduled('fss_hourly_order_sync')) {
            wp_schedule_event(time(), 'hourly', 'fss_hourly_order_sync');
        }
    }
    
    /**
     * Sync when order is submitted via AJAX
     */
    public function sync_on_order_submit() {
        $this->trigger_sync('ajax_order');
    }
    
    /**
     * Sync when Gravity Forms order is submitted
     */
    public function sync_gravity_forms($entry, $form) {
        if (strpos(strtolower($form['title']), 'order') !== false) {
            $this->trigger_sync('gravity_forms');
        }
    }
    
    /**
     * Sync when Contact Form 7 order is submitted
     */
    public function sync_contact_form_7($contact_form) {
        if (strpos(strtolower($contact_form->title()), 'order') !== false) {
            $this->trigger_sync('contact_form_7');
        }
    }
    
    /**
     * Sync when post is inserted (catch-all)
     */
    public function sync_on_post_insert($post_id, $post) {
        if ($post->post_type === 'take_order_submission' || 
            strpos(strtolower($post->post_type), 'order') !== false ||
            strpos(strtolower($post->post_type), 'submission') !== false) {
            
            $this->trigger_sync('post_insert');
        }
    }
    
    /**
     * Scheduled sync (backup method)
     */
    public function scheduled_sync() {
        $this->trigger_sync('scheduled');
    }
    
    /**
     * Trigger the synchronization
     */
    private function trigger_sync($trigger_type = 'manual') {
        $today = wp_date('Y-m-d');
        
        // Clear cached data
        wp_cache_delete('fss_orders_' . $today);
        
        // Get fresh orders data
        $orders_data = FSS_Database::get_orders_data_for_date($today);
        
        // Update existing summary if it exists
        $existing_summary = FSS_Database::get_daily_summary($today);
        
        if ($existing_summary && $orders_data['total_sales'] > 0) {
            global $wpdb;
            
            // Calculate new cash left
            $cash_in = $orders_data['cash'] + $existing_summary->old_cash + $existing_summary->extras + $existing_summary->cash_left_market_card;
            $new_cash_left = $cash_in - $existing_summary->expense;
            
            // Update summary
            $wpdb->update(
                $wpdb->prefix . 'fss_daily_summaries',
                array(
                    'total_sales' => $orders_data['total_sales'],
                    'transfer_card' => $orders_data['transfer_card'],
                    'cash' => $orders_data['cash'],
                    'delivery' => $orders_data['delivery'],
                    'order_count' => $orders_data['order_count'],
                    'data_quality' => $orders_data['data_quality'],
                    'cash_left' => $new_cash_left,
                    'updated_at' => current_time('mysql')
                ),
                array('date' => $today)
            );
            
            // Log the sync
            error_log("FSS: Auto-synced orders for $today via $trigger_type - Orders: {$orders_data['order_count']}, Sales: {$orders_data['total_sales']}");
        }
        
        // Trigger custom action for other integrations
        do_action('fss_orders_synced', $today, $orders_data, $trigger_type);
    }
}