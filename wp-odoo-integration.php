<?php
/**
 * Plugin Name: WordPress/Odoo Integration
 * Description: Integrates WooCommerce with Odoo to validate stock before adding products to the cart.
 * Version: 1.174
 * Author: Mohammad Omar
 *
 * @package Odod
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ODOO_BASE', 'https://almokhlif-oud-live-staging-17381935.dev.odoo.com/' );

// Include REST API integration for Odoo.
require_once plugin_dir_path( __FILE__ ) . 'includes/rest-api.php';
require_once plugin_dir_path( __FILE__ ) . 'failed-orders.php';
require_once plugin_dir_path( __FILE__ ) . 'not-sent-orders.php';
require_once plugin_dir_path( __FILE__ ) . 'odoo.php';

require plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
$anonyengine_update_checker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/MakiOmar/Almoukhlif-Odoo-API/',
	__FILE__,
	plugin_basename( __FILE__ )
);
// Set the branch that contains the stable release.
$anonyengine_update_checker->setBranch( 'master' );

/**
 * Get Odoo authentication token, storing it in a transient for 24 hours.
 *
 * @return string|false The authentication token, or false on failure.
 */
function get_odoo_auth_token() {
	$transient_key = 'odoo_auth_token';
	$token         = get_transient( $transient_key );

	if ( ! $token ) {
		// Odoo API authentication endpoint.
		$auth_url = ODOO_BASE . 'web/session/erp_authenticate';

		// Authentication request body.
		$auth_body = wp_json_encode(
			array(
				'params' => array(
					'db'       => 'almokhlif-oud-live-staging-17381935',
					'login'    => 'test_api@gmail.com',
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
				$token = $auth_data->result->token;
				set_transient( $transient_key, $token, DAY_IN_SECONDS );
				return $token;
			}
		}
	}
	return $token;
}
function check_odoo_stock( $sku, $quantity, $product_id ) {
	$message = 'لا يمكن استرجاع معلومات المخزون. يرجى المحاولة لاحقًا.';

	// Fetch the authentication token.
	$token = get_odoo_auth_token();
	if ( ! $token ) {
		if ( function_exists( 'teamlog' ) ) {
			teamlog( $message );
		}
		return new WP_Error( 'token_error', $message );
	}

	// Odoo API stock endpoint.
	$stock_url = ODOO_BASE . 'api/stock.quant/get_available_qty_data';

	// Prepare the request body for stock data.
	$stock_body = json_encode(
		array(
			'default_code' => $sku,
			'location_id'  => 157,
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
		return new WP_Error( 'stock_api_error', $message );
	}

	$stock_body_response = wp_remote_retrieve_body( $stock_response );
	$stock_data          = json_decode( $stock_body_response );

	if ( ! isset( $stock_data->result->Data ) || ! is_array( $stock_data->result->Data ) ) {
		return new WP_Error( 'stock_data_error', $message );
	}

	// Calculate total positive stock quantity.
	$total_stock = 0;
	foreach ( $stock_data->result->Data as $stock_item ) {
		$q = (int) $stock_item->available_quantity;
		if ( $q > 0 ) {
			$total_stock += $q;
		}
	}

	// Check if the product has a stock multiplier.
	$product    = wc_get_product( $product_id );
	$multiplier = $product->is_type( 'variation' )
		? (float) $product->get_meta( '_stock_multiplier', true )
		: 1;

	$adjusted_quantity = $quantity * $multiplier;
	teamlog( print_r( array( $total_stock, $adjusted_quantity ), true ) );
	// Return stock availability.
	return $total_stock >= $adjusted_quantity;
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
function odoo_check_stock_before_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variation = null ) {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $passed;
	}

	// Get the product and its SKU.
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		wc_add_notice( 'Invalid product.', 'error' );
		return false;
	}

	$sku = $product->get_sku();

	// Determine if this is a variation or simple product.
	$check_id = $variation_id ? $variation_id : $product_id;

	// Use the helper function to check stock with the multiplier.
	$stock_check = check_odoo_stock( $sku, $quantity, $check_id );

	if ( is_wp_error( $stock_check ) ) {
		wc_add_notice( $stock_check->get_error_message(), 'error' );
		return false;
	}

	if ( ! $stock_check ) {
		$message = 'مخزون المنتج محدود. يرجى التواصل مع الدعم للحصول على مزيد من المعلومات.';
		wc_add_notice( $message, 'error' );
		return false; // Prevent adding to cart.
	}

	return $passed; // Allow adding to cart if all checks pass.
}
add_filter( 'woocommerce_add_to_cart_validation', 'odoo_check_stock_before_add_to_cart', 10, 5 );


/**
 * Update stock in WooCommerce based on Odoo stock data for a given SKU or product object.
 *
 * @param string          $sku     The SKU of the product.
 * @param WC_Product|null $product Optional. The product object. If not provided, it will be fetched using the SKU.
 * @return void
 */
function update_odoo_stock( $sku, $product = null ) {
	$token = get_odoo_auth_token();
	if ( ! $token ) {
		if ( function_exists( 'teamlog' ) ) {
			teamlog( 'Failed to update stock in Odoo: Missing authentication token.' );
		} else {
			error_log( 'Failed to update stock in Odoo: Missing authentication token.' );
		}
		return;
	}

	$stock_url    = ODOO_BASE . 'api/stock.quant/get_available_qty_data';
	$request_body = wp_json_encode(
		array(
			'default_code' => $sku,
			'location_id'  => 157,
		)
	);

	$response = wp_remote_post(
		$stock_url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'token'        => $token,
			),
			'body'    => $request_body,
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		if ( function_exists( 'teamlog' ) ) {
			teamlog( 'Failed to update stock in Odoo: ' . $response->get_error_message() );
		} else {
			error_log( 'Failed to update stock in Odoo: ' . $response->get_error_message() );
		}
		return;
	}

	$response_data = json_decode( wp_remote_retrieve_body( $response ) );

	if ( isset( $response_data->result->Data ) && is_array( $response_data->result->Data ) ) {
		$stock_data = $response_data->result->Data;

		// We assume only one relevant entry in Data array for the stock.
		if ( ! empty( $stock_data[0] ) && isset( $stock_data[0]->available_quantity ) ) {
			$forecasted_quantity = (float) $stock_data[0]->available_quantity;

			// Use the provided product object or fetch it by SKU.
			if ( is_null( $product ) ) {
				$product_id = wc_get_product_id_by_sku( $sku );
				$product    = $product_id ? wc_get_product( $product_id ) : null;
			}
			teamlog( $forecasted_quantity );
			if ( $product ) {
				wc_update_product_stock( $product, max( 0, (int) $forecasted_quantity ) ); // Set stock to 0 if negative.
			}
		} elseif ( function_exists( 'teamlog' ) ) {
				teamlog( 'Failed to update stock: Invalid data received from Odoo.' );
		} else {
			error_log( 'Failed to update stock: Invalid data received from Odoo.' );
		}
	} elseif ( function_exists( 'teamlog' ) ) {
			teamlog( 'Failed to update stock: Invalid response format from Odoo.' );
	} else {
		error_log( 'Failed to update stock: Invalid response format from Odoo.' );
	}
}


function send_orders_batch_to_odoo( $order_ids ) {
	if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
		return;
	}

	$token = get_odoo_auth_token();
	if ( ! $token ) {
		if ( function_exists( 'teamlog' ) ) {
			teamlog( 'فشل إرسال الطلبات إلى Odoo: رمز التوثيق غير موجود.' );
		} else {
			error_log( 'فشل إرسال الطلبات إلى Odoo: رمز التوثيق غير موجود.' );
		}
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			update_post_meta( $order_id, 'oodo-status', 'failed' );
			$error_message = 'فشل إرسال الطلب إلى أودو: رد غير متوقع.';
			$order->add_order_note( $error_message, false );
		}
		return false;
	}

	$orders_data = array();
	$orders_temp = array();

	foreach ( $order_ids as $order_id ) {
		$odoo_order = get_post_meta( $order_id, 'odoo_order', true );
		if ( ! empty( $odoo_order ) && is_numeric( $odoo_order ) ) {
			continue;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}

		// **Validate Billing Details**
		$billing_fields = array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'address_1'  => $order->get_billing_address_1(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
		);

		$missing_fields = array();
		foreach ( $billing_fields as $field => $value ) {
			if ( empty( $value ) ) {
				// $missing_fields[] = ucfirst( str_replace( '_', ' ', $field ) );
			}
		}

		if ( ! empty( $missing_fields ) ) {
			$missing_fields_text = implode( ', ', $missing_fields );
			update_post_meta( $order->get_id(), 'oodo-status', 'failed' );
			$order->add_order_note( "لم يتم إرسال الطلب إلى أودو بسبب نقص في بيانات الفوترة: $missing_fields_text.", false );
			continue; // Skip this order
		}
		$order_status  = $order->get_status();
		$orders_temp[] = $order;
		$order_data    = array(
			'woo_commerce_id' => $order->get_id(),
			'manual_confirm'  => false,
			'note'            => $order->get_customer_note(),
			'state'           => 'draft',
			'billing'         => $billing_fields,
			'order_line'      => array(),
			'payment_method'  => $order->get_payment_method_title(),
			'wc_order_status' => wc_get_order_statuses()[ "wc-$order_status" ],
		);

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$product    = $item->get_product();
			$product_id = $product->get_id();
			$multiplier = 1;

			if ( $product->is_type( 'variation' ) ) {
				$multiplier = (float) get_post_meta( $product_id, '_stock_multiplier', true );
				$multiplier = $multiplier > 0 ? $multiplier : 1;
			}

			$quantity   = $item->get_quantity() * $multiplier;
			$unit_price = $item->get_total() / $quantity;

			$order_data['order_line'][] = array(
				'default_code'    => $product->get_sku(),
				'name'            => $item->get_name(),
				'product_uom_qty' => $quantity,
				'price_unit'      => $unit_price + ( $unit_price * 0.15 ),
			);
		}

		$order_data['order_line'][] = array(
			'default_code'    => '1000000',
			'name'            => 'شحن',
			'product_uom_qty' => 1,
			'price_unit'      => $order->get_shipping_total(),
		);

		foreach ( $order->get_items( 'fee' ) as $fee_id => $fee ) {
			$order_data['fees'][] = array(
				'name'  => $fee->get_name(),
				'total' => $fee->get_amount(),
			);
		}

		$orders_data['orders'][] = $order_data;
	}

	if ( empty( $orders_data ) ) {
		return;
	}

	$odoo_api_url = ODOO_BASE . 'api/sale.order/add_update_order';

	$response = wp_remote_post(
		$odoo_api_url,
		array(
			'method'  => 'POST',
			'body'    => wp_json_encode( $orders_data ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'token'        => $token,
			),
			'timeout' => 30,
		)
	);

	$response_body = wp_remote_retrieve_body( $response );
	$response_data = json_decode( $response_body );

	if ( is_wp_error( $response ) || empty( $response_data ) || ! isset( $response_data->result->Code ) || 200 !== $response_data->result->Code ) {
		if ( function_exists( 'teamlog' ) ) {
			teamlog( 'فشل إرسال الطلبات إلى Odoo: رد غير متوقع.' );
		} else {
			error_log( 'فشل إرسال الطلبات إلى Odoo: رد غير متوقع.' );
		}
		foreach ( $orders_temp as $order ) {
			update_post_meta( $order->get_id(), 'oodo-status', 'failed' );
			$error_message = 'فشل إرسال الطلب إلى أودو: رد غير متوقع.';
			$order->add_order_note( $error_message, false );
		}
		return;
	} elseif ( ! empty( $response_data ) && isset( $response_data->result->Code ) && 200 === $response_data->result->Code ) {

		// Loop through the response Data array
		if ( isset( $response_data->result->Data ) && is_array( $response_data->result->Data ) ) {
			foreach ( $response_data->result->Data as $data ) {
				if ( isset( $data->ID ) && $data->ID === false && isset( $data->StatusDescription ) && $data->StatusDescription === 'Failed' && isset( $data->woo_commerce_id ) ) {

					// Update post meta with failed status
					update_post_meta( $data->woo_commerce_id, 'oodo-status', 'failed' );

					// Add order note with Arabic message if exists
					if ( isset( $data->ArabicMessage ) ) {
						$order = wc_get_order( $data->woo_commerce_id );
						if ( $order ) {
							$order->add_order_note( $data->ArabicMessage, false );
						}
					}
				}
			}
		}
	}

	foreach ( $response_data->result->Data as $odoo_order ) {
		if ( $odoo_order->woo_commerce_id ) {
			$order_id = $odoo_order->woo_commerce_id;
			update_post_meta( $order_id, 'odoo_order', $odoo_order->ID );
			update_post_meta( $order_id, 'odoo_order_number', $odoo_order->Number );
			update_post_meta( $order_id, 'oodo-status', 'success' );
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->add_order_note( "تم إرسال الطلب بنجاح إلى أودو برقم أودو ID: {$odoo_order->ID}.", false );
				foreach ( $order->get_items( 'line_item' ) as $item ) {
					$product = $item->get_product();
					if ( $product ) {
						$sku = $product->get_sku();
						update_odoo_stock( $sku, $product );
					}
				}
			} else {
				teamlog( 'Order not found' );
			}
		} else {
			teamlog(
				array(
					'error'    => 'No woo_commerce_id',
					'response' => $odoo_order,
				)
			);
		}
	}
}


/**
 * Display Odoo Order ID under the billing details in the admin order page.
 *
 * @param WC_Order $order The order object.
 */
function display_odoo_order_id_in_admin( $order ) {
	$odoo_order_id     = get_post_meta( $order->get_id(), 'odoo_order', true );
	$odoo_order_number = get_post_meta( $order->get_id(), 'odoo_order_number', true );

	if ( $odoo_order_id ) {
		echo '<p><strong>' . __( 'Odoo Order ID:', 'text-domain' ) . '</strong> ' . esc_html( $odoo_order_id ) . '</p>';
		echo '<p><strong>' . __( 'Odoo Order Number:', 'text-domain' ) . '</strong> ' . esc_html( $odoo_order_number ) . '</p>';
	}
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_odoo_order_id_in_admin', 10, 1 );

add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		send_orders_batch_to_odoo( array( $order_id ) );
	}
);
add_action(
	'woocommerce_checkout_phone_order_processed',
	function ( $order_id ) {
		send_orders_batch_to_odoo( array( $order_id ) );
	}
);

add_action(
	'woocommerce_process_shop_order_meta',
	function ( $order_id ) {
		send_orders_batch_to_odoo( array( $order_id ) );
	},
	99
);

/**
 * Cancel an order in Odoo.
 *
 * @param int $odoo_order_id The Odoo Order ID to cancel.
 * @param int $order_id The WooCommerce order ID.
 * @return void
 */
function cancel_odoo_order( $odoo_order_id, $order_id ) {
	// Get the WooCommerce order object.
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// Fetch Odoo authentication token.
	$token = get_odoo_auth_token();
	if ( ! $token ) {
		$order->add_order_note( 'فشل في إلغاء الطلب في Odoo: رمز التوثيق غير موجود.', false );
		return;
	}

	// Odoo API endpoint for canceling an order.
	$cancel_url   = ODOO_BASE . 'api/sale.order/cancel_order';
	$request_body = wp_json_encode(
		array(
			'orders' => array(
				array( 'RequestID' => (string) $odoo_order_id ),
			),
		)
	);

	// Send the cancellation request to Odoo API.
	$response = wp_remote_post(
		$cancel_url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'token'        => $token,
			),
			'body'    => $request_body,
			'timeout' => 20,
		)
	);

	// Handle the response.
	if ( is_wp_error( $response ) ) {
		$order->add_order_note( 'فشل في إلغاء الطلب في Odoo: ' . $response->get_error_message(), false );
		return;
	}

	$response_data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( isset( $response_data['result']['Code'] ) && 200 === $response_data['result']['Code'] ) {
		// Log success as a private note.
		$order->add_order_note( "تم إلغاء الطلب بنجاح في Odoo برقم: $odoo_order_id", false );
	} else {
		// Log failure as a private note.
		$order->add_order_note( "فشل في إلغاء الطلب في Odoo برقم: $odoo_order_id. الرد: " . wp_remote_retrieve_body( $response ), false );
	}
}


/**
 * إرسال الطلبات المكتملة إلى واجهة Odoo API للتحقق من التسليم.
 *
 * @param int $order_id رقم تعريف الطلب المكتمل في WooCommerce.
 */
function snks_validate_order_delivery_on_completion( $order_id ) {
	// جلب الطلب في WooCommerce.
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	// جلب رقم الطلب في Odoo من الميتا الخاصة بالطلب في WooCommerce.
	$odoo_order_id = get_post_meta( $order_id, 'odoo_order', true );

	if ( ! $odoo_order_id ) {
		$order->add_order_note( "لم يتم العثور على رقم الطلب في Odoo للطلب رقم: $order_id", false );
		return;
	}

	// إعداد البيانات لإرسالها إلى واجهة Odoo API.
	$data = array(
		'orders' => array(
			array(
				'RequestID' => (string) $odoo_order_id,
			),
		),
	);

	// جلب رمز التوثيق من Odoo.
	$token = get_odoo_auth_token();
	if ( ! $token ) {
		$order->add_order_note( 'فشل في إرسال البيانات إلى واجهة Odoo API: رمز التوثيق غير موجود.', false );
		return;
	}

	// رابط واجهة Odoo API.
	$url = ODOO_BASE . 'api/sale.order/validate_order_delivery';

	// إرسال البيانات إلى Odoo API باستخدام wp_remote_post.
	$response = wp_remote_post(
		$url,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'token'        => $token,
			),
			'body'    => wp_json_encode( $data ),
			'timeout' => 20,
		)
	);

	// معالجة الرد.
	if ( is_wp_error( $response ) ) {
		$order->add_order_note( 'خطأ أثناء إرسال البيانات إلى واجهة Odoo API: ' . $response->get_error_message(), false );
		return;
	}

	$response_body = wp_remote_retrieve_body( $response );
	$response_data = json_decode( $response_body, true );

	if ( isset( $response_data['result']['status'] ) && 'success' === $response_data['result']['status'] ) {
		// تسجيل رسالة النجاح.
		$message = $response_data['result']['message'] ?? 'تم التحقق من عملية تسليم الطلب بنجاح.';
		$order->add_order_note( "نجاح واجهة Odoo API: $message", false );
	} else {
		// تسجيل الفشل مع الرد الكامل للتصحيح.
		$order->add_order_note( "فشل في التحقق من تسليم الطلب في Odoo للطلب رقم: $odoo_order_id. الرد: $response_body", false );
	}
}


/**
 * Hook to cancel Odoo order when WooCommerce order status changes to cancelled.
 */
add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $old_status, $new_status ) {
		// Check if the new status is 'cancelled'.
		if ( 'cancelled' === $new_status || 'was-canceled' === $new_status || 'wc-cancelled' === $new_status ) {
			$odoo_order_id = get_post_meta( $order_id, 'odoo_order', true );
			if ( $odoo_order_id ) {
				cancel_odoo_order( $odoo_order_id, $order_id );
			}
		}

		if ( 'international-shi' === $new_status || 'was-shipped' === $new_status ) {
			snks_validate_order_delivery_on_completion( $order_id );
		}
	},
	10,
	3 // Number of arguments passed to the callback (order ID, old status, new status).
);


add_filter( 'woocommerce_add_to_cart_validation', 'odoo_check_stock_before_add_to_cart', 10, 5 );
add_action(
	'wpo_before_load_items',
	function ( $request ) {
		// Ensure the request contains items.
		if ( ! empty( $request['items'] ) ) {
			foreach ( $request['items'] as $item ) {
				$product_id = $item['id'];
				$quantity   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;

				// Get the product object.
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					echo json_encode( array( 'error' => 'Invalid product ID.' ) );
					die();
				}
				// Determine if this is a variation or simple product.
				$sku = $product->get_sku();

				if ( $product->is_type( 'variation' ) ) {
					// For variations, use the variation itself.
					$variation_id = $product_id;
				} else {
					// For simple products, use the product ID.
					$variation_id = $product_id;
				}

				// Use the helper function to check stock with the multiplier.
				$stock_check = check_odoo_stock( $sku, $quantity, $variation_id );

				if ( is_wp_error( $stock_check ) ) {
					echo json_encode(
						array(
							'error' => $stock_check->get_error_message(),
						)
					);
					die();
				}

				if ( ! $stock_check ) {
					echo json_encode(
						array(
							'error' => 'مخزون المنتج غير متوفر بالكمية المطلوبة. يرجى تعديل الكمية أو اختيار منتج آخر.',
						)
					);
					die();
				}
			}
		}
	}
);


add_action(
	'wpo_wcpdf_before_order_data',
	function ( $type, $order ) {
		$odoo = get_post_meta( $order->get_id(), 'odoo_order_number', true );
		if ( ! $odoo && $odoo === '' ) {
			return;
		}
		?>
	<tr class="odoo-number">
		<th><?php _e( 'رقم أودو:', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
		<td><?php echo $odoo; ?></td>
	</tr>
		<?php
	},
	10,
	2
);
