/**
 * BuddyPress Favorite Notification - Enhanced Realtime Scripts
 * Version: 1.2.4
 * 
 * Complete replacement for assets/js/realtime.js
 * Enhanced with multiple notification methods and robust fallback
 */

(function($, window, document) {
    'use strict';

    // Extend BPFN namespace
    window.BPFN = window.BPFN || {};

    /**
     * Enhanced Realtime notification handler
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
            debug: false,
            // Enhanced configuration
            maxRetries: 5,
            retryDelay: 2000,
            backoffMultiplier: 1.5,
            maxBackoffDelay: 300000, // 5 minutes
            connectionTimeout: 30000,
            methods: {
                heartbeat: true,
                polling: true,
                sse: false
            }
        },

        // State
        state: {
            initialized: false,
            notifications: [],
            soundEnabled: true,
            isChecking: false,
            heartbeatActive: false,
            currentMethod: 'none',
            retryCount: 0,
            connectionStatus: 'disconnected',
            lastSuccessfulCheck: 0
        },

        // Polling state
        polling: {
            timeout: null,
            isActive: false,
            backoffDelay: 2000
        },

        // Event source for SSE
        eventSource: null,

        // Audio for notifications
        audio: null,

        /**
         * Initialize with enhanced detection
         */
        init: function(options) {
            var self = this;
            
            if (self.state.initialized) {
                self.log('Already initialized, skipping');
                return Promise.resolve();
            }
            
            // Merge options
            self.config = $.extend(true, {}, self.config, window.BPFNRealtime || {}, options || {});
            self.config.lastChecked = Math.floor(Date.now() / 1000);
            
            self.log('Starting enhanced initialization');
            
            return self.detectEnvironment()
                .then(function(environment) {
                    return self.selectOptimalMethod(environment);
                })
                .then(function(method) {
                    return self.initializeMethod(method);
                })
                .then(function() {
                    self.setupUI();
                    self.bindEvents();
                    self.initSound();
                    self.state.initialized = true;
                    self.updateConnectionStatus('connected');
                    self.log('Enhanced initialization completed successfully');
                    return Promise.resolve();
                })
                .catch(function(error) {
                    self.log('Initialization failed: ' + error.message);
                    self.handleInitializationError(error);
                    return Promise.reject(error);
                });
        },

        /**
         * Detect environment capabilities
         */
        detectEnvironment: function() {
            var self = this;
            
            return new Promise(function(resolve) {
                var environment = {
                    hasHeartbeat: typeof wp !== 'undefined' && wp.heartbeat,
                    hasAjax: typeof ajaxurl !== 'undefined' || (window.BPFNRealtime && window.BPFNRealtime.ajax_url),
                    supportsSSE: typeof EventSource !== 'undefined',
                    supportsWebSocket: typeof WebSocket !== 'undefined',
                    isOnline: navigator.onLine !== false,
                    heartbeatWorking: false
                };
                
                self.log('Environment detection:', environment);
                
                // Test heartbeat if available
                if (environment.hasHeartbeat) {
                    self.testHeartbeat(5000).then(function(working) {
                        environment.heartbeatWorking = working;
                        self.log('Heartbeat test result: ' + (working ? 'working' : 'failed'));
                        resolve(environment);
                    });
                } else {
                    self.log('Heartbeat not available');
                    resolve(environment);
                }
            });
        },

        /**
         * Test if heartbeat is actually working
         */
        testHeartbeat: function(timeout) {
            var self = this;
            timeout = timeout || 5000;
            
            return new Promise(function(resolve) {
                var testTimeout = setTimeout(function() {
                    cleanup();
                    resolve(false);
                }, timeout);
                
                var testData = {
                    bpfn_heartbeat_test: {
                        timestamp: Date.now(),
                        nonce: self.config.nonce
                    }
                };
                
                function cleanup() {
                    clearTimeout(testTimeout);
                    $(document).off('heartbeat-tick.bpfn-test heartbeat-error.bpfn-test heartbeat-send.bpfn-test');
                }
                
                // Listen for response
                $(document).on('heartbeat-tick.bpfn-test', function(e, data) {
                    if (data.bpfn_heartbeat_test_response) {
                        cleanup();
                        resolve(true);
                    }
                });
                
                $(document).on('heartbeat-error.bpfn-test', function() {
                    cleanup();
                    resolve(false);
                });
                
                // Send test data
                $(document).on('heartbeat-send.bpfn-test', function(e, data) {
                    $.extend(data, testData);
                });
                
                // Connect heartbeat if not already connected
                if (wp.heartbeat) {
                    wp.heartbeat.connectNow();
                }
            });
        },

        /**
         * Select optimal notification method
         */
        selectOptimalMethod: function(environment) {
            var self = this;
            var availableMethods = [];
            
            // Prioritize methods based on reliability and performance
            if (environment.heartbeatWorking && self.config.methods.heartbeat) {
                availableMethods.push('heartbeat');
            }
            
            if (environment.supportsSSE && self.config.methods.sse) {
                availableMethods.push('sse');
            }
            
            if (environment.hasAjax && self.config.methods.polling) {
                availableMethods.push('polling');
            }
            
            var selectedMethod = availableMethods.length > 0 ? availableMethods[0] : 'none';
            
            self.log('Available methods: ' + availableMethods.join(', '));
            self.log('Selected method: ' + selectedMethod);
            
            return Promise.resolve(selectedMethod);
        },

        /**
         * Initialize selected method
         */
        initializeMethod: function(method) {
            var self = this;
            self.state.currentMethod = method;
            
            switch (method) {
                case 'heartbeat':
                    return self.initializeHeartbeat();
                    
                case 'sse':
                    return self.initializeSSE();
                    
                case 'polling':
                    return self.initializePolling();
                    
                default:
                    self.log('No valid notification method available');
                    return Promise.reject(new Error('No notification method available'));
            }
        },

        /**
         * Initialize WordPress Heartbeat
         */
        initializeHeartbeat: function() {
            var self = this;
            
            return new Promise(function(resolve, reject) {
                // Clean up any existing handlers
                $(document).off('.bpfn-heartbeat');
                
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
                    self.state.lastSuccessfulCheck = Date.now();
                    self.state.retryCount = 0; // Reset retry count on success
                    
                    if (data.bpfn_realtime_notifications) {
                        self.log('Received heartbeat notifications');
                        self.handleNotificationResponse(data.bpfn_realtime_notifications);
                    }
                });
                
                $(document).on('heartbeat-error.bpfn-heartbeat', function(e, jqXHR, textStatus, error) {
                    self.state.isChecking = false;
                    self.log('Heartbeat error: ' + textStatus + ' - ' + error);
                    self.handleConnectionError('heartbeat', error);
                });
                
                // Configure heartbeat interval
                var intervalSeconds = Math.max(15, Math.floor(self.config.checkInterval / 1000));
                if (wp.heartbeat && wp.heartbeat.interval) {
                    wp.heartbeat.interval(intervalSeconds);
                }
                
                self.log('Heartbeat initialized with ' + intervalSeconds + 's interval');
                resolve();
            });
        },

        /**
         * Initialize Server-Sent Events
         */
        initializeSSE: function() {
            var self = this;
            
            return new Promise(function(resolve, reject) {
                if (!window.EventSource) {
                    reject(new Error('SSE not supported'));
                    return;
                }
                
                var sseUrl = self.config.ajax_url + 
                    '?action=bpfn_sse_stream' +
                    '&nonce=' + encodeURIComponent(self.config.nonce) +
                    '&last_checked=' + self.config.lastChecked;
                
                self.eventSource = new EventSource(sseUrl);
                
                self.eventSource.onopen = function() {
                    self.log('SSE connection opened');
                    self.state.lastSuccessfulCheck = Date.now();
                    resolve();
                };
                
                self.eventSource.onmessage = function(event) {
                    try {
                        var data = JSON.parse(event.data);
                        self.handleNotificationResponse(data);
                    } catch (e) {
                        self.log('SSE message parse error: ' + e.message);
                    }
                };
                
                self.eventSource.addEventListener('notifications', function(event) {
                    try {
                        var data = JSON.parse(event.data);
                        self.handleNotificationResponse(data);
                    } catch (e) {
                        self.log('SSE notifications parse error: ' + e.message);
                    }
                });
                
                self.eventSource.onerror = function(error) {
                    self.log('SSE error occurred');
                    self.handleConnectionError('sse', error);
                };
                
                // Timeout fallback
                setTimeout(function() {
                    if (self.eventSource.readyState !== EventSource.OPEN) {
                        self.eventSource.close();
                        reject(new Error('SSE connection timeout'));
                    }
                }, self.config.connectionTimeout);
            });
        },

        /**
         * Initialize enhanced polling
         */
        initializePolling: function() {
            var self = this;
            
            return new Promise(function(resolve) {
                self.polling = {
                    interval: self.config.checkInterval,
                    timeout: null,
                    isActive: false,
                    backoffDelay: self.config.retryDelay
                };
                
                self.startSmartPolling();
                resolve();
            });
        },

        /**
         * Smart polling with exponential backoff
         */
        startSmartPolling: function() {
            var self = this;
            
            if (self.polling.isActive) {
                return;
            }
            
            self.polling.isActive = true;
            self.scheduleNextPoll();
        },

        /**
         * Schedule next poll with backoff
         */
        scheduleNextPoll: function() {
            var self = this;
            
            if (!self.polling.isActive) {
                return;
            }
            
            // Calculate delay with exponential backoff
            var delay = self.state.retryCount > 0 ? 
                Math.min(
                    self.polling.backoffDelay * Math.pow(self.config.backoffMultiplier, self.state.retryCount),
                    self.config.maxBackoffDelay
                ) : self.polling.interval;
            
            self.log('Scheduling next poll in ' + delay + 'ms (retry: ' + self.state.retryCount + ')');
            
            self.polling.timeout = setTimeout(function() {
                self.performPoll();
            }, delay);
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
            self.updateConnectionStatus('checking');
            
            $.ajax({
                url: self.config.ajax_url,
                type: 'POST',
                timeout: self.config.connectionTimeout,
                data: {
                    action: 'bpfn_check_notifications',
                    last_checked: self.config.lastChecked,
                    nonce: self.config.nonce
                },
                success: function(response) {
                    self.state.isChecking = false;
                    self.state.lastSuccessfulCheck = Date.now();
                    self.state.retryCount = 0; // Reset on success
                    
                    if (response && response.success && response.data) {
                        self.handleNotificationResponse(response.data);
                    }
                    
                    self.updateConnectionStatus('connected');
                    self.scheduleNextPoll();
                },
                error: function(xhr, status, error) {
                    self.state.isChecking = false;
                    self.state.retryCount++;
                    
                    self.log('Polling error: ' + error + ' (attempt ' + self.state.retryCount + ')');
                    self.handleConnectionError('polling', error);
                    self.scheduleNextPoll();
                }
            });
        },

        /**
         * Handle connection errors with graceful degradation
         */
        handleConnectionError: function(method, error) {
            var self = this;
            
            if (self.state.retryCount >= self.config.maxRetries) {
                self.log('Max retries reached for ' + method + ', attempting fallback');
                self.attemptFallback();
            } else {
                self.updateConnectionStatus('error');
                
                // Show user notification after multiple failures
                if (self.state.retryCount >= 3) {
                    self.showConnectionIssue();
                }
            }
        },

        /**
         * Attempt fallback to another method
         */
        attemptFallback: function() {
            var self = this;
            
            self.log('Attempting fallback from ' + self.state.currentMethod);
            
            // Reset retry count
            self.state.retryCount = 0;
            
            // Try alternative methods
            switch (self.state.currentMethod) {
                case 'heartbeat':
                    self.initializePolling().then(function() {
                        self.state.currentMethod = 'polling';
                        self.log('Fell back to polling');
                    });
                    break;
                    
                case 'sse':
                    self.initializePolling().then(function() {
                        self.state.currentMethod = 'polling';
                        self.log('Fell back to polling');
                    });
                    break;
                    
                default:
                    self.updateConnectionStatus('failed');
                    self.showConnectionFailure();
                    break;
            }
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
                    }, index * 200); // Stagger notifications
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
            
            // Create notification container
            if (!self.config.container || !self.config.container.length) {
                self.config.container = $('<div id="bpfn-realtime-container"></div>');
                self.config.container.addClass('bpfn-position-' + self.config.position);
                $('body').append(self.config.container);
            }
            
            // Create connection status indicator (only in debug mode)
            if (self.config.debug) {
                self.createConnectionIndicator();
            }
        },

        /**
         * Create connection status indicator
         */
        createConnectionIndicator: function() {
            var self = this;
            
            if ($('#bpfn-connection-status').length > 0) {
                return;
            }
            
            var $indicator = $('<div id="bpfn-connection-status"></div>');
            $indicator.css({
                position: 'fixed',
                bottom: '10px',
                left: '10px',
                padding: '8px 12px',
                fontSize: '12px',
                borderRadius: '4px',
                zIndex: 9998,
                display: 'none',
                transition: 'all 0.3s ease',
                backgroundColor: '#333',
                color: 'white'
            });
            
            $('body').append($indicator);
        },

        /**
         * Update connection status
         */
        updateConnectionStatus: function(status) {
            var self = this;
            self.state.connectionStatus = status;
            
            var $indicator = $('#bpfn-connection-status');
            if (!$indicator.length || !self.config.debug) return;
            
            var statusConfig = {
                'connected': {
                    icon: '🟢',
                    text: 'Connected',
                    color: '#10b981',
                    show: false
                },
                'checking': {
                    icon: '🟡',
                    text: 'Checking...',
                    color: '#f59e0b',
                    show: false
                },
                'error': {
                    icon: '🟠',
                    text: 'Connection issues',
                    color: '#ef4444',
                    show: true
                },
                'failed': {
                    icon: '🔴',
                    text: 'Connection failed',
                    color: '#dc2626',
                    show: true
                }
            };
            
            var config = statusConfig[status] || statusConfig.error;
            
            $indicator
                .html(config.icon + ' ' + config.text)
                .css('backgroundColor', config.color);
                
            if (config.show) {
                $indicator.fadeIn();
                // Auto-hide after 5 seconds for non-critical statuses
                if (status === 'checking') {
                    setTimeout(function() {
                        if (self.state.connectionStatus === 'checking') {
                            $indicator.fadeOut();
                        }
                    }, 5000);
                }
            } else {
                $indicator.fadeOut();
            }
        },

        /**
         * Show connection issue notification
         */
        showConnectionIssue: function() {
            var self = this;
            
            var message = 'Real-time notifications may be delayed due to connection issues. We\'re trying to reconnect...';
            
            if (window.BPFN && window.BPFN.UI && window.BPFN.UI.showNotification) {
                window.BPFN.UI.showNotification(message, 'warning', {
                    duration: 5000,
                    dismissible: true
                });
            }
        },

        /**
         * Show connection failure notification
         */
        showConnectionFailure: function() {
            var self = this;
            
            var message = 'Real-time notifications are currently unavailable. Please refresh the page to restore functionality.';
            
            if (window.BPFN && window.BPFN.UI && window.BPFN.UI.showNotification) {
                window.BPFN.UI.showNotification(message, 'error', {
                    duration: 0, // Don't auto-dismiss
                    dismissible: true
                });
            }
        },

        /**
         * Handle initialization error
         */
        handleInitializationError: function(error) {
            var self = this;
            
            self.log('Initialization error: ' + error.message);
            
            // Try to initialize with polling as last resort
            if (self.state.currentMethod !== 'polling') {
                self.log('Attempting fallback to polling after initialization error');
                self.initializePolling().then(function() {
                    self.state.currentMethod = 'polling';
                    self.state.initialized = true;
                    self.setupUI();
                    self.bindEvents();
                    self.initSound();
                    self.log('Fallback initialization completed');
                });
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
         * Enhanced logging with timestamps
         */
        log: function(message, data) {
            if (this.config.debug && window.console) {
                var timestamp = new Date().toISOString();
                console.log('[BPFN Realtime ' + timestamp + '] ' + message, data || '');
            }
        },

        /**
         * Destroy and cleanup with enhanced cleanup
         */
        destroy: function() {
            var self = this;
            
            // Clean up all event handlers
            $(document).off('.bpfn-heartbeat .bpfn-realtime');
            
            // Close SSE connection
            if (self.eventSource) {
                self.eventSource.close();
                self.eventSource = null;
            }
            
            // Clear polling
            if (self.polling && self.polling.timeout) {
                clearTimeout(self.polling.timeout);
                self.polling.isActive = false;
            }
            
            // Remove UI elements
            $('#bpfn-connection-status').remove();
            if (self.config.container) {
                self.config.container.remove();
            }
            
            // Reset state
            self.state = {
                initialized: false,
                notifications: [],
                soundEnabled: true,
                isChecking: false,
                heartbeatActive: false,
                currentMethod: 'none',
                retryCount: 0,
                connectionStatus: 'disconnected',
                lastSuccessfulCheck: 0
            };
            
            self.log('Enhanced realtime module destroyed');
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