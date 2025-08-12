/**
 * Backstage Access Content Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Ensure we don't double-initialize
    if (window.baContentAdminInitialized) {
        return;
    }
    window.baContentAdminInitialized = true;
    
    // Role section toggle functionality - initialize immediately
    initializeRoleToggle();
    
    function initializeRoleToggle() {
        console.log('Initializing role toggle functionality');
        
        // Remove any existing event handlers to prevent conflicts
        $(document).off('click.ba-accordion');
        $(document).off('keydown.ba-accordion');
        
        // Use event delegation to handle dynamically added elements
        $(document).on('click.ba-accordion', '.ba-role-toggle', function(e) {
            console.log('Toggle clicked');
            e.preventDefault();
            e.stopPropagation();
            var $roleSection = $(this).closest('.ba-role-section');
            toggleRoleSection($roleSection);
        });
        
        // Also handle clicks on the entire h3 header
        $(document).on('click.ba-accordion', '.ba-role-section h3', function(e) {
            console.log('H3 header clicked', e.target);
            // Only toggle if clicking on h3 but not on buttons within it
            if (!$(e.target).is('button') && !$(e.target).closest('button').length && !$(e.target).hasClass('ba-role-toggle')) {
                e.preventDefault();
                var $roleSection = $(this).closest('.ba-role-section');
                toggleRoleSection($roleSection);
            }
        });
        
        // Add keyboard support
        $(document).on('keydown.ba-accordion', '.ba-role-section h3', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                var $roleSection = $(this).closest('.ba-role-section');
                toggleRoleSection($roleSection);
            }
        });
        
        // Make headers focusable for accessibility
        $('.ba-role-section h3').attr('tabindex', '0').attr('role', 'button');
        
        console.log('Role toggle initialization complete. Found ' + $('.ba-role-section').length + ' sections');
    }
    
    function toggleRoleSection($roleSection) {
        console.log('Toggling section:', $roleSection.data('role'));
        
        $roleSection.toggleClass('collapsed');
        
        // Update aria attributes for accessibility
        var isCollapsed = $roleSection.hasClass('collapsed');
        var $toggle = $roleSection.find('.ba-role-toggle');
        var $content = $roleSection.find('.ba-role-content');
        var $header = $roleSection.find('h3');
        
        $header.attr('aria-expanded', !isCollapsed);
        $content.attr('aria-hidden', isCollapsed);
        
        // Update toggle symbol
        if (isCollapsed) {
            $toggle.text('▶');
        } else {
            $toggle.text('▼');
        }
        
        console.log('Section toggled:', $roleSection.data('role'), isCollapsed ? 'collapsed' : 'expanded');
    }
    
    // Initialize all media functionality
    initializeMediaBrowser();
    initializeContentActions();
    initializeYouTubeModal();
    
    function initializeMediaBrowser() {
        var currentRole, currentType;
        var wp_media_frame;
        
        // Handle media selection buttons
        $(document).on('click', '.ba-add-media', function(e) {
            e.preventDefault();
            
            // Check if wp.media is available
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.error('WordPress media library not available!');
                alert('Media library not available. Please refresh the page and try again.');
                return;
            }
            
            currentRole = $(this).data('role');
            currentType = $(this).data('type');
            
            console.log('Adding media for role:', currentRole, 'type:', currentType);
            console.log('Button clicked:', this);
            console.log('Role section exists:', $('.ba-role-section[data-role="' + currentRole + '"]').length);
            console.log('Target container exists:', $('.ba-role-section[data-role="' + currentRole + '"] .ba-' + currentType + '-list').length);
            
            // Create media frame if it doesn't exist
            if (!wp_media_frame) {
                console.log('Creating new media frame for type:', currentType);
                
                wp_media_frame = wp.media({
                    title: currentType === 'videos' ? 'Select Video Files' : 'Select Document Files',
                    button: {
                        text: 'Add to ' + currentRole.replace('_', ' ').toUpperCase()
                    },
                    multiple: true,
                    library: {
                        type: currentType === 'videos' ? 'video' : ['application', 'text', 'image']
                    }
                });
                
                // For videos, also allow additional video formats that might not be detected as 'video' type
                if (currentType === 'videos') {
                    wp_media_frame.options.library = {
                        type: ['video', 'application'],
                        search: function(attachments) {
                            // Filter to include video files by extension if MIME type isn't detected properly
                            return attachments.filter(function(attachment) {
                                var url = attachment.get('url') || '';
                                var videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm', 'ogv', 'm4v', '3gp', 'mkv'];
                                var extension = url.split('.').pop().toLowerCase();
                                var mimeType = attachment.get('mime') || '';
                                
                                return mimeType.indexOf('video') === 0 || videoExtensions.indexOf(extension) !== -1;
                            });
                        }
                    };
                }
                
                console.log('Media frame created:', wp_media_frame);
                
                // Handle media selection - always rebind the event
                wp_media_frame.off('select').on('select', function() {
                    var selection = wp_media_frame.state().get('selection');
                    var $container = $('.ba-role-section[data-role="' + currentRole + '"] .ba-' + currentType + '-list');
                    
                    console.log('Media selected:', selection.length, 'items');
                    console.log('Container selector:', '.ba-role-section[data-role="' + currentRole + '"] .ba-' + currentType + '-list');
                    console.log('Container found:', $container.length, 'elements');
                    
                    if ($container.length === 0) {
                        console.error('Container not found! Trying alternative selector...');
                        // Try alternative selectors
                        $container = $('.ba-role-section[data-role="' + currentRole + '"] .ba-content-grid[data-type="' + currentType + '"]');
                        console.log('Alternative container found:', $container.length, 'elements');
                    }
                    
                    selection.map(function(attachment) {
                        attachment = attachment.toJSON();
                        console.log('Adding attachment:', attachment.id, attachment.title);
                        addMediaItem(attachment, currentType, currentRole, $container);
                    });
                    
                    updateContentSummary(currentRole);
                    removeEmptyState($container);
                });
            } else {
                // Update library type for current selection
                wp_media_frame.options.library.type = currentType === 'videos' ? 'video' : ['application', 'text', 'image'];
                
                // Rebind the selection event with current context
                wp_media_frame.off('select').on('select', function() {
                    var selection = wp_media_frame.state().get('selection');
                    var $container = $('.ba-role-section[data-role="' + currentRole + '"] .ba-' + currentType + '-list');
                    
                    console.log('Media selected (existing frame):', selection.length, 'items');
                    console.log('Container found:', $container.length, 'elements');
                    
                    if ($container.length === 0) {
                        $container = $('.ba-role-section[data-role="' + currentRole + '"] .ba-content-grid[data-type="' + currentType + '"]');
                    }
                    
                    selection.map(function(attachment) {
                        attachment = attachment.toJSON();
                        addMediaItem(attachment, currentType, currentRole, $container);
                    });
                    
                    updateContentSummary(currentRole);
                    removeEmptyState($container);
                });
            }
            
            wp_media_frame.open();
        });
    }
    
    function addMediaItem(attachment, type, role, $container) {
        console.log('addMediaItem called with:', {
            attachment_id: attachment.id,
            type: type,
            role: role,
            container_length: $container.length,
            container_class: $container.attr('class')
        });
        
        // Check if container exists
        if ($container.length === 0) {
            console.error('Container not found in addMediaItem!');
            return;
        }
        
        // Check if item already exists
        if ($container.find('[data-id="' + attachment.id + '"]').length > 0) {
            console.log('Media item already exists:', attachment.id);
            return;
        }
        
        var isVideo = attachment.mime.indexOf('video') !== -1;
        var fileExt = attachment.url.split('.').pop().toLowerCase();
        var videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm', 'ogv', 'm4v', '3gp', 'mkv'];
        
        // Enhanced video detection - check both MIME type and file extension
        if (!isVideo && videoExtensions.indexOf(fileExt) !== -1) {
            isVideo = true;
            console.log('Video detected by file extension:', fileExt);
        }
        
        var isImage = attachment.mime.indexOf('image') !== -1;
        var fileExt = attachment.url.split('.').pop().toUpperCase();
        var fileSize = formatFileSize(attachment.filesizeInBytes || 0);
        var uploadDate = formatDate(attachment.date);
        
        console.log('Adding media item:', attachment.id, attachment.title, attachment.mime);
        
        var mediaHtml = '<div class="ba-media-card" data-id="' + attachment.id + '">';
        
        // Preview section
        mediaHtml += '<div class="ba-media-preview">';
        
        if (isVideo) {
            mediaHtml += '<div class="ba-video-preview">';
            mediaHtml += '<video width="100%" height="120" preload="metadata" controls>';
            
            // Add multiple source types for better compatibility
            var fileExt = attachment.url.split('.').pop().toLowerCase();
            var mimeTypes = {
                'mp4': 'video/mp4',
                'mov': 'video/quicktime',
                'avi': 'video/x-msvideo',
                'wmv': 'video/x-ms-wmv',
                'flv': 'video/x-flv',
                'webm': 'video/webm',
                'ogv': 'video/ogg',
                'm4v': 'video/x-m4v',
                '3gp': 'video/3gpp',
                'mkv': 'video/x-matroska'
            };
            
            var detectedMime = mimeTypes[fileExt] || attachment.mime;
            mediaHtml += '<source src="' + attachment.url + '" type="' + detectedMime + '">';
            
            // Add fallback source for MOV files
            if (fileExt === 'mov') {
                mediaHtml += '<source src="' + attachment.url + '" type="video/mp4">';
            }
            
            mediaHtml += '<p>Your browser does not support this video format. <a href="' + attachment.url + '" download>Download video</a></p>';
            mediaHtml += '</video>';
            mediaHtml += '<div class="ba-media-type-badge">VIDEO (' + fileExt.toUpperCase() + ')</div>';
            mediaHtml += '</div>';
        } else if (isImage) {
            mediaHtml += '<div class="ba-image-preview">';
            mediaHtml += '<img src="' + (attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url) + '" width="100%" height="120" style="object-fit: cover;">';
            mediaHtml += '<div class="ba-media-type-badge">IMAGE</div>';
            mediaHtml += '</div>';
        } else {
            var fileIcon = getFileIcon(fileExt);
            mediaHtml += '<div class="ba-file-preview">';
            mediaHtml += '<div class="ba-file-icon-large">' + fileIcon + '</div>';
            mediaHtml += '<div class="ba-media-type-badge">' + fileExt + '</div>';
            mediaHtml += '</div>';
        }
        
        mediaHtml += '</div>';
        
        // Details section
        mediaHtml += '<div class="ba-media-details">';
        mediaHtml += '<h5 class="ba-media-title" title="' + attachment.title + '">' + truncateText(attachment.title, 4) + '</h5>';
        mediaHtml += '<div class="ba-media-meta">';
        mediaHtml += '<span class="ba-file-size">' + fileSize + '</span>';
        mediaHtml += '<span class="ba-upload-date">' + uploadDate + '</span>';
        mediaHtml += '</div>';
        mediaHtml += '</div>';
        
        // Actions section
        mediaHtml += '<div class="ba-media-actions">';
        mediaHtml += '<a href="' + attachment.url + '" target="_blank" class="ba-preview-link" title="Preview">👁️</a>';
        mediaHtml += '<button type="button" class="ba-remove-item" title="Remove from role">❌</button>';
        mediaHtml += '</div>';
        
        // Hidden input
        mediaHtml += '<input type="hidden" name="content[' + role + '][' + type + '][]" value="' + attachment.id + '">';
        mediaHtml += '</div>';
        
        $container.append(mediaHtml);
        
        // Add visual feedback
        var $newItem = $container.find('[data-id="' + attachment.id + '"]').last();
        $newItem.css('opacity', '0').animate({opacity: 1}, 500);
        
        console.log('Media item added successfully:', attachment.id);
    }
    
    function initializeYouTubeModal() {
        var currentRole;
        var $youtubeModal = $('#ba-youtube-modal');
        
        // Show YouTube modal
        $(document).on('click', '.ba-add-youtube', function(e) {
            e.preventDefault();
            currentRole = $(this).data('role');
            $youtubeModal.show();
        });
        
        // Hide YouTube modal
        $(document).on('click', '.ba-modal-close, #ba-youtube-cancel', function() {
            $youtubeModal.hide();
            $('#ba-youtube-url, #ba-youtube-title').val('');
        });
        
        // Add YouTube video
        $(document).on('click', '#ba-youtube-add', function() {
            var url = $('#ba-youtube-url').val().trim();
            var customTitle = $('#ba-youtube-title').val().trim();
            
            if (!url) {
                alert('Please enter a YouTube URL');
                return;
            }
            
            if (!isValidYouTubeUrl(url)) {
                alert('Please enter a valid YouTube URL');
                return;
            }
            
            var videoId = extractYouTubeId(url);
            
            // If custom title is provided, use it; otherwise fetch from YouTube
            if (customTitle) {
                addYouTubeItem({
                    url: url,
                    title: customTitle
                }, currentRole);
                $youtubeModal.hide();
                $('#ba-youtube-url, #ba-youtube-title').val('');
            } else {
                // Try to fetch title from YouTube
                fetchYouTubeTitle(videoId, function(title) {
                    addYouTubeItem({
                        url: url,
                        title: title || 'YouTube Video'
                    }, currentRole);
                    $youtubeModal.hide();
                    $('#ba-youtube-url, #ba-youtube-title').val('');
                });
            }
        });
        
        // Auto-fetch title when URL is entered
        $(document).on('blur', '#ba-youtube-url', function() {
            var url = $(this).val().trim();
            var $titleField = $('#ba-youtube-title');
            
            if (url && isValidYouTubeUrl(url) && !$titleField.val()) {
                var videoId = extractYouTubeId(url);
                $titleField.prop('placeholder', 'Fetching title...');
                
                fetchYouTubeTitle(videoId, function(title) {
                    if (title) {
                        $titleField.attr('placeholder', 'Custom title (optional): ' + title);
                    } else {
                        $titleField.attr('placeholder', 'Custom title (optional)');
                    }
                });
            }
        });
    }
    
    function addYouTubeItem(youtubeData, role) {
        var $container = $('.ba-role-section[data-role="' + role + '"] .ba-youtube-list');
        var index = $container.find('.ba-media-card').length;
        var videoId = extractYouTubeId(youtubeData.url);
        var thumbnail = 'https://img.youtube.com/vi/' + videoId + '/mqdefault.jpg';
        var displayUrl = getYouTubeDisplayUrl(youtubeData.url);
        
        var youtubeHtml = '<div class="ba-media-card ba-youtube-card" data-index="' + index + '">';
        
        // Preview section
        youtubeHtml += '<div class="ba-media-preview">';
        youtubeHtml += '<div class="ba-youtube-preview">';
        youtubeHtml += '<img src="' + thumbnail + '" width="100%" height="120" alt="YouTube Thumbnail">';
        youtubeHtml += '<div class="ba-youtube-play-overlay">▶️</div>';
        youtubeHtml += '<div class="ba-media-type-badge">YOUTUBE</div>';
        youtubeHtml += '</div>';
        youtubeHtml += '</div>';
        
        // Details section
        youtubeHtml += '<div class="ba-media-details">';
        youtubeHtml += '<h5 class="ba-media-title" title="' + youtubeData.title + '">' + truncateText(youtubeData.title, 4) + '</h5>';
        youtubeHtml += '<div class="ba-media-meta">';
        youtubeHtml += '<span class="ba-youtube-url" title="' + youtubeData.url + '">' + displayUrl + '</span>';
        youtubeHtml += '</div>';
        youtubeHtml += '</div>';
        
        // Actions section
        youtubeHtml += '<div class="ba-media-actions">';
        youtubeHtml += '<a href="' + youtubeData.url + '" target="_blank" class="ba-preview-link" title="Watch on YouTube">👁️</a>';
        youtubeHtml += '<button type="button" class="ba-edit-youtube" title="Edit YouTube video" data-index="' + index + '">✏️</button>';
        youtubeHtml += '<button type="button" class="ba-remove-item" title="Remove from role">❌</button>';
        youtubeHtml += '</div>';
        
        // Hidden inputs
        youtubeHtml += '<input type="hidden" name="content[' + role + '][youtube][' + index + '][url]" value="' + youtubeData.url + '">';
        youtubeHtml += '<input type="hidden" name="content[' + role + '][youtube][' + index + '][title]" value="' + youtubeData.title + '">';
        youtubeHtml += '</div>';
        
        $container.append(youtubeHtml);
        updateContentSummary(role);
        removeEmptyState($container);
    }
    
    function initializeContentActions() {
        // Remove item functionality
        $(document).on('click', '.ba-remove-item', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to remove this item?')) {
                var $item = $(this).closest('.ba-media-card, .ba-media-item, .ba-youtube-item');
                var $container = $item.closest('.ba-content-grid, .ba-video-list, .ba-document-list, .ba-youtube-list');
                var role = $item.closest('.ba-role-section').data('role');
                
                $item.fadeOut(300, function() {
                    $(this).remove();
                    updateContentSummary(role);
                    checkEmptyState($container);
                });
            }
        });
        
        // Close modal when clicking outside
        $(document).on('click', '.ba-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
    }
    
    // Helper functions
    function updateContentSummary(role) {
        var $roleSection = $('.ba-role-section[data-role="' + role + '"]');
        var $summary = $roleSection.find('.ba-content-summary');
        
        var videoCount = $roleSection.find('.ba-video-list .ba-media-card, .ba-video-list .ba-media-item').length;
        var docCount = $roleSection.find('.ba-document-list .ba-media-card, .ba-document-list .ba-media-item').length;
        var youtubeCount = $roleSection.find('.ba-youtube-list .ba-media-card, .ba-youtube-list .ba-youtube-item').length;
        
        $summary.find('.ba-summary-item').eq(0).text('📹 ' + videoCount + ' videos');
        $summary.find('.ba-summary-item').eq(1).text('📄 ' + docCount + ' documents');
        $summary.find('.ba-summary-item').eq(2).text('🎥 ' + youtubeCount + ' YouTube videos');
    }
    
    function removeEmptyState($container) {
        $container.find('.ba-empty-state').remove();
    }
    
    function checkEmptyState($container) {
        if ($container.find('.ba-media-card, .ba-media-item, .ba-youtube-item').length === 0) {
            var emptyMessage = 'No content assigned. Click the button above to add content.';
            $container.append('<div class="ba-empty-state">' + emptyMessage + '</div>');
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    function truncateText(text, wordLimit) {
        var words = text.split(' ');
        if (words.length > wordLimit) {
            return words.slice(0, wordLimit).join(' ') + '...';
        }
        return text;
    }
    
    function getFileIcon(extension) {
        var icons = {
            'PDF': '📄',
            'DOC': '📝',
            'DOCX': '📝',
            'XLS': '📊',
            'XLSX': '📊',
            'PPT': '📽️',
            'PPTX': '📽️',
            'ZIP': '🗜️',
            'RAR': '🗜️',
            'TXT': '📋',
            'RTF': '📋'
        };
        return icons[extension] || '📋';
    }
    
    function isValidYouTubeUrl(url) {
        var pattern = /^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
        return pattern.test(url);
    }
    
    function extractYouTubeId(url) {
        var pattern = /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
        var match = url.match(pattern);
        return match ? match[1] : '';
    }
    
    function getYouTubeDisplayUrl(url) {
        var videoId = extractYouTubeId(url);
        return videoId ? 'youtube.com/watch?v=' + videoId : url;
    }
    
    function fetchYouTubeTitle(videoId, callback) {
        // Try to fetch from YouTube oEmbed API
        var oembedUrl = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' + videoId + '&format=json';
        
        $.ajax({
            url: oembedUrl,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                callback(data.title);
            },
            error: function() {
                // Fallback: Try to fetch from our server-side proxy
                if (typeof ba_content_admin !== 'undefined') {
                    $.ajax({
                        url: ba_content_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ba_validate_youtube',
                            url: 'https://www.youtube.com/watch?v=' + videoId,
                            nonce: ba_content_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                callback(response.data.title);
                            } else {
                                callback(null);
                            }
                        },
                        error: function() {
                            callback(null);
                        }
                    });
                } else {
                    callback(null);
                }
            }
        });
    }
    
    // Initialize content summaries on page load
    $('.ba-role-section').each(function() {
        var role = $(this).data('role');
        if (role) {
            updateContentSummary(role);
        }
    });
    
    // Auto-collapse role sections if there are many
    if ($('.ba-role-section').length > 3) {
        $('.ba-role-section').slice(1).addClass('collapsed');
    }
    
    // Add visual feedback for debugging
    if (typeof console !== 'undefined' && console.log) {
        console.log('Backstage Access Content Admin: JavaScript initialized');
        console.log('Found ' + $('.ba-role-section').length + ' role sections');
        console.log('Found ' + $('.ba-role-toggle').length + ' toggle elements');
    }
});
