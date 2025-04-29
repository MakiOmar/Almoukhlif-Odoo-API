<?php

/**
 * Plugin Name: WordPress/Odoo Integration
 * Description: Integrates WooCommerce with Odoo to validate stock before adding products to the cart.
 * Version: 1.224
 * Author: Mohammad Omar
 *
 * @package Odoo
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}
$opts = get_option('Anony_Options');
if (is_array($opts)) {
    define('ODOO_BASE', $opts['odoo_url']);
    define('ODOO_DATABASE', $opts['odoo_database']);
    define('ODOO_LOGIN', $opts['odoo_username']);
    define('ODOO_PASS', $opts['odoo_pass']);
    define('ODOO_LOCATION', absint($opts['odoo_location']));
} else {
    define('ODOO_BASE', '');
    define('ODOO_DATABASE', '');
    define('ODOO_LOGIN', '');
    define('ODOO_PASS', '');
    define('ODOO_LOCATION', '');
}

// Include REST API integration for Odoo.
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';
require_once plugin_dir_path(__FILE__) . 'failed-orders.php';
require_once plugin_dir_path(__FILE__) . 'not-sent-orders.php';
require_once plugin_dir_path(__FILE__) . 'not-sent-orders-all.php';
require_once plugin_dir_path(__FILE__) . 'odoo.php';

require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
$anonyengine_update_checker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/MakiOmar/Almoukhlif-Odoo-API/',
    __FILE__,
    plugin_basename(__FILE__)
);
// Set the branch that contains the stable release.
$anonyengine_update_checker->setBranch('master');

/**
 * Get the total coupon discount from the applied coupons array.
 *
 * @param array $applied_coupons List of applied coupons.
 * @return float Total coupon discount.
 */
function odoo_get_total_coupon_discount($applied_coupons)
{
    $total_discount = 0.0;

    if (is_array($applied_coupons)) {
        foreach ($applied_coupons as $coupon) {
            if (isset($coupon['coupon_discount'])) {
                $total_discount += (float) $coupon['coupon_discount'];
            }
        }
    }

    return $total_discount;
}
/**
 * Get Odoo authentication token, storing it in a transient for 24 hours.
 *
 * @return string|false The authentication token, or false on failure.
 */
function get_odoo_auth_token()
{
    $transient_key = 'odoo_auth_token';
    $token         = get_transient($transient_key);

    if (! $token) {
        // Odoo API authentication endpoint.
        $auth_url = ODOO_BASE . 'web/session/erp_authenticate';

        // Authentication request body.
        $auth_body = wp_json_encode(
            array(
                'params' => array(
                    'db'       => ODOO_DATABASE,
                    'login'    => ODOO_LOGIN,
                    'password' => ODOO_PASS,
                ),
            )
        );

        // Send the authentication request.
        $auth_response = wp_remote_post(
            $auth_url,
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => $auth_body,
                'timeout' => 20,
            )
        );

        // Check for errors in the response.
        if (! is_wp_error($auth_response)) {
            $auth_body_response = wp_remote_retrieve_body($auth_response);
            $auth_data          = json_decode($auth_body_response);
            // Check if the token exists in the response.
            if (isset($auth_data->result->token)) {
                $token = $auth_data->result->token;
                set_transient($transient_key, $token, DAY_IN_SECONDS);
                return $token;
            }
        }
    }
    return $token;
}
function check_odoo_stock($sku, $quantity, $product_id)
{
    $message = 'لا يمكن استرجاع معلومات المخزون. يرجى المحاولة لاحقًا.';

    // Fetch the authentication token.
    $token = get_odoo_auth_token();
    if (! $token) {
        if (function_exists('teamlog')) {
            teamlog($message);
        }
        return new WP_Error('token_error', $message);
    }

    // Odoo API stock endpoint.
    $stock_url = ODOO_BASE . 'api/stock.quant/get_available_qty_data';

    // Prepare the request body for stock data.
    $stock_body = json_encode(
        array(
            'default_code' => $sku,
            'location_id'  => ODOO_LOCATION,
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

    if (is_wp_error($stock_response)) {
        delete_transient('odoo_auth_token');
        return new WP_Error('stock_api_error', $message);
    }

    $stock_body_response = wp_remote_retrieve_body($stock_response);
    $stock_data          = json_decode($stock_body_response);

    if (! isset($stock_data->result->Data) || ! is_array($stock_data->result->Data)) {
        return new WP_Error('stock_data_error', $message);
    }

    // Calculate total positive stock quantity.
    $total_stock = 0;
    foreach ($stock_data->result->Data as $stock_item) {
        $q = (int) $stock_item->available_quantity;
        if ($q > 0) {
            $total_stock += $q;
        }
    }

    // Check if the product has a stock multiplier.
    $product    = wc_get_product($product_id);
    $multiplier = $product->is_type('variation')
        ? (float) $product->get_meta('_stock_multiplier', true)
        : 1;

    $adjusted_quantity = $quantity * $multiplier;
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
function odoo_check_stock_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variation = null)
{
    // Check if WooCommerce is active.
    if (! class_exists('WooCommerce')) {
        return $passed;
    }

    // Get the product and its SKU.
    $product = wc_get_product($product_id);
    if (! $product) {
        wc_add_notice('Invalid product.', 'error');
        return false;
    }

    $sku = $product->get_sku();

    // Determine if this is a variation or simple product.
    $check_id = $variation_id ? $variation_id : $product_id;

    // Use the helper function to check stock with the multiplier.
    $stock_check = check_odoo_stock($sku, $quantity, $check_id);

    if (is_wp_error($stock_check)) {
        wc_add_notice($stock_check->get_error_message(), 'error');
        return false;
    }

    if (! $stock_check) {
        $message = 'مخزون المنتج محدود. يرجى التواصل مع الدعم للحصول على مزيد من المعلومات.';
        wc_add_notice($message, 'error');
        return false; // Prevent adding to cart.
    }

    return $passed; // Allow adding to cart if all checks pass.
}
add_filter('woocommerce_add_to_cart_validation', 'odoo_check_stock_before_add_to_cart', 10, 5);


/**
 * Update stock in WooCommerce based on Odoo stock data for a given SKU or product object.
 *
 * @param string          $sku     The SKU of the product.
 * @param WC_Product|null $product Optional. The product object. If not provided, it will be fetched using the SKU.
 * @return void
 */
function update_odoo_stock($sku, $product = null)
{
    $token = get_odoo_auth_token();
    if (! $token) {
        if (function_exists('teamlog')) {
            teamlog('Failed to update stock in Odoo: Missing authentication token.');
        } else {
            error_log('Failed to update stock in Odoo: Missing authentication token.');
        }
        return;
    }

    $stock_url    = ODOO_BASE . 'api/stock.quant/get_available_qty_data';
    $request_body = wp_json_encode(
        array(
            'default_code' => $sku,
            'location_id'  => ODOO_LOCATION,
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

    if (is_wp_error($response)) {
        if (function_exists('teamlog')) {
            teamlog('Failed to update stock in Odoo: ' . $response->get_error_message());
        } else {
            error_log('Failed to update stock in Odoo: ' . $response->get_error_message());
        }
        return;
    }

    $response_data = json_decode(wp_remote_retrieve_body($response));

    if (isset($response_data->result->Data) && is_array($response_data->result->Data)) {
        $stock_data = $response_data->result->Data;

        // We assume only one relevant entry in Data array for the stock.
        if (! empty($stock_data[0]) && isset($stock_data[0]->available_quantity)) {
            $forecasted_quantity = (float) $stock_data[0]->available_quantity;

            // Use the provided product object or fetch it by SKU.
            if (is_null($product)) {
                $product_id = wc_get_product_id_by_sku($sku);
                $product    = $product_id ? wc_get_product($product_id) : null;
            }
            if ($product) {
                wc_update_product_stock($product, max(0, (int) $forecasted_quantity)); // Set stock to 0 if negative.
            }
        } elseif (function_exists('teamlog')) {
            teamlog('Failed to update stock: Invalid data received from Odoo.');
        } else {
            error_log('Failed to update stock: Invalid data received from Odoo.');
        }
    } elseif (function_exists('teamlog')) {
        teamlog('Failed to update stock: Invalid response format from Odoo.');
    } else {
        error_log('Failed to update stock: Invalid response format from Odoo.');
    }
}
function item_gifts($item_id, $item, &$order_data, &$discount)
{
    $value_unserialized = maybe_unserialize(wc_get_order_item_meta($item_id, '_ywapo_meta_data'));
    $is_gift            = wc_get_order_item_meta($item_id, '_fgf_gift_product');
    $gifts_total        = 0;

    // Get order ID and order details
    $order           = wc_get_order($item['order_id']);
    $billing_country = $order ? $order->get_billing_country() : '';
    $is_gulf = is_gulf_country( $billing_country );

    if ($value_unserialized && empty($is_gift)) {
        $counted = count($value_unserialized);
        for ($x = 0; $x < $counted; $x++) {
            $desired_index = array_key_first($value_unserialized[$x]);
            $addon_id      = explode('-', $value_unserialized[0][$desired_index][0]);

            if ('product' === $addon_id[0]) {
                $product = wc_get_product($addon_id[1]);

                $variation      = new WC_Product_Variation($item['variation_id']);
                $variation_name = implode(' / ', $variation->get_variation_attributes());
                $variation_name = urldecode(str_replace('-', ' ', $variation_name));

                $product_name  = $product->get_name();
                $product_sku   = $product->get_sku();
                $multiplier = 1;

                if ($product->is_type('variation')) {
                    $multiplier = (float) get_post_meta($addon_id[1], '_stock_multiplier', true);
                    $multiplier = !empty( $multiplier ) && $multiplier > 0 ? $multiplier : 1;
                }
                $product_qty   = wc_get_order_item_meta($item_id, '_qty');

                $quantity      = $product_qty * $multiplier;
                if ( $multiplier == 1 ) {
                    $unit_price = $product->get_price();
                } else {
                    $unit_price = $product->get_price() / $quantity;
                }
                $product_price = $unit_price;
                $gifts_total  += $product_price * $quantity;

                // Check if customer is from a Gulf country (except Saudi Arabia) and adjust price
                $final_price = $is_gulf ? $product_price : $product_price + ($product_price * 0.15);

                if ($product_price > 0) {
                    $order_data['order_line'][] = array(
                        'default_code'    => $product_sku,
                        'name'            => $product_name,
                        'product_uom_qty' => $item['quantity'],
                        'price_unit'      => $final_price,
                        'discount'        => $discount > 0 ? $discount : 0,
                    );
                }
            }
        }
    }
    return $gifts_total;
}
function is_gulf_country( $billing_country ){
    // Define Gulf countries excluding Saudi Arabia
    $gulf_countries = array('AE', 'BH', 'KW', 'OM', 'QA'); // UAE, Bahrain, Kuwait, Oman, Qatar
    return in_array($billing_country, $gulf_countries);
}
function process_odoo_order($order_ids, &$orders_data, &$orders_temp, $update = false)
{
    foreach ($order_ids as $order_id) {
        $odoo_order = get_post_meta($order_id, 'odoo_order', true);
        if (! empty($odoo_order) && is_numeric($odoo_order) && ! $update) {
            update_post_meta($order_id, 'oodo-status', 'success');
            continue;
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            continue;
        }

        $applied_coupons = array();
        // Loop through coupon items for this order
        foreach ($order->get_items('coupon') as $item) {
            $coupon_data = array(
                'coupon_name'         => $item->get_name(), // Coupon name
                'coupon_code'         => $item->get_code(), // Coupon code
                'coupon_discount'     => $item->get_discount(), // Discount amount
                'coupon_discount_tax' => $item->get_discount_tax(), // Discount tax amount
            );

            $coupon = new WC_Coupon($coupon_data['coupon_code']); // Get the WC_Coupon object

            $coupon_data['coupon_discount_type'] = $coupon->get_discount_type(); // Coupon discount type
            $applied_coupons[]                   = $coupon_data;
        }
        $applied_coupons_discount    = odoo_get_total_coupon_discount($applied_coupons);
        $billing_billing_company_vat = get_post_meta($order->get_id(), 'billing_billing_company_vat', true);
        $billing_short_address       = get_post_meta($order->get_id(), 'billing_short_address', true);
        $billing_address_second      = get_post_meta($order->get_id(), 'billing_address_second', true);
        $billing_building_number     = get_post_meta($order->get_id(), 'billing_building_number', true);
        $billing_district            = get_post_meta($order->get_id(), 'billing_district', true);
        if (! $billing_billing_company_vat || empty($billing_billing_company_vat)) {
            $postcode = $order->get_billing_postcode();
        } else {
            $postcode = get_post_meta($order->get_id(), 'billing_postal_code', true);
        }
        // **Validate Billing Details**
        $billing_fields = array(
            'first_name'      => $order->get_billing_first_name(),
            'last_name'       => $order->get_billing_last_name(),
            'address_1'       => $order->get_billing_address_1(),
            'city'            => $order->get_billing_city(),
            'state'           => $order->get_billing_state(),
            'postcode'        => $postcode,
            'company_vat'     => $billing_billing_company_vat,
            'short_address'   => $billing_short_address,
            'address_second'  => $billing_address_second,
            'building_number' => $billing_building_number,
            'district'        => $billing_district,
            'country'         => $order->get_billing_country(),
            'email'           => $order->get_billing_email(),
            'phone'           => $order->get_billing_phone(),
        );

        $order_status  = $order->get_status();
        $orders_temp[] = $order;
        // Get order billing country
        $billing_country = $order->get_billing_country();
        $is_gulf = is_gulf_country( $billing_country );
        $order_data    = array(
            'manual_confirm'  => false,
            'note'            => $order->get_customer_note(),
            'state'           => 'draft',
            'billing'         => $billing_fields,
            'order_line'      => array(),
            'payment_method'  => $order->get_payment_method_title(),
            'wc_order_status' => wc_get_order_statuses()["wc-$order_status"],
            'is_vat_exmpt'    => $is_gulf,
            'created_date'    => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
        );
        if ($update) {
            $order_data['RequestID'] = $odoo_order;
        } else {
            $order_data['woo_commerce_id'] = $order->get_id();
        }
        $line_items = $order->get_items('line_item');
        $items_count = count($line_items);
        $item_discount = $applied_coupons_discount / $items_count;
        $discount       = 0;
        
        foreach ($line_items as $item_id => $item) {
            $product    = $item->get_product();
            $product_id = $product->get_id();
            $multiplier = 1;

            if ($product->is_type('variation')) {
                $multiplier = (float) get_post_meta($product_id, '_stock_multiplier', true);
                $multiplier = $multiplier > 0 ? $multiplier : 1;
            }
            // Calculate discount percentage
            $line_subtotal = $item->get_subtotal();
            $line_total = $item->get_total();
            $discount_percent = ($line_subtotal > 0)
                ? round((($line_subtotal - $line_total) / $line_subtotal) * 100, 2)
                : 100;
            $gifts_total    = item_gifts($item_id, $item, $order_data, $discount_percent);
            $quantity       = $item->get_quantity() * $multiplier;
            $unit_price     = ( $product->get_price() * $item->get_quantity() ) / $quantity;

            // Check if the customer is from a Gulf country (except Saudi Arabia) and adjust the price
            $final_price = $is_gulf ? $unit_price : $unit_price + ($unit_price * 0.15);
            $order_data['order_line'][] = array(
                'default_code'    => $product->get_sku(),
                'name'            => $item->get_name(),
                'product_uom_qty' => $quantity,
                'price_unit'      => $final_price,
                'discount'        => $discount_percent > 0 ? $discount_percent : 0,
            );
            if ($item->get_total() < 1) {
                $discount += $product->get_price() * $item->get_quantity();
            }
        }

        // Handle shipping cost
        $shipping_cost = $order->get_shipping_total();
        if ($shipping_cost > 0) {
            // Check if 15% should be applied
            $final_shipping_price = $is_gulf ? $shipping_cost : $shipping_cost + ($shipping_cost * 0.15);

            $order_data['order_line'][] = array(
                'default_code'    => '1000000',
                'name'            => 'شحن',
                'product_uom_qty' => 1,
                'price_unit'      => $final_shipping_price,
            );
        }

        foreach ($order->get_items('fee') as $fee_id => $fee) {
            $order_data['fees'][] = array(
                'name'  => $fee->get_name(),
                'total' => $fee->get_amount(),
            );
        }
        $order_data['discount']  = $applied_coupons_discount + $discount;
        $orders_data['orders'][] = $order_data;
    }
    teamlog(print_r($orders_data, true));
}
function process_response($response, $response_data, $orders_temp, $update = false)
{
    if (function_exists('teamlog')) {
        teamlog('Processed response: ' . print_r($response, true));
    } else {
        error_log('Processed response: ' . print_r($response, true));
    }
    if (is_wp_error($response) || empty($response_data) || ! isset($response_data->result->Code) || 200 !== $response_data->result->Code) {
        foreach ($orders_temp as $order) {
            if ( ! $update) {
                update_post_meta($order->get_id(), 'oodo-status', 'failed');
            }
            $error_message = 'فشل إرسال الطلب إلى أودو: رد غير متوقع.';
            $order->add_order_note($error_message, false);
        }
        return false;
    } elseif (! empty($response_data) && isset($response_data->result->Code) && 200 === $response_data->result->Code) {
        // Loop through the response Data array.
        if (isset($response_data->result->Data) && is_array($response_data->result->Data)) {
            foreach ($response_data->result->Data as $data) {
                if (isset($data->ID) && $data->ID === false && isset($data->StatusDescription) && $data->StatusDescription === 'Failed' && isset($data->woo_commerce_id)) {
                    if (strpos($data->EnglishMessage, 'already exists') === false) {
                        if (! $update) {
                            update_post_meta($data->woo_commerce_id, 'oodo-status', 'failed');
                        }
                    } else {
                        update_post_meta($data->woo_commerce_id, 'oodo-status', 'success');
                        update_post_meta($data->woo_commerce_id, 'odoo_order', $data->odoo_id);
                        update_post_meta($data->woo_commerce_id, 'odoo_order_number', $data->name);
                    }
                    // Add order note with Arabic message if exists.
                    if (isset($data->ArabicMessage)) {
                        $order = wc_get_order($data->woo_commerce_id);
                        if ($order) {
                            $order->add_order_note($data->ArabicMessage, false);
                        }
                    }
                    return false;
                }
            }
        }
    }
    return true;
}
function send_to_odoo($orders_data, $token)
{
    $odoo_api_url = ODOO_BASE . 'api/sale.order/add_update_order';

    return wp_remote_post(
        $odoo_api_url,
        array(
            'method'  => 'POST',
            'body'    => wp_json_encode($orders_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'token'        => $token,
            ),
            'timeout' => 30,
        )
    );
}
function send_orders_batch_to_odoo($order_ids, $update = false)
{
    if (empty($order_ids) || ! is_array($order_ids)) {
        return;
    }

    $token = get_odoo_auth_token();
    if (! $token) {
        if (function_exists('teamlog')) {
            teamlog('فشل إرسال الطلبات إلى Odoo: رمز التوثيق غير موجود.');
        } else {
            error_log('فشل إرسال الطلبات إلى Odoo: رمز التوثيق غير موجود.');
        }
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            $odoo_order_id =  get_post_meta($order->get_id(), 'odoo_order', true);
            if ( ! $odoo_order_id || empty( $odoo_order_id ) ) {
                update_post_meta($order_id, 'oodo-status', 'failed');
                $error_message = 'فشل إرسال الطلب إلى أودو: رد غير متوقع.';
            } else {
                $error_message = 'فشل تحديث الطلب في أودو: رد غير متوقع.';
            }
            
            $order->add_order_note($error_message, false);
        }
        return false;
    }

    $orders_data = array();
    $orders_temp = array();

    process_odoo_order($order_ids, $orders_data, $orders_temp, $update);

    if (empty($orders_data)) {
        return;
    }

    $response = send_to_odoo($orders_data, $token);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body);

    $resp = process_response($response, $response_data, $orders_temp, $update);
    if (! $resp) {
        return;
    }
    $sent_to_odoo = array();
    foreach ($response_data->result->Data as $odoo_order) {
        if ($odoo_order->woo_commerce_id) {
            $order_id = $odoo_order->woo_commerce_id;
            if (! $update) {
                update_post_meta($order_id, 'odoo_order', $odoo_order->ID);
                update_post_meta($order_id, 'odoo_order_number', $odoo_order->Number);
                update_post_meta($order_id, 'oodo-status', 'success');
            }
            $sent_to_odoo[] = $order_id;
            $order          = wc_get_order($order_id);
            if ($order) {
                if (! $update) {
                    $order->add_order_note("تم إرسال الطلب بنجاح إلى أودو برقم أودو ID: {$odoo_order->ID}.", false);
                } else {
                    $order->add_order_note("تم تحديث الطلب بنجاح إلى أودو برقم أودو ID: {$odoo_order->ID}.", false);
                }
                $sent_products = array();
                foreach ($order->get_items('line_item') as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $sent_products[] = $product->get_id();
                        $sku             = $product->get_sku();
                        update_odoo_stock($sku, $product);
                    }
                }
                $sent_products_string = implode('-', $sent_products);
                if (! $update) {
                    $order->add_order_note("تم إرسال هذه المنتجات إلى أودو {$sent_products_string}", false);
                } else {
                    $order->add_order_note("تم تحديث هذه المنتجات إلى أودو {$sent_products_string}", false);
                }
            } else {
                teamlog('Order not found');
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
    return $sent_to_odoo;
}


/**
 * Display Odoo Order ID under the billing details in the admin order page.
 *
 * @param WC_Order $order The order object.
 */
function display_odoo_order_id_in_admin($order)
{
    $odoo_order_id     = get_post_meta($order->get_id(), 'odoo_order', true);
    $odoo_order_number = get_post_meta($order->get_id(), 'odoo_order_number', true);

    if ($odoo_order_id) {
        echo '<p><strong>' . __('Odoo Order ID:', 'text-domain') . '</strong> ' . esc_html($odoo_order_id) . '</p>';
        echo '<p><strong>' . __('Odoo Order Number:', 'text-domain') . '</strong> ' . esc_html($odoo_order_number) . '</p>';
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'display_odoo_order_id_in_admin', 10, 1);


/**
 * Cancel an order in Odoo.
 *
 * @param int $odoo_order_id The Odoo Order ID to cancel.
 * @param int $order_id The WooCommerce order ID.
 * @return void
 */
function cancel_odoo_order($odoo_order_id, $order_id)
{
    // Get the WooCommerce order object.
    $order = wc_get_order($order_id);
    if (! $order) {
        return;
    }

    // Fetch Odoo authentication token.
    $token = get_odoo_auth_token();
    if (! $token) {
        $order->add_order_note('فشل في إلغاء الطلب في Odoo: رمز التوثيق غير موجود.', false);
        return;
    }

    // Odoo API endpoint for canceling an order.
    $cancel_url   = ODOO_BASE . 'api/sale.order/cancel_order';
    $request_body = wp_json_encode(
        array(
            'orders' => array(
                array('RequestID' => (string) $odoo_order_id),
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
    if (is_wp_error($response)) {
        $order->add_order_note('فشل في إلغاء الطلب في Odoo: ' . $response->get_error_message(), false);
        return;
    }

    $response_data = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_data['result']['Code']) && 200 === $response_data['result']['Code']) {
        // Log success as a private note.
        $order->add_order_note("تم إلغاء الطلب بنجاح في Odoo برقم: $odoo_order_id", false);
    } else {
        // Log failure as a private note.
        $order->add_order_note("فشل في إلغاء الطلب في Odoo برقم: $odoo_order_id. الرد: " . wp_remote_retrieve_body($response), false);
    }
}


/**
 * إرسال الطلبات المكتملة إلى واجهة Odoo API للتحقق من التسليم.
 *
 * @param int $order_id رقم تعريف الطلب المكتمل في WooCommerce.
 */
function snks_validate_order_delivery_on_completion($order_id)
{
    // جلب الطلب في WooCommerce.
    $order = wc_get_order($order_id);
    if (! $order) {
        return;
    }

    // جلب رقم الطلب في Odoo من الميتا الخاصة بالطلب في WooCommerce.
    $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);

    if (! $odoo_order_id) {
        $order->add_order_note("لم يتم العثور على رقم الطلب في Odoo للطلب رقم: $order_id", false);
        return;
    }

    // إعداد البيانات لإرسالها إلى واجهة Odoo API.
    $data = array(
        'orders' => array(
            array(
                'RequestID' => (string) $odoo_order_id,
                'modified_date' => current_time('Y-m-d H:i:s'),
            ),
        ),
    );

    // جلب رمز التوثيق من Odoo.
    $token = get_odoo_auth_token();
    if (! $token) {
        $order->add_order_note('فشل في إرسال البيانات إلى واجهة Odoo API: رمز التوثيق غير موجود.', false);
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
            'body'    => wp_json_encode($data),
            'timeout' => 20,
        )
    );
    teamlog( 'Deliver validation:' .  print_r( $response, true ) );
    // معالجة الرد.
    if (is_wp_error($response)) {
        $order->add_order_note('خطأ أثناء إرسال البيانات إلى واجهة Odoo API: ' . $response->get_error_message(), false);
        return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    if (isset($response_data['result']['status']) && 'success' === $response_data['result']['status']) {
        // تسجيل رسالة النجاح.
        $message = $response_data['result']['message'] ?? 'تم التحقق من عملية تسليم الطلب بنجاح.';
        $order->add_order_note("نجاح واجهة Odoo API: $message", false);
    } else {
        // تسجيل الفشل مع الرد الكامل للتصحيح.
        $order->add_order_note("فشل في التحقق من تسليم الطلب في Odoo للطلب رقم: $odoo_order_id. الرد: $response_body", false);
    }
}


/**
 * Hook to cancel Odoo order when WooCommerce order status changes to cancelled.
 */
add_action(
    'woocommerce_order_status_changed',
    function ($order_id, $old_status, $new_status) {
        // Check if the new status is 'cancelled'.
        if ('cancelled' === $new_status || 'was-canceled' === $new_status || 'wc-cancelled' === $new_status) {
            $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);
            if ($odoo_order_id) {
                cancel_odoo_order($odoo_order_id, $order_id);
            }
        }

        if ('international-shi' === $new_status || 'was-shipped' === $new_status) {
            snks_validate_order_delivery_on_completion($order_id);
        }
        update_odoo_order_status(array( $order_id ), $new_status);
    },
    10,
    3 // Number of arguments passed to the callback (order ID, old status, new status).
);

add_filter('woocommerce_add_to_cart_validation', 'odoo_check_stock_before_add_to_cart', 10, 5);
// Add stock validation during order item addition.
add_filter(
    'woocommerce_ajax_add_order_item_validation',
    function ($validation_error, $product, $order, $qty) {
        if (! $product) {
            return new WP_Error('invalid_product', __('Invalid product data.', 'woocommerce'));
        }

        $sku          = $product->get_sku();
        $product_id   = $product->get_id();
        $variation_id = $product->is_type('variation') ? $product_id : $product_id;

        $stock_check = check_odoo_stock($sku, $qty, $variation_id);

        if (is_wp_error($stock_check)) {
            return $stock_check;
        }

        if (! $stock_check) {
            return new WP_Error('out_of_stock', __('مخزون المنتج غير متوفر بالكمية المطلوبة. يرجى تعديل الكمية أو اختيار منتج آخر.', 'woocommerce'));
        }

        return $validation_error;
    },
    10,
    4
);
add_action(
    'wpo_before_load_items',
    function ($request) {
        // Ensure the request contains items.
        if (! empty($request['items'])) {
            foreach ($request['items'] as $item) {
                $product_id = $item['id'];
                $quantity   = isset($item['quantity']) ? (int) $item['quantity'] : 1;

                // Get the product object.
                $product = wc_get_product($product_id);
                if (! $product) {
                    echo json_encode(array('error' => 'Invalid product ID.'));
                    die();
                }
                // Determine if this is a variation or simple product.
                $sku = $product->get_sku();

                if ($product->is_type('variation')) {
                    // For variations, use the variation itself.
                    $variation_id = $product_id;
                } else {
                    // For simple products, use the product ID.
                    $variation_id = $product_id;
                }

                // Use the helper function to check stock with the multiplier.
                $stock_check = check_odoo_stock($sku, $quantity, $variation_id);

                if (is_wp_error($stock_check)) {
                    echo json_encode(
                        array(
                            'error' => $stock_check->get_error_message(),
                        )
                    );
                    die();
                }

                if (! $stock_check) {
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
    function ($type, $order) {
        $odoo = get_post_meta($order->get_id(), 'odoo_order_number', true);
        if (! $odoo && $odoo === '') {
            return;
        }
        ?>
    <tr class="odoo-number">
        <th><?php _e('الرقم المرجعي :', 'woocommerce-pdf-invoices-packing-slips'); ?></th>
        <td><?php echo $odoo; ?></td>
    </tr>
        <?php
    },
    10,
    2
);


add_action(
    'woocommerce_checkout_order_created',
    function ($order) {
        send_orders_batch_to_odoo(array($order->get_id()));
    }
);
add_action(
    'woocommerce_process_shop_order_meta',
    function ($order_id) {
        // Only run if current user is NOT a customer
        if (current_user_can('customer')) {
            return;
        }

        $odoo_order = get_post_meta($order_id, 'odoo_order', true);
        $update     = true;

        if (! $odoo_order || empty($odoo_order)) {
            $update = false;
        }

        send_orders_batch_to_odoo(array($order_id), $update);
    },
    99
);



add_action(
    'woocommerce_admin_order_data_after_order_details',
    function ($order) {
        $order_id = $order->get_id();
        $nonce    = wp_create_nonce('sync_order_to_odoo_' . $order_id);

        echo '<button id="sync-to-odoo" class="button button-primary" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonce) . '">
        <span class="sync-text">Sync to Odoo</span>
        <span class="sync-loading" style="display:none;">Loading...</span>
    </button>';
    }
);

add_action(
    'wp_ajax_sync_order_to_odoo',
    function () {
        // Validate order ID.
        if (empty($_POST['order_id']) || empty($_POST['nonce'])) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }

        $order_id = intval($_POST['order_id']);
        $nonce    = sanitize_text_field($_POST['nonce']);

        // Verify nonce for security.
        if (! wp_verify_nonce($nonce, 'sync_order_to_odoo_' . $order_id)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Call the function to sync with Odoo.
        send_orders_batch_to_odoo_v2(array($order_id));

        wp_send_json_success(array('message' => 'Order synced successfully'));
    }
);

function send_orders_batch_to_odoo_v2($order_ids)
{
    if (empty($order_ids) || ! is_array($order_ids)) {
        return wp_send_json_error(array('message' => 'No order IDs provided.'));
    }

    $token = get_odoo_auth_token();
    if (! $token) {
        $error_message = 'فشل إرسال الطلبات إلى Odoo: رمز التوثيق غير موجود.';
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            update_post_meta($order_id, 'oodo-status', 'failed');
            $order->add_order_note($error_message, false);
        }
        return wp_send_json_error($error_message);
    }

    $orders_data = array();
    $orders_temp = array();

    process_odoo_order($order_ids, $orders_data, $orders_temp);

    if (empty($orders_data)) {
        return wp_send_json_error(array('message' => 'No valid orders to send.'));
    }

    $response = send_to_odoo($orders_data, $token);

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body);
    if (is_wp_error($response) || empty($response_data) || ! isset($response_data->result->Code) || 200 !== $response_data->result->Code) {
        $error_message = 'فشل إرسال الطلبات إلى Odoo: رد غير متوقع.';
        foreach ($orders_temp as $order) {
            update_post_meta($order->get_id(), 'oodo-status', 'failed');
            $order->add_order_note($error_message, false);
        }
        return wp_send_json_error($error_message);
    } elseif (! empty($response_data) && isset($response_data->result->Code) && 200 === $response_data->result->Code) {
        // Loop through the response Data array
        if (isset($response_data->result->Data) && is_array($response_data->result->Data)) {
            foreach ($response_data->result->Data as $data) {
                if (isset($data->ID) && $data->ID === false && isset($data->StatusDescription) && $data->StatusDescription === 'Failed' && isset($data->woo_commerce_id)) {
                    if (strpos($data->EnglishMessage, 'already exists') === false) {
                        update_post_meta($data->woo_commerce_id, 'oodo-status', 'failed');
                    }
                    // Add order note with Arabic message if exists
                    if (isset($data->ArabicMessage)) {
                        $order = wc_get_order($data->woo_commerce_id);
                        if ($order) {
                            $order->add_order_note($data->ArabicMessage, false);
                        }
                    }
                    return wp_send_json_error(array('message' => $data->ArabicMessage ?? 'Order failed to send.'));
                } elseif (! isset($data->ID) || $data->ID === false || ! isset($data->woo_commerce_id)) {
                    return wp_send_json_error(array('message' => $data->ArabicMessage ?? 'Order failed to send.'));
                }
            }
        }
    }

    foreach ($response_data->result->Data as $odoo_order) {
        if ($odoo_order->woo_commerce_id) {
            $order_id = $odoo_order->woo_commerce_id;
            update_post_meta($order_id, 'odoo_order', $odoo_order->ID);
            update_post_meta($order_id, 'odoo_order_number', $odoo_order->Number);
            update_post_meta($order_id, 'oodo-status', 'success');
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note("تم إرسال الطلب بنجاح إلى أودو برقم أودو ID: {$odoo_order->ID}.", false);
                foreach ($order->get_items('line_item') as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $sku = $product->get_sku();
                        update_odoo_stock($sku, $product);
                    }
                }
            }
        }
    }

    return wp_send_json_success(array('message' => 'Orders sent to Odoo successfully.'));
}

add_action(
    'admin_footer',
    function () {
        global $pagenow, $post;

        if ('post.php' !== $pagenow || 'shop_order' !== get_post_type($post)) {
            return;
        }
        ?>
    <script>
        jQuery(document).ready(function($) {
            $('#sync-to-odoo').on('click', function(e) {
                e.preventDefault();

                var button = $(this);
                var orderId = button.data('order-id');
                var nonce = button.data('nonce');

                // Show loading state
                button.prop('disabled', true);
                button.find('.sync-text').hide();
                button.find('.sync-loading').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'sync_order_to_odoo',
                        order_id: orderId,
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log(response);
                        alert(response.data.message);
                    },
                    error: function() {
                        alert('Error syncing order.');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        button.find('.sync-text').show();
                        button.find('.sync-loading').hide();
                    }
                });
            });
        });
    </script>
        <?php
    }
);

add_filter('bulk_actions-edit-shop_order', 'register_custom_bulk_action');
function register_custom_bulk_action($bulk_actions)
{
    $bulk_actions['send_to_odoo'] = 'إرسال إلى أودو';
    return $bulk_actions;
}

add_filter('handle_bulk_actions-edit-shop_order', 'handle_custom_bulk_action', 10, 3);
function handle_custom_bulk_action($redirect_to, $action, $order_ids)
{
    if ($action !== 'send_to_odoo') {
        return $redirect_to;
    }

    // Call the function to send orders
    $sent = send_orders_batch_to_odoo($order_ids);

    // Add a query argument to show success message
    $redirect_to = add_query_arg('sent_to_odoo', count($sent), $redirect_to);
    return $redirect_to;
}

add_action('admin_notices', 'display_odoo_bulk_action_admin_notice');
function display_odoo_bulk_action_admin_notice()
{
    if (! empty($_GET['sent_to_odoo'])) {
        $count = intval($_GET['sent_to_odoo']);
        echo "<div class='updated'><p>تم إرسال {$count} طلب(ات) إلى أودو بنجاح!</p></div>";
    }
}

function update_odoo_order_status($order_ids, $new_status = null)
{
    if (empty($order_ids) || ! is_array($order_ids)) {
        return;
    }

    $token = get_odoo_auth_token();
    if (! $token) {
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            $order->add_order_note('فشل تحديث حالة الطلب في أودو: رمز التوثيق غير موجود.', false);
        }
        return false;
    }

    $orders_data = array();

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (! $order) {
            continue;
        }
        $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);
        if (empty($odoo_order_id)) {
            $order->add_order_note('لم يتم تحديث حالة الطلب في أودو: رقم الطلب في أودو غير موجود.', false);
            continue;
        }

        // Use the new status if provided, otherwise get the current order status
        $order_status = $new_status ?? $order->get_status();

        $order_data = array(
            'RequestID'       => $odoo_order_id,
            'wc_order_status' => wc_get_order_statuses()["wc-$order_status"] ?? $order_status,
            'modified_date' => current_time('Y-m-d H:i:s'),
        );

        $orders_data['orders'][] = $order_data;
    }

    if (empty($orders_data)) {
        return;
    }

    $odoo_api_url = ODOO_BASE . 'api/sale.order/add_update_order';

    $response = wp_remote_post(
        $odoo_api_url,
        array(
            'method'  => 'POST',
            'body'    => wp_json_encode($orders_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'token'        => $token,
            ),
            'timeout' => 30,
        )
    );

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body);

    if (is_wp_error($response) || empty($response_data) || ! isset($response_data->result->Code) || 200 !== $response_data->result->Code) {
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            $order->add_order_note('فشل تحديث حالة الطلب في أودو: رد غير متوقع.', false);
        }
        return;
    }

    foreach ($response_data->result->Data as $odoo_order) {
        if (isset($odoo_order->requestID)) {
            $order = wc_get_order($odoo_order->requestID);
            if ($order) {
                $order->add_order_note("تم تحديث حالة الطلب في أودو بنجاح إلى: $order_status.", false);
            }
        }
    }
}

add_action('woocommerce_order_status_pending', function ($order_id) {
    $order = wc_get_order( $order_id );

    if ($order->get_status() == 'pending') {
        remove_action('woocommerce_order_status_pending', 'wc_maybe_increase_stock_levels');
    }
}, 9);
