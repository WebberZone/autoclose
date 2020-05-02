<?php
/**
 * Help tab.
 *
 * Functions to generated the help tab on the Settings page.
 *
 * @since 2.0.0
 *
 * @package AutoClose
 * @subpackage Admin/Help
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Generates the settings help page.
 *
 * @since 2.0.0
 */
function acc_settings_help() {
	global $acc_settings_page;

	$screen = get_current_screen();

	if ( $screen->id !== $acc_settings_page ) {
		return;
	}

	$screen->set_help_sidebar( acc_get_help_sidebar() );

	$screen->add_help_tab(
		array(
			'id'      => 'acc-settings-general',
			'title'   => __( 'General', 'autoclose' ),
			'content' =>
			'<p>' . __( 'This screen provides the basic settings for configuring AutoClose.', 'autoclose' ) . '</p>' .
				'<p>' . __( 'Enable closing of comments, pingbacks/trackbacks and/or deleting revisions. You can also set up the schedule at which this will take place automatically.', 'autoclose' ) . '</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'acc-settings-comments',
			'title'   => __( 'Comments', 'autoclose' ),
			'content' =>
			'<p>' . __( 'This screen provides options to configure options for Comments specifically.', 'autoclose' ) . '</p>' .
				'<p>' . __( 'Select the post types on which comments will be closed, period to close and exceptions.', 'autoclose' ) . '</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'acc-settings-pingtracks',
			'title'   => __( 'Pingbacks / Trackbacks', 'autoclose' ),
			'content' =>
			'<p>' . __( 'This screen provides options to configure options for Pingbacks/Trackbacks specifically.', 'autoclose' ) . '</p>' .
				'<p>' . __( 'Select the post types on which pingbacks/trackbacks will be closed, period to close and exceptions.', 'autoclose' ) . '</p>',
		)
	);

	do_action( 'acc_settings_help', $screen );

}

/**
 * Generates the Tools help page.
 *
 * @since 2.0.0
 */
function acc_settings_tools_help() {
	global $acc_settings_tools;

	$screen = get_current_screen();

	if ( $screen->id !== $acc_settings_tools ) {
		return;
	}

	$screen->set_help_sidebar( acc_get_help_sidebar() );

	$screen->add_help_tab(
		array(
			'id'      => 'acc-settings-general',
			'title'   => __( 'Tools', 'autoclose' ),
			'content' =>
			'<p>' . __( 'This screen gives you a few tools namely one click buttons to run the closing algorithm or open comments, pingbacks/trackbacks.', 'autoclose' ) . '</p>' .
				'<p>' . __( 'You can also delete the old settings from prior to v2.0.0', 'autoclose' ) . '</p>',
		)
	);

	do_action( 'acc_settings_help', $screen );

}


/**
 * Get the sidebar for the help.
 *
 * @since 2.0.0
 */
function acc_get_help_sidebar() {
	/* translators: 1: Support link. */
	$message = '<p>' . sprintf( __( 'For more information or how to get support visit the <a href="%1$s" target="_blank">Support site</a>.', 'autoclose' ), esc_url( 'https://webberzone.com/support/' ) ) . '</p>';

	/* translators: 1: Forum link. */
	$message .= '<p>' . sprintf( __( 'Support queries should be posted in the <a href="%1$s" target="_blank">WordPress.org support forums</a>.', 'autoclose' ), esc_url( 'https://wordpress.org/support/plugin/autoclose' ) ) . '</p>';

	$message .= '<p>' . sprintf(
		/* translators: 1: Github Issues link, 2: Github page. */
		__( '<a href="%1$s" target="_blank">Post an issue</a> on <a href="%2$s" target="_blank">GitHub</a> (bug reports only).', 'autoclose' ),
		esc_url( 'https://github.com/ajaydsouza/autoclose/issues' ),
		esc_url( 'https://github.com/ajaydsouza/autoclose' )
	) . '</p>';

	return $message;

}
