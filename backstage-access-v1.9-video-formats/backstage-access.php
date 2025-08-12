<?php
/**
 * Plugin Name: Backstage Access
 * Description: Restrict content based on WooCommerce purchases or user roles. Auto-assign roles on purchase. Admin dashboard with bulk assignment.
 * Version: 1.9
 * Author: SparkPress Studios
 */

if (!defined('ABSPATH')) exit;

// Plugin activation/deactivation hooks
register_activation_hook(__FILE__, 'backstage_access_activate');
register_deactivation_hook(__FILE__, 'backstage_access_deactivate');
register_uninstall_hook(__FILE__, 'backstage_access_uninstall');

function backstage_access_activate() {
    // Create default options
    add_option('ba_product_role_map', []);
    add_option('ba_plugin_version', '1.8');
    add_option('ba_cache_duration', HOUR_IN_SECONDS);
    add_option('ba_content_assignments', []);
    
    // Create a default backstage role if it doesn't exist
    if (!get_role('backstage_member')) {
        add_role('backstage_member', 'Backstage Member', ['read' => true]);
    }
    
    // Add WooCommerce endpoint
    add_rewrite_endpoint('backstage', EP_ROOT | EP_PAGES);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

function backstage_access_deactivate() {
    // Clear all transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ba_user_products_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ba_user_products_%'");
    
    flush_rewrite_rules();
}

function backstage_access_uninstall() {
    // Remove plugin options
    delete_option('ba_product_role_map');
    delete_option('ba_plugin_version');
    delete_option('ba_cache_duration');
    delete_option('ba_content_assignments');
    
    // Clear all transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ba_user_products_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ba_user_products_%'");
    
    // Clean up user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ba_%'");
}

class BackstageAccess {
    public function init_assets() {
        add_action('admin_enqueue_scripts', function($hook) {
            if ($hook !== 'toplevel_page_backstage-access') return;
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
            wp_add_inline_script('select2', 'jQuery(document).ready(function($){ $("select[multiple]").select2(); });');
        });
    }

    public function __construct() {
        $this->init_assets();
        add_action('admin_notices', [$this, 'show_admin_notices']);
        add_shortcode('backstage_content', [$this, 'shortcode_content']);
        add_shortcode('backstage_login', [$this, 'shortcode_login']);
        add_shortcode('backstage_user_info', [$this, 'shortcode_user_info']);
        add_shortcode('backstage_product_check', [$this, 'shortcode_product_check']);
        add_action('woocommerce_order_status_completed', [$this, 'assign_roles_on_purchase']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_ba_save_mappings', [$this, 'save_product_role_mappings']);
        add_action('admin_post_ba_create_role', [$this, 'create_role']);
        add_action('admin_post_ba_delete_role', [$this, 'delete_role']);
        add_action('admin_post_ba_rename_role', [$this, 'rename_role']);
        add_action('admin_post_ba_bulk_assign_roles', [$this, 'bulk_assign_roles']);
        add_action('admin_post_ba_save_settings', [$this, 'save_settings']);
        add_action('admin_post_ba_clear_cache', [$this, 'clear_cache']);
        add_action('admin_post_ba_save_content', [$this, 'save_content_assignments']);
        
        // Check for WooCommerce dependency
        add_action('admin_init', [$this, 'check_dependencies']);
        
        // Initialize dashboard and content manager
        $this->init_dashboard();
        $this->init_content_manager();
    }

    public function shortcode_content($atts, $content = null) {
        if (!is_user_logged_in()) {
            $atts = shortcode_atts(['login_message' => 'Please log in to view this content.'], $atts);
            return '<div class="backstage-login-required">' . esc_html($atts['login_message']) . '</div>';
        }
        
        $user = wp_get_current_user();
        $atts = shortcode_atts([
            'roles' => '', 
            'products' => '',
            'logic' => 'or', // 'and' or 'or'
            'deny_message' => 'You do not have access to this content.'
        ], $atts);
        
        $roles = array_filter(array_map('trim', explode(',', $atts['roles'])));
        $products = array_filter(array_map('intval', explode(',', $atts['products'])));
        
        $has_role = !empty($roles) ? !empty(array_intersect($roles, $user->roles)) : true;
        $has_product = !empty($products) ? $this->user_has_products($user->ID, $products) : true;
        
        // Apply logic
        $access_granted = false;
        if ($atts['logic'] === 'and') {
            $access_granted = $has_role && $has_product;
        } else {
            $access_granted = $has_role || $has_product;
        }
        
        if ($access_granted) {
            return do_shortcode($content);
        } else {
            return '<div class="backstage-access-denied">' . esc_html($atts['deny_message']) . '</div>';
        }
    }

    public function shortcode_login($atts) {
        if (is_user_logged_in()) return '';
        $atts = shortcode_atts(['fallback' => 'Please log in to view this content.'], $atts);
        
        ob_start();
        echo '<div style="max-width: 400px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05);">';
        wp_login_form(['echo' => true]);
        echo '<p style="text-align: center; font-style: italic; color: #666;">' . esc_html($atts['fallback']) . '</p>';
        echo '</div>';
        return ob_get_clean();
    }

    public function shortcode_user_info($atts) {
        if (!is_user_logged_in()) return '';
        
        $user = wp_get_current_user();
        $atts = shortcode_atts([
            'show' => 'name', // name, email, roles, products
            'separator' => ', '
        ], $atts);
        
        switch ($atts['show']) {
            case 'email':
                return esc_html($user->user_email);
            case 'roles':
                return esc_html(implode($atts['separator'], $user->roles));
            case 'products':
                $products = $this->get_user_purchased_products($user->ID);
                $product_names = [];
                foreach ($products as $product_id) {
                    if (function_exists('wc_get_product')) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            $product_names[] = $product->get_name();
                        }
                    }
                }
                return esc_html(implode($atts['separator'], $product_names));
            default:
                return esc_html($user->display_name);
        }
    }

    public function shortcode_product_check($atts) {
        if (!is_user_logged_in()) return '';
        
        $atts = shortcode_atts([
            'products' => '',
            'has_text' => 'You have purchased this product.',
            'no_text' => 'You have not purchased this product.'
        ], $atts);
        
        $products = array_filter(array_map('intval', explode(',', $atts['products'])));
        $user_id = get_current_user_id();
        
        $has_products = $this->user_has_products($user_id, $products);
        return $has_products ? esc_html($atts['has_text']) : esc_html($atts['no_text']);
    }

    public function get_user_purchased_products($user_id) {
        $cache_key = 'ba_user_products_' . $user_id;
        $user_products = get_transient($cache_key);
        
        if (false === $user_products) {
            $user_products = [];
            
            if (function_exists('wc_get_orders')) {
                $orders = wc_get_orders([
                    'customer_id' => $user_id, 
                    'status' => 'completed',
                    'limit' => -1
                ]);
                
                foreach ($orders as $order) {
                    foreach ($order->get_items() as $item) {
                        $user_products[] = $item->get_product_id();
                    }
                }
            }
            
            $user_products = array_unique($user_products);
            $cache_duration = get_option('ba_cache_duration', HOUR_IN_SECONDS);
            set_transient($cache_key, $user_products, $cache_duration);
        }
        
        return $user_products;
    }

    public function user_has_products($user_id, $product_ids = []) {
        if (empty($product_ids)) return false;
        
        $user_products = $this->get_user_purchased_products($user_id);
        return !empty(array_intersect($product_ids, $user_products));
    }

    public function assign_roles_on_purchase($order_id) {
        if (!function_exists('wc_get_order')) return;
        
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $user_id = $order->get_user_id();
        if (!$user_id) return;

        $mapping = get_option('ba_product_role_map', []);
        if (empty($mapping)) return;
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $roles_assigned = [];
        
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            if (!empty($mapping[$pid])) {
                foreach ($mapping[$pid] as $role) {
                    if (!in_array($role, $user->roles) && !in_array($role, $roles_assigned)) {
                        $user->add_role($role);
                        $roles_assigned[] = $role;
                    }
                }
            }
        }
        
        // Clear user products cache when roles are assigned
        if (!empty($roles_assigned)) {
            delete_transient('ba_user_products_' . $user_id);
            
            // Log the role assignment
            error_log(sprintf(
                'Backstage Access: Assigned roles %s to user %d after order %d completion',
                implode(', ', $roles_assigned),
                $user_id,
                $order_id
            ));
        }
    }

    public function admin_menu() {
        add_menu_page('Backstage Access', 'Backstage Access', 'manage_options', 'backstage-access', [$this, 'admin_page'], 'dashicons-lock', 50);
    }

    public function admin_page() {
        // Add error handling wrapper
        try {
            $tab = sanitize_key($_GET['tab'] ?? 'mapping');
            echo '<div class="wrap"><h1>Backstage Access</h1><nav class="nav-tab-wrapper">';
            echo '<a class="nav-tab' . ($tab === 'mapping' ? ' nav-tab-active' : '') . '" href="?page=backstage-access&tab=mapping">Role Mapping</a>';
            echo '<a class="nav-tab' . ($tab === 'users' ? ' nav-tab-active' : '') . '" href="?page=backstage-access&tab=users">Users & Bulk Assign</a>';
            echo '<a class="nav-tab' . ($tab === 'roles' ? ' nav-tab-active' : '') . '" href="?page=backstage-access&tab=roles">Create Role</a>';
            echo '<a class="nav-tab' . ($tab === 'content' ? ' nav-tab-active' : '') . '" href="?page=backstage-access&tab=content">Content Management</a>';
            echo '<a class="nav-tab' . ($tab === 'settings' ? ' nav-tab-active' : '') . '" href="?page=backstage-access&tab=settings">Settings</a>';
            echo '</nav>';

            // Wrap tab content in error handling
            echo '<div class="tab-content">';
            
            switch ($tab) {
                case 'roles': 
                    $this->safe_execute_tab_method('admin_create_role_tab'); 
                    break;
                case 'users': 
                    $this->safe_execute_tab_method('admin_users_tab'); 
                    break;
                case 'content': 
                    $this->safe_execute_tab_method('admin_content_tab'); 
                    break;
                case 'settings': 
                    $this->safe_execute_tab_method('admin_settings_tab'); 
                    break;
                default: 
                    $this->safe_execute_tab_method('admin_mapping_tab'); 
                    break;
            }
            
            echo '</div>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="wrap">';
            echo '<h1>Backstage Access - Error</h1>';
            echo '<div class="notice notice-error"><p>An error occurred while loading the admin page: ' . esc_html($e->getMessage()) . '</p></div>';
            echo '<p><a href="' . admin_url('admin.php?page=backstage-access') . '" class="button">Try Again</a></p>';
            echo '</div>';
            error_log('Backstage Access Admin Page Error: ' . $e->getMessage());
        } catch (Error $e) {
            echo '<div class="wrap">';
            echo '<h1>Backstage Access - Fatal Error</h1>';
            echo '<div class="notice notice-error"><p>A fatal error occurred. Please check the error logs and contact support if the issue persists.</p></div>';
            echo '<p><a href="' . admin_url('admin.php?page=backstage-access') . '" class="button">Try Again</a></p>';
            echo '</div>';
            error_log('Backstage Access Admin Page Fatal Error: ' . $e->getMessage());
        }
    }

    private function safe_execute_tab_method($method_name) {
        try {
            if (method_exists($this, $method_name)) {
                $this->$method_name();
            } else {
                echo '<div class="notice notice-error"><p>Tab method "' . esc_html($method_name) . '" not found.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error loading tab content: ' . esc_html($e->getMessage()) . '</p></div>';
            error_log('Backstage Access Tab Error (' . $method_name . '): ' . $e->getMessage());
        } catch (Error $e) {
            echo '<div class="notice notice-error"><p>Fatal error loading tab content. Please check the error logs.</p></div>';
            error_log('Backstage Access Tab Fatal Error (' . $method_name . '): ' . $e->getMessage());
        }
    }

    public function admin_mapping_tab() {
        if (!function_exists('wc_get_products')) {
            echo '<div class="notice notice-error"><p>WooCommerce is required for this functionality.</p></div>';
            return;
        }
        
        // Compact instructions with collapsible design
        echo '<div class="ba-tab-instructions">';
        echo '<div class="ba-instruction-header" onclick="baToggleInstructions(this)">';
        echo '<h3>🎯 Role Mapping Guide</h3>';
        echo '<span class="ba-instruction-toggle">▼</span>';
        echo '</div>';
        echo '<div class="ba-instruction-content">';
        echo '<div class="ba-instruction-inner">';
        
        echo '<div class="ba-info-cards">';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">🔗</span>';
        echo '<div class="ba-info-title">Connect Products</div>';
        echo '<div class="ba-info-description">Link WooCommerce products to <span class="ba-tooltip" data-tooltip="When customers purchase these products, they automatically get the selected roles">user roles</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">⚡</span>';
        echo '<div class="ba-info-title">Auto Assignment</div>';
        echo '<div class="ba-info-description">Roles assigned when <span class="ba-tooltip" data-tooltip="Orders must reach Completed status for automatic role assignment">orders complete</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">👥</span>';
        echo '<div class="ba-info-title">Multiple Roles</div>';
        echo '<div class="ba-info-description">One product can assign <span class="ba-tooltip" data-tooltip="Users can have multiple roles for different access levels">several roles</span></div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-quick-tips">';
        echo '<h4>Quick Tips</h4>';
        echo '<ul>';
        echo '<li>Create <span class="ba-tooltip" data-tooltip="Go to Create Role tab to add custom roles">custom roles</span> first before mapping</li>';
        echo '<li>Users keep roles even after <span class="ba-tooltip" data-tooltip="Role removal is not automatic - manage manually if needed">refunds</span></li>';
        echo '<li>Use <span class="ba-tooltip" data-tooltip="Select multiple roles from dropdown using Ctrl/Cmd+click">multiple role selection</span> for comprehensive access</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div></div></div>';
        
        $products = wc_get_products(['limit' => -1]);
        $roles = wp_roles()->roles;
        $map = get_option('ba_product_role_map', []);

        // Product mapping table with better organization
        echo '<div class="ba-section-divider"></div>';
        echo '<h3>Product to Role Mappings</h3>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="ba_save_mappings">';
        wp_nonce_field('ba_mapping');

        if (empty($products)) {
            echo '<div class="ba-empty-state">';
            echo '<p>📦 No WooCommerce products found</p>';
            echo '<p>Create some products in WooCommerce first, then return here to map them to roles.</p>';
            echo '</div>';
        } else {
            echo '<table class="widefat"><thead><tr><th>Product <span class="ba-tooltip" data-tooltip="All your WooCommerce products are listed here">ℹ️</span></th><th>Assigned Roles <span class="ba-tooltip" data-tooltip="Select which roles customers get when they buy this product">ℹ️</span></th></tr></thead><tbody>';
            foreach ($products as $product) {
                $pid = $product->get_id();
                echo '<tr><td>' . esc_html($product->get_name()) . ' <small>(#' . $pid . ')</small></td><td>';
                echo '<select name="mapping[' . $pid . '][]" multiple style="width: 100%;" class="ba-tooltip" data-tooltip="Hold Ctrl/Cmd to select multiple roles">';
                foreach ($roles as $key => $r) {
                    $selected = (!empty($map[$pid]) && in_array($key, $map[$pid])) ? 'selected' : '';
                    echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($r['name']) . '</option>';
                }
                echo '</select></td></tr>';
            }
            echo '</tbody></table>';
            submit_button('Save Role Mappings', 'primary', 'submit', false, ['style' => 'margin-top: 15px;']);
        }
        echo '</form>';
        
        $this->render_instruction_toggle_script();
    }

    public function admin_users_tab() {
        // Compact instructions with collapsible design
        echo '<div class="ba-tab-instructions">';
        echo '<div class="ba-instruction-header" onclick="baToggleInstructions(this)">';
        echo '<h3>👥 User Management Guide</h3>';
        echo '<span class="ba-instruction-toggle">▼</span>';
        echo '</div>';
        echo '<div class="ba-instruction-content">';
        echo '<div class="ba-instruction-inner">';
        
        echo '<div class="ba-info-cards">';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">📋</span>';
        echo '<div class="ba-info-title">Browse Users</div>';
        echo '<div class="ba-info-description">View all users with <span class="ba-tooltip" data-tooltip="Navigate through pages to see all users">pagination support</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">✅</span>';
        echo '<div class="ba-info-title">Bulk Selection</div>';
        echo '<div class="ba-info-description">Select multiple users for <span class="ba-tooltip" data-tooltip="Assign roles to many users at once">batch operations</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">🎭</span>';
        echo '<div class="ba-info-title">Role Assignment</div>';
        echo '<div class="ba-info-description">Manually assign <span class="ba-tooltip" data-tooltip="Users can have multiple roles simultaneously">any role</span> to users</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-quick-tips">';
        echo '<h4>Common Use Cases</h4>';
        echo '<ul>';
        echo '<li><strong>Welcome Package:</strong> Give new members <span class="ba-tooltip" data-tooltip="Create a welcome_member role for special content">welcome roles</span></li>';
        echo '<li><strong>Beta Access:</strong> Grant <span class="ba-tooltip" data-tooltip="Perfect for testing new features with select users">beta_tester roles</span> to power users</li>';
        echo '<li><strong>VIP Treatment:</strong> Upgrade customers to <span class="ba-tooltip" data-tooltip="Give special customers premium access">VIP status</span></li>';
        echo '<li><strong>Free Access:</strong> Provide <span class="ba-tooltip" data-tooltip="Great for scholarships or promotional access">complimentary roles</span> to specific users</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div></div></div>';
        
        // Add debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Backstage Access: Loading users tab');
        }
        
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Add error handling
        try {
            $users = get_users([
                'number' => $per_page,
                'offset' => $offset
            ]);
            
            // Get total user count properly
            $total_users = count(get_users(['fields' => 'ID']));
            $total_pages = ceil($total_users / $per_page);
            
            // Check if wp_roles() is available
            if (!function_exists('wp_roles') || !wp_roles()) {
                echo '<div class="notice notice-error"><p>Unable to load WordPress roles. Please refresh the page.</p></div>';
                return;
            }
            
            $roles = wp_roles()->roles;
            
            if (empty($roles)) {
                echo '<div class="notice notice-error"><p>No roles found. Please check your WordPress installation.</p></div>';
                return;
            }
        } catch (Exception $e) {
            error_log('Backstage Access Users Tab Error: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>Error loading users: ' . esc_html($e->getMessage()) . '</p></div>';
            return;
        } catch (Error $e) {
            error_log('Backstage Access Users Tab Fatal Error: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>Fatal error loading users. Please check the error logs.</p></div>';
            return;
        }

        echo '<div class="ba-section-divider"></div>';

        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="ba_bulk_assign_roles">';
        wp_nonce_field('ba_bulk_roles');

        if (empty($users)) {
            echo '<div class="ba-empty-state">';
            echo '<p>👥 No users found</p>';
            echo '<p>There are no users in your WordPress installation.</p>';
            echo '</div>';
        } else {
            // Compact user statistics
            echo '<div class="ba-info-cards" style="margin-bottom: 15px;">';
            echo '<div class="ba-info-card">';
            echo '<span class="ba-info-icon">👥</span>';
            echo '<div class="ba-info-title">' . number_format($total_users) . '</div>';
            echo '<div class="ba-info-description">Total Users</div>';
            echo '</div>';
            echo '<div class="ba-info-card">';
            echo '<span class="ba-info-icon">📄</span>';
            echo '<div class="ba-info-title">Page ' . $page . ' of ' . $total_pages . '</div>';
            echo '<div class="ba-info-description">Showing ' . count($users) . ' users</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<table class="widefat">';
            echo '<thead><tr>';
            echo '<th><span class="ba-tooltip" data-tooltip="Check users to assign roles in bulk">Select</span></th>';
            echo '<th>User <span class="ba-tooltip" data-tooltip="Display name and user ID">ℹ️</span></th>';
            echo '<th>Email</th>';
            echo '<th>Current Roles <span class="ba-tooltip" data-tooltip="All roles currently assigned to this user">ℹ️</span></th>';
            echo '</tr></thead><tbody>';
            
            foreach ($users as $user) {
                // Ensure user object is valid
                if (!is_object($user) || !isset($user->ID)) {
                    continue;
                }
                
                echo '<tr>';
                echo '<td><input type="checkbox" name="user_ids[]" value="' . esc_attr($user->ID) . '"></td>';
                echo '<td>' . esc_html($user->display_name ?: $user->user_login) . ' <small>(#' . $user->ID . ')</small></td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . esc_html(is_array($user->roles) ? implode(', ', $user->roles) : 'No roles') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // Compact pagination
        if (!empty($users) && $total_pages > 1) {
            echo '<div class="tablenav" style="margin: 15px 0;"><div class="tablenav-pages">';
            $page_links = paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '‹',
                'next_text' => '›',
                'total' => $total_pages,
                'current' => $page
            ]);
            if ($page_links) {
                echo $page_links;
            }
            echo '</div></div>';
        }

        // Compact role assignment section
        if (!empty($users)) {
            echo '<div class="ba-bulk-assignment-section">';
            echo '<h4>🎭 Bulk Role Assignment</h4>';
            echo '<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">';
            echo '<label for="role" style="margin: 0;">Assign role:</label>';
            echo '<select name="role" id="role" class="ba-tooltip" data-tooltip="Choose which role to assign to selected users">';
            foreach ($roles as $key => $r) {
                echo '<option value="' . esc_attr($key) . '">' . esc_html($r['name']) . '</option>';
            }
            echo '</select>';
            submit_button('Assign to Selected', 'primary', 'submit', false, ['style' => 'margin: 0;']);
            echo '</div>';
            echo '</div>';
        }
        echo '</form>';
        
        $this->render_instruction_toggle_script();
    }

    public function admin_create_role_tab() {
        // Display notifications
        if (isset($_GET['error'])) {
            $error_messages = [
                'role_exists' => 'Role already exists. Please choose a different role ID.',
                'creation_failed' => 'Failed to create role. Please try again.',
                'role_has_users' => 'Cannot delete role: It has users assigned to it. Please reassign users first.',
                'invalid_role' => 'Invalid role or cannot modify default WordPress roles.',
                'empty_fields' => 'Please fill in all required fields.',
                'empty_role' => 'Role key cannot be empty.'
            ];
            $message = $error_messages[$_GET['error']] ?? 'An unknown error occurred.';
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }
        
        if (isset($_GET['success'])) {
            echo '<div class="notice notice-success"><p>Role created successfully!</p></div>';
        }
        
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success"><p>Role deleted successfully.</p></div>';
        }
        
        if (isset($_GET['renamed'])) {
            echo '<div class="notice notice-success"><p>Role renamed successfully.</p></div>';
        }
        
        echo '<div class="ba-role-management">';
        
        // Compact instructions with collapsible design
        echo '<div class="ba-tab-instructions">';
        echo '<div class="ba-instruction-header" onclick="baToggleInstructions(this)">';
        echo '<h3>🎭 Role Creation & Management Guide</h3>';
        echo '<span class="ba-instruction-toggle">▼</span>';
        echo '</div>';
        echo '<div class="ba-instruction-content">';
        echo '<div class="ba-instruction-inner">';
        
        echo '<div class="ba-info-cards">';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">✏️</span>';
        echo '<div class="ba-info-title">Create Roles</div>';
        echo '<div class="ba-info-description">Design <span class="ba-tooltip" data-tooltip="Each role represents a different access level">custom access levels</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">✏️</span>';
        echo '<div class="ba-info-title">Manage Existing</div>';
        echo '<div class="ba-info-description">Rename or delete <span class="ba-tooltip" data-tooltip="You cannot delete roles with assigned users">custom roles</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">🔗</span>';
        echo '<div class="ba-info-title">Connect to Products</div>';
        echo '<div class="ba-info-description">Map roles to <span class="ba-tooltip" data-tooltip="Go to Role Mapping tab after creating roles">WooCommerce products</span></div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-quick-tips">';
        echo '<h4>Role Examples & Best Practices</h4>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">';
        echo '<div class="ba-tip-card"><strong>🥉 bronze_member</strong><br>Basic tier access</div>';
        echo '<div class="ba-tip-card"><strong>🥈 silver_member</strong><br>Intermediate access</div>';
        echo '<div class="ba-tip-card"><strong>🥇 gold_member</strong><br>Premium tier access</div>';
        echo '<div class="ba-tip-card"><strong>🎓 course_student</strong><br>Specific course access</div>';
        echo '</div>';
        echo '<ul style="margin-top: 15px;">';
        echo '<li><strong>Role IDs:</strong> Use <span class="ba-tooltip" data-tooltip="WordPress requirement for role names">lowercase with underscores</span> only</li>';
        echo '<li><strong>Cannot modify:</strong> Default WordPress roles are <span class="ba-tooltip" data-tooltip="Administrator, Editor, Author, etc. are protected">protected</span></li>';
        echo '<li><strong>Cannot delete:</strong> Roles with <span class="ba-tooltip" data-tooltip="Reassign users first, then delete the role">assigned users</span></li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div></div></div>';

        echo '<div class="ba-section-divider"></div>';
        
        // Create new role form with compact styling
        echo '<div class="ba-create-role-section">';
        echo '<h4>📝 Create New Role</h4>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" class="ba-create-role-form">';
        echo '<input type="hidden" name="action" value="ba_create_role">';
        wp_nonce_field('ba_create_role');
        echo '<div class="ba-form-section">';
        echo '<div class="ba-form-row">';
        echo '<label for="new_role" class="ba-tooltip" data-tooltip="Use lowercase letters and underscores only">Role ID:</label>';
        echo '<input type="text" id="new_role" name="new_role" placeholder="e.g., premium_member" required pattern="[a-z_]+" title="Only lowercase letters and underscores allowed" style="flex: 1;" />';
        echo '</div>';
        echo '<div class="ba-form-row">';
        echo '<label for="new_role_name" class="ba-tooltip" data-tooltip="Friendly name shown in admin interface">Display Name:</label>';
        echo '<input type="text" id="new_role_name" name="new_role_name" placeholder="e.g., Premium Member" required style="flex: 1;" />';
        echo '</div>';
        echo '<div class="ba-form-actions">';
        submit_button('Create Role', 'primary', 'submit', false);
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="ba-section-divider"></div>';
        
        // Show existing custom roles with management options
        echo '<div class="ba-manage-roles-section">';
        echo '<h4>🗂️ Manage Existing Roles</h4>';
        $roles = wp_roles()->roles;
        $custom_roles = [];
        foreach ($roles as $key => $role) {
            if (!in_array($key, ['administrator', 'editor', 'author', 'contributor', 'subscriber'])) {
                $custom_roles[$key] = $role;
            }
        }
        
        if (!empty($custom_roles)) {
            echo '<table class="widefat ba-roles-table">';
            echo '<thead><tr>';
            echo '<th>Role ID <span class="ba-tooltip" data-tooltip="Internal WordPress role identifier">ℹ️</span></th>';
            echo '<th>Display Name</th>';
            echo '<th>Users <span class="ba-tooltip" data-tooltip="Number of users with this role">ℹ️</span></th>';
            echo '<th>Actions</th>';
            echo '</tr></thead><tbody>';
            foreach ($custom_roles as $key => $role) {
                $user_count = count(get_users(['role' => $key]));
                echo '<tr data-role="' . esc_attr($key) . '">';
                echo '<td><code>' . esc_html($key) . '</code></td>';
                echo '<td class="ba-role-display-name" data-original="' . esc_attr($role['name']) . '">' . esc_html($role['name']) . '</td>';
                echo '<td>' . $user_count . '</td>';
                echo '<td class="ba-role-actions">';
                echo '<button type="button" class="button ba-rename-role-btn" data-role="' . esc_attr($key) . '">Rename</button> ';
                if ($user_count == 0) {
                    echo '<button type="button" class="button button-link-delete ba-delete-role-btn" data-role="' . esc_attr($key) . '">Delete</button>';
                } else {
                    echo '<span class="description ba-tooltip" data-tooltip="Reassign users first to enable deletion">Cannot delete (' . $user_count . ' users)</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="ba-empty-state">';
            echo '<p>🎭 No custom roles created yet</p>';
            echo '<p>Create your first custom role using the form above to get started with membership levels!</p>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
        
        // Add rename modal
        $this->render_rename_role_modal();
        
        // Enqueue role management scripts
        $this->enqueue_role_management_scripts();
        
        $this->render_instruction_toggle_script();
    }

    private function render_rename_role_modal() {
        ?>
        <div id="ba-rename-modal" class="ba-rename-modal" style="display: none;">
            <div class="ba-modal-content">
                <div class="ba-modal-header">
                    <h3>Rename Role</h3>
                    <span class="ba-modal-close" onclick="baHideRenameModal()">&times;</span>
                </div>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('ba_rename_role'); ?>
                    <input type="hidden" name="action" value="ba_rename_role">
                    <input type="hidden" name="role_key" id="ba-rename-role-key">
                    <p>
                        <label for="ba-rename-new-name">New Role Name:</label><br>
                        <input type="text" name="new_name" id="ba-rename-new-name" style="width: 100%; margin-top: 5px;" required>
                    </p>
                    <p>
                        <button type="submit" class="button-primary">Rename Role</button>
                        <button type="button" class="button" onclick="baHideRenameModal()">Cancel</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    private function enqueue_role_management_scripts() {
        ?>
        <style>
        .ba-rename-modal { position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .ba-modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 5px; }
        .ba-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .ba-modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .ba-modal-close:hover { color: black; }
        .ba-roles-table th, .ba-roles-table td { padding: 8px 12px; }
        .ba-role-actions { display: flex; gap: 8px; align-items: center; }
        .ba-role-actions button { padding: 4px 8px; font-size: 11px; }
        </style>
        
        <script>
        function baShowRenameModal(roleKey, roleName) {
            document.getElementById('ba-rename-role-key').value = roleKey;
            document.getElementById('ba-rename-new-name').value = roleName;
            document.getElementById('ba-rename-modal').style.display = 'block';
        }
        
        function baHideRenameModal() {
            document.getElementById('ba-rename-modal').style.display = 'none';
        }
        
        function baDeleteRole(roleKey, roleName) {
            if (confirm('Are you sure you want to delete the role "' + roleName + '"? This action cannot be undone and will remove all content assignments for this role.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo admin_url('admin-post.php'); ?>';
                form.innerHTML = '<input type="hidden" name="action" value="ba_delete_role">' +
                               '<input type="hidden" name="role_key" value="' + roleKey + '">' +
                               '<?php echo wp_nonce_field("ba_delete_role", "_wpnonce", true, false); ?>';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('ba-rename-modal');
            if (event.target === modal) {
                baHideRenameModal();
            }
        }
        
        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ba-rename-role-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const roleKey = this.dataset.role;
                    const roleName = this.closest('tr').querySelector('.ba-role-display-name').dataset.original;
                    baShowRenameModal(roleKey, roleName);
                });
            });
            
            document.querySelectorAll('.ba-delete-role-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const roleKey = this.dataset.role;
                    const roleName = this.closest('tr').querySelector('.ba-role-display-name').textContent;
                    baDeleteRole(roleKey, roleName);
                });
            });
        });
        </script>
        <?php
    }

    public function admin_settings_tab() {
        // Compact instructions with collapsible design
        echo '<div class="ba-tab-instructions">';
        echo '<div class="ba-instruction-header" onclick="baToggleInstructions(this)">';
        echo '<h3>⚙️ Settings & Shortcode Guide</h3>';
        echo '<span class="ba-instruction-toggle">▼</span>';
        echo '</div>';
        echo '<div class="ba-instruction-content">';
        echo '<div class="ba-instruction-inner">';
        
        echo '<div class="ba-info-cards">';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">🔧</span>';
        echo '<div class="ba-info-title">Plugin Settings</div>';
        echo '<div class="ba-info-description">Configure <span class="ba-tooltip" data-tooltip="Cache settings affect performance and accuracy">cache & performance</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">🎯</span>';
        echo '<div class="ba-info-title">Shortcodes</div>';
        echo '<div class="ba-info-description">Display restricted content on <span class="ba-tooltip" data-tooltip="Copy codes into posts, pages, or widgets">pages and posts</span></div>';
        echo '</div>';
        echo '<div class="ba-info-card">';
        echo '<span class="ba-info-icon">🧹</span>';
        echo '<div class="ba-info-title">Cache Management</div>';
        echo '<div class="ba-info-description">Clear cached data for <span class="ba-tooltip" data-tooltip="Use when purchases don\'t appear immediately">fresh lookups</span></div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-quick-tips">';
        echo '<h4>Quick Settings Guide</h4>';
        echo '<ul>';
        echo '<li><strong>Cache Duration:</strong> Longer = <span class="ba-tooltip" data-tooltip="6-24 hours recommended for busy sites">better performance</span>, Shorter = <span class="ba-tooltip" data-tooltip="15 minutes-1 hour for real-time tracking">more accurate</span></li>';
        echo '<li><strong>Shortcodes:</strong> Copy from examples below and <span class="ba-tooltip" data-tooltip="Paste into WordPress editor where you want content">paste into posts/pages</span></li>';
        echo '<li><strong>Clear Cache:</strong> Use after <span class="ba-tooltip" data-tooltip="Forces plugin to rebuild user purchase data">bulk user changes</span></li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div></div></div>';

        echo '<div class="ba-section-divider"></div>';
        
        $cache_duration = get_option('ba_cache_duration', HOUR_IN_SECONDS);
        
        // Compact settings form
        echo '<div class="ba-settings-section">';
        echo '<h4>🔧 Plugin Settings</h4>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="ba_save_settings">';
        wp_nonce_field('ba_settings');
        
        echo '<div class="ba-form-section">';
        echo '<div class="ba-form-row">';
        echo '<label for="cache_duration" class="ba-tooltip" data-tooltip="How long to remember user purchase data">Cache Duration:</label>';
        echo '<select id="cache_duration" name="cache_duration" style="flex: 1;">';
        echo '<option value="' . (15 * MINUTE_IN_SECONDS) . '"' . selected($cache_duration, 15 * MINUTE_IN_SECONDS, false) . '>15 minutes</option>';
        echo '<option value="' . HOUR_IN_SECONDS . '"' . selected($cache_duration, HOUR_IN_SECONDS, false) . '>1 hour</option>';
        echo '<option value="' . (6 * HOUR_IN_SECONDS) . '"' . selected($cache_duration, 6 * HOUR_IN_SECONDS, false) . '>6 hours</option>';
        echo '<option value="' . DAY_IN_SECONDS . '"' . selected($cache_duration, DAY_IN_SECONDS, false) . '>24 hours</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="ba-form-actions">';
        submit_button('Save Settings', 'primary', 'submit', false);
        echo '</div>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="ba-section-divider"></div>';
        
        // Compact cache management
        echo '<div class="ba-cache-section">';
        echo '<h4>🧹 Cache Management</h4>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="display: flex; align-items: center; gap: 10px;">';
        echo '<input type="hidden" name="action" value="ba_clear_cache">';
        wp_nonce_field('ba_clear_cache');
        echo '<span class="ba-tooltip" data-tooltip="Forces fresh lookup of all user purchase data">Clear all cached user purchase data</span>';
        submit_button('Clear Cache', 'secondary', 'submit', false);
        echo '</form>';
        echo '</div>';

        echo '<div class="ba-section-divider"></div>';
        
        // Compact shortcode guide
        echo '<div class="ba-shortcode-guide">';
        echo '<h4>🎯 Shortcode Quick Reference</h4>';
        
        echo '<div class="ba-shortcode-section">';
        echo '<div class="ba-shortcode-category">';
        echo '<h5>🔒 Content Restriction</h5>';
        echo '<div class="ba-shortcode-examples">';
        
        echo '<div class="ba-shortcode-item">';
        echo '<div class="ba-shortcode-header">';
        echo '<strong>Role-based content</strong>';
        echo '<button class="ba-copy-btn" onclick="baCopyToClipboard(\'[backstage_content roles=\\\"vip_member\\\"]\\nVIP content here\\n[/backstage_content]\')">Copy</button>';
        echo '</div>';
        echo '<code>[backstage_content roles="vip_member"]VIP content here[/backstage_content]</code>';
        echo '</div>';
        
        echo '<div class="ba-shortcode-item">';
        echo '<div class="ba-shortcode-header">';
        echo '<strong>Product purchase check</strong>';
        echo '<button class="ba-copy-btn" onclick="baCopyToClipboard(\'[backstage_content products=\\\"123\\\"]\\nContent for product buyers\\n[/backstage_content]\')">Copy</button>';
        echo '</div>';
        echo '<code>[backstage_content products="123"]Content for product buyers[/backstage_content]</code>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-shortcode-category">';
        echo '<h5>🔑 User Interface</h5>';
        echo '<div class="ba-shortcode-examples">';
        
        echo '<div class="ba-shortcode-item">';
        echo '<div class="ba-shortcode-header">';
        echo '<strong>Login form</strong>';
        echo '<button class="ba-copy-btn" onclick="baCopyToClipboard(\'[backstage_login]\')">Copy</button>';
        echo '</div>';
        echo '<code>[backstage_login]</code>';
        echo '</div>';
        
        echo '<div class="ba-shortcode-item">';
        echo '<div class="ba-shortcode-header">';
        echo '<strong>User dashboard</strong>';
        echo '<button class="ba-copy-btn" onclick="baCopyToClipboard(\'[backstage_dashboard]\')">Copy</button>';
        echo '</div>';
        echo '<code>[backstage_dashboard]</code>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-shortcode-category">';
        echo '<h5>ℹ️ User Information</h5>';
        echo '<div class="ba-shortcode-examples">';
        
        echo '<div class="ba-shortcode-item">';
        echo '<div class="ba-shortcode-header">';
        echo '<strong>Display username</strong>';
        echo '<button class="ba-copy-btn" onclick="baCopyToClipboard(\'[backstage_user_info field=\\\"display_name\\\"]\')">Copy</button>';
        echo '</div>';
        echo '<code>[backstage_user_info field="display_name"]</code>';
        echo '</div>';
        
        echo '<div class="ba-shortcode-item">';
        echo '<div class="ba-shortcode-header">';
        echo '<strong>Show user roles</strong>';
        echo '<button class="ba-copy-btn" onclick="baCopyToClipboard(\'[backstage_user_roles]\')">Copy</button>';
        echo '</div>';
        echo '<code>[backstage_user_roles]</code>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        $this->render_shortcode_copy_script();
        $this->render_instruction_toggle_script();
    }

    private function render_instruction_toggle_script() {
        static $script_rendered = false;
        if ($script_rendered) return;
        $script_rendered = true;
        ?>
        <script>
        function baToggleInstructions(header) {
            const content = header.nextElementSibling;
            const toggle = header.querySelector('.ba-instruction-toggle');
            
            if (content.style.display === 'none' || content.style.display === '') {
                content.style.display = 'block';
                toggle.textContent = '▲';
                header.classList.add('active');
            } else {
                content.style.display = 'none';
                toggle.textContent = '▼';
                header.classList.remove('active');
            }
        }

        // Auto-collapse instructions by default
        document.addEventListener('DOMContentLoaded', function() {
            const instructionContents = document.querySelectorAll('.ba-instruction-content');
            instructionContents.forEach(content => {
                content.style.display = 'none';
            });
        });
        </script>
        <?php
    }

    private function render_shortcode_copy_script() {
        static $script_rendered = false;
        if ($script_rendered) return;
        $script_rendered = true;
        ?>
        <script>
        function baCopyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary success message
                const event = arguments.callee.caller.arguments[0];
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.background = '#46b450';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Copy failed. Please select and copy manually.');
            });
        }
        </script>
        <?php
    }

    public function save_product_role_mappings() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_mapping')) wp_die('Unauthorized');
        $data = $_POST['mapping'] ?? [];
        $clean = [];
        foreach ($data as $pid => $roles) {
            $pid = intval($pid);
            $clean[$pid] = array_map('sanitize_key', array_filter($roles));
        }
        update_option('ba_product_role_map', $clean);
        wp_redirect(admin_url('admin.php?page=backstage-access&tab=mapping&updated=true'));
        exit;
    }

    public function create_role() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_create_role')) wp_die('Unauthorized');
        
        $key = sanitize_key($_POST['new_role']);
        $name = sanitize_text_field($_POST['new_role_name']);
        
        // Validation
        if (empty($key) || empty($name)) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=empty_fields'));
            exit;
        }
        
        if (get_role($key)) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=role_exists'));
            exit;
        }
        
        // Create the role
        $result = add_role($key, $name, ['read' => true]);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&created=true'));
        } else {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=creation_failed'));
        }
        exit;
    }

    public function delete_role() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_delete_role')) wp_die('Unauthorized');
        
        $role_key = sanitize_key($_POST['role_key']);
        
        if (empty($role_key)) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=empty_role'));
            exit;
        }
        
        // Check if role exists and is not a default WordPress role
        $role = get_role($role_key);
        if (!$role || in_array($role_key, ['administrator', 'editor', 'author', 'contributor', 'subscriber'])) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=invalid_role'));
            exit;
        }
        
        // Check if role has users
        $users_with_role = get_users(['role' => $role_key]);
        if (!empty($users_with_role)) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=role_has_users'));
            exit;
        }
        
        // Remove the role
        remove_role($role_key);
        
        // Clean up any product mappings
        $product_role_map = get_option('ba_product_role_map', []);
        foreach ($product_role_map as $product_id => $roles) {
            $product_role_map[$product_id] = array_diff($roles, [$role_key]);
            if (empty($product_role_map[$product_id])) {
                unset($product_role_map[$product_id]);
            }
        }
        update_option('ba_product_role_map', $product_role_map);
        
        // Clean up content assignments
        $content_assignments = get_option('ba_content_assignments', []);
        if (isset($content_assignments[$role_key])) {
            unset($content_assignments[$role_key]);
            update_option('ba_content_assignments', $content_assignments);
        }
        
        wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&deleted=true'));
        exit;
    }

    public function rename_role() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_rename_role')) wp_die('Unauthorized');
        
        $role_key = sanitize_key($_POST['role_key']);
        $new_name = sanitize_text_field($_POST['new_name']);
        
        if (empty($role_key) || empty($new_name)) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=empty_fields'));
            exit;
        }
        
        // Check if role exists and is not a default WordPress role
        $role = get_role($role_key);
        if (!$role || in_array($role_key, ['administrator', 'editor', 'author', 'contributor', 'subscriber'])) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&error=invalid_role'));
            exit;
        }
        
        // Update the role name
        global $wp_roles;
        $wp_roles->roles[$role_key]['name'] = $new_name;
        update_option($wp_roles->role_key, $wp_roles->roles);
        
        wp_redirect(admin_url('admin.php?page=backstage-access&tab=roles&renamed=true'));
        exit;
    }

    public function bulk_assign_roles() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_bulk_roles')) {
            wp_die('Unauthorized');
        }
        
        $uids = array_map('intval', $_POST['user_ids'] ?? []);
        $role = sanitize_key($_POST['role'] ?? '');
        
        // Validate inputs
        if (empty($uids) || empty($role)) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=users&error=empty_selection'));
            exit;
        }
        
        // Verify role exists
        if (!get_role($role)) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=users&error=invalid_role'));
            exit;
        }
        
        $assigned_count = 0;
        foreach ($uids as $uid) {
            if ($uid > 0) {
                $user = get_userdata($uid);
                if ($user && !in_array($role, $user->roles)) {
                    $user->add_role($role);
                    $assigned_count++;
                }
            }
        }
        
        wp_redirect(admin_url('admin.php?page=backstage-access&tab=users&bulk=done&assigned=' . $assigned_count));
        exit;
    }

    public function save_settings() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_settings')) wp_die('Unauthorized');
        
        $cache_duration = intval($_POST['cache_duration'] ?? HOUR_IN_SECONDS);
        $valid_durations = [15 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, DAY_IN_SECONDS];
        
        if (in_array($cache_duration, $valid_durations)) {
            update_option('ba_cache_duration', $cache_duration);
        }
        
        wp_redirect(admin_url('admin.php?page=backstage-access&tab=settings&updated=true'));
        exit;
    }

    public function clear_cache() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_clear_cache')) wp_die('Unauthorized');
        
        // Clear all user product caches
        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ba_user_products_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ba_user_products_%'");
        
        wp_redirect(admin_url('admin.php?page=backstage-access&tab=settings&cache_cleared=' . $deleted));
        exit;
    }

    public function init_dashboard() {
        // Check if files exist before including
        $dashboard_file = plugin_dir_path(__FILE__) . 'includes/class-dashboard.php';
        $content_manager_file = plugin_dir_path(__FILE__) . 'includes/class-content-manager.php';
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Backstage Access: Looking for dashboard files');
            error_log('Dashboard file path: ' . $dashboard_file);
            error_log('Content manager file path: ' . $content_manager_file);
            error_log('Dashboard file exists: ' . (file_exists($dashboard_file) ? 'YES' : 'NO'));
            error_log('Content manager file exists: ' . (file_exists($content_manager_file) ? 'YES' : 'NO'));
            error_log('Plugin dir path: ' . plugin_dir_path(__FILE__));
        }
        
        if (!file_exists($dashboard_file) || !file_exists($content_manager_file)) {
            add_action('admin_notices', function() use ($dashboard_file, $content_manager_file) {
                echo '<div class="notice notice-error"><p><strong>Backstage Access:</strong> Dashboard files are missing. Please reinstall the plugin.</p>';
                if (current_user_can('manage_options')) {
                    echo '<p><strong>Debug Info:</strong></p>';
                    echo '<ul>';
                    echo '<li>Dashboard file: ' . esc_html($dashboard_file) . ' - ' . (file_exists($dashboard_file) ? 'EXISTS' : 'MISSING') . '</li>';
                    echo '<li>Content Manager file: ' . esc_html($content_manager_file) . ' - ' . (file_exists($content_manager_file) ? 'EXISTS' : 'MISSING') . '</li>';
                    echo '<li>Plugin directory: ' . esc_html(plugin_dir_path(__FILE__)) . '</li>';
                    echo '</ul>';
                }
                echo '</div>';
            });
            return;
        }
        
        // Include dashboard files
        require_once $dashboard_file;
        require_once $content_manager_file;
        
        // Initialize dashboard only if classes exist
        if (class_exists('BA_Dashboard') && class_exists('BA_Content_Manager')) {
            new BA_Dashboard();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Backstage Access: Dashboard initialized successfully');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Backstage Access: Dashboard classes not found after include');
            }
        }
    }

    public function init_content_manager() {
        // Check if files exist before including
        $content_manager_file = plugin_dir_path(__FILE__) . 'includes/class-content-manager.php';
        
        if (file_exists($content_manager_file)) {
            require_once $content_manager_file;
            
            // Initialize content manager to register AJAX handlers
            if (class_exists('BA_Content_Manager')) {
                new BA_Content_Manager();
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Backstage Access: Content Manager initialized for AJAX handlers');
                }
            }
        }
    }

    public function admin_content_tab() {
        // Check if class exists
        if (!class_exists('BA_Content_Manager')) {
            echo '<div class="notice notice-error"><p>Content Manager class not found.</p></div>';
            return;
        }
        
        $content_manager = new BA_Content_Manager();
        $content_manager->render_admin_content_tab();
    }

    public function save_content_assignments() {
        if (!current_user_can('manage_options') || !check_admin_referer('ba_content')) wp_die('Unauthorized');
        
        // Check if class exists
        if (!class_exists('BA_Content_Manager')) {
            wp_redirect(admin_url('admin.php?page=backstage-access&tab=content&error=class_missing'));
            exit;
        }
        
        $content_manager = new BA_Content_Manager();
        $content_manager->save_content_assignments();
        
        wp_redirect(admin_url('admin.php?page=backstage-access&tab=content&updated=true'));
        exit;
    }

    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Backstage Access:</strong> WooCommerce is required for this plugin to work properly.</p></div>';
            });
        }
    }

    public function show_admin_notices() {
        // Only show notices on our plugin pages
        $current_screen = get_current_screen();
        if (!$current_screen || strpos($current_screen->id, 'backstage-access') === false) {
            return;
        }
        
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
        }
        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Role created successfully.</p></div>';
        }
        if (isset($_GET['bulk'])) {
            $assigned = isset($_GET['assigned']) ? intval($_GET['assigned']) : 0;
            if ($assigned > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Bulk role assignment completed. ' . $assigned . ' users updated.</p></div>';
            } else {
                echo '<div class="notice notice-warning is-dismissible"><p>Bulk role assignment completed, but no users were modified (they may already have the selected role).</p></div>';
            }
        }
        if (isset($_GET['cache_cleared'])) {
            $count = intval($_GET['cache_cleared']);
            echo '<div class="notice notice-success is-dismissible"><p>Cache cleared successfully. ' . $count . ' cache entries removed.</p></div>';
        }
        if (isset($_GET['error'])) {
            $error = sanitize_key($_GET['error']);
            $messages = [
                'empty_fields' => 'Please fill in both role ID and display name.',
                'role_exists' => 'A role with this ID already exists.',
                'creation_failed' => 'Failed to create role. Please try again.',
                'class_missing' => 'Content Manager class is not available.',
                'empty_selection' => 'Please select at least one user and a role to assign.',
                'invalid_role' => 'The selected role is not valid. Please choose a different role.'
            ];
            if (isset($messages[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($messages[$error]) . '</p></div>';
            }
        }
    }
}

// Initialize the plugin only when WordPress is ready
function backstage_access_init() {
    if (class_exists('BackstageAccess')) {
        new BackstageAccess();
    }
}

// Hook into WordPress initialization - only use one hook to prevent duplicate initialization
add_action('plugins_loaded', 'backstage_access_init');
