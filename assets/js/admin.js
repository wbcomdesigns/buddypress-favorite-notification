/**
 * BuddyPress Favorite Notification - Admin Scripts
 * Version: 1.2.3
 */

(function($, window, document) {
    'use strict';

    // Check if bpfnAdmin is defined
    if (typeof bpfnAdmin === 'undefined') {
        return;
    }

    var i18n = (bpfnAdmin && bpfnAdmin.strings) || {};

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
            this.initTabs();
            this.initTooltips();
            this.initColorPicker();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Test notification button
            $('#bpfn-send-test-notification').on('click', function(e) {
                e.preventDefault();
                self.sendTestNotification($(this));
            });
            
            // Test email button
            $('#bpfn-send-test-email').on('click', function(e) {
                e.preventDefault();
                self.sendTestEmail($(this));
            });
            
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
            
            // Repair tables
            $('#bpfn-repair-tables').on('click', function(e) {
                e.preventDefault();
                self.repairTables($(this));
            });
            
            // Bulk update
            $('#bpfn-bulk-update').on('click', function(e) {
                e.preventDefault();
                self.bulkUpdateSettings($(this));
            });
            
            // Export settings
            $('#bpfn-export-settings').on('click', function(e) {
                e.preventDefault();
                self.exportSettings($(this));
            });
            
            // Import settings
            $('#bpfn-import-settings-file').on('change', function(e) {
                self.importSettings($(this));
            });
            
            // Run diagnostics
            $('#bpfn-run-diagnostics').on('click', function(e) {
                e.preventDefault();
                self.runDiagnostics($(this));
            });
            
            // Dismiss notices
            $(document).on('click', '.bpfn-dismiss-notice', function(e) {
                e.preventDefault();
                self.dismissNotice($(this));
            });
            
            // Settings form validation
            $('#bpfn-settings-form').on('submit', function(e) {
                return self.validateSettings($(this));
            });
            
            // Toggle all checkboxes
            $('.bpfn-toggle-all').on('change', function() {
                var $checkboxes = $(this).closest('table').find('tbody input[type="checkbox"]');
                $checkboxes.prop('checked', $(this).prop('checked'));
            });
            
            // Live preview
            $('input[name="bpfn_options[primary_color]"]').on('change', function() {
                self.updateLivePreview();
            });
        },

        /**
         * Send test notification
         */
        sendTestNotification: function($button) {
            var self = this;
            var $result = $('#bpfn-test-result');
            var originalText = $button.text();
            
            // Disable button and show loading
            $button.prop('disabled', true);
            $button.text(bpfnAdmin.strings.testing);
            
            // Clear previous results
            $result.removeClass('success error').hide();
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_send_test_notification',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').html('<strong>' + bpfnAdmin.strings.test_success + '</strong>').show();
                        
                        // Show notification types sent
                        if (response.data && response.data.details) {
							var details = '<ul style="margin-top: 10px; margin-bottom: 0;">';
							if (response.data.details.web) {
								details += '<li>✓ Web notification created</li>';
							}
							if (response.data.details.email) {
								details += '<li>✓ Email sent to ' + response.data.details.email + '</li>';
							}
							if (response.data.details.realtime) {
								details += '<li>✓ Real-time notification queued</li>';
							}
							details += '</ul>';
							$result.append(details);
						}
                    } else {
                        $result.addClass('error').text(response.data.message || bpfnAdmin.strings.test_error).show();
                    }
                },
                error: function(xhr, status, error) {
                    $result.addClass('error').text(bpfnAdmin.strings.test_error + ' (' + error + ')').show();
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Send test email
         */
        sendTestEmail: function($button) {
            var self = this;
            var $container = $button.parent();
            var originalText = $button.text();
            var emailType = $('#bpfn-test-email-type').val();
            
            // Disable button and show loading
            $button.prop('disabled', true);
            $button.text('Sending...');
            
            // Remove any previous messages
            $container.find('.bpfn-message').remove();
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_send_test_email',
                    nonce: bpfnAdmin.nonce,
                    type: emailType
                },
                success: function(response) {
                    if (response.success) {
                        $container.append('<div class="bpfn-message success" style="margin-top: 10px; color: green;">' + response.data.message + '</div>');
                    } else {
                        $container.append('<div class="bpfn-message error" style="margin-top: 10px; color: red;">' + (response.data.message || 'Failed to send test email') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $container.append('<div class="bpfn-message error" style="margin-top: 10px; color: red;">Error: ' + error + '</div>');
                    console.error('Test email error:', xhr.responseText);
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                    
                    // Auto-remove message after 5 seconds
                    setTimeout(function() {
                        $container.find('.bpfn-message').fadeOut();
                    }, 5000);
                }
            });
        },

        /**
         * Migrate favorites
         */
        migrateFavorites: function($button) {
            var self = this;
            bpfnConfirm({
                title: i18n.confirm_migrate_title || 'Run migration?',
                message: i18n.confirm_migrate || 'This will migrate all existing favorites to the new optimized table. Continue?'
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
            $button.html('<span class="dashicons dashicons-update spin"></span> Starting migration...');
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
                        $result.html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Migration failed.') + '</p></div>');
                        $button.prop('disabled', false);
                        $button.text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="notice notice-error inline"><p>An error occurred: ' + error + '</p></div>');
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
                        $('.bpfn-progress-text').text(progress.percent + '% (' + progress.users_processed + '/' + progress.total_users + ' users)');

                        if (progress.status === 'completed') {
                            // Migration complete
                            $result.html('<div class="notice notice-success inline">' +
                                '<p>Migration completed! Processed ' + progress.users_processed + ' users and added ' + progress.favorites_added + ' favorites.</p>' +
                            '</div>');
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
                            $result.html('<div class="notice notice-error inline"><p>Migration ' + progress.status + '</p></div>');
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
                title: i18n.confirm_clear_title || 'Clear old notifications?',
                message: i18n.confirm_clear_old || 'Are you sure you want to clear all read notifications older than 30 days? This cannot be undone.',
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
            $button.html('<span class="bpfn-spinner"></span> Clearing...');
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_clear_old_notifications',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = 'Successfully cleared ' + response.data.count + ' old notifications.';
                        if (response.data.remaining !== undefined) {
                            message += ' (' + response.data.remaining + ' notifications remaining)';
                        }
                        self.showNotice(message, 'success');
                        
                        // Refresh stats if available
                        if (self.refreshStats) {
                            self.refreshStats();
                        }
                    } else {
                        self.showNotice(response.data.message || 'Failed to clear notifications.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotice('An error occurred: ' + error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Repair tables
         */
        repairTables: function($button) {
            var self = this;
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Repairing...');
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_repair_tables',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message || 'Tables repaired successfully!', 'success');
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        self.showNotice(response.data.message || 'Failed to repair tables.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotice('Error: ' + error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Bulk update settings
         */
        bulkUpdateSettings: function($button) {
            var self = this;
            bpfnConfirm({
                title: i18n.confirm_bulk_title || 'Update all users?',
                message: i18n.confirm_bulk || 'This will update notification settings for all users. Continue?'
            }).then(function(ok) {
                if (ok) {
                    self.runBulkUpdateSettings($button);
                }
            });
        },

        /**
         * Run bulk settings update (after confirm).
         */
        runBulkUpdateSettings: function($button) {
            var self = this;
            var originalText = $button.text();

            $button.prop('disabled', true).text('Updating...');
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_bulk_update_settings',
                    nonce: bpfnAdmin.nonce,
                    web: $('#bpfn-bulk-web').is(':checked') ? 1 : 0,
                    email: $('#bpfn-bulk-email').is(':checked') ? 1 : 0,
                    realtime: $('#bpfn-bulk-realtime').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                    } else {
                        self.showNotice(response.data.message || 'Failed to update settings', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotice('Error: ' + error, 'error');
                    console.error('Bulk update error:', xhr.responseText);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Export settings
         */
        exportSettings: function($button) {
            var self = this;
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Preparing export...');
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_export_settings',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Create download link
                        var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                        var url = window.URL.createObjectURL(blob);
                        var filename = 'bpfn-settings-' + self.getDateString() + '.json';
                        var a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        self.showNotice('Settings exported successfully!', 'success');
                    } else {
                        self.showNotice('Failed to export settings.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotice('Export failed: ' + error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Import settings
         */
        importSettings: function($input) {
            var self = this;
            var file = $input[0].files[0];
            
            if (!file) {
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var settings = JSON.parse(e.target.result);
                    
                    $.ajax({
                        url: bpfnAdmin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'bpfn_import_settings',
                            nonce: bpfnAdmin.nonce,
                            settings: JSON.stringify(settings)
                        },
                        success: function(response) {
                            if (response.success) {
                                self.showNotice('Settings imported successfully. Page will reload...', 'success');
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                self.showNotice(response.data.message || 'Failed to import settings.', 'error');
                            }
                        },
                        error: function() {
                            self.showNotice('An error occurred while importing settings.', 'error');
                        }
                    });
                } catch (error) {
                    self.showNotice('Invalid settings file format.', 'error');
                }
            };
            reader.readAsText(file);
            
            // Reset input
            $input.val('');
        },

        /**
         * Run diagnostics
         */
        runDiagnostics: function($button) {
            var self = this;
            var $results = $('#bpfn-diagnostics-results');
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Running diagnostics...');
            $results.html('<div class="bpfn-loading">Running system checks...</div>');
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_run_diagnostics',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.displayDiagnostics(response.data, $results);
                    } else {
                        $results.html('<div class="notice notice-error"><p>Failed to run diagnostics.</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>An error occurred while running diagnostics.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Display diagnostic results
         */
        displayDiagnostics: function(data, $container) {
            var html = '<div class="bpfn-diagnostics-report">';
            
            // System info
            html += '<h3>System Information</h3>';
            html += '<table class="widefat striped">';
            html += '<tr><td><strong>PHP Version:</strong></td><td>' + data.php_version + '</td></tr>';
            html += '<tr><td><strong>WordPress Version:</strong></td><td>' + data.wp_version + '</td></tr>';
            html += '<tr><td><strong>BuddyPress Version:</strong></td><td>' + data.bp_version + '</td></tr>';
            html += '<tr><td><strong>Plugin Version:</strong></td><td>' + data.plugin_version + '</td></tr>';
            html += '</table>';
            
            // Database info
            html += '<h3>Database Status</h3>';
            html += '<table class="widefat striped">';
            html += '<tr><td><strong>Notifications Table:</strong></td><td>' + (data.tables.notifications ? '<span style="color:green;">✓ Exists</span>' : '<span style="color:red;">✗ Missing</span>') + '</td></tr>';
            html += '<tr><td><strong>Preferences Table:</strong></td><td>' + (data.tables.preferences ? '<span style="color:green;">✓ Exists</span>' : '<span style="color:red;">✗ Missing</span>') + '</td></tr>';
            html += '<tr><td><strong>Total Notifications:</strong></td><td>' + data.stats.total_notifications + '</td></tr>';
            html += '<tr><td><strong>Unread Notifications:</strong></td><td>' + data.stats.unread_notifications + '</td></tr>';
            html += '</table>';
            
            // Component status
            html += '<h3>Component Status</h3>';
            html += '<table class="widefat striped">';
            $.each(data.components, function(component, status) {
                var statusText = status ? '<span style="color: green;">✓ Active</span>' : '<span style="color: red;">✗ Inactive</span>';
                html += '<tr><td><strong>' + component.charAt(0).toUpperCase() + component.slice(1) + ':</strong></td><td>' + statusText + '</td></tr>';
            });
            html += '</table>';
            
            html += '</div>';
            
            $container.html(html);
        },

        /**
         * Dismiss notice
         */
        dismissNotice: function($button) {
            var $notice = $button.closest('.notice');
            var noticeId = $notice.data('notice-id');
            
            $notice.fadeOut();
            
            if (noticeId) {
                $.ajax({
                    url: bpfnAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bpfn_dismiss_notice',
                        nonce: bpfnAdmin.nonce,
                        notice_id: noticeId
                    }
                });
            }
        },

        /**
         * Validate settings form
         */
        validateSettings: function($form) {
            var valid = true;
            var errors = [];
            
            // Validate number inputs
            $form.find('input[type="number"]').each(function() {
                var $input = $(this);
                var val = parseInt($input.val());
                var min = parseInt($input.attr('min'));
                var max = parseInt($input.attr('max'));
                
                if (isNaN(val) || val < min || val > max) {
                    valid = false;
                    errors.push($input.attr('name') + ' must be between ' + min + ' and ' + max);
                    $input.addClass('error');
                } else {
                    $input.removeClass('error');
                }
            });
            
            // Validate required fields
            $form.find('[required]').each(function() {
                var $input = $(this);
                if (!$input.val()) {
                    valid = false;
                    errors.push($input.attr('name') + ' is required');
                    $input.addClass('error');
                } else {
                    $input.removeClass('error');
                }
            });
            
            if (!valid) {
                this.showNotice('Please fix the following errors:<br>' + errors.join('<br>'), 'error');
                return false;
            }
            
            return true;
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            $('.bpfn-tab').on('click', function(e) {
                e.preventDefault();
                
                var $tab = $(this);
                var target = $tab.data('tab');
                
                // Update active states
                $('.bpfn-tab').removeClass('active');
                $tab.addClass('active');
                
                // Show/hide content
                $('.bpfn-tab-content').removeClass('active');
                $('#' + target).addClass('active');
                
                // Update URL
                if (history.pushState) {
                    var url = new URL(window.location);
                    url.searchParams.set('tab', target);
                    history.pushState({tab: target}, '', url);
                }
            });
            
            // Handle browser back/forward
            window.addEventListener('popstate', function(e) {
                if (e.state && e.state.tab) {
                    $('.bpfn-tab[data-tab="' + e.state.tab + '"]').trigger('click');
                }
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.bpfn-tooltip').each(function() {
                var $el = $(this);
                var content = $el.data('tooltip');
                
                $el.on('mouseenter', function() {
                    var $tooltip = $('<div class="bpfn-tooltip-content">' + content + '</div>');
                    $('body').append($tooltip);
                    
                    var offset = $el.offset();
                    $tooltip.css({
                        top: offset.top - $tooltip.outerHeight() - 10,
                        left: offset.left + ($el.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    });
                }).on('mouseleave', function() {
                    $('.bpfn-tooltip-content').remove();
                });
            });
        },

        /**
         * Initialize color picker
         */
        initColorPicker: function() {
            if ($.fn.wpColorPicker) {
                $('.bpfn-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        $(event.target).trigger('change');
                    }
                });
            }
        },

        /**
         * Update live preview
         */
        updateLivePreview: function() {
            var primaryColor = $('input[name="bpfn_options[primary_color]"]').val();
            var $preview = $('#bpfn-preview');
            
            if ($preview.length) {
                $preview.find('.bpfn-preview-primary').css('color', primaryColor);
                $preview.find('.bpfn-preview-bg').css('background-color', primaryColor);
            }
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
            
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
        },

        /**
         * Get formatted date string
         */
        getDateString: function() {
            var date = new Date();
            var year = date.getFullYear();
            var month = ('0' + (date.getMonth() + 1)).slice(-2);
            var day = ('0' + date.getDate()).slice(-2);
            var hours = ('0' + date.getHours()).slice(-2);
            var minutes = ('0' + date.getMinutes()).slice(-2);
            
            return year + month + day + '-' + hours + minutes;
        },

        /**
         * Refresh statistics
         */
        refreshStats: function() {
            var self = this;
            
            // Refresh stats widget if present
            var $statsWidget = $('.bpfn-stats');
            if ($statsWidget.length) {
                $statsWidget.css('opacity', '0.5');
                
                $.ajax({
                    url: bpfnAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bpfn_get_stats',
                        nonce: bpfnAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Update notification count
                            if (response.data.total_notifications !== undefined) {
                                $statsWidget.find('.bpfn-stat-value').first().text(
                                    self.formatNumber(response.data.total_notifications)
                                );
                            }
                            // Update active users count
                            if (response.data.active_users !== undefined) {
                                $statsWidget.find('.bpfn-stat-value').last().text(
                                    self.formatNumber(response.data.active_users)
                                );
                            }
                        }
                    },
                    complete: function() {
                        $statsWidget.css('opacity', '1');
                    }
                });
            }
            
            // Refresh chart if present
            if (BPFNStats && BPFNStats.loadChart) {
                BPFNStats.loadChart();
            }
        },

        /**
         * Format number with thousands separator
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
    };

    /**
     * Statistics Chart Module
     */
    var BPFNStats = {
        init: function() {
            if ($('#bpfn-stats-chart').length && window.Chart) {
                this.loadChart();
            }
        },

        loadChart: function() {
            var self = this;
            
            // Show loading state
            $('#bpfn-stats-chart').parent().append('<div class="bpfn-loading-overlay"><span class="bpfn-spinner"></span></div>');
            
            $.ajax({
                url: bpfnAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_get_stats',
                    nonce: bpfnAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.chart) {
                        self.renderChart(response.data.chart);
                    }
                },
                complete: function() {
                    $('.bpfn-loading-overlay').remove();
                }
            });
        },

        renderChart: function(data) {
            var ctx = document.getElementById('bpfn-stats-chart').getContext('2d');
            
            // Destroy existing chart if present
            if (window.bpfnChart) {
                window.bpfnChart.destroy();
            }
            
            window.bpfnChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Notifications',
                        data: data.values,
                        borderColor: '#ff7b00',
                        backgroundColor: 'rgba(255, 123, 0, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    };

    // Initialize when ready
    $(document).ready(function() {
        BPFNAdmin.init();
        BPFNStats.init();
    });

    // Expose to global scope for external access
    window.BPFNAdmin = BPFNAdmin;
    window.BPFNStats = BPFNStats;

})(jQuery, window, document);