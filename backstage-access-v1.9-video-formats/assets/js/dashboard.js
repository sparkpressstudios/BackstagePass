/**
 * Backstage Access Dashboard JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Video error handling
    initializeVideoErrorHandling();
    
    // Tab functionality
    $('.ba-tab-button').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Update tab buttons
        $('.ba-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update tab content
        $('.ba-tab-content').removeClass('active');
        $('#ba-tab-' + tabId).addClass('active');
        
        // Save active tab in localStorage
        localStorage.setItem('ba_active_tab', tabId);
    });
    
    // Restore active tab from localStorage
    var activeTab = localStorage.getItem('ba_active_tab');
    if (activeTab) {
        $('.ba-tab-button[data-tab="' + activeTab + '"]').click();
    }
    
    // Favorite functionality
    $('.ba-favorite-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $btn = $(this);
        var contentId = $btn.data('id');
        
        if (!contentId) return;
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ba_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ba_toggle_favorite',
                content_id: contentId,
                nonce: ba_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.toggleClass('favorited');
                    
                    // Update tooltip
                    var isNowFavorited = $btn.hasClass('favorited');
                    $btn.attr('title', isNowFavorited ? 'Remove from Favorites' : 'Add to Favorites');
                    
                    // Show feedback
                    showNotification(
                        isNowFavorited ? 'Added to favorites!' : 'Removed from favorites!',
                        'success'
                    );
                } else {
                    showNotification('Failed to update favorites', 'error');
                }
            },
            error: function() {
                showNotification('Network error occurred', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Download tracking
    $('.ba-download-btn').on('click', function() {
        var contentId = $(this).data('content-id');
        
        if (contentId) {
            markContentAsViewed(contentId, 'document');
        }
    });
    
    // Video play tracking
    $('.ba-content-item video').on('play', function() {
        var $item = $(this).closest('.ba-content-item');
        var contentId = $item.data('id');
        var contentType = $item.data('type');
        
        if (contentId && contentType) {
            markContentAsViewed(contentId, contentType);
        }
    });
    
    // YouTube embed click tracking (limited due to iframe restrictions)
    $('.ba-youtube-item').on('click', function() {
        var contentId = $(this).data('id');
        
        if (contentId) {
            markContentAsViewed(contentId, 'youtube');
        }
    });
    
    // Search functionality (if implemented)
    var searchTimeout;
    $('#ba-content-search').on('input', function() {
        var query = $(this).val().toLowerCase();
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            filterContent(query);
        }, 300);
    });
    
    // Content filtering
    function filterContent(query) {
        $('.ba-content-item, .ba-document-item, .ba-youtube-item, .ba-purchase-item').each(function() {
            var $item = $(this);
            var title = $item.find('h4').text().toLowerCase();
            var description = $item.find('.ba-content-description').text().toLowerCase();
            
            var matches = title.includes(query) || description.includes(query);
            $item.toggle(matches || query === '');
        });
        
        // Show/hide empty states
        $('.ba-tab-content').each(function() {
            var $tab = $(this);
            var hasVisibleItems = $tab.find('.ba-content-item:visible, .ba-document-item:visible, .ba-youtube-item:visible, .ba-purchase-item:visible').length > 0;
            
            $tab.find('.ba-empty-state').toggle(!hasVisibleItems && query !== '');
        });
    }
    
    // Mark content as viewed
    function markContentAsViewed(contentId, contentType) {
        $.ajax({
            url: ba_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ba_mark_viewed',
                content_id: contentId,
                content_type: contentType,
                nonce: ba_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update UI indicators if needed
                    updateUserStats();
                }
            }
        });
    }
    
    // Update user statistics
    function updateUserStats() {
        // This could refresh the stats section in real-time
        // For now, we'll just mark it for next page load
        sessionStorage.setItem('ba_stats_updated', 'true');
    }
    
    // Show notification
    function showNotification(message, type) {
        var $notification = $('<div class="ba-notification ba-notification-' + type + '">' + message + '</div>');
        
        // Style the notification
        $notification.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            background: type === 'success' ? '#10b981' : '#ef4444',
            color: 'white',
            padding: '12px 20px',
            borderRadius: '8px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            zIndex: 10000,
            fontSize: '14px',
            fontWeight: '500',
            maxWidth: '300px',
            opacity: 0,
            transform: 'translateX(100%)',
            transition: 'all 0.3s ease'
        });
        
        $('body').append($notification);
        
        // Animate in
        setTimeout(function() {
            $notification.css({
                opacity: 1,
                transform: 'translateX(0)'
            });
        }, 10);
        
        // Auto remove
        setTimeout(function() {
            $notification.css({
                opacity: 0,
                transform: 'translateX(100%)'
            });
            
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }
    
    // Lazy loading for videos
    function setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            var videoObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var video = entry.target;
                        if (video.dataset.src) {
                            video.src = video.dataset.src;
                            video.removeAttribute('data-src');
                            videoObserver.unobserve(video);
                        }
                    }
                });
            });
            
            $('video[data-src]').each(function() {
                videoObserver.observe(this);
            });
        } else {
            // Fallback for older browsers
            $('video[data-src]').each(function() {
                this.src = this.dataset.src;
                this.removeAttribute('data-src');
            });
        }
    }
    
    // Progress tracking for videos
    $('.ba-content-item video').on('timeupdate', function() {
        var video = this;
        var duration = video.duration;
        var currentTime = video.currentTime;
        
        if (duration > 0) {
            var progress = (currentTime / duration) * 100;
            var $progressBar = $(video).siblings('.ba-progress-bar');
            
            if ($progressBar.length === 0) {
                $progressBar = $('<div class="ba-progress-bar"><div class="ba-progress-fill"></div></div>');
                $progressBar.css({
                    position: 'absolute',
                    bottom: 0,
                    left: 0,
                    right: 0,
                    height: '4px',
                    background: 'rgba(0,0,0,0.3)',
                    overflow: 'hidden'
                });
                
                $progressBar.find('.ba-progress-fill').css({
                    height: '100%',
                    background: '#667eea',
                    width: '0%',
                    transition: 'width 0.1s ease'
                });
                
                $(video).parent().css('position', 'relative').append($progressBar);
            }
            
            $progressBar.find('.ba-progress-fill').css('width', progress + '%');
        }
    });
    
    // Keyboard navigation
    $(document).on('keydown', function(e) {
        if (e.target.tagName.toLowerCase() === 'input') return;
        
        switch(e.key) {
            case '1':
            case '2':
            case '3':
            case '4':
                var tabIndex = parseInt(e.key) - 1;
                var $tab = $('.ba-tab-button').eq(tabIndex);
                if ($tab.length) {
                    $tab.click();
                }
                break;
        }
    });
    
    // Initialize
    setupLazyLoading();
    
    // Check if stats were updated and show notification
    if (sessionStorage.getItem('ba_stats_updated')) {
        sessionStorage.removeItem('ba_stats_updated');
        // Could show a subtle indicator that stats were updated
    }
    
    // Video error handling for different formats
    function initializeVideoErrorHandling() {
        $('.ba-video-container video').each(function() {
            var $video = $(this);
            var $container = $video.closest('.ba-video-container');
            var sources = $video.find('source');
            var attemptedSources = 0;
            
            // Handle errors on each source
            sources.on('error', function() {
                attemptedSources++;
                console.log('Video source failed to load:', this.src);
                
                // If all sources failed
                if (attemptedSources >= sources.length) {
                    $video.hide();
                    $container.addClass('ba-video-error');
                    
                    // Create error message with download link
                    var videoTitle = $container.find('.ba-content-title').text() || 'Video';
                    var originalSource = sources.first().attr('src');
                    
                    var errorHtml = '<div class="ba-video-error-message">' +
                        '<p>This video format is not supported by your browser.</p>' +
                        '<a href="' + originalSource + '" download class="ba-video-download-link">' +
                        'Download ' + videoTitle + '</a>' +
                        '</div>';
                    
                    $container.append(errorHtml);
                }
            });
            
            // Handle successful load
            $video.on('loadeddata', function() {
                $container.removeClass('ba-video-error');
                console.log('Video loaded successfully:', this.currentSrc);
            });
            
            // Handle cases where video can't play the format
            $video.on('error', function() {
                console.log('Video element error:', this.error);
                if (this.error && this.error.code === MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED) {
                    $video.hide();
                    $container.addClass('ba-video-error');
                    
                    var videoTitle = $container.find('.ba-content-title').text() || 'Video';
                    var originalSource = sources.first().attr('src');
                    
                    var errorHtml = '<div class="ba-video-error-message">' +
                        '<p>This video format (.mov) is not supported by your browser.</p>' +
                        '<a href="' + originalSource + '" download class="ba-video-download-link">' +
                        'Download ' + videoTitle + '</a>' +
                        '</div>';
                    
                    $container.append(errorHtml);
                }
            });
        });
    }
    
    // Responsive handling
    function handleResize() {
        var width = $(window).width();
        
        if (width < 768) {
            // Mobile optimizations
            $('.ba-content-grid').addClass('mobile-grid');
        } else {
            $('.ba-content-grid').removeClass('mobile-grid');
        }
    }
    
    // Initialize video error handling
    initializeVideoErrorHandling();
    
    $(window).on('resize', handleResize);
    handleResize();
});
