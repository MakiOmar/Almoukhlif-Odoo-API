# Changelog

# Changelog

## [1.257] - 2025-11-24

### Added
- `billing.is_company` is now set when either the order-level or customer-level `billing_billing_company_vat` meta contains a numeric value.

### Technical Details
- Files Modified:
  - `includes/class-odoo-orders.php` – Added VAT meta check and injected `is_company` into billing payloads.
  - `wp-odoo-integration.php` – Bumped version to 1.257.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.257.
  - `README.md` – Documented the new behavior.
  - `CHANGELOG.md` – Added this entry.

## [1.256] - 2025-11-24

### Changed
- Added fallback logic so billing city/state are never empty when preparing order data for Odoo.

### Technical Details
- Files Modified:
  - `includes/class-odoo-orders.php` – Normalizes billing city/state before building the payload.
  - `wp-odoo-integration.php` – Bumped version to 1.256.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.256.
  - `README.md` – Documented the change.
  - `CHANGELOG.md` – Added this entry.

## [1.255] - 2025-11-24

### Fixed
- Corrected WooCommerce hook registrations that caused `call_user_func_array` fatal errors during checkout.

### Technical Details
- Files Modified:
  - `hooks/class-odoo-hooks.php` – Fixed `add_action` signatures for `woocommerce_checkout_order_created` and `woocommerce_process_shop_order_meta`.
  - `wp-odoo-integration.php` – Bumped version to 1.255.
  - `includes/class-odoo-core.php` – Updated `VERSION` constant to 1.255.
  - `README.md` – Documented version 1.255.
  - `CHANGELOG.md` – Added this entry.

## [1.254] - 2025-11-23

### Added
- Activity log entries for manual “Send to Odoo” attempts now include full request payloads and Odoo responses.

### Changed
- `send_batch()` / `send_batch_ajax()` accept optional context to store request snapshots, response metadata, and retry info.
- Admin sync button, WooCommerce bulk action, and failed/missing orders pages pass logging context so every manual attempt is recorded.

### Technical Details
- Files Modified:
  - `includes/class-odoo-orders.php` – Added context-aware logging, response capture, and last-send snapshot.
  - `utils/class-odoo-order-activity-logger.php` – Extended `log_odoo_send_attempt()` to persist request/response details.
  - `hooks/class-odoo-hooks.php` – Synced button and bulk action now pass logging context.
  - `admin/includes/class-odoo-filters.php` – Bulk resend helper logs send attempts with page context.
  - `admin/pages/not-sent-orders-all.php` – Logs request/response for resend operations.
  - `wp-odoo-integration.php` – Public send helpers accept context argument.
  - `README.md` – Documented version 1.254.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.254.

## [1.253] - 2025-11-23

### Fixed
- Order discount calculation now properly captures all discount types including cart discounts, manual discounts, and coupons
- Fixed issue where `_cart_discount` meta stored as array was not being read correctly
- Discount retrieval now uses multiple fallback methods to ensure discounts are always captured

### Enhanced
- Implemented three-tier discount retrieval system:
  1. `$order->get_discount_total()` - Standard WooCommerce method
  2. Order meta and post meta reading with proper array handling
  3. Calculation fallback: `Subtotal + Shipping + Tax - Total = Discount`
- Added comprehensive debug logging for discount calculation troubleshooting

### Technical Details
- Files Modified:
  - `includes/class-odoo-orders.php` – Enhanced discount retrieval with multiple methods and fallbacks, added debug logging
  - `wp-odoo-integration.php` – Bumped version to 1.253
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.253
  - `README.md` – Documented version 1.253 and discount calculation fix
  - `CHANGELOG.md` – Added this entry

## [1.252] - 2025-11-17

### Added
- Activity log entry for delivery validation attempts, capturing sanitized request metadata and raw response content for each order.

### Changed
- Delivery validation helper now builds the payload once, reuses it for logging, and passes it directly to the API layer for consistent auditing.
- `Odoo_API::validate_delivery()` accepts an optional payload argument so callers can log exactly what was sent.

### Technical Details
- Files Modified:
  - `utils/class-odoo-helpers.php` – Captured request metadata, response details, and invoked the activity logger.
  - `utils/class-odoo-order-activity-logger.php` – Added `log_delivery_validation()` helper.
  - `includes/class-odoo-api.php` – Allowed passing prebuilt payloads for delivery validation.
  - `README.md` – Documented version 1.252.
  - `wp-odoo-integration.php` – Bumped version to 1.252.
  - `includes/class-odoo-core.php` – Updated `VERSION` constant to 1.252.
  - `CHANGELOG.md` – Added this entry.

## [1.251] - 2025-11-07

### Added
- "Clear Activity Logs" action in the admin Activity Logs screen with confirmation dialog and success summary.
- Logger utility method to purge stored activity logs programmatically.

### Changed
- Admin action handler now supports clearing activity logs with proper permission and nonce checks.

### Technical Details
- Files Modified:
  - `admin/pages/order-activity-logs.php` – Added clear logs button, confirmation, and success notice.
  - `admin/class-odoo-admin.php` – Added handler for clearing logs.
  - `utils/class-odoo-order-activity-logger.php` – Introduced recursive deletion helper.
  - `wp-odoo-integration.php` – Bumped version to 1.251.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.251.
  - `README.md` – Documented log clearing feature and version update.

## [1.250] - 2025-11-07

### Added
- Captured Odoo request payloads in activity logs for success, failure, and cancellation events.

### Changed
- `odoo_order_sent`, `odoo_order_failed`, and `odoo_order_cancelled` hooks now receive the request payload as the third parameter.
- Order processing pipeline stores per-order request payloads for downstream logging.

### Technical Details
- Files Modified:
  - `includes/class-odoo-orders.php` – Added request payload map and forwarded data to hooks.
  - `utils/class-odoo-order-activity-logger.php` – Logged new `odoo_request` field for Odoo activity types.
  - `utils/class-odoo-helpers.php` – Passed cancellation request context to activity logger.
  - `wp-odoo-integration.php` – Bumped version to 1.250 and updated helper.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.250.
  - `README.md` – Documented request payload logging and version update.
  - `README-AR.md` – Updated custom hook usage documentation.

## [1.249] - 2025-11-07

### Added
- Row-level “Set as Skipped” action on the Failed Orders admin screen.

### Changed
- Centralized skip handling in `Odoo_Admin` so single and bulk actions share the same logic and preserve current filters.

### Technical Details
- Files Modified:
  - `admin/pages/failed-orders.php` – Added per-row skip button and refactored bulk action to reuse shared helper.
  - `admin/class-odoo-admin.php` – Added helper methods for marking orders as skipped and handling single-action redirects.
  - `README.md` – Documented row-level skip action and updated version badge.
  - `wp-odoo-integration.php` – Bumped version to 1.249.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.249.

## [1.248] - 2025-11-07

### Added
- Bulk admin action on Failed Orders screen to mark selected orders as skipped instead of failed.

### Changed
- Orders with `oodo-status` set to `skipped` are excluded from Failed Orders views and counts.

### Technical Details
- Files Modified:
  - `admin/pages/failed-orders.php` – Added skip action processing, UI button, and success notice.
  - `README.md` – Documented skipped status workflow and updated version banner.
  - `wp-odoo-integration.php` – Bumped version to 1.248.
  - `includes/class-odoo-core.php` – Updated VERSION constant to 1.248.

## [1.247] - 2025-11-07

### Changed
- Removed remaining `error_log()` fallbacks so logs no longer hit the default WordPress debug log.
- Added `odoo_logger_missing_handler` action hook for custom fallback handlers when `odoo_log()` and `teamlog()` are unavailable.

### Technical Details
- Files Modified:
  - `utils/class-odoo-logger.php` – Removed `error_log()` fallback and added action hook.
  - `includes/class-odoo-core.php` – Updated error logging to use new fallback chain and bumped version constant to 1.247.
  - `wp-odoo-integration.php` – Bumped version to 1.247.

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
