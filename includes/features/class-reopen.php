<?php
/**
 * Feature: Auto-reopen comments on post update.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Util\Hook_Registry;
use WebberZone\AutoClose\Util\Options;
use WebberZone\AutoClose\Util\Helpers;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Reopen class.
 *
 * When a published post is saved/updated, reopens comments for a configured
 * number of days by writing a Unix timestamp to `_acc_reopen_until` post meta.
 * The cron closing query in Comments::edit_discussions() respects this window
 * and will not close the post until the window expires.
 *
 * @since 3.1.0
 */
class Reopen {

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		Hook_Registry::add_action( 'save_post', array( $this, 'reopen_on_update' ), 10, 2 );
	}

	/**
	 * Reopen comments when a published post is saved.
	 *
	 * @since 3.1.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function reopen_on_update( $post_id, $post ): void {
		static $processing = false;
		if ( $processing ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! Options::get_option( 'reopen_on_update' ) ) {
			return;
		}

		// Only act on post types configured for comment closing.
		$post_types = Helpers::parse_post_types( Options::get_option( 'comment_post_types' ) );
		if ( ! empty( $post_types ) && ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// Reopen comments on the post.
		$processing = true;
		wp_update_post(
			array(
				'ID'             => $post_id,
				'comment_status' => 'open',
			)
		);
		$processing = false;

		// Store the reopen window as a Unix timestamp in post meta.
		$days = (int) Options::get_option( 'reopen_days' );
		if ( $days > 0 ) {
			update_post_meta( $post_id, '_acc_reopen_until', time() + ( $days * DAY_IN_SECONDS ) );
		} else {
			delete_post_meta( $post_id, '_acc_reopen_until' );
		}
	}
}
