<?php
/**
 * Tests for discussion status maintenance.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Options_API;

/**
 * Discussion maintenance tests.
 */
class CommentsTest extends WP_UnitTestCase {

	/**
	 * Discussion processor.
	 *
	 * @var Comments
	 */
	private $comments;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->comments = new Comments();
		$this->set_settings(
			array(
				'close_comment'      => 0,
				'close_pbtb'         => 0,
				'comment_post_types' => 'post',
				'pbtb_post_types'    => 'post',
				'comment_age'        => 90,
				'pbtb_age'           => 90,
			)
		);
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		delete_option( Options_API::SETTINGS_OPTION );
		Options_API::flush_cache();

		parent::tear_down();
	}

	/**
	 * Save plugin settings and flush the request cache.
	 *
	 * @param array $settings Settings to save.
	 */
	private function set_settings( array $settings ) {
		update_option( Options_API::SETTINGS_OPTION, $settings );
		Options_API::flush_cache();
	}

	/**
	 * Legacy close values migrate to the canonical value without reopening posts.
	 */
	public function test_legacy_statuses_are_migrated_idempotently() {
		global $wpdb;

		$post_id = self::factory()->post->create(
			array(
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);
		$wpdb->update(
			$wpdb->posts,
			array(
				'comment_status' => 'close',
				'ping_status'    => 'close',
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$result = $this->comments->migrate_legacy_statuses();

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 2, $result['updated'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		$this->assertSame( 'closed', get_post_field( 'ping_status', $post_id ) );

		$second = $this->comments->migrate_legacy_statuses();
		$this->assertSame( 'success', $second['status'] );
		$this->assertSame( 0, $second['updated'] );
	}

	/**
	 * A failed batch preserves the successful updates already made.
	 */
	public function test_discussion_result_preserves_affected_count_on_failure() {
		$post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );
		$result  = $this->comments->edit_discussions_result(
			'comment',
			'close',
			array( 'post_ids' => array( $post_id ) )
		);

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 1, $result['affected'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
	}

	/**
	 * Reopening comments does not suppress an independent ping close.
	 */
	public function test_reopen_window_only_excludes_comments() {
		$this->set_settings(
			array(
				'close_comment'      => 1,
				'close_pbtb'         => 1,
				'comment_post_types' => 'post',
				'pbtb_post_types'    => 'post',
				'comment_age'        => 0,
				'pbtb_age'           => 0,
			)
		);
		$post_id = self::factory()->post->create(
			array(
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);
		update_post_meta( $post_id, '_acc_reopen_until', time() + DAY_IN_SECONDS );

		$result = $this->comments->process_comments();

		$this->assertSame( 'open', get_post_field( 'comment_status', $post_id ) );
		$this->assertSame( 'closed', get_post_field( 'ping_status', $post_id ) );
		$this->assertSame( 0, $result['comments_closed'] );
		$this->assertSame( 1, $result['pings_closed'] );
	}
}
