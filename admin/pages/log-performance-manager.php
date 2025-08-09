<?php
/**
 * Log Performance Manager Admin Page
 * 
 * Manages the improved hierarchical logging system with performance monitoring
 */

defined('ABSPATH') || die;

if (!function_exists('display_log_performance_manager_page')) {
    function display_log_performance_manager_page() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Handle actions
        if (isset($_POST['action'])) {
            $action = sanitize_text_field($_POST['action']);
            
            switch ($action) {
                case 'migrate_legacy':
                    if (isset($_POST['migrate_date'])) {
                        $date = sanitize_text_field($_POST['migrate_date']);
                        $result = Odoo_Order_Activity_Logger::migrate_legacy_logs($date);
                        $migration_result = $result;
                    }
                    break;
                    
                case 'cleanup_old':
                    $days_to_keep = isset($_POST['days_to_keep']) ? intval($_POST['days_to_keep']) : 365;
                    $cleanup_result = Odoo_Order_Activity_Logger::cleanup_old_logs($days_to_keep);
                    break;
                    
                case 'get_statistics':
                    $stats_date = isset($_POST['stats_date']) ? sanitize_text_field($_POST['stats_date']) : current_time('Y-m-d');
                    $statistics = Odoo_Order_Activity_Logger::get_log_statistics($stats_date);
                    break;
            }
        }
        
        // Get current statistics
        $current_stats = Odoo_Order_Activity_Logger::get_log_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Log Performance Manager', 'text-domain'); ?></h1>
            
            <!-- Performance Overview -->
            <div class="card">
                <h2><?php _e('Performance Overview', 'text-domain'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Current Date:', 'text-domain'); ?></th>
                        <td><?php echo esc_html($current_stats['date']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('New Structure Active:', 'text-domain'); ?></th>
                        <td>
                            <?php if ($current_stats['new_structure_exists']): ?>
                                <span style="color: green;">✓ <?php _e('Yes', 'text-domain'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php _e('No', 'text-domain'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Legacy Structure Present:', 'text-domain'); ?></th>
                        <td>
                            <?php if ($current_stats['legacy_structure_exists']): ?>
                                <span style="color: orange;">⚠ <?php _e('Yes (can be migrated)', 'text-domain'); ?></span>
                            <?php else: ?>
                                <span style="color: green;">✓ <?php _e('No (fully migrated)', 'text-domain'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Order Files Count:', 'text-domain'); ?></th>
                        <td><?php echo esc_html($current_stats['order_files_count']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Total Entries:', 'text-domain'); ?></th>
                        <td><?php echo esc_html($current_stats['total_entries']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Total Size:', 'text-domain'); ?></th>
                        <td><?php echo size_format($current_stats['total_size']); ?></td>
                    </tr>
                    <?php if ($current_stats['performance_improvement'] !== 'unknown'): ?>
                    <tr>
                        <th><?php _e('Performance Improvement:', 'text-domain'); ?></th>
                        <td><strong><?php echo esc_html($current_stats['performance_improvement']); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Migration Tools -->
            <div class="card">
                <h2><?php _e('Migration Tools', 'text-domain'); ?></h2>
                
                <?php if (isset($migration_result)): ?>
                    <div class="notice notice-<?php echo $migration_result['success'] ? 'success' : 'error'; ?>">
                        <h4><?php _e('Migration Result:', 'text-domain'); ?></h4>
                        <ul>
                            <li><strong><?php _e('Date:', 'text-domain'); ?></strong> <?php echo esc_html($migration_result['date']); ?></li>
                            <li><strong><?php _e('Success:', 'text-domain'); ?></strong> <?php echo $migration_result['success'] ? 'Yes' : 'No'; ?></li>
                            <?php if (!$migration_result['success']): ?>
                                <li><strong><?php _e('Error:', 'text-domain'); ?></strong> <?php echo esc_html($migration_result['message']); ?></li>
                            <?php else: ?>
                                <li><strong><?php _e('Migrated Entries:', 'text-domain'); ?></strong> <?php echo esc_html($migration_result['migrated_entries']); ?></li>
                                <li><strong><?php _e('Created Files:', 'text-domain'); ?></strong> <?php echo esc_html($migration_result['created_files']); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="migrate_legacy">
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Migrate Legacy Logs:', 'text-domain'); ?></th>
                            <td>
                                <label for="migrate_date"><?php _e('Date to migrate (YYYY-MM-DD):', 'text-domain'); ?></label>
                                <input type="date" id="migrate_date" name="migrate_date" value="<?php echo current_time('Y-m-d'); ?>">
                                <p class="description"><?php _e('Migrate legacy log files to the new hierarchical structure for better performance.', 'text-domain'); ?></p>
                                <input type="submit" class="button button-primary" value="<?php _e('Migrate Legacy Logs', 'text-domain'); ?>">
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            
            <!-- Statistics Tool -->
            <div class="card">
                <h2><?php _e('Statistics Tool', 'text-domain'); ?></h2>
                
                <?php if (isset($statistics)): ?>
                    <div class="notice notice-info">
                        <h4><?php _e('Statistics for:', 'text-domain'); ?> <?php echo esc_html($statistics['date']); ?></h4>
                        <ul>
                            <li><strong><?php _e('New Structure Exists:', 'text-domain'); ?></strong> <?php echo $statistics['new_structure_exists'] ? 'Yes' : 'No'; ?></li>
                            <li><strong><?php _e('Legacy Structure Exists:', 'text-domain'); ?></strong> <?php echo $statistics['legacy_structure_exists'] ? 'Yes' : 'No'; ?></li>
                            <li><strong><?php _e('Order Files Count:', 'text-domain'); ?></strong> <?php echo esc_html($statistics['order_files_count']); ?></li>
                            <li><strong><?php _e('Total Entries:', 'text-domain'); ?></strong> <?php echo esc_html($statistics['total_entries']); ?></li>
                            <li><strong><?php _e('Total Size:', 'text-domain'); ?></strong> <?php echo size_format($statistics['total_size']); ?></li>
                            <?php if (isset($statistics['summary_entries'])): ?>
                                <li><strong><?php _e('Summary Entries:', 'text-domain'); ?></strong> <?php echo esc_html($statistics['summary_entries']); ?></li>
                            <?php endif; ?>
                            <?php if ($statistics['performance_improvement'] !== 'unknown'): ?>
                                <li><strong><?php _e('Performance Improvement:', 'text-domain'); ?></strong> <?php echo esc_html($statistics['performance_improvement']); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="get_statistics">
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Get Statistics:', 'text-domain'); ?></th>
                            <td>
                                <label for="stats_date"><?php _e('Date to analyze (YYYY-MM-DD):', 'text-domain'); ?></label>
                                <input type="date" id="stats_date" name="stats_date" value="<?php echo current_time('Y-m-d'); ?>">
                                <p class="description"><?php _e('Get detailed statistics for a specific date.', 'text-domain'); ?></p>
                                <input type="submit" class="button" value="<?php _e('Get Statistics', 'text-domain'); ?>">
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            
            <!-- Cleanup Tools -->
            <div class="card">
                <h2><?php _e('Cleanup Tools', 'text-domain'); ?></h2>
                
                <?php if (isset($cleanup_result)): ?>
                    <div class="notice notice-success">
                        <h4><?php _e('Cleanup Result:', 'text-domain'); ?></h4>
                        <ul>
                            <li><strong><?php _e('Cutoff Date:', 'text-domain'); ?></strong> <?php echo esc_html($cleanup_result['cutoff_date']); ?></li>
                            <li><strong><?php _e('Deleted Directories:', 'text-domain'); ?></strong> <?php echo esc_html($cleanup_result['deleted_directories']); ?></li>
                            <li><strong><?php _e('Deleted Files:', 'text-domain'); ?></strong> <?php echo esc_html($cleanup_result['deleted_files']); ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" onsubmit="return confirm('<?php _e('Are you sure you want to delete old log files? This action cannot be undone.', 'text-domain'); ?>');">
                    <input type="hidden" name="action" value="cleanup_old">
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Cleanup Old Logs:', 'text-domain'); ?></th>
                            <td>
                                <label for="days_to_keep"><?php _e('Keep logs for how many days:', 'text-domain'); ?></label>
                                <input type="number" id="days_to_keep" name="days_to_keep" value="365" min="1" max="3650">
                                <p class="description"><?php _e('Delete log files older than the specified number of days. Default is 365 days (1 year).', 'text-domain'); ?></p>
                                <input type="submit" class="button button-secondary" value="<?php _e('Cleanup Old Logs', 'text-domain'); ?>">
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            
            <!-- Performance Benefits -->
            <div class="card">
                <h2><?php _e('Performance Benefits', 'text-domain'); ?></h2>
                <div class="notice notice-info">
                    <h4><?php _e('How the new system improves performance:', 'text-domain'); ?></h4>
                    <ul>
                        <li><strong><?php _e('Order-Specific Files:', 'text-domain'); ?></strong> <?php _e('Each order gets its own log file, eliminating the need to scan through all activities for a specific order.', 'text-domain'); ?></li>
                        <li><strong><?php _e('Hierarchical Structure:', 'text-domain'); ?></strong> <?php _e('Logs are organized by year/month/day folders, making it easier to locate specific date ranges.', 'text-domain'); ?></li>
                        <li><strong><?php _e('Daily Summary Files:', 'text-domain'); ?></strong> <?php _e('Quick overview files for daily activities without loading full details.', 'text-domain'); ?></li>
                        <li><strong><?php _e('Reduced Memory Usage:', 'text-domain'); ?></strong> <?php _e('Only relevant files are loaded into memory, significantly reducing server load.', 'text-domain'); ?></li>
                        <li><strong><?php _e('Faster Filtering:', 'text-domain'); ?></strong> <?php _e('Order ID filtering is now O(1) instead of O(n) where n is the number of log entries.', 'text-domain'); ?></li>
                        <li><strong><?php _e('Better Scalability:', 'text-domain'); ?></strong> <?php _e('System performance remains consistent even with thousands of orders per day.', 'text-domain'); ?></li>
                    </ul>
                </div>
            </div>
            
            <!-- File Structure Example -->
            <div class="card">
                <h2><?php _e('New File Structure', 'text-domain'); ?></h2>
                <div style="background: #f1f1f1; padding: 15px; font-family: monospace; font-size: 12px;">
                    <pre>wp-content/order-activity-logs/
├── 2024/
│   ├── 01/
│   │   ├── 15/
│   │   │   ├── order-12345.log
│   │   │   ├── order-12346.log
│   │   │   ├── order-12347.log
│   │   │   └── daily-summary.log
│   │   └── 16/
│   │       ├── order-12348.log
│   │       ├── order-12349.log
│   │       └── daily-summary.log
│   └── 02/
│       └── 01/
│           ├── order-12350.log
│           └── daily-summary.log
└── 2025/
    └── 01/
        └── 15/
            ├── order-12351.log
            └── daily-summary.log</pre>
                </div>
                <p><em><?php _e('This structure allows for efficient querying by date and order ID, dramatically improving performance for large-scale operations.', 'text-domain'); ?></em></p>
            </div>
        </div>
        <?php
    }
}
