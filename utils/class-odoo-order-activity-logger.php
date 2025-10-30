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
        
        // Hook into admin order actions (disabled - don't log order views)
        // add_action('woocommerce_admin_order_actions', array(__CLASS__, 'log_admin_order_actions'), 10, 2);
        
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
        // Validate inputs to prevent conflicts with other plugins
        if (!$order_id || !is_numeric($order_id)) {
            return;
        }
        
        if (!is_string($old_status) || !is_string($new_status)) {
            return;
        }
        
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
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
     * @return array Actions array (unchanged)
     */
    public static function log_admin_order_actions($actions, $order) {
        // Validate inputs to prevent conflicts
        if (!is_array($actions)) {
            return array();
        }
        
        if (!$order || !is_a($order, 'WC_Order')) {
            return $actions;
        }
        
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
        
        // Always return the actions array unchanged
        return $actions;
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
     * Write activity log to file with improved hierarchical structure
     * 
     * @param array $activity_data Activity data to log
     */
    public static function write_activity_log($activity_data) {
        // Validate input to prevent conflicts
        if (!is_array($activity_data)) {
            return;
        }
        
        // Create hierarchical logs directory structure
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        // Create date-based folder structure: YYYY/MM/DD/
        $date = current_time('Y-m-d');
        $date_parts = explode('-', $date);
        $year_dir = $logs_dir . '/' . $date_parts[0];
        $month_dir = $year_dir . '/' . $date_parts[1];
        $day_dir = $month_dir . '/' . $date_parts[2];
        
        // Create directories if they don't exist
        if (!file_exists($year_dir)) {
            wp_mkdir_p($year_dir);
        }
        if (!file_exists($month_dir)) {
            wp_mkdir_p($month_dir);
        }
        if (!file_exists($day_dir)) {
            wp_mkdir_p($day_dir);
        }
        
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
        
        // Write to order-specific file for better performance
        $order_id = $activity_data['order_id'] ?? 'system';
        $order_log_file = $day_dir . '/order-' . $order_id . '.log';
        
        // Write to file
        $log_line = json_encode($log_entry) . "\n";
        file_put_contents($order_log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Also maintain a daily summary file for quick overview
        $daily_summary_file = $day_dir . '/daily-summary.log';
        $summary_entry = array(
            'timestamp' => $activity_data['timestamp'],
            'order_id' => $activity_data['order_id'] ?? null,
            'activity_type' => $activity_data['activity_type'],
            'user_id' => $activity_data['user_id'],
            'trigger_source' => $activity_data['trigger_source']
        );
        $summary_line = json_encode($summary_entry) . "\n";
        file_put_contents($daily_summary_file, $summary_line, FILE_APPEND | LOCK_EX);
        
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
    public static function get_user_info() {
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
    public static function detect_trigger_source() {
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
    public static function get_client_ip() {
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
    public static function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    }
    
    /**
     * Get backtrace information for debugging
     * 
     * @return array Backtrace info
     */
    public static function get_backtrace_info() {
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
     * Get activity logs for a specific order with improved performance
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
        $date_parts = explode('-', $date);
        $day_dir = $logs_dir . '/' . $date_parts[0] . '/' . $date_parts[1] . '/' . $date_parts[2];
        
        // Try to get from order-specific file first (much faster)
        $order_log_file = $day_dir . '/order-' . $order_id . '.log';
        
        if (file_exists($order_log_file)) {
            $logs = array();
            $lines = file($order_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $log_entry = json_decode($line, true);
                if ($log_entry) {
                    $logs[] = $log_entry;
                }
            }
            
            return $logs;
        }
        
        // Fallback to legacy format if new structure doesn't exist
        $legacy_log_file = $logs_dir . '/order-activity-' . $date . '.log';
        
        if (!file_exists($legacy_log_file)) {
            return array();
        }
        
        $logs = array();
        $lines = file($legacy_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry && isset($log_entry['order_id']) && $log_entry['order_id'] == $order_id) {
                $logs[] = $log_entry;
            }
        }
        
        return $logs;
    }
    
    /**
     * Get all activity logs for a date range with improved performance
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
        
        // Check if we have order_id filter for optimized lookup
        $order_id_filter = isset($filters['order_id']) ? $filters['order_id'] : null;
        
        for ($date = clone $start; $date <= $end; $date->add(new DateInterval('P1D'))) {
            $date_str = $date->format('Y-m-d');
            $date_parts = explode('-', $date_str);
            $day_dir = $logs_dir . '/' . $date_parts[0] . '/' . $date_parts[1] . '/' . $date_parts[2];
            
            // If we have order_id filter, use optimized lookup
            if ($order_id_filter && file_exists($day_dir)) {
                $order_log_file = $day_dir . '/order-' . $order_id_filter . '.log';
                if (file_exists($order_log_file)) {
                    $lines = file($order_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $log_entry = json_decode($line, true);
                        if ($log_entry && self::matches_filters($log_entry, $filters)) {
                            $logs[] = $log_entry;
                        }
                    }
                }
            } else {
                // Use daily summary file for quick overview or scan all order files
                $daily_summary_file = $day_dir . '/daily-summary.log';
                if (file_exists($daily_summary_file)) {
                    $lines = file($daily_summary_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $summary_entry = json_decode($line, true);
                        if ($summary_entry && self::matches_filters($summary_entry, $filters)) {
                            // Get full log entry from order-specific file
                            $order_log_file = $day_dir . '/order-' . $summary_entry['order_id'] . '.log';
                            if (file_exists($order_log_file)) {
                                $order_lines = file($order_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                foreach ($order_lines as $order_line) {
                                    $log_entry = json_decode($order_line, true);
                                    if ($log_entry && $log_entry['timestamp'] === $summary_entry['timestamp']) {
                                        $logs[] = $log_entry;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // Fallback to legacy format
                    $legacy_log_file = $logs_dir . '/order-activity-' . $date_str . '.log';
                    if (file_exists($legacy_log_file)) {
                        $lines = file($legacy_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        foreach ($lines as $line) {
                            $log_entry = json_decode($line, true);
                            if ($log_entry && self::matches_filters($log_entry, $filters)) {
                                $logs[] = $log_entry;
                            }
                        }
                    }
                }
            }
        }
        
        return $logs;
    }
	
	/**
	 * Get activity logs for a specific order across all dates
	 *
	 * Scans the hierarchical logs directory (YYYY/MM/DD) and aggregates
	 * entries from per-day `order-{id}.log` files. Applies optional
	 * additional filters like activity_type, user_id, trigger_source.
	 * Falls back to legacy daily files if present.
	 *
	 * @param int $order_id Order ID
	 * @param array $filters Optional filters to apply on each entry
	 * @return array Activity logs
	 */
	public static function get_activity_logs_for_order_all_dates($order_id, $filters = array()) {
		$logs = array();
		$logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
		
		if (!is_numeric($order_id) || intval($order_id) <= 0) {
			return $logs;
		}
		$order_id = intval($order_id);
		
		// Scan hierarchical structure: /YYYY/MM/DD/order-{id}.log
		if (file_exists($logs_dir)) {
			$year_dirs = glob($logs_dir . '/[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR) ?: array();
			foreach ($year_dirs as $year_dir) {
				$month_dirs = glob($year_dir . '/[0-9][0-9]', GLOB_ONLYDIR) ?: array();
				foreach ($month_dirs as $month_dir) {
					$day_dirs = glob($month_dir . '/[0-9][0-9]', GLOB_ONLYDIR) ?: array();
					foreach ($day_dirs as $day_dir) {
						$order_log_file = $day_dir . '/order-' . $order_id . '.log';
						if (file_exists($order_log_file)) {
							$lines = file($order_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
							foreach ($lines as $line) {
								$log_entry = json_decode($line, true);
								if ($log_entry && self::matches_filters($log_entry, $filters)) {
									$logs[] = $log_entry;
								}
							}
						}
					}
				}
			}
		}
		
		// Fallback: scan legacy daily files order-activity-YYYY-MM-DD.log
		$legacy_files = glob($logs_dir . '/order-activity-*.log') ?: array();
		foreach ($legacy_files as $legacy_file) {
			$lines = file($legacy_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				$log_entry = json_decode($line, true);
				if ($log_entry && isset($log_entry['order_id']) && intval($log_entry['order_id']) === $order_id) {
					if (self::matches_filters($log_entry, $filters)) {
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
    
    /**
     * Get log file statistics for performance monitoring
     * 
     * @param string $date Date in Y-m-d format (optional)
     * @return array Statistics
     */
    public static function get_log_statistics($date = null) {
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        $date_parts = explode('-', $date);
        $day_dir = $logs_dir . '/' . $date_parts[0] . '/' . $date_parts[1] . '/' . $date_parts[2];
        
        $stats = array(
            'date' => $date,
            'new_structure_exists' => file_exists($day_dir),
            'legacy_structure_exists' => file_exists($logs_dir . '/order-activity-' . $date . '.log'),
            'order_files_count' => 0,
            'total_entries' => 0,
            'total_size' => 0,
            'performance_improvement' => 'unknown'
        );
        
        if (file_exists($day_dir)) {
            $order_files = glob($day_dir . '/order-*.log');
            $stats['order_files_count'] = count($order_files);
            
            foreach ($order_files as $file) {
                $stats['total_size'] += filesize($file);
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $stats['total_entries'] += count($lines);
            }
            
            // Check daily summary
            $summary_file = $day_dir . '/daily-summary.log';
            if (file_exists($summary_file)) {
                $stats['summary_entries'] = count(file($summary_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            }
        }
        
        // Compare with legacy structure
        $legacy_file = $logs_dir . '/order-activity-' . $date . '.log';
        if (file_exists($legacy_file)) {
            $legacy_size = filesize($legacy_file);
            $legacy_entries = count(file($legacy_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            
            if ($stats['total_entries'] > 0 && $legacy_entries > 0) {
                $improvement = (($legacy_entries - $stats['order_files_count']) / $legacy_entries) * 100;
                $stats['performance_improvement'] = round($improvement, 2) . '% fewer files to scan';
            }
        }
        
        return $stats;
    }
    
    /**
     * Migrate legacy log files to new hierarchical structure
     * 
     * @param string $date Date to migrate (optional, defaults to today)
     * @return array Migration results
     */
    public static function migrate_legacy_logs($date = null) {
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        $legacy_file = $logs_dir . '/order-activity-' . $date . '.log';
        
        if (!file_exists($legacy_file)) {
            return array('success' => false, 'message' => 'No legacy log file found for date: ' . $date);
        }
        
        $results = array(
            'success' => true,
            'date' => $date,
            'migrated_entries' => 0,
            'created_files' => 0,
            'errors' => array()
        );
        
        $lines = file($legacy_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);
            if ($log_entry) {
                // Use the new write method to create hierarchical structure
                $activity_data = array(
                    'timestamp' => $log_entry['timestamp'],
                    'activity_type' => $log_entry['activity_type'],
                    'order_id' => $log_entry['order_id'],
                    'user_id' => $log_entry['user_id'],
                    'user_info' => $log_entry['user_info'],
                    'trigger_source' => $log_entry['trigger_source'],
                    'ip_address' => $log_entry['ip_address'],
                    'user_agent' => $log_entry['user_agent']
                );
                
                // Add any additional data
                if (isset($log_entry['data'])) {
                    $activity_data = array_merge($activity_data, $log_entry['data']);
                }
                
                self::write_activity_log($activity_data);
                $results['migrated_entries']++;
            }
        }
        
        // Get statistics after migration
        $stats = self::get_log_statistics($date);
        $results['created_files'] = $stats['order_files_count'];
        
        return $results;
    }
    
    /**
     * Clean up old log files based on retention policy
     * 
     * @param int $days_to_keep Number of days to keep logs (default: 365)
     * @return array Cleanup results
     */
    public static function cleanup_old_logs($days_to_keep = 365) {
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        $cutoff_date = date('Y-m-d', strtotime("-{$days_to_keep} days"));
        
        $results = array(
            'cutoff_date' => $cutoff_date,
            'deleted_directories' => 0,
            'deleted_files' => 0,
            'errors' => array()
        );
        
        // Clean up hierarchical structure
        if (file_exists($logs_dir)) {
            $year_dirs = glob($logs_dir . '/[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR);
            
            foreach ($year_dirs as $year_dir) {
                $year = basename($year_dir);
                $month_dirs = glob($year_dir . '/[0-9][0-9]', GLOB_ONLYDIR);
                
                foreach ($month_dirs as $month_dir) {
                    $month = basename($month_dir);
                    $day_dirs = glob($month_dir . '/[0-9][0-9]', GLOB_ONLYDIR);
                    
                    foreach ($day_dirs as $day_dir) {
                        $day = basename($day_dir);
                        $date_str = $year . '-' . $month . '-' . $day;
                        
                        if ($date_str < $cutoff_date) {
                            // Delete entire day directory
                            $files = glob($day_dir . '/*');
                            foreach ($files as $file) {
                                if (unlink($file)) {
                                    $results['deleted_files']++;
                                }
                            }
                            
                            if (rmdir($day_dir)) {
                                $results['deleted_directories']++;
                            }
                        }
                    }
                    
                    // Remove empty month directories
                    if (count(glob($month_dir . '/*')) === 0) {
                        rmdir($month_dir);
                    }
                }
                
                // Remove empty year directories
                if (count(glob($year_dir . '/*')) === 0) {
                    rmdir($year_dir);
                }
            }
        }
        
        // Also clean up legacy files
        $legacy_files = glob($logs_dir . '/order-activity-*.log');
        foreach ($legacy_files as $file) {
            $filename = basename($file);
            if (preg_match('/order-activity-(\d{4}-\d{2}-\d{2})\.log/', $filename, $matches)) {
                $file_date = $matches[1];
                if ($file_date < $cutoff_date) {
                    if (unlink($file)) {
                        $results['deleted_files']++;
                    }
                }
            }
        }
        
        return $results;
    }
} 