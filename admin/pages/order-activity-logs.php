<?php
/**
 * Order Activity Logs Admin Page
 * 
 * Displays comprehensive order activity logs with filtering and search
 */

defined('ABSPATH') || die;

if (!function_exists('display_order_activity_logs_page')) {
    function display_order_activity_logs_page() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Direct raw file access to avoid timeouts on large files
        // Supports:
        // 1) Legacy daily file:  ?page=order-activity-logs&oa_file=order-activity-YYYY-MM-DD.log
        // 2) Per-order file:     ?page=order-activity-logs&oa_order_id=123&oa_date=YYYY-MM-DD
        // 3) Daily summary:      ?page=order-activity-logs&oa_summary=1&oa_date=YYYY-MM-DD
        if (isset($_GET['oa_file']) || isset($_GET['oa_order_id']) || isset($_GET['oa_summary'])) {
            
            $base_dir = WP_CONTENT_DIR . '/order-activity-logs';
            $target = '';
            
            // Case 1: Legacy daily file by exact filename
            if (isset($_GET['oa_file'])) {
                $filename = basename(sanitize_text_field($_GET['oa_file']));
                if (!preg_match('/^order-activity-\d{4}-\d{2}-\d{2}\.log$/', $filename)) {
                    wp_die(__('Invalid log filename.', 'text-domain'));
                }
                $target = trailingslashit($base_dir) . $filename;
            }
            
            // Case 2: Per-order file by date and order id
            if (!$target && isset($_GET['oa_order_id']) && isset($_GET['oa_date'])) {
                $order_id = intval($_GET['oa_order_id']);
                $date = sanitize_text_field($_GET['oa_date']);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    wp_die(__('Invalid date format.', 'text-domain'));
                }
                $parts = explode('-', $date);
                $day_dir = $base_dir . '/' . $parts[0] . '/' . $parts[1] . '/' . $parts[2];
                $target = $day_dir . '/order-' . $order_id . '.log';
            }
            
            // Case 3: Daily summary file by date
            if (!$target && isset($_GET['oa_summary']) && isset($_GET['oa_date'])) {
                $date = sanitize_text_field($_GET['oa_date']);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    wp_die(__('Invalid date format.', 'text-domain'));
                }
                $parts = explode('-', $date);
                $day_dir = $base_dir . '/' . $parts[0] . '/' . $parts[1] . '/' . $parts[2];
                $target = $day_dir . '/daily-summary.log';
            }
            
            if ($target && file_exists($target) && is_readable($target)) {
                @ob_end_clean();
                if (!headers_sent()) {
                    header('Content-Type: text/plain; charset=UTF-8');
                    header('Content-Length: ' . filesize($target));
                    header('X-Content-Type-Options: nosniff');
                    header('Content-Disposition: inline; filename="' . basename($target) . '"');
                }
                set_time_limit(0);
                $fp = fopen($target, 'rb');
                if ($fp) {
                    while (!feof($fp)) {
                        echo fread($fp, 8192);
                        flush();
                    }
                    fclose($fp);
                }
                exit;
            } else if ($target) {
                wp_die(__('Log file not found or not readable.', 'text-domain'));
            }
        }
        
		// Check if either search-by-id or general filter form was submitted
		$order_search_id = isset($_GET['search_order_id']) ? intval($_GET['search_order_id']) : 0;
		$order_search_submitted = $order_search_id > 0;
		$filter_submitted = $order_search_submitted || isset($_GET['start_date']) || isset($_GET['end_date']) || isset($_GET['order_id']) || 
						   isset($_GET['activity_type']) || isset($_GET['user_id']) || isset($_GET['trigger_source']) || 
						   isset($_GET['paged']);
        
		// Handle date range and filters
		$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-1 month'));
		$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d', strtotime('+1 day'));
		$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : '';
		$activity_type = isset($_GET['activity_type']) ? sanitize_text_field($_GET['activity_type']) : '';
		$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';
		$trigger_source = isset($_GET['trigger_source']) ? sanitize_text_field($_GET['trigger_source']) : '';
        
        // Build filters
        $filters = array();
        if ($order_id) $filters['order_id'] = $order_id;
        if ($activity_type) $filters['activity_type'] = $activity_type;
        if ($user_id) $filters['user_id'] = $user_id;
        if ($trigger_source) $filters['trigger_source'] = $trigger_source;
        
		// Get logs depending on mode
		$logs = array();
		if ($filter_submitted && class_exists('Odoo_Order_Activity_Logger')) {
			if ($order_search_submitted) {
				// Separate order ID search across all dates; don't include order_id in filters since file is scoped per order
				$filters_for_order = $filters;
				unset($filters_for_order['order_id']);
				$logs = Odoo_Order_Activity_Logger::get_activity_logs_for_order_all_dates($order_search_id, $filters_for_order);
			} else {
				$logs = Odoo_Order_Activity_Logger::get_activity_logs($start_date, $end_date, $filters);
			}
		}
        
        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_logs = count($logs);
        $total_pages = ceil($total_logs / $per_page);
        $offset = ($current_page - 1) * $per_page;
        $paginated_logs = array_slice($logs, $offset, $per_page);
        
        // Get available activity types for filter
        $activity_types = array(
            'status_change' => 'Status Change',
            'order_created' => 'Order Created',
            'order_updated' => 'Order Updated',
            'rest_api_update' => 'REST API Update',
            'admin_action_viewed' => 'Admin Action Viewed',
            'bulk_action' => 'Bulk Action',
            'ajax_action' => 'AJAX Action',
            'odoo_order_sent' => 'Odoo Order Sent',
            'odoo_order_failed' => 'Odoo Order Failed',
            'odoo_order_cancelled' => 'Odoo Order Cancelled'
        );
        
        // Get available trigger sources for filter
        $trigger_sources = array(
            'Admin Panel' => 'Admin Panel',
            'AJAX' => 'AJAX',
            'REST API' => 'REST API',
            'Frontend' => 'Frontend',
            'Cron Job' => 'Cron Job',
            'WP-CLI' => 'WP-CLI',
            'Bulk Action' => 'Bulk Action',
            'Odoo Integration' => 'Odoo Integration'
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Order Activity Logs', 'text-domain'); ?></h1>
            
            <!-- Filters -->
            <div class="tablenav top">
				<form method="get" action="">
					<input type="hidden" name="page" value="order-activity-logs">
					
					<div class="alignleft actions">
						<label for="search_order_id"><?php _e('Search by Order ID:', 'text-domain'); ?></label>
						<input type="number" id="search_order_id" name="search_order_id" value="<?php echo esc_attr($order_search_id); ?>" placeholder="Order ID">
						<input type="submit" class="button" value="<?php _e('Search', 'text-domain'); ?>">
						<a href="?page=order-activity-logs" class="button"><?php _e('Clear', 'text-domain'); ?></a>
					</div>
				</form>
				
				<form method="get" action="" style="margin-top:10px;">
					<input type="hidden" name="page" value="order-activity-logs">
					
					<div class="alignleft actions">
                        <label for="start_date"><?php _e('Start Date:', 'text-domain'); ?></label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                        
                        <label for="end_date"><?php _e('End Date:', 'text-domain'); ?></label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                        
                        <label for="order_id"><?php _e('Order ID:', 'text-domain'); ?></label>
                        <input type="number" id="order_id" name="order_id" value="<?php echo esc_attr($order_id); ?>" placeholder="Order ID">
                        
                        <label for="activity_type"><?php _e('Activity Type:', 'text-domain'); ?></label>
                        <select id="activity_type" name="activity_type">
                            <option value=""><?php _e('All Types', 'text-domain'); ?></option>
                            <?php foreach ($activity_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($activity_type, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label for="trigger_source"><?php _e('Trigger Source:', 'text-domain'); ?></label>
                        <select id="trigger_source" name="trigger_source">
                            <option value=""><?php _e('All Sources', 'text-domain'); ?></option>
                            <?php foreach ($trigger_sources as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($trigger_source, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
						<input type="submit" class="button" value="<?php _e('Filter', 'text-domain'); ?>">
						<a href="?page=order-activity-logs" class="button"><?php _e('Clear Filters', 'text-domain'); ?></a>
                    </div>
                </form>
            </div>
            
            <!-- Summary -->
			<?php if ($filter_submitted): ?>
                <div class="notice notice-info">
					<p>
						<?php if ($order_search_submitted): ?>
							<?php printf(
								__('Showing %d logs for Order #%d (all dates)', 'text-domain'),
								$total_logs,
								$order_search_id
							); ?>
						<?php else: ?>
							<?php printf(
								__('Showing %d logs from %s to %s', 'text-domain'),
								$total_logs,
								date('M j, Y', strtotime($start_date)),
								date('M j, Y', strtotime($end_date))
							); ?>
						<?php endif; ?>
					</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php _e('Please use the filters above to search for order activity logs.', 'text-domain'); ?></p>
                </div>
            <?php endif; ?>
            

            
            <!-- Logs Table -->
            <?php if ($filter_submitted): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Timestamp', 'text-domain'); ?></th>
                            <th><?php _e('Order ID', 'text-domain'); ?></th>
                            <th><?php _e('Activity Type', 'text-domain'); ?></th>
                            <th><?php _e('User', 'text-domain'); ?></th>
                            <th><?php _e('Trigger Source', 'text-domain'); ?></th>
                            <th><?php _e('IP Address', 'text-domain'); ?></th>
                            <th><?php _e('Details', 'text-domain'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paginated_logs)): ?>
                            <tr>
                                <td colspan="7"><?php _e('No logs found for the selected criteria.', 'text-domain'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paginated_logs as $log): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['timestamp']))); ?>
                                    </td>
                                    <td>
                                        <?php if ($log['order_id']): ?>
                                            <a href="<?php echo admin_url('post.php?post=' . $log['order_id'] . '&action=edit'); ?>" target="_blank">
                                                #<?php echo esc_html($log['order_id']); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="activity-type activity-type-<?php echo esc_attr($log['activity_type']); ?>">
                                            <?php echo esc_html($activity_types[$log['activity_type']] ?? $log['activity_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['user_info']['id']): ?>
                                            <strong><?php echo esc_html($log['user_info']['display_name']); ?></strong><br>
                                            <small><?php echo esc_html($log['user_info']['username']); ?></small><br>
                                            <small><?php echo esc_html(implode(', ', $log['user_info']['roles'])); ?></small>
                                        <?php else: ?>
                                            <?php echo esc_html($log['user_info']['display_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="trigger-source trigger-source-<?php echo esc_attr(strtolower(str_replace(' ', '-', $log['trigger_source']))); ?>">
                                            <?php echo esc_html($log['trigger_source']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log['ip_address']); ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small" onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                                            <?php _e('View Details', 'text-domain'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $current_page
                            ));
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Log Details Modal -->
        <div id="log-details-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php _e('Log Details', 'text-domain'); ?></h2>
                <div id="log-details-content"></div>
            </div>
        </div>
        
        <style>
            .activity-type {
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .activity-type-status_change { background: #e7f5ff; color: #0066cc; }
            .activity-type-order_created { background: #e8f5e8; color: #006600; }
            .activity-type-order_updated { background: #fff3cd; color: #856404; }
            .activity-type-odoo_order_sent { background: #d4edda; color: #155724; }
            .activity-type-odoo_order_failed { background: #f8d7da; color: #721c24; }
            .activity-type-odoo_order_cancelled { background: #f8d7da; color: #721c24; }
            
            .trigger-source {
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                background: #f1f1f1;
                color: #333;
            }
            
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
            }
            
            .modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 800px;
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            
            .close:hover,
            .close:focus {
                color: black;
                text-decoration: none;
                cursor: pointer;
            }
            
            .log-detail {
                margin-bottom: 10px;
                padding: 10px;
                background: #f9f9f9;
                border-left: 4px solid #0073aa;
            }
            
            .log-detail h4 {
                margin: 0 0 5px 0;
                color: #0073aa;
            }
            
            .log-detail pre {
                background: #fff;
                padding: 10px;
                border: 1px solid #ddd;
                overflow-x: auto;
                margin: 0;
            }
        </style>
        
        <script>
            function showLogDetails(log) {
                const modal = document.getElementById('log-details-modal');
                const content = document.getElementById('log-details-content');
                
                let html = '<div class="log-detail">';
                html += '<h4>Basic Information</h4>';
                html += '<p><strong>Timestamp:</strong> ' + log.timestamp + '</p>';
                html += '<p><strong>Activity Type:</strong> ' + log.activity_type + '</p>';
                html += '<p><strong>Order ID:</strong> ' + (log.order_id || 'N/A') + '</p>';
                html += '<p><strong>User:</strong> ' + log.user_info.display_name + ' (' + log.user_info.username + ')</p>';
                html += '<p><strong>Trigger Source:</strong> ' + log.trigger_source + '</p>';
                html += '<p><strong>IP Address:</strong> ' + log.ip_address + '</p>';
                html += '</div>';
                
                if (log.data) {
                    html += '<div class="log-detail">';
                    html += '<h4>Additional Data</h4>';
                    html += '<pre>' + JSON.stringify(log.data, null, 2) + '</pre>';
                    html += '</div>';
                }
                
                if (log.backtrace && log.backtrace.length > 0) {
                    html += '<div class="log-detail">';
                    html += '<h4>Backtrace</h4>';
                    html += '<pre>' + JSON.stringify(log.backtrace, null, 2) + '</pre>';
                    html += '</div>';
                }
                
                content.innerHTML = html;
                modal.style.display = 'block';
            }
            
            // Close modal when clicking on X or outside
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('log-details-modal');
                const span = document.getElementsByClassName('close')[0];
                
                span.onclick = function() {
                    modal.style.display = 'none';
                }
                
                window.onclick = function(event) {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                }
            });
        </script>
        <?php
    }
} 