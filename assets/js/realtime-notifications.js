/**
 * Real-time notifications using WordPress Heartbeat API
 */
(function($) {
    'use strict';
    
    var lastCheckedTime = 0;
    
    // Initialize
    $(document).ready(function() {
        // Create notification container if it doesn't exist
        if ($('#bpfn-realtime-notifications-container').length === 0) {
            $('body').append('<div id="bpfn-realtime-notifications-container"></div>');
        }
        
        // Initialize last checked time
        lastCheckedTime = Math.floor(Date.now() / 1000);
        console.log(lastCheckedTime);
    });
    
    // Hook into Heartbeat API
    $(document).on('heartbeat-send', function(event, data) {
        data.bpfn_realtime_check = {
            last_checked: lastCheckedTime,
            nonce: BPFNRealtimeData.nonce,
            
        };
        
    });
    
    // Process response from Heartbeat API
    $(document).on('heartbeat-tick', function(event, data) {
        console.log('wbcom_designs_tick');
        if (data.bpfn_realtime_notifications) {
            // Update last checked time
            lastCheckedTime = Math.floor(Date.now() / 1000);
            
            // Process new notifications
            if (data.bpfn_realtime_notifications.notifications && 
                data.bpfn_realtime_notifications.notifications.length > 0) {
                
                // Display each notification
                $.each(data.bpfn_realtime_notifications.notifications, function(index, notification) {
                    showNotification(notification);
                    console.log('wbcom_designs');
                });
                
                // Update toolbar notification count if needed
                if (typeof data.bpfn_realtime_notifications.count !== 'undefined') {
                    updateNotificationCount(data.bpfn_realtime_notifications.count);
                }
            }
        }
    });
    
    /**
     * Display a notification popup
     */
    function showNotification(notification) {
        var notificationHtml = 
            '<div class="bpfn-notification" data-id="' + notification.id + '">' +
                '<div class="bpfn-notification-header">' +
                    '<span class="bpfn-notification-title">' + BPFNRealtimeData.strings.newFavorite + '</span>' +
                    '<span class="bpfn-notification-close">' + BPFNRealtimeData.strings.close + '</span>' +
                '</div>' +
                '<div class="bpfn-notification-content">' +
                    '<div class="bpfn-notification-avatar">' + notification.avatar + '</div>' +
                    '<div class="bpfn-notification-message">' + notification.text + '</div>' +
                '</div>' +
                '<div class="bpfn-notification-actions">' +
                    '<a href="' + notification.link + '" class="bpfn-notification-action">' + 
                        BPFNRealtimeData.strings.viewActivity + 
                    '</a>' +
                '</div>' +
            '</div>';
        
        // Add notification to container
        $('#bpfn-realtime-notifications-container').append(notificationHtml);
        // Show notification with animation
        setTimeout(function() {
            $('.bpfn-notification[data-id="' + notification.id + '"]').addClass('show');
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.bpfn-notification[data-id="' + notification.id + '"]').removeClass('show');
                
                // Remove after animation completes
                setTimeout(function() {
                    $('.bpfn-notification[data-id="' + notification.id + '"]').remove();
                }, 500);
            }, 5000);
        }, 100);
    }
    
    /**
     * Update notification count in the BuddyPress toolbar
     */
    function updateNotificationCount(count) {
        var $notificationCount = $('#wp-admin-bar-my-account-notifications .count');
        
        if ($notificationCount.length) {
            $notificationCount.text(count);
        } else {
            $('#wp-admin-bar-my-account-notifications a').append('<span class="count">' + count + '</span>');
        }
    }
    
    // Handle close button click
    $(document).on('click', '.bpfn-notification-close', function() {
        var $notification = $(this).closest('.bpfn-notification');
        $notification.removeClass('show');
        
        setTimeout(function() {
            $notification.remove();
        }, 500);
    });
    
})(jQuery);