<?php
/**
 * Default settings.
 *
 * Functions to get the default settings for the plugin.
 *
 * @since 2.0.0
 *
 * @package AutoClose
 * @subpackage Admin/Register_Settings
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Retrieve the array of plugin settings
 *
 * @since 2.0.0
 *
 * @return array Settings array
 */
function acc_get_registered_settings() {

	$acc_settings = array(
		'general'    => acc_settings_general(),
		'comments'   => acc_settings_comments(),
		'pingtracks' => acc_settings_pingtracks(),
	);

	/**
	 * Filters the settings array
	 *
	 * @since 2.0.0
	 *
	 * @param array $acc_setings Settings array
	 */
	return apply_filters( 'acc_registered_settings', $acc_settings );

}

/**
 * Retrieve the array of General settings
 *
 * @since 2.0.0
 *
 * @return array General settings array
 */
function acc_settings_general() {

	$settings = array(
		'close_comment'    => array(
			'id'      => 'close_comment',
			'name'    => esc_html__( 'Close comments', 'autoclose' ),
			'desc'    => esc_html__( 'Additional settings have to be configured on the Comments tab', 'autoclose' ),
			'type'    => 'checkbox',
			'options' => false,
		),
		'close_pbtb'       => array(
			'id'      => 'close_pbtb',
			'name'    => esc_html__( 'Close Pingbacks/Trackbacks', 'autoclose' ),
			'desc'    => esc_html__( 'Additional settings have to be configured on the Pingbacks/Trackbacks tab', 'autoclose' ),
			'type'    => 'checkbox',
			'options' => false,
		),
		'delete_revisions' => array(
			'id'      => 'delete_revisions',
			'name'    => esc_html__( 'Delete post revisions', 'autoclose' ),
			'desc'    => esc_html__( 'The WordPress revisions system stores a record of each saved draft or published update. This can gather up a lot of overhead in the long run. Use this option to delete old post revisions.', 'autoclose' ),
			'type'    => 'checkbox',
			'options' => false,
		),
		'cron_header'      => array(
			'id'   => 'cron_header',
			'name' => '<h3>' . esc_html__( 'Automatic Closing', 'autoclose' ) . '</h3>',
			'desc' => '',
			'type' => 'header',
		),
		'cron_on'          => array(
			'id'      => 'cron_on',
			'name'    => esc_html__( 'Activate scheduled closing', 'autoclose' ),
			'desc'    => esc_html__( 'This creates a WordPress cron job. Comments and/or pingbacks/trackbacks will be closed at time and schedule specified below.', 'autoclose' ),
			'type'    => 'checkbox',
			'options' => false,
		),
		'cron_range_desc'  => array(
			'id'   => 'cron_range_desc',
			'name' => '<strong>' . esc_html__( 'Time to run closing', 'autoclose' ) . '</strong>',
			'desc' => esc_html__( 'The next two options allow you to set the time to run the cron. The cron job will run now if the hour:min set below if before the current time. e.g. if the time now is 20:30 hours and you set the schedule to 9:00. Else it will run later today at the scheduled time.', 'autoclose' ),
			'type' => 'descriptive_text',
		),
		'cron_hour'        => array(
			'id'      => 'cron_hour',
			'name'    => esc_html__( 'Hour', 'autoclose' ),
			'desc'    => '',
			'type'    => 'number',
			'options' => '0',
			'min'     => '0',
			'max'     => '23',
			'size'    => 'small',
		),
		'cron_min'         => array(
			'id'      => 'cron_min',
			'name'    => esc_html__( 'Minute', 'autoclose' ),
			'desc'    => '',
			'type'    => 'number',
			'options' => '0',
			'min'     => '0',
			'max'     => '59',
			'size'    => 'small',
		),
		'cron_recurrence'  => array(
			'id'      => 'cron_recurrence',
			'name'    => esc_html__( 'Run maintenance', 'autoclose' ),
			'desc'    => '',
			'type'    => 'radio',
			'default' => 'daily',
			'options' => array(
				'daily'       => esc_html__( 'Daily', 'autoclose' ),
				'weekly'      => esc_html__( 'Weekly', 'autoclose' ),
				'fortnightly' => esc_html__( 'Fortnightly', 'autoclose' ),
				'monthly'     => esc_html__( 'Monthly', 'autoclose' ),
			),
		),
	);

	/**
	 * Filters the General settings array
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings General settings array
	 */
	return apply_filters( 'acc_settings_general', $settings );
}


/**
 * Retrieve the array of Comments settings
 *
 * @since 2.0.0
 *
 * @return array Comments settings array
 */
function acc_settings_comments() {

	$settings = array(
		'comment_post_types' => array(
			'id'      => 'comment_post_types',
			'name'    => esc_html__( 'Post types to include', 'autoclose' ),
			'desc'    => esc_html__( 'At least one option should be selected above. Select which post types on which you want comments closed.', 'autoclose' ),
			'type'    => 'posttypes',
			'options' => 'post',
		),
		'comment_age'        => array(
			'id'      => 'comment_age',
			'name'    => esc_html__( 'Close comments on posts/pages older than', 'autoclose' ),
			'desc'    => esc_html__( 'Comments that are older than the above number, in days, will be closed automatically if the schedule is enabled', 'autoclose' ),
			'type'    => 'number',
			'options' => '90',
		),
		'comment_pids'       => array(
			'id'      => 'comment_pids',
			'name'    => esc_html__( 'Keep comments on these posts/pages open', 'autoclose' ),
			'desc'    => esc_html__( 'Comma-separated list of post, page or custom post type IDs. e.g. 188,320,500', 'autoclose' ),
			'type'    => 'numbercsv',
			'options' => '',
			'size'    => 'large',
		),
	);

	/**
	 * Filters the Comments settings array
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings Comments settings array
	 */
	return apply_filters( 'acc_settings_comments', $settings );
}


/**
 * Retrieve the array of Pingbacks/Trackbacks settings
 *
 * @since 2.0.0
 *
 * @return array Pingbacks/Trackbacks settings array
 */
function acc_settings_pingtracks() {

	$settings = array(
		'pbtb_post_types' => array(
			'id'      => 'pbtb_post_types',
			'name'    => esc_html__( 'Post types to include', 'autoclose' ),
			'desc'    => esc_html__( 'At least one option should be selected above. Select which post types on which you want pingbacks/trackbacks closed.', 'autoclose' ),
			'type'    => 'posttypes',
			'options' => 'post',
		),
		'pbtb_age'        => array(
			'id'      => 'pbtb_age',
			'name'    => esc_html__( 'Close pingbacks/trackbacks on posts/pages older than', 'autoclose' ),
			'desc'    => esc_html__( 'Pingbacks/Trackbacks that are older than the above number, in days, will be closed automatically if the schedule is enabled', 'autoclose' ),
			'type'    => 'number',
			'options' => '90',
		),
		'pbtb_pids'       => array(
			'id'      => 'pbtb_pids',
			'name'    => esc_html__( 'Keep pingbacks/trackbacks on these posts/pages open', 'autoclose' ),
			'desc'    => esc_html__( 'Comma-separated list of post, page or custom post type IDs. e.g. 188,320,500', 'autoclose' ),
			'type'    => 'numbercsv',
			'options' => '',
			'size'    => 'large',
		),
	);

	/**
	 * Filters the Pingbacks/Trackbacks settings array
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings Pingbacks/Trackbacks settings array
	 */
	return apply_filters( 'acc_settings_pingtracks', $settings );
}


/**
 * Upgrade pre v2.0.0 settings.
 *
 * @since v2.0.0
 *
 * @return array Settings array
 */
function acc_upgrade_settings() {
	$old_settings = get_option( 'ald_acc_settings' );

	if ( empty( $old_settings ) ) {
		return false;
	}

	// Start will assigning all the old settings to the new settings and we will unset later on.
	$settings = $old_settings;

	$settings['cron_on'] = $old_settings['daily_run'];

	// Rename the cron job.
	if ( wp_next_scheduled( 'ald_acc_hook' ) ) {
		$next_event = wp_get_scheduled_event( 'ald_acc_hook' );
		wp_schedule_event( $next_event->timestamp, $next_event->schedule, 'acc_cron_hook' );
		wp_clear_scheduled_hook( 'ald_acc_hook' );
	}


	return $settings;
}


