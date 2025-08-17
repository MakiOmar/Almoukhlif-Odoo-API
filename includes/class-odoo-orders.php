<?php
/**
 * Odoo Orders Management Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Orders {
    
    /**
     * Send orders batch to Odoo (background/admin version)
     * 
     * @param array $order_ids Array of order IDs
     * @param bool $update Whether this is an update operation
     * @param int $retry_attempt Current retry attempt
     * @return array|false Array of successful order IDs or false on failure
     */
    public static function send_batch($order_ids, $update = false, $retry_attempt = 0) {
        if (empty($order_ids) || !is_array($order_ids)) {
            return [];
        }

        // Authentication check
        $token = Odoo_Auth::get_auth_token();
        if (!$token) {
            self::handle_authentication_failure($order_ids, $update);
            return false;
        }

        // Prepare order data
        $orders_data = array();
        $orders_temp = array();
        self::process_order_data($order_ids, $orders_data, $orders_temp, $update);

        if (empty($orders_data)) {
            return [];
        }

        // Send to Odoo
        $response = Odoo_API::send_to_odoo($orders_data, $token);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body);

        // Process response using unified processor
        $result = Odoo_Response::process_unified($response_data, $orders_temp, $update, false);

        // Log the processing result with order IDs
        $processing_log_data = array(
            'order_ids' => $order_ids,
            'orders_data' => $orders_data,
            'result' => $result,
            'response_data' => $response_data,
            'update' => $update,
            'timestamp' => current_time('Y-m-d H:i:s'),
            'user_id' => get_current_user_id()
        );
        
        if (function_exists('teamlog')) {
            teamlog('Order processing result: ' . print_r($processing_log_data, true));
        } else {
            odoo_log('[Odoo Order Processing Result] ' . print_r($processing_log_data, true), 'info');
        }

        // Try to use Odoo_Logger if available
        if (class_exists('Odoo_Logger')) {
            Odoo_Logger::log('order_processing_result', $processing_log_data);
        }

        // Trigger activity logger events
        if ($result['success']) {
            foreach ($result['processed_orders'] as $order_id) {
                do_action('odoo_order_sent', $order_id, $response_data);
            }
        } else {
            foreach ($order_ids as $order_id) {
                do_action('odoo_order_failed', $order_id, array(
                    'error' => $result['message'],
                    'response' => $response_data
                ));
            }
        }

        // Handle retries if needed
        if (!$result['success'] && $retry_attempt < 3) {
            $retry_log_data = array(
                'order_ids' => $order_ids,
                'retry_attempt' => $retry_attempt,
                'response_data' => $response_data,
                'result' => $result,
                'update' => $update,
                'timestamp' => current_time('Y-m-d H:i:s'),
                'user_id' => get_current_user_id()
            );
            
            if (function_exists('teamlog')) {
                teamlog("Retry attempt for orders: " . print_r($retry_log_data, true));
            } else {
                odoo_log('[Odoo Retry Attempt] ' . print_r($retry_log_data, true), 'warning');
            }

            // Try to use Odoo_Logger if available
            if (class_exists('Odoo_Logger')) {
                Odoo_Logger::log('retry_attempt', $retry_log_data);
            }
            
            return self::handle_retry_attempt($order_ids, $update, $retry_attempt, 'send_batch');
        }

        return $result['processed_orders'];
    }
    
    /**
     * Send orders batch to Odoo (AJAX version)
     * 
     * @param array $order_ids Array of order IDs
     * @param int $retry_attempt Current retry attempt
     * @return void Sends JSON response
     */
    public static function send_batch_ajax($order_ids, $retry_attempt = 0) {
        if (empty($order_ids) || !is_array($order_ids)) {
            return wp_send_json_error(array('message' => 'No order IDs provided.'));
        }

        // Authentication check
        $token = Odoo_Auth::get_auth_token();
        if (!$token) {
            $error_message = 'فشل إرسال الطلبات إلى Odoo: رمز التوثيق غير موجود.';
            self::handle_authentication_failure($order_ids, false);
            return wp_send_json_error($error_message);
        }

        // Prepare order data
        $orders_data = array();
        $orders_temp = array();
        self::process_order_data($order_ids, $orders_data, $orders_temp, false);

        if (empty($orders_data)) {
            return wp_send_json_error(array('message' => 'No valid orders to send.'));
        }

        // Send to Odoo
        $response = Odoo_API::send_to_odoo($orders_data, $token);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body);

        // Process response using unified processor
        $result = Odoo_Response::process_unified($response_data, $orders_temp, false, true);

        // Log the AJAX processing result with order IDs
        $ajax_processing_log_data = array(
            'order_ids' => $order_ids,
            'orders_data' => $orders_data,
            'result' => $result,
            'response_data' => $response_data,
            'is_ajax' => true,
            'timestamp' => current_time('Y-m-d H:i:s'),
            'user_id' => get_current_user_id()
        );
        
        if (function_exists('teamlog')) {
            teamlog('AJAX Order processing result: ' . print_r($ajax_processing_log_data, true));
        } else {
            odoo_log('[Odoo AJAX Order Processing Result] ' . print_r($ajax_processing_log_data, true), 'info');
        }

        // Try to use Odoo_Logger if available
        if (class_exists('Odoo_Logger')) {
            Odoo_Logger::log('ajax_order_processing_result', $ajax_processing_log_data);
        }

        // Trigger activity logger events
        if ($result['success']) {
            foreach ($result['processed_orders'] as $order_id) {
                do_action('odoo_order_sent', $order_id, $response_data);
            }
        } else {
            foreach ($order_ids as $order_id) {
                do_action('odoo_order_failed', $order_id, array(
                    'error' => $result['message'],
                    'response' => $response_data
                ));
            }
        }

        // Handle retries if needed
        if (!$result['success'] && $retry_attempt < 3) {
            $ajax_retry_log_data = array(
                'order_ids' => $order_ids,
                'retry_attempt' => $retry_attempt,
                'response_data' => $response_data,
                'result' => $result,
                'is_ajax' => true,
                'timestamp' => current_time('Y-m-d H:i:s'),
                'user_id' => get_current_user_id()
            );
            
            if (function_exists('teamlog')) {
                teamlog("AJAX Retry attempt for orders: " . print_r($ajax_retry_log_data, true));
            } else {
                odoo_log('[Odoo AJAX Retry Attempt] ' . print_r($ajax_retry_log_data, true), 'warning');
            }

            // Try to use Odoo_Logger if available
            if (class_exists('Odoo_Logger')) {
                Odoo_Logger::log('ajax_retry_attempt', $ajax_retry_log_data);
            }
            
            return self::handle_retry_attempt($order_ids, false, $retry_attempt, 'send_batch_ajax');
        }

        // Return appropriate JSON response
        if ($result['success']) {
            return wp_send_json_success(array('message' => $result['message']));
        } else {
            return wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Process order data for sending to Odoo
     * 
     * @param array $order_ids Array of order IDs
     * @param array $orders_data Reference to orders data array
     * @param array $orders_temp Reference to orders temp array
     * @param bool $update Whether this is an update operation
     */
    public static function process_order_data($order_ids, &$orders_data, &$orders_temp, $update = false) {
        foreach ($order_ids as $order_id) {
            $odoo_order = get_post_meta($order_id, 'odoo_order', true);
            if (!empty($odoo_order) && is_numeric($odoo_order) && !$update) {
                update_post_meta($order_id, 'oodo-status', 'success');
                continue;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            $applied_coupons = array();
            // Loop through coupon items for this order
            foreach ($order->get_items('coupon') as $item) {
                $coupon_data = array(
                    'coupon_name'         => $item->get_name(),
                    'coupon_code'         => $item->get_code(),
                    'coupon_discount'     => $item->get_discount(),
                    'coupon_discount_tax' => $item->get_discount_tax(),
                );

                $coupon = new WC_Coupon($coupon_data['coupon_code']);
                $coupon_data['coupon_discount_type'] = $coupon->get_discount_type();
                $applied_coupons[] = $coupon_data;
            }
            
            $applied_coupons_discount = Odoo_Helpers::get_total_coupon_discount($applied_coupons);
            $billing_billing_company_vat = get_post_meta($order->get_id(), 'billing_billing_company_vat', true);
            $billing_short_address = get_post_meta($order->get_id(), 'billing_short_address', true);
            $billing_address_second = get_post_meta($order->get_id(), 'billing_address_second', true);
            $billing_building_number = get_post_meta($order->get_id(), 'billing_building_number', true);
            $billing_district = get_post_meta($order->get_id(), 'billing_district', true);
            
            if (!$billing_billing_company_vat || empty($billing_billing_company_vat)) {
                $postcode = $order->get_billing_postcode();
            } else {
                $postcode = get_post_meta($order->get_id(), 'billing_postal_code', true);
            }
            
            // Validate Billing Details
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

            $order_status = $order->get_status();
            $orders_temp[] = $order;
            
            // Get order billing country
            $billing_country = $order->get_billing_country();
            $is_gulf = Odoo_Helpers::is_gulf_country($billing_country);
            
            $order_data = array(
                'manual_confirm'  => false,
                'note'            => $order->get_customer_note(),
                'state'           => 'draft',
                'billing'         => $billing_fields,
                'order_line'      => array(),
                'payment_method'  => $order->get_payment_method_title(),
                'wc_order_status' => wc_get_order_statuses()["wc-$order_status"],
                'wc_order_status_code' => $order_status,
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
            $discount = 0;
            
            foreach ($line_items as $item_id => $item) {
                $product = $item->get_product();
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
                    
                $gifts_total = self::process_item_gifts($item_id, $item, $order_data, $discount_percent);
                $quantity = $item->get_quantity() * $multiplier;
                $unit_price = ($product->get_price() * $item->get_quantity()) / $quantity;

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
            
            $order_data['discount'] = $applied_coupons_discount + $discount;
            $orders_data['orders'][] = $order_data;
        }
        
        if (function_exists('teamlog')) {
            // teamlog(print_r($orders_data, true)); // Removed as orders_data is now logged in the main activity log
        }
    }
    
    /**
     * Process item gifts for an order item
     * 
     * @param int $item_id Item ID
     * @param WC_Order_Item $item Order item
     * @param array $order_data Order data array
     * @param float $discount Discount percentage
     * @return float Total gifts value
     */
    private static function process_item_gifts($item_id, $item, &$order_data, $discount) {
        $value_unserialized = maybe_unserialize(wc_get_order_item_meta($item_id, '_ywapo_meta_data'));
        $is_gift = wc_get_order_item_meta($item_id, '_fgf_gift_product');
        $gifts_total = 0;

        // Get order ID and order details
        $order = wc_get_order($item['order_id']);
        $billing_country = $order ? $order->get_billing_country() : '';
        $is_gulf = Odoo_Helpers::is_gulf_country($billing_country);

        if ($value_unserialized && empty($is_gift)) {
            $counted = count($value_unserialized);
            for ($x = 0; $x < $counted; $x++) {
                $desired_index = array_key_first($value_unserialized[$x]);
                $addon_id = explode('-', $value_unserialized[0][$desired_index][0]);

                if ('product' === $addon_id[0]) {
                    $product = wc_get_product($addon_id[1]);

                    $variation = new WC_Product_Variation($item['variation_id']);
                    $variation_name = implode(' / ', $variation->get_variation_attributes());
                    $variation_name = urldecode(str_replace('-', ' ', $variation_name));

                    $product_name = $product->get_name();
                    $product_sku = $product->get_sku();
                    $multiplier = 1;

                    if ($product->is_type('variation')) {
                        $multiplier = (float) get_post_meta($addon_id[1], '_stock_multiplier', true);
                        $multiplier = !empty($multiplier) && $multiplier > 0 ? $multiplier : 1;
                    }
                    
                    $product_qty = wc_get_order_item_meta($item_id, '_qty');
                    $quantity = $product_qty * $multiplier;
                    
                    if ($multiplier == 1) {
                        $unit_price = $product->get_price();
                    } else {
                        $unit_price = $product->get_price() / $quantity;
                    }
                    
                    $product_price = $unit_price;
                    $gifts_total += $product_price * $quantity;

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
    
    /**
     * Handle authentication failure consistently
     * 
     * @param array $order_ids Array of order IDs
     * @param bool $update Whether this is an update operation
     */
    public static function handle_authentication_failure($order_ids, $update = false) {
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $odoo_order_id = get_post_meta($order->get_id(), 'odoo_order', true);
            
            if (!$odoo_order_id || empty($odoo_order_id)) {
                if (!$update) {
                    update_post_meta($order_id, 'oodo-status', 'failed');
                }
                $error_message = 'فشل إرسال الطلب إلى أودو: رمز التوثيق غير موجود.';
            } else {
                $error_message = 'فشل تحديث الطلب في أودو: رمز التوثيق غير موجود.';
            }
            
            $order->add_order_note($error_message, false);
        }
    }
    
    /**
     * Handle retry attempts consistently
     * 
     * @param array $order_ids Array of order IDs
     * @param bool $update Whether this is an update operation
     * @param int $retry_attempt Current retry attempt
     * @param string $function_name Name of the function to retry
     * @return mixed Result from the retry attempt
     */
    public static function handle_retry_attempt($order_ids, $update, $retry_attempt, $function_name) {
        if (function_exists('teamlog')) {
            teamlog("Retry attempt " . ($retry_attempt + 1) . " for orders: " . implode(', ', $order_ids));
        }
        
        // Exponential backoff with jitter
        $delay = pow(2, $retry_attempt) + rand(0, 1000) / 1000;
        sleep($delay);
        
        // Recursive call - handle both regular and AJAX functions
        if ($function_name === 'send_batch_ajax') {
            return self::send_batch_ajax($order_ids, $retry_attempt + 1);
        } else {
            return self::send_batch($order_ids, $update, $retry_attempt + 1);
        }
    }
} 