<?php
/**
 * Odoo Debug Log Viewer
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Handle log clearing
if (isset($_POST['clear_log']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_odoo_log')) {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/odoo-logs/odoo-debug.log';
    
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
        echo '<div class="notice notice-success"><p>Log file cleared successfully.</p></div>';
    }
}

// Handle log download
if (isset($_GET['download_log']) && wp_verify_nonce($_GET['_wpnonce'], 'download_odoo_log')) {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/odoo-logs/odoo-debug.log';
    
    if (file_exists($log_file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="odoo-debug-' . date('Y-m-d-H-i-s') . '.log"');
        header('Content-Length: ' . filesize($log_file));
        readfile($log_file);
        exit;
    }
}

// Get log file path
$upload_dir = wp_upload_dir();
$log_file = $upload_dir['basedir'] . '/odoo-logs/odoo-debug.log';
$log_exists = file_exists($log_file);
$log_size = $log_exists ? filesize($log_file) : 0;
$log_size_formatted = size_format($log_size, 2);

// Read log content (last 1000 lines to prevent memory issues)
$log_content = '';
if ($log_exists) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        $lines = array_slice($lines, -1000); // Get last 1000 lines
        $log_content = implode("\n", $lines);
    }
}

?>
<div class="wrap">
    <h1><?php _e('Odoo Debug Log Viewer', 'wp-odoo-integration'); ?></h1>
    
    <div class="odoo-log-info">
        <p>
            <strong><?php _e('Log File:', 'wp-odoo-integration'); ?></strong> 
            <code><?php echo esc_html($log_file); ?></code>
        </p>
        <p>
            <strong><?php _e('File Size:', 'wp-odoo-integration'); ?></strong> 
            <?php echo esc_html($log_size_formatted); ?>
            <?php if ($log_exists): ?>
                (<?php echo number_format(count(file($log_file))); ?> lines)
            <?php endif; ?>
        </p>
        <p>
            <strong><?php _e('Last Modified:', 'wp-odoo-integration'); ?></strong> 
            <?php echo $log_exists ? esc_html(date('Y-m-d H:i:s', filemtime($log_file))) : __('N/A', 'wp-odoo-integration'); ?>
        </p>
    </div>
    
    <div class="odoo-log-actions">
        <?php if ($log_exists && $log_size > 0): ?>
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('clear_odoo_log'); ?>
                <input type="submit" name="clear_log" class="button button-secondary" value="<?php _e('Clear Log', 'wp-odoo-integration'); ?>" onclick="return confirm('<?php _e('Are you sure you want to clear the log file?', 'wp-odoo-integration'); ?>');">
            </form>
            
            <a href="<?php echo wp_nonce_url(add_query_arg('download_log', '1'), 'download_odoo_log'); ?>" class="button button-primary">
                <?php _e('Download Log', 'wp-odoo-integration'); ?>
            </a>
        <?php endif; ?>
        
        <button type="button" class="button button-secondary" onclick="location.reload();">
            <?php _e('Refresh', 'wp-odoo-integration'); ?>
        </button>
    </div>
    
    <div class="odoo-log-content">
        <?php if ($log_exists && $log_size > 0): ?>
            <div class="log-viewer">
                <pre id="log-content"><?php echo esc_html($log_content); ?></pre>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p><?php _e('No log file found or log file is empty.', 'wp-odoo-integration'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.odoo-log-info {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
}

.odoo-log-info p {
    margin: 5px 0;
}

.odoo-log-actions {
    margin-bottom: 20px;
}

.odoo-log-actions .button {
    margin-right: 10px;
}

.log-viewer {
    background: #1e1e1e;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 600px;
    overflow-y: auto;
}

.log-viewer pre {
    color: #f8f8f2;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.4;
    margin: 0;
    padding: 15px;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.log-viewer pre::-webkit-scrollbar {
    width: 8px;
}

.log-viewer pre::-webkit-scrollbar-track {
    background: #2e2e2e;
}

.log-viewer pre::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 4px;
}

.log-viewer pre::-webkit-scrollbar-thumb:hover {
    background: #777;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Auto-scroll to bottom of log
    var logContent = $('#log-content');
    if (logContent.length) {
        logContent.scrollTop(logContent[0].scrollHeight);
    }
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        location.reload();
    }, 30000);
});
</script>
