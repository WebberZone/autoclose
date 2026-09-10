<?php
/**
 * Fired when the plugin is uninstalled
 *
 * @package AutoClose
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}


if ( is_multisite() ) {

	$offset = 0;
	do {
		$site_ids = get_sites(
			array(
				'archived' => 0,
				'spam'     => 0,
				'deleted'  => 0,
				'number'   => 100,
				'offset'   => $offset,
				'fields'   => 'ids',
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			try {
				acc_delete_data();
			} finally {
				restore_current_blog();
			}
		}

		$batch_count = count( $site_ids );
		$offset     += $batch_count;
	} while ( 100 === $batch_count );
} else {
	acc_delete_data();
}


/**
 * Delete Data.
 *
 * @since 2.0.0
 */
function acc_delete_data() {

	delete_option( 'acc_settings' );
	delete_option( 'ald_acc_settings' );
	delete_option( 'acc_maintenance_status' );
	delete_option( 'acc_legacy_status_migration_complete' );
	delete_option( 'acc_close_date_restore_cursor' );
	delete_option( 'acc_close_date_restore_attempts' );
	delete_option( 'acc_close_date_restore_done' );
	delete_option( 'acc_revision_policy_ack' );

	// Wizard options.
	delete_option( 'acc_wizard_completed' );
	delete_option( 'acc_wizard_completed_date' );
	delete_option( 'acc_wizard_current_step' );
	delete_option( 'acc_show_wizard' );

	delete_transient( 'acc_show_wizard_activation_redirect' );

	wp_clear_scheduled_hook( 'acc_cron_hook' );
	wp_clear_scheduled_hook( 'autoclose_restore_close_dates_event' );
	wp_clear_scheduled_hook( 'ald_acc_hook' );
	wp_unschedule_hook( 'autoclose_close_comments_pings_event' );
}
