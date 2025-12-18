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
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Constructor code.
	}

	/**
	 * Function to enable run or actions.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $hour       Hour.
	 * @param int    $min        Minute.
	 * @param string $recurrence Frequency.
	 */
	public function enable_run( $hour, $min, $recurrence ) {
		$on   = mktime( $hour, $min, 0, gmdate( 'm' ), gmdate( 'd' ), gmdate( 'Y' ) );
		$date = gmdate( 'm/d/Y H:i:s', $on );

		if ( ! wp_next_scheduled( 'acc_cron_hook' ) ) {
			wp_schedule_event( $on, $recurrence, 'acc_cron_hook' );
		} else {
			wp_clear_scheduled_hook( 'acc_cron_hook' );
			wp_schedule_event( $on, $recurrence, 'acc_cron_hook' );
		}
	}

	/**
	 * Function to disable daily run or actions.
	 *
	 * @since 3.0.0
	 */
	public function disable_run() {
		if ( wp_next_scheduled( 'acc_cron_hook' ) ) {
			wp_clear_scheduled_hook( 'acc_cron_hook' );
		}
	}
}
