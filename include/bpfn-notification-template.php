<?php 
/**
 * Template for rendering enhanced favorite notifications
 *
 * @param array $notification The notification data
 * @return string HTML for the notification
 */
function bpfn_render_enhanced_notification($notification) {
    // Return early if this is not a favorite notification
    if (empty($notification['notification_type']) || $notification['notification_type'] !== 'favorite') {
        return false;
    }
    
    $output = '<div class="bpfn-enhanced-notification">';
    
    // Add user avatar if available
    if (!empty($notification['user_avatar'])) {
        $output .= '<div class="bpfn-notification-avatar">';
        $output .= '<a href="' . esc_url($notification['user_link']) . '">' . $notification['user_avatar'] . '</a>';
        $output .= '</div>';
    }
    
    $output .= '<div class="bpfn-notification-content">';
    
    // Add notification text
    $output .= '<div class="bpfn-notification-text">' . $notification['text'] . '</div>';
    
    // Add activity excerpt if available
    if (!empty($notification['activity_excerpt'])) {
        $output .= '<div class="bpfn-activity-excerpt">';
        $output .= '<blockquote>' . $notification['activity_excerpt'] . '</blockquote>';
        $output .= '</div>';
    }
    
    // Add timestamp
    if (!empty($notification['timestamp'])) {
        $output .= '<div class="bpfn-notification-time">';
        $output .= '<time datetime="' . date('c', $notification['timestamp']) . '">';
        $output .= human_time_diff($notification['timestamp'], current_time('timestamp')) . ' ' . __('ago', 'bp-fav-notification');
        $output .= '</time>';
        $output .= '</div>';
    }
    
    // Add action buttons
    $output .= '<div class="bpfn-notification-actions">';
    $output .= '<a href="' . esc_url($notification['link']) . '" class="bpfn-view-activity button">';
    $output .= __('Views Activity', 'bp-fav-notification');
    $output .= '</a>';
    $output .= '</div>';
    
    $output .= '</div>'; // End notification content
    $output .= '</div>'; // End notification wrapper
    
    return $output;
}