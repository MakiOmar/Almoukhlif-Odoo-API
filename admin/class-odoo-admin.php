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
        // Load admin includes
        require_once ODOO_PLUGIN_DIR . 'admin/includes/class-odoo-filters.php';
        
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_bar_menu', array(__CLASS__, 'add_admin_bar_items'), 100);
        
        // Clear cache when orders are processed
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'clear_admin_bar_cache'));
        add_action('woocommerce_new_order', array(__CLASS__, 'clear_admin_bar_cache'));
        add_action('woocommerce_order_refunded', array(__CLASS__, 'clear_admin_bar_cache'));
        
        // Clear cache when Odoo status is updated
        add_action('updated_post_meta', array(__CLASS__, 'maybe_clear_admin_bar_cache'), 10, 4);
        add_action('added_post_meta', array(__CLASS__, 'maybe_clear_admin_bar_cache'), 10, 4);
        
        // Handle admin actions
        add_action('admin_init', array(__CLASS__, 'handle_admin_actions'));
        
        // Initialize activity debug (must be before admin_menu hook)
        add_action('admin_menu', array(__CLASS__, 'init_activity_debug'), 5);
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
            '',
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'odoo-orders',
            'Sent Orders',
            'Sent Orders',
            'manage_woocommerce',
            'odoo-orders-with-meta',
            array(__CLASS__, 'render_sent_orders_page')
        );

        add_submenu_page(
            'odoo-orders',
            'Orders Without Odoo Status',
            'Missing Status Orders',
            'manage_woocommerce',
            'odoo-missing-status-orders',
            array(__CLASS__, 'render_missing_status_orders_page')
        );

        add_submenu_page(
            'odoo-orders',
            'All Missing Status Orders',
            'All Missing Status Orders',
            'manage_woocommerce',
            'all-odoo-missing-status-orders',
            array(__CLASS__, 'render_all_missing_status_orders_page')
        );

        add_submenu_page(
            'odoo-orders',
            'Failed Odoo Orders',
            'Failed Orders',
            'manage_woocommerce',
            'odoo-failed-orders',
            array(__CLASS__, 'render_failed_orders_page')
        );

        add_submenu_page(
            'odoo-orders',
            'Order Activity Logs',
            'Activity Logs',
            'manage_woocommerce',
            'order-activity-logs',
            array(__CLASS__, 'render_order_activity_logs_page')
        );
        
        // Add debug page for administrators
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'odoo-orders',
                'Odoo Activity Debug',
                'Debug Tools',
                'manage_options',
                'odoo-activity-debug',
                array(__CLASS__, 'render_debug_page')
            );
        }

        // Remove Odoo Order Debug submenu page
        // (No code for Odoo Order Debug page here anymore)
    }
    
    /**
     * Add all admin bar notification items (colorized links)
     */
    public static function add_admin_bar_items($wp_admin_bar) {
        self::add_failed_orders_admin_bar_item($wp_admin_bar);
        self::add_missing_status_orders_admin_bar_item($wp_admin_bar);
        self::add_all_missing_status_orders_admin_bar_item($wp_admin_bar);
    }
    
    /**
     * Add failed orders admin bar item (red/green)
     */
    public static function add_failed_orders_admin_bar_item($wp_admin_bar) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Get cached count or calculate it
        $cache_key = 'odoo_failed_orders_count';
        $failed_count = get_transient($cache_key);
        
        if ($failed_count === false) {
            // Count failed orders (same logic as failed orders page)
            $always_excluded_statuses = array('wc-user-changed','wc-refunded','wc-cancel-request','wc-cancelled','wc-was-canceled','wc-completed','wc-custom-failed','wc-checkout-draft','wc-failed');
            $statuses = wc_get_order_statuses();
            foreach ($always_excluded_statuses as $status) unset($statuses[$status]);
            
            $failed_orders = get_posts(array(
                'post_type' => 'shop_order',
                'post_status' => array_keys($statuses),
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
            
            // Cache for 5 minutes
            set_transient($cache_key, $failed_count, 5 * MINUTE_IN_SECONDS);
        }

        $color = $failed_count > 0 ? 'red' : 'green';

        $wp_admin_bar->add_node(array(
            'id' => 'failed_odoo_orders',
            'title' => sprintf('<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px;">%s (%d)</span>', $color, esc_html__('Failed Odoo Orders', 'text-domain'), $failed_count),
            'href' => admin_url('admin.php?page=odoo-failed-orders&paged=1'),
        ));
    }
    
    /**
     * Add missing status orders admin bar item (red/green)
     */
    public static function add_missing_status_orders_admin_bar_item($wp_admin_bar) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Get cached count or calculate it
        $cache_key = 'odoo_missing_status_orders_count';
        $count = get_transient($cache_key);
        
        if ($count === false) {
            // Count orders missing Odoo status
            $args = array(
                'post_type' => 'shop_order',
                'post_status' => array('wc-processing', 'wc-on-hold', 'wc-custom-status'),
                'posts_per_page' => -1,
                'date_query' => array(
                    array(
                        'after' => '2025-02-17',
                        'inclusive' => true
                    )
                ),
                'meta_query' => array(
                    array(
                        'key' => 'oodo-status',
                        'compare' => 'NOT EXISTS'
                    )
                ),
                'fields' => 'ids'
            );
            $orders = get_posts($args);
            $count = count($orders);
            
            // Cache for 5 minutes
            set_transient($cache_key, $count, 5 * MINUTE_IN_SECONDS);
        }

        $color = $count > 0 ? 'red' : 'green';

        $wp_admin_bar->add_node(array(
            'id' => 'missing_odoo_status_orders',
            'title' => sprintf('<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px;">%s (%d)</span>', $color, esc_html__('Orders Missing Odoo Status', 'text-domain'), $count),
            'href' => admin_url('admin.php?page=odoo-missing-status-orders'),
        ));
    }
    
    /**
     * Add all-missing status orders admin bar item (red/green)
     */
    public static function add_all_missing_status_orders_admin_bar_item($wp_admin_bar) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Get cached count or calculate it
        $cache_key = 'odoo_all_missing_status_orders_count';
        $count = get_transient($cache_key);
        
        if ($count === false) {
            // Count all orders missing Odoo status
            $always_excluded = array('wc-user-changed','wc-refunded','wc-cancel-request','wc-cancelled','wc-was-canceled','wc-completed','wc-custom-failed','wc-checkout-draft','wc-failed');
            $statuses = wc_get_order_statuses();
            foreach ($always_excluded as $status) unset($statuses[$status]);
            $args = array(
                'post_type' => 'shop_order',
                'post_status' => array_keys($statuses),
                'posts_per_page' => -1,
                'date_query' => array(
                    array(
                        'after' => '2025-02-17',
                        'inclusive' => true
                    )
                ),
                'meta_query' => array(
                    array(
                        'key' => 'oodo-status',
                        'compare' => 'NOT EXISTS'
                    )
                ),
                'fields' => 'ids'
            );
            $orders = get_posts($args);
            $count = count($orders);
            
            // Cache for 5 minutes
            set_transient($cache_key, $count, 5 * MINUTE_IN_SECONDS);
        }

        $color = $count > 0 ? 'red' : 'green';

        $wp_admin_bar->add_node(array(
            'id' => 'all_missing_odoo_status_orders',
            'title' => sprintf('<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px;">%s (%d)</span>', $color, esc_html__('All Missing Odoo Status Orders', 'text-domain'), $count),
            'href' => admin_url('admin.php?page=all-odoo-missing-status-orders'),
        ));
    }
    
    /**
     * Render sent orders page
     */
    public static function render_sent_orders_page() {
        require_once ODOO_PLUGIN_DIR . 'admin/pages/sent-orders.php';
        if (function_exists('display_odoo_orders_with_meta_page')) {
            display_odoo_orders_with_meta_page();
        }
    }
    
    /**
     * Render missing status orders page
     */
    public static function render_missing_status_orders_page() {
        require_once ODOO_PLUGIN_DIR . 'admin/pages/missing-status-orders.php';
        if (function_exists('display_odoo_missing_status_orders_page')) {
            display_odoo_missing_status_orders_page();
        }
    }
    
    /**
     * Render all missing status orders page
     */
    public static function render_all_missing_status_orders_page() {
        require_once ODOO_PLUGIN_DIR . 'admin/pages/all-missing-status-orders.php';
        if (function_exists('display_all_odoo_missing_status_orders_page')) {
            display_all_odoo_missing_status_orders_page();
        }
    }
    
    /**
     * Render failed orders page
     */
    public static function render_failed_orders_page() {
        require_once ODOO_PLUGIN_DIR . 'admin/pages/failed-orders.php';
        if (function_exists('display_odoo_failed_orders_page')) {
            display_odoo_failed_orders_page();
        }
    }
    
    /**
     * Render order activity logs page
     */
    public static function render_order_activity_logs_page() {
        require_once ODOO_PLUGIN_DIR . 'admin/pages/order-activity-logs.php';
        if (function_exists('display_order_activity_logs_page')) {
            display_order_activity_logs_page();
        }
    }
    
    /**
     * Clear admin bar count caches
     */
    public static function clear_admin_bar_cache() {
        delete_transient('odoo_failed_orders_count');
        delete_transient('odoo_missing_status_orders_count');
        delete_transient('odoo_all_missing_status_orders_count');
    }
    
    /**
     * Clear admin bar cache if oodo-status meta was updated
     */
    public static function maybe_clear_admin_bar_cache($meta_id, $post_id, $meta_key, $meta_value) {
        if ($meta_key === 'oodo-status') {
            self::clear_admin_bar_cache();
        }
    }
    
    /**
     * Manually clear admin bar cache (can be called from admin pages)
     */
    public static function clear_cache() {
        self::clear_admin_bar_cache();
    }
    
    /**
     * Handle admin actions for cache management
     */
    public static function handle_admin_actions() {
        if (isset($_GET['action']) && $_GET['action'] === 'clear_odoo_cache' && 
            isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_odoo_cache')) {
            
            self::clear_admin_bar_cache();
            
            // Redirect back with success message
            wp_redirect(add_query_arg('cache_cleared', '1', remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
    }
    
    /**
     * Initialize activity debug functionality
     */
    public static function init_activity_debug() {
        // Only load for administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Load debug class if not already loaded
        if (!class_exists('Odoo_Activity_Debug')) {
            $activity_debug_file = ODOO_PLUGIN_DIR . 'utils/class-odoo-activity-debug.php';
            if (file_exists($activity_debug_file)) {
                require_once $activity_debug_file;
            }
        }
        
        // Initialize debug functionality
        if (class_exists('Odoo_Activity_Debug')) {
            Odoo_Activity_Debug::init();
        }
    }
    
    /**
     * Render debug page
     */
    public static function render_debug_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Load debug class if not already loaded
        if (!class_exists('Odoo_Activity_Debug')) {
            $activity_debug_file = ODOO_PLUGIN_DIR . 'utils/class-odoo-activity-debug.php';
            if (file_exists($activity_debug_file)) {
                require_once $activity_debug_file;
            }
        }
        
        // Render debug page
        if (class_exists('Odoo_Activity_Debug')) {
            Odoo_Activity_Debug::render_debug_page();
        } else {
            echo '<div class="wrap">';
            echo '<h1>Odoo Activity Debug</h1>';
            echo '<div class="notice notice-error"><p>Debug class not available.</p></div>';
            echo '</div>';
        }
    }
} 