<?php
/**
 * Backward compatibility functions.
 *
 * @package    AutoClose
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( "Aren't you supposed to come here via WP-Admin?" );
}

use WebberZone\AutoClose\Util\Options;
use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;

/**
 * Get Settings.
 *
 * Retrieves all plugin settings
 *
 * @since  2.0.0
 * @return array AutoClose settings
 */
function acc_get_settings() {
	return Options::get_options();
}

/**
 * Register settings function
 *
 * @since 2.0.0
 */
function acc_register_settings() {
	// This is now handled by the AutoClose class.
}

/**
 * Function to close comments.
 *
 * @since 1.0.0
 */
function acc_close_comments() {
	$comments = new Comments();
	$comments->close_comments();
}

/**
 * Function to close pingbacks/trackbacks.
 *
 * @since 1.0.0
 */
function acc_close_trackbacks() {
	$comments = new Comments();
	$comments->close_pingbacks();
}

/**
 * Function to delete post revisions.
 *
 * @since 1.0.0
 */
function acc_delete_revisions() {
	$revisions = new Revisions();
	$revisions->delete_revisions();
}

/**
 * Function to run the cron.
 *
 * @since 1.0.0
 */
function acc_run_cron() {
	$comments = new Comments();
	$comments->process_comments();

	$revisions = new Revisions();
	$revisions->process_revisions();
}
