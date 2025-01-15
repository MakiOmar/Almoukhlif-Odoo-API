<?php
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
			'location_id'  => 104,
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
		'woo_commerce_id' => $order->get_id(),
		'manual_confirm'  => true,
		'note'            => $order->get_customer_note(),
		'state'           => 'draft',
		'order_line'      => array(),
		'total'           => $order->get_total(),
		'currency'        => $order->get_currency(),
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
		'fees'            => array(),
		'tax'             => $order->get_total_tax(),
		'discount'        => $order->get_discount_total(),
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