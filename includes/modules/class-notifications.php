<?php
/**
 * Debug Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Module Class
 */
class BPFN_Module_Debug {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Always apply critical fixes
		$this->apply_fixes();
	}

	/**
	 * Apply fixes for notification issues
	 */
	private function apply_fixes() {
		// Fix 1: Ensure proper user display names
		add_filter( 'bp_core_get_user_displayname', array( $this, 'fix_user_displayname' ), 10, 2 );
		
		// Fix 2: Ensure component is properly initialized
		add_action( 'bp_init', array( $this, 'ensure_component_initialized' ), 5 );
		
		// Fix 3: Add filter to fix notification text
		add_filter( 'bpfn_notification_text', array( $this, 'fix_notification_text' ), 10, 5 );
		
		// Fix 4: Add filter to fix notification array data
		add_filter( 'bpfn_notification_array', array( $this, 'fix_notification_array' ), 10, 3 );
		
		// Fix 5: Add JavaScript fix for any missed cases
		add_action( 'wp_footer', array( $this, 'add_frontend_fixes' ) );
		
		// Fix 6: AJAX handler for getting proper user names
		add_action( 'wp_ajax_bpfn_get_user_display_name', array( $this, 'ajax_get_user_display_name' ) );
		add_action( 'wp_ajax_nopriv_bpfn_get_user_display_name', array( $this, 'ajax_get_user_display_name' ) );
	}

	/**
	 * Fix user display name
	 */
	public function fix_user_displayname( $display_name, $user_id ) {
		// Check if display name looks like "User X"
		if ( preg_match( '/^User\s+\d+$/', $display_name ) ) {
			// Get the real display name
			$user = get_userdata( $user_id );
			if ( $user ) {
				// Try display name first
				if ( ! empty( $user->display_name ) && ! preg_match( '/^User\s+\d+$/', $user->display_name ) ) {
					return $user->display_name;
				}
				
				// Then try first + last name
				if ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) {
					$name = trim( $user->first_name . ' ' . $user->last_name );
					if ( ! empty( $name ) ) {
						return $name;
					}
				}
				
				// Finally use login
				if ( ! empty( $user->user_login ) ) {
					return $user->user_login;
				}
			}
		}
		
		return $display_name;
	}

	/**
	 * Fix notification text
	 */
	public function fix_notification_text( $text, $item_id, $secondary_item_id, $total_items, $type ) {
		// Check if text contains "User X" pattern
		if ( preg_match( '/User\s+\d+/', $text ) ) {
			// Get the actual user name
			$user = get_userdata( $secondary_item_id );
			if ( $user ) {
				$proper_name = $this->get_proper_display_name( $user );
				
				// Replace the generic name with the proper name
				$text = preg_replace( '/User\s+\d+/', $proper_name, $text );
			}
		}
		
		return $text;
	}

	/**
	 * Fix notification array data
	 */
	public function fix_notification_array( $data, $activity, $secondary_item_id ) {
		// Fix user_name if it looks like "User X"
		if ( ! empty( $data['user_name'] ) && preg_match( '/^User\s+\d+$/', $data['user_name'] ) ) {
			$user = get_userdata( $secondary_item_id );
			if ( $user ) {
				$data['user_name'] = $this->get_proper_display_name( $user );
			}
		}
		
		return $data;
	}

	/**
	 * Get proper display name for a user
	 */
	private function get_proper_display_name( $user ) {
		// Try display name first
		if ( ! empty( $user->display_name ) && ! preg_match( '/^User\s+\d+$/', $user->display_name ) ) {
			return $user->display_name;
		}
		
		// Then try first + last name
		if ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) {
			$name = trim( $user->first_name . ' ' . $user->last_name );
			if ( ! empty( $name ) ) {
				return $name;
			}
		}
		
		// Finally use login
		if ( ! empty( $user->user_login ) ) {
			return $user->user_login;
		}
		
		// Fallback
		return __( 'Someone', 'bp-fav-notification' );
	}

	/**
	 * Ensure component is initialized
	 */
	public function ensure_component_initialized() {
		global $bp;
		
		// Check if our component is set up
		if ( ! isset( $bp->favorite_notifier ) ) {
			// Force initialization
			if ( function_exists( 'bpfn' ) ) {
				$plugin = bpfn();
				if ( method_exists( $plugin, 'setup_globals' ) ) {
					$plugin->setup_globals();
				}
			}
		}
	}

	/**
	 * Add frontend fixes
	 */
	public function add_frontend_fixes() {
		// Only on BuddyPress pages
		if ( ! is_buddypress() ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			// Fix any remaining "User X" display names
			function fixUserNames() {
				$('a:contains("User "), .notification-content:contains("User "), .bpfn-notification-text:contains("User ")').each(function() {
					var $elem = $(this);
					var text = $elem.text();
					var matches = text.match(/User (\d+)/g);
					
					if (matches) {
						matches.forEach(function(match) {
							var userId = match.replace('User ', '');
							
							// Check if we already have this user's name cached
							if (window.bpfnUserNames && window.bpfnUserNames[userId]) {
								var newText = text.replace(match, window.bpfnUserNames[userId]);
								$elem.text(newText);
							} else {
								// Fetch the user's real name
								$.post(ajaxurl, {
									action: 'bpfn_get_user_display_name',
									user_id: userId,
									nonce: '<?php echo wp_create_nonce( 'bpfn_get_user_name' ); ?>'
								}, function(response) {
									if (response.success && response.data.name) {
										// Cache the name
										window.bpfnUserNames = window.bpfnUserNames || {};
										window.bpfnUserNames[userId] = response.data.name;
										
										// Update all occurrences
										var newText = $elem.text().replace('User ' + userId, response.data.name);
										$elem.text(newText);
									}
								});
							}
						});
					}
				});
			}
			
			// Run on page load
			fixUserNames();
			
			// Run after AJAX updates
			$(document).ajaxComplete(function() {
				setTimeout(fixUserNames, 100);
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler to get user display names
	 */
	public function ajax_get_user_display_name() {
		check_ajax_referer( 'bpfn_get_user_name', 'nonce' );
		
		$user_id = intval( $_POST['user_id'] );
		$user = get_userdata( $user_id );
		
		if ( $user ) {
			$display_name = $this->get_proper_display_name( $user );
			wp_send_json_success( array( 'name' => $display_name ) );
		}
		
		wp_send_json_error();
	}
}