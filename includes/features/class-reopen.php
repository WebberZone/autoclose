<?php
/**
 * Feature: Auto-reopen comments on post update.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Util\Hook_Registry;
use WebberZone\AutoClose\Options_API;
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
	 * Number of temporarily suppressed reopen contexts.
	 *
	 * @var int
	 */
	private static $suppressed = 0;

	/**
	 * Post state captured before an update, used to handle revision restores.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $before_update = array();

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		Hook_Registry::add_action( 'post_updated', array( $this, 'capture_before_update' ), 10, 3 );
		Hook_Registry::add_action( 'save_post', array( $this, 'reopen_on_update' ), 10, 2 );
		Hook_Registry::add_action( 'wp_restore_post_revision', array( $this, 'restore_after_revision' ), 10, 2 );
	}

	/**
	 * Run a callback without allowing the resulting post update to reopen comments.
	 *
	 * @since 3.2.0
	 *
	 * @param callable $callback Callback to invoke.
	 */
	public static function without_reopening( callable $callback ): void {
		++self::$suppressed;

		try {
			call_user_func( $callback );
		} finally {
			--self::$suppressed;
		}
	}

	/**
	 * Capture the state immediately before an existing post is updated.
	 *
	 * @since 3.2.0
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after Post object after the update. Unused.
	 * @param \WP_Post $post_before Post object before the update.
	 */
	public function capture_before_update( $post_id, $post_after, $post_before ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->before_update[ (int) $post_id ] = array(
			'comment_status'     => $post_before->comment_status,
			'reopen_meta_exists' => metadata_exists( 'post', $post_id, '_acc_reopen_until' ),
			'reopen_until'       => get_post_meta( $post_id, '_acc_reopen_until', true ),
		);
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
		if ( $processing || self::$suppressed > 0 ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! Options_API::get_option( 'reopen_on_update' ) ) {
			return;
		}

		// Only act on post types configured for comment closing.
		$post_types = Helpers::parse_post_types( Options_API::get_option( 'comment_post_types' ) );
		if ( ! empty( $post_types ) && ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// Reopen comments on the post.
		$processing = true;
		try {
			wp_update_post(
				array(
					'ID'             => $post_id,
					'comment_status' => 'open',
				)
			);
		} finally {
			$processing = false;
		}

		// Store the reopen window as a Unix timestamp in post meta.
		$days = (int) Options_API::get_option( 'reopen_days' );
		if ( $days > 0 ) {
			update_post_meta( $post_id, '_acc_reopen_until', time() + ( $days * DAY_IN_SECONDS ) );
		} else {
			delete_post_meta( $post_id, '_acc_reopen_until' );
		}
	}

	/**
	 * Restore the pre-update comment state after a revision is restored.
	 *
	 * Restoring a revision uses wp_update_post() internally and therefore fires
	 * save_post before the wp_restore_post_revision action. Reapply the state
	 * that existed before that update so a closed post is not reopened silently.
	 *
	 * @since 3.2.0
	 *
	 * @param int $post_id     Restored post ID.
	 * @param int $revision_id Restored revision ID. Unused.
	 */
	public function restore_after_revision( $post_id, $revision_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$post_id = (int) $post_id;
		$state   = $this->before_update[ $post_id ] ?? null;
		unset( $this->before_update[ $post_id ] );

		if ( ! is_array( $state ) ) {
			return;
		}

		$comment_status = (string) ( $state['comment_status'] ?? 'open' );
		self::without_reopening(
			static function () use ( $post_id, $comment_status ) {
				if ( get_post_field( 'comment_status', $post_id ) !== $comment_status ) {
					wp_update_post(
						array(
							'ID'             => $post_id,
							'comment_status' => $comment_status,
						)
					);
				}
			}
		);

		if ( ! empty( $state['reopen_meta_exists'] ) ) {
			update_post_meta( $post_id, '_acc_reopen_until', $state['reopen_until'] );
		} else {
			delete_post_meta( $post_id, '_acc_reopen_until' );
		}
	}
}
