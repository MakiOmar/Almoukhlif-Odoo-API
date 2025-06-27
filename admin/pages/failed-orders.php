<?php
/**
 * Oddo failed to be sent orders
 */

defined( 'ABSPATH' ) || die;

// Admin page for failed Odoo orders (table, filters, bulk actions)
if (!function_exists('display_odoo_failed_orders_page')) {
function display_odoo_failed_orders_page() {
	// Process form submission.
	if (isset($_POST['bulk_send_odoo']) && !empty($_POST['order_ids'])) {
		send_orders_batch_to_odoo($_POST['order_ids'], false);
		// Clear admin bar cache after bulk operation
		if (class_exists('Odoo_Admin')) {
			Odoo_Admin::clear_cache();
		}
		echo '<div class="updated"><p>' . esc_html__('Selected orders have been sent to Odoo.', 'text-domain') . '</p></div>';
	}
	$per_page = 50;
	$paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$offset   = ($paged - 1) * $per_page;
	$always_excluded_statuses = array('wc-user-changed','wc-refunded','wc-cancel-request','wc-cancelled','wc-was-canceled','wc-completed','wc-custom-failed','wc-checkout-draft','wc-failed');
	$statuses = wc_get_order_statuses();
	foreach ($always_excluded_statuses as $status) unset($statuses[$status]);
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => array_keys($statuses),
		'meta_query'     => array([
			'key'     => 'oodo-status',
			'value'   => 'failed',
			'compare' => '=',
		]),
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'offset'         => $offset,
	);
	$orders = get_posts($args);
	$total_orders = count(get_posts(array_merge($args, array('posts_per_page' => -1, 'paged' => 1, 'offset' => 0))));
	$total_pages  = ceil($total_orders / $per_page);
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Failed Odoo Orders', 'text-domain'); ?></h1>
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
							$order      = wc_get_order($order_post->ID);
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
							<td colspan="6">
								<?php esc_html_e('No orders with failed Odoo status found.', 'text-domain'); ?>
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
		<?php
		echo paginate_links(array(
			'base'      => add_query_arg('paged', '%#%'),
			'format'    => '',
			'current'   => $paged,
			'total'     => $total_pages,
			'prev_text' => __('« Previous', 'text-domain'),
			'next_text' => __('Next »', 'text-domain'),
		));
		?>
	</div>
	<script>
		document.getElementById('select_all').addEventListener('click', function() {
			const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
			checkboxes.forEach(checkbox => checkbox.checked = this.checked);
		});
	</script>
	<?php
}
}