<?php
/**
 * Add an admin menu page to display orders with Odoo meta key.
 */
function odoo_orders_with_meta_admin_page() {
	add_menu_page(
		esc_html__( 'Odoo orders', 'text-domain' ),
		esc_html__( 'Odoo Orders', 'text-domain' ),
		'manage_woocommerce',
		'odoo-orders-with-meta',
		'display_odoo_orders_with_meta_page',
		'dashicons-clipboard',
		59
	);
}
add_action( 'admin_menu', 'odoo_orders_with_meta_admin_page' );

/**
 * Display the admin page content for orders with `odoo_order` meta key.
 */
function display_odoo_orders_with_meta_page() {
	// Fetch orders with odoo_order meta key.
	$args = array(
		'post_type'   => 'shop_order',
		'post_status' => 'any',
		'meta_query'  => array(
			array(
				'key'     => 'odoo_order',
				'compare' => 'EXISTS',
			),
		),
	);

	$orders = get_posts( $args );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Orders with Odoo Meta Key', 'text-domain' ); ?></h1>
		<table class="widefat fixed" cellspacing="0">
			<thead>
				<tr>
					<th><input type="checkbox" id="select_all_meta" /></th>
					<th><?php esc_html_e( 'Order ID', 'text-domain' ); ?></th>
					<th><?php esc_html_e( 'Customer Name', 'text-domain' ); ?></th>
					<th><?php esc_html_e( 'Total', 'text-domain' ); ?></th>
					<th><?php esc_html_e( 'Odoo Meta Value', 'text-domain' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'text-domain' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $orders ) ) : ?>
					<?php foreach ( $orders as $order_post ) : ?>
						<?php
						$order      = wc_get_order( $order_post->ID );
						$order_name = $order->get_formatted_billing_full_name();
						$odoo_value = get_post_meta( $order->get_id(), 'odoo_order', true );
						?>
						<tr>
							<td><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr( $order->get_id() ); ?>" /></td>
							<td><?php echo esc_html( $order->get_id() ); ?></td>
							<td><?php echo esc_html( $order_name ); ?></td>
							<td><?php echo wc_price( $order->get_total() ); ?></td>
							<td><?php echo esc_html( $odoo_value ); ?></td>
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
							<?php esc_html_e( 'No orders with Odoo meta key found.', 'text-domain' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<script>
		document.getElementById('select_all_meta').addEventListener('click', function() {
			const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
			checkboxes.forEach(checkbox => checkbox.checked = this.checked);
		});
	</script>
	<?php
}
