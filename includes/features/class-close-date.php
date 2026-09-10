<?php
/**
 * Feature: Per-post comment/trackback close date logic (scheduling & closing only).
 *
 * @package    WebberZone\AutoClose
 * @subpackage Features
 */

namespace WebberZone\AutoClose\Features;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Close_Date
 *
 * Handles scheduling and closing logic for comments/trackbacks based on meta.
 * No admin UI/metabox logic here.
 *
 * @since 3.0.0
 */
class Close_Date {

	/**
	 * Hook used to reconcile persisted close dates after activation.
	 *
	 * @since 3.2.0
	 */
	public const RESTORE_HOOK = 'autoclose_restore_close_dates_event';

	/**
	 * Number of posts inspected per activation batch.
	 *
	 * @var int
	 */
	private const RESTORE_BATCH_SIZE = 100;

	/**
	 * Maximum number of posts reconciled during one cron request.
	 *
	 * @since 3.2.0
	 */
	private const RESTORE_MAX_POSTS = 500;

	/**
	 * Option storing the last post ID reconciled by the deferred restore.
	 *
	 * @since 3.2.0
	 */
	private const RESTORE_CURSOR_OPTION = 'acc_close_date_restore_cursor';

	/**
	 * Option flagging that the deferred restore has completed at least once.
	 *
	 * @since 3.2.0
	 */
	public const RESTORE_DONE_OPTION = 'acc_close_date_restore_done';

	/**
	 * Option counting consecutive failed restore selections.
	 *
	 * @since 3.2.0
	 */
	private const RESTORE_ATTEMPTS_OPTION = 'acc_close_date_restore_attempts';

	/**
	 * Maximum consecutive selection failures before the restore stops retrying.
	 *
	 * @since 3.2.0
	 */
	private const RESTORE_MAX_ATTEMPTS = 5;

	/**
	 * Prefix for meta keys and filters.
	 *
	 * @var string
	 */
	protected $prefix = 'acc';

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Schedules cron if date/time is in the future, closes immediately if past,
	 * and clears any existing scheduled event before (re-)scheduling.
	 *
	 * @param int $post_id Post ID.
	 * @return array Result data.
	 */
	public function maybe_schedule_or_close( $post_id ): array {
		$result = array(
			'status'    => 'success',
			'scheduled' => 0,
			'closed'    => 0,
			'errors'    => array(),
		);

		foreach ( array( 'comments', 'pings' ) as $type ) {
			$type_result          = $this->schedule_or_close_type( $post_id, $type );
			$result['scheduled'] += $type_result['scheduled'];
			$result['closed']    += $type_result['closed'];
			$result['errors']     = array_merge( $result['errors'], $type_result['errors'] );
		}

		if ( ! empty( $result['errors'] ) ) {
			$result['status'] = 'failed';
		}

		return $result;
	}

	/**
	 * Restore one-off close events from persisted post metadata.
	 *
	 * @since 3.2.0
	 *
	 * @return array Restoration result.
	 */
	public function restore_scheduled_events(): array {
		global $wpdb;

		delete_option( self::RESTORE_DONE_OPTION );

		$last_id   = (int) get_option( self::RESTORE_CURSOR_OPTION, 0 );
		$processed = 0;
		$result    = array(
			'status'    => 'success',
			'posts'     => 0,
			'scheduled' => 0,
			'closed'    => 0,
			'pending'   => false,
			'errors'    => array(),
		);

		do {
			$comments_key = "_{$this->prefix}_comments_date";
			$pings_key    = "_{$this->prefix}_pings_date";
			$post_ids     = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.post_id > %d AND pm.meta_key IN (%s, %s) AND pm.meta_value <> '' ORDER BY pm.post_id ASC LIMIT %d",
					$last_id,
					$comments_key,
					$pings_key,
					self::RESTORE_BATCH_SIZE
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				$result['errors'][] = $wpdb->last_error;
				$attempts           = (int) get_option( self::RESTORE_ATTEMPTS_OPTION, 0 ) + 1;
				update_option( self::RESTORE_ATTEMPTS_OPTION, $attempts, false );

				if ( self::RESTORE_MAX_ATTEMPTS > $attempts ) {
					$result['pending'] = true;
				} else {
					$result['errors'][] = __( 'The close-date restoration was abandoned after repeated database failures.', 'autoclose' );
				}

				break;
			}

			delete_option( self::RESTORE_ATTEMPTS_OPTION );

			$post_ids    = array_map( 'intval', $post_ids );
			$batch_count = count( $post_ids );
			if ( empty( $post_ids ) ) {
				break;
			}

			foreach ( $post_ids as $post_id ) {
				++$result['posts'];
				$post_result          = $this->maybe_schedule_or_close( $post_id );
				$result['scheduled'] += $post_result['scheduled'];
				$result['closed']    += $post_result['closed'];
				$result['errors']     = array_merge( $result['errors'], $post_result['errors'] );
			}

			$last_id    = (int) end( $post_ids );
			$processed += $batch_count;
			update_option( self::RESTORE_CURSOR_OPTION, $last_id, false );

			if ( self::RESTORE_MAX_POSTS <= $processed && self::RESTORE_BATCH_SIZE === $batch_count ) {
				$result['pending'] = true;
				break;
			}
		} while ( self::RESTORE_BATCH_SIZE === $batch_count );

		if ( $result['pending'] && ! $this->schedule_restore_continuation() ) {
			$result['errors'][] = __( 'The close-date restoration continuation could not be scheduled.', 'autoclose' );
		}

		if ( ! $result['pending'] ) {
			delete_option( self::RESTORE_CURSOR_OPTION );
			delete_option( self::RESTORE_ATTEMPTS_OPTION );

			if ( empty( $result['errors'] ) ) {
				update_option( self::RESTORE_DONE_OPTION, true, false );
			}
		}

		if ( ! empty( $result['errors'] ) ) {
			$result['status'] = 'failed';
		}

		return $result;
	}

	/**
	 * Whether the deferred restore has completed at least once on this site.
	 *
	 * @since 3.2.0
	 *
	 * @return bool Whether the restore is done.
	 */
	public static function is_restore_done(): bool {
		return (bool) get_option( self::RESTORE_DONE_OPTION, false );
	}

	/**
	 * Schedule another bounded restore request when close-date rows remain.
	 *
	 * @since 3.2.0
	 *
	 * @return bool Whether the continuation is registered.
	 */
	private function schedule_restore_continuation(): bool {
		if ( false !== wp_next_scheduled( self::RESTORE_HOOK ) ) {
			return true;
		}

		$scheduled = wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RESTORE_HOOK, array(), true );

		return ! is_wp_error( $scheduled );
	}

	/**
	 * Convert a stored site-local close date to a Unix timestamp.
	 *
	 * @param string $date Stored date in Y-m-d\\TH:i format.
	 * @return int|false Timestamp, or false when no valid date is stored.
	 */
	private function get_date_timestamp( $date ) {
		if ( empty( $date ) ) {
			return false;
		}

		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d\\TH:i', (string) $date, wp_timezone() );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( false === $parsed || ( false !== $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || (string) $date !== $parsed->format( 'Y-m-d\\TH:i' ) ) {
			return false;
		}

		return $parsed->getTimestamp();
	}

	/**
	 * Cron callback to close comments/pings if due.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Event type ('comments' or 'pings').
	 */
	public function maybe_close_due_comments_pings( $post_id, $type = '' ): void {
		if ( in_array( $type, array( 'comments', 'pings' ), true ) ) {
			$this->schedule_or_close_type( $post_id, $type );
			return;
		}

		$this->maybe_schedule_or_close( $post_id );
	}

	/**
	 * Schedule or close one discussion type.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Discussion type.
	 * @return array Result data.
	 */
	private function schedule_or_close_type( $post_id, string $type ): array {
		$date       = get_post_meta( $post_id, "_{$this->prefix}_{$type}_date", true );
		$timestamp  = $this->get_date_timestamp( $date );
		$result     = array(
			'scheduled' => 0,
			'closed'    => 0,
			'errors'    => array(),
		);
		$type_label = $this->get_type_label( $type );

		$cleared = wp_clear_scheduled_hook( 'autoclose_close_comments_pings_event', array( $post_id, $type ) );
		if ( false === $cleared ) {
			$result['errors'][] = sprintf( __( 'The %s close event could not be cleared.', 'autoclose' ), $type_label );
			return $result;
		}

		if ( false === $timestamp ) {
			if ( ! empty( $date ) ) {
				$result['errors'][] = sprintf( __( 'The %s close date is invalid.', 'autoclose' ), $type_label );
			}
			return $result;
		}

		if ( $timestamp <= time() ) {
			$closed = 'comments' === $type ? $this->close_comments( $post_id ) : $this->close_pings( $post_id );
			if ( ! $closed ) {
				$result['errors'][] = sprintf( __( 'The %s status could not be closed.', 'autoclose' ), $type_label );
			} else {
				$result['closed'] = 1;
			}
			return $result;
		}

		$scheduled = wp_schedule_single_event( $timestamp, 'autoclose_close_comments_pings_event', array( $post_id, $type ), true );
		if ( is_wp_error( $scheduled ) ) {
			$result['errors'][] = sprintf( __( 'The %s close event could not be scheduled.', 'autoclose' ), $type_label );
		} else {
			$result['scheduled'] = 1;
		}

		return $result;
	}

	/**
	 * Get a translated label for a discussion type.
	 *
	 * @since 3.2.0
	 *
	 * @param string $type Discussion type.
	 * @return string Translated discussion type label.
	 */
	private function get_type_label( string $type ): string {
		return 'comments' === $type ? __( 'comments', 'autoclose' ) : __( 'pingbacks/trackbacks', 'autoclose' );
	}

	/**
	 * Actually close comments for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether the status was closed.
	 */
	protected function close_comments( $post_id ): bool {
		if ( 'closed' !== get_post_field( 'comment_status', $post_id ) ) {
			$updated = false;
			Reopen::without_reopening(
				static function () use ( $post_id, &$updated ) {
					$result  = wp_update_post(
						array(
							'ID'             => $post_id,
							'comment_status' => 'closed',
						),
						true
					);
					$updated = ! is_wp_error( $result );
				}
			);
			return $updated;
		}

		return true;
	}

	/**
	 * Actually close pings/trackbacks for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether the status was closed.
	 */
	protected function close_pings( $post_id ): bool {
		if ( 'closed' !== get_post_field( 'ping_status', $post_id ) ) {
			$updated = false;
			Reopen::without_reopening(
				static function () use ( $post_id, &$updated ) {
					$result  = wp_update_post(
						array(
							'ID'          => $post_id,
							'ping_status' => 'closed',
						),
						true
					);
					$updated = ! is_wp_error( $result );
				}
			);
			return $updated;
		}

		return true;
	}

	/**
	 * Get supported post types for the close logic.
	 *
	 * @return array Array of post types.
	 */
	protected function get_supported_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		return array_filter(
			$post_types,
			static function ( $type ) {
				return post_type_supports( $type, 'comments' );
			}
		);
	}
}
