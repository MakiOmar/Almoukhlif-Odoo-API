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
if (!defined('ABSPATH')) {
    exit;
}

// Initialize the plugin
require_once plugin_dir_path(__FILE__) . 'includes/class-odoo-core.php';

// Initialize core
$odoo_core = new Odoo_Core();
$odoo_core->init_update_checker();

// Initialize admin
require_once plugin_dir_path(__FILE__) . 'admin/class-odoo-admin.php';
Odoo_Admin::init();

// Initialize hooks
require_once plugin_dir_path(__FILE__) . 'hooks/class-odoo-hooks.php';
Odoo_Hooks::init();

// Legacy function aliases for backward compatibility
if (!function_exists('get_odoo_auth_token')) {
    function get_odoo_auth_token($retry_attempt = 0) {
        return Odoo_Auth::get_auth_token($retry_attempt);
    }
}

if (!function_exists('check_odoo_stock')) {
    function check_odoo_stock($sku, $quantity, $product_id) {
        return Odoo_Stock::check_stock($sku, $quantity, $product_id);
    }
}

if (!function_exists('odoo_check_stock_before_add_to_cart')) {
    function odoo_check_stock_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variation = null) {
        return Odoo_Stock::check_stock_before_add_to_cart($passed, $product_id, $quantity, $variation_id, $variation);
    }
}

if (!function_exists('update_odoo_stock')) {
    function update_odoo_stock($sku, $product = null) {
        return Odoo_Stock::update_stock($sku, $product);
    }
}

if (!function_exists('process_odoo_order')) {
    function process_odoo_order($order_ids, &$orders_data, &$orders_temp, $update = false) {
        return Odoo_Orders::process_order_data($order_ids, $orders_data, $orders_temp, $update);
    }
}

if (!function_exists('send_orders_batch_to_odoo')) {
    function send_orders_batch_to_odoo($order_ids, $update = false, $retry_attempt = 0) {
        return Odoo_Orders::send_batch($order_ids, $update, $retry_attempt);
    }
}

if (!function_exists('send_orders_batch_to_odoo_v2')) {
    function send_orders_batch_to_odoo_v2($order_ids, $retry_attempt = 0) {
        return Odoo_Orders::send_batch_ajax($order_ids, $retry_attempt);
    }
}

if (!function_exists('cancel_odoo_order')) {
    function cancel_odoo_order($odoo_order_id, $order_id) {
        return Odoo_Helpers::cancel_odoo_order($odoo_order_id, $order_id);
    }
}

if (!function_exists('snks_validate_order_delivery_on_completion')) {
    function snks_validate_order_delivery_on_completion($order_id) {
        return Odoo_Helpers::validate_order_delivery_on_completion($order_id);
    }
}

if (!function_exists('update_odoo_order_status')) {
    function update_odoo_order_status($order_ids, $new_status = null) {
        return Odoo_Helpers::update_odoo_order_status($order_ids, $new_status);
    }
}

if (!function_exists('odoo_get_total_coupon_discount')) {
    function odoo_get_total_coupon_discount($applied_coupons) {
        return Odoo_Helpers::get_total_coupon_discount($applied_coupons);
    }
}

if (!function_exists('is_gulf_country')) {
    function is_gulf_country($billing_country) {
        return Odoo_Helpers::is_gulf_country($billing_country);
    }
}

if (!function_exists('display_odoo_order_id_in_admin')) {
    function display_odoo_order_id_in_admin($order) {
        return Odoo_Helpers::display_odoo_order_id_in_admin($order);
    }
}

if (!function_exists('send_to_odoo')) {
    function send_to_odoo($orders_data, $token) {
        return Odoo_API::send_to_odoo($orders_data, $token);
    }
}

if (!function_exists('process_odoo_response_unified')) {
    function process_odoo_response_unified($response_data, $orders_temp, $update = false, $is_ajax = false) {
        return Odoo_Response::process_unified($response_data, $orders_temp, $update, $is_ajax);
    }
}

if (!function_exists('process_single_order_response')) {
    function process_single_order_response($data, $order, $update = false) {
        return Odoo_Response::process_single_order($data, $order, $update);
    }
}

if (!function_exists('update_order_products_stock')) {
    function update_order_products_stock($order, $update = false) {
        return Odoo_Response::update_order_products_stock($order, $update);
    }
}

if (!function_exists('handle_authentication_failure')) {
    function handle_authentication_failure($order_ids, $update = false) {
        return Odoo_Orders::handle_authentication_failure($order_ids, $update);
    }
}

if (!function_exists('handle_retry_attempt')) {
    function handle_retry_attempt($order_ids, $update, $retry_attempt, $function_name) {
        return Odoo_Orders::handle_retry_attempt($order_ids, $update, $retry_attempt, $function_name);
    }
}
