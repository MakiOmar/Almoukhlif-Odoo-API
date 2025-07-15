<?php
/**
 * Odoo Hooks Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Hooks {
    
    /**
     * Store original order status for comparison
     */
    private static $original_order_statuses = array();
    
    /**
     * Initialize all hooks
     */
    public static function init() {
        // Initialize order activity logger
        if (class_exists('Odoo_Order_Activity_Logger')) {
            Odoo_Order_Activity_Logger::init();
        }
        
        // Add compatibility fix for WooCommerce Order Status Manager plugin
        add_action('init', array(__CLASS__, 'add_order_status_manager_compatibility'), 5);
        
        // Stock validation hooks
        add_filter('woocommerce_add_to_cart_validation', array('Odoo_Stock', 'check_stock_before_add_to_cart'), 10, 5);
        
        // Order hooks
        add_action('woocommerce_checkout_order_created', array(__CLASS__, 'on_order_created'));
        add_action('woocommerce_process_shop_order_meta', array(__CLASS__, 'on_order_updated'), 99);
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'on_order_status_changed'), 10, 3);
        
        // Hook into post updates to catch ALL order status changes (including $order->update_status())
        add_action('post_updated', array(__CLASS__, 'on_post_updated'), 10, 3);
        
        // Hook into REST API to catch order status changes via API
        add_action('rest_api_init', array(__CLASS__, 'hook_rest_api_status_changes'));
        
        // Hook into WooCommerce order save to catch ALL order changes including update_status()
        add_action('woocommerce_before_shop_order_object_save', array(__CLASS__, 'on_before_order_save'), 10, 2);
        add_action('woocommerce_after_shop_order_object_save', array(__CLASS__, 'on_after_order_save'), 10, 2);
        
        // Admin hooks
        add_action('woocommerce_admin_order_data_after_billing_address', array('Odoo_Helpers', 'display_odoo_order_id_in_admin'), 10, 1);
        add_action('woocommerce_admin_order_data_after_order_details', array(__CLASS__, 'add_sync_button'));
        
        // AJAX hooks
        add_action('wp_ajax_sync_order_to_odoo', array(__CLASS__, 'handle_sync_ajax'));
        
        // Bulk actions - use lower priority to avoid conflicts with other plugins
        add_filter('bulk_actions-edit-shop_order', array(__CLASS__, 'register_bulk_action'), 20);
        add_filter('handle_bulk_actions-edit-shop_order', array(__CLASS__, 'handle_bulk_action'), 20, 3);
        add_action('admin_notices', array(__CLASS__, 'display_bulk_action_notice'));
        
        // Stock hooks
        add_filter('woocommerce_ajax_add_order_item_validation', array(__CLASS__, 'validate_ajax_add_order_item'), 10, 4);
        add_action('wpo_before_load_items', array(__CLASS__, 'validate_before_load_items'));
        
        // PDF hooks
        add_action('wpo_wcpdf_before_order_data', array(__CLASS__, 'add_odoo_number_to_pdf'), 10, 2);
        
        // Stock level hooks
        add_action('woocommerce_order_status_pending', array(__CLASS__, 'prevent_stock_increase'), 9);
        
        // Admin footer script
        add_action('admin_footer', array(__CLASS__, 'add_admin_script'));
    }
    
    /**
     * Handle order creation
     */
    public static function on_order_created($order) {
        Odoo_Orders::send_batch(array($order->get_id()));
    }
    
    /**
     * Handle order updates
     */
    public static function on_order_updated($order_id) {
        // Only run if current user is NOT a customer
        if (current_user_can('customer')) {
            return;
        }

        $odoo_order = get_post_meta($order_id, 'odoo_order', true);
        $update = true;

        if (!$odoo_order || empty($odoo_order)) {
            $update = false;
        }

        Odoo_Orders::send_batch(array($order_id), $update);
    }
    
    /**
     * Handle order status changes
     */
    public static function on_order_status_changed($order_id, $old_status, $new_status) {
        // Validate inputs to prevent conflicts with other plugins
        if (!$order_id || !is_numeric($order_id)) {
            return;
        }
        
        if (!is_string($old_status) || !is_string($new_status)) {
            return;
        }
        
        // Debug logging
        if (function_exists('teamlog')) {
            teamlog("Order status changed hook - Order #$order_id: $old_status -> $new_status");
        }
        
        // Check if the new status is 'cancelled'.
        if ('cancelled' === $new_status || 'was-canceled' === $new_status || 'wc-cancelled' === $new_status || 'custom-failed' === $new_status || 'failed' === $new_status) {
            $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);
            if ($odoo_order_id) {
                Odoo_Helpers::cancel_odoo_order($odoo_order_id, $order_id);
            }
        }

        if ('international-shi' === $new_status || 'was-shipped' === $new_status || 'received' === $new_status) {
            Odoo_Helpers::validate_order_delivery_on_completion($order_id);
        }
        
        Odoo_Helpers::update_odoo_order_status(array($order_id), $new_status);
    }
    
    /**
     * Handle post updates to catch ALL order status changes (including $order->update_status())
     * This hook catches changes that might bypass the standard WooCommerce hooks
     */
    public static function on_post_updated($post_id, $post_after, $post_before) {
        // Validate inputs to prevent conflicts with other plugins
        if (!$post_id || !is_numeric($post_id)) {
            return;
        }
        
        if (!$post_after || !is_object($post_after) || !$post_before || !is_object($post_before)) {
            return;
        }
        
        // Debug logging
        if (function_exists('teamlog')) {
            teamlog("Post updated hook - Post #$post_id: {$post_before->post_status} -> {$post_after->post_status}");
        }
        
        // Only process shop_order post types
        if ($post_after->post_type !== 'shop_order') {
            if (function_exists('teamlog')) {
                teamlog("Post updated hook - Post #$post_id: Not a shop_order, skipping");
            }
            return;
        }
        
        // Check if the post status changed
        if ($post_after->post_status === $post_before->post_status) {
            if (function_exists('teamlog')) {
                teamlog("Post updated hook - Post #$post_id: No status change, skipping");
            }
            return;
        }
        
        // Get the order object
        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }
        
        // Extract status without 'wc-' prefix for comparison
        $old_status = str_replace('wc-', '', $post_before->post_status);
        $new_status = str_replace('wc-', '', $post_after->post_status);
        
        // Only proceed if status actually changed
        if ($old_status === $new_status) {
            return;
        }
        
        // Log this status change through our activity logger
        if (class_exists('Odoo_Order_Activity_Logger')) {
            Odoo_Order_Activity_Logger::log_order_status_change($post_id, $old_status, $new_status, $order);
        }
        
        // Handle the same logic as on_order_status_changed
        if ('cancelled' === $new_status || 'was-canceled' === $new_status || 'wc-cancelled' === $new_status || 'custom-failed' === $new_status || 'failed' === $new_status) {
            $odoo_order_id = get_post_meta($post_id, 'odoo_order', true);
            if ($odoo_order_id) {
                Odoo_Helpers::cancel_odoo_order($odoo_order_id, $post_id);
            }
        }

        if ('international-shi' === $new_status || 'was-shipped' === $new_status || 'received' === $new_status) {
            Odoo_Helpers::validate_order_delivery_on_completion($post_id);
        }
        
        Odoo_Helpers::update_odoo_order_status(array($post_id), $new_status);
    }
    
    /**
     * Store original order status before save
     */
    public static function on_before_order_save($order, $data_store) {
        // Only process if this is a valid order
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        
        // Additional validation to prevent conflicts
        if (!method_exists($order, 'get_id')) {
            return;
        }
        
        $order_id = $order->get_id();
        
        // Store the original status before any changes
        $original_status = get_post_status($order_id);
        $original_status = str_replace('wc-', '', $original_status);
        
        self::$original_order_statuses[$order_id] = $original_status;
        
        // Debug logging
        if (function_exists('teamlog')) {
            teamlog("Before save - Order #$order_id: Original status = $original_status");
        }
    }
    
    /**
     * Handle WooCommerce order object save to catch ALL order changes including update_status()
     * This hook fires after any order object is saved, including status changes
     */
    public static function on_after_order_save($order, $data_store) {
        // Only process if this is a valid order
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        
        // Additional validation to prevent conflicts
        if (!method_exists($order, 'get_id') || !method_exists($order, 'get_status')) {
            return;
        }
        
        $order_id = $order->get_id();
        
        // Get the current status
        $current_status = $order->get_status();
        
        // Get the original status we stored before the save
        $original_status = isset(self::$original_order_statuses[$order_id]) ? self::$original_order_statuses[$order_id] : null;
        
        // Clean up the stored status
        unset(self::$original_order_statuses[$order_id]);
        
        // Debug logging
        if (function_exists('teamlog')) {
            teamlog("After save - Order #$order_id: Original = $original_status, Current = $current_status");
        }
        
        // Only proceed if we have an original status and it's different
        if ($original_status === null || $original_status === $current_status) {
            if (function_exists('teamlog')) {
                teamlog("After save - Order #$order_id: No status change detected (Original: $original_status, Current: $current_status)");
            }
            return;
        }
        
        // Log this status change through our activity logger
        if (class_exists('Odoo_Order_Activity_Logger')) {
            if (function_exists('teamlog')) {
                teamlog("After save - Order #$order_id: Logging status change from $original_status to $current_status");
            }
            Odoo_Order_Activity_Logger::log_order_status_change($order_id, $original_status, $current_status, $order);
        } else {
            if (function_exists('teamlog')) {
                teamlog("After save - Order #$order_id: Odoo_Order_Activity_Logger class not found");
            }
        }
        
        // Handle the same logic as on_order_status_changed
        if ('cancelled' === $current_status || 'was-canceled' === $current_status || 'wc-cancelled' === $current_status || 'custom-failed' === $current_status || 'failed' === $current_status) {
            $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);
            if ($odoo_order_id) {
                Odoo_Helpers::cancel_odoo_order($odoo_order_id, $order_id);
            }
        }

        if ('international-shi' === $current_status || 'was-shipped' === $current_status || 'received' === $current_status) {
            Odoo_Helpers::validate_order_delivery_on_completion($order_id);
        }
        
        Odoo_Helpers::update_odoo_order_status(array($order_id), $current_status);
    }
    
    /**
     * Hook into REST API to catch order status changes
     */
    public static function hook_rest_api_status_changes() {
        // Hook into REST API order updates
        add_filter('woocommerce_rest_prepare_shop_order_object', array(__CLASS__, 'log_rest_api_order_update'), 10, 3);
        add_action('woocommerce_rest_insert_shop_order_object', array(__CLASS__, 'on_rest_api_order_update'), 10, 3);
    }
    
    /**
     * Log REST API order updates
     */
    public static function log_rest_api_order_update($response, $order, $request) {
        // Log the REST API update through our activity logger
        if (class_exists('Odoo_Order_Activity_Logger')) {
            $order_id = $order->get_id();
            $activity_data = array(
                'order_id' => $order_id,
                'activity_type' => 'rest_api_update',
                'status' => $order->get_status(),
                'user_id' => get_current_user_id(),
                'user_info' => Odoo_Order_Activity_Logger::get_user_info(),
                'trigger_source' => 'REST API',
                'timestamp' => current_time('Y-m-d H:i:s'),
                'ip_address' => Odoo_Order_Activity_Logger::get_client_ip(),
                'user_agent' => Odoo_Order_Activity_Logger::get_user_agent(),
                'backtrace' => Odoo_Order_Activity_Logger::get_backtrace_info(),
                'request_data' => $request->get_params()
            );
            
            Odoo_Order_Activity_Logger::write_activity_log($activity_data);
        }
        
        return $response;
    }
    
    /**
     * Handle REST API order updates
     */
    public static function on_rest_api_order_update($order, $request, $creating) {
        // Only process if not creating (i.e., updating)
        if ($creating) {
            return;
        }
        
        $order_id = $order->get_id();
        
        // Check if status was changed via REST API
        $status_param = $request->get_param('status');
        if ($status_param && $status_param !== $order->get_status()) {
            // Handle the same logic as on_order_status_changed
            if ('cancelled' === $status_param || 'was-canceled' === $status_param || 'wc-cancelled' === $status_param || 'custom-failed' === $status_param || 'failed' === $status_param) {
                $odoo_order_id = get_post_meta($order_id, 'odoo_order', true);
                if ($odoo_order_id) {
                    Odoo_Helpers::cancel_odoo_order($odoo_order_id, $order_id);
                }
            }

            if ('international-shi' === $status_param || 'was-shipped' === $status_param || 'received' === $status_param) {
                Odoo_Helpers::validate_order_delivery_on_completion($order_id);
            }
            
            Odoo_Helpers::update_odoo_order_status(array($order_id), $status_param);
        }
    }
    
    /**
     * Add sync button to admin order page
     */
    public static function add_sync_button($order) {
        $order_id = $order->get_id();
        $nonce = wp_create_nonce('sync_order_to_odoo_' . $order_id);

        echo '<button id="sync-to-odoo" class="button button-primary" data-order-id="' . esc_attr($order_id) . '" data-nonce="' . esc_attr($nonce) . '">
            <span class="sync-text">Sync to Odoo</span>
            <span class="sync-loading" style="display:none;">Loading...</span>
        </button>';
    }
    
    /**
     * Handle AJAX sync request
     */
    public static function handle_sync_ajax() {
        // Validate order ID.
        if (empty($_POST['order_id']) || empty($_POST['nonce'])) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }

        $order_id = intval($_POST['order_id']);
        $nonce = sanitize_text_field($_POST['nonce']);

        // Verify nonce for security.
        if (!wp_verify_nonce($nonce, 'sync_order_to_odoo_' . $order_id)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Call the function to sync with Odoo.
        Odoo_Orders::send_batch_ajax(array($order_id));
    }
    
    /**
     * Register bulk action
     */
    public static function register_bulk_action($bulk_actions) {
        // Ensure $bulk_actions is an array to prevent conflicts with other plugins
        if (!is_array($bulk_actions)) {
            $bulk_actions = array();
        }
        
        // Add our bulk action
        $bulk_actions['send_to_odoo'] = 'إرسال إلى أودو';
        
        // Ensure we always return an array
        return is_array($bulk_actions) ? $bulk_actions : array();
    }
    
    /**
     * Handle bulk action
     */
    public static function handle_bulk_action($redirect_to, $action, $order_ids) {
        if ($action !== 'send_to_odoo') {
            return $redirect_to;
        }

        // Call the function to send orders
        $sent = Odoo_Orders::send_batch($order_ids);

        // Add a query argument to show success message
        $redirect_to = add_query_arg('sent_to_odoo', count($sent), $redirect_to);
        return $redirect_to;
    }
    
    /**
     * Display bulk action notice
     */
    public static function display_bulk_action_notice() {
        if (!empty($_GET['sent_to_odoo'])) {
            $count = intval($_GET['sent_to_odoo']);
            echo "<div class='updated'><p>تم إرسال {$count} طلب(ات) إلى أودو بنجاح!</p></div>";
        }
    }
    
    /**
     * Validate AJAX add order item
     */
    public static function validate_ajax_add_order_item($validation_error, $product, $order, $qty) {
        if (!$product) {
            return new WP_Error('invalid_product', __('Invalid product data.', 'woocommerce'));
        }

        $sku = $product->get_sku();
        $product_id = $product->get_id();
        $variation_id = $product->is_type('variation') ? $product_id : $product_id;

        $stock_check = Odoo_Stock::check_stock($sku, $qty, $variation_id);

        if (is_wp_error($stock_check)) {
            return $stock_check;
        }

        if (!$stock_check) {
            return new WP_Error('out_of_stock', __('مخزون المنتج غير متوفر بالكمية المطلوبة. يرجى تعديل الكمية أو اختيار منتج آخر.', 'woocommerce'));
        }

        return $validation_error;
    }
    
    /**
     * Validate before load items
     */
    public static function validate_before_load_items($request) {
        // Ensure the request contains items.
        if (!empty($request['items'])) {
            foreach ($request['items'] as $item) {
                $product_id = $item['id'];
                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;

                // Get the product object.
                $product = wc_get_product($product_id);
                if (!$product) {
                    echo json_encode(array('error' => 'Invalid product ID.'));
                    die();
                }
                
                // Determine if this is a variation or simple product.
                $sku = $product->get_sku();

                if ($product->is_type('variation')) {
                    // For variations, use the variation itself.
                    $variation_id = $product_id;
                } else {
                    // For simple products, use the product ID.
                    $variation_id = $product_id;
                }

                // Use the helper function to check stock with the multiplier.
                $stock_check = Odoo_Stock::check_stock($sku, $quantity, $variation_id);

                if (is_wp_error($stock_check)) {
                    echo json_encode(
                        array(
                            'error' => $stock_check->get_error_message(),
                        )
                    );
                    die();
                }

                if (!$stock_check) {
                    echo json_encode(
                        array(
                            'error' => 'مخزون المنتج غير متوفر بالكمية المطلوبة. يرجى تعديل الكمية أو اختيار منتج آخر.',
                        )
                    );
                    die();
                }
            }
        }
    }
    
    /**
     * Add Odoo number to PDF
     */
    public static function add_odoo_number_to_pdf($type, $order) {
        $odoo = get_post_meta($order->get_id(), 'odoo_order_number', true);
        if (!$odoo && $odoo === '') {
            return;
        }
        ?>
        <tr class="odoo-number">
            <th><?php _e('الرقم المرجعي :', 'woocommerce-pdf-invoices-packing-slips'); ?></th>
            <td><?php echo $odoo; ?></td>
        </tr>
        <?php
    }
    
    /**
     * Prevent stock increase for pending orders
     */
    public static function prevent_stock_increase($order_id) {
        $order = wc_get_order($order_id);

        if ($order->get_status() == 'pending') {
            remove_action('woocommerce_order_status_pending', 'wc_maybe_increase_stock_levels');
        }
    }
    
    /**
     * Add compatibility fix for WooCommerce Order Status Manager plugin
     */
    public static function add_order_status_manager_compatibility() {
        // Check if WooCommerce Order Status Manager is active
        if (!class_exists('WC_Order_Status_Manager')) {
            return;
        }
        
        // Add a filter to ensure custom_actions is always an array
        add_filter('woocommerce_admin_order_actions', function($actions, $order) {
            // Ensure actions is always an array
            if (!is_array($actions)) {
                $actions = array();
            }
            return $actions;
        }, 5, 2);
    }
    
    /**
     * Add admin script
     */
    public static function add_admin_script() {
        global $pagenow, $post;

        if ('post.php' !== $pagenow || 'shop_order' !== get_post_type($post)) {
            return;
        }
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('#sync-to-odoo').on('click', function(e) {
                    e.preventDefault();

                    var button = $(this);
                    var orderId = button.data('order-id');
                    var nonce = button.data('nonce');

                    // Show loading state
                    button.prop('disabled', true);
                    button.find('.sync-text').hide();
                    button.find('.sync-loading').show();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'sync_order_to_odoo',
                            order_id: orderId,
                            nonce: nonce
                        },
                        success: function(response) {
                            console.log(response);
                            alert(response.data.message);
                        },
                        error: function() {
                            alert('Error syncing order.');
                        },
                        complete: function() {
                            button.prop('disabled', false);
                            button.find('.sync-text').show();
                            button.find('.sync-loading').hide();
                        }
                    });
                });
            });
        </script>
        <?php
    }
} 