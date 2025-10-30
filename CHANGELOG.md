# Changelog

## [1.246] - 2025-10-30

### Added
- Separate Order ID search on Activity Logs page that fetches logs across all dates.

### Changed
- When searching by Order ID, other filters apply to that order regardless of date range.
- Summary banner reflects search mode (order-specific vs date-range).

### Technical Details
- Files Modified:
  - `admin/pages/order-activity-logs.php` – Added separate Order ID form and branching logic.
  - `utils/class-odoo-order-activity-logger.php` – Added `get_activity_logs_for_order_all_dates()`.
  - `wp-odoo-integration.php` – Bumped version to 1.246.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.246.

## [1.245] - 2025-10-29

### Changed
- Disabled order view logging in activity logger to reduce noise in logs

### Technical Details
- Files Modified:
  - `utils/class-odoo-order-activity-logger.php` – Commented out `woocommerce_admin_order_actions` hook
  - `wp-odoo-integration.php` – Bumped version to 1.245
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.245

## [1.244] - 2025-10-29

### Added
- Direct URL streaming for order activity logs on `order-activity-logs` admin page:
  - Legacy file: `oa_file=order-activity-YYYY-MM-DD.log`
  - Per-order: `oa_order_id={id}&oa_date=YYYY-MM-DD`
  - Daily summary: `oa_summary=1&oa_date=YYYY-MM-DD`

### Changed
- Removed nonce requirement for direct URL streaming; retains capability checks and strict validation.

### Technical Details
- Files Modified:
  - `admin/pages/order-activity-logs.php` – Added chunked streaming handler and validation.
  - `wp-odoo-integration.php` – Bumped version to 1.244.

### Performance
- Large logs stream without loading UI or exhausting memory, reducing timeouts.

## [1.243] - 2024-01-15

### Added
- **Date-Based Log Rotation**: Implemented automatic log rotation by date to reduce file sizes
- **Log Cleanup System**: Automatic cleanup of log files older than 30 days
- **Date Selection in Log Viewer**: Added dropdown to select specific date logs
- **Performance Optimization**: Cleanup runs only once per day to avoid performance impact

### Changed
- **Log File Naming**: Log files now use date-based naming (e.g., `odoo-debug-2024-01-15.log`)
- **Log Viewer Interface**: Updated to show available log files and allow date selection
- **Log Management**: Clear and download functions now work with specific date files
- **Plugin Description**: Updated to mention automatic log rotation

### Technical Details
- **Files Modified**: 
  - `wp-odoo-integration.php` - Updated logging function with date rotation and cleanup
  - `admin/pages/log-viewer.php` - Enhanced interface for date-based log viewing
  - `CHANGELOG.md` - Added v1.243 changelog entry

### Performance
- Log files are automatically rotated by date
- Old logs (30+ days) are automatically deleted
- Cleanup process runs only once per day
- Individual log files remain manageable in size

### Compatibility
- Maintains full backward compatibility
- Existing log files are preserved
- New logs use date-based naming

## [1.242] - 2024-01-15

### Added
- **Dedicated Debug Logging System**: Implemented custom logging function `odoo_log()` that stores all debug information in a dedicated `odoo-debug.log` file instead of the default WordPress debug.log
- **Admin Log Viewer**: Added new admin page "Debug Log" under Odoo Orders menu for viewing, downloading, and managing debug logs
- **Log Security**: Implemented security measures including `.htaccess` protection and directory listing prevention for log files
- **Log Levels**: Added proper log levels (INFO, WARNING, ERROR, DEBUG) with formatted timestamps
- **Log Management**: Features include viewing last 1000 lines, downloading complete logs, clearing logs, and auto-refresh functionality

### Changed
- **Logging Location**: All debug logs now stored in `wp-content/uploads/odoo-logs/odoo-debug.log`
- **Error Log Calls**: Updated all `error_log()` calls throughout the plugin to use the new `odoo_log()` function
- **Fallback System**: Maintained backward compatibility with fallback to WordPress `error_log()` if custom function fails
- **Plugin Description**: Updated to mention the new debug logging system

### Technical Details
- **Files Modified**: 
  - `wp-odoo-integration.php` - Added custom logging function and updated version
  - `utils/class-odoo-logger.php` - Updated to use new logging system
  - `utils/class-odoo-helpers.php` - Updated error_log calls
  - `includes/class-odoo-api.php` - Updated error_log calls
  - `includes/class-odoo-orders.php` - Updated error_log calls
  - `includes/class-odoo-response.php` - Updated error_log calls
  - `includes/class-odoo-stock.php` - Updated error_log calls
  - `includes/class-odoo-core.php` - Updated error_log calls
  - `admin/class-odoo-admin.php` - Added log viewer menu item and render method

- **Files Added**:
  - `admin/pages/log-viewer.php` - New admin interface for log management
  - `LOGGING_SYSTEM_README.md` - Comprehensive documentation for the logging system
  - `CHANGELOG.md` - This changelog file

### Security
- Log files are protected from direct web access
- Only administrators can access the log viewer
- Directory listing is prevented

### Performance
- Log viewer limited to last 1000 lines to prevent memory issues
- File locking prevents concurrent write issues
- Log directory created only when needed

### Compatibility
- Maintains full backward compatibility
- Order activity logging system remains completely unchanged
- All existing functionality preserved
