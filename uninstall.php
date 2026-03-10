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

	// Get all blogs in the network and activate plugin on each one.
	$sites = get_sites(
		array(
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
		)
	);

	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		acc_delete_data();
		restore_current_blog();
	}
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

	// Wizard options.
	delete_option( 'acc_wizard_completed' );
	delete_option( 'acc_wizard_completed_date' );
	delete_option( 'acc_wizard_current_step' );
	delete_option( 'acc_show_wizard' );

	delete_transient( 'acc_show_wizard_activation_redirect' );

	wp_clear_scheduled_hook( 'acc_cron_hook' );
	wp_clear_scheduled_hook( 'ald_acc_hook' );
	wp_clear_scheduled_hook( 'autoclose_close_comments_pings_event' );
}
