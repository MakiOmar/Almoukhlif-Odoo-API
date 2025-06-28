# WordPress/Odoo Integration Plugin

A comprehensive WordPress plugin that integrates WooCommerce with Odoo ERP system for stock validation, order synchronization, and inventory management.

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
3. **Status Updates**: Order status changes are synchronized between systems
4. **Error Handling**: Failed orders are tracked and can be retried

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