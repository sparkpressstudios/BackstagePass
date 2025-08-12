# Backstage Access WordPress Plugin

A comprehensive WordPress plugin that provides role-based content access with WooCommerce integration, featuring a user dashboard for videos, documents, and YouTube content.

## Features

- **WooCommerce Integration**: Automatically assign user roles based on product purchases
- **Content Management**: Admin interface to assign videos, documents, and YouTube content to specific roles
- **User Dashboard**: Beautiful, responsive dashboard integrated into WooCommerce My Account
- **Multiple Content Types**: Support for WordPress media library videos, documents, and YouTube videos
- **User Statistics**: Track video views, file downloads, and user activity
- **Favorites System**: Users can favorite content for easy access
- **Responsive Design**: Mobile-first design that works on all devices
- **AJAX Functionality**: Smooth user experience with real-time updates

## Installation

1. Download the plugin files
2. Upload the entire `backstage-access` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Ensure WooCommerce is installed and activated

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+

## Usage

### Admin Configuration

1. Go to **Backstage Access** in your WordPress admin menu
2. Use the **Role Mapping** tab to assign user roles to WooCommerce products
3. Create custom roles in the **Create Role** tab if needed
4. Use **Content Management** tab to assign videos, documents, and YouTube content to roles
5. Manage users and bulk assign roles in the **Users & Bulk Assign** tab

### User Experience

Users will see a "Backstage Pass" tab in their WooCommerce My Account section where they can:
- View assigned videos with built-in player
- Download assigned documents
- Watch embedded YouTube videos
- Track their viewing statistics
- Favorite content for quick access
- View their purchase history that granted access

### Shortcodes

The plugin provides several shortcodes for content restriction:

```php
// Restrict content by roles and/or products
[backstage_content roles="backstage_member,premium_user" products="123,456" logic="or"]
    This content is only visible to users with the specified roles or products.
[/backstage_content]

// Display login form for non-logged-in users
[backstage_login fallback="Please log in to view exclusive content."]

// Show user information
[backstage_user_info show="name"] // Options: name, email, roles, products

// Check if user has purchased specific products
[backstage_product_check products="123,456" has_text="You have access!" no_text="Purchase required"]
```

## File Structure

```
backstage-access/
├── backstage-access.php          # Main plugin file
├── includes/
│   ├── class-dashboard.php       # WooCommerce dashboard integration
│   └── class-content-manager.php # Admin content management
├── templates/
│   └── dashboard-main.php        # User dashboard template
├── assets/
│   ├── css/
│   │   ├── dashboard.css         # Frontend dashboard styles
│   │   └── content-admin.css     # Admin interface styles
│   └── js/
│       ├── dashboard.js          # Frontend functionality
│       └── content-admin.js      # Admin interface functionality
└── README.md                     # This file
```

## Customization

### Styling

You can override the plugin's CSS by adding styles to your theme:

```css
/* Customize dashboard colors */
.ba-dashboard {
    --primary-color: #your-color;
    --accent-color: #your-accent;
}

/* Override specific components */
.ba-welcome-section {
    background: your-custom-gradient;
}
```

### Templates

Copy `templates/dashboard-main.php` to your theme directory under `backstage-access/dashboard-main.php` to customize the dashboard layout.

### Hooks and Filters

The plugin provides several hooks for customization:

```php
// Modify user content before display
add_filter('ba_user_content', function($content, $user_id, $roles) {
    // Modify $content array
    return $content;
}, 10, 3);

// Add custom content types
add_action('ba_content_types', function($content_manager) {
    // Add your custom content type
});
```

## Support

For support, feature requests, or bug reports, please contact SparkPress Studios.

## Changelog

### Version 1.1
- Added comprehensive dashboard system
- WooCommerce My Account integration
- Content management interface
- User statistics and favorites
- Mobile-responsive design
- YouTube video support
- Enhanced security and error handling

### Version 1.0
- Initial release
- Basic content restriction
- Role-based access control
- WooCommerce integration

## License

This plugin is proprietary software developed by SparkPress Studios.
