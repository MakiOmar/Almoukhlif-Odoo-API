# WordPress Plugin Update Checker Implementation Guide

## Overview

This document provides a comprehensive guide to implementing automatic update notifications and one-click upgrades for WordPress plugins using the Plugin Update Checker library. This implementation enables your plugin to work like WordPress.org plugins with automatic update notifications.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Basic Implementation](#basic-implementation)
- [Advanced Configuration](#advanced-configuration)
- [GitHub Integration](#github-integration)
- [Update Process](#update-process)
- [Troubleshooting](#troubleshooting)
- [Security Considerations](#security-considerations)
- [API Reference](#api-reference)
- [Examples](#examples)

## Prerequisites

- WordPress plugin with a main plugin file
- GitHub repository (or other supported VCS)
- Plugin Update Checker library
- Basic PHP knowledge

## Installation

### 1. Download Plugin Update Checker

Download the [Plugin Update Checker library](https://github.com/YahnisElsts/plugin-update-checker) and add it to your plugin:

```
your-plugin/
├── your-plugin.php
├── includes/
├── admin/
└── plugin-update-checker/     # Add this directory
    ├── plugin-update-checker.php
    ├── Puc/
    └── ...
```

### 2. Include the Library

Add this to your main plugin file:

```php
// Load the update checker library
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
```

## Basic Implementation

### Step 1: Initialize Update Checker

Add this code to your main plugin file or a separate class:

```php
/**
 * Initialize plugin update checker
 */
function init_plugin_update_checker() {
    // Check if the update checker class exists
    if (!class_exists('Puc_v4_Factory')) {
        error_log('Plugin update checker library not found');
        return;
    }
    
    try {
        // Replace these values with your plugin details
        $plugin_file = __FILE__; // Path to your main plugin file
        $plugin_slug = 'your-plugin-slug'; // Unique identifier for your plugin
        $repository_url = 'https://github.com/your-username/your-repo/'; // Your GitHub repository
        
        $update_checker = Puc_v4_Factory::buildUpdateChecker(
            $repository_url,
            $plugin_file,
            $plugin_slug
        );
        
        // Set the branch that contains the stable release
        $update_checker->setBranch('main'); // or 'master'
        
        return $update_checker;
    } catch (Exception $e) {
        error_log('Failed to initialize update checker: ' . $e->getMessage());
        return false;
    }
}

// Initialize on plugins_loaded hook
add_action('plugins_loaded', 'init_plugin_update_checker');
```

### Step 2: Update Your Plugin Header

Ensure your main plugin file has the correct header:

```php
<?php
/**
 * Plugin Name: Your Plugin Name
 * Description: Your plugin description
 * Version: 1.0.0
 * Author: Your Name
 * 
 * @package YourPlugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Your plugin code here...
```

## Advanced Configuration

### Optional Configuration Options

```php
function init_plugin_update_checker() {
    if (!class_exists('Puc_v4_Factory')) {
        return;
    }
    
    $update_checker = Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/your-username/your-repo/',
        __FILE__,
        'your-plugin-slug'
    );
    
    // Set authentication for private repositories
    $update_checker->setAuthentication('your-github-token');
    
    // Enable GitHub releases (recommended)
    $update_checker->getVcsApi()->enableReleaseAssets();
    
    // Set custom update check frequency (default: 12 hours)
    $update_checker->setCheckPeriod(6); // Check every 6 hours
    
    // Add custom query arguments
    $update_checker->addQueryArgFilter(function($queryArgs) {
        $queryArgs['license_key'] = get_option('your_plugin_license_key');
        $queryArgs['site_url'] = get_site_url();
        return $queryArgs;
    });
    
    // Set custom branch
    $update_checker->setBranch('stable');
    
    return $update_checker;
}
```

### Class-Based Implementation

For better organization, you can create a dedicated class:

```php
class Your_Plugin_Update_Checker {
    
    private $update_checker;
    private $plugin_file;
    private $plugin_slug;
    private $repository_url;
    
    public function __construct($plugin_file, $plugin_slug, $repository_url) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = $plugin_slug;
        $this->repository_url = $repository_url;
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        if (!class_exists('Puc_v4_Factory')) {
            return;
        }
        
        try {
            $this->update_checker = Puc_v4_Factory::buildUpdateChecker(
                $this->repository_url,
                $this->plugin_file,
                $this->plugin_slug
            );
            
            $this->configure_update_checker();
            
        } catch (Exception $e) {
            error_log('Update checker initialization failed: ' . $e->getMessage());
        }
    }
    
    private function configure_update_checker() {
        // Enable GitHub releases
        $this->update_checker->getVcsApi()->enableReleaseAssets();
        
        // Set branch
        $this->update_checker->setBranch('main');
        
        // Set authentication if needed
        $github_token = get_option('your_plugin_github_token');
        if ($github_token) {
            $this->update_checker->setAuthentication($github_token);
        }
        
        // Add custom filters
        $this->add_custom_filters();
    }
    
    private function add_custom_filters() {
        // Add license key to requests
        $this->update_checker->addQueryArgFilter(function($queryArgs) {
            $queryArgs['license_key'] = get_option('your_plugin_license_key');
            return $queryArgs;
        });
        
        // Modify update information
        add_filter('puc_pre_inject_update', array($this, 'modify_update_info'));
    }
    
    public function modify_update_info($update) {
        // Add custom logic here
        return $update;
    }
    
    public function get_update_checker() {
        return $this->update_checker;
    }
}

// Usage
new Your_Plugin_Update_Checker(
    __FILE__,
    'your-plugin-slug',
    'https://github.com/your-username/your-repo/'
);
```

## GitHub Integration

### Repository Structure

Your GitHub repository should have this structure:

```
your-repo/
├── your-plugin.php          # Main plugin file
├── includes/                # Plugin includes
├── admin/                   # Admin interface
├── assets/                  # CSS, JS, images
├── languages/               # Translation files
├── plugin-update-checker/   # Update checker library
├── readme.txt               # WordPress readme (for plugins)
└── README.md                # Repository documentation
```

### Update Methods

#### 1. GitHub Releases (Recommended)

Create releases on GitHub:
- Tag name: `v1.0.1` (following semantic versioning)
- Release title: `Version 1.0.1`
- Description: Changelog and update notes
- Assets: Upload the plugin ZIP file

Enable in your code:
```php
$update_checker->getVcsApi()->enableReleaseAssets();
```

#### 2. Branch-based Updates

Updates based on a specific branch:
```php
$update_checker->setBranch('stable');
```

#### 3. Git Tags

Create tags for each version:
```bash
git tag v1.0.1
git push origin v1.0.1
```

### Version Management

#### Plugin Header Version
```php
/**
 * Plugin Name: Your Plugin
 * Version: 1.0.0
 */
```

#### Semantic Versioning
Use semantic versioning (MAJOR.MINOR.PATCH):
- `1.0.0` - Initial release
- `1.0.1` - Bug fix
- `1.1.0` - New feature
- `2.0.0` - Breaking changes

## Update Process

### Automatic Update Flow

1. **Check Frequency**: Every 12 hours (configurable)
2. **Version Check**: Compares local vs. remote version
3. **Notification**: Shows update notice in WordPress admin
4. **Download**: Downloads new version from repository
5. **Installation**: Uses WordPress automatic update system
6. **Activation**: Plugin remains activated after update

### Manual Update Check

Users can manually check for updates:
1. Go to **Plugins** → **Installed Plugins**
2. Find your plugin
3. Click **Check for updates** link
4. If update available, click **Update Now**

## Troubleshooting

### Common Issues

#### 1. Update Checker Not Loading
**Symptoms**: No update notifications, errors in logs

**Solutions**:
```php
// Check if library is loaded
if (!class_exists('Puc_v4_Factory')) {
    error_log('Plugin update checker library not found');
}

// Verify file paths
$update_checker_path = plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
if (!file_exists($update_checker_path)) {
    error_log('Update checker file missing: ' . $update_checker_path);
}
```

#### 2. GitHub API Rate Limits
**Symptoms**: Update checks fail, 403 errors

**Solutions**:
```php
// Add GitHub token for higher rate limits
$update_checker->setAuthentication('your-github-token');

// Increase check interval
$update_checker->setCheckPeriod(24); // Check every 24 hours
```

#### 3. Version Not Updating
**Symptoms**: Update available but version doesn't change

**Solutions**:
- Verify version number in plugin header
- Check GitHub repository for latest version
- Clear WordPress cache
- Check file permissions

#### 4. Update Download Fails
**Symptoms**: Update notification shows but download fails

**Solutions**:
- Check GitHub repository accessibility
- Verify ZIP file exists in releases
- Check server download permissions
- Review WordPress update logs

### Debug Tools

#### 1. Debug Bar Integration
Install Debug Bar plugin and check the "PUC" panel for:
- Update checker status
- Last check time
- Version comparison details
- Error messages

#### 2. Manual Update Check
```php
// Force update check
$update_checker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/your-username/your-repo/',
    __FILE__,
    'your-plugin-slug'
);
$update_checker->checkForUpdates();
```

#### 3. Logging
Enable error logging:
```php
// Add to your plugin
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        $update_checker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/your-username/your-repo/',
            __FILE__,
            'your-plugin-slug'
        );
        
        $last_check = $update_checker->getLastRequestApiErrors();
        if (!empty($last_check)) {
            echo '<div class="notice notice-warning"><p>Update checker errors: ' . esc_html(print_r($last_check, true)) . '</p></div>';
        }
    }
});
```

## Security Considerations

### Repository Security

1. **Private Repositories**: Use GitHub tokens for private repos
2. **Code Signing**: Consider code signing for releases
3. **HTTPS Only**: Always use HTTPS for repository URLs
4. **Access Control**: Limit repository access to trusted developers

### Update Security

1. **Version Validation**: Verify version numbers before update
2. **File Integrity**: Check file checksums if possible
3. **Backup**: Always backup before updates
4. **Testing**: Test updates in staging environment

### Best Practices

1. **Semantic Versioning**: Use proper version numbering
2. **Changelog**: Maintain detailed changelog for each version
3. **Release Notes**: Provide clear release notes for users
4. **Rollback Plan**: Have rollback strategy for failed updates

## API Reference

### Puc_v4_Factory Methods

#### buildUpdateChecker($metadataUrl, $pluginFile, $slug = null)
Creates a new update checker instance.

**Parameters**:
- `$metadataUrl`: URL to metadata file or repository
- `$pluginFile`: Path to main plugin file
- `$slug`: Optional plugin slug

**Returns**: Update checker instance

#### setBranch($branch)
Sets the Git branch to check for updates.

#### setAuthentication($token)
Sets GitHub authentication token.

#### setCheckPeriod($hours)
Sets how often to check for updates (in hours).

#### enableReleaseAssets()
Enables GitHub release assets for updates.

### Update Checker Instance Methods

#### checkForUpdates()
Manually triggers an update check.

#### getUpdate()
Returns update information if available.

#### getLastRequestApiErrors()
Returns any API errors from the last request.

### Hooks and Filters

#### Actions
```php
// Fired when update is available
do_action('puc_pre_inject_update', $update);

// Fired before update is injected
do_action('puc_post_inject_update', $update);
```

#### Filters
```php
// Modify update information
add_filter('puc_pre_inject_update', function($update) {
    // Modify $update object
    return $update;
});

// Modify request arguments
add_filter('puc_request_update_query_args', function($args) {
    // Add custom arguments
    return $args;
});
```

## Examples

### Example 1: Basic Implementation

```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 */

// Load update checker
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

// Initialize update checker
add_action('plugins_loaded', function() {
    if (class_exists('Puc_v4_Factory')) {
        Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/your-username/my-plugin/',
            __FILE__,
            'my-plugin'
        );
    }
});
```

### Example 2: Advanced Implementation with Settings

```php
<?php
/**
 * Plugin Name: Advanced Plugin
 * Version: 1.0.0
 */

require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

class Advanced_Plugin_Update_Checker {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_init', array($this, 'add_settings'));
    }
    
    public function init() {
        if (!class_exists('Puc_v4_Factory')) {
            return;
        }
        
        $update_checker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/your-username/advanced-plugin/',
            __FILE__,
            'advanced-plugin'
        );
        
        // Enable releases
        $update_checker->getVcsApi()->enableReleaseAssets();
        
        // Set authentication from settings
        $github_token = get_option('advanced_plugin_github_token');
        if ($github_token) {
            $update_checker->setAuthentication($github_token);
        }
        
        // Add license key
        $update_checker->addQueryArgFilter(function($queryArgs) {
            $queryArgs['license_key'] = get_option('advanced_plugin_license_key');
            return $queryArgs;
        });
    }
    
    public function add_settings() {
        add_settings_section(
            'advanced_plugin_updates',
            'Update Settings',
            array($this, 'settings_section_callback'),
            'general'
        );
        
        add_settings_field(
            'advanced_plugin_github_token',
            'GitHub Token',
            array($this, 'github_token_callback'),
            'general',
            'advanced_plugin_updates'
        );
        
        register_setting('general', 'advanced_plugin_github_token');
    }
    
    public function settings_section_callback() {
        echo '<p>Configure update settings for Advanced Plugin.</p>';
    }
    
    public function github_token_callback() {
        $token = get_option('advanced_plugin_github_token');
        echo '<input type="text" name="advanced_plugin_github_token" value="' . esc_attr($token) . '" class="regular-text">';
        echo '<p class="description">Enter your GitHub personal access token for private repositories.</p>';
    }
}

new Advanced_Plugin_Update_Checker();
```

### Example 3: Multi-Plugin Implementation

```php
<?php
/**
 * Plugin Name: Plugin Suite
 * Version: 1.0.0
 */

require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

class Plugin_Suite_Update_Manager {
    
    private $plugins = array();
    
    public function __construct() {
        $this->register_plugins();
        add_action('plugins_loaded', array($this, 'init_all_updaters'));
    }
    
    private function register_plugins() {
        $this->plugins = array(
            'plugin-a' => array(
                'file' => __FILE__,
                'slug' => 'plugin-a',
                'repo' => 'https://github.com/your-username/plugin-a/'
            ),
            'plugin-b' => array(
                'file' => plugin_dir_path(__FILE__) . 'plugin-b/plugin-b.php',
                'slug' => 'plugin-b',
                'repo' => 'https://github.com/your-username/plugin-b/'
            )
        );
    }
    
    public function init_all_updaters() {
        if (!class_exists('Puc_v4_Factory')) {
            return;
        }
        
        foreach ($this->plugins as $plugin) {
            $update_checker = Puc_v4_Factory::buildUpdateChecker(
                $plugin['repo'],
                $plugin['file'],
                $plugin['slug']
            );
            
            $update_checker->getVcsApi()->enableReleaseAssets();
        }
    }
}

new Plugin_Suite_Update_Manager();
```

## Migration Guide

### From Manual Updates

1. **Backup Current System**: Backup existing update mechanism
2. **Install Update Checker**: Add plugin-update-checker library
3. **Configure Repository**: Set up GitHub repository
4. **Update Code**: Implement update checker initialization
5. **Test Updates**: Test update process thoroughly
6. **Remove Old System**: Remove manual update code

### From Other Update Systems

1. **Identify Current System**: Document existing update mechanism
2. **Plan Migration**: Create migration plan
3. **Implement Gradually**: Implement in phases
4. **Test Thoroughly**: Test all update scenarios
5. **Monitor**: Monitor update process after migration

## Performance Considerations

### Update Check Frequency

- **Default**: 12 hours (good balance)
- **High-traffic sites**: 24 hours (reduce server load)
- **Development**: 1 hour (frequent testing)

### Caching

The update checker implements caching:
- **Metadata cache**: 12 hours by default
- **Version cache**: Until next check
- **Error cache**: 1 hour for failed requests

### Server Load

- **Concurrent checks**: Limited to prevent overload
- **Timeout handling**: 30-second timeout for requests
- **Error handling**: Graceful degradation on failures

## Support and Maintenance

### Regular Maintenance

1. **Version Updates**: Keep plugin version current
2. **Repository Maintenance**: Maintain clean repository
3. **Release Management**: Regular release schedule
4. **Documentation**: Keep documentation updated

### Monitoring

1. **Update Success Rate**: Monitor successful updates
2. **Error Tracking**: Track and resolve errors
3. **User Feedback**: Collect user feedback on updates
4. **Performance Metrics**: Monitor update performance

### Support Resources

- **GitHub Issues**: Report bugs and feature requests
- **Documentation**: Keep this guide updated
- **Community**: Engage with user community
- **Testing**: Regular testing of update process

---

## Conclusion

This implementation guide provides everything you need to add automatic updates to your WordPress plugin. By following these steps, you can provide your users with a seamless update experience similar to WordPress.org plugins.

Remember to:
- Test thoroughly before releasing
- Maintain proper version numbering
- Keep your repository organized
- Monitor update success rates
- Provide clear release notes

For additional support, refer to the [Plugin Update Checker documentation](https://github.com/YahnisElsts/plugin-update-checker) or create an issue in the repository.
