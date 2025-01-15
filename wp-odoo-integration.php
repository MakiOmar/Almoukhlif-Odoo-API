<?php
/**
 * Plugin Name: WordPress/Odoo Integration
 * Description: Integrates WooCommerce with Odoo to validate stock before adding products to the cart.
 * Version: 1.166
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

function send_orders_batch_to_odoo( $order_ids ) {
	// تحقق إذا لم يتم تمرير أي أرقام طلبات.
	if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
		return;
	}

	// الحصول على رمز التوثيق من Odoo.
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
			$order->add_order_note( $error_message, false ); // تسجيل الخطأ كملاحظة للطلب.
		}
		return false;
	}

	// إعداد مصفوفة الطلبات لإرسالها إلى Odoo.
	$orders_data = array();

	$orders_temp = array();

	foreach ( $order_ids as $order_id ) {
		// التحقق مما إذا كان الطلب قد تم إرساله بالفعل إلى Odoo.
		$odoo_order = get_post_meta( $order_id, 'odoo_order', true );
		if ( ! empty( $odoo_order ) && is_numeric( $odoo_order ) ) {
			continue;
		}

		// جلب تفاصيل الطلب.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}
		$orders_temp[] = $order;
		$order_data    = array(
			'woo_commerce_id' => $order->get_id(),
			'manual_confirm'  => true,
			'note'            => $order->get_customer_note(),
			'state'           => 'draft',
			'billing'         => array(
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
			'order_line'      => array(),
			//'location_id'     => '',
		);

		// إضافة عناصر الطلب (المنتجات) إلى بيانات الطلب.
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

		// إضافة تكلفة الشحن.
		$order_data['order_line'][] = array(
			'default_code'    => '1000000',
			'name'            => 'شحن',
			'product_uom_qty' => 1,
			'price_unit'      => $order->get_shipping_total(),
		);

		// إضافة الرسوم الأخرى.
		foreach ( $order->get_items( 'fee' ) as $fee_id => $fee ) {
			$order_data['fees'][] = array(
				'name'  => $fee->get_name(),
				'total' => $fee->get_amount(),
			);
		}

		$orders_data['orders'][] = $order_data;
	}

	// إذا لم يكن هناك أي بيانات لإرسالها.
	if ( empty( $orders_data ) ) {
		return;
	}

	// رابط API الخاص بـ أودو.
	$odoo_api_url = ODOO_BASE . 'api/sale.order/add_update_order';

	// إرسال البيانات إلى أودو باستخدام wp_remote_post.
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

	// معالجة الرد من أودو.
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
			$order->add_order_note( $error_message, false ); // تسجيل الخطأ كملاحظة للطلب.
		}
		return;
	}
	// تحديث الطلبات الناجحة.
	foreach ( $response_data->result->Data as $odoo_order ) {
		$order_id = $odoo_order->woo_commerce_id;
		update_post_meta( $order_id, 'odoo_order', $odoo_order->ID );
		update_post_meta( $order_id, 'oodo-status', 'success' );
		$order = wc_get_order( $order_id );
		$order->add_order_note( "تم إرسال الطلب بنجاح إلى أودو برقم أودو ID: {$odoo_order->ID}.", false );
	}
}

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

add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		send_orders_batch_to_odoo( array( $order_id ) );
	}
);
