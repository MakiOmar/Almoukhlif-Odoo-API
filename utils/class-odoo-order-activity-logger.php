<?php
/**
 * Odoo Order Activity Logger Class
 * 
 * Tracks all order status changes and activities for audit purposes
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Order_Activity_Logger {
    
    /**
     * Initialize the order activity logger
     */
    public static function init() {
        // Hook into all possible order status change triggers
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'log_order_status_change'), 10, 4);
        add_action('woocommerce_order_status_pending', array(__CLASS__, 'log_order_status_action'), 10, 1);
        add_action('woocommerce_order_status_failed', array(__CLASS__, 'log_order_status_action'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array(__CLASS__, 'log_order_status_action'), 10, 1);
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'log_order_status_action'), 10, 1);
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'log_order_status_action'), 10, 1);
        add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'log_order_status_action'), 10, 1);
        add_action('woocommerce_order_status_refunded', array(__CLASS__, 'log_order_status_action'), 10, 1);
        
        // Hook into order creation
        add_action('woocommerce_new_order', array(__CLASS__, 'log_order_created'), 10, 1);
        
        // Hook into order updates
        add_action('woocommerce_process_shop_order_meta', array(__CLASS__, 'log_order_updated'), 10, 2);
        
        // Hook into REST API order status changes
        add_action('rest_api_init', array(__CLASS__, 'hook_rest_api_logging'));
        
        // Hook into admin order actions
        add_action('woocommerce_admin_order_actions', array(__CLASS__, 'log_admin_order_actions'), 10, 2);
        
        // Hook into bulk actions
        add_action('handle_bulk_actions-edit-shop_order', array(__CLASS__, 'log_bulk_action'), 10, 3);
        
        // Hook into AJAX actions
        add_action('wp_ajax_sync_order_to_odoo', array(__CLASS__, 'log_ajax_action'), 5);
        
        // Hook into Odoo-specific actions
        add_action('odoo_order_sent', array(__CLASS__, 'log_odoo_order_sent'), 10, 2);
        add_action('odoo_order_failed', array(__CLASS__, 'log_odoo_order_failed'), 10, 2);
        add_action('odoo_order_cancelled', array(__CLASS__, 'log_odoo_order_cancelled'), 10, 2);
    }
    
    /**
     * Log order status change with detailed information
     * 
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param WC_Order $order Order object
     */
    public static function log_order_status_change($order_id, $old_status, $new_status, $order) {
        $activity_data = array(
            'order_id' => $order_id,
            'activity_type' => 'status_change',
            'old_status' => $old_status,
            'new_status' => $new_status,
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => self::detect_trigger_source(),
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'backtrace' => self::get_backtrace_info(),
            'order_data' => array(
                'order_number' => $order->get_order_number(),
                'customer_id' => $order->get_customer_id(),
                'customer_email' => $order->get_billing_email(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'payment_method' => $order->get_payment_method(),
                'shipping_method' => $order->get_shipping_method()
            )
        );
        
        self::write_activity_log($activity_data);
        
        // Also log to Odoo logger for consistency
        $message = sprintf(
            'Order #%s status changed from "%s" to "%s" by %s via %s',
            $order_id,
            $old_status,
            $new_status,
            $activity_data['user_info']['display_name'],
            $activity_data['trigger_source']
        );
        
        Odoo_Logger::info($message);
    }
    
    /**
     * Log specific order status action
     * 
     * @param int $order_id Order ID
     */
    public static function log_order_status_action($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $activity_data = array(
            'order_id' => $order_id,
            'activity_type' => 'status_action',
            'status' => $order->get_status(),
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => self::detect_trigger_source(),
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'backtrace' => self::get_backtrace_info()
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Log order creation
     * 
     * @param int $order_id Order ID
     */
    public static function log_order_created($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $activity_data = array(
            'order_id' => $order_id,
            'activity_type' => 'order_created',
            'status' => $order->get_status(),
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => self::detect_trigger_source(),
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'backtrace' => self::get_backtrace_info(),
            'order_data' => array(
                'order_number' => $order->get_order_number(),
                'customer_id' => $order->get_customer_id(),
                'customer_email' => $order->get_billing_email(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'payment_method' => $order->get_payment_method(),
                'shipping_method' => $order->get_shipping_method()
            )
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Log order updates
     * 
     * @param int $order_id Order ID
     * @param WP_Post $post Post object
     */
    public static function log_order_updated($order_id, $post) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $activity_data = array(
            'order_id' => $order_id,
            'activity_type' => 'order_updated',
            'status' => $order->get_status(),
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => self::detect_trigger_source(),
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'backtrace' => self::get_backtrace_info()
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Hook REST API logging
     */
    public static function hook_rest_api_logging() {
        // Hook into REST API order status changes
        add_filter('woocommerce_rest_prepare_shop_order_object', array(__CLASS__, 'log_rest_api_order_update'), 10, 3);
    }
    
    /**
     * Log REST API order updates
     * 
     * @param WP_REST_Response $response Response object
     * @param WC_Order $order Order object
     * @param WP_REST_Request $request Request object
     */
    public static function log_rest_api_order_update($response, $order, $request) {
        if ($request->get_method() === 'PUT' || $request->get_method() === 'POST') {
            $activity_data = array(
                'order_id' => $order->get_id(),
                'activity_type' => 'rest_api_update',
                'status' => $order->get_status(),
                'user_id' => get_current_user_id(),
                'user_info' => self::get_user_info(),
                'trigger_source' => 'REST API',
                'timestamp' => current_time('Y-m-d H:i:s'),
                'ip_address' => self::get_client_ip(),
                'user_agent' => self::get_user_agent(),
                'request_data' => $request->get_params()
            );
            
            self::write_activity_log($activity_data);
        }
        
        return $response;
    }
    
    /**
     * Log admin order actions
     * 
     * @param array $actions Actions array
     * @param WC_Order $order Order object
     */
    public static function log_admin_order_actions($actions, $order) {
        $activity_data = array(
            'order_id' => $order->get_id(),
            'activity_type' => 'admin_action_viewed',
            'status' => $order->get_status(),
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => 'Admin Panel',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent()
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Log bulk actions
     * 
     * @param string $redirect_to Redirect URL
     * @param string $action Action name
     * @param array $order_ids Order IDs
     */
    public static function log_bulk_action($redirect_to, $action, $order_ids) {
        $activity_data = array(
            'order_ids' => $order_ids,
            'activity_type' => 'bulk_action',
            'action' => $action,
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => 'Bulk Action',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent()
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Log AJAX actions
     */
    public static function log_ajax_action() {
        if (isset($_POST['order_id'])) {
            $order_id = intval($_POST['order_id']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                $activity_data = array(
                    'order_id' => $order_id,
                    'activity_type' => 'ajax_action',
                    'action' => 'sync_order_to_odoo',
                    'status' => $order->get_status(),
                    'user_id' => get_current_user_id(),
                    'user_info' => self::get_user_info(),
                    'trigger_source' => 'AJAX',
                    'timestamp' => current_time('Y-m-d H:i:s'),
                    'ip_address' => self::get_client_ip(),
                    'user_agent' => self::get_user_agent()
                );
                
                self::write_activity_log($activity_data);
            }
        }
    }
    
    /**
     * Log Odoo order sent
     * 
     * @param int $order_id Order ID
     * @param array $response Response data
     */
    public static function log_odoo_order_sent($order_id, $response) {
        $activity_data = array(
            'order_id' => $order_id,
            'activity_type' => 'odoo_order_sent',
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => 'Odoo Integration',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'odoo_response' => $response
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Log Odoo order failed
     * 
     * @param int $order_id Order ID
     * @param array $error Error data
     */
    public static function log_odoo_order_failed($order_id, $error) {
        $activity_data = array(
            'order_id' => $order_id,
            'activity_type' => 'odoo_order_failed',
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => 'Odoo Integration',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'error_data' => $error
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Log Odoo order cancelled
     * 
     * @param int $order_id Order ID
     * @param array $response Response data
     */
    public static function log_odoo_order_cancelled($order_id, $response) {
        $activity_data = array(
            'order_id' => $order_id,
            'activity_type' => 'odoo_order_cancelled',
            'user_id' => get_current_user_id(),
            'user_info' => self::get_user_info(),
            'trigger_source' => 'Odoo Integration',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'odoo_response' => $response
        );
        
        self::write_activity_log($activity_data);
    }
    
    /**
     * Write activity log to file
     * 
     * @param array $activity_data Activity data to log
     */
    private static function write_activity_log($activity_data) {
        // Create logs directory if it doesn't exist
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        // Create daily log file
        $date = current_time('Y-m-d');
        $log_file = $logs_dir . '/order-activity-' . $date . '.log';
        
        // Format log entry
        $log_entry = array(
            'timestamp' => $activity_data['timestamp'],
            'activity_type' => $activity_data['activity_type'],
            'order_id' => $activity_data['order_id'] ?? null,
            'user_id' => $activity_data['user_id'],
            'user_info' => $activity_data['user_info'],
            'trigger_source' => $activity_data['trigger_source'],
            'ip_address' => $activity_data['ip_address'],
            'user_agent' => $activity_data['user_agent'],
            'data' => array_diff_key($activity_data, array_flip(['timestamp', 'activity_type', 'order_id', 'user_id', 'user_info', 'trigger_source', 'ip_address', 'user_agent']))
        );
        
        // Write to file
        $log_line = json_encode($log_entry) . "\n";
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Also use teamlog if available
        if (function_exists('teamlog')) {
            $summary = sprintf(
                'Order Activity: %s - Order #%s - User: %s - Source: %s',
                $activity_data['activity_type'],
                $activity_data['order_id'] ?? 'N/A',
                $activity_data['user_info']['display_name'],
                $activity_data['trigger_source']
            );
            teamlog($summary);
        }
    }
    
    /**
     * Get current user information
     * 
     * @return array User information
     */
    private static function get_user_info() {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            $user = get_userdata($user_id);
            return array(
                'id' => $user_id,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles
            );
        }
        
        return array(
            'id' => 0,
            'username' => 'Guest',
            'display_name' => 'Guest User',
            'email' => '',
            'roles' => array()
        );
    }
    
    /**
     * Detect the source that triggered the action
     * 
     * @return string Trigger source
     */
    private static function detect_trigger_source() {
        if (wp_doing_ajax()) {
            return 'AJAX';
        }
        
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'REST API';
        }
        
        if (is_admin()) {
            return 'Admin Panel';
        }
        
        if (wp_doing_cron()) {
            return 'Cron Job';
        }
        
        if (defined('WP_CLI') && WP_CLI) {
            return 'WP-CLI';
        }
        
        return 'Frontend';
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
     * Get user agent
     * 
     * @return string User agent
     */
    private static function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    }
    
    /**
     * Get backtrace information for debugging
     * 
     * @return array Backtrace info
     */
    private static function get_backtrace_info() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $relevant_trace = array();
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && isset($trace['line'])) {
                $relevant_trace[] = array(
                    'file' => basename($trace['file']),
                    'line' => $trace['line'],
                    'function' => $trace['function'] ?? '',
                    'class' => $trace['class'] ?? ''
                );
            }
        }
        
        return $relevant_trace;
    }
    
    /**
     * Get activity logs for a specific order
     * 
     * @param int $order_id Order ID
     * @param string $date Date in Y-m-d format (optional)
     * @return array Activity logs
     */
    public static function get_order_activity_logs($order_id, $date = null) {
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        $log_file = $logs_dir . '/order-activity-' . $date . '.log';
        
        if (!file_exists($log_file)) {
            return array();
        }
        
        $logs = array();
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry && isset($log_entry['order_id']) && $log_entry['order_id'] == $order_id) {
                $logs[] = $log_entry;
            }
        }
        
        return $logs;
    }
    
    /**
     * Get all activity logs for a date range
     * 
     * @param string $start_date Start date in Y-m-d format
     * @param string $end_date End date in Y-m-d format
     * @param array $filters Optional filters
     * @return array Activity logs
     */
    public static function get_activity_logs($start_date, $end_date, $filters = array()) {
        $logs = array();
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        for ($date = $start; $date <= $end; $date->add(new DateInterval('P1D'))) {
            $log_file = $logs_dir . '/order-activity-' . $date->format('Y-m-d') . '.log';
            
            if (file_exists($log_file)) {
                $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                
                foreach ($lines as $line) {
                    $log_entry = json_decode($line, true);
                    if ($log_entry && self::matches_filters($log_entry, $filters)) {
                        $logs[] = $log_entry;
                    }
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Check if log entry matches filters
     * 
     * @param array $log_entry Log entry
     * @param array $filters Filters
     * @return bool Whether entry matches filters
     */
    private static function matches_filters($log_entry, $filters) {
        foreach ($filters as $key => $value) {
            if (isset($log_entry[$key]) && $log_entry[$key] != $value) {
                return false;
            }
        }
        return true;
    }
} 