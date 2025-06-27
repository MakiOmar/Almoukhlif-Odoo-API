<?php
/**
 * Odoo Admin Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Admin {
    
    /**
     * Initialize admin functionality
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_bar_menu', array(__CLASS__, 'add_admin_bar_notification'), 100);
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_menu_page(
            'Odoo Orders',
            'Odoo Orders',
            'manage_woocommerce',
            'odoo-orders',
            array(__CLASS__, 'render_sent_orders_page'),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'odoo-orders',
            'Sent Orders',
            'Sent Orders',
            'manage_woocommerce',
            'odoo-orders',
            array(__CLASS__, 'render_sent_orders_page')
        );

        add_submenu_page(
            'odoo-orders',
            'Failed Orders',
            'Failed Orders',
            'manage_woocommerce',
            'failed-orders',
            array(__CLASS__, 'render_failed_orders_page')
        );

        add_submenu_page(
            'odoo-orders',
            'Not Sent Orders',
            'Not Sent Orders',
            'manage_woocommerce',
            'not-sent-orders',
            array(__CLASS__, 'render_not_sent_orders_page')
        );

        add_submenu_page(
            'odoo-orders',
            'All Not Sent Orders',
            'All Not Sent Orders',
            'manage_woocommerce',
            'not-sent-orders-all',
            array(__CLASS__, 'render_not_sent_orders_all_page')
        );
    }
    
    /**
     * Add admin bar notification
     */
    public static function add_admin_bar_notification($wp_admin_bar) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Count failed orders
        $failed_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => 'oodo-status',
                    'value' => 'failed',
                    'compare' => '='
                )
            ),
            'numberposts' => -1,
            'fields' => 'ids'
        ));

        $failed_count = count($failed_orders);

        if ($failed_count > 0) {
            $wp_admin_bar->add_menu(array(
                'id' => 'odoo-failed-orders',
                'title' => sprintf('Odoo Failed Orders (%d)', $failed_count),
                'href' => admin_url('admin.php?page=failed-orders'),
                'meta' => array(
                    'class' => 'odoo-failed-orders-notification'
                )
            ));
        }
    }
    
    /**
     * Render sent orders page
     */
    public static function render_sent_orders_page() {
        require_once plugin_dir_path(__FILE__) . 'pages/sent-orders.php';
    }
    
    /**
     * Render failed orders page
     */
    public static function render_failed_orders_page() {
        require_once plugin_dir_path(__FILE__) . 'pages/failed-orders.php';
    }
    
    /**
     * Render not sent orders page
     */
    public static function render_not_sent_orders_page() {
        require_once plugin_dir_path(__FILE__) . 'pages/not-sent-orders.php';
    }
    
    /**
     * Render all not sent orders page
     */
    public static function render_not_sent_orders_all_page() {
        require_once plugin_dir_path(__FILE__) . 'pages/not-sent-orders-all.php';
    }
} 