<?php
/**
 * Backstage Dashboard Main Template
 * This template renders the user's backstage pass dashboard
 */

if (!defined('ABSPATH')) exit;
?>

<div class="ba-dashboard">
    <!-- User Welcome Section -->
    <div class="ba-welcome-section">
        <h2><?php printf(__('Welcome to your Backstage Pass, %s!', 'backstage-access'), esc_html($user->display_name)); ?></h2>
        <div class="ba-user-status">
            <div class="ba-status-item">
                <span class="ba-status-label"><?php _e('Access Level:', 'backstage-access'); ?></span>
                <span class="ba-status-value">
                    <?php
                    $role_names = [];
                    foreach ($user_roles as $role) {
                        $role_obj = get_role($role);
                        if ($role_obj) {
                            $wp_roles = wp_roles();
                            $role_names[] = $wp_roles->roles[$role]['name'] ?? $role;
                        }
                    }
                    echo esc_html(implode(', ', $role_names));
                    ?>
                </span>
            </div>
            <div class="ba-status-item">
                <span class="ba-status-label"><?php _e('Member Since:', 'backstage-access'); ?></span>
                <span class="ba-status-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></span>
            </div>
        </div>
    </div>

    <!-- User Stats -->
    <div class="ba-stats-section">
        <div class="ba-stat-box">
            <div class="ba-stat-number"><?php echo esc_html($user_stats['videos_watched']); ?></div>
            <div class="ba-stat-label"><?php _e('Videos Watched', 'backstage-access'); ?></div>
        </div>
        <div class="ba-stat-box">
            <div class="ba-stat-number"><?php echo esc_html($user_stats['files_downloaded']); ?></div>
            <div class="ba-stat-label"><?php _e('Files Downloaded', 'backstage-access'); ?></div>
        </div>
        <div class="ba-stat-box">
            <div class="ba-stat-number"><?php echo count($recent_purchases); ?></div>
            <div class="ba-stat-label"><?php _e('Access Products', 'backstage-access'); ?></div>
        </div>
        <div class="ba-stat-box">
            <div class="ba-stat-number"><?php echo $user_stats['last_activity'] ? human_time_diff(strtotime($user_stats['last_activity'])) . ' ago' : 'Never'; ?></div>
            <div class="ba-stat-label"><?php _e('Last Activity', 'backstage-access'); ?></div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="ba-nav-tabs">
        <button class="ba-tab-button active" data-tab="videos"><?php _e('Videos', 'backstage-access'); ?></button>
        <button class="ba-tab-button" data-tab="documents"><?php _e('Documents', 'backstage-access'); ?></button>
        <button class="ba-tab-button" data-tab="youtube"><?php _e('YouTube', 'backstage-access'); ?></button>
        <button class="ba-tab-button" data-tab="purchases"><?php _e('Purchases', 'backstage-access'); ?></button>
    </div>

    <!-- Tab Content -->
    <div class="ba-tab-contents">
        
        <!-- Videos Tab -->
        <div id="ba-tab-videos" class="ba-tab-content active">
            <h3><?php _e('Your Videos', 'backstage-access'); ?></h3>
            <?php if (!empty($user_content['videos'])): ?>
                <div class="ba-content-grid">
                    <?php foreach ($user_content['videos'] as $video): ?>
                        <div class="ba-content-item" data-id="<?php echo esc_attr($video['id']); ?>" data-type="video">
                            <div class="ba-content-thumbnail">
                                <video controls preload="metadata" width="100%" height="200">
                                    <?php
                                    // Add multiple source formats for better compatibility
                                    $video_url = $video['url'];
                                    $file_ext = strtolower($video['file_type']['ext']);
                                    
                                    // Determine MIME type based on file extension for better compatibility
                                    $mime_types = [
                                        'mp4' => 'video/mp4',
                                        'mov' => 'video/quicktime',
                                        'avi' => 'video/x-msvideo',
                                        'wmv' => 'video/x-ms-wmv',
                                        'flv' => 'video/x-flv',
                                        'webm' => 'video/webm',
                                        'ogv' => 'video/ogg',
                                        'm4v' => 'video/x-m4v',
                                        '3gp' => 'video/3gpp',
                                        'mkv' => 'video/x-matroska'
                                    ];
                                    
                                    $mime_type = isset($mime_types[$file_ext]) ? $mime_types[$file_ext] : $video['file_type']['type'];
                                    ?>
                                    
                                    <source src="<?php echo esc_url($video_url); ?>" type="<?php echo esc_attr($mime_type); ?>">
                                    
                                    <?php if ($file_ext === 'mov'): ?>
                                        <!-- Additional codec specifications for MOV files -->
                                        <source src="<?php echo esc_url($video_url); ?>" type="video/quicktime; codecs=avc1.42E01E,mp4a.40.2">
                                        <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                                    <?php elseif ($file_ext === 'mp4'): ?>
                                        <!-- Additional codec specifications for MP4 files -->
                                        <source src="<?php echo esc_url($video_url); ?>" type="video/mp4; codecs=avc1.42E01E,mp4a.40.2">
                                        <source src="<?php echo esc_url($video_url); ?>" type="video/mp4; codecs=avc1.4D4028,mp4a.40.2">
                                    <?php endif; ?>
                                    
                                    <!-- Fallback message -->
                                    <p><?php _e('Your browser does not support the video format. ', 'backstage-access'); ?>
                                       <a href="<?php echo esc_url($video_url); ?>" download><?php _e('Download video file', 'backstage-access'); ?></a>
                                    </p>
                                </video>
                                <button class="ba-favorite-btn" data-id="<?php echo esc_attr($video['id']); ?>" title="<?php _e('Add to Favorites', 'backstage-access'); ?>">
                                    <span class="dashicons dashicons-heart"></span>
                                </button>
                            </div>
                            <div class="ba-content-info">
                                <h4><?php echo esc_html($video['title']); ?></h4>
                                <?php if ($video['description']): ?>
                                    <p class="ba-content-description"><?php echo wp_kses_post(wp_trim_words($video['description'], 20)); ?></p>
                                <?php endif; ?>
                                <div class="ba-content-meta">
                                    <span class="ba-file-type"><?php echo esc_html(strtoupper($video['file_type']['ext'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ba-empty-state">
                    <p><?php _e('No videos have been assigned to your access level yet.', 'backstage-access'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Documents Tab -->
        <div id="ba-tab-documents" class="ba-tab-content">
            <h3><?php _e('Your Documents', 'backstage-access'); ?></h3>
            <?php if (!empty($user_content['documents'])): ?>
                <div class="ba-content-list">
                    <?php foreach ($user_content['documents'] as $document): ?>
                        <div class="ba-document-item" data-id="<?php echo esc_attr($document['id']); ?>" data-type="document">
                            <div class="ba-document-icon">
                                <?php
                                $ext = $document['file_type']['ext'];
                                $icon = 'dashicons-media-default';
                                if (in_array($ext, ['pdf'])) $icon = 'dashicons-pdf';
                                elseif (in_array($ext, ['doc', 'docx'])) $icon = 'dashicons-media-document';
                                elseif (in_array($ext, ['xls', 'xlsx'])) $icon = 'dashicons-media-spreadsheet';
                                ?>
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            </div>
                            <div class="ba-document-info">
                                <h4><?php echo esc_html($document['title']); ?></h4>
                                <?php if ($document['description']): ?>
                                    <p class="ba-content-description"><?php echo wp_kses_post(wp_trim_words($document['description'], 20)); ?></p>
                                <?php endif; ?>
                                <div class="ba-content-meta">
                                    <span class="ba-file-type"><?php echo esc_html(strtoupper($document['file_type']['ext'])); ?></span>
                                </div>
                            </div>
                            <div class="ba-document-actions">
                                <a href="<?php echo esc_url($document['url']); ?>" class="button ba-download-btn" download target="_blank" data-content-id="<?php echo esc_attr($document['id']); ?>">
                                    <?php _e('Download', 'backstage-access'); ?>
                                </a>
                                <button class="ba-favorite-btn" data-id="<?php echo esc_attr($document['id']); ?>" title="<?php _e('Add to Favorites', 'backstage-access'); ?>">
                                    <span class="dashicons dashicons-heart"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ba-empty-state">
                    <p><?php _e('No documents have been assigned to your access level yet.', 'backstage-access'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- YouTube Tab -->
        <div id="ba-tab-youtube" class="ba-tab-content">
            <h3><?php _e('YouTube Videos', 'backstage-access'); ?></h3>
            <?php if (!empty($user_content['youtube'])): ?>
                <div class="ba-content-grid">
                    <?php foreach ($user_content['youtube'] as $index => $youtube): ?>
                        <?php $video_id = preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtube['url'], $matches) ? $matches[1] : ''; ?>
                        <?php if ($video_id): ?>
                            <div class="ba-content-item ba-youtube-item" data-id="<?php echo esc_attr($index); ?>" data-type="youtube">
                                <div class="ba-content-thumbnail">
                                    <div class="ba-youtube-embed">
                                        <iframe width="100%" height="200" 
                                                src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>" 
                                                frameborder="0" 
                                                allowfullscreen>
                                        </iframe>
                                    </div>
                                    <button class="ba-favorite-btn" data-id="<?php echo esc_attr($index); ?>" title="<?php _e('Add to Favorites', 'backstage-access'); ?>">
                                        <span class="dashicons dashicons-heart"></span>
                                    </button>
                                </div>
                                <div class="ba-content-info">
                                    <h4><?php echo esc_html($youtube['title']); ?></h4>
                                    <div class="ba-content-meta">
                                        <a href="<?php echo esc_url($youtube['url']); ?>" target="_blank" class="ba-youtube-link">
                                            <?php _e('Watch on YouTube', 'backstage-access'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ba-empty-state">
                    <p><?php _e('No YouTube videos have been assigned to your access level yet.', 'backstage-access'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Purchases Tab -->
        <div id="ba-tab-purchases" class="ba-tab-content">
            <h3><?php _e('Your Backstage Purchases', 'backstage-access'); ?></h3>
            <?php if (!empty($recent_purchases)): ?>
                <div class="ba-purchases-list">
                    <?php foreach ($recent_purchases as $purchase): ?>
                        <div class="ba-purchase-item">
                            <div class="ba-purchase-info">
                                <h4><?php echo esc_html($purchase['product_name']); ?></h4>
                                <p class="ba-purchase-date">
                                    <?php printf(__('Purchased on %s', 'backstage-access'), esc_html($purchase['date']->date_i18n(get_option('date_format')))); ?>
                                </p>
                                <div class="ba-purchase-roles">
                                    <strong><?php _e('Access Granted:', 'backstage-access'); ?></strong>
                                    <?php
                                    $role_names = [];
                                    foreach ($purchase['roles'] as $role) {
                                        $wp_roles = wp_roles();
                                        $role_names[] = $wp_roles->roles[$role]['name'] ?? $role;
                                    }
                                    echo esc_html(implode(', ', $role_names));
                                    ?>
                                </div>
                            </div>
                            <div class="ba-purchase-actions">
                                <?php if (function_exists('wc_get_order')): ?>
                                    <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $purchase['order_id'], wc_get_page_permalink('myaccount'))); ?>" class="button">
                                        <?php _e('View Order', 'backstage-access'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ba-empty-state">
                    <p><?php _e('No backstage purchases found.', 'backstage-access'); ?></p>
                    <?php if (function_exists('wc_get_page_permalink')): ?>
                        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button button-primary">
                            <?php _e('Browse Products', 'backstage-access'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
