<?php
/**
 * Plugin Name: WordPress/Odoo Integration
 * Description: Integrates WooCommerce with Odoo to validate stock before adding products to the cart.
 * Version: 1.0
 * Author: Mohammad Omar
 *
 * @package Odod
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ODOO_BASE', 'https://almokhlif-oud-live-staging-15355104.dev.odoo.com/' );

// Include REST API integration for Odoo.
require_once plugin_dir_path( __FILE__ ) . 'includes/rest-api.php';
/**
 * Generate Odoo authentication token.
 *
 * @return string|false The token if successful, or false if there is an error.
 */
function get_odoo_auth_token() {
	// Odoo API authentication endpoint.
	$auth_url = ODOO_BASE . 'web/session/erp_authenticate';

	// Authentication request body.
	$auth_body = wp_json_encode(
		array(
			'params' => array(
				'db'       => 'almokhlif-oud-live-staging-15355104',
				'login'    => 'hussam.elsayed@almokhlifoud.com',
				'password' => '123',
			),
		)
	);

	// Send the authentication request.
	$auth_response = wp_remote_post(
		$auth_url,
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => $auth_body,
		)
	);

	// Check for errors in the response.
	if ( is_wp_error( $auth_response ) ) {
		error_log( 'Odoo authentication failed: ' . $auth_response->get_error_message() ); // Log the error for debugging.
		return false;
	}

	$auth_body_response = wp_remote_retrieve_body( $auth_response );
	$auth_data          = json_decode( $auth_body_response );

	// Check if the token exists in the response.
	if ( isset( $auth_data->result->token ) ) {
		return $auth_data->result->token;
	}

	error_log( 'Odoo authentication failed: Token not found in response.' ); // Log the error for debugging.
	return false;
}
/**
 * Check stock from Odoo before allowing a product to be added to the WooCommerce cart.
 *
 * This function fetches the product SKU and calls an Odoo API endpoint to verify stock levels.
 * If the Odoo stock is less than the WooCommerce stock, the product is prevented from being added to the cart.
 *
 * @param bool $passed     Whether the add-to-cart action should proceed.
 * @param int  $product_id The ID of the product being added to the cart.
 * @param int  $quantity   The quantity of the product.
 * @return bool Whether the add-to-cart action should proceed.
 */
function odoo_check_stock_before_add_to_cart( $passed, $product_id, $quantity ) {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $passed;
	}

	// Get the product SKU.
	$product = wc_get_product( $product_id );
	$sku     = $product->get_sku();

	// Fetch the authentication token.
	$token = get_odoo_auth_token();

	if ( ! $token ) {
		wc_add_notice( 'فشل التحقق من المخزون. يرجى المحاولة لاحقًا.', 'error' );
		return false;
	}

	// Odoo API stock endpoint.
	$stock_url = ODOO_BASE . 'api/stock.quant/get_available_qty_data';

	// Prepare the request body for stock data.
	$stock_body = json_encode( array( 'default_code' => $sku ) );

	// Send the stock request.
	$stock_response = wp_remote_post(
		$stock_url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'token'        => $token,
			),
			'body'    => $stock_body,
		)
	);

	if ( is_wp_error( $stock_response ) ) {
		wc_add_notice( 'لا يمكن التحقق من المخزون في هذا الوقت. يرجى المحاولة لاحقًا.', 'error' );
		return false;
	}

	$stock_body_response = wp_remote_retrieve_body( $stock_response );
	$stock_data          = json_decode( $stock_body_response );

	if ( ! isset( $stock_data->result->Data ) || ! is_array( $stock_data->result->Data ) ) {
		wc_add_notice( 'لا يمكن استرجاع معلومات المخزون. يرجى المحاولة لاحقًا.', 'error' );
		return false;
	}

	// Calculate total positive stock quantity.
	$total_stock = 0;
	foreach ( $stock_data->result->Data as $stock_item ) {
		$q = (int) $stock_item->quantity;
		if ( $q > 0 ) {
			$total_stock += $q;
		}
	}

	// Compare Odoo stock with WooCommerce stock.
	if ( absint( $total_stock ) < absint( $quantity ) ) {
		wc_add_notice( 'مخزون المنتج محدود. يرجى التواصل مع الدعم للحصول على مزيد من المعلومات.', 'error' );
		return false; // Prevent adding to cart.
	}

	return $passed; // Allow adding to cart if all checks pass.
}


add_filter( 'woocommerce_add_to_cart_validation', 'odoo_check_stock_before_add_to_cart', 10, 3 );

/**
 * Send order payment details to an external API when the order status changes to `odoo_transfered`.
 *
 * @package WordPress_Odoo_Integration
 */


/**
 * Send order payment details to an external API after order status changes to `odoo_transfered`.
 *
 * This function triggers when an order status changes to `odoo_transfered`, gathers order details,
 * and sends them to the specified external API endpoint in JSON format.
 *
 * @param int    $order_id   The ID of the order.
 * @param string $old_status The old status of the order.
 * @param string $new_status The new status of the order.
 */
function send_order_details_to_odoo( $order_id, $old_status, $new_status ) {
	// Check if the new status is `odoo_transfered`.
	if ( 'completed' === $new_status ) {
		$token = get_odoo_auth_token();

		if ( ! $token ) {
			error_log( "Order {$order_id} faild to be sont to Odoo" );
			return false;
		}
		$odoo_order = get_post_meta( $order_id, 'odoo_order', true );
		if ( ! empty( $odoo_order ) && is_numeric( $odoo_order ) ) {
			return;
		}
		// Get the order object.
		$order = wc_get_order( $order_id );

		// Prepare the order data.
		$order_data = array(
			'phone'          => '0594001088', // $order->get_billing_phone()
			'manual_confirm' => true,
			'state'          => 'draft',
			'order_line'     => array(),
			'order_id'       => $order->get_id(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'billing'        => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
			),
			'fees'           => array(),
			'tax'            => $order->get_total_tax(),
			'discount'       => $order->get_discount_total(),
			'shipping'       => array(
				'total'  => $order->get_shipping_total(),
				'tax'    => $order->get_shipping_tax(),
				'method' => $order->get_shipping_method(),
			),
		);

		// Get line items.
		/**
		 * Order item.
		 *
		 * @var WC_Order_Item $item
		 */
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$product                    = $item->get_product();
			$order_data['order_line'][] = array(
				'default_code'    => $product->get_sku(),
				'name'            => $item->get_name(),
				'product_uom_qty' => $item->get_quantity(),
				'price_subtotal'  => $item->get_subtotal(),      // Subtotal for the line item.
				'price_total'     => $item->get_total(),         // Total including discounts.
			);
		}

		// Get fees.
		foreach ( $order->get_items( 'fee' ) as $fee_id => $fee ) {
			$order_data['fees'][] = array(
				'name'  => $fee->get_name(),
				'total' => $fee->get_amount(), // Correct method for fee total.
			);
		}

		// Set the external API URL.
		$odoo_api_url = ODOO_BASE . 'api/sale.order/add_update_order';

		// Send the data to the external API.
		$response = wp_remote_post(
			$odoo_api_url,
			array(
				'method'  => 'POST',
				'body'    => wp_json_encode( $order_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'token'        => $token,
				),
			)
		);

		$order_body_response = wp_remote_retrieve_body( $response );
		$order_body          = json_decode( $order_body_response );

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			error_log( 'Order ' . $order_id . ' transfer to Odoo failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Check if the response is successful.
		if ( isset( $order_body->result->Code ) && 200 === $order_body->result->Code ) {
			// Extract the ID from the response and save it as order meta.
			$odoo_order_id = $order_body->result->Data->ID;
			update_post_meta( $order_id, 'odoo_order', $odoo_order_id );
		}
	}
}
add_action( 'woocommerce_order_status_changed', 'send_order_details_to_odoo', 10, 3 );


/**
 * Display Odoo Order ID under the billing details in the admin order page.
 *
 * @param WC_Order $order The order object.
 */
function display_odoo_order_id_in_admin( $order ) {
	$odoo_order_id = get_post_meta( $order->get_id(), 'odoo_order', true );

	if ( $odoo_order_id ) {
		echo '<p><strong>' . __( 'Odoo Order ID:', 'text-domain' ) . '</strong> ' . esc_html( $odoo_order_id ) . '</p>';
	}
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_odoo_order_id_in_admin', 10, 1 );
