<?php

/**
 * Plugin Name: WordPress/Odoo Integration
 * Description: Integrates WooCommerce with Odoo to validate stock before adding products to the cart.
 * Version: 1.230
 * Author: Mohammad Omar
 *
 * @package Odoo
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ODOO_PLUGIN_FILE', __FILE__);
define('ODOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ODOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ODOO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Initialize the plugin with error handling
try {
    // Load core class
    $core_file = ODOO_PLUGIN_DIR . 'includes/class-odoo-core.php';
    if (!file_exists($core_file)) {
        throw new Exception('Core class file not found');
    }
    
    require_once $core_file;
    
    // Load order activity logger
    $activity_logger_file = ODOO_PLUGIN_DIR . 'utils/class-odoo-order-activity-logger.php';
    if (file_exists($activity_logger_file)) {
        require_once $activity_logger_file;
    }
    
    // Activity debug class will be loaded via admin_init hook when needed
    
    // Initialize core
    if (class_exists('Odoo_Core')) {
        $odoo_core = new Odoo_Core();
    } else {
        throw new Exception('Odoo_Core class not found');
    }
    
    // Initialize update checker on plugins_loaded hook
    add_action('plugins_loaded', function() use ($odoo_core) {
        $odoo_core->init_update_checker();
    });
    
    // Initialize admin
    $admin_file = ODOO_PLUGIN_DIR . 'admin/class-odoo-admin.php';
    if (file_exists($admin_file)) {
        require_once $admin_file;
        if (class_exists('Odoo_Admin')) {
            Odoo_Admin::init();
        }
    }
    
    // Initialize activity debug (will be done later via hooks)
    // Odoo_Activity_Debug::init() is called via admin_init hook
    
    // Initialize hooks
    $hooks_file = ODOO_PLUGIN_DIR . 'hooks/class-odoo-hooks.php';
    if (file_exists($hooks_file)) {
        require_once $hooks_file;
        if (class_exists('Odoo_Hooks')) {
            Odoo_Hooks::init();
        }
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("[Odoo Integration] Initialization error: " . $e->getMessage());
    
    // Add admin notice
    add_action('admin_notices', function() use ($e) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Odoo Integration Error:</strong> ' . esc_html($e->getMessage());
        echo '<br>Please check the error logs for more details.';
        echo '</p></div>';
    });
    
    return;
}

// Legacy function aliases for backward compatibility
if (!function_exists('get_odoo_auth_token')) {
    function get_odoo_auth_token($retry_attempt = 0) {
        if (class_exists('Odoo_Auth')) {
            return Odoo_Auth::get_auth_token($retry_attempt);
        }
        return false;
    }
}

if (!function_exists('check_odoo_stock')) {
    function check_odoo_stock($sku, $quantity, $product_id) {
        if (class_exists('Odoo_Stock')) {
            return Odoo_Stock::check_stock($sku, $quantity, $product_id);
        }
        return new WP_Error('class_not_found', 'Odoo_Stock class not available');
    }
}

if (!function_exists('odoo_check_stock_before_add_to_cart')) {
    function odoo_check_stock_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variation = null) {
        if (class_exists('Odoo_Stock')) {
            return Odoo_Stock::check_stock_before_add_to_cart($passed, $product_id, $quantity, $variation_id, $variation);
        }
        return $passed;
    }
}

if (!function_exists('update_odoo_stock')) {
    function update_odoo_stock($sku, $product = null) {
        if (class_exists('Odoo_Stock')) {
            return Odoo_Stock::update_stock($sku, $product);
        }
    }
}

if (!function_exists('process_odoo_order')) {
    function process_odoo_order($order_ids, &$orders_data, &$orders_temp, $update = false) {
        if (class_exists('Odoo_Orders')) {
            return Odoo_Orders::process_order_data($order_ids, $orders_data, $orders_temp, $update);
        }
    }
}

if (!function_exists('send_orders_batch_to_odoo')) {
    function send_orders_batch_to_odoo($order_ids, $update = false, $retry_attempt = 0) {
        if (class_exists('Odoo_Orders')) {
            return Odoo_Orders::send_batch($order_ids, $update, $retry_attempt);
        }
        return [];
    }
}

if (!function_exists('send_orders_batch_to_odoo_v2')) {
    function send_orders_batch_to_odoo_v2($order_ids, $retry_attempt = 0) {
        if (class_exists('Odoo_Orders')) {
            return Odoo_Orders::send_batch_ajax($order_ids, $retry_attempt);
        }
        return wp_send_json_error(array('message' => 'Odoo_Orders class not available'));
    }
}

if (!function_exists('cancel_odoo_order')) {
    function cancel_odoo_order($odoo_order_id, $order_id) {
        if (class_exists('Odoo_Helpers')) {
            return Odoo_Helpers::cancel_odoo_order($odoo_order_id, $order_id);
        }
    }
}

if (!function_exists('snks_validate_order_delivery_on_completion')) {
    function snks_validate_order_delivery_on_completion($order_id) {
        if (class_exists('Odoo_Helpers')) {
            return Odoo_Helpers::validate_order_delivery_on_completion($order_id);
        }
    }
}

if (!function_exists('update_odoo_order_status')) {
    function update_odoo_order_status($order_ids, $new_status = null) {
        if (class_exists('Odoo_Helpers')) {
            return Odoo_Helpers::update_odoo_order_status($order_ids, $new_status);
        }
        return false;
    }
}

if (!function_exists('odoo_get_total_coupon_discount')) {
    function odoo_get_total_coupon_discount($applied_coupons) {
        if (class_exists('Odoo_Helpers')) {
            return Odoo_Helpers::get_total_coupon_discount($applied_coupons);
        }
        return 0.0;
    }
}

if (!function_exists('is_gulf_country')) {
    function is_gulf_country($billing_country) {
        if (class_exists('Odoo_Helpers')) {
            return Odoo_Helpers::is_gulf_country($billing_country);
        }
        return false;
    }
}

if (!function_exists('display_odoo_order_id_in_admin')) {
    function display_odoo_order_id_in_admin($order) {
        if (class_exists('Odoo_Helpers')) {
            return Odoo_Helpers::display_odoo_order_id_in_admin($order);
        }
    }
}

if (!function_exists('send_to_odoo')) {
    function send_to_odoo($orders_data, $token) {
        if (class_exists('Odoo_API')) {
            return Odoo_API::send_to_odoo($orders_data, $token);
        }
        return new WP_Error('class_not_found', 'Odoo_API class not available');
    }
}

if (!function_exists('process_odoo_response_unified')) {
    function process_odoo_response_unified($response_data, $orders_temp, $update = false, $is_ajax = false) {
        if (class_exists('Odoo_Response')) {
            return Odoo_Response::process_unified($response_data, $orders_temp, $update, $is_ajax);
        }
        return ['success' => false, 'message' => 'Odoo_Response class not available'];
    }
}

if (!function_exists('process_single_order_response')) {
    function process_single_order_response($data, $order, $update = false) {
        if (class_exists('Odoo_Response')) {
            return Odoo_Response::process_single_order($data, $order, $update);
        }
        return ['success' => false, 'message' => 'Odoo_Response class not available'];
    }
}

if (!function_exists('update_order_products_stock')) {
    function update_order_products_stock($order, $update = false) {
        if (class_exists('Odoo_Response')) {
            return Odoo_Response::update_order_products_stock($order, $update);
        }
    }
}

if (!function_exists('handle_authentication_failure')) {
    function handle_authentication_failure($order_ids, $update = false) {
        if (class_exists('Odoo_Orders')) {
            return Odoo_Orders::handle_authentication_failure($order_ids, $update);
        }
    }
}

if (!function_exists('handle_retry_attempt')) {
    function handle_retry_attempt($order_ids, $update, $retry_attempt, $function_name) {
        if (class_exists('Odoo_Orders')) {
            return Odoo_Orders::handle_retry_attempt($order_ids, $update, $retry_attempt, $function_name);
        }
    }
}
