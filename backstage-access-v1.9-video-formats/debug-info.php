<?php
/**
 * Debug Information for Backstage Access Plugin
 * Add this to your WordPress debug log or run it directly to check plugin status
 */

// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    echo "This file must be run within WordPress.\n";
    exit;
}

echo "<h2>Backstage Access Debug Information</h2>\n";

// Check basic WordPress functions
echo "<h3>WordPress Environment</h3>\n";
echo "WordPress Version: " . get_bloginfo('version') . "<br>\n";
echo "PHP Version: " . PHP_VERSION . "<br>\n";
echo "Plugin Directory: " . plugin_dir_path(__FILE__) . "<br>\n";

// Check if required functions exist
echo "<h3>Required Functions</h3>\n";
$required_functions = ['get_users', 'wp_roles', 'get_role', 'add_role'];
foreach ($required_functions as $func) {
    $status = function_exists($func) ? '✓' : '✗';
    echo "{$status} {$func}<br>\n";
}

// Check user count
echo "<h3>User Information</h3>\n";
try {
    $users = get_users(['fields' => 'ID']);
    echo "Total Users: " . count($users) . "<br>\n";
    
    $test_users = get_users(['number' => 5]);
    echo "Sample Users Retrieved: " . count($test_users) . "<br>\n";
} catch (Exception $e) {
    echo "Error getting users: " . $e->getMessage() . "<br>\n";
}

// Check roles
echo "<h3>Role Information</h3>\n";
try {
    if (function_exists('wp_roles') && wp_roles()) {
        $roles = wp_roles()->roles;
        echo "Total Roles: " . count($roles) . "<br>\n";
        echo "Available Roles: " . implode(', ', array_keys($roles)) . "<br>\n";
    } else {
        echo "wp_roles() function not available or returned null<br>\n";
    }
} catch (Exception $e) {
    echo "Error getting roles: " . $e->getMessage() . "<br>\n";
}

// Check plugin files
echo "<h3>Plugin Files</h3>\n";
$required_files = [
    'includes/class-dashboard.php',
    'includes/class-content-manager.php',
    'assets/js/content-admin.js',
    'assets/css/content-admin.css'
];

foreach ($required_files as $file) {
    $full_path = plugin_dir_path(__FILE__) . $file;
    $status = file_exists($full_path) ? '✓' : '✗';
    echo "{$status} {$file}<br>\n";
}

// Check for class existence
echo "<h3>Plugin Classes</h3>\n";
$classes = ['BackstageAccess', 'BA_Dashboard', 'BA_Content_Manager'];
foreach ($classes as $class) {
    $status = class_exists($class) ? '✓' : '✗';
    echo "{$status} {$class}<br>\n";
}

// Check WooCommerce
echo "<h3>WooCommerce Integration</h3>\n";
$wc_status = class_exists('WooCommerce') ? '✓ Active' : '✗ Not Active';
echo "WooCommerce: {$wc_status}<br>\n";

if (function_exists('wc_get_products')) {
    try {
        $products = wc_get_products(['limit' => 1]);
        echo "WooCommerce Products Test: ✓ Working<br>\n";
    } catch (Exception $e) {
        echo "WooCommerce Products Test: ✗ Error - " . $e->getMessage() . "<br>\n";
    }
}

echo "<h3>Current Request Info</h3>\n";
echo "Current User Can Manage Options: " . (current_user_can('manage_options') ? '✓' : '✗') . "<br>\n";
echo "Current Admin Page: " . (isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'N/A') . "<br>\n";
echo "Current Tab: " . (isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'N/A') . "<br>\n";

?>
