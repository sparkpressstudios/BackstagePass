<?php
/**
 * Backstage Access Content Manager Class
 * Handles content assignment and organization
 */

if (!defined('ABSPATH')) exit;

class BA_Content_Manager {
    
    public function __construct() {
        // Admin hooks for content management
        add_action('wp_ajax_ba_search_media', [$this, 'ajax_search_media']);
        add_action('wp_ajax_ba_validate_youtube', [$this, 'ajax_validate_youtube']);
    }
    
    /**
     * Render admin content management tab
     */
    public function render_admin_content_tab() {
        $roles = $this->get_backstage_roles();
        $content_assignments = get_option('ba_content_assignments', []);
        
        echo '<div class="ba-content-manager">';
        
        // Add instructions for this tab
        echo '<div class="ba-tab-instructions">';
        echo '<h3>📁 Content Management Instructions</h3>';
        echo '<div class="ba-instruction-box">';
        echo '<p><strong>Purpose:</strong> Assign videos, documents, and YouTube content to specific user roles. Content will appear in each role\'s dashboard.</p>';
        echo '<h4>How to Use:</h4>';
        echo '<ol>';
        echo '<li><strong>Click on role sections</strong> below to expand/collapse them (▼ means expanded, ▶ means collapsed)</li>';
        echo '<li><strong>Add Videos:</strong> Click "+ Add Videos" to select video files from your media library</li>';
        echo '<li><strong>Add Documents:</strong> Click "+ Add Documents" to select PDFs, docs, or other files</li>';
        echo '<li><strong>Add YouTube:</strong> Click "+ Add YouTube Video" to add external YouTube content</li>';
        echo '<li><strong>Save Changes:</strong> Click "Save Content Assignments" to update user dashboards</li>';
        echo '</ol>';
        echo '<div class="ba-tips">';
        echo '<p><strong>💡 Pro Tips:</strong></p>';
        echo '<ul>';
        echo '<li>Upload content to WordPress Media Library first, then assign it to roles here</li>';
        echo '<li>One piece of content can be assigned to multiple roles</li>';
        echo '<li>Users will see content from ALL their assigned roles in their dashboard</li>';
        echo '<li>Remove content by clicking the ❌ button on any item</li>';
        echo '<li>Preview content by clicking the 👁️ button before assigning it</li>';
        echo '</ul>';
        echo '</div>';
        echo '<div class="ba-workflow">';
        echo '<p><strong>📋 Recommended Workflow:</strong></p>';
        echo '<ol>';
        echo '<li>Create user roles in the "Create Role" tab</li>';
        echo '<li>Upload videos/documents to WordPress Media Library</li>';
        echo '<li>Assign content to roles here</li>';
        echo '<li>Map roles to products in "Role Mapping" tab</li>';
        echo '<li>Users automatically get access when they purchase!</li>';
        echo '</ol>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<h2>Content Management</h2>';
        echo '<p>Assign videos, documents, and YouTube content to specific user roles.</p>';
        
        // Content assignment form
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" id="ba-content-form">';
        echo '<input type="hidden" name="action" value="ba_save_content">';
        wp_nonce_field('ba_content');
        
        foreach ($roles as $role_key => $role_data) {
            $this->render_role_content_section($role_key, $role_data, $content_assignments);
        }
        
        submit_button('Save Content Assignments');
        echo '</form>';
        echo '</div>';
        
        // Add media browser modal
        $this->render_media_browser_modal();
        
        // Enqueue admin scripts
        $this->enqueue_admin_scripts();
        
        // Add fallback JavaScript for accordion functionality
        $this->add_fallback_accordion_script();
    }
    
    /**
     * Render content section for a specific role
     */
    private function render_role_content_section($role_key, $role_data, $assignments) {
        $role_content = $assignments[$role_key] ?? ['videos' => [], 'documents' => [], 'youtube' => []];
        
        echo '<div class="ba-role-section" data-role="' . esc_attr($role_key) . '">';
        echo '<h3 role="button" aria-expanded="true" tabindex="0">';
        echo '<span class="ba-role-toggle" data-role="' . esc_attr($role_key) . '">▼</span> ';
        echo esc_html($role_data['name']) . ' Content';
        echo '</h3>';
        echo '<div class="ba-role-content" id="ba-role-' . esc_attr($role_key) . '" aria-hidden="false">';
        
        // Content statistics
        $video_count = count($role_content['videos']);
        $doc_count = count($role_content['documents']);
        $youtube_count = count($role_content['youtube']);
        
        echo '<div class="ba-content-summary">';
        echo '<span class="ba-summary-item">📹 ' . $video_count . ' videos</span>';
        echo '<span class="ba-summary-item">📄 ' . $doc_count . ' documents</span>';
        echo '<span class="ba-summary-item">🎥 ' . $youtube_count . ' YouTube videos</span>';
        echo '</div>';
        
        // Videos section
        echo '<div class="ba-content-type-section">';
        echo '<h4>WordPress Media Videos <button type="button" class="button button-small ba-add-media" data-role="' . esc_attr($role_key) . '" data-type="videos">+ Add Videos</button></h4>';
        echo '<div class="ba-content-grid ba-video-list" data-type="videos">';
        
        if (!empty($role_content['videos'])) {
            foreach ($role_content['videos'] as $video_id) {
                $this->render_improved_media_item($video_id, 'videos', $role_key);
            }
        } else {
            echo '<div class="ba-empty-state">No videos assigned. Click "Add Videos" to select videos from your media library.</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Documents section
        echo '<div class="ba-content-type-section">';
        echo '<h4>Documents & Downloads <button type="button" class="button button-small ba-add-media" data-role="' . esc_attr($role_key) . '" data-type="documents">+ Add Documents</button></h4>';
        echo '<div class="ba-content-grid ba-document-list" data-type="documents">';
        
        if (!empty($role_content['documents'])) {
            foreach ($role_content['documents'] as $doc_id) {
                $this->render_improved_media_item($doc_id, 'documents', $role_key);
            }
        } else {
            echo '<div class="ba-empty-state">No documents assigned. Click "Add Documents" to select files from your media library.</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // YouTube section
        echo '<div class="ba-content-type-section">';
        echo '<h4>YouTube Videos <button type="button" class="button button-small ba-add-youtube" data-role="' . esc_attr($role_key) . '">+ Add YouTube Video</button></h4>';
        echo '<div class="ba-content-grid ba-youtube-list" data-type="youtube">';
        
        if (!empty($role_content['youtube'])) {
            foreach ($role_content['youtube'] as $index => $youtube_data) {
                $this->render_improved_youtube_item($youtube_data, $role_key, $index);
            }
        } else {
            echo '<div class="ba-empty-state">No YouTube videos assigned. Click "Add YouTube Video" to add external videos.</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // ba-role-content
        echo '</div>'; // ba-role-section
        echo '<hr>';
    }
    
    /**
     * Render improved media item with better preview
     */
    private function render_improved_media_item($media_id, $type, $role_key) {
        $attachment = get_post($media_id);
        if (!$attachment) return;
        
        $url = wp_get_attachment_url($media_id);
        $title = $attachment->post_title;
        $file_type = wp_check_filetype($url);
        $file_size = size_format(filesize(get_attached_file($media_id)));
        $upload_date = get_the_date('M j, Y', $media_id);
        
        echo '<div class="ba-media-card" data-id="' . esc_attr($media_id) . '">';
        
        echo '<div class="ba-media-preview">';
        if (strpos($file_type['type'], 'video') !== false) {
            echo '<div class="ba-video-preview">';
            echo '<video width="100%" height="120" preload="metadata" controls>';
            echo '<source src="' . esc_url($url) . '" type="' . esc_attr($file_type['type']) . '">';
            echo '</video>';
            echo '<div class="ba-media-type-badge">VIDEO</div>';
            echo '</div>';
        } elseif (strpos($file_type['type'], 'image') !== false) {
            $thumb = wp_get_attachment_image($media_id, [200, 120]);
            echo '<div class="ba-image-preview">';
            echo $thumb ?: '<div class="ba-file-icon">🖼️</div>';
            echo '<div class="ba-media-type-badge">IMAGE</div>';
            echo '</div>';
        } else {
            echo '<div class="ba-file-preview">';
            echo '<div class="ba-file-icon-large">';
            switch ($file_type['ext']) {
                case 'pdf': echo '📄'; break;
                case 'doc':
                case 'docx': echo '📝'; break;
                case 'xls':
                case 'xlsx': echo '📊'; break;
                case 'ppt':
                case 'pptx': echo '📽️'; break;
                case 'zip':
                case 'rar': echo '🗜️'; break;
                default: echo '📋'; break;
            }
            echo '</div>';
            echo '<div class="ba-media-type-badge">' . strtoupper($file_type['ext']) . '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '<div class="ba-media-details">';
        echo '<h5 class="ba-media-title" title="' . esc_attr($title) . '">' . esc_html(wp_trim_words($title, 4)) . '</h5>';
        echo '<div class="ba-media-meta">';
        echo '<span class="ba-file-size">' . esc_html($file_size) . '</span>';
        echo '<span class="ba-upload-date">' . esc_html($upload_date) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-media-actions">';
        echo '<a href="' . esc_url($url) . '" target="_blank" class="ba-preview-link" title="Preview">👁️</a>';
        echo '<button type="button" class="ba-remove-item" title="Remove from role">❌</button>';
        echo '</div>';
        
        echo '<input type="hidden" name="content[' . esc_attr($role_key) . '][' . esc_attr($type) . '][]" value="' . esc_attr($media_id) . '">';
        echo '</div>';
    }

    /**
     * Render improved YouTube item with better preview
     */
    private function render_improved_youtube_item($youtube_data, $role_key, $index) {
        $video_id = $this->extract_youtube_id($youtube_data['url']);
        $thumbnail = "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg";
        
        echo '<div class="ba-media-card ba-youtube-card" data-index="' . esc_attr($index) . '">';
        
        echo '<div class="ba-media-preview">';
        echo '<div class="ba-youtube-preview">';
        echo '<img src="' . esc_url($thumbnail) . '" width="100%" height="120" alt="YouTube Thumbnail">';
        echo '<div class="ba-youtube-play-overlay">▶️</div>';
        echo '<div class="ba-media-type-badge">YOUTUBE</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-media-details">';
        echo '<h5 class="ba-media-title" title="' . esc_attr($youtube_data['title']) . '">' . esc_html(wp_trim_words($youtube_data['title'], 4)) . '</h5>';
        echo '<div class="ba-media-meta">';
        echo '<span class="ba-youtube-url" title="' . esc_attr($youtube_data['url']) . '">' . esc_html($this->get_youtube_display_url($youtube_data['url'])) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ba-media-actions">';
        echo '<a href="' . esc_url($youtube_data['url']) . '" target="_blank" class="ba-preview-link" title="Watch on YouTube">👁️</a>';
        echo '<button type="button" class="ba-edit-youtube" title="Edit YouTube video" data-index="' . esc_attr($index) . '">✏️</button>';
        echo '<button type="button" class="ba-remove-item" title="Remove from role">❌</button>';
        echo '</div>';
        
        echo '<input type="hidden" name="content[' . esc_attr($role_key) . '][youtube][' . esc_attr($index) . '][url]" value="' . esc_url($youtube_data['url']) . '">';
        echo '<input type="hidden" name="content[' . esc_attr($role_key) . '][youtube][' . esc_attr($index) . '][title]" value="' . esc_attr($youtube_data['title']) . '">';
        echo '</div>';
    }

    /**
     * Get display-friendly YouTube URL
     */
    private function get_youtube_display_url($url) {
        $video_id = $this->extract_youtube_id($url);
        return $video_id ? "youtube.com/watch?v={$video_id}" : $url;
    }

    /**
     * Render media item (legacy)
     */
    private function render_media_item($media_id, $type, $role_key) {
        $attachment = get_post($media_id);
        if (!$attachment) return;
        
        $url = wp_get_attachment_url($media_id);
        $title = $attachment->post_title;
        $file_type = wp_check_filetype($url);
        
        echo '<div class="ba-media-item" data-id="' . esc_attr($media_id) . '">';
        echo '<div class="ba-media-preview">';
        
        if (strpos($file_type['type'], 'video') !== false) {
            echo '<video width="150" height="100" controls><source src="' . esc_url($url) . '" type="' . esc_attr($file_type['type']) . '"></video>';
        } else {
            $thumb = wp_get_attachment_image($media_id, [150, 100]);
            echo $thumb ? $thumb : '<div class="ba-file-icon">📄</div>';
        }
        
        echo '</div>';
        echo '<div class="ba-media-info">';
        echo '<strong>' . esc_html($title) . '</strong><br>';
        echo '<small>' . esc_html($file_type['ext']) . '</small>';
        echo '</div>';
        echo '<input type="hidden" name="content[' . esc_attr($role_key) . '][' . esc_attr($type) . '][]" value="' . esc_attr($media_id) . '">';
        echo '<button type="button" class="button-link ba-remove-item" style="color: red;">Remove</button>';
        echo '</div>';
    }
    
    /**
     * Render YouTube item
     */
    private function render_youtube_item($youtube_data, $role_key, $index) {
        $video_id = $this->extract_youtube_id($youtube_data['url']);
        $thumbnail = "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg";
        
        echo '<div class="ba-youtube-item" data-index="' . esc_attr($index) . '">';
        echo '<div class="ba-youtube-preview">';
        echo '<img src="' . esc_url($thumbnail) . '" width="150" height="100" alt="YouTube Thumbnail">';
        echo '</div>';
        echo '<div class="ba-youtube-info">';
        echo '<strong>' . esc_html($youtube_data['title']) . '</strong><br>';
        echo '<small>' . esc_url($youtube_data['url']) . '</small>';
        echo '</div>';
        echo '<input type="hidden" name="content[' . esc_attr($role_key) . '][youtube][' . esc_attr($index) . '][url]" value="' . esc_url($youtube_data['url']) . '">';
        echo '<input type="hidden" name="content[' . esc_attr($role_key) . '][youtube][' . esc_attr($index) . '][title]" value="' . esc_attr($youtube_data['title']) . '">';
        echo '<button type="button" class="button-link ba-remove-item" style="color: red;">Remove</button>';
        echo '</div>';
    }
    
    /**
     * Render media browser modal
     */
    private function render_media_browser_modal() {
        echo '<div id="ba-media-modal" class="ba-modal" style="display: none;">';
        echo '<div class="ba-modal-content">';
        echo '<div class="ba-modal-header">';
        echo '<h3>Select Media</h3>';
        echo '<span class="ba-modal-close">&times;</span>';
        echo '</div>';
        echo '<div class="ba-modal-body">';
        echo '<div id="ba-media-browser"></div>';
        echo '</div>';
        echo '<div class="ba-modal-footer">';
        echo '<button type="button" class="button" id="ba-media-select">Add Selected</button>';
        echo '<button type="button" class="button" id="ba-media-cancel">Cancel</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // YouTube modal
        echo '<div id="ba-youtube-modal" class="ba-modal" style="display: none;">';
        echo '<div class="ba-modal-content">';
        echo '<div class="ba-modal-header">';
        echo '<h3>Add YouTube Video</h3>';
        echo '<span class="ba-modal-close">&times;</span>';
        echo '</div>';
        echo '<div class="ba-modal-body">';
        echo '<label for="ba-youtube-url">YouTube URL:</label>';
        echo '<input type="url" id="ba-youtube-url" placeholder="https://www.youtube.com/watch?v=..." style="width: 100%; margin-bottom: 10px;">';
        echo '<label for="ba-youtube-title">Custom Title (optional):</label>';
        echo '<input type="text" id="ba-youtube-title" placeholder="Leave empty to fetch from YouTube" style="width: 100%;">';
        echo '</div>';
        echo '<div class="ba-modal-footer">';
        echo '<button type="button" class="button button-primary" id="ba-youtube-add">Add Video</button>';
        echo '<button type="button" class="button" id="ba-youtube-cancel">Cancel</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Get backstage roles
     */
    private function get_backstage_roles() {
        $all_roles = wp_roles()->roles;
        $backstage_roles = [];
        
        foreach ($all_roles as $key => $role) {
            if (!in_array($key, ['administrator', 'editor', 'author', 'contributor', 'subscriber'])) {
                $backstage_roles[$key] = $role;
            }
        }
        
        return $backstage_roles;
    }
    
    /**
     * Save content assignments
     */
    public function save_content_assignments() {
        // Verify nonce and permissions
        if (!current_user_can('manage_options') || !check_admin_referer('ba_content')) {
            wp_die(__('Unauthorized access.', 'backstage-access'));
        }
        
        $content = $_POST['content'] ?? [];
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Backstage Access: Saving content assignments');
            error_log('Content data: ' . print_r($content, true));
        }
        
        // Clean and validate the data
        $clean_content = [];
        foreach ($content as $role => $types) {
            $role = sanitize_key($role);
            $clean_content[$role] = [
                'videos' => array_map('intval', array_filter($types['videos'] ?? [])),
                'documents' => array_map('intval', array_filter($types['documents'] ?? [])),
                'youtube' => []
            ];
            
            // Handle YouTube videos
            if (!empty($types['youtube'])) {
                foreach ($types['youtube'] as $youtube_item) {
                    if (!empty($youtube_item['url']) && filter_var($youtube_item['url'], FILTER_VALIDATE_URL)) {
                        $clean_content[$role]['youtube'][] = [
                            'url' => esc_url_raw($youtube_item['url']),
                            'title' => sanitize_text_field($youtube_item['title'] ?: 'YouTube Video')
                        ];
                    }
                }
            }
        }
        
        $result = update_option('ba_content_assignments', $clean_content);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Backstage Access: Content assignments save result: ' . ($result ? 'SUCCESS' : 'FAILED'));
            error_log('Final clean content: ' . print_r($clean_content, true));
        }
        
        return $result;
    }
    
    /**
     * Get user content based on roles
     */
    public function get_user_content($user_id, $user_roles) {
        $assignments = get_option('ba_content_assignments', []);
        $user_content = ['videos' => [], 'documents' => [], 'youtube' => []];
        
        foreach ($user_roles as $role) {
            if (isset($assignments[$role])) {
                $user_content['videos'] = array_merge($user_content['videos'], $assignments[$role]['videos'] ?? []);
                $user_content['documents'] = array_merge($user_content['documents'], $assignments[$role]['documents'] ?? []);
                $user_content['youtube'] = array_merge($user_content['youtube'], $assignments[$role]['youtube'] ?? []);
            }
        }
        
        // Remove duplicates
        $user_content['videos'] = array_unique($user_content['videos']);
        $user_content['documents'] = array_unique($user_content['documents']);
        
        // Get actual content data
        $user_content['videos'] = $this->get_media_data($user_content['videos']);
        $user_content['documents'] = $this->get_media_data($user_content['documents']);
        
        return $user_content;
    }
    
    /**
     * Get media data
     */
    private function get_media_data($media_ids) {
        $media_data = [];
        
        foreach ($media_ids as $media_id) {
            $attachment = get_post($media_id);
            if ($attachment) {
                $media_data[] = [
                    'id' => $media_id,
                    'title' => $attachment->post_title,
                    'url' => wp_get_attachment_url($media_id),
                    'description' => $attachment->post_content,
                    'file_type' => wp_check_filetype(wp_get_attachment_url($media_id))
                ];
            }
        }
        
        return $media_data;
    }
    
    /**
     * Extract YouTube video ID from URL
     */
    private function extract_youtube_id($url) {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        preg_match($pattern, $url, $matches);
        return $matches[1] ?? '';
    }
    
    /**
     * Enqueue admin scripts
     */
    private function enqueue_admin_scripts() {
        wp_enqueue_media();
        
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        
        wp_enqueue_script('ba-content-admin', $plugin_url . 'assets/js/content-admin.js', ['jquery', 'media-upload', 'media-views', 'media-editor'], '1.9', true);
        wp_enqueue_style('ba-content-admin', $plugin_url . 'assets/css/content-admin.css', [], '1.9');
        
        wp_localize_script('ba-content-admin', 'ba_content_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ba_content_admin'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
        
        // Add debugging info
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Backstage Access: Content admin scripts enqueued - JS: ' . $plugin_url . 'assets/js/content-admin.js');
        }
    }
    
    /**
     * Add fallback accordion script - disabled in favor of main JS file
     */
    private function add_fallback_accordion_script() {
        // Fallback accordion is now handled by content-admin.js
        // This method is kept for backwards compatibility but does nothing
        
        // Only add basic CSS for accordion styling
        ?>
        <style>
        .ba-role-section h3 {
            position: relative;
            cursor: pointer;
            user-select: none;
        }
        .ba-role-section h3:hover {
            background: #e9ecef;
        }
        .ba-role-section h3:focus {
            outline: 2px solid #0073aa;
            outline-offset: 2px;
        }
        .ba-role-toggle {
            transition: transform 0.3s ease;
            display: inline-block;
            margin-right: 8px;
        }
        .ba-role-section.collapsed .ba-role-toggle {
            transform: rotate(-90deg);
        }
        .ba-role-section.collapsed .ba-role-content {
            display: none;
        }
        .ba-role-content {
            transition: all 0.3s ease;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Search media
     */
    public function ajax_search_media() {
        // Check nonce and permissions
        if (!check_ajax_referer('ba_content_admin', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 20,
            's' => $search
        ];
        
        if ($type === 'videos') {
            $args['post_mime_type'] = 'video';
        } elseif ($type === 'documents') {
            $args['post_mime_type'] = ['application', 'text'];
        }
        
        $attachments = get_posts($args);
        $results = [];
        
        foreach ($attachments as $attachment) {
            $results[] = [
                'id' => $attachment->ID,
                'title' => $attachment->post_title,
                'url' => wp_get_attachment_url($attachment->ID),
                'mime_type' => $attachment->post_mime_type
            ];
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Validate YouTube URL
     */
    public function ajax_validate_youtube() {
        // Check nonce and permissions
        if (!check_ajax_referer('ba_content_admin', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $url = esc_url_raw($_POST['url'] ?? '');
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL format');
            return;
        }
        
        $video_id = $this->extract_youtube_id($url);
        
        if (!$video_id) {
            wp_send_json_error('Invalid YouTube URL');
            return;
        }
        
        // Try to get video title from YouTube API (if available) or use generic title
        $title = $this->get_youtube_title($video_id) ?: 'YouTube Video';
        
        wp_send_json_success([
            'video_id' => $video_id,
            'title' => $title,
            'thumbnail' => "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg"
        ]);
    }
    
    /**
     * Get YouTube video title (basic implementation)
     */
    private function get_youtube_title($video_id) {
        // This is a basic implementation. For production, consider using YouTube API
        $url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video_id}&format=json";
        $response = wp_remote_get($url);
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return $data['title'] ?? null;
        }
        
        return null;
    }
}
