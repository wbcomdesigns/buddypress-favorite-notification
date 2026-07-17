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
            position: 'bottom-right',
            maxNotifications: 5,
            autoDismiss: 5000,
            lastChecked: 0,
            checkInterval: 15000
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
            
            return self.initializeHeartbeat()
                .then(function() {
                    self.setupUI();
                    self.bindEvents();
                    self.state.initialized = true;
                    self.log('Realtime initialization completed');
                    return Promise.resolve();
                })
                .catch(function(error) {
                    self.log('Heartbeat failed, falling back to polling');
                    self.initializePolling();
                    self.setupUI();
                    self.bindEvents();
                    self.state.initialized = true;
                    return Promise.resolve();
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
         * Initialize polling fallback
         */
        initializePolling: function() {
            var self = this;
            
            self.polling = {
                interval: self.config.checkInterval,
                timeout: null,
                isActive: false
            };
            
            self.startPolling();
        },

        /**
         * Start polling
         */
        startPolling: function() {
            var self = this;
            
            if (self.polling.isActive) {
                return;
            }
            
            self.polling.isActive = true;
            self.scheduleNextPoll();
        },

        /**
         * Schedule next poll
         */
        scheduleNextPoll: function() {
            var self = this;
            
            if (!self.polling.isActive) {
                return;
            }
            
            self.polling.timeout = setTimeout(function() {
                self.performPoll();
            }, self.polling.interval);
        },

        /**
         * Perform polling request
         */
        performPoll: function() {
            var self = this;
            
            if (self.state.isChecking) {
                self.scheduleNextPoll();
                return;
            }
            
            self.state.isChecking = true;
            
            $.ajax({
                url: self.config.ajax_url,
                type: 'POST',
                timeout: 10000,
                data: {
                    action: 'bpfn_check_notifications',
                    last_checked: self.config.lastChecked,
                    nonce: self.config.nonce
                },
                success: function(response) {
                    self.state.isChecking = false;
                    
                    if (response && response.success && response.data) {
                        self.handleNotificationResponse(response.data);
                    }
                    
                    self.scheduleNextPoll();
                },
                error: function(xhr, status, error) {
                    self.state.isChecking = false;
                    self.log('Polling error: ' + error);
                    self.scheduleNextPoll();
                }
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
                
                data.notifications.forEach(function(notification, index) {
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
                self.config.container = $('<div id="bpfn-realtime-container"></div>');
                self.config.container.addClass('bpfn-position-' + self.config.position);
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
                        (data.text || strings.default_message || 'Someone favorited your activity') +
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
            
            // Clear polling
            if (self.polling && self.polling.timeout) {
                clearTimeout(self.polling.timeout);
                self.polling.isActive = false;
            }
            
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