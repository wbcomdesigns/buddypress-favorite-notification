(function($) {
    'use strict';

    var BPFNFavoriteDisplay = {

        /**
         * Timestamp (ms) of the last successful refresh per activity ID.
         * Used to dedupe the click-fallback timer against the ajaxSuccess
         * listener so a single favorite action triggers one refresh, not two.
         */
        lastRefreshed: {},

        /**
         * Element that had focus when the modal was opened, so focus can be
         * restored to it on close.
         */
        lastFocused: null,

        init: function() {
            this.bindEvents();
        },

        /**
         * Translated UI string with an English fallback.
         *
         * @param {string} key      Key inside bpfnFavorites.strings.
         * @param {string} fallback Fallback text.
         * @return {string}
         */
        str: function(key, fallback) {
            if (typeof bpfnFavorites !== 'undefined' && bpfnFavorites.strings && bpfnFavorites.strings[key]) {
                return bpfnFavorites.strings[key];
            }
            return fallback;
        },

        bindEvents: function() {
            var self = this;

            // Listen for BuddyPress heartbeat updates.
            $(document).on('heartbeat-tick', function(event, data) {
                if (data.bp_activity_latest_update) {
                    self.checkForUpdates();
                }
            });

            // Listen for page-loaded event from BuddyPress.
            $(document).on('bp_ajax_request', function() {
                // Activity stream AJAX loaded.
                setTimeout(function() {
                    self.updateAllDisplays();
                }, 500);
            });

            // Deterministic refresh: BuddyPress (Legacy and Nouveau templates)
            // posts `activity_mark_fav` / `activity_mark_unfav` through
            // jQuery.ajax, so the global ajaxSuccess event fires at the exact
            // moment the favorite has been recorded server-side. Refreshing
            // here (instead of only guessing with a fixed delay after the
            // button click) is what makes the "liked this" line update
            // instantly without a page reload.
            $(document).ajaxSuccess(function(event, xhr, settings) {
                if (!settings || typeof settings.data !== 'string') {
                    return;
                }
                if (settings.data.indexOf('action=activity_mark_fav') === -1 &&
                    settings.data.indexOf('action=activity_mark_unfav') === -1) {
                    return;
                }
                var match = settings.data.match(/(?:^|&)id=(\d+)/);
                if (match && match[1]) {
                    self.updateDisplay(match[1]);
                }
            });

            // View all favorites modal. `.bpfn-view-all-favorites` is the
            // counter button rendered in modal mode; `.bpfn-others-count` is
            // the "and N others" link rendered in inline mode.
            $(document).on('click', '.bpfn-view-all-favorites, .bpfn-others-count', this.showAllFavorites.bind(this));

            // Load the next page of likers into the open modal.
            $(document).on('click', '.bpfn-load-more-favorites', this.loadMoreFavorites.bind(this));

            // Close modal.
            $(document).on('click', '.bpfn-modal-close, .bpfn-modal-overlay', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // ESC key to close modal.
            $(document).keyup(function(e) {
                if (e.key === "Escape") {
                    self.closeModal();
                }
            });

            // Keep Tab inside the dialog while it is open.
            $(document).on('keydown', '.bpfn-modal-overlay', function(e) {
                if (e.key !== 'Tab') {
                    return;
                }
                var $focusable = $(this).find('button, a[href]').filter(':visible');
                if (!$focusable.length) {
                    return;
                }
                var first = $focusable.first()[0];
                var last = $focusable.last()[0];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });

            // Fallback for favorite flows that bypass jQuery.ajax (e.g. a
            // theme replacing BP's handler with fetch()): refresh shortly
            // after the button click unless the ajaxSuccess listener already
            // handled this activity.
            $(document).on('click', '.fav, .unfav', function() {
                var $activity = $(this).closest('.activity-item, li[id^="activity-"]');
                if (!$activity.length) {
                    return;
                }
                var activityId = ($activity.attr('id') || '').replace('activity-', '');
                if (!activityId) {
                    return;
                }
                var clickedAt = Date.now();
                setTimeout(function() {
                    if (self.lastRefreshed[activityId] && self.lastRefreshed[activityId] >= clickedAt) {
                        return; // Already refreshed by the ajaxSuccess listener.
                    }
                    self.updateDisplay(activityId);
                }, 1000);
            });
        },

        /**
         * Insert a freshly rendered display into an activity item, mirroring
         * the server-side render locations: the theme post footer
         * (bp_activity_before_post_footer_content - BuddyX/BuddyX Pro/Reign)
         * or the end of the activity content (bp_activity_entry_content -
         * core Nouveau/Legacy templates). Previously only the theme footer
         * was targeted, so on themes without `.bp-activity-post-footer` the
         * first favorite never appeared until a page refresh.
         *
         * @param {jQuery} $activity Activity item element.
         * @param {string} html      Display HTML from the server.
         */
        insertDisplay: function($activity, html) {
            var $footer = $activity.find('.bp-activity-post-footer').first();
            if ($footer.length) {
                $footer.prepend(html);
                return;
            }

            var $inner = $activity.find('.activity-inner').first();
            if ($inner.length) {
                $inner.append(html);
                return;
            }

            var $content = $activity.find('.activity-content').first();
            if ($content.length) {
                $content.append(html);
            }
        },

        updateDisplay: function(activityId) {
            var self = this;
            var $container = $('.bpfn-favorite-display[data-activity-id="' + activityId + '"]');
            var $activity = $('#activity-' + activityId);

            this.lastRefreshed[activityId] = Date.now();

            // Reload the favorite display for this activity.
            $.ajax({
                url: bpfnFavorites.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_refresh_favorite_display',
                    activity_id: activityId,
                    nonce: bpfnFavorites.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        if ($container.length) {
                            // Update existing display.
                            $container.replaceWith(response.data.html);
                        } else {
                            // Add new display if it doesn't exist yet.
                            self.insertDisplay($activity, response.data.html);
                        }
                        // Fade in animation.
                        $('.bpfn-favorite-display[data-activity-id="' + activityId + '"]').hide().fadeIn(300);
                    } else if (response.success && response.data && response.data.count === 0) {
                        // No favorites, remove display.
                        $container.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                }
            });
        },

        updateAllDisplays: function() {
            var self = this;
            $('.bpfn-favorite-display').each(function() {
                var activityId = $(this).data('activity-id');
                if (activityId) {
                    self.updateDisplay(activityId);
                }
            });
        },

        checkForUpdates: function() {
            // Placeholder for future real-time updates via heartbeat.
            // Currently updates happen on button click.
        },

        showAllFavorites: function(e) {
            e.preventDefault();
            var $target = $(e.currentTarget);
            var activityId = $target.data('activity-id') || $target.closest('.bpfn-favorite-display').data('activity-id');

            if (!activityId) {
                return;
            }

            var self = this;

            // Show loading state.
            this.showModal('<div class="bpfn-loading">' + this.str('loading', 'Loading…') + '</div>');

            $.ajax({
                url: bpfnFavorites.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_get_all_favorites',
                    activity_id: activityId,
                    nonce: bpfnFavorites.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        self.showModal(response.data.html);
                    } else {
                        self.showModal('<div class="bpfn-error">' + self.str('loadFailed', 'Failed to load favorites list.') + '</div>');
                    }
                },
                error: function() {
                    self.showModal('<div class="bpfn-error">' + self.str('loadError', 'An error occurred while loading favorites.') + '</div>');
                }
            });
        },

        /**
         * Append the next page of likers to the open modal.
         *
         * @param {Event} e Click event.
         */
        loadMoreFavorites: function(e) {
            e.preventDefault();

            var self = this;
            var $button = $(e.currentTarget);
            var activityId = $button.data('activity-id');
            var nextPage = parseInt($button.data('next-page'), 10) || 2;

            if (!activityId || $button.prop('disabled')) {
                return;
            }

            var originalLabel = $button.text();
            $button.prop('disabled', true).text(this.str('loading', 'Loading…'));

            $.ajax({
                url: bpfnFavorites.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_get_all_favorites',
                    activity_id: activityId,
                    page: nextPage,
                    nonce: bpfnFavorites.nonce
                },
                success: function(response) {
                    if (!response.success || !response.data) {
                        $button.prop('disabled', false).text(originalLabel);
                        return;
                    }

                    var $list = $('.bpfn-modal .bpfn-favorites-user-list');
                    // Index of the first incoming row = how many were already
                    // there, captured before the append.
                    var priorCount = $list.children('.bpfn-favorite-user-item').length;
                    if (response.data.items) {
                        $list.append(response.data.items);
                    }

                    if (response.data.has_more) {
                        $button.prop('disabled', false)
                            .text(originalLabel)
                            .data('next-page', nextPage + 1);
                    } else {
                        // Nothing left to fetch — drop the control rather than
                        // leaving a button that does nothing.
                        $button.closest('.bpfn-favorites-pagination').remove();
                    }

                    // Move focus to the first newly added row so keyboard and
                    // screen-reader users land on the new content instead of
                    // being dropped at the top of the dialog.
                    var $added = $list.children('.bpfn-favorite-user-item').eq(priorCount);
                    if ($added.length) {
                        $added.find('a').first().trigger('focus');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalLabel);
                }
            });
        },

        showModal: function(html) {
            var $existing = $('.bpfn-modal-overlay');

            // Remember what opened the dialog so focus can be restored on close.
            // Only capture it when no dialog is open yet: showAllFavorites()
            // calls this twice (loading state, then content), and by the second
            // call focus is on the close button of the dialog we are about to
            // remove — capturing then would record that instead of the counter,
            // and removing it leaves document.activeElement as <body>, so focus
            // would be dumped at the top of the page on close.
            if (!$existing.length) {
                this.lastFocused = document.activeElement;
            }

            // Remove existing modal.
            $existing.remove();

            var $modal = $('<div class="bpfn-modal-overlay">' +
                '<div class="bpfn-modal" role="dialog" aria-modal="true">' +
                    '<button class="bpfn-modal-close" aria-label="' + this.str('close', 'Close') + '">&times;</button>' +
                    html +
                '</div>' +
            '</div>');

            $('body').append($modal);

            // Fade in animation.
            $modal.hide().fadeIn(200);

            // Prevent body scroll when modal is open.
            $('body').addClass('bpfn-modal-open');

            $modal.find('.bpfn-modal-close').trigger('focus');
        },

        closeModal: function() {
            var $overlay = $('.bpfn-modal-overlay');
            if (!$overlay.length) {
                return;
            }

            $overlay.fadeOut(200, function() {
                $(this).remove();
            });
            $('body').removeClass('bpfn-modal-open');

            // Return focus to the counter/link that opened the dialog.
            if (this.lastFocused && document.body.contains(this.lastFocused)) {
                $(this.lastFocused).trigger('focus');
            }
            this.lastFocused = null;
        }
    };

    // Initialize when document is ready.
    $(document).ready(function() {
        if (typeof bpfnFavorites !== 'undefined') {
            BPFNFavoriteDisplay.init();
        }
    });

})(jQuery);
