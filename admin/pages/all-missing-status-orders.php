<?php
// Admin page for all orders missing Odoo status (table, filters, bulk actions)
if (!function_exists('display_all_odoo_missing_status_orders_page')) {
function display_all_odoo_missing_status_orders_page() {
    $orders_per_page = 50;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $excluded_statuses = isset($_GET['excluded_statuses']) ? (array)$_GET['excluded_statuses'] : array();
    $from_date = isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : '';
    $to_date   = isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : '';
    $always_excluded_statuses = array('wc-user-changed','wc-refunded','wc-cancel-request','wc-cancelled','wc-was-canceled','wc-completed','wc-custom-failed','wc-checkout-draft','wc-failed');
    $statuses = wc_get_order_statuses();
    foreach ($always_excluded_statuses as $status) unset($statuses[$status]);
    $date_query = array();
    if (!empty($from_date)) {
        $date_query['after'] = $from_date;
    } else {
        $date_query['after'] = '2025-02-17';
    }
    if (!empty($to_date)) {
        $date_query['before'] = $to_date;
    }
    $date_query['inclusive'] = true;
    if (isset($_POST['bulk_send_odoo']) && !empty($_POST['order_ids'])) {
        send_orders_batch_to_odoo($_POST['order_ids']);
        // Clear admin bar cache after bulk operation
        if (class_exists('Odoo_Admin')) {
            Odoo_Admin::clear_cache();
        }
        echo '<div class="updated"><p>' . esc_html__('Selected orders have been sent to Odoo.', 'text-domain') . '</p></div>';
    }
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => array_diff(array_keys($statuses), $excluded_statuses),
        'orderby'        => 'date',
        'order'          => 'DESC',
        'posts_per_page' => $orders_per_page,
        'paged'          => $paged,
        'date_query'     => array($date_query),
        'meta_query'     => array([
            'key'     => 'oodo-status',
            'compare' => 'NOT EXISTS',
        ]),
    );
    $orders_query = new WP_Query($args);
    $orders = $orders_query->posts;
    $total_pages = $orders_query->max_num_pages;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Orders Without Odoo Status', 'text-domain'); ?></h1>
        <form method="GET" style="direction:rtl">
            <div style="margin-bottom: 15px;">
                <strong><?php esc_html_e('Filter by Date Range:', 'text-domain'); ?></strong><br>
                <label>
                    <?php esc_html_e('From:', 'text-domain'); ?>
                    <input type="date" name="from_date" value="<?php echo esc_attr($from_date); ?>">
                </label>
                <label>
                    <?php esc_html_e('To:', 'text-domain'); ?>
                    <input type="date" name="to_date" value="<?php echo esc_attr($to_date); ?>">
                </label>
            </div>
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <div style="margin-bottom: 15px;">
                <strong><?php esc_html_e('Exclude Order Statuses:', 'text-domain'); ?></strong><br>
                <label style="margin-right: 10px;display:inline-flex;width:15%;align-items:center">
                    <input type="checkbox" id="check_all_statuses">&nbsp;
                    <strong><?php esc_html_e('Check All', 'text-domain'); ?></strong>
                </label>
                <?php foreach ($statuses as $status_key => $status_label) : ?>
                    <label style="margin-right: 10px;display:inline-flex;width:15%;align-items:center">
                        <input type="checkbox" class="status-checkbox" name="excluded_statuses[]" value="<?php echo esc_attr($status_key); ?>" <?php checked(in_array($status_key, $excluded_statuses)); ?>>&nbsp;
                        <?php echo esc_html($status_label); ?>
                    </label>
                <?php endforeach; ?>
                <button type="submit" class="button button-primary"><?php esc_html_e('Apply Filter', 'text-domain'); ?></button>
            </div>
        </form>
        <form method="POST">
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
                            $status_key = 'wc-' . $order->get_status();
                            $status_label = isset($statuses[$status_key]) ? $statuses[$status_key] : ucfirst($order->get_status());
                            ?>
                            <tr>
                                <td><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->get_id()); ?>" /></td>
                                <td><?php echo esc_html($order->get_id()); ?></td>
                                <td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
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
        <script>
            document.getElementById('select_all').addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        </script>
    </div>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            echo paginate_links(array(
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'prev_text' => __('&laquo; Previous', 'text-domain'),
                'next_text' => __('Next &raquo;', 'text-domain'),
                'total'     => $total_pages,
                'current'   => $paged,
            ));
            ?>
        </div>
    </div>
    <?php
}
} 