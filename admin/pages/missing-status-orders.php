<?php
// Admin page for orders missing Odoo status (table, pagination, filters)
if (!function_exists('display_odoo_missing_status_orders_page')) {
function display_odoo_missing_status_orders_page() {
    // Process bulk action
    Odoo_Admin_Filters::process_bulk_action('bulk_send_odoo', $_POST['order_ids'] ?? array(), false);
    
    // Get filter parameters
    $filters = Odoo_Admin_Filters::get_filter_params();
    $orders_per_page = 50;
    
    // Get available statuses
    $available_statuses = array('wc-processing', 'wc-on-hold', 'wc-custom-status');
    
    // Build base query args
    $base_args = array(
        'post_type' => 'shop_order',
        'post_status' => $available_statuses,
        'orderby' => 'date',
        'order' => 'DESC',
        'posts_per_page' => $orders_per_page,
        'paged' => $filters['paged'],
        'meta_query' => array([
            'key' => 'oodo-status',
            'compare' => 'NOT EXISTS',
        ]),
    );
    
    // Default date filter (after 2025-02-17)
    $default_date_filter = array([
        'after' => '2025-02-17',
        'inclusive' => true,
    ]);
    
    // Apply filters
    $args = Odoo_Admin_Filters::build_query_args($base_args, $filters, $default_date_filter);
    
    $orders_query = new WP_Query($args);
    $orders = $orders_query->posts;
    $total_pages = $orders_query->max_num_pages;
    $total_orders = $orders_query->found_posts;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Orders Without Odoo Status', 'text-domain'); ?></h1>
        
        <!-- Filters Form -->
        <?php 
        $statuses = wc_get_order_statuses();
        $filter_statuses = array();
        foreach ($available_statuses as $status) {
            $filter_statuses[$status] = isset($statuses[$status]) ? $statuses[$status] : ucfirst(str_replace('wc-', '', $status));
        }
        Odoo_Admin_Filters::render_filters_form($filters, $filter_statuses, 'odoo-missing-status-orders'); 
        ?>
        
        <!-- Results Summary -->
        <?php Odoo_Admin_Filters::render_results_summary($total_orders); ?>
        
        <form method="post">
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select_all" /></th>
                        <th><?php esc_html_e('Order ID', 'text-domain'); ?></th>
                        <th><?php esc_html_e('Customer Name', 'text-domain'); ?></th>
                        <th><?php esc_html_e('Total', 'text-domain'); ?></th>
                        <th><?php esc_html_e('Status', 'text-domain'); ?></th>
                        <th><?php esc_html_e('Date', 'text-domain'); ?></th>
                        <th><?php esc_html_e('Actions', 'text-domain'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)) : ?>
                        <?php foreach ($orders as $order_post) : ?>
                            <?php
                            $order = wc_get_order($order_post->ID);
                            $order_name = $order->get_formatted_billing_full_name();
                            $status_key = 'wc-' . $order->get_status();
                            $status_label = isset($statuses[$status_key]) ? $statuses[$status_key] : ucfirst($order->get_status());
                            ?>
                            <tr>
                                <td><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->get_id()); ?>" /></td>
                                <td><?php echo esc_html($order->get_id()); ?></td>
                                <td><?php echo esc_html($order_name); ?></td>
                                <td><?php echo wc_price($order->get_total()); ?></td>
                                <td><?php echo esc_html($status_label); ?></td>
                                <td><?php echo esc_html($order->get_date_created()->date('Y-m-d H:i:s')); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')); ?>">
                                        <?php esc_html_e('View Order', 'text-domain'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7">
                                <?php esc_html_e('No orders without Odoo status found.', 'text-domain'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!empty($orders)) : ?>
                <p>
                    <input type="submit" name="bulk_send_odoo" class="button-primary" value="<?php esc_attr_e('Send Selected to Odoo', 'text-domain'); ?>" />
                </p>
            <?php endif; ?>
        </form>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo; Previous', 'text-domain'),
                    'next_text' => __('Next &raquo;', 'text-domain'),
                    'total' => $total_pages,
                    'current' => $filters['paged'],
                ));
                ?>
            </div>
        </div>
    </div>
    <?php Odoo_Admin_Filters::render_select_all_js(); ?>
    <?php
}
} 