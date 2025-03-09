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

	// Get date range filters from the request.
	$from_date = isset( $_GET['from_date'] ) ? sanitize_text_field( $_GET['from_date'] ) : '';
	$to_date   = isset( $_GET['to_date'] ) ? sanitize_text_field( $_GET['to_date'] ) : '';

	// Define statuses that should always be removed from the filter & query.
	$always_excluded_statuses = array(
		'wc-user-changed',
		'wc-refunded',
		'wc-pending',
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

	// Build the date query.
	$date_query = array();
	if ( ! empty( $from_date ) ) {
		$date_query['after'] = $from_date;
	}
	if ( ! empty( $to_date ) ) {
		$date_query['before'] = $to_date;
	}
	if ( ! empty( $date_query ) ) {
		$date_query['inclusive'] = true;
	}

	// Build the query args.
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => array_diff( array_keys( $statuses ), $excluded_statuses ), // Apply user-selected exclusions
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => $orders_per_page,
		'paged'          => $paged,
		'date_query'     => array( $date_query ), // Apply date range filter
		'meta_query'     => array(
			array(
				'key'     => 'oodo-status',
				'compare' => 'NOT EXISTS', // Fetch orders that do not have this meta key.
			),
		),
	);
	$orders_query = new WP_Query( $args );
	$orders       = $orders_query->posts;
	$total_pages  = $orders_query->max_num_pages;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Orders Without Odoo Status', 'text-domain' ); ?></h1>

		<form method="GET" style="direction:rtl">
			<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">

			<div style="margin-bottom: 15px;">
				<strong><?php esc_html_e( 'Filter by Date Range:', 'text-domain' ); ?></strong><br>
				<label>
					<?php esc_html_e( 'From:', 'text-domain' ); ?>
					<input type="date" name="from_date" value="<?php echo esc_attr( $from_date ); ?>">
				</label>
				<label>
					<?php esc_html_e( 'To:', 'text-domain' ); ?>
					<input type="date" name="to_date" value="<?php echo esc_attr( $to_date ); ?>">
				</label>
			</div>

			<div style="margin-bottom: 15px;">
				<strong><?php esc_html_e( 'Exclude Order Statuses:', 'text-domain' ); ?></strong><br>

				<!-- Check All Box -->
				<label style="margin-right: 10px;display:inline-flex;width:15%;align-items:center">
					<input type="checkbox" id="check_all_statuses">&nbsp;
					<strong><?php esc_html_e( 'Check All', 'text-domain' ); ?></strong>
				</label>

				<?php foreach ( $statuses as $status_key => $status_label ) : ?>
					<label style="margin-right: 10px;display:inline-flex;width:15%;align-items:center">
						<input type="checkbox" class="status-checkbox" name="excluded_statuses[]" value="<?php echo esc_attr( $status_key ); ?>" <?php checked( in_array( $status_key, $excluded_statuses ) ); ?>>&nbsp;
						<?php echo esc_html( $status_label ); ?>
					</label>
				<?php endforeach; ?>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filter', 'text-domain' ); ?></button>
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
						$status_key = 'wc-' . $order->get_status();
						$status_label = isset( $statuses[ $status_key ] ) ? $statuses[ $status_key ] : ucfirst( $order->get_status() );
						?>
						<tr>
							<td><?php echo esc_html( $order->get_id() ); ?></td>
							<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
							<td><?php echo wc_price( $order->get_total() ); ?></td>
							<td><?php echo esc_html( $status_label ); ?></td>
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

function add_missing_all_status_orders_admin_bar_item( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// Get user-selected excluded statuses from URL parameters.
	$user_excluded_statuses = isset( $_GET['excluded_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_GET['excluded_statuses'] ) : array();

	// Get user-selected date filters.
	$from_date = isset( $_GET['from_date'] ) ? sanitize_text_field( $_GET['from_date'] ) : '';
	$to_date   = isset( $_GET['to_date'] ) ? sanitize_text_field( $_GET['to_date'] ) : '';

	// Define statuses that should always be excluded.
	$always_excluded_statuses = array(
		'wc-user-changed',
		'wc-refunded',
		'wc-pending',
		'wc-cancel-request',
		'wc-cancelled',
		'wc-was-canceled',
		'wc-completed',
		'wc-custom-failed',
		'wc-checkout-draft',
		'wc-failed',
	);

	// Merge user-selected and always-excluded statuses.
	$excluded_statuses = array_unique( array_merge( $always_excluded_statuses, $user_excluded_statuses ) );

	// Build the date query.
	$date_query = array();
	if ( ! empty( $from_date ) ) {
		$date_query['after'] = $from_date;
	}
	if ( ! empty( $to_date ) ) {
		$date_query['before'] = $to_date;
	}
	if ( ! empty( $date_query ) ) {
		$date_query['inclusive'] = true;
	}

	// Fetch the count of orders without Odoo status.
	$args = array(
		'post_type'      => 'shop_order',
		'post_status'    => array_diff( array_keys( wc_get_order_statuses() ), $excluded_statuses ), // Exclude both user-selected & always-excluded
		'posts_per_page' => -1,
		'date_query'     => array( $date_query ), // Apply date range filter
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

	// Generate the URL for the admin page, keeping user-selected filters in the query string.
	$admin_url = admin_url( 'admin.php?page=all-odoo-missing-status-orders' );

	// Preserve excluded statuses in the URL.
	if ( ! empty( $user_excluded_statuses ) ) {
		foreach ( $user_excluded_statuses as $index => $status ) {
			$admin_url = add_query_arg( "excluded_statuses[$index]", $status, $admin_url );
		}
	}

	// Preserve date filters in the URL.
	if ( ! empty( $from_date ) ) {
		$admin_url = add_query_arg( 'from_date', $from_date, $admin_url );
	}
	if ( ! empty( $to_date ) ) {
		$admin_url = add_query_arg( 'to_date', $to_date, $admin_url );
	}

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
			'href'  => $admin_url,
		)
	);
}

add_action( 'admin_bar_menu', 'add_missing_all_status_orders_admin_bar_item', 100 );
