# Backstage Access Plugin - Troubleshooting Guide

## Critical Error on "Users & Bulk Assign" Tab

If you're experiencing a critical error when accessing the "Users & Bulk Assign" tab, here are the steps to resolve it:

### Quick Fix
The most common cause is a PHP error in the user count calculation. This has been fixed in the updated plugin files.

### Steps to Resolve:

1. **Check WordPress Requirements**
   - Ensure you're running WordPress 5.0 or higher
   - Verify PHP version is 7.4 or higher
   - Confirm WooCommerce is installed and activated

2. **Enable WordPress Debug Mode**
   ```php
   // Add these lines to your wp-config.php file
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **Check Error Logs**
   - Look in `/wp-content/debug.log` for specific error messages
   - Check your hosting provider's error logs

4. **Run Debug Information**
   - Access the `debug-info.php` file in the plugin directory
   - This will show you the current status of all plugin requirements

### Common Issues and Solutions:

#### Issue: "Fatal error: Call to undefined function"
**Solution:** WordPress core functions are not loaded properly
- Deactivate and reactivate the plugin
- Check that WordPress is fully loaded before the plugin initializes

#### Issue: "Cannot count array in admin_users_tab"
**Solution:** Fixed in the updated version
- The user count calculation has been improved with proper error handling

#### Issue: "wp_roles() function not available"
**Solution:** WordPress roles system not initialized
- Try accessing the page again after a few seconds
- Clear any caching plugins
- Check if other plugins are interfering

#### Issue: Missing files error
**Solution:** Plugin files are incomplete
- Re-upload all plugin files
- Check file permissions (should be 644 for files, 755 for directories)

### Advanced Troubleshooting:

1. **Plugin Conflicts**
   - Deactivate all other plugins temporarily
   - Test if the issue persists
   - Reactivate plugins one by one to identify conflicts

2. **Theme Conflicts**
   - Switch to a default WordPress theme (Twenty Twenty-Three)
   - Test the plugin functionality
   - If it works, there's a theme conflict

3. **Memory Issues**
   - Increase PHP memory limit in wp-config.php:
     ```php
     ini_set('memory_limit', '256M');
     ```

4. **Database Issues**
   - Check if the user and role tables are intact
   - Run WordPress database repair if needed

### Getting Help:

If the issue persists after trying these steps:

1. **Gather Information:**
   - WordPress version
   - PHP version
   - Active plugins list
   - Theme name
   - Error messages from debug.log
   - Output from debug-info.php

2. **Contact Support:**
   - Include all the information above
   - Describe the exact steps that trigger the error
   - Include any relevant error messages

### Prevention:

To prevent similar issues in the future:

1. **Keep WordPress Updated**
   - Always use the latest stable version of WordPress
   - Update plugins and themes regularly

2. **Use Staging Environment**
   - Test plugin updates on a staging site first
   - Keep regular backups

3. **Monitor Error Logs**
   - Regularly check for PHP errors and warnings
   - Address issues promptly

4. **Proper Plugin Management**
   - Only install plugins from trusted sources
   - Remove unused plugins
   - Keep plugins updated

### File Permissions:

Ensure proper file permissions:
- Files: 644
- Directories: 755
- wp-config.php: 600

### Database Backup:

Before making any changes, always backup your database:
```bash
mysqldump -u username -p database_name > backup.sql
```

This troubleshooting guide should help resolve most issues with the Backstage Access plugin.
