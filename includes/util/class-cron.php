<?php
/**
 * Scheduling functionality.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Util;

use WebberZone\AutoClose\Features\Close_Date;
use WebberZone\AutoClose\Options_API;

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
		// wp_get_schedules() can run before init; translating there triggers a just-in-time textdomain notice.
		if ( ! isset( $schedules['fortnightly'] ) ) {
			$schedules['fortnightly'] = array(
				'interval' => 14 * DAY_IN_SECONDS,
				'display'  => did_action( 'init' ) ? __( 'Every fortnight', 'autoclose' ) : 'Every fortnight',
			);
		}

		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => did_action( 'init' ) ? __( 'Every 30 days', 'autoclose' ) : 'Every 30 days',
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
	 * @param bool   $force      Reschedule even when the existing event matches.
	 */
	public function enable_run( $hour, $min, $recurrence, $future = false, $force = false ): bool {
		$hour       = max( 0, min( 23, (int) $hour ) );
		$min        = max( 0, min( 59, (int) $min ) );
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
		if ( ! $force && $existing && $existing->schedule === $recurrence ) {
			$existing_hour  = (int) gmdate( 'G', (int) $existing->timestamp );
			$existing_min   = (int) gmdate( 'i', (int) $existing->timestamp );
			$existing_valid = ! $future || (int) $existing->timestamp > time();

			if ( $existing_valid && $existing_hour === $hour && $existing_min === $min ) {
				return true;
			}
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

	/**
	 * Repair the scheduled maintenance event for the current site.
	 *
	 * Only repairs the event when scheduled maintenance is enabled in the
	 * current site's settings. It does not enable or disable the feature.
	 * Shared by the WP-CLI repair command and the Tools page repair action.
	 *
	 * @since 3.2.0
	 *
	 * @param bool $force Reschedule the event even when it is already registered.
	 * @return array Repair result.
	 */
	public function repair( bool $force = false ): array {
		$before  = wp_next_scheduled( 'acc_cron_hook' );
		$enabled = (bool) Options_API::get_option( 'cron_on' );
		$overdue = false !== $before && (int) $before < time();
		$errors  = array();
		$outcome = 'success';

		if ( ! $enabled ) {
			$outcome  = 'failed';
			$errors[] = __( 'Scheduled maintenance is disabled for the current site.', 'autoclose' );
		} elseif ( false === $before || $overdue || $force ) {
			$repaired = $this->enable_run(
				(int) Options_API::get_option( 'cron_hour' ),
				(int) Options_API::get_option( 'cron_min' ),
				(string) Options_API::get_option( 'cron_recurrence' ),
				true,
				$force
			);

			if ( ! $repaired ) {
				$outcome  = 'failed';
				$errors[] = __( 'The AutoClose cron event could not be repaired.', 'autoclose' );
			}
		}

		$after = wp_next_scheduled( 'acc_cron_hook' );

		if ( $enabled && false === $after ) {
			$outcome  = 'failed';
			$errors[] = __( 'The AutoClose cron event could not be registered.', 'autoclose' );
		}

		if ( ! Close_Date::is_restore_done() && false === wp_next_scheduled( Close_Date::RESTORE_HOOK ) ) {
			$restore_scheduled = wp_schedule_single_event( time() + MINUTE_IN_SECONDS, Close_Date::RESTORE_HOOK, array(), true );

			if ( is_wp_error( $restore_scheduled ) ) {
				$outcome  = 'failed';
				$errors[] = __( 'The close-date restoration event could not be scheduled.', 'autoclose' );
			}
		}

		return array(
			'outcome'    => $outcome,
			'forced'     => $force,
			'overdue'    => $overdue,
			'enabled'    => $enabled,
			'before'     => false === $before ? null : (int) $before,
			'after'      => false === $after ? null : (int) $after,
			'recurrence' => (string) Options_API::get_option( 'cron_recurrence' ),
			'errors'     => $errors,
		);
	}
}
