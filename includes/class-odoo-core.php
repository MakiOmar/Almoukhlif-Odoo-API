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
        // Load core classes
        require_once plugin_dir_path(__FILE__) . 'class-odoo-api.php';
        require_once plugin_dir_path(__FILE__) . 'class-odoo-auth.php';
        require_once plugin_dir_path(__FILE__) . 'class-odoo-stock.php';
        require_once plugin_dir_path(__FILE__) . 'class-odoo-orders.php';
        require_once plugin_dir_path(__FILE__) . 'class-odoo-response.php';
        
        // Load utility classes
        require_once plugin_dir_path(__FILE__) . '../utils/class-odoo-helpers.php';
        require_once plugin_dir_path(__FILE__) . '../utils/class-odoo-logger.php';
        
        // Load existing files
        require_once plugin_dir_path(__FILE__) . 'draft.php';
        require_once plugin_dir_path(__FILE__) . 'rest-api.php';
        
        // Load plugin update checker
        require plugin_dir_path(__FILE__) . '../plugin-update-checker/plugin-update-checker.php';
    }
    
    /**
     * Initialize plugin update checker
     */
    public function init_update_checker() {
        $anonyengine_update_checker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/MakiOmar/Almoukhlif-Odoo-API/',
            plugin_dir_path(__FILE__) . '../wp-odoo-integration.php',
            'wp-odoo-integration/wp-odoo-integration.php'
        );
        
        // Set the branch that contains the stable release
        $anonyengine_update_checker->setBranch('master');
    }
} 