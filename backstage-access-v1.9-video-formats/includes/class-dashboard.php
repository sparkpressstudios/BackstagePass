<?php
/**
 * Backstage Access Dashboard Class
 * Handles WooCommerce My Account integration and user dashboard
 */

if (!defined('ABSPATH')) exit;

class BA_Dashboard {
    
    private $endpoint = 'backstage';
    
    public function __construct() {
        // WooCommerce My Account integration
        add_action('init', [$this, 'add_wc_endpoints']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_item']);
        add_action('woocommerce_account_backstage_endpoint', [$this, 'render_dashboard']);
        
        // Enqueue dashboard assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_ba_mark_viewed', [$this, 'ajax_mark_content_viewed']);
        add_action('wp_ajax_ba_toggle_favorite', [$this, 'ajax_toggle_favorite']);
    }
    
    /**
     * Add WooCommerce endpoints
     */
    public function add_wc_endpoints() {
        add_rewrite_endpoint($this->endpoint, EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add menu item to WooCommerce My Account
     */
    public function add_menu_item($items) {
        // Ensure we have a valid items array
        if (!is_array($items)) {
            return $items;
        }
        
        // Insert backstage item before logout
        if (isset($items['customer-logout'])) {
            $logout = $items['customer-logout'];
            unset($items['customer-logout']);
            
            $items['backstage'] = __('Backstage Pass', 'backstage-access');
            $items['customer-logout'] = $logout;
        } else {
            // Fallback: just add at the end
            $items['backstage'] = __('Backstage Pass', 'backstage-access');
        }
        
        return $items;
    }
    
    /**
     * Render the backstage dashboard
     */
    public function render_dashboard() {
        if (!is_user_logged_in()) {
            if (function_exists('wc_print_notice')) {
                wc_print_notice(__('Please log in to access your backstage content.', 'backstage-access'), 'error');
            } else {
                echo '<div class="woocommerce-error"><p>' . __('Please log in to access your backstage content.', 'backstage-access') . '</p></div>';
            }
            return;
        }
        
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        // Check if user has any backstage roles
        $backstage_roles = $this->get_backstage_roles();
        $user_backstage_roles = array_intersect($user_roles, array_keys($backstage_roles));
        
        if (empty($user_backstage_roles)) {
            echo '<div class="woocommerce-info">';
            echo '<p>' . __('You don\'t have access to backstage content yet. Purchase one of our products to get access!', 'backstage-access') . '</p>';
            
            // Check if WooCommerce shop page exists
            $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
            echo '<a href="' . esc_url($shop_url) . '" class="button">' . __('Browse Products', 'backstage-access') . '</a>';
            echo '</div>';
            return;
        }
        
        // Load dashboard template
        $this->load_dashboard_template($user, $user_backstage_roles);
    }
    
    /**
     * Load dashboard template
     */
    private function load_dashboard_template($user, $user_roles) {
        // Check if Content Manager class exists
        if (!class_exists('BA_Content_Manager')) {
            echo '<div class="woocommerce-error">';
            echo '<p>' . __('Dashboard content manager is not available. Please contact administrator.', 'backstage-access') . '</p>';
            echo '</div>';
            return;
        }
        
        $content_manager = new BA_Content_Manager();
        $user_content = $content_manager->get_user_content($user->ID, $user_roles);
        $user_stats = $this->get_user_stats($user->ID);
        $recent_purchases = $this->get_recent_purchases($user->ID);
        
        $template_path = plugin_dir_path(__FILE__) . '../templates/dashboard-main.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="woocommerce-error">';
            echo '<p>' . __('Dashboard template not found. Please contact administrator.', 'backstage-access') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Get backstage roles
     */
    private function get_backstage_roles() {
        $all_roles = wp_roles()->roles;
        $backstage_roles = [];
        
        foreach ($all_roles as $key => $role) {
            // Skip default WordPress roles
            if (!in_array($key, ['administrator', 'editor', 'author', 'contributor', 'subscriber'])) {
                $backstage_roles[$key] = $role;
            }
        }
        
        return $backstage_roles;
    }
    
    /**
     * Get user statistics
     */
    private function get_user_stats($user_id) {
        $stats = get_user_meta($user_id, 'ba_user_stats', true);
        if (!$stats) {
            $stats = [
                'videos_watched' => 0,
                'files_downloaded' => 0,
                'last_activity' => null,
                'total_time_spent' => 0
            ];
        }
        return $stats;
    }
    
    /**
     * Get recent purchases that granted backstage access
     */
    private function get_recent_purchases($user_id) {
        // Check if WooCommerce is active and available
        if (!function_exists('wc_get_orders')) {
            return [];
        }
        
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => 'completed',
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $backstage_purchases = [];
        $product_role_map = get_option('ba_product_role_map', []);
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if (isset($product_role_map[$product_id])) {
                    $backstage_purchases[] = [
                        'order_id' => $order->get_id(),
                        'product_name' => $item->get_name(),
                        'product_id' => $product_id,
                        'date' => $order->get_date_created(),
                        'roles' => $product_role_map[$product_id]
                    ];
                }
            }
        }
        
        return $backstage_purchases;
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets() {
        if ((function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('backstage')) || 
            (isset($GLOBALS['wp_query']->query_vars['backstage']))) {
            
            $plugin_url = plugin_dir_url(dirname(__FILE__));
            
            wp_enqueue_style('ba-dashboard', $plugin_url . 'assets/css/dashboard.css', [], '1.9');
            wp_enqueue_script('ba-dashboard', $plugin_url . 'assets/js/dashboard.js', ['jquery'], '1.9', true);
            
            wp_localize_script('ba-dashboard', 'ba_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ba_dashboard'),
                'strings' => [
                    'loading' => __('Loading...', 'backstage-access'),
                    'error' => __('An error occurred.', 'backstage-access')
                ]
            ]);
        }
    }
    
    /**
     * AJAX: Mark content as viewed
     */
    public function ajax_mark_content_viewed() {
        // Check nonce and user login
        if (!check_ajax_referer('ba_dashboard', 'nonce', false) || !is_user_logged_in()) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $content_id = intval($_POST['content_id'] ?? 0);
        $content_type = sanitize_text_field($_POST['content_type'] ?? '');
        $user_id = get_current_user_id();
        
        if (!$user_id || !$content_id || !in_array($content_type, ['video', 'document', 'youtube'])) {
            wp_send_json_error('Invalid request parameters');
            return;
        }
        
        // Update user stats
        $stats = $this->get_user_stats($user_id);
        
        if ($content_type === 'video') {
            $stats['videos_watched']++;
        } elseif ($content_type === 'document') {
            $stats['files_downloaded']++;
        }
        
        $stats['last_activity'] = current_time('mysql');
        update_user_meta($user_id, 'ba_user_stats', $stats);
        
        // Track content view
        $viewed_content = get_user_meta($user_id, 'ba_viewed_content', true) ?: [];
        if (!in_array($content_id, $viewed_content)) {
            $viewed_content[] = $content_id;
            update_user_meta($user_id, 'ba_viewed_content', $viewed_content);
        }
        
        wp_send_json_success(['message' => 'Content marked as viewed']);
    }
    
    /**
     * AJAX: Toggle favorite content
     */
    public function ajax_toggle_favorite() {
        // Check nonce and user login
        if (!check_ajax_referer('ba_dashboard', 'nonce', false) || !is_user_logged_in()) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $content_id = intval($_POST['content_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$content_id) {
            wp_send_json_error('Invalid request parameters');
            return;
        }
        
        $favorites = get_user_meta($user_id, 'ba_favorite_content', true) ?: [];
        
        if (in_array($content_id, $favorites)) {
            $favorites = array_diff($favorites, [$content_id]);
            $action = 'removed';
        } else {
            $favorites[] = $content_id;
            $action = 'added';
        }
        
        update_user_meta($user_id, 'ba_favorite_content', $favorites);
        
        wp_send_json_success(['action' => $action, 'favorites_count' => count($favorites)]);
    }
}
