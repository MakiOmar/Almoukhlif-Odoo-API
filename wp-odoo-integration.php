<?php
/**
 * Plugin Name: WordPress/Odoo Integration
 * Description: Integrates WooCommerce with Odoo to validate stock before adding products to the cart.
 * Version: 1.13
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
		// Replace with actual API credentials and URL.
		$response = wp_remote_post(
			ODOO_AUTH_URL,
			array(
				'body'    => array(
					'username' => ODOO_USERNAME,
					'password' => ODOO_PASSWORD,
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['token'] ) ) {
			$token = $data['token'];
			set_transient( $transient_key, $token, DAY_IN_SECONDS );
		} else {
			return false;
		}
	}

	return $token;
}



/**
 * Send order payment details to an external API after order status changes to `odoo_transfered`.
 *
 * This function triggers when an order status changes to `odoo_transfered`, gathers order details,
 * and sends them to the specified external API endpoint in JSON format.
 *
 * @param int $order_id   The ID of the order.
 */
function send_order_details_to_odoo( $order_id ) {
	// التحقق مما إذا كان الطلب قد تم إرساله بالفعل إلى Odoo.
	$odoo_order = get_post_meta( $order_id, 'odoo_order', true );

	if ( ! empty( $odoo_order ) && is_numeric( $odoo_order ) ) {
		return;
	}

	// الحصول على رمز التوثيق من Odoo.
	$token = get_odoo_auth_token();
	if ( ! $token ) {
		$message = "فشل إرسال الطلب {$order_id} إلى Odoo: رمز التوثيق غير موجود.";
		$order   = wc_get_order( $order_id );
		$order->add_order_note( $message, false ); // تسجيل الخطأ كملاحظة للطلب.
		update_post_meta( $order_id, 'oodo-status', 'failed' );
		return false;
	}

	// جلب تفاصيل الطلب.
	$order = wc_get_order( $order_id );

	// إعداد بيانات الطلب لإرسالها إلى أودو.
	$order_data = array(
		'manual_confirm' => true,
		'state'          => 'draft',
		'order_line'     => array(),
		'order_id'       => $order->get_id(),
		'total'          => $order->get_total(),
		'currency'       => $order->get_currency(),
		'note'           => $order->get_customer_note(),
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

	// إضافة عناصر الطلب (المنتجات) إلى بيانات الطلب.
	foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
		$product    = $item->get_product();
		$product_id = $product->get_id();
		$multiplier = 1;

		// إذا كان المنتج نوعه "متغير"، يتم تطبيق المضاعف.
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

	// إضافة تكلفة الشحن إلى بيانات الطلب.
	$order_data['order_line'][] = array(
		'default_code'    => '1000000',
		'name'            => 'شحن',
		'product_uom_qty' => 1,
		'price_unit'      => $order->get_shipping_total(),
	);

	// إضافة الرسوم الأخرى إلى بيانات الطلب.
	foreach ( $order->get_items( 'fee' ) as $fee_id => $fee ) {
		$order_data['fees'][] = array(
			'name'  => $fee->get_name(),
			'total' => $fee->get_amount(),
		);
	}

	// رابط API الخاص بـ أودو.
	$odoo_api_url = ODOO_BASE . 'api/sale.order/add_update_order';

	// إرسال البيانات إلى أودو باستخدام wp_remote_post.
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

	// معالجة الرد من أودو.
	$order_body_response = wp_remote_retrieve_body( $response );
	$order_body          = json_decode( $order_body_response );

	// التحقق من وجود خطأ في الرد.
	if ( ! $response || is_wp_error( $response ) ) {
		$error_message = 'فشل إرسال الطلب إلى أودو: ' . $response->get_error_message();
		$order->add_order_note( $error_message, false ); // تسجيل الخطأ كملاحظة للطلب.
		update_post_meta( $order_id, 'oodo-status', 'failed' );
		return;
	}

	// التحقق من نجاح الرد.
	if ( isset( $order_body->result->Code ) && 200 === $order_body->result->Code ) {
		$odoo_order_id = $order_body->result->Data->ID;
		update_post_meta( $order_id, 'odoo_order', $odoo_order_id );
		$success_message = "تم إرسال الطلب بنجاح إلى أودو برقم أودو ID: {$odoo_order_id}.";
		$order->add_order_note( $success_message, false ); // تسجيل النجاح كملاحظة للطلب.
		update_post_meta( $order_id, 'oodo-status', 'success' );
	} else {
		$error_message = 'فشل إرسال الطلب إلى أودو: رد غير متوقع.';
		$order->add_order_note( $error_message, false ); // تسجيل الخطأ كملاحظة للطلب.
		update_post_meta( $order_id, 'oodo-status', 'failed' );
	}
}

add_action( 'woocommerce_thankyou', 'send_order_details_to_odoo' );


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
