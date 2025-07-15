<?php
/**
 * Odoo API Communication Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_API {
    
    /**
     * Send data to Odoo API
     * 
     * @param array $orders_data Order data to send
     * @param string $token Authentication token
     * @return WP_Error|array Response from Odoo
     */
    public static function send_to_odoo($orders_data, $token) {
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
    
    /**
     * Send order cancellation request to Odoo
     * 
     * @param int $odoo_order_id Odoo order ID
     * @param string $token Authentication token
     * @return WP_Error|array Response from Odoo
     */
    public static function cancel_order($odoo_order_id, $token) {
        $cancel_url = ODOO_BASE . 'api/sale.order/cancel_order';
        $request_body = wp_json_encode(
            array(
                'orders' => array(
                    array('RequestID' => (string) $odoo_order_id),
                ),
            )
        );

        return wp_remote_post(
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
    }
    
    /**
     * Send order delivery validation request to Odoo
     * 
     * @param int $odoo_order_id Odoo order ID
     * @param string $token Authentication token
     * @return WP_Error|array Response from Odoo
     */
    public static function validate_delivery($odoo_order_id, $token) {
        $url = ODOO_BASE . 'api/sale.order/validate_order_delivery';
        $data = array(
            'orders' => array(
                array(
                    'RequestID' => (string) $odoo_order_id,
                    'modified_date' => current_time('Y-m-d H:i:s'),
                ),
            ),
        );

        // Log the data being sent to delivery validation endpoint
        $log_data = array(
            'endpoint' => $url,
            'odoo_order_id' => $odoo_order_id,
            'request_data' => $data,
            'timestamp' => current_time('Y-m-d H:i:s'),
            'user_id' => get_current_user_id(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown',
            'ip_address' => self::get_client_ip(),
            'request_source' => self::get_request_source()
        );

        // Log using available logging methods
        if (function_exists('teamlog')) {
            teamlog('Delivery Validation Request: ' . print_r($log_data, true));
        } else {
            error_log('[Odoo Delivery Validation] ' . print_r($log_data, true));
        }

        // Try to use Odoo_Logger if available
        if (class_exists('Odoo_Logger')) {
            Odoo_Logger::log('delivery_validation', $log_data);
        }

        return wp_remote_post(
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
    }

    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    }

    /**
     * Get request source for logging
     * 
     * @return string Request source
     */
    private static function get_request_source() {
        if (wp_doing_ajax()) {
            return 'AJAX';
        } elseif (wp_doing_cron()) {
            return 'CRON';
        } elseif (is_admin()) {
            return 'ADMIN';
        } elseif (defined('REST_REQUEST') && REST_REQUEST) {
            return 'REST_API';
        } else {
            return 'FRONTEND';
        }
    }
    
    /**
     * Get stock data from Odoo
     * 
     * @param string $sku Product SKU
     * @param string $token Authentication token
     * @return WP_Error|array Response from Odoo
     */
    public static function get_stock_data($sku, $token) {
        $stock_url = ODOO_BASE . 'api/stock.quant/get_available_qty_data';
        $stock_body = json_encode(
            array(
                'default_code' => $sku,
                'location_id'  => ODOO_LOCATION,
            )
        );

        return wp_remote_post(
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
    }
} 