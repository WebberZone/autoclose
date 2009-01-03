<?php
/*
Plugin Name: Auto-Close Comments, Pingbacks and Trackbacks
Version:     1.3.1
Plugin URI:  http://ajaydsouza.com/wordpress/plugins/autoclose/
Description: Automatically close Comments, Pingbacks and Trackbacks after certain amount of days.  <a href="options-general.php?page=acc_options">Configure...</a>
Author:      Ajay D'Souza
Author URI:  http://ajaydsouza.com/
*/

if (!defined('ABSPATH')) die("Aren't you supposed to come here via WP-Admin?");

function ald_autoclose_init() {
     load_plugin_textdomain('ald_autoclose_plugin', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)));
}
add_action('init', 'ald_autoclose_init');

define('ALD_ACC_DIR', dirname(__FILE__));

/*********************************************************************
*				Main Function (Do not edit)					*
********************************************************************/

add_action('ald_acc_hook', 'ald_acc');
function ald_acc() {
    global $wpdb;
    $poststable = $wpdb->posts;
	$acc_settings = acc_read_options();

    $comment_age = $acc_settings[comment_age]. ' DAY';
    $pbtb_age = $acc_settings[pbtb_age]. ' DAY';
    $comment_pids = $acc_settings[comment_pids];
    $pbtb_pids = $acc_settings[pbtb_pids];
	
	// Close Comments on posts
	if ($acc_settings[close_comment]) {
		$comment_date = $wpdb->get_var("
			SELECT DATE_ADD(DATE_SUB(CURDATE(), INTERVAL $comment_age), INTERVAL 1 DAY)
		");

		$wpdb->query("
			UPDATE $poststable
			SET comment_status = 'closed'
			WHERE comment_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'post'
			AND post_date < '$comment_date'
		");
	}
	
	// Close Pingbacks/Trackbacks on posts
	if ($acc_settings[close_pbtb]) {
		$pbtb_date = $wpdb->get_var("
			SELECT DATE_ADD(DATE_SUB(CURDATE(), INTERVAL $pbtb_age), INTERVAL 1 DAY)
		");

		$wpdb->query("
			UPDATE $poststable
			SET ping_status = 'closed'
			WHERE ping_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'post'
			AND post_date < '$pbtb_date'
		");
	}

	// Close Comments on pages
	if ($acc_settings[close_comment_pages]) {
		$comment_date = $wpdb->get_var("
			SELECT DATE_ADD(DATE_SUB(CURDATE(), INTERVAL $comment_age), INTERVAL 1 DAY)
		");

		$wpdb->query("
			UPDATE $poststable
			SET comment_status = 'closed'
			WHERE comment_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'page'
			AND post_date < '$comment_date'
		");
	}
	
	// Close Pingbacks/Trackbacks on pages
	if ($acc_settings[close_pbtb_pages]) {
		$pbtb_date = $wpdb->get_var("
			SELECT DATE_ADD(DATE_SUB(CURDATE(), INTERVAL $pbtb_age), INTERVAL 1 DAY)
		");

		$wpdb->query("
			UPDATE $poststable
			SET ping_status = 'closed'
			WHERE ping_status = 'open'
			AND post_status = 'publish'
			AND post_type = 'page'
			AND post_date < '$pbtb_date'
		");
	}

	// Open Comments on these posts
	if ($acc_settings[comment_pids]!='') {
		$wpdb->query("
			UPDATE $poststable
			SET comment_status = 'open'
			WHERE comment_status = 'closed'
			AND post_status = 'publish'
			AND ID IN ($comment_pids)
		");
	}
	// Open Pingbacks / Trackbacks on these posts
	if ($acc_settings[pbtb_pids]!='') {
		$wpdb->query("
			UPDATE $poststable
			SET ping_status = 'open'
			WHERE ping_status = 'closed'
			AND post_status = 'publish'
			AND ID IN ($pbtb_pids)
		");
	}

	// Delete Post Revisions (WordPress 2.6 and above)
	if ($acc_settings[delete_revisions]) {
		$wpdb->query("
			DELETE FROM $poststable
			WHERE post_type = 'revision'
		");
	}
}

// Default Options
function acc_default_options() {
	$acc_settings = 	Array (
						comment_age => '90',	// Close comments before these many days
						pbtb_age => '90',		// Close pingbacks/trackbacks before these many days
						comment_pids => '',	// Comments on these Post IDs to open
						pbtb_pids => '',		// Pingback on these Post IDs to open
						close_comment => false,	// Close Comments on posts
						close_comment_pages => false,	// Close Comments on pages
						close_pbtb => false,		// Close Pingbacks and Trackbacks on posts
						close_pbtb_pages => false,		// Close Pingbacks and Trackbacks on pages
						delete_revisions => true,		// Delete post revisions
						daily_run => false,		// Run Daily?
						cron_hour => '0',		// Cron Hour
						cron_min => '0',		// Cron Minute
						);
	
	return $acc_settings;
}

// Function to read options from the database
function acc_read_options() 
{
	$acc_settings_changed = false;
	
	$defaults = acc_default_options();
	
	$acc_settings = array_map('stripslashes',(array)get_option('ald_acc_settings'));
	unset($acc_settings[0]); // produced by the (array) casting when there's nothing in the DB
	
	foreach ($defaults as $k=>$v) {
		if (!isset($acc_settings[$k]))
			$acc_settings[$k] = $v;
		$acc_settings_changed = true;	
	}
	if ($acc_settings_changed == true)
		update_option('ald_acc_settings', $acc_settings);
	
	return $acc_settings;

}

// Function to enable run or actions
function acc_enable_run($hour, $min)
{
	if (function_exists('wp_schedule_event'))
	{
		// Invoke WordPress 2.1 internal cron
		if (!wp_next_scheduled('ald_acc_hook')) {
			wp_schedule_event( mktime($hour,$min), 'daily', 'ald_acc_hook' );
		}
		else
		{
			wp_clear_scheduled_hook('ald_acc_hook');
			wp_schedule_event( mktime($hour,$min), 'daily', 'ald_acc_hook' );
		}
	}
	else
	{
		add_action('publish_post',   'ald_acc', 7);
		add_action('comment_post',   'ald_acc', 7);
		add_action('trackback_post', 'ald_acc', 7);
		add_action('pingback_post',  'ald_acc', 7);
	}
}

// Function to disable daily run or actions
function acc_disable_run()
{
	if (function_exists('wp_schedule_event'))
	{
		if (wp_next_scheduled('ald_acc_hook')) {
			wp_clear_scheduled_hook('ald_acc_hook');
		}
	}
	else
	{
		remove_action('publish_post',   'ald_acc');
		remove_action('comment_post',   'ald_acc');
		remove_action('trackback_post', 'ald_acc');
		remove_action('pingback_post',  'ald_acc');
	}
}

// Function to add weekly and fortnightly recurrences - Sample Code courtesy http://blog.slaven.net.au/archives/2007/02/01/timing-is-everything-scheduling-in-wordpress/
if (!function_exists('ald_more_reccurences')) {
function ald_more_reccurences() {
	return array(
		'weekly' => array('interval' => 604800, 'display' => 'Once Weekly'),
		'fortnightly' => array('interval' => 1209600, 'display' => 'Once Fortnightly'),
		'monthly' => array('interval' => 2419200, 'display' => 'Once Monthly'),
	);
}
add_filter('cron_schedules', 'ald_more_reccurences');
}


// This function adds an Options page in WP Admin
if (is_admin() || strstr($_SERVER['PHP_SELF'], 'wp-admin/')) {
	require_once(ALD_ACC_DIR . "/admin.inc.php");
}

?>