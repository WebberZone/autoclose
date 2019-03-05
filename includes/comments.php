<?php
/**
 * Functions to modify comments, pingbacks and trackbacks.
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
 * Function to open/close comments or pingback/trackbacks
 *
 * @since 2.0.0
 *
 * @param string $type   'comment' or 'ping'.
 * @param string $action 'open' or 'close'.
 * @param array  $args   Array of arguments.
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_edit_discussions( $type = 'comment', $action = 'open', $args = array() ) {
	global $wpdb;

	$sql = '';

	switch ( $type ) {
		case 'comment':
			$type = 'comment';
			break;
		case 'ping':
			$type = 'ping';
			break;
		default:
			return false;
	}

	switch ( $action ) {
		case 'close':
			$old_status = 'open';
			$new_status = 'close';
			break;
		case 'open':
			$old_status = 'close';
			$new_status = 'open';
			break;
		default:
			return false;
	}

	$defaults = array(
		'age'        => 0,
		'post_types' => array(),
		'post_ids'   => '',
	);

	// Parse incomming $args into an array and merge it with $defaults.
	$args = wp_parse_args( $args, $defaults );

	$current_time = current_time( 'timestamp', 0 );
	$close_date   = $current_time - ( max( 0, ( $args['age'] - 1 ) ) * DAY_IN_SECONDS );
	$close_date   = gmdate( 'Y-m-d H:i:s', $close_date );

	$sql = "UPDATE {$wpdb->posts} ";

	$sql .= $wpdb->prepare( " SET {$type}_status = %s ", $new_status );
	$sql .= $wpdb->prepare( " WHERE {$type}_status = %s ", $old_status );

	if ( $args['age'] > 0 ) {
		$sql .= $wpdb->prepare( " AND $wpdb->posts.post_date < %s ", $close_date );
	}

	if ( ! empty( $args['post_types'] ) ) {

		$sql .= " AND $wpdb->posts.post_type IN ('" . join( "', '", $args['post_types'] ) . "') ";

	}

	if ( ! empty( $args['post_ids'] ) ) {

		$sql .= " AND $wpdb->posts.ID IN ( {$args['post_ids']} )";

	}

	$result = $wpdb->query( $sql );

	return $result;
}


/**
 * Open comments/pingbacks.
 *
 * @since 2.0.0
 *
 * @param string $type 'comment' or 'ping'.
 * @param array  $args Array of arguments.
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_open_discussions( $type = 'comment', $args = array() ) {

	return acc_edit_discussions( $type, 'open', $args );

}

/**
 * Close comments/pingbacks.
 *
 * @since 2.0.0
 *
 * @param string $type 'comment' or 'ping'.
 * @param array  $args Array of arguments.
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_close_discussions( $type = 'comment', $args = array() ) {

	return acc_edit_discussions( $type, 'close', $args );

}


/**
 * Open comments.
 *
 * @since 2.0.0
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_open_comments() {

	return acc_edit_discussions( 'comment', 'open' );

}


/**
 * Open pingbacks/trackbacks.
 *
 * @since 2.0.0
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_open_pingtracks() {

	return acc_edit_discussions( 'ping', 'open' );

}


/**
 * Close comments.
 *
 * @since 2.0.0
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_close_comments() {

	return acc_edit_discussions( 'comment', 'close' );

}


/**
 * Close pingbacks/trackbacks.
 *
 * @since 2.0.0
 *
 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
 */
function acc_close_pingtracks() {

	return acc_edit_discussions( 'ping', 'close' );

}


