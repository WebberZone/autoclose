<?php
/**
 * Helper functions.
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
 * @since   1.0
 *
 * @param mixed $post_types_input Post types array or csv string.
 * @return array Options for the database. Will add any missing options.
 */
function acc_parse_post_types( $post_types_input ) {

	// If post_types is empty or contains a query string then use parse_str else consider it comma-separated.
	if ( ! empty( $post_types_input ) && is_array( $post_types_input ) ) {
		$post_types = $post_types_input;
	} elseif ( ! empty( $post_types_input ) && false === strpos( $post_types_input, '=' ) ) {
		$post_types = explode( ',', $post_types_input );
	} else {
		parse_str( $post_types_input, $post_types );  // Save post types in $post_types variable.
	}

	// If post_types is empty or if we want all the post types.
	if ( empty( $post_types ) || 'all' === $post_types_input ) {
		$post_types = get_post_types(
			array(
				'public' => true,
			)
		);
	}

	return $post_types;
}


/**
 * Function to read options from the database.
 *
 * @since 2.0.0
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_open_comments() {
	global $wpdb;

	$result = $wpdb->query( "
		UPDATE {$wpdb->posts}
		SET comment_status = 'open'
		WHERE comment_status = 'closed'
	" );

	return $result;
}


/**
 * Open pingbacks/trackbacks.
 *
 * @since 2.0.0
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_open_pingtracks() {
	global $wpdb;

	$result = $wpdb->query( "
		UPDATE {$wpdb->posts}
		SET ping_status = 'open'
		WHERE ping_status = 'closed'
	" );

	return $result;
}

