<?php
/**
 * Make certain fields read-only for staff users
 */

class FSS_Frontend_ReadOnly_Fix {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_readonly_scripts'));
        add_filter('fss_field_attributes', array($this, 'add_readonly_attributes'), 10, 2);
    }
    
    /**
     * Check if current user is staff (not admin)
     */
    private function is_staff_user() {
        return current_user_can('edit_posts') && !current_user_can('manage_options');
    }
    
    /**
     * Add readonly attributes to specific fields
     */
    public function add_readonly_attributes($attributes, $field_name) {
        if (!$this->is_staff_user()) {
            return $attributes; // Admins can edit everything
        }
        
        $readonly_fields = array(
            'total_sales',
            'transfer_card', 
            'cash',
            'delivery',
            'cash_left',
            'old_cash',
            'cash_left_market_card'
        );
        
        if (in_array($field_name, $readonly_fields)) {
            $attributes .= ' readonly="readonly" class="fss-readonly-field"';
        }
        
        return $attributes;
    }
    
    /**
     * Enqueue scripts to enforce readonly behavior
     */
    public function enqueue_readonly_scripts() {
        global $post;
        
        if ($post && has_shortcode($post->post_content, 'financial_summary_form')) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Make fields readonly for staff users
                <?php if ($this->is_staff_user()): ?>
                const readonlyFields = [
                    'input[name="total_sales"]',
                    'input[name="transfer_card"]', 
                    'input[name="cash"]',
                    'input[name="delivery"]',
                    'input[name="cash_left"]',
                    'input[name="old_cash"]',
                    'input[name="cash_left_market_card"]'
                ];
                
                readonlyFields.forEach(function(selector) {
                    const field = document.querySelector(selector);
                    if (field) {
                        field.setAttribute('readonly', 'readonly');
                        field.classList.add('fss-readonly-field');
                        field.style.backgroundColor = '#f8f9fa';
                        field.style.cursor = 'not-allowed';
                        field.style.opacity = '0.7';
                        
                        // Prevent editing attempts
                        field.addEventListener('keydown', function(e) {
                            e.preventDefault();
                            return false;
                        });
                        
                        field.addEventListener('paste', function(e) {
                            e.preventDefault();
                            return false;
                        });
                        
                        field.addEventListener('input', function(e) {
                            e.preventDefault();
                            return false;
                        });
                    }
                });
                
                // Add visual indicator
                const style = document.createElement('style');
                style.textContent = `
                    .fss-readonly-field::before {
                        content: "🔒";
                        position: absolute;
                        right: 10px;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #6c757d;
                        pointer-events: none;
                    }
                    .fss-readonly-field {
                        position: relative;
                        background-color: #f8f9fa !important;
                        cursor: not-allowed !important;
                    }
                `;
                document.head.appendChild(style);
                <?php endif; ?>
            });
            </script>
            
            <style>
            .fss-readonly-field {
                background-color: #f8f9fa !important;
                cursor: not-allowed !important;
                opacity: 0.7;
                border: 1px solid #e9ecef !important;
            }
            
            .fss-readonly-field::after {
                content: "🔒 Read Only";
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 10px;
                color: #6c757d;
                pointer-events: none;
            }
            
            .field-container {
                position: relative;
            }
            </style>
            <?php
        }
    }
}

// Initialize readonly fix
new FSS_Frontend_ReadOnly_Fix();