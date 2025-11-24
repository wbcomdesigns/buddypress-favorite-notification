<?php
/**
 * Favorites Migration Tool for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Favorites Migration Class
 */
class BPFN_Favorites_Migration {

	/**
	 * Database table name
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bp_activity_favorites';
	}

	/**
	 * Start migration process (background processing)
	 */
	public function start_migration() {
		// Initialize migration status
		update_option( 'bpfn_migration_status', array(
			'status'          => 'running',
			'start_time'      => current_time( 'mysql' ),
			'users_processed' => 0,
			'favorites_added' => 0,
			'errors'          => array(),
			'offset'          => 0,
		) );

		// Schedule first batch
		wp_schedule_single_event( time(), 'bpfn_process_migration_batch' );

		return array(
			'success' => true,
			'message' => __( 'Migration started. Processing in background...', 'bp-fav-notification' ),
		);
	}

	/**
	 * Process a single batch of users (chunked migration)
	 */
	public function process_migration_batch() {
		global $wpdb;

		$batch_size = 50; // Process 50 users per batch
		$status = get_option( 'bpfn_migration_status', array() );

		if ( empty( $status ) || $status['status'] !== 'running' ) {
			return; // Migration not running
		}

		$offset = isset( $status['offset'] ) ? (int) $status['offset'] : 0;

		// Get batch of users with favorites
		$users_with_favorites = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, meta_value
			FROM {$wpdb->usermeta}
			WHERE meta_key = 'bp_favorite_activities'
			LIMIT %d OFFSET %d",
			$batch_size,
			$offset
		) );

		// If no users found, migration is complete
		if ( empty( $users_with_favorites ) ) {
			$this->complete_migration( $status );
			return;
		}

		// Process this batch
		foreach ( $users_with_favorites as $user_meta ) {
			$status['users_processed']++;

			$user_id = (int) $user_meta->user_id;
			$favorites = maybe_unserialize( $user_meta->meta_value );

			if ( ! is_array( $favorites ) || empty( $favorites ) ) {
				continue;
			}

			foreach ( $favorites as $activity_id ) {
				$activity_id = (int) $activity_id;

				if ( ! $activity_id ) {
					continue;
				}

				// Check if already exists (avoid duplicates)
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$this->table_name}
					WHERE activity_id = %d AND user_id = %d",
					$activity_id,
					$user_id
				) );

				if ( $exists ) {
					continue; // Skip if already migrated
				}

				// Insert into our table
				$result = $wpdb->insert(
					$this->table_name,
					array(
						'activity_id' => $activity_id,
						'user_id'     => $user_id,
					),
					array( '%d', '%d' )
				);

				if ( $result ) {
					$status['favorites_added']++;
				} else {
					$status['errors'][] = sprintf(
						'Failed to insert activity_id: %d for user_id: %d',
						$activity_id,
						$user_id
					);
				}
			}
		}

		// Update status and offset
		$status['offset'] = $offset + $batch_size;
		update_option( 'bpfn_migration_status', $status );

		// Schedule next batch
		wp_schedule_single_event( time() + 5, 'bpfn_process_migration_batch' );
	}

	/**
	 * Complete migration
	 */
	private function complete_migration( $status ) {
		$status['status'] = 'completed';
		$status['end_time'] = current_time( 'mysql' );
		$status['message'] = sprintf(
			/* translators: 1: number of users, 2: number of favorites */
			__( 'Migration complete! Processed %1$d users and added %2$d favorites.', 'bp-fav-notification' ),
			$status['users_processed'],
			$status['favorites_added']
		);

		// Save final log
		update_option( 'bpfn_migration_log', $status );
		update_option( 'bpfn_migration_status', $status );

		// Mark migration as complete
		update_option( 'bpfn_favorites_migrated', true );
	}

	/**
	 * Run migration synchronously (for small sites or manual trigger)
	 * This is the legacy method for backward compatibility
	 */
	public function run_migration() {
		global $wpdb;

		$log = array(
			'start_time'      => current_time( 'mysql' ),
			'users_processed' => 0,
			'favorites_added' => 0,
			'errors'          => array(),
		);

		// Get all users who have favorites
		$users_with_favorites = $wpdb->get_results(
			"SELECT user_id, meta_value
			FROM {$wpdb->usermeta}
			WHERE meta_key = 'bp_favorite_activities'"
		);

		if ( empty( $users_with_favorites ) ) {
			$log['message'] = __( 'No favorites found to migrate', 'bp-fav-notification' );
			return $log;
		}

		foreach ( $users_with_favorites as $user_meta ) {
			$log['users_processed']++;

			$user_id = (int) $user_meta->user_id;
			$favorites = maybe_unserialize( $user_meta->meta_value );

			if ( ! is_array( $favorites ) || empty( $favorites ) ) {
				continue;
			}

			foreach ( $favorites as $activity_id ) {
				$activity_id = (int) $activity_id;

				if ( ! $activity_id ) {
					continue;
				}

				// Check if already exists (avoid duplicates)
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$this->table_name}
					WHERE activity_id = %d AND user_id = %d",
					$activity_id,
					$user_id
				) );

				if ( $exists ) {
					continue; // Skip if already migrated
				}

				// Insert into our table
				$result = $wpdb->insert(
					$this->table_name,
					array(
						'activity_id' => $activity_id,
						'user_id'     => $user_id,
					),
					array( '%d', '%d' )
				);

				if ( $result ) {
					$log['favorites_added']++;
				} else {
					$log['errors'][] = sprintf(
						'Failed to insert activity_id: %d for user_id: %d',
						$activity_id,
						$user_id
					);
				}
			}
		}

		$log['end_time'] = current_time( 'mysql' );
		$log['message'] = sprintf(
			/* translators: 1: number of users, 2: number of favorites */
			__( 'Migration complete! Processed %1$d users and added %2$d favorites.', 'bp-fav-notification' ),
			$log['users_processed'],
			$log['favorites_added']
		);

		// Save migration log
		update_option( 'bpfn_migration_log', $log );

		// Mark migration as complete
		update_option( 'bpfn_favorites_migrated', true );

		return $log;
	}

	/**
	 * Check if migration has been run
	 */
	public function is_migrated() {
		return (bool) get_option( 'bpfn_favorites_migrated', false );
	}

	/**
	 * Get migration log
	 */
	public function get_migration_log() {
		return get_option( 'bpfn_migration_log', array() );
	}

	/**
	 * Reset migration status (for testing)
	 */
	public function reset_migration() {
		delete_option( 'bpfn_favorites_migrated' );
		delete_option( 'bpfn_migration_log' );
	}

	/**
	 * Get migration statistics
	 */
	public function get_migration_stats() {
		global $wpdb;

		// Count users with favorites in user meta
		$users_with_meta = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->usermeta}
			WHERE meta_key = 'bp_favorite_activities'"
		);

		// Count favorites in our table
		$favorites_in_table = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$this->table_name}"
		);

		// Count total favorite activity IDs in user meta
		$all_meta = $wpdb->get_col(
			"SELECT meta_value
			FROM {$wpdb->usermeta}
			WHERE meta_key = 'bp_favorite_activities'"
		);

		$total_meta_favorites = 0;
		foreach ( $all_meta as $meta_value ) {
			$favorites = maybe_unserialize( $meta_value );
			if ( is_array( $favorites ) ) {
				$total_meta_favorites += count( $favorites );
			}
		}

		return array(
			'users_with_favorites'   => (int) $users_with_meta,
			'meta_favorites_count'   => $total_meta_favorites,
			'table_favorites_count'  => (int) $favorites_in_table,
			'migrated'               => $this->is_migrated(),
			'migration_pending'      => ! $this->is_migrated() && $total_meta_favorites > 0,
		);
	}

	/**
	 * Get migration progress (for background processing)
	 */
	public function get_migration_progress() {
		$status = get_option( 'bpfn_migration_status', array() );

		if ( empty( $status ) ) {
			return array(
				'status'  => 'not_started',
				'percent' => 0,
			);
		}

		// Calculate progress percentage
		$stats = $this->get_migration_stats();
		$total_users = $stats['users_with_favorites'];
		$processed = isset( $status['users_processed'] ) ? $status['users_processed'] : 0;

		$percent = $total_users > 0 ? round( ( $processed / $total_users ) * 100 ) : 0;

		return array(
			'status'          => isset( $status['status'] ) ? $status['status'] : 'unknown',
			'percent'         => $percent,
			'users_processed' => $processed,
			'total_users'     => $total_users,
			'favorites_added' => isset( $status['favorites_added'] ) ? $status['favorites_added'] : 0,
			'errors'          => isset( $status['errors'] ) ? count( $status['errors'] ) : 0,
		);
	}

	/**
	 * Register WP Cron hooks
	 */
	public function register_hooks() {
		add_action( 'bpfn_process_migration_batch', array( $this, 'process_migration_batch' ) );
	}

	/**
	 * Cancel ongoing migration
	 */
	public function cancel_migration() {
		$status = get_option( 'bpfn_migration_status', array() );
		$status['status'] = 'cancelled';
		$status['cancelled_at'] = current_time( 'mysql' );
		update_option( 'bpfn_migration_status', $status );

		// Clear scheduled events
		wp_clear_scheduled_hook( 'bpfn_process_migration_batch' );

		return array(
			'success' => true,
			'message' => __( 'Migration cancelled.', 'bp-fav-notification' ),
		);
	}
}
