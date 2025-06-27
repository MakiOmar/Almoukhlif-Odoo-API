<?php
/**
 * Core Odoo Integration Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Core {
    
    /**
     * Plugin version
     */
    const VERSION = '1.224';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_constants();
        $this->load_dependencies();
    }
    
    /**
     * Initialize plugin constants
     */
    private function init_constants() {
        $opts = get_option('Anony_Options');
        
        if (is_array($opts)) {
            define('ODOO_BASE', $opts['odoo_url']);
            define('ODOO_DATABASE', $opts['odoo_database']);
            define('ODOO_LOGIN', $opts['odoo_username']);
            define('ODOO_PASS', $opts['odoo_pass']);
            define('ODOO_LOCATION', absint($opts['odoo_location']));
        } else {
            define('ODOO_BASE', '');
            define('ODOO_DATABASE', '');
            define('ODOO_LOGIN', '');
            define('ODOO_PASS', '');
            define('ODOO_LOCATION', '');
        }
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        $plugin_dir = plugin_dir_path(__FILE__);
        
        // Load core classes with error handling
        $core_classes = [
            'class-odoo-api.php',
            'class-odoo-auth.php', 
            'class-odoo-stock.php',
            'class-odoo-orders.php',
            'class-odoo-response.php'
        ];
        
        foreach ($core_classes as $class_file) {
            $file_path = $plugin_dir . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                $this->log_error("Missing required file: {$class_file}");
            }
        }
        
        // Load utility classes with error handling
        $utils_dir = $plugin_dir . '../utils/';
        $utils_classes = [
            'class-odoo-helpers.php',
            'class-odoo-logger.php'
        ];
        
        foreach ($utils_classes as $class_file) {
            $file_path = $utils_dir . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                $this->log_error("Missing utility file: {$class_file}");
            }
        }
        
        // Load existing files
        $existing_files = [
            'draft.php',
            'rest-api.php'
        ];
        
        foreach ($existing_files as $file) {
            $file_path = $plugin_dir . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                $this->log_error("Missing existing file: {$file}");
            }
        }
        
        // Load plugin update checker
        $update_checker_path = $plugin_dir . '../plugin-update-checker/plugin-update-checker.php';
        if (file_exists($update_checker_path)) {
            require $update_checker_path;
        } else {
            $this->log_error("Missing plugin update checker file");
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private function log_error($message) {
        // Try to use WordPress error log
        error_log("[Odoo Integration Error] {$message}");
        
        // Try to use teamlog if available
        if (function_exists('teamlog')) {
            teamlog("Odoo Integration Error: {$message}");
        }
    }
    
    /**
     * Initialize plugin update checker
     */
    public function init_update_checker() {
        // Check if the update checker class exists
        if (!class_exists('Puc_v4_Factory')) {
            $this->log_error("Plugin update checker not available");
            return;
        }
        
        try {
            $anonyengine_update_checker = Puc_v4_Factory::buildUpdateChecker(
                'https://github.com/MakiOmar/Almoukhlif-Odoo-API/',
                plugin_dir_path(__FILE__) . '../wp-odoo-integration.php',
                'wp-odoo-integration/wp-odoo-integration.php'
            );
            
            // Set the branch that contains the stable release
            $anonyengine_update_checker->setBranch('master');
        } catch (Exception $e) {
            $this->log_error("Failed to initialize update checker: " . $e->getMessage());
        }
    }
} 