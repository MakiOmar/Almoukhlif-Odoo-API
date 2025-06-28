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
	
	// Get filter parameters
	$per_page = 50;
	$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$from_date = isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : '';
	$to_date = isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : '';
	$order_status = isset($_GET['order_status']) ? sanitize_text_field($_GET['order_status']) : '';
	$customer_search = isset($_GET['customer_search']) ? sanitize_text_field($_GET['customer_search']) : '';
	$order_id_search = isset($_GET['order_id_search']) ? sanitize_text_field($_GET['order_id_search']) : '';
	
	$always_excluded_statuses = array('wc-user-changed','wc-refunded','wc-cancel-request','wc-cancelled','wc-was-canceled','wc-completed','wc-custom-failed','wc-checkout-draft','wc-failed');
	$statuses = wc_get_order_statuses();
	foreach ($always_excluded_statuses as $status) unset($statuses[$status]);
	
	// Build query args
	$args = array(
		'post_type' => 'shop_order',
		'post_status' => array_keys($statuses),
		'meta_query' => array([
			'key' => 'oodo-status',
			'value' => 'failed',
			'compare' => '=',
		]),
		'posts_per_page' => $per_page,
		'paged' => $paged,
		'orderby' => 'date',
		'order' => 'DESC',
	);
	
	// Add date filters
	if (!empty($from_date) || !empty($to_date)) {
		$args['date_query'] = array();
		if (!empty($from_date)) {
			$args['date_query']['after'] = $from_date;
		}
		if (!empty($to_date)) {
			$args['date_query']['before'] = $to_date;
		}
		$args['date_query']['inclusive'] = true;
	}
	
	// Add order status filter
	if (!empty($order_status)) {
		$args['post_status'] = array($order_status);
	}
	
	// Add customer search
	if (!empty($customer_search)) {
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key' => '_billing_first_name',
				'value' => $customer_search,
				'compare' => 'LIKE'
			),
			array(
				'key' => '_billing_last_name',
				'value' => $customer_search,
				'compare' => 'LIKE'
			),
			array(
				'key' => '_billing_email',
				'value' => $customer_search,
				'compare' => 'LIKE'
			)
		);
	}
	
	// Add order ID search
	if (!empty($order_id_search)) {
		$args['post__in'] = array(intval($order_id_search));
	}
	
	$orders = get_posts($args);
	$total_orders = count(get_posts(array_merge($args, array('posts_per_page' => -1, 'paged' => 1))));
	$total_pages = ceil($total_orders / $per_page);
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Failed Odoo Orders', 'text-domain'); ?></h1>
		
		<!-- Filters Form -->
		<form method="GET" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
			
			<div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end;">
				<div>
					<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Date Range:', 'text-domain'); ?></label>
					<div style="display: flex; gap: 10px;">
						<input type="date" name="from_date" value="<?php echo esc_attr($from_date); ?>" placeholder="<?php esc_attr_e('From Date', 'text-domain'); ?>">
						<input type="date" name="to_date" value="<?php echo esc_attr($to_date); ?>" placeholder="<?php esc_attr_e('To Date', 'text-domain'); ?>">
					</div>
				</div>
				
				<div>
					<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Order Status:', 'text-domain'); ?></label>
					<select name="order_status" style="min-width: 150px;">
						<option value=""><?php esc_html_e('All Statuses', 'text-domain'); ?></option>
						<?php foreach ($statuses as $status_key => $status_label) : ?>
							<option value="<?php echo esc_attr($status_key); ?>" <?php selected($order_status, $status_key); ?>>
								<?php echo esc_html($status_label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div>
					<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Customer Search:', 'text-domain'); ?></label>
					<input type="text" name="customer_search" value="<?php echo esc_attr($customer_search); ?>" placeholder="<?php esc_attr_e('Name or Email', 'text-domain'); ?>" style="min-width: 200px;">
				</div>
				
				<div>
					<label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Order ID:', 'text-domain'); ?></label>
					<input type="number" name="order_id_search" value="<?php echo esc_attr($order_id_search); ?>" placeholder="<?php esc_attr_e('Order ID', 'text-domain'); ?>" style="min-width: 100px;">
				</div>
				
				<div>
					<button type="submit" class="button button-primary"><?php esc_html_e('Apply Filters', 'text-domain'); ?></button>
					<a href="<?php echo esc_url(admin_url('admin.php?page=odoo-failed-orders')); ?>" class="button"><?php esc_html_e('Clear Filters', 'text-domain'); ?></a>
				</div>
			</div>
		</form>
		
		<!-- Results Summary -->
		<div style="margin-bottom: 15px;">
			<p><strong><?php esc_html_e('Total Orders Found:', 'text-domain'); ?></strong> <?php echo esc_html($total_orders); ?></p>
		</div>
		
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
			'base' => add_query_arg('paged', '%#%'),
			'format' => '',
			'current' => $paged,
			'total' => $total_pages,
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