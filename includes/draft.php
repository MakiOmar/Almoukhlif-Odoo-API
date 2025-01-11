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