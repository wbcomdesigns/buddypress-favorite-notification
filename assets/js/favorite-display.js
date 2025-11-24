(function($) {
    'use strict';

    var BPFNFavoriteDisplay = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Listen for BuddyPress favorite/unfavorite actions
            $(document).on('heartbeat-tick', function(event, data) {
                // BuddyPress uses heartbeat for some updates
                if (data.bp_activity_latest_update) {
                    self.checkForUpdates();
                }
            });

            // Listen for page-loaded event from BuddyPress
            $(document).on('bp_ajax_request', function() {
                // Activity stream AJAX loaded
                setTimeout(function() {
                    self.updateAllDisplays();
                }, 500);
            });

            // View all favorites modal
            $(document).on('click', '.bpfn-view-all-favorites, .bpfn-others-count', this.showAllFavorites.bind(this));

            // Close modal
            $(document).on('click', '.bpfn-modal-close, .bpfn-modal-overlay', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // ESC key to close modal
            $(document).keyup(function(e) {
                if (e.key === "Escape") {
                    self.closeModal();
                }
            });

            // Monitor favorite button clicks (BP Nouveau uses these)
            $(document).on('click', '.fav, .unfav', function() {
                var $activity = $(this).closest('.activity-item, li[id^="activity-"]');
                if ($activity.length) {
                    var activityId = $activity.attr('id').replace('activity-', '');
                    // Update display after a short delay to let BP process the action
                    setTimeout(function() {
                        self.updateDisplay(activityId);
                    }, 1000);
                }
            });
        },

        updateDisplay: function(activityId) {
            var $container = $('.bpfn-favorite-display[data-activity-id="' + activityId + '"]');
            var $activity = $('#activity-' + activityId);

            // Reload the favorite display for this activity
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
                            // Update existing display
                            $container.replaceWith(response.data.html);
                        } else {
                            // Add new display if it doesn't exist yet
                            $activity.find('.bp-activity-post-footer').first().prepend(response.data.html);
                        }
                        // Fade in animation
                        $('.bpfn-favorite-display[data-activity-id="' + activityId + '"]').hide().fadeIn(300);
                    } else if (response.data && response.data.count === 0) {
                        // No favorites, remove display
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
            // Placeholder for future real-time updates via heartbeat
            // Currently updates happen on button click
        },

        showAllFavorites: function(e) {
            e.preventDefault();
            var $target = $(e.currentTarget);
            var activityId = $target.data('activity-id') || $target.closest('.bpfn-favorite-display').data('activity-id');

            if (!activityId) {
                return;
            }

            var self = this;

            // Show loading state
            this.showModal('<div class="bpfn-loading">Loading...</div>');

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
                        self.showModal('<div class="bpfn-error">Failed to load favorites list.</div>');
                    }
                },
                error: function() {
                    self.showModal('<div class="bpfn-error">An error occurred while loading favorites.</div>');
                }
            });
        },

        showModal: function(html) {
            // Remove existing modal
            $('.bpfn-modal-overlay').remove();

            var $modal = $('<div class="bpfn-modal-overlay">' +
                '<div class="bpfn-modal">' +
                    '<button class="bpfn-modal-close" aria-label="Close">&times;</button>' +
                    html +
                '</div>' +
            '</div>');

            $('body').append($modal);

            // Fade in animation
            $modal.hide().fadeIn(200);

            // Prevent body scroll when modal is open
            $('body').addClass('bpfn-modal-open');
        },

        closeModal: function() {
            $('.bpfn-modal-overlay').fadeOut(200, function() {
                $(this).remove();
            });
            $('body').removeClass('bpfn-modal-open');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof bpfnFavorites !== 'undefined') {
            BPFNFavoriteDisplay.init();
        }
    });

})(jQuery);
