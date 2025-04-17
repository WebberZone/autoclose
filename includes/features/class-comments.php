<?php
/**
 * Comments management.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Utilities\Options;
use WebberZone\AutoClose\Utilities\Helpers;

/**
 * Comments class.
 *
 * @since 3.0.0
 */
class Comments {

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Constructor code.
	}

	/**
	 * Process comments based on settings.
	 *
	 * @since 3.0.0
	 */
	public function process_comments() {
		$comment_age  = Options::get_option( 'comment_age' );
		$comment_pids = Options::get_option( 'comment_pids' );
		$pbtb_age     = Options::get_option( 'pbtb_age' );
		$pbtb_pids    = Options::get_option( 'pbtb_pids' );

		// Get the post types.
		$comment_post_types = Helpers::parse_post_types( Options::get_option( 'comment_post_types' ) );
		$pbtb_post_types    = Helpers::parse_post_types( Options::get_option( 'pbtb_post_types' ) );

		// Close Comments on posts.
		if ( Options::get_option( 'close_comment' ) ) {
			$this->close_comments(
				array(
					'age'        => $comment_age,
					'post_types' => $comment_post_types,
				)
			);
		}

		// Close Pingbacks/Trackbacks on posts.
		if ( Options::get_option( 'close_pbtb' ) ) {
			$this->close_pingbacks(
				array(
					'age'        => $pbtb_age,
					'post_types' => $pbtb_post_types,
				)
			);
		}

		// Open Comments on these posts.
		if ( ! empty( $comment_pids ) ) {
			$this->open_comments(
				array(
					'post_ids' => $comment_pids,
				)
			);
		}

		// Open Pingbacks / Trackbacks on these posts.
		if ( ! empty( $pbtb_pids ) ) {
			$this->open_pingbacks(
				array(
					'post_ids' => $pbtb_pids,
				)
			);
		}
	}

	/**
	 * Function to open/close comments or pingback/trackbacks
	 *
	 * @since 3.0.0
	 *
	 * @param string       $type   'comment' or 'ping'.
	 * @param string       $action 'open' or 'close'.
	 * @param string|array $args   Optional arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function edit_discussions( $type = 'comment', $action = 'open', $args = array() ) {
		global $wpdb;

		$sql = '';

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

		if ( ! in_array( $type, array( 'comment', 'ping' ), true ) || false === $action ) {
			return false;
		}

		$defaults = array(
			'age'        => 0,
			'post_types' => array(),
			'post_ids'   => '',
		);

		// Parse incoming $args into an array and merge it with $defaults.
		$args = wp_parse_args( $args, $defaults );

		$current_time = strtotime( current_time( 'mysql' ) );
		$close_date   = $current_time - ( max( 0, ( $args['age'] - 1 ) ) * DAY_IN_SECONDS );
		$close_date   = gmdate( 'Y-m-d H:i:s', $close_date );

		$sql = "UPDATE {$wpdb->posts} ";

		$sql .= $wpdb->prepare( " SET {$type}_status = %s ", $new_status ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql .= $wpdb->prepare( " WHERE {$type}_status = %s ", $old_status ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $args['age'] > 0 ) {
			$sql .= $wpdb->prepare( " AND $wpdb->posts.post_date < %s ", $close_date );
		}

		if ( ! empty( $args['post_types'] ) ) {
			$post_types = wp_parse_list( $args['post_types'] );

			$sql .= " AND $wpdb->posts.post_type IN ('" . join( "', '", $post_types ) . "') ";
		}

		if ( ! empty( $args['post_ids'] ) ) {
			$post_ids = wp_parse_id_list( $args['post_ids'] );

			$sql .= " AND $wpdb->posts.ID IN ( {$post_ids} )";
		}

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		return $result;
	}

	/**
	 * Open comments.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function open_comments( $args = array() ) {
		return $this->edit_discussions( 'comment', 'open', $args );
	}

	/**
	 * Close comments.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function close_comments( $args = array() ) {
		return $this->edit_discussions( 'comment', 'close', $args );
	}

	/**
	 * Open pingbacks/trackbacks.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function open_pingbacks( $args = array() ) {
		return $this->edit_discussions( 'ping', 'open', $args );
	}

	/**
	 * Close pingbacks/trackbacks.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function close_pingbacks( $args = array() ) {
		return $this->edit_discussions( 'ping', 'close', $args );
	}

	/**
	 * Delete pingbacks/trackbacks.
	 *
	 * @since 3.0.0
	 *
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function delete_pingbacks() {
		global $wpdb;

		$post_ids = array();

		$comments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			"
			SELECT comment_ID, comment_post_ID FROM {$wpdb->comments}
			WHERE comment_type IN ('pingback', 'trackback')
			",
			ARRAY_A
		);

		if ( $comments ) {
			foreach ( $comments as $comment ) {
				wp_delete_comment( $comment['comment_ID'], true );
				$post_ids[] = $comment['comment_post_ID'];
			}
			$post_ids = array_unique( $post_ids );
			foreach ( $post_ids as $post_id ) {
				clean_post_cache( $post_id );
			}
		}

		return count( $comments );
	}
}
