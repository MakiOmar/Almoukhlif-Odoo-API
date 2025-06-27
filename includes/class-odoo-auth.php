<?php
/**
 * Odoo Authentication Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Auth {
    
    /**
     * Get Odoo authentication token, storing it in a transient for 24 hours.
     *
     * @param int $retry_attempt Current retry attempt
     * @return string|false The authentication token, or false on failure.
     */
    public static function get_auth_token($retry_attempt = 0) {
        $transient_key = 'odoo_auth_token';
        $token = get_transient($transient_key);

        if (!$token) {
            // Odoo API authentication endpoint.
            $auth_url = ODOO_BASE . 'web/session/erp_authenticate';

            // Authentication request body.
            $auth_body = wp_json_encode(
                array(
                    'params' => array(
                        'db'       => ODOO_DATABASE,
                        'login'    => ODOO_LOGIN,
                        'password' => ODOO_PASS,
                    ),
                )
            );

            // Send the authentication request.
            $auth_response = wp_remote_post(
                $auth_url,
                array(
                    'headers' => array('Content-Type' => 'application/json'),
                    'body'    => $auth_body,
                    'timeout' => 20,
                )
            );

            // Check for errors in the response.
            if (!is_wp_error($auth_response)) {
                $auth_body_response = wp_remote_retrieve_body($auth_response);
                $auth_data = json_decode($auth_body_response);
                
                // Check if the token exists in the response.
                if (isset($auth_data->result->token)) {
                    $token = $auth_data->result->token;
                    set_transient($transient_key, $token, DAY_IN_SECONDS);
                    return $token;
                }
            } else {
                // Simple retry mechanism for authentication - try again up to 2 times
                if ($retry_attempt < 2) {
                    if (function_exists('teamlog')) {
                        teamlog("Auth retry attempt " . ($retry_attempt + 1) . ": " . $auth_response->get_error_message());
                    }
                    
                    // Wait a bit before retrying
                    $delay = pow(2, $retry_attempt) + rand(0, 1000) / 1000; // 2, 4 seconds + random jitter
                    sleep($delay);
                    
                    // Recursive call with incremented retry attempt
                    return self::get_auth_token($retry_attempt + 1);
                } else {
                    // Max retries reached
                    if (function_exists('teamlog')) {
                        teamlog("Auth max retries reached: " . $auth_response->get_error_message());
                    }
                }
            }
        }
        
        return $token;
    }
    
    /**
     * Clear the authentication token
     */
    public static function clear_auth_token() {
        delete_transient('odoo_auth_token');
    }
    
    /**
     * Check if authentication token is valid
     * 
     * @return bool True if token exists and is valid
     */
    public static function is_token_valid() {
        $token = self::get_auth_token();
        return !empty($token);
    }
} 