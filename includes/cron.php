<?php
/**
 * Deprecated functions.
 *
 * @since 2.0.0
 *
 * @package AutoClose
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Function to enable run or actions.
 *
 * @since 1.0
 * @since 2.0.0 Added $recurrence
 *
 * @param int $hour       Hour.
 * @param int $min        Minute.
 * @param int $recurrence Frequency.
 */
function acc_enable_run( $hour, $min, $recurrence ) {
	if ( ! wp_next_scheduled( 'ald_acc_hook' ) ) {
		wp_schedule_event( mktime( $hour, $min, 0 ), $recurrence, 'ald_acc_hook' );
	} else {
		wp_clear_scheduled_hook( 'ald_acc_hook' );
		wp_schedule_event( mktime( $hour, $min, 0 ), $recurrence, 'ald_acc_hook' );
	}
}


/**
 * Function to disable daily run or actions.
 *
 * @since 1.0
 */
function acc_disable_run() {
	if ( wp_next_scheduled( 'ald_acc_hook' ) ) {
		wp_clear_scheduled_hook( 'ald_acc_hook' );
	}
}

