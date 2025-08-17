# Odoo Integration Logging System

## Overview

The Odoo Integration plugin now uses a dedicated logging system that stores all debug information in a separate `odoo-debug.log` file instead of the default WordPress debug.log.

## Log File Location

Log files are stored in: `wp-content/uploads/odoo-logs/`

**File Naming Convention**: `odoo-debug-YYYY-MM-DD.log` (e.g., `odoo-debug-2024-01-15.log`)

**Log Rotation**: New log file created each day automatically

## Log Format

Each log entry follows this format:
```
[YYYY-MM-DD HH:MM:SS] [LEVEL] Message
```

### Log Levels

- **INFO**: General information about operations
- **WARNING**: Warning messages for potential issues
- **ERROR**: Error messages for failed operations
- **DEBUG**: Debug information (only logged when WP_DEBUG is enabled)

## Logging Function

The plugin uses a custom `odoo_log()` function that:

1. Creates a dedicated log directory in `wp-content/uploads/odoo-logs/`
2. Protects log files with `.htaccess` to prevent direct access
3. Formats messages with timestamps and log levels
4. Falls back to WordPress `error_log()` if the custom function fails

### Usage

```php
// Basic logging
odoo_log('This is an info message', 'info');

// Error logging
odoo_log('This is an error message', 'error');

// Warning logging
odoo_log('This is a warning message', 'warning');

// Debug logging (only when WP_DEBUG is enabled)
odoo_log('This is a debug message', 'debug');
```

## Admin Interface

### Log Viewer

Access the log viewer at: **WordPress Admin → Odoo Orders → Debug Log**

Features:
- **Date Selection**: Dropdown to select specific date logs
- **File Management**: View available log files (last 30 days)
- **Log Viewing**: View the last 1000 lines of selected date's log file
- **Download**: Download specific date's log file
- **Clear**: Clear specific date's log file
- **Auto-refresh**: Auto-refresh every 30 seconds
- **Syntax Highlighting**: Better readability with dark theme

### Log Management

The log viewer provides:
- **File Information**: Shows log directory, available files count, and current file details
- **Date Selection**: Dropdown to browse logs by specific dates
- **Clear Log**: Removes all content from the selected date's log file
- **Download Log**: Downloads the selected date's log file
- **Refresh**: Manually refresh the log viewer

## Log Categories

The plugin logs various types of information:

### Order Processing
- Order processing results
- Retry attempts
- AJAX order processing
- Failed orders

### API Communication
- Delivery validation requests/responses
- Stock updates
- Authentication errors
- API response processing

### System Operations
- Plugin initialization
- Error handling
- Debug information

## Security

- Log files are stored outside the web root when possible
- `.htaccess` file prevents direct access to log files
- `index.php` file prevents directory listing
- Only administrators can access the log viewer

## Performance Considerations

- **Log Rotation**: New log file created each day to keep individual files manageable
- **Automatic Cleanup**: Log files older than 30 days are automatically deleted
- **Daily Cleanup**: Cleanup process runs only once per day to avoid performance impact
- **Viewer Limits**: Log viewer limited to last 1000 lines to prevent memory issues
- **File Locking**: Prevents concurrent write issues
- **Directory Creation**: Log directory created only when needed

## Troubleshooting

### Log File Not Created
1. Check if the `wp-content/uploads` directory is writable
2. Verify that the plugin has proper permissions
3. Check for any PHP errors in the main error log

### Cannot Access Log Viewer
1. Ensure you have administrator privileges
2. Check if the log file exists
3. Verify file permissions

### Large Log Files
1. Log files are automatically rotated by date to prevent large single files
2. Use the "Clear Log" function to reset specific date's log
3. Old logs (30+ days) are automatically cleaned up
4. Monitor log directory size regularly

## Migration from Old System

All existing `error_log()` calls have been updated to use the new `odoo_log()` function. The system maintains backward compatibility by falling back to `error_log()` if the custom function is not available.

## Configuration

The logging system can be customized by modifying the `odoo_log()` function in `wp-odoo-integration.php`. You can:

- Change the log file location
- Modify the log format
- Add additional log levels
- Implement log rotation
- Add log filtering

## Example Log Entries

```
[2024-01-15 10:30:45] [INFO] Odoo Integration plugin initialized successfully
[2024-01-15 10:31:12] [INFO] [Odoo Order Processing Result] Array ( [order_ids] => Array ( [0] => 12345 ) [success] => 1 )
[2024-01-15 10:31:15] [ERROR] Failed to update stock in Odoo: Missing authentication token
[2024-01-15 10:31:20] [WARNING] [Odoo Retry Attempt] Array ( [retry_attempt] => 1 )
[2024-01-15 10:31:25] [DEBUG] [Odoo Debug] Order not found: 12346
```

## File Structure Example

```
wp-content/uploads/odoo-logs/
├── .htaccess
├── index.php
├── odoo-debug-2024-01-15.log
├── odoo-debug-2024-01-16.log
├── odoo-debug-2024-01-17.log
└── odoo-debug-2024-01-18.log
```
