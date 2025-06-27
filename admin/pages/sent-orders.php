<?php
// Admin page for sent Odoo orders (table, pagination)
if (!function_exists('display_odoo_orders_with_meta_page')) {
function display_odoo_orders_with_meta_page() {
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20; // Number of orders per page
    $offset = ($paged - 1) * $per_page;
    $args = array(
        'post_type'   => 'shop_order',
        'post_status' => 'any',
        'meta_query'  => array(
            array(
                'key'     => 'oodo-status',
                'value'   => 'success',
                'compare' => '=',
            ),
        ),
        'posts_per_page' => $per_page,
        'offset'         => $offset,
    );
    $orders = get_posts($args);
    $total_orders = count(get_posts(array_merge($args, array('posts_per_page' => -1, 'offset' => 0))));
    $total_pages = ceil($total_orders / $per_page);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Orders with Odoo Meta Key', 'text-domain'); ?></h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select_all_meta" /></th>
                    <th><?php esc_html_e('Order ID', 'text-domain'); ?></th>
                    <th><?php esc_html_e('Customer Name', 'text-domain'); ?></th>
                    <th><?php esc_html_e('Total', 'text-domain'); ?></th>
                    <th><?php esc_html_e('Odoo Meta Value', 'text-domain'); ?></th>
                    <th><?php esc_html_e('Actions', 'text-domain'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)) : ?>
                    <?php foreach ($orders as $order_post) : ?>
                        <?php
                        $order      = wc_get_order($order_post->ID);
                        $order_id   = $order->get_id();
                        $order_name = $order->get_formatted_billing_full_name();
                        $odoo_value = get_post_meta($order_id, 'odoo_order', true);
                        ?>
                        <tr>
                            <td><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order_id); ?>" /></td>
                            <td><?php echo esc_html($order_id); ?></td>
                            <td><?php echo esc_html($order_name); ?></td>
                            <td><?php echo wc_price($order->get_total()); ?></td>
                            <td><?php echo esc_html($odoo_value); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')); ?>">
                                    <?php esc_html_e('View Order', 'text-domain'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">
                            <?php esc_html_e('No orders with Odoo meta key found.', 'text-domain'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php if ($total_pages > 1) : ?>
                    <?php $base_url = admin_url('admin.php?page=odoo-orders-with-meta'); ?>
                    <span class="pagination-links">
                        <?php if ($paged > 1) : ?>
                            <a class="prev-page" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $base_url)); ?>">&laquo; Previous</a>
                        <?php endif; ?>
                        <span class="current-page">Page <?php echo esc_html($paged); ?> of <?php echo esc_html($total_pages); ?></span>
                        <?php if ($paged < $total_pages) : ?>
                            <a class="next-page" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $base_url)); ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('select_all_meta').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    </script>
    <?php
}
}
