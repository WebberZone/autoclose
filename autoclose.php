<?php
/**
 * Auto-Close Comments, Pingbacks and Trackbacks
 *
 * Automatically close Comments, Pingbacks and Trackbacks after certain amount of days.
 *
 * @package AutoClose
 * @author  Ajay D'Souza
 * @license GPL-2.0+
 * @link    http://ajaydsouza.com
 * @copyright   2008-2019 Ajay D'Souza
 *
 * @wordpress-plugin
 * Plugin Name: Auto-Close Comments, Pingbacks and Trackbacks
 * Plugin URI:  http://ajaydsouza.com/wordpress/plugins/autoclose/
 * Description: Automatically close Comments, Pingbacks and Trackbacks after certain amount of days.
 * Version:     2.0.0-beta1
 * Author:      Ajay D'Souza
 * Author URI:  http://ajaydsouza.com/
 * Text Domain: autoclose
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/ajaydsouza/autoclose/
 */

// If this file is called directly, then abort execution.
if ( ! defined( 'WPINC' ) ) {
	die( "Aren't you supposed to come here via WP-Admin?" );
}


/**
 * Holds the filesystem directory path (with trailing slash) for AutoClose
 *
 * @since 2.0.0
 *
 * @var string Plugin folder path
 */
if ( ! defined( 'ACC_PLUGIN_DIR' ) ) {
	define( 'ACC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Holds the filesystem directory path (with trailing slash) for AutoClose
 *
 * @since 2.0.0
 *
 * @var string Plugin folder URL
 */
if ( ! defined( 'ACC_PLUGIN_URL' ) ) {
	define( 'ACC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Holds the filesystem directory path (with trailing slash) for AutoClose
 *
 * @since 2.0.0
 *
 * @var string Plugin Root File
 */
if ( ! defined( 'ACC_PLUGIN_FILE' ) ) {
	define( 'ACC_PLUGIN_FILE', __FILE__ );
}

/**
 * Global variable holding the current settings for AutoClose
 *
 * @since 2.0.0
 *
 * @var array
 */
global $acc_settings;
$acc_settings = acc_get_settings();


/**
 * Get Settings.
 *
 * Retrieves all plugin settings
 *
 * @since  2.5.0
 * @return array AutoClose settings
 */
function acc_get_settings() {

	$settings = get_option( 'acc_settings' );

	/**
	 * Settings array
	 *
	 * Retrieves all plugin settings
	 *
	 * @since 2.0.0
	 * @param array $settings Settings array
	 */
	return apply_filters( 'acc_get_settings', $settings );
}


/**
 * Main function.
 *
 * @since 2.0.0
 */
function acc_main() {
	global $wpdb, $acc_settings;

	$acc_settings = acc_get_settings();

	$comment_age      = $acc_settings['comment_age'];
	$pbtb_age         = $acc_settings['pbtb_age'];
	$comment_pids     = $acc_settings['comment_pids'];
	$pbtb_pids        = $acc_settings['pbtb_pids'];
	$delete_revisions = $acc_settings['delete_revisions'];

	// Get the post types.
	$comment_post_types = acc_parse_post_types( $acc_settings['comment_post_types'] );
	$pbtb_post_types    = acc_parse_post_types( $acc_settings['pbtb_post_types'] );

	// What is the time now?
	$now = gmdate( 'Y-m-d H:i:s', ( time() + ( get_option( 'gmt_offset' ) * 3600 ) ) );

	// Get the date up to which comments and pings will be closed
	$comment_age  = $comment_age - 1;
	$comment_date = strtotime( '-' . $comment_age . ' DAY', strtotime( $now ) );
	$comment_date = date( 'Y-m-d H:i:s', $comment_date );

	$pbtb_age  = $pbtb_age - 1;
	$pbtb_date = strtotime( '-' . $pbtb_age . ' DAY', strtotime( $now ) );
	$pbtb_date = date( 'Y-m-d H:i:s', $pbtb_date );

	// Close Comments on posts
	if ( $acc_settings['close_comment'] ) {
		// Prepare the query
		$acc_settings = array(
			$comment_date,
		);
		$sql          = "
                UPDATE $wpdb->posts
                SET comment_status = 'closed'
                WHERE comment_status = 'open'
                AND post_date < '%s'
		";
		$sql         .= ' AND ( ';
		$multiple     = false;
		foreach ( $comment_post_types as $post_type ) {
			if ( $multiple ) {
				$sql .= ' OR '; }
			$sql           .= " post_type = '%s'";
			$multiple       = true;
			$acc_settings[] = $post_type;   // Add the post types to the $acc_settings array
		}
		$sql .= ' ) ';

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $acc_settings ) );
	}

	// Close Pingbacks/Trackbacks on posts
	if ( $acc_settings['close_pbtb'] ) {
		// Prepare the query
		$acc_settings = array(
			$pbtb_date,
		);
		$sql          = "
                UPDATE $wpdb->posts
                SET ping_status = 'closed'
                WHERE ping_status = 'open'
                AND post_date < '%s'
		";
		$sql         .= ' AND ( ';
		$multiple     = false;
		foreach ( $pbtb_post_types as $post_type ) {
			if ( $multiple ) {
				$sql .= ' OR '; }
			$sql           .= " post_type = '%s'";
			$multiple       = true;
			$acc_settings[] = $post_type;   // Add the post types to the $acc_settings array
		}
		$sql .= ' ) ';

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $acc_settings ) );
	}

	// Open Comments on these posts
	if ( '' != $comment_pids ) {
		$wpdb->query(
			"
            UPDATE $wpdb->posts
            SET comment_status = 'open'
            WHERE comment_status = 'closed'
            AND ID IN ($comment_pids)
		"
		);
	}

	// Open Pingbacks / Trackbacks on these posts
	if ( '' != $pbtb_pids ) {
		$wpdb->query(
			"
            UPDATE $wpdb->posts
            SET ping_status = 'open'
            WHERE ping_status = 'closed'
            AND ID IN ($pbtb_pids)
		"
		);
	}

	// Delete Post Revisions (WordPress 2.6 and above)
	if ( $delete_revisions ) {
		$wpdb->query(
			"
            DELETE FROM $wpdb->posts
            WHERE post_type = 'revision'
		"
		);
	}
}


/*
 *---------------------------------------------------------------------------*
 * AutoClose modules
 *---------------------------------------------------------------------------*
 */

require_once ACC_PLUGIN_DIR . 'includes/admin/default-settings.php';
require_once ACC_PLUGIN_DIR . 'includes/admin/register-settings.php';
require_once ACC_PLUGIN_DIR . 'includes/cron.php';
require_once ACC_PLUGIN_DIR . 'includes/l10n.php';
require_once ACC_PLUGIN_DIR . 'includes/helpers.php';


/*
 *---------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *---------------------------------------------------------------------------*
 */

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

	require_once ACC_PLUGIN_DIR . 'includes/admin/admin.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/settings-page.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/save-settings.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/help-tab.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/tools.php';

}

/*
 *---------------------------------------------------------------------------*
 * Deprecated functions
 *---------------------------------------------------------------------------*
 */

require_once ACC_PLUGIN_DIR . 'includes/deprecated.php';

