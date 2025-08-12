# Troubleshooting: Dashboard Files Missing Error

## Error Message
"Backstage Access: Dashboard files are missing. Please reinstall the plugin."

## Possible Causes & Solutions

### 1. WordPress Plugin Upload Issue
**Problem**: WordPress didn't extract the ZIP file properly, missing the `includes` folder.

**Solution**: 
1. Use the **debug version**: `backstage-access-plugin-debug.zip`
2. This version will show you exactly which files are missing
3. Upload and activate this version first to see the debug information

### 2. File Permissions Issue
**Problem**: Files exist but can't be read due to permissions.

**Solution**:
1. Check file permissions on your server
2. Ensure the `includes` folder and files are readable by the web server
3. Set folder permissions to 755 and file permissions to 644

### 3. Plugin Directory Structure
**Expected Structure**:
```
backstage-access/
├── backstage-access.php
├── includes/
│   ├── class-dashboard.php
│   └── class-content-manager.php
├── assets/
│   ├── css/
│   │   ├── content-admin.css
│   │   └── dashboard.css
│   └── js/
│       ├── content-admin.js
│       └── dashboard.js
└── templates/
    └── dashboard-main.php
```

### 4. Manual File Upload
**If automatic upload fails**:

1. **Extract the ZIP file** on your computer
2. **Upload via FTP/cPanel**:
   - Upload all files to `/wp-content/plugins/backstage-access/`
   - Ensure the folder structure matches the expected structure above
3. **Activate the plugin** from WordPress admin

### 5. Enable Debug Mode
**To see detailed error information**:

1. **Edit wp-config.php**:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Check error logs**:
   - Look in `/wp-content/debug.log`
   - Or check your hosting provider's error logs

### 6. Verify File Existence
**Check these files exist in your plugin directory**:
- `backstage-access.php` (main plugin file)
- `includes/class-dashboard.php`
- `includes/class-content-manager.php`
- `assets/css/content-admin.css`
- `assets/css/dashboard.css`
- `assets/js/content-admin.js`
- `assets/js/dashboard.js`
- `templates/dashboard-main.php`

## Files Available for Download

### Option 1: Standard Version
- **File**: `backstage-access-plugin-fixed-v2.zip`
- **Use**: Normal installation

### Option 2: Debug Version
- **File**: `backstage-access-plugin-debug.zip`
- **Use**: Shows detailed error information
- **Recommended**: Use this first to diagnose the issue

## Step-by-Step Recovery Process

### Method 1: Use Debug Version
1. Download `backstage-access-plugin-debug.zip`
2. Upload and activate in WordPress
3. Check the error message for specific file paths
4. Verify those files exist in the correct location

### Method 2: Manual Upload
1. Download and extract `backstage-access-plugin-fixed-v2.zip`
2. Upload the entire folder via FTP to `/wp-content/plugins/`
3. Rename folder to `backstage-access` if needed
4. Activate the plugin in WordPress admin

### Method 3: Fresh Installation
1. **Deactivate and delete** the current plugin
2. **Clear any cached files** (if using caching plugins)
3. **Upload the new ZIP file**
4. **Activate the plugin**

## Still Having Issues?

If none of the above solutions work:

1. **Enable WP_DEBUG** and check error logs
2. **Use the debug version** to see specific error details
3. **Check file permissions** on your server
4. **Try manual FTP upload** instead of WordPress upload
5. **Contact your hosting provider** for assistance
