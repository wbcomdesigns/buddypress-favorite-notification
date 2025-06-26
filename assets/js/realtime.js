/**
 * BuddyPress Favorite Notification - Realtime Scripts
 * Version: 1.2.3
 */

(function($, window, document) {
    'use strict';

    // Extend BPFN namespace
    window.BPFN = window.BPFN || {};

    /**
     * Realtime notification handler
     */
    BPFN.Realtime = {
        
        // Configuration
        config: {
            container: null,
            position: 'bottom-right',
            maxNotifications: 5,
            autoDismiss: 5000,
            soundEnabled: true,
            lastChecked: 0,
            checkInterval: 15000,
            debug: false
        },

        // State
        state: {
            initialized: false,
            notifications: [],
            soundEnabled: true,
            isChecking: false,
            heartbeatActive: false
        },

        /**
         * Initialize realtime notifications
         */
        init: function(options) {
            var self = this;
            
            // Prevent double initialization
            if (self.state.initialized) {
                self.log('Already initialized, skipping');
                return;
            }
            
            // Merge options
            self.config = $.extend(true, {}, self.config, window.BPFNRealtime || {}, options || {});
            
            // Set last checked time
            self.config.lastChecked = Math.floor(Date.now() / 1000);
            
            // Create container
            self.createContainer();
            
            // Setup heartbeat with retry logic
            self.setupHeartbeatWithRetry();
            
            // Bind events
            self.bindEvents();
            
            // Initialize sound
            self.initSound();
            
            // Mark as initialized
            self.state.initialized = true;
            
            self.log('Realtime module initialized with config:', self.config);
            
            // Check if heartbeat is available after a short delay
            setTimeout(function() {
                if (!self.state.heartbeatActive) {
                    self.log('Heartbeat not active after init, using fallback polling');
                    self.startFallbackPolling();
                }
            }, 5000);
        },

        /**
         * Setup WordPress Heartbeat API with retry logic
         */
        setupHeartbeatWithRetry: function() {
            var self = this;
            var retryCount = 0;
            var maxRetries = 3;
            
            function trySetupHeartbeat() {
                // Check if heartbeat is available
                if (!window.wp || !window.wp.heartbeat) {
                    self.log('WordPress Heartbeat API not available, retry ' + (retryCount + 1) + '/' + maxRetries);
                    
                    if (retryCount < maxRetries) {
                        retryCount++;
                        setTimeout(trySetupHeartbeat, 2000);
                    } else {
                        self.log('Heartbeat not available after retries, using fallback');
                        self.startFallbackPolling();
                    }
                    return;
                }
                
                self.setupHeartbeat();
            }
            
            trySetupHeartbeat();
        },

        /**
         * Setup WordPress Heartbeat API
         */
        setupHeartbeat: function() {
            var self = this;
            
            // Remove any existing handlers first
            $(document).off('heartbeat-send.bpfn heartbeat-tick.bpfn heartbeat-error.bpfn');
            
            // Hook into heartbeat-send
            $(document).on('heartbeat-send.bpfn', function(e, data) {
                if (!self.state.initialized || self.state.isChecking) {
                    return;
                }
                
                // Add our data to heartbeat
                data.bpfn_realtime_check = {
                    last_checked: self.config.lastChecked,
                    nonce: self.config.nonce || (window.BPFNRealtime && window.BPFNRealtime.nonce)
                };
                
                self.state.isChecking = true;
                self.log('Sending heartbeat check with last_checked: ' + self.config.lastChecked);
            });
            
            // Hook into heartbeat-tick
            $(document).on('heartbeat-tick.bpfn', function(e, data) {
                self.state.isChecking = false;
                self.state.heartbeatActive = true;
                
                if (data.bpfn_realtime_notifications) {
                    self.log('Received heartbeat response', data.bpfn_realtime_notifications);
                    self.handleHeartbeatResponse(data.bpfn_realtime_notifications);
                }
            });
            
            // Hook into heartbeat-error
            $(document).on('heartbeat-error.bpfn', function(e, jqXHR, textStatus, error) {
                self.state.isChecking = false;
                self.log('Heartbeat error: ' + textStatus + ' - ' + error);
                
                // If heartbeat fails, try fallback
                if (!self.fallbackTimer) {
                    self.startFallbackPolling();
                }
            });
            
            // Configure heartbeat interval (convert from milliseconds to seconds)
            var intervalSeconds = Math.max(15, Math.floor(self.config.checkInterval / 1000));
            wp.heartbeat.interval(intervalSeconds);
            
            self.log('Heartbeat configured with interval: ' + intervalSeconds + ' seconds');
        },

        /**
         * Start fallback polling
         */
        startFallbackPolling: function() {
            var self = this;
            
            if (self.fallbackTimer) {
                return;
            }
            
            self.log('Starting fallback polling with interval: ' + self.config.checkInterval);
            
            // Initial check
            self.checkNotifications();
            
            // Set up interval
            self.fallbackTimer = setInterval(function() {
                self.checkNotifications();
            }, self.config.checkInterval);
        },

        /**
         * Check for new notifications via AJAX
         */
        checkNotifications: function() {
            var self = this;
            
            if (self.state.isChecking || !self.state.initialized) {
                return;
            }
            
            self.state.isChecking = true;
            
            $.ajax({
                url: self.config.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'bpfn_check_notifications',
                    last_checked: self.config.lastChecked,
                    nonce: self.config.nonce || (window.BPFN && window.BPFN.nonce)
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.log('AJAX check response:', response.data);
                        self.handleHeartbeatResponse(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    self.log('AJAX check error: ' + error);
                },
                complete: function() {
                    self.state.isChecking = false;
                }
            });
        },

        /**
         * Create notification container
         */
        createContainer: function() {
            var self = this;
            
            if ($('#bpfn-realtime-container').length === 0) {
                self.config.container = $('<div id="bpfn-realtime-container"></div>');
                self.config.container.addClass('bpfn-position-' + self.config.position);
                $('body').append(self.config.container);
            } else {
                self.config.container = $('#bpfn-realtime-container');
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Close button
            $(document).on('click', '.bpfn-realtime-close', function() {
                var $notification = $(this).closest('.bpfn-realtime-notification');
                self.dismissNotification($notification);
            });
            
            // Action buttons
            $(document).on('click', '.bpfn-realtime-action', function(e) {
                if ($(this).hasClass('dismiss')) {
                    e.preventDefault();
                    var $notification = $(this).closest('.bpfn-realtime-notification');
                    self.dismissNotification($notification);
                }
            });
            
            // Auto-dismiss on click
            $(document).on('click', '.bpfn-realtime-notification', function(e) {
                if ($(e.target).is('a')) {
                    return;
                }
                self.dismissNotification($(this));
            });
            
            // Sound toggle
            $(document).on('bpfn:realtime:toggle-sound', function() {
                self.toggleSound();
            });
            
            // Custom events for manual notifications
            $(document).on('bpfn:notification:new', function(e, data) {
                self.showNotification(data);
            });
        },

        /**
         * Handle heartbeat response
         */
        handleHeartbeatResponse: function(data) {
            var self = this;
            
            // Update last checked time
            self.config.lastChecked = data.timestamp || Math.floor(Date.now() / 1000);
            
            // Process new notifications
            if (data.notifications && data.notifications.length > 0) {
                self.log('Processing ' + data.notifications.length + ' new notifications');
                
                $.each(data.notifications, function(i, notification) {
                    // Delay each notification slightly for stagger effect
                    setTimeout(function() {
                        self.showNotification(notification);
                    }, i * 200);
                });
            }
            
            // Update global count
            if (typeof data.count !== 'undefined') {
                self.updateGlobalCount(data.count);
            }
        },

        /**
         * Show notification
         */
        showNotification: function(data) {
            var self = this;
            
            self.log('Showing notification', data);
            
            // Check max notifications
            if (self.state.notifications.length >= self.config.maxNotifications) {
                self.removeOldestNotification();
            }
            
            // Create notification element
            var $notification = self.createNotificationElement(data);
            
            // Add to container
            self.config.container.prepend($notification);
            
            // Add to state
            self.state.notifications.push({
                id: data.notification_id || Date.now(),
                element: $notification,
                data: data
            });
            
            // Show with animation
            setTimeout(function() {
                $notification.addClass('show');
                
                // Play sound
                if (self.state.soundEnabled && self.config.soundEnabled) {
                    self.playSound();
                }
                
                // Auto-dismiss
                if (self.config.autoDismiss > 0) {
                    setTimeout(function() {
                        self.dismissNotification($notification);
                    }, self.config.autoDismiss);
                }
            }, 10);
            
            // Trigger event
            if (window.BPFN && window.BPFN.Core) {
                window.BPFN.Core.trigger('realtime:notification:shown', data);
            }
        },

        /**
         * Create notification element
         */
        createNotificationElement: function(data) {
            var type = data.notification_type || 'favorite';
            var timeAgo = data.time_ago || 'just now';
            var strings = window.BPFNRealtime && window.BPFNRealtime.strings || {};
            
            var html = 
                '<div class="bpfn-realtime-notification type-' + type + '" data-id="' + (data.notification_id || '') + '">' +
                    '<div class="bpfn-realtime-header">' +
                        '<span class="bpfn-realtime-title">' +
                            '<i class="dashicons dashicons-heart"></i>' +
                            (strings.new_notification || 'New notification') +
                        '</span>' +
                        '<button class="bpfn-realtime-close" aria-label="' + (strings.dismiss || 'Dismiss') + '">&times;</button>' +
                    '</div>' +
                    '<div class="bpfn-realtime-body">' +
                        '<div class="bpfn-realtime-content">';
            
            // Add avatar if available
            if (data.user_avatar) {
                html += '<div class="bpfn-realtime-avatar">' + data.user_avatar + '</div>';
            }
            
            html += '<div class="bpfn-realtime-message">' +
                        (data.text || 'Someone favorited your activity') +
                        '<div class="bpfn-realtime-time">' + timeAgo + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            // Add actions
            html += '<div class="bpfn-realtime-actions">' +
                '<a href="' + (data.link || '#') + '" class="bpfn-realtime-action primary">' +
                    (strings.view_activity || 'View Activity') +
                '</a>' +
                '<a href="#" class="bpfn-realtime-action secondary dismiss">' +
                    (strings.dismiss || 'Dismiss') +
                '</a>' +
            '</div>';
            
            html += '</div>';
            
            return $(html);
        },

        /**
         * Show test notification (ONLY for admin/debugging)
         */
        showTestNotification: function() {
            var self = this;
            
            var testData = {
                notification_id: 'test-' + Date.now(),
                notification_type: 'favorite',
                text: '<strong>Test User</strong> favorited your activity',
                link: '#',
                time_ago: 'just now',
                user_avatar: '<img src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=60" width="60" height="60" />'
            };
            
            self.showNotification(testData);
        },

        /**
         * Dismiss notification
         */
        dismissNotification: function($notification) {
            var self = this;
            var notificationId = $notification.data('id');
            
            // Remove show class
            $notification.removeClass('show');
            
            // Remove after animation
            setTimeout(function() {
                $notification.remove();
                
                // Remove from state
                self.state.notifications = self.state.notifications.filter(function(n) {
                    return n.element.get(0) !== $notification.get(0);
                });
                
                // Mark as read if has ID
                if (notificationId && !notificationId.toString().startsWith('test-')) {
                    self.markAsRead(notificationId);
                }
            }, 300);
            
            // Trigger event
            if (window.BPFN && window.BPFN.Core) {
                window.BPFN.Core.trigger('realtime:notification:dismissed', { id: notificationId });
            }
        },

        /**
         * Remove oldest notification
         */
        removeOldestNotification: function() {
            var self = this;
            
            if (self.state.notifications.length > 0) {
                var oldest = self.state.notifications.shift();
                self.dismissNotification(oldest.element);
            }
        },

        /**
         * Mark notification as read
         */
        markAsRead: function(notificationId) {
            if (!notificationId || !window.BPFN || !window.BPFN.Ajax) return;
            
            window.BPFN.Ajax.post('dismiss_notification', {
                notification_id: notificationId
            });
        },

        /**
         * Update global notification count
         */
        updateGlobalCount: function(count) {
            // Update admin bar count
            var $adminBarCount = $('#wp-admin-bar-bp-notifications .count');
            if ($adminBarCount.length) {
                $adminBarCount.text(count);
                if (count > 0) {
                    $adminBarCount.show();
                } else {
                    $adminBarCount.hide();
                }
            }
            
            // Update any other count displays
            $('.bpfn-count').text(count);
            
            // Trigger event
            if (window.BPFN && window.BPFN.Core) {
                window.BPFN.Core.trigger('realtime:count:updated', { count: count });
            }
        },

        /**
         * Initialize sound
         */
        initSound: function() {
            var self = this;
            
            // Check if user has disabled sound
            var soundPref = localStorage.getItem('bpfn_sound_enabled');
            if (soundPref !== null) {
                self.state.soundEnabled = soundPref === 'true';
            }
            
            // Create audio element
            self.audio = new Audio();
            self.audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUant9HlcFQYuhMnx2ZI';
            self.audio.volume = 0.5;
        },

        /**
         * Play notification sound
         */
        playSound: function() {
            var self = this;
            
            if (self.audio && self.state.soundEnabled) {
                try {
                    self.audio.play().catch(function(e) {
                        self.log('Could not play sound: ' + e.message);
                    });
                } catch (e) {
                    self.log('Sound playback error: ' + e.message);
                }
            }
        },

        /**
         * Toggle sound
         */
        toggleSound: function() {
            var self = this;
            
            self.state.soundEnabled = !self.state.soundEnabled;
            localStorage.setItem('bpfn_sound_enabled', self.state.soundEnabled);
            
            // Show indicator
            var indicatorText = self.state.soundEnabled ? 'Sound enabled' : 'Sound disabled';
            self.showSoundIndicator(indicatorText);
        },

        /**
         * Show sound indicator
         */
        showSoundIndicator: function(text) {
            var $indicator = $('<div class="bpfn-sound-indicator">' + text + '</div>');
            $('body').append($indicator);
            
            setTimeout(function() {
                $indicator.addClass('show');
            }, 10);
            
            setTimeout(function() {
                $indicator.removeClass('show');
                setTimeout(function() {
                    $indicator.remove();
                }, 300);
            }, 2000);
        },

        /**
         * Log debug messages
         */
        log: function(message, data) {
            if (this.config.debug && window.console) {
                console.log('[BPFN Realtime] ' + message, data || '');
            }
        },

        /**
         * Destroy and cleanup
         */
        destroy: function() {
            var self = this;
            
            // Remove event handlers
            $(document).off('.bpfn');
            
            // Clear timers
            if (self.fallbackTimer) {
                clearInterval(self.fallbackTimer);
                self.fallbackTimer = null;
            }
            
            // Remove container
            if (self.config.container) {
                self.config.container.remove();
            }
            
            // Reset state
            self.state.initialized = false;
            self.state.notifications = [];
            self.state.heartbeatActive = false;
            
            self.log('Realtime module destroyed');
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Check if realtime config exists
        if (window.BPFNRealtime && window.BPFNRealtime.nonce) {
            // Add a small delay to ensure all scripts are loaded
            setTimeout(function() {
                BPFN.Realtime.init();
            }, 100);
        }
    });

})(jQuery, window, document);