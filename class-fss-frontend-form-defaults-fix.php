<?php
/**
 * Remove default values from expense fields
 */

class FSS_Frontend_Form_Defaults_Fix {
    
    public function __construct() {
        add_filter('fss_field_default_value', array($this, 'remove_expense_defaults'), 10, 2);
        add_action('wp_footer', array($this, 'clear_expense_defaults_js'));
    }
    
    /**
     * Remove default values for expense fields
     */
    public function remove_expense_defaults($default_value, $field_name) {
        $fields_to_clear = array(
            'expense',
            'expense_remark'
        );
        
        if (in_array($field_name, $fields_to_clear)) {
            return '';
        }
        
        return $default_value;
    }
    
    /**
     * Clear expense defaults with JavaScript
     */
    public function clear_expense_defaults_js() {
        global $post;
        
        if ($post && has_shortcode($post->post_content, 'financial_summary_form')) {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Clear default values from expense fields
                const expenseAmount = document.querySelector('input[name="expense"]');
                const expenseRemark = document.querySelector('input[name="expense_remark"], textarea[name="expense_remark"]');
                
                if (expenseAmount) {
                    // Remove default value and placeholder
                    if (expenseAmount.value === '500.00' || expenseAmount.value === '0.00') {
                        expenseAmount.value = '';
                    }
                    expenseAmount.placeholder = 'Enter expense amount';
                }
                
                if (expenseRemark) {
                    // Remove default text
                    if (expenseRemark.value === 'Default import expense' || 
                        expenseRemark.value === 'Imported from Take Order Form' ||
                        expenseRemark.value.includes('Default')) {
                        expenseRemark.value = '';
                    }
                    expenseRemark.placeholder = 'Enter expense description';
                }
                
                // Clear extras remark if it has default text
                const extrasRemark = document.querySelector('input[name="extras_remark"], textarea[name="extras_remark"]');
                if (extrasRemark) {
                    if (extrasRemark.value === 'Imported from Take Order Form' ||
                        extrasRemark.value.includes('Imported')) {
                        extrasRemark.value = '';
                    }
                    extrasRemark.placeholder = 'Enter extras description';
                }
            });
            </script>
            <?php
        }
    }
}

// Initialize defaults fix
new FSS_Frontend_Form_Defaults_Fix();