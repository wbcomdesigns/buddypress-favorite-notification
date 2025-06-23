/**
 * BuddyPress Favorite Notification - Core Scripts
 * Version: 1.2.3
 * 
 * Modular JavaScript with extensible API
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.BPFN = window.BPFN || {};

    /**
     * Core notification handler
     */
    BPFN.Core = {
        
        // Configuration
        config: {
            debug: false,
            ajaxUrl: '',
            nonce: '',
            userId: 0,
            componentId: '',
            selectors: {
                notification: '.bpfn-notification, .bpfn-notification-item',
                favoriteBtn: '.fav-button, .unfav-button',
                count: '.bpfn-count',
                container: '#buddypress'
            },
            classes: {
                loading: 'bpfn-loading',
                unread: 'unread',
                read: 'read',
                fadeIn: 'bpfn-fade-in'
            }
        },

        // State management
        state: {
            initialized: false,
            processing: false,
            notifications: []
        },

        /**
         * Initialize
         */
        init: function(options) {
            var self = this;
            
            // Merge options
            self.config = $.extend(true, {}, self.config, options || {});
            
            // Initialize modules
            self.initModules();
            
            // Bind events
            self.bindEvents();
            
            // Mark as initialized
            self.state.initialized = true;
            
            // Trigger init event
            self.trigger('init', self);
            
            self.log('BPFN Core initialized');
        },

        /**
         * Initialize modules
         */
        initModules: function() {
            var self = this;
            
            // Initialize each module if present
            var modules = ['UI', 'Ajax', 'Events', 'Utils'];
            
            $.each(modules, function(i, module) {
                if (BPFN[module] && typeof BPFN[module].init === 'function') {
                    BPFN[module].init(self.config);
                    self.log(module + ' module initialized');
                }
            });
        },

        /**
         * Bind core events
         */
        bindEvents: function() {
            var self = this;
            
            // Favorite button clicks
            $(document).on('click', self.config.selectors.favoriteBtn, function(e) {
                e.preventDefault();
                self.handleFavoriteClick($(this));
            });
            
            // Notification clicks
            $(document).on('click', self.config.selectors.notification + ' a', function() {
                var $notification = $(this).closest(self.config.selectors.notification);
                self.markAsRead($notification);
            });
            
            // Custom events
            $(document).on('bpfn:favorite:success', function(e, data) {
                self.onFavoriteSuccess(data);
            });
            
            $(document).on('bpfn:notification:new', function(e, data) {
                self.addNotification(data);
            });
        },

        /**
         * Handle favorite button click
         */
        handleFavoriteClick: function($button) {
            var self = this;
            
            if (self.state.processing) {
                return;
            }
            
            self.state.processing = true;
            $button.addClass(self.config.classes.loading);
            
            var data = {
                action: $button.hasClass('fav-button') ? 'favorite' : 'unfavorite',
                activityId: self.getActivityId($button),
                button: $button
            };
            
            // Trigger before event
            if (self.trigger('favorite:before', data) === false) {
                self.state.processing = false;
                $button.removeClass(self.config.classes.loading);
                return;
            }
            
            // Show feedback
            BPFN.UI.showFeedback(
                data.action === 'favorite' ? 
                BPFN.strings.favoriting : 
                BPFN.strings.unfavoriting
            );
            
            // Let BuddyPress handle the actual favorite action
            // We just add our enhancements
            setTimeout(function() {
                self.state.processing = false;
                $button.removeClass(self.config.classes.loading);
                self.trigger('favorite:after', data);
            }, 500);
        },

        /**
         * Get activity ID from button
         */
        getActivityId: function($button) {
            var $activity = $button.closest('.activity-item');
            if ($activity.length) {
                return $activity.attr('id').replace('activity-', '');
            }
            return 0;
        },

        /**
         * Mark notification as read
         */
        markAsRead: function($notification) {
            var self = this;
            
            if ($notification.hasClass(self.config.classes.read)) {
                return;
            }
            
            $notification
                .removeClass(self.config.classes.unread)
                .addClass(self.config.classes.read);
            
            // Get notification ID
            var notificationId = $notification.data('notification-id');
            
            if (notificationId) {
                // Mark as read via AJAX
                BPFN.Ajax.post('mark_notification_read', {
                    notification_id: notificationId
                });
            }
            
            // Update count
            self.updateCount(-1);
            
            // Trigger event
            self.trigger('notification:read', {
                element: $notification,
                id: notificationId
            });
        },

        /**
         * Add new notification
         */
        addNotification: function(data) {
            var self = this;
            
            // Add to state
            self.state.notifications.push(data);
            
            // Update count
            self.updateCount(1);
            
            // Trigger event
            self.trigger('notification:added', data);
        },

        /**
         * Update notification count
         */
        updateCount: function(change) {
            var self = this;
            var $counts = $(self.config.selectors.count);
            
            $counts.each(function() {
                var $count = $(this);
                var current = parseInt($count.text()) || 0;
                var newCount = Math.max(0, current + change);
                
                if (newCount > 0) {
                    $count.text(newCount).show();
                } else {
                    $count.hide();
                }
            });
            
            self.trigger('count:updated', { change: change });
        },

        /**
         * Event handlers
         */
        onFavoriteSuccess: function(data) {
            var self = this;
            
            // Show success message
            BPFN.UI.showNotification(
                data.action === 'favorite' ? 
                BPFN.strings.favorited : 
                BPFN.strings.unfavorited,
                'success'
            );
            
            // Trigger custom event
            self.trigger('favorite:success', data);
        },

        /**
         * Trigger custom event
         */
        trigger: function(event, data) {
            var self = this;
            var eventName = 'bpfn:' + event;
            
            self.log('Triggering event: ' + eventName, data);
            
            var e = $.Event(eventName);
            $(document).trigger(e, [data]);
            
            return !e.isDefaultPrevented();
        },

        /**
         * Log message
         */
        log: function(message, data) {
            if (this.config.debug && window.console) {
                console.log('[BPFN] ' + message, data || '');
            }
        }
    };

    /**
     * UI Module
     */
    BPFN.UI = {
        
        config: {},
        
        init: function(config) {
            this.config = config;
        },

        /**
         * Show temporary feedback
         */
        showFeedback: function(message, duration) {
            var $feedback = $('<div class="bpfn-feedback">' + message + '</div>');
            
            $('body').append($feedback);
            
            $feedback.addClass('show');
            
            setTimeout(function() {
                $feedback.removeClass('show');
                setTimeout(function() {
                    $feedback.remove();
                }, 300);
            }, duration || 1500);
        },

        /**
         * Show notification
         */
        showNotification: function(message, type, options) {
            var defaults = {
                duration: 3000,
                position: 'top-right',
                dismissible: true
            };
            
            options = $.extend({}, defaults, options || {});
            
            var $notification = $('<div class="bpfn-notification-popup bpfn-' + type + '">' +
                '<div class="bpfn-notification-popup-content">' + message + '</div>' +
                (options.dismissible ? '<button class="bpfn-notification-popup-close">&times;</button>' : '') +
                '</div>');
            
            $('body').append($notification);
            
            // Position
            $notification.addClass('bpfn-position-' + options.position);
            
            // Show
            setTimeout(function() {
                $notification.addClass('show');
            }, 10);
            
            // Auto-hide
            if (options.duration > 0) {
                setTimeout(function() {
                    BPFN.UI.hideNotification($notification);
                }, options.duration);
            }
            
            // Dismiss button
            $notification.find('.bpfn-notification-popup-close').on('click', function() {
                BPFN.UI.hideNotification($notification);
            });
            
            return $notification;
        },

        /**
         * Hide notification
         */
        hideNotification: function($notification) {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }
    };

    /**
     * AJAX Module
     */
    BPFN.Ajax = {
        
        config: {},
        
        init: function(config) {
            this.config = config;
        },

        /**
         * POST request
         */
        post: function(action, data, callbacks) {
            var self = this;
            
            var defaults = {
                action: 'bpfn_' + action,
                nonce: self.config.nonce
            };
            
            data = $.extend({}, defaults, data || {});
            callbacks = callbacks || {};
            
            return $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: data,
                dataType: 'json',
                beforeSend: function() {
                    if (callbacks.before) {
                        callbacks.before();
                    }
                },
                success: function(response) {
                    if (response.success) {
                        if (callbacks.success) {
                            callbacks.success(response.data);
                        }
                        BPFN.Core.trigger('ajax:success', {
                            action: action,
                            data: response.data
                        });
                    } else {
                        if (callbacks.error) {
                            callbacks.error(response.data);
                        }
                        BPFN.Core.trigger('ajax:error', {
                            action: action,
                            error: response.data
                        });
                    }
                },
                error: function(xhr, status, error) {
                    if (callbacks.error) {
                        callbacks.error({ message: error });
                    }
                    BPFN.Core.trigger('ajax:error', {
                        action: action,
                        error: error
                    });
                },
                complete: function() {
                    if (callbacks.complete) {
                        callbacks.complete();
                    }
                }
            });
        }
    };

    /**
     * Events Module - Custom event handlers
     */
    BPFN.Events = {
        
        handlers: {},
        
        init: function() {
            // Register default event handlers
        },

        /**
         * Register event handler
         */
        on: function(event, handler) {
            if (!this.handlers[event]) {
                this.handlers[event] = [];
            }
            this.handlers[event].push(handler);
        },

        /**
         * Trigger event handlers
         */
        trigger: function(event, data) {
            if (this.handlers[event]) {
                $.each(this.handlers[event], function(i, handler) {
                    handler(data);
                });
            }
        }
    };

    /**
     * Utility functions
     */
    BPFN.Utils = {
        
        init: function() {},

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },

        /**
         * Throttle function
         */
        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var args = arguments;
                var context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() {
                        inThrottle = false;
                    }, limit);
                }
            };
        }
    };

    /**
     * Initialize when ready
     */
    $(document).ready(function() {
        // Initialize if config is available
        if (typeof BPFN !== 'undefined') {
            // Check if we have config
            if (BPFN.config) {
                BPFN.Core.init(BPFN.config);
            } else {
                // Try to initialize with available data
                BPFN.Core.init({
                    ajaxUrl: BPFN.ajax_url || '',
                    nonce: BPFN.nonce || '',
                    userId: BPFN.user_id || 0,
                    componentId: BPFN.component_id || 'favorite_notifier',
                    debug: BPFN.debug || false
                });
            }
            
            // Add strings if available
            if (BPFN.strings) {
                BPFN.strings = $.extend({}, BPFN.strings);
            }
        }
    });

    // Public API
    BPFN.init = function(options) {
        BPFN.Core.init(options);
    };
    
    BPFN.on = function(event, handler) {
        BPFN.Events.on(event, handler);
    };
    
    BPFN.showNotification = function(message, type, options) {
        return BPFN.UI.showNotification(message, type, options);
    };

})(jQuery, window, document);