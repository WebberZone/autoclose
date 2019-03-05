<?php
/**
 * Main function.
 *
 * @since 2.0.0
 * @package AutoClose
 */

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

	// Close Comments on posts.
	if ( $acc_settings['close_comment'] ) {
		acc_close_discussions(
			'comment',
			array(
				'age'        => $comment_age,
				'post_types' => $comment_post_types,
			)
		);
	}

	// Close Pingbacks/Trackbacks on posts.
	if ( $acc_settings['close_pbtb'] ) {
		acc_close_discussions(
			'ping',
			array(
				'age'        => $pbtb_age,
				'post_types' => $pbtb_post_types,
			)
		);
	}

	// Open Comments on these posts
	if ( ! empty( $comment_pids ) ) {
		acc_open_discussions(
			'comment',
			array(
				'post_ids' => $comment_pids,
			)
		);
	}

	// Open Pingbacks / Trackbacks on these posts
	if ( ! empty( $pbtb_pids ) ) {
		acc_open_discussions(
			'comment',
			array(
				'post_ids' => $pbtb_pids,
			)
		);
	}

	// Delete Post Revisions (WordPress 2.6 and above)
	if ( $delete_revisions ) {
		acc_delete_revisions();
	}
}


