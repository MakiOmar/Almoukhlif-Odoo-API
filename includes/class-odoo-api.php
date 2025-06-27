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