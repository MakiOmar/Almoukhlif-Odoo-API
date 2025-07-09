# WordPress/Odoo Integration Plugin

A comprehensive WordPress plugin that integrates WooCommerce with Odoo ERP system for stock validation, order synchronization, and inventory management.

**Current Version: 1.227**

## 📋 Changelog

### Version 1.227 (Latest)
- 🔧 **FIXED**: Complete order status change tracking
  - Added `post_updated` hook to catch ALL order status changes including `$order->update_status()`
  - Added REST API hooks to track order status changes via API calls
  - Enhanced logging system to capture programmatic status changes
  - Added comprehensive testing tools for status change tracking
- 🛠️ **IMPROVED**: Order activity logging coverage
  - Now tracks status changes from all sources: admin panel, REST API, programmatic calls
  - Added detailed logging for REST API order updates with request data
  - Enhanced trigger source detection for better audit trails
- ✨ **NEW**: Update status tracking test functionality
  - Added "Test Update Status Tracking" button in debug interface
  - Automatically tests `$order->update_status()` calls with real orders
  - Verifies logging system captures all status change methods
  - Provides detailed feedback on tracking success/failure

### Version 1.226
- 🔧 **IMPROVED**: Plugin path constants implementation
  - Added `ODOO_PLUGIN_FILE`, `ODOO_PLUGIN_DIR`, `ODOO_PLUGIN_URL`, `ODOO_PLUGIN_BASENAME` constants
  - Updated all file loading operations to use consistent path constants
  - Improved plugin update checker integration with proper file paths
  - Enhanced code maintainability and reliability
- 🛠️ **FIXED**: Plugin update checker configuration
  - Corrected plugin slug from `'wp-odoo-integration/wp-odoo-integration.php'` to `'wp-odoo-integration'`
  - Fixed initialization timing by moving to `plugins_loaded` hook
  - Added comprehensive debugging and testing tools
- ✨ **NEW**: Update checker testing functionality
  - Added "Test Update Checker" button in debug interface
  - Comprehensive diagnostics for GitHub repository access
  - Verification of readme.txt presence and releases

### Version 1.225
- ✨ **NEW**: Comprehensive Order Activity Logging System
  - Complete audit trail for all order status changes
  - User identification and source detection
  - Daily log files with detailed context
  - Admin interface for viewing and filtering logs
  - Debug tools for testing and management
- ✨ **NEW**: Enhanced Order Status Synchronization
  - Now sends both status label and status code to Odoo
  - `wc_order_status`: Human-readable status label
  - `wc_order_status_code`: Machine-readable status code
- 🔧 **IMPROVED**: Better error handling and debugging
- 🛠️ **FIXED**: PHP fatal error with user functions during initialization
- 📊 **ENHANCED**: Admin interface with activity logs page
- 🔒 **SECURITY**: Proper permission checks for debug functionality

## 🚀 Features

- **Real-time Stock Validation**: Check Odoo stock before adding products to cart
- **Order Synchronization**: Automatically sync WooCommerce orders to Odoo
- **Comprehensive Admin Interface**: Monitor failed, sent, and pending orders
- **Retry Mechanism**: Automatic retry with exponential backoff for failed requests
- **Bulk Operations**: Send multiple orders to Odoo at once
- **Order Status Updates**: Sync order status changes between systems
- **Stock Updates**: Keep WooCommerce stock in sync with Odoo

## 📁 Plugin Structure

```
wp-odoo-integration/
├── wp-odoo-integration.php          # Main plugin file
├── README.md                        # This file
├── includes/                        # Core functionality
│   ├── class-odoo-core.php         # Main plugin initialization
│   ├── class-odoo-api.php          # Odoo API communication
│   ├── class-odoo-auth.php         # Authentication handling
│   ├── class-odoo-stock.php        # Stock validation and updates
│   ├── class-odoo-orders.php       # Order processing and management
│   ├── class-odoo-response.php     # Response processing
│   ├── draft.php                   # Legacy draft functionality
│   └── rest-api.php                # REST API integration
├── admin/                          # Admin interface
│   ├── class-odoo-admin.php        # Admin menu and pages
│   ├── includes/                   # Admin includes
│   │   └── class-odoo-filters.php  # Reusable filtration system
│   ├── pages/                      # Admin page files
│   │   ├── sent-orders.php         # Sent orders page
│   │   ├── failed-orders.php       # Failed orders page
│   │   ├── not-sent-orders.php     # Not sent orders page
│   │   └── not-sent-orders-all.php # All not sent orders page
│   └── assets/                     # Admin assets
│       ├── css/                    # Stylesheets
│       └── js/                     # JavaScript files
├── hooks/                          # WordPress hooks
│   └── class-odoo-hooks.php        # All plugin hooks and actions
├── utils/                          # Utility functions
│   ├── class-odoo-helpers.php      # Helper functions
│   └── class-odoo-logger.php       # Logging functionality
└── plugin-update-checker/          # Plugin update checker
```

## 🔧 Classes Overview

### Core Classes

- **`Odoo_Core`**: Main plugin initialization and dependency loading
- **`Odoo_API`**: Handles all Odoo API communication
- **`Odoo_Auth`**: Manages authentication tokens and sessions
- **`Odoo_Stock`**: Stock validation and synchronization
- **`Odoo_Orders`**: Order processing and management
- **`Odoo_Response`**: Unified response processing

### Admin Classes

- **`Odoo_Admin`**: Admin menu and page management
- **`Odoo_Admin_Filters`**: Reusable filtration system for admin pages
- **`Odoo_Hooks`**: WordPress hooks and actions

### Utility Classes

- **`Odoo_Helpers`**: Helper functions and utilities
- **`Odoo_Logger`**: Logging functionality

## 🛠️ Installation

1. Upload the plugin files to `/wp-content/plugins/wp-odoo-integration/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure Odoo settings in your theme options
4. Test the integration

## ⚙️ Configuration

The plugin requires the following Odoo settings to be configured:

- **Odoo URL**: Your Odoo instance URL
- **Database**: Odoo database name
- **Username**: Odoo username
- **Password**: Odoo password
- **Location ID**: Stock location ID in Odoo

## 🔄 Order Flow

1. **Order Creation**: When a WooCommerce order is created, it's automatically sent to Odoo
2. **Stock Validation**: Before adding products to cart, stock is validated against Odoo
3. **Status Updates**: Order status changes are synchronized between systems (both status label and status code)
4. **Error Handling**: Failed orders are tracked and can be retried

### **Order Status Synchronization**

The plugin sends both the order status label and status code to Odoo:

- **`wc_order_status`**: Human-readable status label (e.g., "Processing", "Completed", "Cancelled")
- **`wc_order_status_code`**: Machine-readable status code (e.g., "processing", "completed", "cancelled")

This dual approach ensures that Odoo receives both the user-friendly status name and the programmatic status identifier for better integration and processing.

## 📊 Admin Interface

The plugin provides a comprehensive admin interface with:

- **Sent Orders**: View all successfully sent orders
- **Failed Orders**: Monitor and retry failed orders with advanced filtration
- **Not Sent Orders**: View orders that haven't been sent yet with advanced filtration
- **All Missing Status Orders**: Comprehensive view with date range and status filters
- **Bulk Operations**: Send multiple orders at once
- **Real-time Notifications**: Admin bar notifications for failed orders
- **Advanced Filtration**: Date range, order status, customer search, and order ID filters
- **Cached Counts**: 5-minute cache for admin bar counts to reduce server load

## 📊 Order Activity Logging System

The plugin includes a comprehensive order activity logging system that tracks all order status changes and activities for audit purposes. This system helps you understand who changed order statuses, when, and how the changes were triggered.

### **Features**

- **Complete Activity Tracking**: Logs all order status changes, creations, updates, and Odoo interactions
- **User Identification**: Tracks which user performed each action
- **Source Detection**: Identifies whether changes came from admin panel, REST API, AJAX, etc.
- **Detailed Context**: Captures IP addresses, user agents, and backtrace information
- **Daily Log Files**: Organizes logs by date for easy management
- **Admin Interface**: Provides a comprehensive admin page to view and filter logs
- **teamlog Integration**: Uses your existing teamlog function for additional logging

### **What Gets Logged**

#### **Order Status Changes**
- Status changes from any source (admin panel, REST API, AJAX, etc.)
- Old and new status values
- User who made the change
- Timestamp and IP address

#### **Order Activities**
- Order creation
- Order updates
- Admin panel interactions
- Bulk actions
- AJAX operations

#### **Odoo Integration Activities**
- Orders sent to Odoo (success/failure)
- Order cancellations in Odoo
- Authentication failures
- API response details

#### **System Information**
- Trigger source (Admin Panel, REST API, AJAX, Frontend, etc.)
- User information (ID, username, display name, roles)
- IP address and user agent
- Backtrace information for debugging

### **Log File Structure**

Logs are stored in `wp-content/order-activity-logs/` with daily files:
- Format: `order-activity-YYYY-MM-DD.log`
- Each line is a JSON object containing complete activity information
- Files are automatically created and managed

### **Admin Interface**

Access the activity logs via **Odoo Orders → Activity Logs** in the WordPress admin:

#### **Filtering Options**
- Date range selection
- Order ID filtering
- Activity type filtering
- User filtering
- Trigger source filtering

#### **Log Details**
- Click "View Details" to see complete log information
- Modal popup with structured data display
- Backtrace information for debugging
- Additional context data

### **Debug Tools**

Access debug tools via **Tools → Odoo Activity Debug**:

#### **Testing**
- Test logging functionality
- Verify system configuration
- Check log directory permissions

#### **Management**
- View log file information
- Clear all log files
- System information display

### **Usage Examples**

#### **Viewing Logs for a Specific Order**
1. Go to **Odoo Orders → Activity Logs**
2. Enter the order ID in the filter
3. Click "Filter" to see all activities for that order

#### **Investigating Status Changes**
1. Filter by activity type "Status Change"
2. Set date range to cover the period of interest
3. Review user information and trigger sources

#### **Audit Trail**
1. Use date range filters to focus on specific periods
2. Filter by user to see all actions by a specific person
3. Export or review logs for compliance purposes

### **Configuration**

The logging system is automatically enabled when the plugin is active. No additional configuration is required.

#### **Log Retention**
- Logs are stored indefinitely by default
- Use the debug tools to manually clear old logs
- Consider implementing automated log rotation for production

#### **Performance**
- Logging is designed to be lightweight and non-blocking
- Uses file locking to prevent corruption
- Minimal impact on order processing performance

## 🚨 Odoo Status Failures & Order Notes

The plugin tracks order synchronization status using the `oodo-status` meta key and stores detailed error messages in order notes. Here's what happens when orders fail:

### **Status Tracking**

Orders can have the following `oodo-status` values:
- **`success`**: Order successfully sent to Odoo
- **`failed`**: Order failed to send to Odoo
- **`pending`**: Order not yet processed

### **When Odoo Status is Set to "failed"**

The plugin sets `oodo-status = 'failed'` in the following scenarios:

#### **1. Authentication Failures**
- **Trigger**: When Odoo authentication token is missing or invalid
- **Order Note**: `فشل إرسال الطلب إلى أودو: رمز التوثيق غير موجود.` (Failed to send order to Odoo: Authentication token not found)

#### **2. API Response Errors**
- **Trigger**: When Odoo API returns HTTP status code other than 200
- **Order Note**: `فشل إرسال الطلب إلى أودو: رمز الاستجابة [CODE]` (Failed to send order to Odoo: Response code [CODE])

#### **3. Invalid Response Structure**
- **Trigger**: When Odoo API returns unexpected response format
- **Order Note**: `فشل إرسال الطلب إلى أودو: رد غير متوقع.` (Failed to send order to Odoo: Unexpected response)

#### **4. Missing Response Data**
- **Trigger**: When Odoo API response lacks required data fields
- **Order Note**: `فشل إرسال الطلب إلى أودو: بيانات الاستجابة غير صحيحة.` (Failed to send order to Odoo: Invalid response data)

#### **5. Odoo Processing Failures**
- **Trigger**: When Odoo successfully receives the order but fails to process it
- **Order Note**: The specific error message from Odoo (Arabic or English)

#### **6. Network/Connection Issues**
- **Trigger**: When unable to connect to Odoo API
- **Order Note**: `فشل إرسال الطلب إلى أودو: [ERROR_MESSAGE]` (Failed to send order to Odoo: [ERROR_MESSAGE])

### **Special Cases**

#### **"Already Exists" Orders**
- **Behavior**: Orders that already exist in Odoo are treated as **success**, not failure
- **Order Note**: `الطلب موجود بالفعل في أودو.` (Order already exists in Odoo)
- **Status**: Set to `success` with existing Odoo ID

#### **Successful Orders**
- **Order Note**: `تم إرسال الطلب بنجاح إلى أودو برقم أودو ID: [ODOO_ID].` (Order sent successfully to Odoo with ID: [ODOO_ID])
- **Additional Note**: `تم إرسال هذه المنتجات إلى أودو: [PRODUCT_IDS]` (These products sent to Odoo: [PRODUCT_IDS])

### **Order Notes Structure**

All order notes are added as **private notes** (not visible to customers) and include:

1. **Timestamp**: Automatically added by WordPress
2. **Error Type**: Clear indication of what went wrong
3. **Specific Details**: Odoo error messages or system error details
4. **Action Required**: Whether manual intervention is needed

### **Recovery Options**

Failed orders can be recovered through:

1. **Admin Interface**: Use the "Failed Orders" page to retry
2. **Bulk Actions**: Select multiple failed orders and resend
3. **Individual Retry**: Use the "Sync to Odoo" button on order pages
4. **Automatic Retry**: Plugin attempts up to 3 retries with exponential backoff

## 🔒 Security Features

- Nonce verification for AJAX requests
- Input sanitization and validation
- Proper error handling and logging
- Secure authentication token management

## 🚨 Error Handling

The plugin includes robust error handling:

- **Retry Mechanism**: Automatic retries with exponential backoff
- **Error Logging**: Comprehensive error logging
- **User Notifications**: Clear error messages for users
- **Admin Monitoring**: Easy monitoring of failed operations

## 🔄 API Retry Mechanism

The plugin implements a sophisticated retry mechanism with exponential backoff to handle temporary network issues and API failures. Here's when retries occur and how they work:

### **When Retries Are Triggered**

#### **1. Order Processing Failures**
- **Trigger**: When sending orders to Odoo API fails (HTTP errors, network timeouts, invalid responses)
- **Retry Limit**: Up to 3 attempts per order batch
- **Retry Function**: `send_batch()` and `send_batch_ajax()`
- **Conditions**: 
  - HTTP status codes other than 200
  - Network connection errors
  - Invalid response structure from Odoo
  - Missing required response data

#### **2. Authentication Failures**
- **Trigger**: When Odoo authentication token retrieval fails
- **Retry Limit**: Up to 2 attempts for authentication
- **Retry Function**: `get_auth_token()`
- **Conditions**:
  - Network connection errors during authentication
  - Odoo authentication service temporarily unavailable
  - Invalid credentials (though this won't be retried)

### **Retry Strategy**

#### **Exponential Backoff with Jitter**
The plugin uses exponential backoff with random jitter to prevent thundering herd problems:

```php
$delay = pow(2, $retry_attempt) + rand(0, 1000) / 1000;
```

**Retry Delays:**
- **1st Retry**: ~2-3 seconds (2 + 0-1 second jitter)
- **2nd Retry**: ~4-5 seconds (4 + 0-1 second jitter)  
- **3rd Retry**: ~8-9 seconds (8 + 0-1 second jitter)

#### **Authentication Retry Delays:**
- **1st Retry**: ~2-3 seconds
- **2nd Retry**: ~4-5 seconds

### **What Happens During Retries**

#### **Order Processing Retries:**
1. **Logging**: Each retry attempt is logged with order IDs and attempt number
2. **Delay**: System waits using exponential backoff
3. **Re-authentication**: Fresh authentication token is obtained
4. **Re-processing**: Order data is re-sent to Odoo API
5. **Response Handling**: Same response processing logic is applied

#### **Authentication Retries:**
1. **Logging**: Authentication retry attempts are logged
2. **Delay**: System waits using exponential backoff
3. **Re-attempt**: Authentication request is re-sent
4. **Token Storage**: Successful token is cached for 24 hours

### **Retry Success Conditions**

#### **Order Processing:**
- **Success**: Odoo API returns HTTP 200 with valid response structure
- **Partial Success**: Some orders succeed, others fail (handled individually)
- **Failure**: All retry attempts exhausted, orders marked as failed

#### **Authentication:**
- **Success**: Valid authentication token received and stored
- **Failure**: All retry attempts exhausted, authentication fails

### **What Doesn't Trigger Retries**

#### **Permanent Failures (No Retry):**
- **Invalid Credentials**: Wrong username/password
- **Invalid Order Data**: Malformed order structure
- **Odoo Processing Errors**: Orders rejected by Odoo business logic
- **Already Existing Orders**: Orders that already exist in Odoo
- **Authentication Token Expired**: Token cleared and re-authentication attempted

#### **Manual Retry Options:**
- **Admin Interface**: Failed orders can be manually retried from admin pages
- **Bulk Actions**: Multiple failed orders can be retried at once
- **Individual Retry**: Single orders can be retried from order edit page

### **Monitoring Retries**

#### **Logging:**
- **Function**: `teamlog()` if available, otherwise `error_log()`
- **Information**: Retry attempt number, order IDs, error messages
- **Location**: Server error logs or custom logging system

#### **Admin Interface:**
- **Failed Orders Page**: Shows orders that failed after all retry attempts
- **Real-time Counts**: Admin bar shows count of failed orders
- **Retry Actions**: Manual retry buttons for failed orders

### **Performance Considerations**

#### **Retry Impact:**
- **Processing Time**: Each retry adds delay to order processing
- **Server Load**: Exponential backoff prevents overwhelming Odoo API
- **User Experience**: Orders may take longer to sync during retry attempts

#### **Optimization:**
- **Token Caching**: Authentication tokens cached for 24 hours
- **Batch Processing**: Multiple orders sent in single API call
- **Jitter**: Random delays prevent synchronized retry attempts

## 🔄 Backward Compatibility

The plugin maintains backward compatibility through function aliases, ensuring existing integrations continue to work.

## 📝 Changelog

### Version 1.224
- **Major Refactoring**: Reorganized code into proper classes
- **Improved Error Handling**: Unified response processing
- **Better Code Organization**: Separated concerns into different classes
- **Enhanced Admin Interface**: Better organized admin pages
- **Maintained Compatibility**: All existing functionality preserved

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🆘 Support

For support and questions, please contact the plugin author or create an issue in the repository. 