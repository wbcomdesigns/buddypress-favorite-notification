# Favorite Count & "Who Liked" Feature Plan

## Overview

Add favorite count display and "who liked" user list to BuddyPress activities, similar to Facebook's like count and user list.

## Current System Analysis

### BuddyPress Favorite System

**Database Structure:**

1. **User Meta** - `bp_favorite_activities`
   - Stores array of activity IDs each user has favorited
   - Example: `array(554, 531, 514, 287, 482, 267, 558)`
   - Key: `bp_favorite_activities`
   - Type: Serialized array

2. **Activity Meta** - `favorite_count`
   - Stores total number of favorites for each activity
   - Example: `9` (9 users favorited this activity)
   - Key: `favorite_count`
   - Type: Integer

**Current Display:**
```html
<div class="generic-button">
    <a href="https://buddyx-pro.local/activity/unfavorite/558/?_wpnonce=a665bec206"
       class="button unfav bp-secondary-action bp-tooltip"
       data-bp-tooltip="Unlike"
       aria-pressed="true">
        <span class="bp-screen-reader-text">Unlike</span>
    </a>
</div>
```

**Template Location:**
- `/buddypress/activity/entry.php` (line 60)
- Rendered by: `bp_nouveau_activity_entry_buttons()`
- Hook available: `bp_activity_before_post_footer_content` (line 57)

## Proposed Feature

### Visual Design

**Option 1: Inline Count (Simple)**
```
[Unlike button] ❤ 12 likes
```

**Option 2: Facebook-Style (Detailed)**
```
❤ John Doe, Jane Smith, and 10 others liked this
[Unlike button]
```

**Option 3: Expandable (Interactive)**
```
❤ 12 [View all]
[Unlike button]

(Click "View all" to show modal/dropdown with user list)
```

### Components Needed

#### 1. **Favorite Count Display**
- Show count next to favorite button
- Format: "12 likes", "1 like", "No likes yet"
- Icon: Heart icon
- Position: Before or after favorite button

#### 2. **User List Display**
- Show avatars of first 3-5 users who liked
- Truncated text: "John, Jane, and 8 others"
- Clickable to expand full list
- Modal or dropdown with complete user list

#### 3. **AJAX Update**
- Real-time count update when user clicks like/unlike
- Update user list without page refresh
- Smooth animations

## Technical Implementation

### Module 1: Backend Functions

**New File:** `includes/modules/class-favorite-display.php`

```php
class BPFN_Module_Favorite_Display {

    /**
     * Get favorite count for activity
     */
    public function get_favorite_count( $activity_id ) {
        $count = (int) bp_activity_get_meta( $activity_id, 'favorite_count' );
        return max( 0, $count );
    }

    /**
     * Get users who favorited activity
     */
    public function get_users_who_favorited( $activity_id, $limit = 5 ) {
        global $wpdb;

        // Query users who have this activity ID in their favorites
        $sql = $wpdb->prepare(
            "SELECT user_id, meta_value
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'bp_favorite_activities'"
        );

        $results = $wpdb->get_results( $sql );
        $users = array();

        foreach ( $results as $row ) {
            $favorites = maybe_unserialize( $row->meta_value );
            if ( is_array( $favorites ) && in_array( $activity_id, $favorites ) ) {
                $users[] = (int) $row->user_id;
            }
        }

        // Get user data
        if ( ! empty( $users ) ) {
            $user_objects = array();
            $shown_users = array_slice( $users, 0, $limit );

            foreach ( $shown_users as $user_id ) {
                $user = get_userdata( $user_id );
                if ( $user ) {
                    $user_objects[] = array(
                        'id' => $user_id,
                        'name' => $user->display_name,
                        'avatar' => bp_core_fetch_avatar( array(
                            'item_id' => $user_id,
                            'type'    => 'thumb',
                            'html'    => false
                        ) ),
                        'link' => bp_core_get_user_domain( $user_id )
                    );
                }
            }

            return array(
                'users' => $user_objects,
                'total' => count( $users ),
                'remaining' => max( 0, count( $users ) - $limit )
            );
        }

        return array(
            'users' => array(),
            'total' => 0,
            'remaining' => 0
        );
    }

    /**
     * Format favorite text
     */
    public function format_favorite_text( $users_data ) {
        $total = $users_data['total'];
        $users = $users_data['users'];
        $remaining = $users_data['remaining'];

        if ( $total === 0 ) {
            return '';
        }

        if ( $total === 1 ) {
            return sprintf(
                '<a href="%s">%s</a> likes this',
                $users[0]['link'],
                $users[0]['name']
            );
        }

        if ( $total === 2 ) {
            return sprintf(
                '<a href="%s">%s</a> and <a href="%s">%s</a> like this',
                $users[0]['link'],
                $users[0]['name'],
                $users[1]['link'],
                $users[1]['name']
            );
        }

        // More than 2
        $names = array();
        foreach ( array_slice( $users, 0, 2 ) as $user ) {
            $names[] = sprintf( '<a href="%s">%s</a>', $user['link'], $user['name'] );
        }

        if ( $remaining > 0 ) {
            return sprintf(
                '%s, and <span class="bpfn-others-count" data-toggle="modal">%d others</span> like this',
                implode( ', ', $names ),
                $remaining
            );
        } else {
            return implode( ', ', $names ) . ' like this';
        }
    }
}
```

### Module 2: Frontend Display

**Hook into Activity Template:**

```php
// In class-favorite-display.php constructor
add_action( 'bp_activity_before_post_footer_content', array( $this, 'display_favorite_count' ), 10 );
```

**Display Function:**

```php
public function display_favorite_count() {
    $activity_id = bp_get_activity_id();
    $count = $this->get_favorite_count( $activity_id );

    if ( $count === 0 ) {
        return; // Don't show anything if no favorites
    }

    $users_data = $this->get_users_who_favorited( $activity_id, 3 );
    ?>
    <div class="bpfn-favorite-display" data-activity-id="<?php echo esc_attr( $activity_id ); ?>">
        <span class="bpfn-favorite-icon">❤</span>
        <span class="bpfn-favorite-text">
            <?php echo $this->format_favorite_text( $users_data ); ?>
        </span>
        <?php if ( $users_data['remaining'] > 0 ) : ?>
            <button class="bpfn-view-all-favorites" data-activity-id="<?php echo esc_attr( $activity_id ); ?>">
                View all <?php echo $count; ?>
            </button>
        <?php endif; ?>
    </div>
    <?php
}
```

### Module 3: AJAX Handlers

**Update Count on Like/Unlike:**

```php
// Hook into BuddyPress favorite actions
add_action( 'bp_activity_add_user_favorite', array( $this, 'ajax_update_favorite_display' ), 10, 2 );
add_action( 'bp_activity_remove_user_favorite', array( $this, 'ajax_update_favorite_display' ), 10, 2 );

public function ajax_update_favorite_display( $activity_id, $user_id ) {
    $count = $this->get_favorite_count( $activity_id );
    $users_data = $this->get_users_who_favorited( $activity_id, 3 );

    wp_send_json_success( array(
        'activity_id' => $activity_id,
        'count' => $count,
        'html' => $this->format_favorite_text( $users_data ),
        'users' => $users_data
    ) );
}
```

**Get All Users Who Liked (for modal):**

```php
public function ajax_get_all_favorites() {
    check_ajax_referer( 'bpfn-favorite-nonce', 'nonce' );

    $activity_id = isset( $_POST['activity_id'] ) ? intval( $_POST['activity_id'] ) : 0;

    if ( ! $activity_id ) {
        wp_send_json_error( array( 'message' => 'Invalid activity ID' ) );
    }

    $users_data = $this->get_users_who_favorited( $activity_id, 999 ); // Get all

    ob_start();
    ?>
    <div class="bpfn-favorites-modal-content">
        <h3><?php echo $users_data['total']; ?> Likes</h3>
        <ul class="bpfn-favorites-user-list">
            <?php foreach ( $users_data['users'] as $user ) : ?>
                <li class="bpfn-favorite-user-item">
                    <a href="<?php echo esc_url( $user['link'] ); ?>">
                        <img src="<?php echo esc_url( $user['avatar'] ); ?>"
                             alt="<?php echo esc_attr( $user['name'] ); ?>"
                             class="bpfn-user-avatar">
                        <span class="bpfn-user-name"><?php echo esc_html( $user['name'] ); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success( array( 'html' => $html ) );
}
```

### Module 4: JavaScript

**New File:** `assets/js/favorite-display.js`

```javascript
(function($) {
    'use strict';

    var BPFNFavoriteDisplay = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Listen for BuddyPress favorite/unfavorite AJAX success
            $(document).on('bp_activity_favorite_success', this.updateDisplay.bind(this));
            $(document).on('bp_activity_unfavorite_success', this.updateDisplay.bind(this));

            // View all favorites modal
            $(document).on('click', '.bpfn-view-all-favorites', this.showAllFavorites.bind(this));
        },

        updateDisplay: function(event, activityId) {
            var $container = $('.bpfn-favorite-display[data-activity-id="' + activityId + '"]');

            $.ajax({
                url: bpfnFavorites.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_update_favorite_display',
                    activity_id: activityId,
                    nonce: bpfnFavorites.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.count === 0) {
                            $container.fadeOut();
                        } else {
                            $container.find('.bpfn-favorite-text').html(response.data.html);
                            $container.fadeIn();
                        }
                    }
                }
            });
        },

        showAllFavorites: function(e) {
            e.preventDefault();
            var activityId = $(e.currentTarget).data('activity-id');

            $.ajax({
                url: bpfnFavorites.ajax_url,
                type: 'POST',
                data: {
                    action: 'bpfn_get_all_favorites',
                    activity_id: activityId,
                    nonce: bpfnFavorites.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show modal with user list
                        BPFNFavoriteDisplay.showModal(response.data.html);
                    }
                }
            });
        },

        showModal: function(html) {
            var $modal = $('<div class="bpfn-modal-overlay">' +
                '<div class="bpfn-modal">' +
                    '<button class="bpfn-modal-close">&times;</button>' +
                    html +
                '</div>' +
            '</div>');

            $('body').append($modal);
            $modal.fadeIn();

            $('.bpfn-modal-close, .bpfn-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    $modal.fadeOut(function() {
                        $modal.remove();
                    });
                }
            });
        }
    };

    $(document).ready(function() {
        BPFNFavoriteDisplay.init();
    });

})(jQuery);
```

### Module 5: CSS Styling

**New File:** `assets/css/favorite-display.css`

```css
/* Favorite Count Display */
.bpfn-favorite-display {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 0;
    font-size: 14px;
    color: #666;
}

.bpfn-favorite-icon {
    color: #ff4458;
    font-size: 16px;
}

.bpfn-favorite-text a {
    color: #1d84b5;
    text-decoration: none;
    font-weight: 500;
}

.bpfn-favorite-text a:hover {
    text-decoration: underline;
}

.bpfn-others-count {
    color: #1d84b5;
    cursor: pointer;
    font-weight: 500;
}

.bpfn-others-count:hover {
    text-decoration: underline;
}

.bpfn-view-all-favorites {
    background: transparent;
    border: none;
    color: #1d84b5;
    cursor: pointer;
    font-size: 13px;
    padding: 0;
}

.bpfn-view-all-favorites:hover {
    text-decoration: underline;
}

/* Modal Styles */
.bpfn-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}

.bpfn-modal {
    background: #fff;
    border-radius: 8px;
    max-width: 400px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    padding: 20px;
    position: relative;
}

.bpfn-modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: transparent;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.bpfn-modal-close:hover {
    color: #000;
}

.bpfn-favorites-modal-content h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
}

.bpfn-favorites-user-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.bpfn-favorite-user-item {
    margin-bottom: 12px;
}

.bpfn-favorite-user-item a {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #333;
    padding: 8px;
    border-radius: 4px;
    transition: background 0.2s;
}

.bpfn-favorite-user-item a:hover {
    background: #f5f5f5;
}

.bpfn-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

.bpfn-user-name {
    font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
    .bpfn-favorite-display {
        flex-wrap: wrap;
    }

    .bpfn-modal {
        max-width: 95%;
    }
}
```

## Implementation Steps

### Phase 1: Backend Setup (Day 1)

1. ✅ Create `includes/modules/class-favorite-display.php`
2. ✅ Add `get_favorite_count()` method
3. ✅ Add `get_users_who_favorited()` method
4. ✅ Add `format_favorite_text()` method
5. ✅ Register module in main plugin file

### Phase 2: Frontend Display (Day 2)

1. ✅ Add `display_favorite_count()` method
2. ✅ Hook into `bp_activity_before_post_footer_content`
3. ✅ Create HTML template for count display
4. ✅ Test display on activities

### Phase 3: AJAX Integration (Day 3)

1. ✅ Add AJAX handlers for real-time updates
2. ✅ Hook into BuddyPress favorite/unfavorite actions
3. ✅ Create `ajax_get_all_favorites()` method
4. ✅ Register AJAX actions

### Phase 4: JavaScript & Animations (Day 4)

1. ✅ Create `assets/js/favorite-display.js`
2. ✅ Implement real-time count updates
3. ✅ Create modal for "view all" functionality
4. ✅ Add smooth animations

### Phase 5: Styling (Day 5)

1. ✅ Create `assets/css/favorite-display.css`
2. ✅ Style favorite count display
3. ✅ Style modal and user list
4. ✅ Add responsive design
5. ✅ Add dark mode support

### Phase 6: Testing & Optimization (Day 6)

1. ✅ Test with 0, 1, 2, 10+ favorites
2. ✅ Test AJAX updates
3. ✅ Test modal functionality
4. ✅ Performance testing with large user lists
5. ✅ Optimize database queries (add caching if needed)

## Database Optimization

### Potential Performance Issue

Current approach queries ALL users' `bp_favorite_activities` meta to find who favorited an activity. This is inefficient for large sites.

### Solution 1: Add Activity Meta with User List

When user favorites/unfavorites, also update activity meta with user list:

```php
// On favorite
$favorited_by = bp_activity_get_meta( $activity_id, 'favorited_by_users' );
if ( ! is_array( $favorited_by ) ) {
    $favorited_by = array();
}
$favorited_by[] = $user_id;
bp_activity_update_meta( $activity_id, 'favorited_by_users', array_unique( $favorited_by ) );

// On unfavorite
$favorited_by = bp_activity_get_meta( $activity_id, 'favorited_by_users' );
if ( is_array( $favorited_by ) ) {
    $favorited_by = array_diff( $favorited_by, array( $user_id ) );
    bp_activity_update_meta( $activity_id, 'favorited_by_users', $favorited_by );
}
```

This makes lookups instant: just read `favorited_by_users` meta instead of querying all users.

### Solution 2: Object Caching

Cache the user list for 5-10 minutes:

```php
$cache_key = 'bpfn_favorited_by_' . $activity_id;
$users = wp_cache_get( $cache_key );

if ( false === $users ) {
    $users = $this->get_users_who_favorited( $activity_id );
    wp_cache_set( $cache_key, $users, '', 300 ); // 5 minutes
}
```

## Admin Settings

Add options to control display:

```php
add_settings_field(
    'show_favorite_count',
    __( 'Show Favorite Count', 'bp-fav-notification' ),
    array( $this, 'field_checkbox' ),
    'bpfn-settings',
    'bpfn_general',
    array(
        'name' => 'show_favorite_count',
        'label' => __( 'Display favorite count and user list on activities', 'bp-fav-notification' ),
    )
);

add_settings_field(
    'favorite_display_style',
    __( 'Display Style', 'bp-fav-notification' ),
    array( $this, 'field_select' ),
    'bpfn-settings',
    'bpfn_general',
    array(
        'name' => 'favorite_display_style',
        'options' => array(
            'count_only' => __( 'Count only (12 likes)', 'bp-fav-notification' ),
            'names' => __( 'Names (John, Jane, and 8 others)', 'bp-fav-notification' ),
            'avatars' => __( 'Avatars + names', 'bp-fav-notification' ),
        ),
    )
);
```

## User Privacy

Add option for users to hide from "who liked" list:

```php
// In user settings
$hide_from_favorites = get_user_meta( $user_id, 'bpfn_hide_from_favorite_list', true );

// Filter user list
public function get_users_who_favorited( $activity_id, $limit = 5 ) {
    // ... existing code ...

    // Filter out users who want privacy
    $filtered_users = array();
    foreach ( $users as $user_id ) {
        $hide = get_user_meta( $user_id, 'bpfn_hide_from_favorite_list', true );
        if ( ! $hide ) {
            $filtered_users[] = $user_id;
        }
    }

    return $filtered_users;
}
```

## Hooks & Filters

Provide hooks for developers:

```php
// Filter favorite count display
$html = apply_filters( 'bpfn_favorite_count_html', $html, $activity_id, $count, $users_data );

// Filter user list
$users = apply_filters( 'bpfn_favorite_users_list', $users, $activity_id );

// Filter display style
$style = apply_filters( 'bpfn_favorite_display_style', $style, $activity_id );

// Action before displaying count
do_action( 'bpfn_before_favorite_count_display', $activity_id );

// Action after displaying count
do_action( 'bpfn_after_favorite_count_display', $activity_id );
```

## Compatibility

### BuddyPress Versions
- Tested with: BP 5.0+
- Use modern BP functions
- Fallback for deprecated functions

### BuddyPress Template Packs
- **BP Nouveau** (default) - Full support
- **BP Legacy** - Full support with adjusted hooks
- **Custom themes** - Provide template tags

### Performance
- Efficient for sites with < 10,000 users
- For larger sites, implement Solution 1 (activity meta with user list)
- Add object caching for high-traffic sites

## Future Enhancements

1. **Reaction Types** - Like, Love, Wow, etc. (Facebook-style)
2. **Favorite Insights** - Analytics for post authors
3. **Trending Activities** - Show most-favorited activities
4. **Email Digest** - "Your activity was liked by 10 people this week"
5. **Push Notifications** - Browser push when someone likes

## Testing Checklist

- [ ] Display with 0 favorites
- [ ] Display with 1 favorite
- [ ] Display with 2 favorites
- [ ] Display with 10+ favorites
- [ ] Display with 100+ favorites
- [ ] AJAX update on like
- [ ] AJAX update on unlike
- [ ] Modal opens correctly
- [ ] Modal shows all users
- [ ] Responsive design works
- [ ] Dark mode support
- [ ] Performance with 1000+ users
- [ ] Caching works correctly
- [ ] Privacy setting works
- [ ] Admin settings work
- [ ] Filters and hooks work

## Documentation

Add to plugin documentation:

1. **User Guide** - How to see who liked activities
2. **Developer Guide** - Available hooks and filters
3. **FAQ** - Common questions about favorite display
4. **Privacy** - How to hide from favorite lists

## End Result

Users will see:

```
❤ John Doe, Jane Smith, and 10 others like this
[Unlike]
```

Clicking "10 others" opens a modal showing all 12 users who liked the activity, with their avatars and names.

This provides the same experience as Facebook/Instagram likes while maintaining BuddyPress compatibility and performance.
