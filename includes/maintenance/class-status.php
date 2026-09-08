<?php
/**
 * Maintenance status persistence and cron health reporting.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\Maintenance;

use WebberZone\AutoClose\Options_API;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stores the latest maintenance outcome and reports cron health.
 *
 * @since 3.2.0
 */
class Status {

	/**
	 * Option containing the latest maintenance status.
	 *
	 * @since 3.2.0
	 */
	const OPTION_NAME = 'acc_maintenance_status';

	/**
	 * Get the latest persisted maintenance status for the current site.
	 *
	 * @since 3.2.0
	 *
	 * @return array Latest status, or an empty array when no run has completed.
	 */
	public static function get_saved(): array {
		$status = get_option( self::OPTION_NAME, array() );

		return is_array( $status ) ? $status : array();
	}

	/**
	 * Save the latest maintenance status for the current site.
	 *
	 * @since 3.2.0
	 *
	 * @param array $status Status data.
	 * @return bool Whether WordPress accepted the update.
	 */
	public static function save( array $status ): bool {
		return update_option( self::OPTION_NAME, $status, false );
	}

	/**
	 * Mark the beginning of a maintenance attempt.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $timestamp Attempt timestamp.
	 * @param string $run_id    Run identifier.
	 */
	public static function record_attempt( int $timestamp, string $run_id ): void {
		$status                      = self::get_saved();
		$status['run_id']            = $run_id;
		$status['last_attempt']      = $timestamp;
		$status['last_attempt_at']   = gmdate( 'c', $timestamp );
		$status['last_state']        = 'running';
		$status['last_error']        = null;
		$status['last_errors']       = array();
		$status['last_completed']    = $status['last_completed'] ?? null;
		$status['last_success']      = $status['last_success'] ?? null;
		$status['last_success_at']   = $status['last_success_at'] ?? null;
		$status['last_completed_at'] = $status['last_completed_at'] ?? null;

		self::save( $status );
	}

	/**
	 * Record a completed maintenance run.
	 *
	 * Only the latest summary is kept. Successful zero-change runs update the
	 * successful timestamp; partial and failed runs do not.
	 *
	 * @since 3.2.0
	 *
	 * @param array $summary Completed run summary.
	 */
	public static function record_run( array $summary ): void {
		$status                      = self::get_saved();
		$completed                   = isset( $summary['completed_at_timestamp'] ) ? (int) $summary['completed_at_timestamp'] : time();
		$outcome                     = isset( $summary['outcome'] ) ? (string) $summary['outcome'] : 'failed';
		$status['run_id']            = $summary['run_id'] ?? ( $status['run_id'] ?? '' );
		$status['last_attempt']      = isset( $summary['started_at_timestamp'] ) ? (int) $summary['started_at_timestamp'] : ( $status['last_attempt'] ?? $completed );
		$status['last_attempt_at']   = gmdate( 'c', (int) $status['last_attempt'] );
		$status['last_completed']    = $completed;
		$status['last_completed_at'] = gmdate( 'c', $completed );
		$status['last_state']        = $outcome;
		$status['last_counts']       = array(
			'comments_closed'   => (int) ( $summary['comments_closed'] ?? 0 ),
			'pings_closed'      => (int) ( $summary['pings_closed'] ?? 0 ),
			'comments_opened'   => (int) ( $summary['comments_opened'] ?? 0 ),
			'pings_opened'      => (int) ( $summary['pings_opened'] ?? 0 ),
			'revisions_deleted' => (int) ( $summary['revisions_deleted'] ?? 0 ),
			'revisions_scanned' => (int) ( $summary['revisions_scanned'] ?? 0 ),
			// True when the run hit its per-run bound, so revisions still await the next run.
			'revisions_pending' => (bool) ( $summary['revisions_remaining'] ?? false ),
		);
		$status['last_errors']       = array_values( (array) ( $summary['errors'] ?? array() ) );
		$status['last_error']        = empty( $status['last_errors'] ) ? null : (string) reset( $status['last_errors'] );

		if ( 'success' === $outcome ) {
			$status['last_success']    = $completed;
			$status['last_success_at'] = gmdate( 'c', $completed );
		}

		self::save( $status );
	}

	/**
	 * Return current site, feature, schedule, and latest-run status.
	 *
	 * @since 3.2.0
	 *
	 * @return array Status report.
	 */
	public static function get(): array {
		$cron_enabled  = (bool) Options_API::get_option( 'cron_on' );
		$next_run      = wp_next_scheduled( 'acc_cron_hook' );
		$next_run      = false === $next_run ? null : (int) $next_run;
		$warnings      = array();
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		if ( $cron_enabled && null === $next_run ) {
			$warnings[] = __( 'Scheduled maintenance is enabled, but its cron event is not registered.', 'autoclose' );
		}

		if ( $cron_enabled && null !== $next_run && $next_run < time() ) {
			$warnings[] = __( 'The scheduled maintenance event is overdue.', 'autoclose' );
		}

		$saved    = self::get_saved();
		$timezone = wp_timezone_string();
		if ( empty( $timezone ) ) {
			$timezone = 'UTC';
		}

		return array(
			'health'   => empty( $warnings ) ? ( empty( $saved ) ? 'unknown' : 'healthy' ) : 'warning',
			'warnings' => $warnings,
			'plugin'   => array(
				'version' => defined( 'ACC_PLUGIN_VERSION' ) ? ACC_PLUGIN_VERSION : 'unknown',
			),
			'site'     => array(
				'id'        => (int) get_current_blog_id(),
				'name'      => get_bloginfo( 'name' ),
				'url'       => home_url( '/' ),
				'timezone'  => $timezone,
				'multisite' => is_multisite(),
			),
			'features' => array(
				'close_comments'   => (bool) Options_API::get_option( 'close_comment' ),
				'close_pings'      => (bool) Options_API::get_option( 'close_pbtb' ),
				'delete_revisions' => (bool) Options_API::get_option( 'delete_revisions' ),
				'revision_age'     => max( 0, (int) Options_API::get_option( 'revision_age' ) ),
			),
			'schedule' => array(
				'enabled'          => $cron_enabled,
				'event_exists'     => null !== $next_run,
				'timezone'         => 'UTC',
				'next_run'         => $next_run,
				'next_run_at'      => null === $next_run ? null : wp_date( DATE_ATOM, $next_run ),
				'recurrence'       => (string) Options_API::get_option( 'cron_recurrence' ),
				'overdue'          => $cron_enabled && null !== $next_run && $next_run < time(),
				'wp_cron_disabled' => $cron_disabled,
			),
			'last_run' => array(
				'attempt'      => $saved['last_attempt'] ?? null,
				'attempt_at'   => $saved['last_attempt_at'] ?? null,
				'completed'    => $saved['last_completed'] ?? null,
				'completed_at' => $saved['last_completed_at'] ?? null,
				'success'      => $saved['last_success'] ?? null,
				'success_at'   => $saved['last_success_at'] ?? null,
				'outcome'      => $saved['last_state'] ?? null,
				'counts'       => $saved['last_counts'] ?? array(),
				'errors'       => $saved['last_errors'] ?? array(),
				'run_id'       => $saved['run_id'] ?? null,
			),
		);
	}
}
