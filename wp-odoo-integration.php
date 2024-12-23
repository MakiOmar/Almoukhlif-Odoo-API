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
			'timeout' => 20,
		)
	);

	// Check for errors in the response.
	if ( ! is_wp_error( $auth_response ) ) {
		$auth_body_response = wp_remote_retrieve_body( $auth_response );
		$auth_data          = json_decode( $auth_body_response );

		// Check if the token exists in the response.
		if ( isset( $auth_data->result->token ) ) {
			return $auth_data->result->token;
		}
	}
	return false;
}
/**
 * Check stock from Odoo before allowing a product to be added to the WooCommerce cart.
 *
 * This function fetches the product SKU and calls an Odoo API endpoint to verify stock levels.
 * If the Odoo stock is less than the WooCommerce stock, the product is prevented from being added to the cart.
 *
 * @param bool    $passed     Whether the add-to-cart action should proceed.
 * @param int     $product_id The ID of the product being added to the cart.
 * @param int     $quantity   The quantity of the product.
 * @param integer $variation_id      Variation ID being added to the cart.
 * @param array   $variation         Variation data.
 * @return bool Whether the add-to-cart action should proceed.
 */
function odoo_check_stock_before_add_to_cart( $passed, $product_id, $quantity, $variation_id, $variation ) {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $passed;
	}
	// Get the product SKU.
	$product = wc_get_product( $product_id );
	$sku     = $product->get_sku();
	// Check if the product is a variation.
	if ( $variation_id ) {
		// Get the parent (main) product object.
		$variation  = wc_get_product( $variation_id );
		$multiplier = $variation->get_meta( '_stock_multiplier' );
	} else {
		// Use the simple product's SKU.
		$multiplier = 1;
	}
	$quantity = $quantity * $multiplier;
	$message  = 'لا يمكن استرجاع معلومات المخزون. يرجى المحاولة لاحقًا.';
	// Fetch the authentication token.
	$token = get_odoo_auth_token();

	if ( ! $token ) {
		if ( function_exists( 'teamlog' ) ) {
			teamlog( $message );
		}
		wc_add_notice( $message, 'error' );
		return false;
	}

	// Odoo API stock endpoint.
	$stock_url = ODOO_BASE . 'api/stock.quant/get_available_qty_data';

	// Prepare the request body for stock data.
	$stock_body = json_encode(
		array(
			'default_code' => $sku,
			// 'location_id'  => 70,
		)
	);

	// Send the stock request.
	$stock_response = wp_remote_post(
		$stock_url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'token'        => $token,
			),
			'body'    => $stock_body,
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $stock_response ) ) {
		wc_add_notice( $message, 'error' );
		return false;
	}

	$stock_body_response = wp_remote_retrieve_body( $stock_response );
	$stock_data          = json_decode( $stock_body_response );
	if ( ! isset( $stock_data->result->Data ) || ! is_array( $stock_data->result->Data ) ) {
		wc_add_notice( $message, 'error' );
		return false;
	}
	// Calculate total positive stock quantity.
	$total_stock = 0;
	foreach ( $stock_data->result->Data as $stock_item ) {
		$q = (int) $stock_item->forecasted_quantity;
		if ( $q > 0 ) {
			$total_stock += $q;
		}
	}
	// Compare Odoo stock with WooCommerce stock.
	if ( absint( $total_stock ) < absint( $quantity ) ) {
		$message = 'مخزون المنتج محدود. يرجى التواصل مع الدعم للحصول على مزيد من المعلومات.';
		wc_add_notice( $message, 'error' );
		return false; // Prevent adding to cart.
	}

	return $passed; // Allow adding to cart if all checks pass.
}


add_filter( 'woocommerce_add_to_cart_validation', 'odoo_check_stock_before_add_to_cart', 10, 5 );

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
	// Check if the new status is `completed`.
	if ( 'completed' === $new_status ) {
		$odoo_order = get_post_meta( $order_id, 'odoo_order', true );

		if ( ! empty( $odoo_order ) && is_numeric( $odoo_order ) ) {
			return;
		}

		$token = get_odoo_auth_token();
		if ( ! $token ) {
			$message = "Order {$order_id} failed to be sent to Odoo.";
			error_log( $message );
			add_action(
				'admin_notices',
				function () use ( $message ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
				}
			);
			return false;
		}

		// Get the order object.
		$order = wc_get_order( $order_id );

		// Prepare the order data.
		$order_data = array(
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
		);

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			// Get the product associated with the line item.
			$product = $item->get_product();

			// Get the product ID.
			$product_id = $product->get_id();

			// Initialize the multiplier.
			$multiplier = 1;

			// Check if the product is a variation.
			if ( $product->is_type( 'variation' ) ) {
				// Get the `_stock_multiplier` meta value.
				$multiplier = (float) get_post_meta( $product_id, '_stock_multiplier', true );

				// Default multiplier to 1 if not set or invalid.
				if ( empty( $multiplier ) || $multiplier <= 0 ) {
					$multiplier = 1;
				}
			}

			// Multiply the quantity by the multiplier.
			$quantity = $item->get_quantity() * $multiplier;

			// Add the line item to the order data.
			$order_data['order_line'][] = array(
				'default_code'    => $product->get_sku(),
				'name'            => $item->get_name(),
				'product_uom_qty' => $quantity, // Adjusted quantity with multiplier.
				'price_unit'      => $item->get_total() / $quantity,
			);
		}

		$order_data['order_line'][] = array(
			'default_code'    => '1000000',
			'name'            => 'شحن',
			'product_uom_qty' => 1, // Adjusted quantity with multiplier.
			'price_unit'      => $order->get_shipping_total(),
		);

		// Get fees.
		foreach ( $order->get_items( 'fee' ) as $fee_id => $fee ) {
			$order_data['fees'][] = array(
				'name'  => $fee->get_name(),
				'total' => $fee->get_amount(),
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
				'timeout' => 20,
			)
		);

		$order_body_response = wp_remote_retrieve_body( $response );
		$order_body          = json_decode( $order_body_response );
		// Check for errors.
		if ( ! $response || is_wp_error( $response ) ) {
			$message = 'Order ' . $order_id . ' transfer to Odoo failed: ' . $response->get_error_message();
			error_log( $message );
			add_action(
				'admin_notices',
				function () use ( $message ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
				}
			);
		}

		// Check if the response is successful.
		if ( isset( $order_body->result->Code ) && 200 === $order_body->result->Code ) {
			// Extract the ID from the response and save it as order meta.
			$odoo_order_id = $order_body->result->Data->ID;
			update_post_meta( $order_id, 'odoo_order', $odoo_order_id );

			$message = "Order {$order_id} successfully transferred to Odoo with Odoo ID {$odoo_order_id}.";
			add_action(
				'admin_notices',
				function () use ( $message ) {
					echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
				}
			);
		} else {
			error_log( 'حدث خطأ ما' );
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
