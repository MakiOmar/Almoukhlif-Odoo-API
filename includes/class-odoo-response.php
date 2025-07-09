<?php
/**
 * Odoo Response Processing Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Response {
    
    /**
     * Unified response processor that handles all Odoo API responses consistently
     * 
     * @param object $response_data The decoded response from Odoo API
     * @param array $orders_temp Array of order objects
     * @param bool $update Whether this is an update operation
     * @param bool $is_ajax Whether this is an AJAX request
     * @return array Result with status, message, and processed orders
     */
    public static function process_unified($response_data, $orders_temp, $update = false, $is_ajax = false) {
        $result = [
            'success' => false,
            'message' => '',
            'processed_orders' => [],
            'failed_orders' => []
        ];

        // Log the response for debugging
        if (function_exists('teamlog')) {
            teamlog('Processed response: ' . print_r($response_data, true));
        } else {
            error_log('Processed response: ' . print_r($response_data, true));
        }

        // Check for basic response structure
        if (empty($response_data) || !isset($response_data->result->Code)) {
            $result['message'] = 'فشل إرسال الطلب إلى أودو: رد غير متوقع.';
            $result['failed_orders'] = array_map(function($order) use ($update) {
                if (!$update) {
                    update_post_meta($order->get_id(), 'oodo-status', 'failed');
                }
                return $order->get_id();
            }, $orders_temp);
            return $result;
        }

        // Check HTTP status code
        if ($response_data->result->Code !== 200) {
            $result['message'] = 'فشل إرسال الطلب إلى أودو: رمز الاستجابة ' . $response_data->result->Code;
            $result['failed_orders'] = array_map(function($order) use ($update) {
                if (!$update) {
                    update_post_meta($order->get_id(), 'oodo-status', 'failed');
                }
                return $order->get_id();
            }, $orders_temp);
            return $result;
        }

        // Process individual order responses
        if (!isset($response_data->result->Data) || !is_array($response_data->result->Data)) {
            $result['message'] = 'فشل إرسال الطلب إلى أودو: بيانات الاستجابة غير صحيحة.';
            $result['failed_orders'] = array_map(function($order) use ($update) {
                if (!$update) {
                    update_post_meta($order->get_id(), 'oodo-status', 'failed');
                }
                return $order->get_id();
            }, $orders_temp);
            return $result;
        }

        $has_failures = false;
        $success_count = 0;

        foreach ($response_data->result->Data as $data) {
            $order_id = $data->woo_commerce_id ?? null;
            
            if (!$order_id) {
                continue;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            // Handle different response scenarios
            $order_result = self::process_single_order($data, $order, $update);
            
            if ($order_result['success']) {
                $result['processed_orders'][] = $order_id;
                $success_count++;
            } else {
                $result['failed_orders'][] = $order_id;
                $has_failures = true;
                
                // Add order note with specific error message
                if (isset($data->ArabicMessage)) {
                    $order->add_order_note($data->ArabicMessage, false);
                } elseif (isset($data->EnglishMessage)) {
                    $order->add_order_note($data->EnglishMessage, false);
                }
            }
        }

        // Set overall result
        if ($success_count > 0 && !$has_failures) {
            $result['success'] = true;
            $result['message'] = "تم إرسال {$success_count} طلب(ات) بنجاح إلى أودو.";
        } elseif ($success_count > 0 && $has_failures) {
            $result['success'] = true;
            $result['message'] = "تم إرسال {$success_count} طلب(ات) بنجاح، وفشل " . count($result['failed_orders']) . " طلب(ات).";
        } else {
            $result['message'] = 'فشل إرسال جميع الطلبات إلى أودو.';
        }

        return $result;
    }

    /**
     * Process response for a single order
     * 
     * @param object $data Order response data from Odoo
     * @param WC_Order $order WooCommerce order object
     * @param bool $update Whether this is an update operation
     * @return array Result for this specific order
     */
    public static function process_single_order($data, $order, $update = false) {
        $result = ['success' => false, 'message' => ''];

        // Case 1: Order failed in Odoo
        if (isset($data->ID) && ($data->ID === false || $data->ID === null || $data->ID === '') && 
            isset($data->StatusDescription) && $data->StatusDescription === 'Failed') {
            
            // Special case: "already exists" is treated as success
            if (isset($data->EnglishMessage) && strpos($data->EnglishMessage, 'already exists') !== false) {
                if (isset($data->odoo_id) && isset($data->name)) {
                    update_post_meta($order->get_id(), 'odoo_order', $data->odoo_id);
                    update_post_meta($order->get_id(), 'odoo_order_number', $data->name);
                    update_post_meta($order->get_id(), 'oodo-status', 'success');
                    $result['success'] = true;
                    $result['message'] = 'الطلب موجود بالفعل في أودو.';
                }
            } else {
                if (!$update) {
                    update_post_meta($order->get_id(), 'oodo-status', 'failed');
                }
                $result['message'] = $data->ArabicMessage ?? $data->EnglishMessage ?? 'فشل في معالجة الطلب في أودو.';
            }
            return $result;
        }

        // Case 2: Order processed successfully
        if (isset($data->ID) && $data->ID !== false && isset($data->Number)) {
            if (!$update) {
                update_post_meta($order->get_id(), 'odoo_order', $data->ID);
                update_post_meta($order->get_id(), 'odoo_order_number', $data->Number);
                update_post_meta($order->get_id(), 'oodo-status', 'success');
            }
            
            // Add success note
            $note_message = $update ? 
                "تم تحديث الطلب بنجاح في أودو برقم أودو ID: {$data->ID}." :
                "تم إرسال الطلب بنجاح إلى أودو برقم أودو ID: {$data->ID}.";
            $order->add_order_note($note_message, false);
            
            // Update stock for products
            self::update_order_products_stock($order, $update);
            
            $result['success'] = true;
            $result['message'] = 'تم معالجة الطلب بنجاح.';
            return $result;
        }

        // Case 3: Invalid response structure
        if (!isset($data->ID) || !isset($data->woo_commerce_id)) {
            if (!$update) {
                update_post_meta($order->get_id(), 'oodo-status', 'failed');
            }
            $result['message'] = 'استجابة غير صحيحة من أودو.';
            return $result;
        }

        // Case 4: Unknown response format
        if (!$update) {
            update_post_meta($order->get_id(), 'oodo-status', 'failed');
        }
        $result['message'] = 'تنسيق استجابة غير معروف من أودو.';
        return $result;
    }

    /**
     * Update stock for all products in an order
     * 
     * @param WC_Order $order WooCommerce order object
     * @param bool $update Whether this is an update operation
     */
    public static function update_order_products_stock($order, $update = false) {
        $sent_products = [];
        
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if ($product) {
                $sent_products[] = $product->get_id();
                $sku = $product->get_sku();
                Odoo_Stock::update_stock($sku, $product);
            }
        }
        
        if (!empty($sent_products)) {
            $sent_products_string = implode('-', $sent_products);
            $note_message = $update ? 
                "تم تحديث هذه المنتجات في أودو: {$sent_products_string}" :
                "تم إرسال هذه المنتجات إلى أودو: {$sent_products_string}";
            $order->add_order_note($note_message, false);
        }
    }
} 