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

	$quantity = $quantity;

	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $passed;
	}

	// Get the product SKU.
	$product = wc_get_product( $product_id );
	$sku     = $product->get_sku();

	// Odoo API endpoints.
	$auth_url  = ODOO_BASE . 'web/session/erp_authenticate';
	$stock_url = ODOO_BASE . 'api/stock.quant/get_available_qty_data';

	// Step 1: Authenticate to get the token.
	$auth_body = json_encode(
		array(
			'params' => array(
				'db'       => 'almokhlif-oud-live-staging-15355104',
				'login'    => 'hussam.elsayed@almokhlifoud.com',
				'password' => '123',
			),
		)
	);

	$auth_response = wp_remote_post(
		$auth_url,
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => $auth_body,
		)
	);
	if ( is_wp_error( $auth_response ) ) {
		wc_add_notice( 'لا يمكن الاتصال بالخادم للتحقق من المخزون. يرجى المحاولة لاحقًا.', 'error' );
		return false;
	}

	$auth_body_response = wp_remote_retrieve_body( $auth_response );
	$auth_data          = json_decode( $auth_body_response );
	if ( ! isset( $auth_data->result->token ) ) {
		wc_add_notice( 'فشل التحقق من المخزون. يرجى المحاولة لاحقًا.', 'error' );
		return false;
	}

	// Retrieve the token from the response.
	$token = $auth_data->result->token;

	// Step 2: Fetch stock data.
	$stock_body = json_encode( array( 'default_code' => $sku ) );

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
		// Get the order object.
		$order = wc_get_order( $order_id );

		// Prepare the order data.
		$order_data = array(
			'order_id' => $order->get_id(),
			'total'    => $order->get_total(),
			'currency' => $order->get_currency(),
			'billing'  => array(
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
			'items'    => array(),
			'fees'     => array(),
			'tax'      => $order->get_total_tax(),
			'discount' => $order->get_discount_total(),
		);

		// Get line items.
		/**
		 * Order item.
		 *
		 * @var WC_Order_Item $item
		 */
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$product                  = $item->get_product();
			$order_data['products'][] = array(
				'product_id' => $product ? $product->get_id() : 0,
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),      // Subtotal for the line item.
				'total'      => $item->get_total(),         // Total including discounts.
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
		$odoo_api_url = 'https://your-odoo-instance.com/api/order';

		// Send the data to the external API.
		$response = wp_remote_post(
			$odoo_api_url,
			array(
				'method'  => 'POST',
				'body'    => wp_json_encode( $order_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			error_log( 'Order transfer to Odoo failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		} else {
			error_log( 'Order transfer to Odoo succeeded for order ID: ' . $order_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
add_action( 'woocommerce_order_status_changed', 'send_order_details_to_odoo', 10, 3 );
