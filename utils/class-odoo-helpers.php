<?php
/**
 * Odoo Helpers Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Helpers {
    
    /**
     * Get the total coupon discount from the applied coupons array.
     *
     * @param array $applied_coupons List of applied coupons.
     * @return float Total coupon discount.
     */
    public static function get_total_coupon_discount($applied_coupons) {
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
     * Check if a country is a Gulf country (excluding Saudi Arabia)
     * 
     * @param string $billing_country Country code
     * @return bool True if Gulf country
     */
    public static function is_gulf_country($billing_country) {
        // Define Gulf countries excluding Saudi Arabia
        $gulf_countries = array('AE', 'BH', 'KW', 'OM', 'QA'); // UAE, Bahrain, Kuwait, Oman, Qatar
        return in_array($billing_country, $gulf_countries);
    }
    
    /**
     * Display Odoo Order ID under the billing details in the admin order page.
     *
     * @param WC_Order $order The order object.
     */
    public static function display_odoo_order_id_in_admin($order) {
        $odoo_order_id = get_post_meta($order->get_id(), 'odoo_order', true);
        $odoo_order_number = get_post_meta($order->get_id(), 'odoo_order_number', true);

        if ($odoo_order_id) {
            echo '<p><strong>' . __('Odoo Order ID:', 'text-domain') . '</strong> ' . esc_html($odoo_order_id) . '</p>';
            echo '<p><strong>' . __('Odoo Order Number:', 'text-domain') . '</strong> ' . esc_html($odoo_order_number) . '</p>';
        }
    }
    
    /**
     * Cancel an order in Odoo.
     *
     * @param int $odoo_order_id The Odoo Order ID to cancel.
     * @param int $order_id The WooCommerce order ID.
     * @return void
     */
    public static function cancel_odoo_order($odoo_order_id, $order_id) {
        // Get the WooCommerce order object.
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Fetch Odoo authentication token.
        $token = Odoo_Auth::get_auth_token();
        if (!$token) {
            $order->add_order_note('فشل في إلغاء الطلب في Odoo: رمز التوثيق غير موجود.', false);
            return;
        }

        // Send the cancellation request to Odoo API.
        $response = Odoo_API::cancel_order($odoo_order_id, $token);

        // Handle the response.
        if (is_wp_error($response)) {
            $order->add_order_note('فشل في إلغاء الطلب في Odoo: ' . $response->get_error_message(), false);
            return;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_data['result']['Code']) && 200 === $response_data['result']['Code']) {
            // Log success as a private note.
            $order->add_order_note("تم إلغاء الطلب بنجاح في Odoo برقم: $odoo_order_id", false);
            
            // Trigger activity logger event
            do_action('odoo_order_cancelled', $order_id, $response_data);
        } else {
            // Log failure as a private note.
            $order->add_order_note("فشل في إلغاء الطلب في Odoo برقم: $odoo_order_id. الرد: " . wp_remote_retrieve_body($response), false);
        }
    }
    
    /**
     * Validate order delivery on completion
     *
     * @param int $order_id Order ID
     */
    public static function validate_order_delivery_on_completion($order_id) {
        // Get the order in WooCommerce.
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get the Odoo order ID from the order meta in WooCommerce.
        $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);

        if (!$odoo_order_id) {
            $order->add_order_note("لم يتم العثور على رقم الطلب في Odoo للطلب رقم: $order_id", false);
            return;
        }

        // Get authentication token from Odoo.
        $token = Odoo_Auth::get_auth_token();
        if (!$token) {
            $order->add_order_note('فشل في إرسال البيانات إلى واجهة Odoo API: رمز التوثيق غير موجود.', false);
            return;
        }

        // Send data to Odoo API using wp_remote_post.
        $response = Odoo_API::validate_delivery($odoo_order_id, $token);
        
        // Log the response data
        $response_log_data = array(
            'order_id' => $order_id,
            'odoo_order_id' => $odoo_order_id,
            'response' => $response,
            'response_body' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response),
            'response_code' => is_wp_error($response) ? 'WP_ERROR' : wp_remote_retrieve_response_code($response),
            'timestamp' => current_time('Y-m-d H:i:s'),
            'user_id' => get_current_user_id()
        );
        
        if (function_exists('teamlog')) {
            teamlog('Delivery Validation Response: ' . print_r($response_log_data, true));
        } else {
            odoo_log('[Odoo Delivery Validation Response] ' . print_r($response_log_data, true), 'info');
        }

        // Try to use Odoo_Logger if available
        if (class_exists('Odoo_Logger')) {
            Odoo_Logger::log('delivery_validation_response', $response_log_data);
        }
        
        // Process the response.
        if (is_wp_error($response)) {
            $order->add_order_note('خطأ أثناء إرسال البيانات إلى واجهة Odoo API: ' . $response->get_error_message(), false);
            return;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (isset($response_data['result']['status']) && 'success' === $response_data['result']['status']) {
            // Log success message.
            $message = $response_data['result']['message'] ?? 'تم التحقق من عملية تسليم الطلب بنجاح.';
            $order->add_order_note("نجاح واجهة Odoo API: $message", false);
        } else {
            // Log failure with complete response for debugging.
            $order->add_order_note("فشل في التحقق من تسليم الطلب في Odoo للطلب رقم: $odoo_order_id. الرد: $response_body", false);
        }
    }
    
    /**
     * Update Odoo order status
     * 
     * @param array $order_ids Array of order IDs
     * @param string $new_status New status
     * @return bool Success status
     */
    public static function update_odoo_order_status($order_ids, $new_status = null) {
        if (empty($order_ids) || !is_array($order_ids)) {
            return false;
        }

        $token = Odoo_Auth::get_auth_token();
        if (!$token) {
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                $order->add_order_note('فشل تحديث حالة الطلب في أودو: رمز التوثيق غير موجود.', false);
            }
            return false;
        }

        $orders_data = array();
        $order_status_labels = array(); // Store status labels for success messages

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }
            $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);
            if (empty($odoo_order_id)) {
                $order->add_order_note('لم يتم تحديث حالة الطلب في أودو: رقم الطلب في أودو غير موجود.', false);
                continue;
            }

            // Use the new status if provided, otherwise get the current order status
            $order_status = $new_status ?? $order->get_status();
            
            // Get the status label for the success message
            $status_label = wc_get_order_statuses()["wc-$order_status"] ?? $order_status;
            $order_status_labels[$order_id] = $status_label;

            $order_data = array(
                'RequestID'       => $odoo_order_id,
                'wc_order_status' => $status_label,
                'wc_order_status_code' => $order_status,
                'modified_date' => current_time('Y-m-d H:i:s'),
            );

            $orders_data['orders'][] = $order_data;
        }

        if (empty($orders_data)) {
            return false;
        }

        $response = Odoo_API::send_to_odoo($orders_data, $token);

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body);

        if (is_wp_error($response) || empty($response_data) || !isset($response_data->result->Code) || 200 !== $response_data->result->Code) {
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                $order->add_order_note('فشل تحديث حالة الطلب في أودو: رد غير متوقع.', false);
            }
            return false;
        }

        foreach ($response_data->result->Data as $odoo_order) {
            if (isset($odoo_order->woo_commerce_id)) {
                $order = wc_get_order($odoo_order->woo_commerce_id);
                if ($order) {
                    $order_id = $order->get_id();
                    $status_label = $order_status_labels[$order_id] ?? 'unknown';
                    $order->add_order_note("تم تحديث حالة الطلب في أودو بنجاح إلى: $status_label.", false);
                }
            }
        }
        
        return true;
    }

    /**
     * Debug utility: Log order status and order key for a given order ID
     *
     * @param int $order_id WooCommerce order ID
     */
    public static function debug_order_status_and_key($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            odoo_log("[Odoo Debug] Order not found: $order_id", 'warning');
            return;
        }
        $status = $order->get_status();
        $order_key = get_post_meta($order_id, '_order_key', true);
        $log_message = sprintf(
            '[Odoo Debug] Order ID: %d | Status: %s | _order_key: %s',
            $order_id,
            $status,
            $order_key
        );
        if (function_exists('teamlog')) {
            teamlog($log_message);
        } else {
            odoo_log($log_message, 'debug');
        }
    }
} 