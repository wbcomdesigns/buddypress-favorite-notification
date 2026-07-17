/**
 * BuddyPress Favorite Notification - Admin Scripts
 *
 * Drives the card-panel admin (Overview / Tools). Only binds to controls the
 * current views actually render:
 *   #bpfn-migrate-favorites       (Tools tab  -> bpfn_migrate_favorites +
 *                                  bpfn_migration_progress polling)
 *   #bpfn-clear-old-notifications (Tools tab  -> bpfn_clear_old_notifications)
 */

(function($, window, document) {
    'use strict';

    // Check if bpfnAdmin is defined
    if (typeof bpfnAdmin === 'undefined') {
        return;
    }

    var i18n = (bpfnAdmin && bpfnAdmin.strings) || {};

    function str(key, fallback) {
        return i18n[key] || fallback;
    }

    /* ─── Toast (no native alert) ─────────────────────────────── */

    function getToastHost() {
        var host = document.querySelector('.bpfn-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.className = 'bpfn-toast-host';
            document.body.appendChild(host);
        }
        return host;
    }

    function bpfnToast(message, tone) {
        tone = tone || 'info';
        var host = getToastHost();
        var el = document.createElement('div');
        el.className = 'bpfn-toast bpfn-toast--' + tone;
        el.setAttribute('role', 'status');
        el.textContent = String(message);
        host.appendChild(el);

        window.requestAnimationFrame(function() {
            el.classList.add('bpfn-toast--visible');
        });

        window.setTimeout(function() {
            el.classList.remove('bpfn-toast--visible');
            window.setTimeout(function() {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 250);
        }, 3600);
    }

    window.bpfnToast = bpfnToast;

    /* ─── Confirm modal (returns a Promise, no native confirm) ── */

    function bpfnConfirm(opts) {
        opts = opts || {};
        return new Promise(function(resolve) {
            var backdrop = document.createElement('div');
            backdrop.className = 'bpfn-confirm-backdrop';

            var card = document.createElement('div');
            card.className = 'bpfn-confirm';
            card.setAttribute('role', 'dialog');
            card.setAttribute('aria-modal', 'true');

            var title = document.createElement('h2');
            title.className = 'bpfn-confirm__title';
            title.textContent = opts.title || '';
            if (opts.title) {
                card.appendChild(title);
            }

            var desc = document.createElement('p');
            desc.className = 'bpfn-confirm__desc';
            desc.textContent = opts.message || i18n.confirm_danger || '';
            if (opts.message || i18n.confirm_danger) {
                card.appendChild(desc);
            }

            var actions = document.createElement('div');
            actions.className = 'bpfn-confirm__actions';

            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'bpfn-btn bpfn-btn-secondary';
            cancelBtn.textContent = opts.cancelLabel || i18n.confirm_cancel || 'Cancel';

            var confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'bpfn-btn ' + ('danger' === opts.tone ? 'bpfn-btn-danger' : 'bpfn-btn-primary');
            confirmBtn.textContent = opts.confirmLabel || i18n.confirm_continue || 'Continue';

            actions.appendChild(cancelBtn);
            actions.appendChild(confirmBtn);
            card.appendChild(actions);
            backdrop.appendChild(card);
            document.body.appendChild(backdrop);

            function cleanup(result) {
                document.removeEventListener('keydown', onKey);
                if (backdrop.parentNode) {
                    backdrop.parentNode.removeChild(backdrop);
                }
                resolve(result);
            }

            function onKey(e) {
                if ('Escape' === e.key) {
                    cleanup(false);
                }
                if ('Enter' === e.key) {
                    cleanup(true);
                }
            }

            cancelBtn.addEventListener('click', function() {
                cleanup(false);
            });
            confirmBtn.addEventListener('click', function() {
                cleanup(true);
            });
            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop) {
                    cleanup(false);
                }
            });
            document.addEventListener('keydown', onKey);
            confirmBtn.focus();
        });
    }

    window.bpfnConfirm = bpfnConfirm;

    /**
     * Admin handler
     */
    var BPFNAdmin = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Migrate favorites
            $('#bpfn-migrate-favorites').on('click', function(e) {
                e.preventDefault();
                self.migrateFavorites($(this));
            });

            // Clear old notifications
            $('#bpfn-clear-old-notifications').on('click', function(e) {
                e.preventDefault();
                self.clearOldNotifications($(this));
            });
        },

        /**
         * Migrate favorites
         */
        migrateFavorites: function($button) {
            var self = this;
            bpfnConfirm({
                title: str('confirm_migrate_title', 'Run migration?'),
                message: str('confirm_migrate', 'This will migrate all existing favorites to the new optimized table. Continue?')
            }).then(function(ok) {
                if (ok) {
                    self.runMigrateFavorites($button);
                }
            });
        },

        /**
         * Run favorites migration (after confirm).
         */
        runMigrateFavorites: function($button) {
            var self = this;
            var originalText = $button.text();
            var $result = $('#bpfn-migrate-result');

            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin"></span> ' + str('migrating', 'Starting migration...'));
            $result.html('');

            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_migrate_favorites',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Check if background migration
                        if (response.data.background) {
                            // Show progress bar
                            $result.html('<div class="notice notice-info inline">' +
                                '<p>' + response.data.message + '</p>' +
                                '<div class="bpfn-progress-wrapper">' +
                                    '<div class="bpfn-progress-bar">' +
                                        '<div class="bpfn-progress-fill" style="width: 0%;"></div>' +
                                    '</div>' +
                                    '<div class="bpfn-progress-text">0%</div>' +
                                '</div>' +
                            '</div>');
                            // Start polling for progress
                            self.checkMigrationProgress();
                        } else {
                            // Synchronous migration completed
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + (response.data.message || str('migrate_failed', 'Migration failed.')) + '</p></div>');
                        $button.prop('disabled', false);
                        $button.text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="notice notice-error inline"><p>' + str('error_generic', 'An error occurred:') + ' ' + error + '</p></div>');
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Check migration progress (polling for background migration)
         */
        checkMigrationProgress: function() {
            var self = this;
            var $result = $('#bpfn-migrate-result');

            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_migration_progress',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var progress = response.data;

                        // Update progress bar
                        $('.bpfn-progress-fill').css('width', progress.percent + '%');
                        $('.bpfn-progress-text').text(
                            progress.percent + '% (' + progress.users_processed + '/' + progress.total_users + ' ' + str('users', 'users') + ')'
                        );

                        if (progress.status === 'completed') {
                            // Migration complete
                            $result.html('<div class="notice notice-success inline"><p>' +
                                str('migration_complete', 'Migration completed! Processed %1$s users and added %2$s favorites.')
                                    .replace('%1$s', progress.users_processed)
                                    .replace('%2$s', progress.favorites_added) +
                            '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else if (progress.status === 'running') {
                            // Continue polling
                            setTimeout(function() {
                                self.checkMigrationProgress();
                            }, 2000);
                        } else {
                            // Error or cancelled
                            $result.html('<div class="notice notice-error inline"><p>' +
                                str('migration_status', 'Migration %s').replace('%s', progress.status) +
                            '</p></div>');
                        }
                    }
                },
                error: function() {
                    // Retry on error
                    setTimeout(function() {
                        self.checkMigrationProgress();
                    }, 3000);
                }
            });
        },

        /**
         * Clear old notifications
         */
        clearOldNotifications: function($button) {
            var self = this;
            bpfnConfirm({
                title: str('confirm_clear_title', 'Clear old notifications?'),
                message: str('confirm_clear_old', 'Are you sure you want to clear all read notifications older than the retention period? This cannot be undone.'),
                tone: 'danger'
            }).then(function(ok) {
                if (ok) {
                    self.runClearOldNotifications($button);
                }
            });
        },

        /**
         * Run clear old notifications (after confirm).
         */
        runClearOldNotifications: function($button) {
            var self = this;
            var originalText = $button.text();

            $button.prop('disabled', true);
            $button.html('<span class="bpfn-spinner"></span> ' + str('clearing', 'Clearing...'));

            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_clear_old_notifications',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = str('cleared', 'Successfully cleared %s old notifications.').replace('%s', response.data.count);
                        if (response.data.remaining !== undefined) {
                            message += ' (' + str('remaining', '%s notifications remaining').replace('%s', response.data.remaining) + ')';
                        }
                        self.showNotice(message, 'success');
                    } else {
                        self.showNotice(response.data.message || str('clear_failed', 'Failed to clear notifications.'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotice(str('error_generic', 'An error occurred:') + ' ' + error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + str('dismiss_notice', 'Dismiss this notice.') + '</span></button></div>');

            $('.wrap h1').first().after($notice);

            // Trigger WordPress notice dismiss handler
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });

            // Auto dismiss success after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $notice.find('.notice-dismiss').trigger('click');
                }, 5000);
            }
        }
    };

    // Initialize when ready
    $(document).ready(function() {
        BPFNAdmin.init();
    });

    // Expose to global scope for external access
    window.BPFNAdmin = BPFNAdmin;

})(jQuery, window, document);
