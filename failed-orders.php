<?php
/**
 * Oddo failed to be sent orders
 */

defined( 'ABSPATH' ) || die;
/**
 * Add an admin menu page to display orders with failed Odoo status.
 */
function odoo_failed_orders_admin_page() {
	add_submenu_page(
		'odoo-orders',
		esc_html__( 'Failed Odoo Orders', 'text-domain' ),
		esc_html__( 'Failed Orders', 'text-domain' ),
		'manage_woocommerce',
		'odoo-failed-orders',
		'display_odoo_failed_orders_page'
	);
}

add_action( 'admin_menu', 'odoo_failed_orders_admin_page' );

/**
 * Process bulk send to Odoo form submission.
 */
function process_odoo_bulk_send_form() {
	if ( isset( $_POST['bulk_send_odoo'] ) && ! empty( $_POST['order_ids'] ) ) {
		send_orders_batch_to_odoo( $_POST['order_ids'] );
		echo '<div class="updated"><p>' . esc_html__( 'Selected orders have been sent to Odoo.', 'text-domain' ) . '</p></div>';
	}
}

/**
 * Display the admin page content for failed Odoo orders.
 */
function display_odoo_failed_orders_page() {
	// Process form submission.
	process_odoo_bulk_send_form();

	// Fetch orders with odoo-status set to failed.
	$args = array(
		'post_type'   => 'shop_order',
		'post_status' => 'any',
		'meta_query'  => array(
			array(
				'key'     => 'oodo-status',
				'value'   => 'failed',
				'compare' => '=',
			),
		),
	);

	$orders = get_posts( $args );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Failed Odoo Orders', 'text-domain' ); ?></h1>
		<form method="post">
			<table class="widefat fixed" cellspacing="0">
				<thead>
					<tr>
						<th><input type="checkbox" id="select_all" /></th>
						<th><?php esc_html_e( 'Order ID', 'text-domain' ); ?></th>
						<th><?php esc_html_e( 'Customer Name', 'text-domain' ); ?></th>
						<th><?php esc_html_e( 'Total', 'text-domain' ); ?></th>
						<th><?php esc_html_e( 'Status', 'text-domain' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'text-domain' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $orders ) ) : ?>
						<?php foreach ( $orders as $order_post ) : ?>
							<?php
							$order      = wc_get_order( $order_post->ID );
							$order_name = $order->get_formatted_billing_full_name();
							?>
							<tr>
								<td><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr( $order->get_id() ); ?>" /></td>
								<td><?php echo esc_html( $order->get_id() ); ?></td>
								<td><?php echo esc_html( $order_name ); ?></td>
								<td><?php echo wc_price( $order->get_total() ); ?></td>
								<td><?php echo esc_html( ucfirst( get_post_meta( $order->get_id(), 'oodo-status', true ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>">
										<?php esc_html_e( 'View Order', 'text-domain' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="6">
								<?php esc_html_e( 'No orders with failed Odoo status found.', 'text-domain' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php if ( ! empty( $orders ) ) : ?>
				<p>
					<input type="submit" name="bulk_send_odoo" class="button-primary" value="<?php esc_attr_e( 'Send Selected to Odoo', 'text-domain' ); ?>" />
				</p>
			<?php endif; ?>
		</form>
	</div>
	<script>
		document.getElementById('select_all').addEventListener('click', function() {
			const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
			checkboxes.forEach(checkbox => checkbox.checked = this.checked);
		});
	</script>
	<?php
}


/**
 * Add a link with a count of failed orders to the admin bar.
 */
function add_failed_orders_admin_bar_item( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// Fetch the count of failed Odoo orders.
	$args = array(
		'post_type'   => 'shop_order',
		'post_status' => 'any',
		'meta_query'  => array(
			array(
				'key'     => 'oodo-status',
				'value'   => 'failed',
				'compare' => '=',
			),
		),
		'fields'      => 'ids',
	);

	$orders = get_posts( $args );
	$count  = count( $orders );
	$color  = $count > 0 ? 'red' : 'green';
	// Add a menu item to the admin bar.
	$wp_admin_bar->add_node(
		array(
			'id'    => 'failed_odoo_orders',
			'title' => sprintf(
				'<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px;">%s (%d)</span>',
				$color,
				esc_html__( 'Failed Odoo Orders', 'text-domain' ),
				$count
			),
			'href'  => admin_url( 'admin.php?page=odoo-failed-orders' ),
		)
	);
}
add_action( 'admin_bar_menu', 'add_failed_orders_admin_bar_item', 100 );
