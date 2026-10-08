/**
 * Clean BuddyPress Favorite Notification - Realtime Scripts
 * Version: 1.2.4
 */

(function($, window, document) {
    'use strict';

    // Extend BPFN namespace
    window.BPFN = window.BPFN || {};

    /**
     * Clean Realtime notification handler
     */
    BPFN.Realtime = {
        
        // Configuration
        config: {
            container: null,
            // A burst of favorites must not cover the page: 2 cards on desktop,
            // 1 on phones. The bell count still carries the total.
            maxNotifications: window.matchMedia && window.matchMedia('(max-width: 480px)').matches ? 1 : 2,
            autoDismiss: 5000,
            lastChecked: 0
        },

        // State
        state: {
            initialized: false,
            notifications: [],
            isChecking: false,
            heartbeatActive: false
        },

        /**
         * Initialize
         */
        init: function(options) {
            var self = this;
            
            if (self.state.initialized) {
                return Promise.resolve();
            }
            
            // Merge options
            self.config = $.extend(true, {}, self.config, window.BPFNRealtime || {}, options || {});
            self.config.lastChecked = Math.floor(Date.now() / 1000);
            
            self.log('Starting realtime initialization');
            
            return self.initializeHeartbeat().then(function() {
                self.setupUI();
                self.bindEvents();
                self.state.initialized = true;
                self.log('Realtime initialization completed');
            });
        },

        /**
         * Initialize WordPress Heartbeat
         */
        initializeHeartbeat: function() {
            var self = this;
            
            return new Promise(function(resolve, reject) {
                if (typeof wp === 'undefined' || !wp.heartbeat) {
                    reject(new Error('Heartbeat not available'));
                    return;
                }
                
                // Setup heartbeat handlers
                $(document).on('heartbeat-send.bpfn-heartbeat', function(e, data) {
                    if (!self.state.initialized || self.state.isChecking) {
                        return;
                    }
                    
                    data.bpfn_realtime_check = {
                        last_checked: self.config.lastChecked,
                        nonce: self.config.nonce
                    };
                    
                    self.state.isChecking = true;
                    self.log('Sending heartbeat check');
                });
                
                $(document).on('heartbeat-tick.bpfn-heartbeat', function(e, data) {
                    self.state.isChecking = false;
                    self.state.heartbeatActive = true;
                    
                    if (data.bpfn_realtime_notifications) {
                        self.log('Received heartbeat notifications');
                        self.handleNotificationResponse(data.bpfn_realtime_notifications);
                    }
                });
                
                $(document).on('heartbeat-error.bpfn-heartbeat', function(e, jqXHR, textStatus, error) {
                    self.state.isChecking = false;
                    self.log('Heartbeat error: ' + textStatus);
                    reject(new Error(textStatus));
                });
                
                self.log('Heartbeat initialized');
                resolve();
            });
        },

        /**
         * Handle notification response
         */
        handleNotificationResponse: function(data) {
            var self = this;
            
            // Update last checked time
            self.config.lastChecked = data.timestamp || Math.floor(Date.now() / 1000);
            
            // Process new notifications
            if (data.notifications && data.notifications.length > 0) {
                self.log('Processing ' + data.notifications.length + ' new notifications');
                
                data.notifications.slice(0, self.config.maxNotifications).forEach(function(notification, index) {
                    setTimeout(function() {
                        self.showNotification(notification);
                    }, index * 200);
                });
            }
            
            // Update global count
            if (typeof data.count !== 'undefined') {
                self.updateGlobalCount(data.count);
            }
        },

        /**
         * Setup UI components
         */
        setupUI: function() {
            var self = this;
            
            if (!self.config.container || !self.config.container.length) {
                self.config.container = $('<div id="bpfn-realtime-container" aria-live="polite"></div>');
                $('body').append(self.config.container);
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
                self.dismissNotification($notification, true); // Explicit close: the member saw it.
            });

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
                
                // Auto-dismiss
                if (self.config.autoDismiss > 0) {
                    setTimeout(function() {
                        self.dismissNotification($notification);
                    }, self.config.autoDismiss);
                }
            }, 10);
        },

        /**
         * Create notification element
         */
        createNotificationElement: function(data) {
            var type = data.notification_type || 'favorite';
            var strings = window.BPFNRealtime && window.BPFNRealtime.strings || {};
            var timeAgo = data.time_ago || strings.just_now || 'just now';
            
            // Server-escaped fields (BPFN_Module_Realtime::format_realtime_notification).
            var html =
                '<div class="bpfn-realtime-notification type-' + type + '" data-id="' + (data.notification_id || '') + '" role="status">' +
                    '<a class="bpfn-realtime-link" href="' + (data.link || '#') + '">' +
                        (data.user_avatar ? '<span class="bpfn-realtime-avatar">' + data.user_avatar + '</span>' : '') +
                        '<span class="bpfn-realtime-message">' +
                            (data.text || strings.default_message || 'Someone favorited your activity') +
                            '<span class="bpfn-realtime-time">' + timeAgo + '</span>' +
                        '</span>' +
                    '</a>' +
                    '<button type="button" class="bpfn-realtime-close" aria-label="' + (strings.dismiss || 'Dismiss') + '">&times;</button>' +
                '</div>';

            return $(html);
        },

        /**
         * Dismiss notification
         */
        dismissNotification: function($notification, markRead) {
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
                
                // Only an explicit close marks read. Auto-hide and overflow
                // removal must leave it unread in the member's notifications.
                if (markRead && notificationId) {
                    self.markAsRead(notificationId);
                }
            }, 300);
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
            if (!notificationId) return;
            
            $.ajax({
                url: window.BPFNRealtime.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_dismiss_notification',
                    notification_id: notificationId,
                    nonce: window.BPFNRealtime.nonce
                }
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
        },

        /**
         * Logging
         */
        log: function(message, data) {
            if (window.BPFNRealtime && window.BPFNRealtime.debug && window.console) {
                console.log('[BPFN Realtime] ' + message, data || '');
            }
        },

        /**
         * Destroy and cleanup
         */
        destroy: function() {
            var self = this;
            
            // Clean up event handlers
            $(document).off('.bpfn-heartbeat .bpfn-realtime');
            
            // Remove UI elements
            if (self.config.container) {
                self.config.container.remove();
            }
            
            // Reset state
            self.state = {
                initialized: false,
                notifications: [],
                isChecking: false,
                heartbeatActive: false
            };
            
            self.log('Realtime module destroyed');
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (window.BPFNRealtime && window.BPFNRealtime.nonce) {
            setTimeout(function() {
                BPFN.Realtime.init().catch(function(error) {
                    console.warn('[BPFN] Failed to initialize real-time notifications:', error.message);
                });
            }, 100);
        }
    });

})(jQuery, window, document);