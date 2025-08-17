# Changelog

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
