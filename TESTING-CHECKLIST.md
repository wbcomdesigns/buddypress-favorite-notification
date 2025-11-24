# BuddyPress Favorite Notification - Testing Checklist

## Test Card 1: Favorite Count Display (Facebook-style)

**Feature:** Facebook-style "who liked" display on activities

**Steps to Test:**
1. Log in as a regular user
2. Go to activity stream and find an activity with favorites
3. Verify display shows:
   - "❤ John Doe" (1 favorite)
   - "❤ John Doe and Jane Smith" (2 favorites)
   - "❤ John, Jane, and X others" (3+ favorites)
4. Click on a user name link - should navigate to their profile
5. If "View all" button appears (3+ favorites), click it
6. Verify modal opens showing all users who favorited
7. Log out and verify favorite display is completely hidden from guest users
8. Log back in, like an activity - verify display updates in real-time

**Expected Result:**
- ✅ Display shows correctly for 1, 2, 3+ favorites
- ✅ User profile links work
- ✅ Modal shows complete list with avatars
- ✅ Hidden from guest users
- ✅ Real-time AJAX updates work

---

## Test Card 2: Admin Dashboard - Overview Section

**Feature:** Admin analytics dashboard with statistics

**Steps to Test:**
1. Log in as administrator
2. Navigate to **BP Favorites → Dashboard**
3. Verify 4 stat cards display correctly:
   - Total Favorites (all time)
   - Notifications Sent (all time)
   - Active Users (last 7 days)
   - Most Liked Activity
4. Verify stats show accurate numbers from database
5. Check "Recent Favorites (Last 7 Days)" table displays recent activity
6. Click activity links in table - should open activity page in new tab
7. Test responsive layout on mobile/tablet devices

**Expected Result:**
- ✅ All 4 stat cards show correct data
- ✅ Recent Favorites table displays properly
- ✅ Activity links work (open in new tab)
- ✅ Mobile responsive layout
- ✅ Trending indicators show (+X in last 7 days)

---

## Test Card 3: Trending Analytics (7 Days)

**Feature:** Top 10 most favorited activities in last 7 days

**Steps to Test:**
1. Go to **BP Favorites → Dashboard**
2. Scroll to "Trending Activities (Last 7 Days)" section
3. Verify table columns display:
   - Rank (#1, #2, #3, etc.)
   - Activity (clickable link)
   - Favorites (blue badge with count)
   - Author name
   - Preview (first 100 characters)
4. Verify ranking is correct (highest favorite count first)
5. Click activity links - should open in new tab
6. Verify preview text is truncated with "..."
7. If no data, verify "No trending activities in the last 7 days" message

**Expected Result:**
- ✅ Top 10 trending activities shown
- ✅ Correct ranking order (descending)
- ✅ Blue badges for favorite counts
- ✅ All links work
- ✅ Preview text truncated at 100 chars

---

## Test Card 4: Trending Analytics (30 Days)

**Feature:** Monthly trending analysis side-by-side with 7-day

**Steps to Test:**
1. Go to **BP Favorites → Dashboard**
2. Locate "Trending Activities (Last 30 Days)" section
3. Verify table format matches 7-day trending structure
4. Verify data differs from 7-day (broader time range)
5. Test on mobile - sections should stack vertically
6. Test on desktop (1024px+) - should be side-by-side with 7-day
7. Verify responsive breakpoint at 1024px

**Expected Result:**
- ✅ Monthly trending shows correctly
- ✅ Different data than 7-day trending
- ✅ Responsive grid layout works
- ✅ Side-by-side on desktop
- ✅ Stacked on mobile/tablet

---

## Test Card 5: Favorite Migration Tool

**Feature:** Migrate existing favorites from user meta to optimized table

**Steps to Test:**
1. Go to **BP Favorites → Dashboard → Tools & Maintenance**
2. Check "Migrate Favorites" box shows current migration status
3. If migration needed, note user count and favorite count
4. Click "Run Migration" button
5. **For large sites (100+ users):**
   - Verify progress bar appears
   - Watch percentage increase (polls every 2 seconds)
   - Verify "X/Y users" counter updates
6. **For small sites (<100 users):**
   - Verify immediate completion message
7. After completion, verify success message with stats
8. Run query: `SELECT COUNT(*) FROM wp_bp_activity_favorites` - verify data migrated

**Expected Result:**
- ✅ Migration status detected correctly
- ✅ Progress bar works for large sites
- ✅ Batch processing prevents timeout
- ✅ Stats show users processed and favorites migrated
- ✅ All data migrated to new table

---

## Test Card 6: Database Cleanup (Automatic)

**Feature:** Monthly automatic cleanup of old read notifications

**Steps to Test:**
1. Go to **BP Favorites → Dashboard → Tools & Maintenance → Database Maintenance**
2. Verify "Automatic Cleanup" checkbox is displayed
3. Check the checkbox to enable automatic cleanup
4. Select retention period from dropdown: 7, 15, 30, 60, or 90 days
5. Click "Save Settings" button
6. Verify success message appears
7. Check "Next automatic cleanup" date is displayed
8. Manually trigger cron (optional): `wp cron event run bpfn_auto_cleanup_notifications`
9. Verify "Last automatic cleanup" stats appear after run
10. Confirm deleted count and remaining count are shown

**Expected Result:**
- ✅ Settings save successfully
- ✅ Next cleanup date calculated (30 days from now)
- ✅ Monthly cron job scheduled
- ✅ Only old READ notifications deleted
- ✅ Stats show accurate deleted/remaining counts

---

## Test Card 7: Database Cleanup (Manual)

**Feature:** On-demand cleanup without waiting for cron

**Steps to Test:**
1. Go to **BP Favorites → Dashboard → Tools & Maintenance → Database Maintenance**
2. Scroll to "Manual Cleanup" section
3. Click "Clear Old Notifications Now" button
4. Verify button shows loading state (spinner)
5. Verify success message displays with number of notifications deleted
6. Run query to confirm: `SELECT COUNT(*) FROM wp_bp_notifications WHERE component_name='favorite_notifier' AND is_new=0`
7. Verify recent unread notifications are preserved (is_new=1)
8. Check that cleanup respects retention period setting

**Expected Result:**
- ✅ Manual cleanup executes immediately
- ✅ Loading state shows during execution
- ✅ Success message with deletion count
- ✅ Only old read notifications deleted
- ✅ Unread notifications preserved
- ✅ Respects retention period setting

---

## Test Card 8: Admin UI Consistency

**Feature:** Unified admin interface with WordPress native styling

**Steps to Test:**
1. Go to WordPress admin sidebar
2. Verify only **ONE** "BP Favorites" menu item exists (no duplicate "Settings")
3. Click "BP Favorites" - verify single page loads with 3 sections:
   - Overview
   - Settings
   - Tools & Maintenance
4. Verify all postboxes have consistent padding (content not touching borders)
5. Verify section titles (h2.title) have left padding and bottom border
6. Verify all tables have clickable activity links that open in new tab
7. Check spacing between sections (40px margin)
8. Test on mobile - verify responsive layout stacks properly

**Expected Result:**
- ✅ Single unified menu item
- ✅ All sections on one scrollable page
- ✅ WordPress native postbox styling
- ✅ Consistent 16px padding throughout
- ✅ All activity links clickable
- ✅ Proper section spacing
- ✅ Mobile responsive

---

## Test Card 9: Performance (Large Site)

**Feature:** Optimized queries and caching for large sites

**Steps to Test:**
1. Test on site with 1000+ activities and 100+ users with favorites
2. Go to activity stream - measure initial page load time
3. Check favorite count displays load quickly (should use cached data)
4. Click "View all" on activity with 50+ favorites - modal should open instantly
5. Navigate to admin dashboard - measure page load time (should be <2 seconds)
6. Install Query Monitor plugin
7. Check database queries use indexes (wp_bp_activity_favorites table)
8. Run migration on 1000+ users - verify no PHP timeout errors
9. Check object cache hits for favorite counts (wp_cache_get)

**Expected Result:**
- ✅ Activity stream loads in <3 seconds
- ✅ Cached queries (5-min TTL) working
- ✅ Database indexes used (activity_id, user_id)
- ✅ No timeouts on large datasets
- ✅ Admin dashboard loads <2 seconds
- ✅ Migration handles 10k+ users

---

## Test Card 10: Guest User Restrictions

**Feature:** Privacy - hide favorite data from non-logged-in users

**Steps to Test:**
1. Log out completely (become guest user)
2. Browse activity stream as guest
3. Verify favorite count display is completely hidden (no ❤ or names)
4. Verify favorite/unfavorite buttons still visible (BuddyPress core feature)
5. Try accessing admin dashboard URL directly: `/wp-admin/admin.php?page=bpfn-dashboard`
6. Verify redirect to login or access denied
7. Log back in as subscriber role
8. Verify favorite display appears again
9. Test with different user roles: contributor, author, editor, admin

**Expected Result:**
- ✅ Guests cannot see favorite counts
- ✅ Guests cannot see who favorited
- ✅ Admin dashboard requires login + manage_options capability
- ✅ All logged-in users see favorite display
- ✅ Only admins can access dashboard
- ✅ Privacy maintained across all pages

---

## Quick Smoke Test (All Features)

**For rapid testing after updates:**

1. ✅ Log in → Activity stream → See favorite counts
2. ✅ Click "View all" → Modal opens
3. ✅ Admin Dashboard → All 3 sections load
4. ✅ Trending tables → Show data with links
5. ✅ Migration → Runs without errors
6. ✅ Cleanup → Manual cleanup works
7. ✅ Log out → Favorite counts hidden
8. ✅ Mobile view → Responsive layout works

---

## Test Environment Requirements

- WordPress 5.0+
- PHP 7.4+
- BuddyPress 5.0+
- Active theme supporting BuddyPress
- Multiple test users (10+ recommended)
- Test activities with varying favorite counts (0, 1, 2, 5, 10+)
- Browser: Chrome, Firefox, Safari
- Devices: Desktop (1920px), Tablet (768px), Mobile (375px)

---

## Reporting Issues

When reporting bugs, include:
1. Test card number
2. Step where issue occurred
3. Expected vs actual result
4. Browser/device information
5. Screenshots if applicable
6. Error messages from browser console
7. PHP error log entries (if any)
