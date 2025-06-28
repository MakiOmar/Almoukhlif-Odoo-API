<?php
/**
 * Odoo Admin Filters Class
 * 
 * Handles filtration functionality for admin pages
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Admin_Filters {
    
    /**
     * Get filter parameters from GET request
     */
    public static function get_filter_params() {
        return array(
            'paged' => isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1,
            'from_date' => isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : '',
            'to_date' => isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : '',
            'order_status' => isset($_GET['order_status']) ? sanitize_text_field($_GET['order_status']) : '',
            'customer_search' => isset($_GET['customer_search']) ? sanitize_text_field($_GET['customer_search']) : '',
            'order_id_search' => isset($_GET['order_id_search']) ? sanitize_text_field($_GET['order_id_search']) : '',
        );
    }
    
    /**
     * Build query arguments with filters
     */
    public static function build_query_args($base_args, $filters, $default_date_filter = null) {
        $args = $base_args;
        
        // Add date filters
        if (!empty($filters['from_date']) || !empty($filters['to_date'])) {
            $args['date_query'] = array();
            if (!empty($filters['from_date'])) {
                $args['date_query']['after'] = $filters['from_date'];
            }
            if (!empty($filters['to_date'])) {
                $args['date_query']['before'] = $filters['to_date'];
            }
            $args['date_query']['inclusive'] = true;
        } elseif ($default_date_filter) {
            $args['date_query'] = $default_date_filter;
        }
        
        // Add order status filter
        if (!empty($filters['order_status'])) {
            $args['post_status'] = array($filters['order_status']);
        }
        
        // Add customer search
        if (!empty($filters['customer_search'])) {
            $customer_meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => '_billing_first_name',
                    'value' => $filters['customer_search'],
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_billing_last_name',
                    'value' => $filters['customer_search'],
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_billing_email',
                    'value' => $filters['customer_search'],
                    'compare' => 'LIKE'
                )
            );
            
            if (isset($args['meta_query'])) {
                $args['meta_query'][] = $customer_meta_query;
            } else {
                $args['meta_query'] = array($customer_meta_query);
            }
        }
        
        // Add order ID search
        if (!empty($filters['order_id_search'])) {
            $args['post__in'] = array(intval($filters['order_id_search']));
        }
        
        return $args;
    }
    
    /**
     * Get available order statuses for filtering
     */
    public static function get_available_statuses($exclude_statuses = array()) {
        $statuses = wc_get_order_statuses();
        
        if (!empty($exclude_statuses)) {
            foreach ($exclude_statuses as $status) {
                unset($statuses[$status]);
            }
        }
        
        return $statuses;
    }
    
    /**
     * Render filters form
     */
    public static function render_filters_form($filters, $available_statuses, $page_slug) {
        ?>
        <form method="GET" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            
            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Date Range:', 'text-domain'); ?></label>
                    <div style="display: flex; gap: 10px;">
                        <input type="date" name="from_date" value="<?php echo esc_attr($filters['from_date']); ?>" placeholder="<?php esc_attr_e('From Date', 'text-domain'); ?>">
                        <input type="date" name="to_date" value="<?php echo esc_attr($filters['to_date']); ?>" placeholder="<?php esc_attr_e('To Date', 'text-domain'); ?>">
                    </div>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Order Status:', 'text-domain'); ?></label>
                    <select name="order_status" style="min-width: 150px;">
                        <option value=""><?php esc_html_e('All Statuses', 'text-domain'); ?></option>
                        <?php foreach ($available_statuses as $status_key => $status_label) : ?>
                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected($filters['order_status'], $status_key); ?>>
                                <?php echo esc_html($status_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Customer Search:', 'text-domain'); ?></label>
                    <input type="text" name="customer_search" value="<?php echo esc_attr($filters['customer_search']); ?>" placeholder="<?php esc_attr_e('Name or Email', 'text-domain'); ?>" style="min-width: 200px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Order ID:', 'text-domain'); ?></label>
                    <input type="number" name="order_id_search" value="<?php echo esc_attr($filters['order_id_search']); ?>" placeholder="<?php esc_attr_e('Order ID', 'text-domain'); ?>" style="min-width: 100px;">
                </div>
                
                <div>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply Filters', 'text-domain'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page_slug)); ?>" class="button"><?php esc_html_e('Clear Filters', 'text-domain'); ?></a>
                </div>
            </div>
        </form>
        <?php
    }
    
    /**
     * Render results summary
     */
    public static function render_results_summary($total_orders) {
        ?>
        <div style="margin-bottom: 15px;">
            <p><strong><?php esc_html_e('Total Orders Found:', 'text-domain'); ?></strong> <?php echo esc_html($total_orders); ?></p>
        </div>
        <?php
    }
    
    /**
     * Process bulk action
     */
    public static function process_bulk_action($action_name, $order_ids, $update = false) {
        if (isset($_POST[$action_name]) && !empty($_POST['order_ids'])) {
            send_orders_batch_to_odoo($_POST['order_ids'], $update);
            
            // Clear admin bar cache after bulk operation
            if (class_exists('Odoo_Admin')) {
                Odoo_Admin::clear_cache();
            }
            
            echo '<div class="updated"><p>' . esc_html__('Selected orders have been sent to Odoo.', 'text-domain') . '</p></div>';
            return true;
        }
        return false;
    }
    
    /**
     * Render select all JavaScript
     */
    public static function render_select_all_js() {
        ?>
        <script>
            document.getElementById('select_all').addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        </script>
        <?php
    }
} 