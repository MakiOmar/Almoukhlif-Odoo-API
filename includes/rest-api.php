<?php
/**
 * REST API integration with Odoo for WooCommerce stock update.
 *
 * @package WordPress_Odoo_Integration
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'anony_theme_general_options',
	function ( $general ) {
		if ( class_exists( 'ANONY_SECURITY_KEYS' ) ) {
			$key = ANONY_SECURITY_KEYS::generate_rest_api_key();
		} else {
			$key = '';
		}
		$general['fields'][] = array(
			'id'       => 'rest_api_key',
			'title'    => esc_html__( 'Api Key', 'smartpage' ),
			'type'     => 'text',
			'validate' => 'no_html',
			'default'  => $key,
		);
		return $general;
	}
);

/**
 * Permission callback for the update stock endpoint.
 *
 * Validates the API key sent in the request.
 *
 * @param WP_REST_Request $request The REST API request.
 * @return bool True if the API key is valid, false otherwise.
 */
function odoo_update_stock_permission_check( $request ) {
	$options = get_option( 'Anony_Options' );
	if ( ! is_array( $options ) || empty( $options['rest_api_key'] ) ) {
		return false;
	}
	// Retrieve the API key from the headers.
	$api_key = $request->get_header( 'x-api-key' );

	// Replace 'your-secure-api-key' with the actual API key.
	$valid_api_key = $options['rest_api_key'];

	// Check if the provided API key matches the valid API key.
	return $api_key && hash_equals( $valid_api_key, $api_key );
}

/**
 * Registers the custom REST API endpoint for updating stock in WooCommerce.
 */
function register_odoo_update_stock_endpoint() {
	register_rest_route(
		'odoo/v1',
		'/update-stock',
		array(
			'methods'             => 'POST',
			'callback'            => 'odoo_update_stock_endpoint_handler',
			'args'                => array(
				'sku'   => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_string( $param ) && ! empty( $param );
					},
				),
				'stock' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param >= 0;
					},
				),
			),
			'permission_callback' => 'odoo_update_stock_permission_check', // Replace with proper permission check in production.
		)
	);
	register_rest_route(
		'woocommerce/v1',
		'/set-order-status',
		array(
			'methods'             => 'POST',
			'callback'            => 'set_order_status_handler',
			'args'                => array(
				'order_id' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0;
					},
				),
				'status' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						// Validate if it's a valid WooCommerce order status.
						$valid_statuses = wc_get_order_statuses();
						return isset( $valid_statuses[ 'wc-' . $param ] );
					},
				),
			),
			'permission_callback' => 'odoo_update_stock_permission_check',
		)
	);
}
add_action( 'rest_api_init', 'register_odoo_update_stock_endpoint' );

/**
 * Handles the request to set WooCommerce order status.
 *
 * @param WP_REST_Request $request The REST API request.
 * @return WP_REST_Response The response indicating success or failure.
 */
function set_order_status_handler( $request ) {
	$order_id = intval( $request->get_param( 'order_id' ) );
	$status   = sanitize_text_field( $request->get_param( 'status' ) );

	// Retrieve the order.
	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Invalid order ID.',
			),
			404
		);
	}

	// Update the order status.
	try {
		$order->update_status( $status, 'Status updated via REST API.', true );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Order status updated successfully.',
				'order_id' => $order_id,
				'status'   => $status,
			),
			200
		);
	} catch ( Exception $e ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Failed to update order status: ' . $e->getMessage(),
			),
			500
		);
	}
}
/**
 * Handles the custom REST API endpoint request to update WooCommerce stock by SKU.
 *
 * Updates stock information in WooCommerce for the given product SKU.
 *
 * @param WP_REST_Request $request The REST API request.
 * @return WP_REST_Response The response containing the update result or an error message.
 */
function odoo_update_stock_endpoint_handler( $request ) {
	// Sanitize and retrieve the SKU and stock parameters.
	$sku   = sanitize_text_field( $request->get_param( 'sku' ) );
	$stock = intval( $request->get_param( 'stock' ) );

	// Find the WooCommerce product by SKU.
	$product_id = wc_get_product_id_by_sku( $sku );
	if ( ! $product_id ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'No WooCommerce product found for the given SKU.',
			),
			404
		);
	}

	// Get the WooCommerce product and update its stock quantity.
	$product = wc_get_product( $product_id );
	if ( $product->managing_stock() ) {
		$product->set_stock_quantity( $stock );
		$product->save();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Stock updated successfully.',
				'sku'     => $sku,
				'stock'   => $stock,
			),
			200
		);
	} else {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Product does not manage stock.',
			),
			400
		);
	}
}
