<?php
/**
 * Functions to handle post revisions.
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
 * Function to read options from the database.
 *
 * @since 2.0.0
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_delete_revisions() {
	global $wpdb;

	$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"
		DELETE FROM {$wpdb->posts}
		WHERE post_type = 'revision'
		"
	);

	return $result;
}


