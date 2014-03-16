<?php
/*
Plugin Name: Auto-Close Comments, Pingbacks and Trackbacks
Version:     1.4
Plugin URI:  http://ajaydsouza.com/wordpress/plugins/autoclose/
Description: Automatically close Comments, Pingbacks and Trackbacks after certain amount of days.
Author:      Ajay D'Souza
Author URI:  http://ajaydsouza.com/
*/

if ( ! defined( 'ABSPATH' ) ) die( "Aren't you supposed to come here via WP-Admin?" );

define( 'ALD_ACC_DIR', dirname( __FILE__ ) );
define( 'ACC_LOCAL_NAME', 'autoclose' );

// Guess the location
$acc_path = plugin_dir_path( __FILE__ );
$acc_url = plugins_url() . '/' . plugin_basename( dirname( __FILE__ ) );


/**
 * Initialises text domain for l10n.
 * 
 * @access public
 * @return void
 */
function ald_acc_lang_init() {
	load_plugin_textdomain( ACC_LOCAL_NAME, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'ald_acc_lang_init' );


/**
 * Main function.
 * 
 * @access public
 * @return void
 */
function ald_acc() {
    global $wpdb;
    $poststable = $wpdb->posts;
	$acc_settings = acc_read_options();

    $comment_age = $acc_settings['comment_age']. ' DAY';
    $pbtb_age = $acc_settings['pbtb_age']. ' DAY';
    $comment_pids = $acc_settings['comment_pids'];
    $pbtb_pids = $acc_settings['pbtb_pids'];
	
	// What is the time now?
	$now = gmdate( "Y-m-d H:i:s", ( time() + ( get_option( 'gmt_offset' ) * 3600 ) ) );

	// Get the date up to which comments and pings will be closed
	$comment_age = $comment_age - 1;
	$comment_date = strtotime( '-' . $comment_age . ' DAY' , strtotime( $now ) );
	$comment_date = date( 'Y-m-d H:i:s' , $comment_date );

	$pbtb_age = $pbtb_age - 1;
	$pbtb_date = strtotime( '-' . $pbtb_age . ' DAY' , strtotime( $now ) );
	$pbtb_date = date( 'Y-m-d H:i:s' , $pbtb_date );
	
	// Close Comments on posts
	if ( $acc_settings['close_comment'] ) {
		$wpdb->query( $wpdb->prepare( "
			UPDATE $poststable
			SET comment_status = 'closed'
			WHERE comment_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'post'
			AND post_date < '%s'
		", $comment_date ) );
	}
	
	// Close Pingbacks/Trackbacks on posts
	if ( $acc_settings['close_pbtb'] ) {
		$wpdb->query( $wpdb->prepare( "
			UPDATE $poststable
			SET ping_status = 'closed'
			WHERE ping_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'post'
			AND post_date < '%s'
		", $pbtb_date ) );
	}

	// Close Comments on pages
	if ( $acc_settings['close_comment_pages'] ) {
		$wpdb->query( $wpdb->prepare( "
			UPDATE $poststable
			SET comment_status = 'closed'
			WHERE comment_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'page'
			AND post_date < '%s'
		", $comment_date ) );
	}
	
	// Close Pingbacks/Trackbacks on pages
	if ( $acc_settings['close_pbtb_pages'] ) {
		$wpdb->query( $wpdb->prepare( "
			UPDATE $poststable
			SET ping_status = 'closed'
			WHERE ping_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'page'
			AND post_date < '%s'
		", $pbtb_date ) );
	}

	// Open Comments on these posts
	if ( '' != $acc_settings['comment_pids'] ) {
		$wpdb->query( "
			UPDATE $poststable
			SET comment_status = 'open'
			WHERE comment_status = 'closed'
			AND post_status = 'publish'
			AND ID IN ($comment_pids)
		" );
	}
	
	// Open Pingbacks / Trackbacks on these posts
	if ( '' != $acc_settings['pbtb_pids'] ) {
		$wpdb->query( "
			UPDATE $poststable
			SET ping_status = 'open'
			WHERE ping_status = 'closed'
			AND post_status = 'publish'
			AND ID IN ($pbtb_pids)
		" );
	}

	// Delete Post Revisions (WordPress 2.6 and above)
	if ( $acc_settings['delete_revisions'] ) {
		$wpdb->query( "
			DELETE FROM $poststable
			WHERE post_type = 'revision'
		" );
	}
}
add_action( 'ald_acc_hook', 'ald_acc' );


/**
 * Default options.
 * 
 * @access public
 * @return void
 */
function acc_default_options() {
	$acc_settings = array (
						'comment_age' => '90',	// Close comments before these many days
						'pbtb_age' => '90',		// Close pingbacks/trackbacks before these many days
						'comment_pids' => '',	// Comments on these Post IDs to open
						'pbtb_pids' => '',		// Pingback on these Post IDs to open
						'close_comment' => false,	// Close Comments on posts
						'close_comment_pages' => false,	// Close Comments on pages
						'close_pbtb' => false,		// Close Pingbacks and Trackbacks on posts
						'close_pbtb_pages' => false,		// Close Pingbacks and Trackbacks on pages
						'delete_revisions' => false,		// Delete post revisions
						'daily_run' => false,		// Run Daily?
						'cron_hour' => '0',		// Cron Hour
						'cron_min' => '0',		// Cron Minute
					);
	
	return $acc_settings;
}


/**
 * Function to read options from the database.
 * 
 * @access public
 * @return void
 */
function acc_read_options() {
	$acc_settings_changed = false;
	
	$defaults = acc_default_options();
	
	$acc_settings = array_map( 'stripslashes', (array)get_option( 'ald_acc_settings' ) );
	unset( $acc_settings[0] ); // produced by the (array) casting when there's nothing in the DB
	
	foreach ( $defaults as $k=>$v ) {
		if ( ! isset( $acc_settings[$k] ) ) {
			$acc_settings[$k] = $v;
		}
		$acc_settings_changed = true;	
	}
	if ( true == $acc_settings_changed ) {
		update_option( 'ald_acc_settings', $acc_settings );
	}
	
	return $acc_settings;
}


/**
 * Function to enable run or actions.
 * 
 * @access public
 * @param int $hour
 * @param int $min
 * @return void
 */
function acc_enable_run( $hour, $min ) {
	if ( ! wp_next_scheduled( 'ald_acc_hook' ) ) {
		wp_schedule_event( mktime( $hour, $min, 0, date( "n" ), date( "j" ) + 1, date( "Y" ) ), 'daily', 'ald_acc_hook' );
	} else {
		wp_clear_scheduled_hook( 'ald_acc_hook' );
		wp_schedule_event( mktime( $hour, $min, 0, date( "n" ), date( "j" ) + 1, date( "Y" ) ), 'daily', 'ald_acc_hook' );
	}
}


/**
 * Function to disable daily run or actions.
 * 
 * @access public
 * @return void
 */
function acc_disable_run() {
	if ( wp_next_scheduled( 'ald_acc_hook' ) ) {
		wp_clear_scheduled_hook( 'ald_acc_hook' );
	}
}


// Process the admin page if we're on the admin screen
if ( is_admin() || strstr( $_SERVER['PHP_SELF'], 'wp-admin/' ) ) {
	require_once( ALD_ACC_DIR . "/admin.inc.php" );
	/**
	 * Filter to add link to WordPress plugin action links.
	 * 
	 * @access public
	 * @param array $links
	 * @return array
	 */
	function acc_plugin_actions_links( $links ) {
	
		return array_merge( array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=acc_options' ) . '">' . __( 'Settings', ACC_LOCAL_NAME ) . '</a>'
			), $links );
	
	}
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'acc_plugin_actions_links' );

	/**
	 * Filter to add links to the plugin action row.
	 * 
	 * @access public
	 * @param array $links
	 * @param array $file
	 * @return void
	 */
	function acc_plugin_actions( $links, $file ) {
		static $plugin;
		if ( ! $plugin ) $plugin = plugin_basename( __FILE__ );
	 
		// create link
		if ( $file == $plugin ) {
			$links[] = '<a href="http://wordpress.org/support/plugin/autoclose">' . __( 'Support', ACC_LOCAL_NAME ) . '</a>';
			$links[] = '<a href="http://ajaydsouza.com/donate/">' . __( 'Donate', ACC_LOCAL_NAME ) . '</a>';
		}
		return $links;
	}
	
	global $wp_version;
	if ( version_compare( $wp_version, '2.8alpha', '>' ) ) {
		add_filter( 'plugin_row_meta', 'acc_plugin_actions', 10, 2 ); // only 2.8 and higher
	} else {
		add_filter( 'plugin_action_links', 'acc_plugin_actions', 10, 2 );
	}
	
} // End admin.inc

?>