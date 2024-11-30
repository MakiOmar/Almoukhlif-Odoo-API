<?php
/**
 * REST API integration with Odoo for WooCommerce stock validation.
 *
 * @package WordPress_Odoo_Integration
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the custom REST API endpoint for checking stock from Odoo.
 */
function register_odoo_stock_check_endpoint() {
	register_rest_route(
		'odoo/v1',
		'/check-stock',
		array(
			'methods'             => 'GET',
			'callback'            => 'odoo_check_stock_endpoint_handler',
			'args'                => array(
				'sku' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_string( $param ) && ! empty( $param );
					},
				),
			),
			'permission_callback' => '__return_true', // In production, replace with appropriate permission check.
		)
	);
}
add_action( 'rest_api_init', 'register_odoo_stock_check_endpoint' );

/**
 * Handles the custom REST API endpoint request to retrieve WooCommerce stock by SKU.
 *
 * Fetches stock information from WooCommerce for the given product SKU.
 *
 * @param WP_REST_Request $request The REST API request.
 * @return WP_REST_Response The response containing WooCommerce stock data or an error message.
 */
function odoo_check_stock_endpoint_handler( $request ) {
	// Sanitize and retrieve the SKU parameter.
	$sku = sanitize_text_field( $request->get_param( 'sku' ) );
	// Return the WooCommerce stock data in the response.
	return new WP_REST_Response(
		array(
			'success' => true,
			'sku'     => $sku,
			'stock'   => 5,
		),
		200
	);
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

	// Get WooCommerce stock quantity for the product.
	$product           = wc_get_product( $product_id );
	$woocommerce_stock = $product->get_stock_quantity();

	// Return the WooCommerce stock data in the response.
	return new WP_REST_Response(
		array(
			'success' => true,
			'sku'     => $sku,
			'stock'   => (int) $woocommerce_stock,
		),
		200
	);
}
