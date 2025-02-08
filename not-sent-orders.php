<?php
/**
 * Orders without Odoo status meta key created after February 1, 2025
 */

defined( 'ABSPATH' ) || die;
/**
 * Add an admin menu page to display orders without oodo-status.
 */
function odoo_missing_status_orders_admin_page() {
	add_menu_page(
		esc_html__( 'Orders Without Odoo Status', 'text-domain' ),
		esc_html__( 'Odoo Missing Status Orders', 'text-domain' ),
		'manage_woocommerce',
		'odoo-missing-status-orders',
		'display_odoo_missing_status_orders_page',
		'dashicons-warning',
		58
	);
}
add_action( 'admin_menu', 'odoo_missing_status_orders_admin_page' );

/**
 * Display the admin page content for orders without Odoo status.
 */
function display_odoo_missing_status_orders_page() {
	// Fetch orders without oodo-status meta key created after February 1, 2025.
	$args = array(
		'post_type'   => 'shop_order',
		'post_status' => 'any',
		'orderby'     => 'date',
		'order'       => 'DESC',
		'posts_per_page' => -1,
		'date_query'  => array(
			array(
				'after'     => '2025-02-01',
				'inclusive' => true,
			),
		),
		'meta_query'  => array(
			array(
				'key'     => 'oodo-status',
				'compare' => 'NOT EXISTS', // Fetch orders that do not have this meta key
			),
		),
	);

	$orders = get_posts( $args );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Orders Without Odoo Status', 'text-domain' ); ?></h1>
		<table class="widefat fixed" cellspacing="0">
			<thead>
				<tr>
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
							<td><?php echo esc_html( $order->get_id() ); ?></td>
							<td><?php echo esc_html( $order_name ); ?></td>
							<td><?php echo wc_price( $order->get_total() ); ?></td>
							<td><?php echo esc_html( ucfirst( $order->get_status() ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>">
									<?php esc_html_e( 'View Order', 'text-domain' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5">
							<?php esc_html_e( 'No orders without Odoo status found.', 'text-domain' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Add a link with a count of orders missing Odoo status to the admin bar.
 */
function add_missing_status_orders_admin_bar_item( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// Fetch the count of orders without Odoo status meta key created after February 1, 2025.
	$args = array(
		'post_type'   => 'shop_order',
		'post_status' => 'any',
		'date_query'  => array(
			array(
				'after'     => '2025-02-01',
				'inclusive' => true,
			),
		),
		'meta_query'  => array(
			array(
				'key'     => 'oodo-status',
				'compare' => 'NOT EXISTS',
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
			'id'    => 'missing_odoo_status_orders',
			'title' => sprintf(
				'<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px;">%s (%d)</span>',
				$color,
				esc_html__( 'Orders Missing Odoo Status', 'text-domain' ),
				$count
			),
			'href'  => admin_url( 'admin.php?page=odoo-missing-status-orders' ),
		)
	);
}
add_action( 'admin_bar_menu', 'add_missing_status_orders_admin_bar_item', 100 );
