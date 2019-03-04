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

	$screen->set_help_sidebar(
		/* translators: 1: Support link. */
		'<p>' . sprintf( __( 'For more information or how to get support visit the <a href="%1$s">WebberZone support site</a>.', 'autoclose' ), esc_url( 'https://ajaydsouza.com/support/' ) ) . '</p>' .
		/* translators: 1: Forum link. */
		'<p>' . sprintf( __( 'Support queries should be posted in the <a href="%1$s">WordPress.org support forums</a>.', 'autoclose' ), esc_url( 'https://wordpress.org/support/plugin/autoclose' ) ) . '</p>' .
		'<p>' . sprintf(
			/* translators: 1: Github Issues link, 2: Github page. */
			__( '<a href="%1$s">Post an issue</a> on <a href="%2$s">GitHub</a> (bug reports only).', 'autoclose' ),
			esc_url( 'https://github.com/ajaydsouza/autoclose/issues' ),
			esc_url( 'https://github.com/ajaydsouza/autoclose' )
		) . '</p>'
	);

	$screen->add_help_tab(
		array(
			'id'      => 'acc-settings-general',
			'title'   => __( 'General', 'autoclose' ),
			'content' =>
			'<p>' . __( 'This screen provides the basic settings for configuring your knowledge base.', 'autoclose' ) . '</p>' .
				'<p>' . __( 'Set the knowledge base slugs which drive what the urls are for the knowledge base homepage, articles, categories and tags.', 'autoclose' ) . '</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'acc-settings-styles',
			'title'   => __( 'Styles', 'autoclose' ),
			'content' =>
			'<p>' . __( 'This screen provides options to control the look and feel of the knowledge base.', 'autoclose' ) . '</p>' .
				'<p>' . __( 'Disable the styles included within the plugin and/or add your own CSS styles to customize this.', 'autoclose' ) . '</p>',
		)
	);

	do_action( 'acc_settings_help', $screen );

}
