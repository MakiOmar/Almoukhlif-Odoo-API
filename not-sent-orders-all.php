<?php
/**
 * Orders without Odoo status meta key created after February 1, 2025
 */

defined( 'ABSPATH' ) || die;
function display_all_odoo_missing_status_orders_page() {
	// Set the number of orders to display per page.
	$orders_per_page = 10;

	// Get the current page number.
	$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

	// Get excluded statuses from the request.
	$excluded_statuses = isset( $_GET['excluded_statuses'] ) ? (array) $_GET['excluded_statuses'] : array();

	// Get all WooCommerce order statuses.
	$statuses = wc_get_order_statuses();

	// Build the meta query for orders missing 'oodo-status'.
	$meta_query = array(
		array(
			'key'     => 'oodo-status',
			'compare' => 'NOT EXISTS', // Fetch orders that do not have this meta key.
		),
	);

	// Build the query args.
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => array_diff( array_keys( $statuses ), $excluded_statuses ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => $orders_per_page,
		'paged'          => $paged,
		'date_query'     => array(
			array(
				'after'     => '2025-02-17',
				'inclusive' => true,
			),
		),
		'meta_query'     => $meta_query,
	);
	var_dump( $args );
	$orders_query = new WP_Query( $args );
	$orders       = $orders_query->posts;
	$total_pages  = $orders_query->max_num_pages;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Orders Without Odoo Status', 'text-domain' ); ?></h1>

		<form method="GET" style="direction:rtl">
			<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">
			<div style="margin-bottom: 15px;">
				<strong><?php esc_html_e( 'Exclude Order Statuses:', 'text-domain' ); ?></strong><br>
				<?php foreach ( $statuses as $status_key => $status_label ) : ?>
					<label style="margin-right: 10px;display:inline-flex;width:15%;align-items:center">
						<input type="checkbox" name="excluded_statuses[]" value="<?php echo esc_attr( $status_key ); ?>" <?php checked( in_array( $status_key, $excluded_statuses ) ); ?>>&nbsp;
						<?php echo esc_html( $status_label ); ?>
					</label>
				<?php endforeach; ?>
				<button type="submit" class="button button-primary">Apply Filter</button>
			</div>
		</form>

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
						$order = wc_get_order( $order_post->ID );
						?>
						<tr>
							<td><?php echo esc_html( $order->get_id() ); ?></td>
							<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
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

		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo; Previous', 'text-domain' ),
						'next_text' => __( 'Next &raquo;', 'text-domain' ),
						'total'     => $total_pages,
						'current'   => $paged,
					)
				);
				?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Add a link with a count of orders missing Odoo status to the admin bar.
 */
function add_missing_all_status_orders_admin_bar_item( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// Fetch the count of orders without Odoo status meta key created after February 1, 2025.
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'date_query'     => array(
			array(
				'after'     => '2025-02-17',
				'inclusive' => true,
			),
		),
		'meta_query'     => array(
			array(
				'key'     => 'oodo-status',
				'compare' => 'NOT EXISTS',
			),
		),
		'fields'         => 'ids',
	);

	$orders = get_posts( $args );
	$count  = count( $orders );
	$color  = $count > 0 ? 'red' : 'green';

	// Add a menu item to the admin bar.
	$wp_admin_bar->add_node(
		array(
			'id'    => 'all_missing_odoo_status_orders',
			'title' => sprintf(
				'<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px;">%s (%d)</span>',
				$color,
				esc_html__( 'All Missing Odoo Status', 'text-domain' ),
				$count
			),
			'href'  => admin_url( 'admin.php?page=all-odoo-missing-status-orders' ),
		)
	);
}
add_action( 'admin_bar_menu', 'add_missing_all_status_orders_admin_bar_item', 100 );