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
		send_orders_batch_to_odoo( $_POST['order_ids'], false );
		echo '<div class="updated"><p>' . esc_html__( 'Selected orders have been sent to Odoo.', 'text-domain' ) . '</p></div>';
	}
}

/**
 * Display the admin page content for failed Odoo orders with pagination.
 */
function display_odoo_failed_orders_page() {
	// Process form submission.
	process_odoo_bulk_send_form();

	// Pagination setup
	$per_page = 50;
	$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$offset   = ( $paged - 1 ) * $per_page;
	// Define statuses that should always be removed from the filter & query.
	$always_excluded_statuses = array(
		'wc-user-changed',
		'wc-refunded',
		//'wc-pending',
		'wc-cancel-request',
		'wc-cancelled',
		'wc-was-canceled',
		'wc-completed',
		'wc-custom-failed',
		'wc-checkout-draft',
		'wc-failed',
	);

	// Get all WooCommerce order statuses.
	$statuses = wc_get_order_statuses();

	// Remove permanently excluded statuses from the filter options.
	foreach ( $always_excluded_statuses as $status ) {
		unset( $statuses[ $status ] );
	}
	// Fetch orders with odoo-status set to failed.
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => array_keys( $statuses ),
		'meta_query'     => array(
			array(
				'key'     => 'oodo-status',
				'value'   => 'failed',
				'compare' => '=',
			),
		),
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'offset'         => $offset,
	);

	$orders       = get_posts( $args );
	$total_orders = count(
		get_posts(
			array_merge(
				$args,
				array(
					'posts_per_page' => -1,
					'paged'          => 1,
					'offset'         => 0,
				)
			)
		)
	);
	$total_pages  = ceil( $total_orders / $per_page );
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
						<th><?php esc_html_e( 'Date', 'text-domain' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'text-domain' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $orders ) ) : ?>
						<?php foreach ( $orders as $order_post ) : ?>
							<?php
							$order      = wc_get_order( $order_post->ID );
							$order_name = $order->get_formatted_billing_full_name();
							$status_key = 'wc-' . $order->get_status();
							$status_label = isset( $statuses[ $status_key ] ) ? $statuses[ $status_key ] : ucfirst( $order->get_status() );
							?>
							<tr>
								<td><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr( $order->get_id() ); ?>" /></td>
								<td><?php echo esc_html( $order->get_id() ); ?></td>
								<td><?php echo esc_html( $order_name ); ?></td>
								<td><?php echo wc_price( $order->get_total() ); ?></td>
								<td><?php echo esc_html( $status_label ); ?></td>
								<td><?php echo esc_html( $order->get_date_created()->date('Y-m-d H:i:s') ); ?></td>
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

		<?php
		echo paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => __( '« Previous', 'text-domain' ),
				'next_text' => __( 'Next »', 'text-domain' ),
			)
		);
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

/**
 * Add a link with a count of failed orders to the admin bar.
 */
function add_failed_orders_admin_bar_item( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$always_excluded_statuses = array(
		'wc-user-changed',
		'wc-refunded',
		//'wc-pending',
		'wc-cancel-request',
		'wc-cancelled',
		'wc-was-canceled',
		'wc-completed',
		'wc-custom-failed',
		'wc-checkout-draft',
		'wc-failed',
	);

	// Get all WooCommerce order statuses.
	$statuses = wc_get_order_statuses();

	// Remove permanently excluded statuses from the filter options.
	foreach ( $always_excluded_statuses as $status ) {
		unset( $statuses[ $status ] );
	}
	// Fetch only the count of failed Odoo orders (more efficient)
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => array_keys( $statuses ),
		'meta_query'     => array(
			array(
				'key'     => 'oodo-status',
				'value'   => 'failed',
				'compare' => '=',
			),
		),
		'posts_per_page' => 1,  // No need to fetch all, just get the count
		'fields'         => 'ids',
	);

	$query = new WP_Query($args);
	$count = $query->found_posts; // More efficient than count(get_posts())

	// Set the color of the badge
	$color = $count > 0 ? 'red' : 'green';

	// Add a menu item to the admin bar
	$wp_admin_bar->add_node(
		array(
			'id'    => 'failed_odoo_orders',
			'title' => sprintf(
				'<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px;">%s (%d)</span>',
				$color,
				esc_html__( 'Failed Odoo Orders', 'text-domain' ),
				$count
			),
			'href'  => admin_url( 'admin.php?page=odoo-failed-orders&paged=1' ), // Direct to paginated list
		)
	);
}
add_action( 'admin_bar_menu', 'add_failed_orders_admin_bar_item', 100 );