<?php
/**
 * Scheduling functionality.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Util;

/**
 * Cron class.
 *
 * @since 3.0.0
 */
class Cron {

	/**
	 * Register the custom recurrence intervals used by AutoClose.
	 *
	 * @since 3.2.0
	 */
	public static function register_schedules(): void {
		if ( false !== has_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) ) ) {
			return;
		}

		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) );
	}

	/**
	 * Add AutoClose's non-core recurrence intervals.
	 *
	 * @since 3.2.0
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Schedules with AutoClose intervals.
	 */
	public static function add_schedules( array $schedules ): array {
		if ( ! isset( $schedules['fortnightly'] ) ) {
			$schedules['fortnightly'] = array(
				'interval' => 14 * DAY_IN_SECONDS,
				'display'  => __( 'Every fortnight', 'autoclose' ),
			);
		}

		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Every 30 days', 'autoclose' ),
			);
		}

		return $schedules;
	}

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		self::register_schedules();
	}

	/**
	 * Function to enable run or actions.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $hour       Hour.
	 * @param int    $min        Minute.
	 * @param string $recurrence Frequency.
	 * @param bool   $future     Ensure the first occurrence is in the future.
	 */
	public function enable_run( $hour, $min, $recurrence, $future = false ): bool {
		$recurrence = (string) $recurrence;
		$schedules  = wp_get_schedules();
		if ( ! isset( $schedules[ $recurrence ] ) ) {
			return false;
		}

		$on = gmmktime( $hour, $min, 0, (int) gmdate( 'm' ), (int) gmdate( 'd' ), (int) gmdate( 'Y' ) );
		if ( $future && $on <= time() ) {
			$on = gmmktime( $hour, $min, 0, (int) gmdate( 'm' ), (int) gmdate( 'd' ) + 1, (int) gmdate( 'Y' ) );
		}

		$existing = wp_get_scheduled_event( 'acc_cron_hook' );
		if ( $existing && ( (int) $existing->timestamp === $on && $existing->schedule === $recurrence ) ) {
			return true;
		}

		if ( $existing ) {
			$cleared = wp_clear_scheduled_hook( 'acc_cron_hook' );
			if ( false === $cleared ) {
				return false;
			}
		}

		$scheduled = wp_schedule_event( $on, $recurrence, 'acc_cron_hook', array(), true );
		if ( is_wp_error( $scheduled ) ) {
			if ( $existing ) {
				wp_schedule_event( $existing->timestamp, $existing->schedule, 'acc_cron_hook', $existing->args, true );
			}
			return false;
		}

		return true;
	}

	/**
	 * Function to disable daily run or actions.
	 *
	 * @since 3.0.0
	 */
	public function disable_run(): bool {
		$cleared = wp_clear_scheduled_hook( 'acc_cron_hook' );
		return false !== $cleared;
	}
}
