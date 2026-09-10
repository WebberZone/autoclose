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
	 * Recurring hook that applies due close dates.
	 *
	 * @since 3.2.0
	 */
	public const SWEEP_HOOK = 'autoclose_close_dates_event';

	/**
	 * Per-post event hook used before 3.2.0, cleared on upgrade.
	 *
	 * @since 3.2.0
	 */
	public const LEGACY_EVENT_HOOK = 'autoclose_close_comments_pings_event';

	/**
	 * Option flagging that per-post events have been replaced by the sweep.
	 *
	 * @since 3.2.0
	 */
	public const MIGRATED_OPTION = 'acc_close_dates_migrated';

	/**
	 * Number of posts read per sweep batch.
	 *
	 * @var int
	 */
	private const SWEEP_BATCH_SIZE = 200;

	/**
	 * Maximum number of posts processed during one sweep request.
	 *
	 * @var int
	 */
	private const SWEEP_MAX_POSTS = 2000;

	/**
	 * Argument marking a one-off continuation of the sweep.
	 *
	 * A continuation must not share the recurring event's empty argument list,
	 * or wp_get_scheduled_event() returns the continuation and callers treat
	 * the recurring sweep as missing or misconfigured.
	 *
	 * @var string
	 */
	private const SWEEP_CONTINUATION_ARG = 'continuation';

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
	 * Apply a post's close dates now, leaving future dates to the sweep.
	 *
	 * @param int $post_id Post ID.
	 * @return array Result data.
	 */
	public function maybe_schedule_or_close( $post_id ): array {
		$result  = array(
			'status'    => 'success',
			'scheduled' => 0,
			'closed'    => 0,
			'errors'    => array(),
		);
		$post_id = (int) $post_id;

		foreach ( array( 'comments', 'pings' ) as $type ) {
			$date = get_post_meta( $post_id, "_{$this->prefix}_{$type}_date", true );
			if ( '' === (string) $date ) {
				continue;
			}

			$timestamp = $this->get_date_timestamp( $date );
			if ( false === $timestamp ) {
				/* translators: 1: Discussion type. */
				$result['errors'][] = sprintf( __( 'The %s close date is invalid.', 'autoclose' ), $this->get_type_label( $type ) );
				continue;
			}

			if ( $timestamp > time() ) {
				++$result['scheduled'];
				continue;
			}

			$applied           = $this->apply_due_date( $post_id, $type );
			$result['closed'] += $applied['closed'];
			$result['errors']  = array_merge( $result['errors'], $applied['errors'] );
		}

		if ( $result['scheduled'] > 0 && ! self::schedule_sweep() ) {
			$result['errors'][] = __( 'The close-date sweep could not be scheduled.', 'autoclose' );
		}

		if ( ! empty( $result['errors'] ) ) {
			$result['status'] = 'failed';
		}

		return $result;
	}

	/**
	 * Close every date that is due, in bounded batches.
	 *
	 * One recurring event serves every post, so the number of scheduled events
	 * does not grow with the number of close dates on the site.
	 *
	 * @since 3.2.0
	 *
	 * @return array Sweep result.
	 */
	public function process_due_dates(): array {
		global $wpdb;

		$result = array(
			'status'  => 'success',
			'posts'   => 0,
			'closed'  => 0,
			'pending' => false,
			'errors'  => array(),
		);

		$comments_key = "_{$this->prefix}_comments_date";
		$pings_key    = "_{$this->prefix}_pings_date";
		$now          = wp_date( 'Y-m-d\TH:i' );
		$last_id      = 0;
		$processed    = 0;

		/**
		 * Filters how many posts one sweep query reads.
		 *
		 * @since 3.2.0
		 *
		 * @param int $batch_size Posts read per query.
		 */
		$batch_size = max( 1, (int) apply_filters( 'acc_close_dates_batch_size', self::SWEEP_BATCH_SIZE ) );

		/**
		 * Filters how many posts one sweep request applies before deferring the rest.
		 *
		 * @since 3.2.0
		 *
		 * @param int $max_posts Posts applied per request.
		 */
		$max_posts = max( 1, (int) apply_filters( 'acc_close_dates_sweep_limit', self::SWEEP_MAX_POSTS ) );

		do {
			$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE post_id > %d AND meta_key IN (%s, %s) AND meta_value <> '' AND meta_value <= %s ORDER BY post_id ASC LIMIT %d",
					$last_id,
					$comments_key,
					$pings_key,
					$now,
					$batch_size
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				$result['errors'][] = $wpdb->last_error;
				break;
			}

			$post_ids    = array_map( 'intval', $post_ids );
			$batch_count = count( $post_ids );
			if ( 0 === $batch_count ) {
				break;
			}

			foreach ( $post_ids as $post_id ) {
				++$result['posts'];
				$applied           = $this->apply_due_date( $post_id, 'comments' );
				$result['closed'] += $applied['closed'];
				$result['errors']  = array_merge( $result['errors'], $applied['errors'] );

				$applied           = $this->apply_due_date( $post_id, 'pings' );
				$result['closed'] += $applied['closed'];
				$result['errors']  = array_merge( $result['errors'], $applied['errors'] );
			}

			$last_id    = (int) end( $post_ids );
			$processed += $batch_count;

			if ( $max_posts <= $processed && $batch_size === $batch_count ) {
				$result['pending'] = true;
				break;
			}
		} while ( $batch_size === $batch_count );

		// Applied dates are deleted, so a continuation always drains rows and cannot loop.
		if ( $result['pending'] ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::SWEEP_HOOK, array( self::SWEEP_CONTINUATION_ARG ) );
		}

		if ( ! empty( $result['errors'] ) ) {
			$result['status'] = 'failed';
		}

		return $result;
	}

	/**
	 * Close one discussion type when its stored date is due.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Discussion type.
	 * @return array Result data.
	 */
	private function apply_due_date( int $post_id, string $type ): array {
		$result = array(
			'closed' => 0,
			'errors' => array(),
		);
		$key    = "_{$this->prefix}_{$type}_date";
		$date   = get_post_meta( $post_id, $key, true );

		if ( '' === (string) $date ) {
			return $result;
		}

		$timestamp = $this->get_date_timestamp( $date );
		if ( false === $timestamp ) {
			/* translators: 1: Discussion type. */
			$result['errors'][] = sprintf( __( 'The %s close date is invalid.', 'autoclose' ), $this->get_type_label( $type ) );
			delete_post_meta( $post_id, $key );
			return $result;
		}

		if ( $timestamp > time() ) {
			return $result;
		}

		$closed = 'comments' === $type ? $this->close_comments( $post_id ) : $this->close_pings( $post_id );
		if ( ! $closed ) {
			/* translators: 1: Discussion type. */
			$result['errors'][] = sprintf( __( 'The %s status could not be closed.', 'autoclose' ), $this->get_type_label( $type ) );
			return $result;
		}

		delete_post_meta( $post_id, $key );
		$result['closed'] = 1;

		return $result;
	}

	/**
	 * Recurrence used for the close-date sweep.
	 *
	 * @since 3.2.0
	 *
	 * @return string Registered recurrence name.
	 */
	public static function get_sweep_recurrence(): string {
		/**
		 * Filters how often due close dates are applied.
		 *
		 * @since 3.2.0
		 *
		 * @param string $recurrence Registered recurrence name.
		 */
		$recurrence = (string) apply_filters( 'acc_close_dates_recurrence', 'hourly' );
		$schedules  = wp_get_schedules();

		return isset( $schedules[ $recurrence ] ) ? $recurrence : 'hourly';
	}

	/**
	 * Register the recurring sweep, replacing it when the recurrence changes.
	 *
	 * @since 3.2.0
	 *
	 * @return bool Whether the sweep is registered.
	 */
	public static function schedule_sweep(): bool {
		$recurrence = self::get_sweep_recurrence();
		$existing   = wp_get_scheduled_event( self::SWEEP_HOOK );

		if ( $existing && $existing->schedule === $recurrence ) {
			return true;
		}

		if ( $existing && false === wp_clear_scheduled_hook( self::SWEEP_HOOK ) ) {
			return false;
		}

		$scheduled = wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, self::SWEEP_HOOK, array(), true );

		return ! is_wp_error( $scheduled );
	}

	/**
	 * Drop the per-post events used before 3.2.0 and register the sweep.
	 *
	 * @since 3.2.0
	 */
	public static function maybe_migrate(): void {
		if ( ! get_option( self::MIGRATED_OPTION, false ) ) {
			wp_unschedule_hook( self::LEGACY_EVENT_HOOK );
			update_option( self::MIGRATED_OPTION, true, true );
			self::schedule_sweep();
			return;
		}

		if ( false === wp_next_scheduled( self::SWEEP_HOOK ) ) {
			self::schedule_sweep();
		}
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
	 * Cron callback for per-post events scheduled before 3.2.0.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Event type ('comments' or 'pings').
	 */
	public function maybe_close_due_comments_pings( $post_id, $type = '' ): void {
		if ( in_array( $type, array( 'comments', 'pings' ), true ) ) {
			$this->apply_due_date( (int) $post_id, $type );
			return;
		}

		$this->maybe_schedule_or_close( $post_id );
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
