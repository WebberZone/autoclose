<?php
/**
 * Feature: Per-post comment/trackback close date logic (scheduling & closing only).
 *
 * @package WebberZone\AutoClose
 * @subpackage Features
 */

namespace WebberZone\AutoClose\Features;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Close_Date
 *
 * Handles scheduling and closing logic for comments/trackbacks based on meta.
 * No admin UI/metabox logic here.
 *
 * @since 3.0.0
 */
class Close_Date {
	/**
	 * Prefix for meta keys and filters.
	 *
	 * @var string
	 */
	protected $prefix = 'acc';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'schedule_cron_for_due_posts' ) );
		add_action( 'autoclose_close_comments_pings_event', array( $this, 'maybe_close_due_comments_pings' ) );
	}

	/**
	 * Schedules cron if date/time is in the future, closes immediately if past.
	 *
	 * @param int $post_id Post ID.
	 */
	public function maybe_schedule_or_close( $post_id ): void {
		$comments_date = get_post_meta( $post_id, "_{$this->prefix}_comments_date", true );
		$pings_date    = get_post_meta( $post_id, "_{$this->prefix}_pings_date", true );
		$now           = current_time( 'Y-m-d\TH:i' );
		if ( $comments_date && $comments_date <= $now ) {
			$this->close_comments( $post_id );
		} elseif ( $comments_date ) {
			wp_schedule_single_event( strtotime( $comments_date ), 'autoclose_close_comments_pings_event', array( $post_id ) );
		}
		if ( $pings_date && $pings_date <= $now ) {
			$this->close_pings( $post_id );
		} elseif ( $pings_date ) {
			wp_schedule_single_event( strtotime( $pings_date ), 'autoclose_close_comments_pings_event', array( $post_id ) );
		}
	}

	/**
	 * Cron callback to close comments/pings if due.
	 *
	 * @param int $post_id Post ID.
	 */
	public function maybe_close_due_comments_pings( $post_id ): void {
		$this->maybe_schedule_or_close( $post_id );
	}

	/**
	 * Actually close comments for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function close_comments( $post_id ): void {
		if ( 'closed' !== get_post_field( 'comment_status', $post_id ) ) {
			wp_update_post(
				array(
					'ID'             => $post_id,
					'comment_status' => 'closed',
				)
			);
		}
	}

	/**
	 * Actually close pings/trackbacks for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function close_pings( $post_id ): void {
		if ( 'closed' !== get_post_field( 'ping_status', $post_id ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'ping_status' => 'closed',
				)
			);
		}
	}

	/**
	 * On plugin load, schedule cron for any due posts (fail-safe for missed events).
	 */
	public function schedule_cron_for_due_posts(): void {
		foreach ( $this->get_supported_post_types() as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $posts as $post_id ) {
				$this->maybe_schedule_or_close( $post_id );
			}
		}
	}

	/**
	 * Get supported post types for the close logic.
	 *
	 * @return array Array of post types.
	 */
	protected function get_supported_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		return array_filter(
			$post_types,
			static function ( $type ) {
				return post_type_supports( $type, 'comments' );
			}
		);
	}
}
