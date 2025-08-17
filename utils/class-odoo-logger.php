<?php
/**
 * Odoo Logger Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Logger {
    
    /**
     * Log a message using available logging methods
     * 
     * @param string $message Message to log
     * @param string $level Log level (info, warning, error)
     * @return void
     */
    public static function log($message, $level = 'info') {
        // Try to use teamlog function if available
        if (function_exists('teamlog')) {
            teamlog($message);
            return;
        }
        
        // Fallback to custom Odoo logging
        $log_message = sprintf('[Odoo Integration] %s', $message);
        
        if (function_exists('odoo_log')) {
            odoo_log($log_message, $level);
        } else {
            // Ultimate fallback to WordPress error log
            error_log($log_message);
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message Message to log
     * @return void
     */
    public static function info($message) {
        self::log($message, 'info');
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Message to log
     * @return void
     */
    public static function warning($message) {
        self::log($message, 'warning');
    }
    
    /**
     * Log error message
     * 
     * @param string $message Message to log
     * @return void
     */
    public static function error($message) {
        self::log($message, 'error');
    }
    
    /**
     * Log debug message (only if WP_DEBUG is enabled)
     * 
     * @param string $message Message to log
     * @return void
     */
    public static function debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, 'debug');
        }
    }
    
    /**
     * Log API request/response data
     * 
     * @param string $endpoint API endpoint
     * @param array $request_data Request data
     * @param mixed $response Response data
     * @param bool $is_error Whether this is an error response
     * @return void
     */
    public static function log_api_call($endpoint, $request_data, $response, $is_error = false) {
        $level = $is_error ? 'error' : 'info';
        $message = sprintf(
            'API Call to %s - Request: %s - Response: %s',
            $endpoint,
            json_encode($request_data),
            is_wp_error($response) ? $response->get_error_message() : json_encode($response)
        );
        
        self::log($message, $level);
    }
} 