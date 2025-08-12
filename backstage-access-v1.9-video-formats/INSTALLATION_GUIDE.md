# Backstage Access Plugin v1.2 - Installation Guide

## 📦 Files Ready for Upload

**Main Plugin File**: `backstage-access-plugin-v1.2-fixed.zip` (40,741 bytes)

This ZIP file contains all the fixed and updated plugin files ready for WordPress installation.

## 🚀 Installation Steps

### Method 1: WordPress Admin Upload (Recommended)

1. **Login to WordPress Admin**
   - Go to your WordPress site admin area
   - Navigate to `Plugins > Add New`

2. **Upload Plugin**
   - Click `Upload Plugin` button at the top
   - Click `Choose File` and select `backstage-access-plugin-v1.2-fixed.zip`
   - Click `Install Now`

3. **Activate Plugin**
   - After installation, click `Activate Plugin`
   - You should see "Backstage Access" in your admin menu

### Method 2: FTP Upload (Alternative)

1. **Extract ZIP File**
   - Extract `backstage-access-plugin-v1.2-fixed.zip` to a folder
   - You should see a `backstage-access-plugin-v1.2` folder

2. **Upload via FTP**
   - Connect to your server via FTP
   - Navigate to `/wp-content/plugins/`
   - Upload the entire `backstage-access-plugin-v1.2` folder
   - Rename it to `backstage-access` (optional, for cleaner URL)

3. **Activate in WordPress**
   - Go to `Plugins > Installed Plugins`
   - Find "Backstage Access" and click `Activate`

## ⚙️ Post-Installation Setup

### 1. Check Dependencies
- **WooCommerce Required**: Make sure WooCommerce is installed and activated
- The plugin will show a warning if WooCommerce is missing

### 2. Initial Configuration

#### Step 1: Create Roles (if needed)
1. Go to `Backstage Access > Create Role`
2. Create custom roles like:
   - Role ID: `backstage_member`
   - Display Name: `Backstage Member`

#### Step 2: Map Products to Roles
1. Go to `Backstage Access > Role Mapping`
2. Select which WooCommerce products should assign which roles
3. Save the mappings

#### Step 3: Manage Users (Optional)
1. Go to `Backstage Access > Users & Bulk Assign`
2. Use the bulk assignment feature to assign roles to existing users

#### Step 4: Set Up Content (NEW FEATURE)
1. Go to `Backstage Access > Content Management`
2. **Accordion sections should now work properly!**
3. Assign videos, documents, and YouTube content to each role
4. Click on role headers to expand/collapse sections

#### Step 5: Configure Settings
1. Go to `Backstage Access > Settings`
2. Set cache duration for better performance
3. Review available shortcodes

## ✅ What's Been Fixed

### 🔧 Critical Error Resolution
- **Fixed**: "Critical error on this website" when accessing Users & Bulk Assign tab
- **Cause**: Incorrect user count calculation in pagination
- **Solution**: Proper array handling and error catching

### 🎯 Accordion Functionality
- **Fixed**: Content Management accordion sections not opening/closing
- **Added**: Improved JavaScript event handling with fallback
- **Enhanced**: Keyboard accessibility (Tab + Enter/Space)
- **Improved**: Visual feedback and smooth animations

### 🛡️ Enhanced Error Handling
- **Added**: Comprehensive try-catch blocks throughout
- **Improved**: User-friendly error messages
- **Enhanced**: Debug logging for troubleshooting
- **Added**: Graceful fallbacks for missing dependencies

### 🎨 User Experience Improvements
- **Added**: Better visual feedback on hover
- **Enhanced**: ARIA attributes for accessibility
- **Improved**: Console logging for debugging
- **Added**: Loading states and error recovery

## 🧪 Testing the Fixes

### 1. Test Users & Bulk Assign Tab
- Go to `Backstage Access > Users & Bulk Assign`
- Should load without critical errors
- Pagination should work properly
- User selection and role assignment should function

### 2. Test Content Management Accordion
- Go to `Backstage Access > Content Management`
- Click on any role section header
- Sections should expand/collapse smoothly
- Arrow should rotate with animation
- Try using Tab + Enter for keyboard navigation

### 3. Test Error Handling
- All tabs should load gracefully
- Any errors should show user-friendly messages
- Check browser console (F12) for debug information

## 🔍 Troubleshooting

### If You Still See Critical Errors

1. **Clear All Cache**
   - Clear browser cache
   - Clear WordPress cache (if using caching plugins)
   - Clear object cache (if applicable)

2. **Check Error Logs**
   - Look in `wp-content/debug.log` (if WP_DEBUG is enabled)
   - Check server error logs
   - Look for "Backstage Access" related errors

3. **Verify File Permissions**
   - Ensure plugin files are readable by web server
   - Check that JavaScript/CSS files can be loaded

4. **Re-upload if Necessary**
   - If problems persist, try uploading the plugin again
   - Make sure to overwrite existing files completely

### Debug Mode (Optional)

To enable debugging, add these lines to your `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

This will log errors to `/wp-content/debug.log` for investigation.

## 📋 Plugin Features Summary

### Core Functionality
- ✅ Restrict content based on WooCommerce purchases
- ✅ Restrict content based on user roles
- ✅ Auto-assign roles when products are purchased
- ✅ Bulk user role assignment
- ✅ Content management with media assignment
- ✅ Caching for performance optimization

### Available Shortcodes
```php
[backstage_content roles="role1,role2" products="1,2,3" logic="or"]
    Your protected content here
[/backstage_content]

[backstage_login fallback="Please log in"]

[backstage_user_info show="name|email|roles|products"]

[backstage_product_check products="1,2,3" 
    has_text="You own this" 
    no_text="You need to purchase"]
```

### Admin Interface
- **Role Mapping**: Connect WooCommerce products to user roles
- **Users & Bulk Assign**: Manage user roles in bulk (FIXED)
- **Create Role**: Create and manage custom user roles
- **Content Management**: Assign media content to roles (ACCORDION FIXED)
- **Settings**: Configure caching and view documentation

## 📞 Support

If you encounter any issues:

1. **Check browser console** (F12 → Console tab) for JavaScript errors
2. **Check WordPress error logs** for PHP errors
3. **Verify WooCommerce is active** and functioning
4. **Test with default WordPress theme** to rule out theme conflicts
5. **Disable other plugins temporarily** to check for conflicts

## 🎉 Upgrade Notes

- **Version**: Updated to 1.2 with all fixes applied
- **Backward Compatible**: All existing settings and data preserved
- **New Features**: Enhanced error handling and accordion functionality
- **Performance**: Improved caching and loading times

---

**Ready to Upload**: `backstage-access-plugin-v1.2-fixed.zip`

This plugin is now ready for production use with all critical errors resolved and enhanced functionality!
