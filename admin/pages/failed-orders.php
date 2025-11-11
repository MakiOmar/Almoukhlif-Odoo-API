<?php
/**
 * Oddo failed to be sent orders
 */

defined( 'ABSPATH' ) || die;

// Admin page for failed Odoo orders (table, filters, bulk actions)
if (!function_exists('display_odoo_failed_orders_page')) {
function display_odoo_failed_orders_page() {
	// Process bulk resend action
	Odoo_Admin_Filters::process_bulk_action('bulk_send_odoo', $_POST['order_ids'] ?? array(), false);

	// Handle marking orders as skipped
	if (!empty($_POST['bulk_mark_skipped']) && !empty($_POST['order_ids'])) {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(__('You do not have permission to perform this action.', 'text-domain'));
		}

		check_admin_referer('odoo_failed_orders_actions', 'odoo_failed_orders_nonce');

		$skipped_count = class_exists('Odoo_Admin')
			? Odoo_Admin::mark_orders_as_skipped($_POST['order_ids'])
			: 0;

		$redirect_args = class_exists('Odoo_Admin')
			? Odoo_Admin::get_failed_orders_redirect_args($skipped_count)
			: array(
				'page' => 'odoo-failed-orders',
				'odoo_skipped' => max(0, intval($skipped_count)),
			);

		wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
		exit;
	}
	
	// Get filter parameters
	$filters = Odoo_Admin_Filters::get_filter_params();
	$per_page = 50;
	
	// Get available statuses
	$always_excluded_statuses = array('wc-user-changed','wc-refunded','wc-cancel-request','wc-cancelled','wc-was-canceled','wc-completed','wc-custom-failed','wc-checkout-draft','wc-failed');
	$available_statuses = Odoo_Admin_Filters::get_available_statuses($always_excluded_statuses);
	
	// Build base query args
	$base_args = array(
		'post_type' => 'shop_order',
		'post_status' => array_keys($available_statuses),
		'meta_query' => array([
			'key' => 'oodo-status',
			'value' => 'failed',
			'compare' => '=',
		]),
		'posts_per_page' => $per_page,
		'paged' => $filters['paged'],
		'orderby' => 'date',
		'order' => 'DESC',
	);
	
	// Apply filters
	$args = Odoo_Admin_Filters::build_query_args($base_args, $filters);
	
	$orders = get_posts($args);
	$total_orders = count(get_posts(array_merge($args, array('posts_per_page' => -1, 'paged' => 1))));
	$total_pages = ceil($total_orders / $per_page);
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Failed Odoo Orders', 'text-domain'); ?></h1>

		<?php if (isset($_GET['odoo_skipped'])) : ?>
			<?php
			$skipped_count = max(0, intval($_GET['odoo_skipped']));
			if ($skipped_count > 0) :
				?>
				<div class="updated notice is-dismissible">
					<p>
						<?php
						printf(
							esc_html(_n('Marked %d order as skipped.', 'Marked %d orders as skipped.', $skipped_count, 'text-domain')),
							$skipped_count
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		
		<!-- Filters Form -->
		<?php Odoo_Admin_Filters::render_filters_form($filters, $available_statuses, 'odoo-failed-orders'); ?>
		
		<!-- Results Summary -->
		<?php Odoo_Admin_Filters::render_results_summary($total_orders); ?>
		
		<form method="post">
			<?php wp_nonce_field('odoo_failed_orders_actions', 'odoo_failed_orders_nonce'); ?>
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
							$status_label = isset($available_statuses[$status_key]) ? $available_statuses[$status_key] : ucfirst($order->get_status());
							$skip_base_args = class_exists('Odoo_Admin')
								? Odoo_Admin::get_failed_orders_redirect_args(null)
								: array('page' => 'odoo-failed-orders');
							$skip_url = add_query_arg(
								array_merge(
									(array) $skip_base_args,
									array(
										'action'   => 'odoo_mark_skipped_single',
										'order_id' => $order->get_id(),
									)
								),
								admin_url('admin.php')
							);
							$skip_url = wp_nonce_url($skip_url, 'odoo_mark_skipped_single_' . $order->get_id());
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
									|
									<a class="button button-secondary button-small" href="<?php echo esc_url($skip_url); ?>">
										<?php esc_html_e('Set as Skipped', 'text-domain'); ?>
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
					<input type="submit" name="bulk_mark_skipped" class="button" value="<?php esc_attr_e('Mark Selected as Skipped', 'text-domain'); ?>" />
				</p>
			<?php endif; ?>
		</form>
		<?php
		echo paginate_links(array(
			'base' => add_query_arg('paged', '%#%'),
			'format' => '',
			'current' => $filters['paged'],
			'total' => $total_pages,
			'prev_text' => __('« Previous', 'text-domain'),
			'next_text' => __('Next »', 'text-domain'),
		));
		?>
	</div>
	<?php Odoo_Admin_Filters::render_select_all_js(); ?>
	<?php
}
}