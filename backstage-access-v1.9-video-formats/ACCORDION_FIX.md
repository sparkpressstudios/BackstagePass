# Accordion Fix for Content Management

## Problem
The accordion sections in the Content Management tab were not opening or closing when clicked.

## Root Cause
The JavaScript event handlers for the accordion toggle functionality were not properly bound to the dynamic content, and there were potential issues with script loading.

## Solutions Implemented

### 1. JavaScript Event Delegation
- **File**: `assets/js/content-admin.js`
- **Fix**: Changed from direct event binding to event delegation using `$(document).on()`
- **Benefit**: Works with dynamically loaded content and is more robust

### 2. Enhanced Toggle Functionality
- **File**: `assets/js/content-admin.js`
- **Improvements**:
  - Added support for clicking anywhere on the header (not just the toggle arrow)
  - Added keyboard support (Enter and Space keys)
  - Added ARIA attributes for accessibility
  - Added visual feedback and debugging console logs

### 3. Improved CSS Styling
- **File**: `assets/css/content-admin.css`
- **Enhancements**:
  - Added hover effects for better user experience
  - Improved transition animations
  - Better visual feedback for collapsed/expanded states

### 4. PHP Template Updates
- **File**: `includes/class-content-manager.php`
- **Changes**:
  - Added proper ARIA attributes (`role="button"`, `aria-expanded`, `tabindex`)
  - Updated script version numbers to ensure cache busting
  - Added debugging output when WP_DEBUG is enabled

### 5. Fallback JavaScript Solution
- **File**: `includes/class-content-manager.php`
- **Feature**: Added inline JavaScript as a fallback if the main script fails to load
- **Includes**:
  - Basic accordion functionality
  - Console logging for debugging
  - Auto-collapse for sections when there are many

## How to Test

### 1. Basic Functionality Test
1. Go to **Admin → Backstage Access → Content Management**
2. You should see role sections with triangular arrows (▼)
3. Click on any role header or arrow
4. The section should collapse/expand with smooth animation
5. The arrow should rotate when collapsed

### 2. Keyboard Accessibility Test
1. Use Tab key to navigate to a role header
2. Press Enter or Space bar
3. The section should toggle open/closed

### 3. Auto-Collapse Test
1. If you have more than 3 roles, the 2nd, 3rd, etc. should be collapsed by default
2. Only the first role section should be expanded initially

### 4. Debug Information
1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Look for messages like:
   - "Backstage Access Content Admin: JavaScript initialized"
   - "Found X role sections"
   - "Toggle role section: [role_name] [collapsed/expanded]"

## Troubleshooting

### If Accordion Still Doesn't Work

1. **Check Console for Errors**
   - Open F12 Developer Tools
   - Look for JavaScript errors in Console tab

2. **Verify Script Loading**
   - Go to Network tab in Developer Tools
   - Refresh the page
   - Look for `content-admin.js` and `content-admin.css` files
   - They should load with 200 status

3. **Test Fallback Functionality**
   - The fallback script should work even if main script fails
   - Look for "Fallback accordion initialized" in console

4. **Clear Cache**
   - Clear browser cache
   - If using caching plugins, clear them too
   - Script version updated to 1.2 to force reload

### If Scripts Are Not Loading

1. **Check File Permissions**
   - Ensure `assets/js/content-admin.js` is readable
   - Ensure `assets/css/content-admin.css` is readable

2. **Check Plugin Path**
   - Verify plugin is in correct directory
   - Check that `plugin_dir_url()` is returning correct path

3. **Enable Debug Mode**
   - Add `define('WP_DEBUG', true);` to wp-config.php
   - Check error logs for any PHP errors

## Code Changes Summary

### JavaScript Changes (`content-admin.js`)
```javascript
// Old: Direct binding
$('.ba-role-toggle').on('click', function(e) { ... });

// New: Event delegation
$(document).on('click', '.ba-role-toggle', function(e) { ... });
```

### CSS Changes (`content-admin.css`)
```css
/* Added hover effects and better transitions */
.ba-role-section h3:hover {
    background: #e9ecef;
}

.ba-role-toggle {
    transition: transform 0.3s ease;
}
```

### PHP Changes (`class-content-manager.php`)
```php
// Added ARIA attributes
echo '<h3 role="button" aria-expanded="true" tabindex="0">';

// Added fallback script
$this->add_fallback_accordion_script();
```

## Verification

After implementing these fixes, the accordion should:
- ✅ Open and close smoothly when clicked
- ✅ Show visual feedback on hover
- ✅ Work with keyboard navigation
- ✅ Auto-collapse extra sections
- ✅ Work even if main JavaScript fails (fallback)
- ✅ Be accessible to screen readers

## Contact

If you continue to experience issues, please check:
1. Browser console for error messages
2. WordPress error logs
3. Network tab for failed script loading
4. Ensure WordPress and plugins are up to date
