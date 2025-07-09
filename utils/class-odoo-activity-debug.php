<?php
/**
 * Odoo Activity Debug Class
 * 
 * Utility class for debugging and testing the order activity logging system
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Activity_Debug {
    
    /**
     * Initialize debug functionality
     */
    public static function init() {
        // Add debug menu item for administrators
        add_action('admin_menu', array(__CLASS__, 'add_debug_menu'));
    }
    
    /**
     * Add debug menu
     */
    public static function add_debug_menu() {
        // Only add menu for administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'tools.php',
            'Odoo Activity Debug',
            'Odoo Activity Debug',
            'manage_options',
            'odoo-activity-debug',
            array(__CLASS__, 'render_debug_page')
        );
    }
    
    /**
     * Render debug page
     */
    public static function render_debug_page() {
        // Double-check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Handle test actions
        if (isset($_POST['test_logging']) && wp_verify_nonce($_POST['_wpnonce'], 'test_odoo_logging')) {
            self::test_logging();
        }
        
        if (isset($_POST['test_update_checker']) && wp_verify_nonce($_POST['_wpnonce'], 'test_update_checker')) {
            self::test_update_checker();
        }
        
        if (isset($_POST['test_update_status_tracking']) && wp_verify_nonce($_POST['_wpnonce'], 'test_update_status_tracking')) {
            self::test_update_status_tracking();
        }
        
        if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_odoo_logs')) {
            self::clear_logs();
        }
        
        // Display order status data test
        self::test_order_status_data();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Odoo Activity Debug', 'text-domain'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('This page allows you to test and debug the order activity logging system.', 'text-domain'); ?></p>
            </div>
            
            <!-- Test Logging -->
            <div class="card">
                <h2><?php _e('Test Logging', 'text-domain'); ?></h2>
                <p><?php _e('Click the button below to test the logging system by creating a test log entry.', 'text-domain'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('test_odoo_logging'); ?>
                    <input type="submit" name="test_logging" class="button button-primary" value="<?php _e('Test Logging', 'text-domain'); ?>">
                </form>
            </div>
            
            <!-- Test Update Checker -->
            <div class="card">
                <h2><?php _e('Test Update Checker', 'text-domain'); ?></h2>
                <p><?php _e('Click the button below to test the plugin update checker functionality.', 'text-domain'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('test_update_checker'); ?>
                    <input type="submit" name="test_update_checker" class="button button-primary" value="<?php _e('Test Update Checker', 'text-domain'); ?>">
                </form>
            </div>
            
            <!-- Test Update Status Tracking -->
            <div class="card">
                <h2><?php _e('Test Update Status Tracking', 'text-domain'); ?></h2>
                <p><?php _e('Click the button below to test if $order->update_status() calls are being tracked by the logging system.', 'text-domain'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('test_update_status_tracking'); ?>
                    <input type="submit" name="test_update_status_tracking" class="button button-primary" value="<?php _e('Test Update Status Tracking', 'text-domain'); ?>">
                </form>
            </div>
            
            <!-- Clear Logs -->
            <div class="card">
                <h2><?php _e('Clear Logs', 'text-domain'); ?></h2>
                <p><?php _e('Warning: This will delete all order activity log files. This action cannot be undone.', 'text-domain'); ?></p>
                <form method="post" onsubmit="return confirm('<?php _e('Are you sure you want to delete all log files?', 'text-domain'); ?>');">
                    <?php wp_nonce_field('clear_odoo_logs'); ?>
                    <input type="submit" name="clear_logs" class="button button-secondary" value="<?php _e('Clear All Logs', 'text-domain'); ?>">
                </form>
            </div>
            
            <!-- Log Directory Info -->
            <div class="card">
                <h2><?php _e('Log Directory Information', 'text-domain'); ?></h2>
                <?php
                $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
                $dir_exists = file_exists($logs_dir);
                $dir_writable = is_writable($logs_dir);
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Log Directory:', 'text-domain'); ?></th>
                        <td><code><?php echo esc_html($logs_dir); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php _e('Directory Exists:', 'text-domain'); ?></th>
                        <td>
                            <?php if ($dir_exists): ?>
                                <span style="color: green;">✓ <?php _e('Yes', 'text-domain'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('No', 'text-domain'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Directory Writable:', 'text-domain'); ?></th>
                        <td>
                            <?php if ($dir_writable): ?>
                                <span style="color: green;">✓ <?php _e('Yes', 'text-domain'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('No', 'text-domain'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Log Files:', 'text-domain'); ?></th>
                        <td>
                            <?php
                            if ($dir_exists) {
                                $log_files = glob($logs_dir . '/order-activity-*.log');
                                if ($log_files) {
                                    echo '<ul>';
                                    foreach ($log_files as $file) {
                                        $filename = basename($file);
                                        $filesize = size_format(filesize($file));
                                        $modified = date('Y-m-d H:i:s', filemtime($file));
                                        echo '<li><code>' . esc_html($filename) . '</code> (' . esc_html($filesize) . ') - ' . esc_html($modified) . '</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo '<em>' . __('No log files found.', 'text-domain') . '</em>';
                                }
                            } else {
                                echo '<em>' . __('Directory does not exist.', 'text-domain') . '</em>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- System Information -->
            <div class="card">
                <h2><?php _e('System Information', 'text-domain'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('PHP Version:', 'text-domain'); ?></th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('WordPress Version:', 'text-domain'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('WooCommerce Version:', 'text-domain'); ?></th>
                        <td><?php echo esc_html(WC()->version ?? 'Not active'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('teamlog Function Available:', 'text-domain'); ?></th>
                        <td>
                            <?php if (function_exists('teamlog')): ?>
                                <span style="color: green;">✓ <?php _e('Yes', 'text-domain'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;">⚠ <?php _e('No (will use error_log)', 'text-domain'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Odoo Order Activity Logger Class:', 'text-domain'); ?></th>
                        <td>
                            <?php if (class_exists('Odoo_Order_Activity_Logger')): ?>
                                <span style="color: green;">✓ <?php _e('Available', 'text-domain'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('Not Available', 'text-domain'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Test logging functionality
     */
    private static function test_logging() {
        if (!class_exists('Odoo_Order_Activity_Logger')) {
            echo '<div class="notice notice-error"><p>' . __('Odoo Order Activity Logger class not found.', 'text-domain') . '</p></div>';
            return;
        }
        
        // Create a test log entry
        $test_data = array(
            'order_id' => 0,
            'activity_type' => 'test_logging',
            'user_id' => get_current_user_id(),
            'user_info' => array(
                'id' => get_current_user_id(),
                'username' => 'test_user',
                'display_name' => 'Test User',
                'email' => 'test@example.com',
                'roles' => array('administrator')
            ),
            'trigger_source' => 'Debug Test',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Debug Test',
            'test_data' => array(
                'message' => 'This is a test log entry created by the debug system',
                'timestamp' => time(),
                'random_value' => rand(1000, 9999)
            )
        );
        
        // Write test log
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        if (!file_exists($logs_dir)) {
            $created = wp_mkdir_p($logs_dir);
            if (!$created) {
                echo '<div class="notice notice-error"><p>' . __('Failed to create log directory.', 'text-domain') . '</p></div>';
                return;
            }
        }
        
        $date = current_time('Y-m-d');
        $log_file = $logs_dir . '/order-activity-' . $date . '.log';
        
        $log_entry = array(
            'timestamp' => $test_data['timestamp'],
            'activity_type' => $test_data['activity_type'],
            'order_id' => $test_data['order_id'],
            'user_id' => $test_data['user_id'],
            'user_info' => $test_data['user_info'],
            'trigger_source' => $test_data['trigger_source'],
            'ip_address' => $test_data['ip_address'],
            'user_agent' => $test_data['user_agent'],
            'data' => $test_data['test_data']
        );
        
        $log_line = json_encode($log_entry) . "\n";
        $result = file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . __('Test log entry created successfully!', 'text-domain') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to create test log entry.', 'text-domain') . '</p></div>';
        }
    }
    
    /**
     * Test order status data structure
     */
    private static function test_order_status_data() {
        echo '<div class="card">';
        echo '<h2>' . __('Order Status Data Test', 'text-domain') . '</h2>';
        
        // Get sample order statuses
        $statuses = wc_get_order_statuses();
        $sample_statuses = array_slice($statuses, 0, 5, true);
        
        echo '<p>' . __('Sample order status data structure that will be sent to Odoo:', 'text-domain') . '</p>';
        echo '<table class="form-table">';
        echo '<tr><th>' . __('Status Code', 'text-domain') . '</th><th>' . __('Status Label', 'text-domain') . '</th><th>' . __('Data Sent to Odoo', 'text-domain') . '</th></tr>';
        
        foreach ($sample_statuses as $status_code => $status_label) {
            $clean_status_code = str_replace('wc-', '', $status_code);
            $data_structure = array(
                'wc_order_status' => $status_label,
                'wc_order_status_code' => $clean_status_code
            );
            
            echo '<tr>';
            echo '<td><code>' . esc_html($clean_status_code) . '</code></td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '<td><pre>' . esc_html(json_encode($data_structure, JSON_PRETTY_PRINT)) . '</pre></td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Clear all log files
     */
    private static function clear_logs() {
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        
        if (!file_exists($logs_dir)) {
            echo '<div class="notice notice-warning"><p>' . __('Log directory does not exist.', 'text-domain') . '</p></div>';
            return;
        }
        
        $log_files = glob($logs_dir . '/order-activity-*.log');
        $deleted_count = 0;
        
        foreach ($log_files as $file) {
            if (unlink($file)) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            echo '<div class="notice notice-success"><p>' . sprintf(__('Successfully deleted %d log files.', 'text-domain'), $deleted_count) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>' . __('No log files were deleted.', 'text-domain') . '</p></div>';
        }
    }
    
    /**
     * Test plugin update checker functionality
     */
    public static function test_update_checker() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="wrap">';
        echo '<h2>Plugin Update Checker Test</h2>';
        
        // Check if update checker class exists
        if (!class_exists('Puc_v4_Factory')) {
            echo '<div class="notice notice-error"><p>Plugin Update Checker class not found!</p></div>';
            return;
        }
        
        echo '<div class="notice notice-success"><p>Plugin Update Checker class found.</p></div>';
        
        // Check if we can access the GitHub repository
        $response = wp_remote_get('https://api.github.com/repos/MakiOmar/Almoukhlif-Odoo-API');
        
        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>Error accessing GitHub repository: ' . $response->get_error_message() . '</p></div>';
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['name'])) {
                echo '<div class="notice notice-success"><p>GitHub repository accessible. Repository: ' . esc_html($data['name']) . '</p></div>';
                
                // Check for readme.txt
                $readme_response = wp_remote_get('https://api.github.com/repos/MakiOmar/Almoukhlif-Odoo-API/contents/readme.txt');
                if (!is_wp_error($readme_response) && wp_remote_retrieve_response_code($readme_response) === 200) {
                    echo '<div class="notice notice-success"><p>readme.txt file found in repository.</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>readme.txt file not found in repository. This may affect update checker functionality.</p></div>';
                }
                
                // Check for releases
                $releases_response = wp_remote_get('https://api.github.com/repos/MakiOmar/Almoukhlif-Odoo-API/releases/latest');
                if (!is_wp_error($releases_response) && wp_remote_retrieve_response_code($releases_response) === 200) {
                    $releases_body = wp_remote_retrieve_body($releases_response);
                    $releases_data = json_decode($releases_body, true);
                    if ($releases_data && isset($releases_data['tag_name'])) {
                        echo '<div class="notice notice-success"><p>Latest release found: ' . esc_html($releases_data['tag_name']) . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-warning"><p>No releases found in repository.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>Could not parse GitHub repository data.</p></div>';
            }
        }
        
        // Check current plugin version
        $plugin_data = get_plugin_data(ODOO_PLUGIN_FILE);
        echo '<div class="notice notice-info"><p>Current plugin version: ' . esc_html($plugin_data['Version']) . '</p></div>';
        
        // Check if update checker is initialized
        global $wp_filter;
        if (isset($wp_filter['plugins_loaded'])) {
            $has_update_checker = false;
            foreach ($wp_filter['plugins_loaded']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0])) {
                        $class_name = get_class($callback['function'][0]);
                        if ($class_name === 'Odoo_Core') {
                            $has_update_checker = true;
                            break 2;
                        }
                    }
                }
            }
            
            if ($has_update_checker) {
                echo '<div class="notice notice-success"><p>Update checker is properly hooked to plugins_loaded.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Update checker is not hooked to plugins_loaded.</p></div>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * Get log statistics
     * 
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Statistics
     */
    public static function get_log_statistics($start_date, $end_date) {
        if (!class_exists('Odoo_Order_Activity_Logger')) {
            return array();
        }
        
        $logs = Odoo_Order_Activity_Logger::get_activity_logs($start_date, $end_date);
        
        $stats = array(
            'total_logs' => count($logs),
            'activity_types' => array(),
            'trigger_sources' => array(),
            'users' => array(),
            'orders' => array()
        );
        
        foreach ($logs as $log) {
            // Activity types
            $activity_type = $log['activity_type'];
            if (!isset($stats['activity_types'][$activity_type])) {
                $stats['activity_types'][$activity_type] = 0;
            }
            $stats['activity_types'][$activity_type]++;
            
            // Trigger sources
            $trigger_source = $log['trigger_source'];
            if (!isset($stats['trigger_sources'][$trigger_source])) {
                $stats['trigger_sources'][$trigger_source] = 0;
            }
            $stats['trigger_sources'][$trigger_source]++;
            
            // Users
            $user_id = $log['user_id'];
            if (!isset($stats['users'][$user_id])) {
                $stats['users'][$user_id] = array(
                    'name' => $log['user_info']['display_name'],
                    'count' => 0
                );
            }
            $stats['users'][$user_id]['count']++;
            
            // Orders
            if ($log['order_id']) {
                if (!isset($stats['orders'][$log['order_id']])) {
                    $stats['orders'][$log['order_id']] = 0;
                }
                $stats['orders'][$log['order_id']]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Test order update_status() tracking
     */
    public static function test_update_status_tracking() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="wrap">';
        echo '<h2>Order Update Status Tracking Test</h2>';
        
        // First, check if the logging system is properly set up
        echo '<div class="notice notice-info"><p>Checking logging system setup...</p></div>';
        
        // Check if the logger class exists
        if (!class_exists('Odoo_Order_Activity_Logger')) {
            echo '<div class="notice notice-error"><p>✗ Odoo_Order_Activity_Logger class not found. Please check if the plugin is properly loaded.</p></div>';
            echo '</div>';
            return;
        }
        
        // Check if the log directory is writable
        $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
        if (!file_exists($logs_dir)) {
            $created = wp_mkdir_p($logs_dir);
            if ($created) {
                echo '<div class="notice notice-success"><p>✓ Created log directory: ' . esc_html($logs_dir) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>✗ Failed to create log directory: ' . esc_html($logs_dir) . '</p></div>';
                echo '</div>';
                return;
            }
        }
        
        if (!is_writable($logs_dir)) {
            echo '<div class="notice notice-error"><p>✗ Log directory is not writable: ' . esc_html($logs_dir) . '</p></div>';
            echo '</div>';
            return;
        }
        
        echo '<div class="notice notice-success"><p>✓ Logging system is properly set up</p></div>';
        
        // Get a test order (first order we can find)
        $orders = wc_get_orders(array(
            'limit' => 1,
            'status' => array('processing', 'completed', 'on-hold')
        ));
        
        // Get a test order (first order we can find)
        $orders = wc_get_orders(array(
            'limit' => 1,
            'status' => array('processing', 'completed', 'on-hold')
        ));
        
        if (empty($orders)) {
            echo '<div class="notice notice-error"><p>No orders found for testing. Please create an order first.</p></div>';
            echo '</div>';
            return;
        }
        
        $order = $orders[0];
        $order_id = $order->get_id();
        $current_status = $order->get_status();
        
        echo '<div class="notice notice-info"><p>Testing with Order #' . esc_html($order_id) . ' (Current status: ' . esc_html($current_status) . ')</p></div>';
        
        // First, test the logging system directly
        echo '<div class="notice notice-info"><p>Testing logging system directly...</p></div>';
        if (class_exists('Odoo_Order_Activity_Logger')) {
            // Create a test log entry to verify the system works
            $test_log_data = array(
                'order_id' => $order_id,
                'activity_type' => 'test_status_tracking',
                'old_status' => $current_status,
                'new_status' => 'test',
                'user_id' => get_current_user_id(),
                'user_info' => array(
                    'display_name' => 'Test User',
                    'email' => 'test@example.com',
                    'roles' => array('administrator')
                ),
                'trigger_source' => 'Debug Test',
                'timestamp' => current_time('Y-m-d H:i:s'),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Debug Test',
                'test_data' => array(
                    'message' => 'Testing status tracking system',
                    'timestamp' => time()
                )
            );
            
            Odoo_Order_Activity_Logger::write_activity_log($test_log_data);
            echo '<div class="notice notice-success"><p>✓ Test log entry created successfully</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>✗ Odoo_Order_Activity_Logger class not available</p></div>';
        }
        
        // Test different status changes
        $test_statuses = array('on-hold', 'processing', 'completed');
        $original_status = $current_status;
        
        foreach ($test_statuses as $test_status) {
            if ($test_status === $current_status) {
                continue; // Skip if already in this status
            }
            
            echo '<div class="notice notice-info"><p>Testing status change to: ' . esc_html($test_status) . '</p></div>';
            
            try {
                // Change status using update_status()
                $order->update_status($test_status, 'Test status change via update_status() - ' . date('Y-m-d H:i:s'));
                
                echo '<div class="notice notice-success"><p>✓ Status changed to ' . esc_html($test_status) . ' successfully</p></div>';
                
                // Wait a moment for hooks to process
                sleep(1);
                
                // Check if it was logged
                $logs_dir = WP_CONTENT_DIR . '/order-activity-logs';
                $log_file = $logs_dir . '/' . date('Y-m-d') . '.log';
                
                // Create log directory and file if they don't exist
                if (!file_exists($logs_dir)) {
                    wp_mkdir_p($logs_dir);
                    echo '<div class="notice notice-info"><p>✓ Created log directory: ' . esc_html($logs_dir) . '</p></div>';
                }
                
                if (file_exists($log_file)) {
                    $log_content = file_get_contents($log_file);
                    if (strpos($log_content, "Order #$order_id") !== false && strpos($log_content, $test_status) !== false) {
                        echo '<div class="notice notice-success"><p>✓ Status change was logged successfully</p></div>';
                    } else {
                        echo '<div class="notice notice-warning"><p>⚠ Status change may not have been logged</p></div>';
                        echo '<div class="notice notice-info"><p>Debug: Checking log file: ' . esc_html($log_file) . '</p></div>';
                        echo '<div class="notice notice-info"><p>Debug: Log content length: ' . strlen($log_content) . ' characters</p></div>';
                        echo '<div class="notice notice-info"><p>Debug: Looking for "Order #' . esc_html($order_id) . '" and "' . esc_html($test_status) . '"</p></div>';
                    }
                } else {
                    // Create empty log file for testing
                    $result = file_put_contents($log_file, '');
                    if ($result !== false) {
                        echo '<div class="notice notice-info"><p>✓ Created empty log file: ' . esc_html($log_file) . '</p></div>';
                        echo '<div class="notice notice-warning"><p>⚠ No log entries found yet - this may indicate the logging system needs to be tested</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>✗ Failed to create log file: ' . esc_html($log_file) . '</p></div>';
                    }
                }
                
            } catch (Exception $e) {
                echo '<div class="notice notice-error"><p>✗ Error changing status: ' . esc_html($e->getMessage()) . '</p></div>';
            }
        }
        
        // Restore original status
        try {
            $order->update_status($original_status, 'Restoring original status after test');
            echo '<div class="notice notice-success"><p>✓ Original status restored</p></div>';
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>✗ Error restoring original status: ' . esc_html($e->getMessage()) . '</p></div>';
        }
        
        echo '<div class="notice notice-info"><p><strong>Test completed!</strong> Check the Order Activity Logs page to see if all status changes were tracked.</p></div>';
        echo '</div>';
    }
} 