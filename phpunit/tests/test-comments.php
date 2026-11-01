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

		delete_option( 'acc_legacy_status_migration_complete' );

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
		delete_option( 'acc_legacy_status_migration_complete' );
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
		$this->assertSame( 1, $result['comments'] );
		$this->assertSame( 1, $result['pings'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		$this->assertSame( 'closed', get_post_field( 'ping_status', $post_id ) );

		$wpdb->update(
			$wpdb->posts,
			array( 'comment_status' => 'close' ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$second = $this->comments->migrate_legacy_statuses();
		$this->assertSame( 'success', $second['status'] );
		$this->assertSame( 0, $second['updated'] );
		$this->assertSame( 'close', get_post_field( 'comment_status', $post_id ) );
	}
	/**
	 * A successful batch reports the affected count.
	 */
	public function test_discussion_result_reports_successful_affected_count() {
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
	 * Unpublished posts are not closed by a scheduled run.
	 */
	public function test_draft_posts_are_not_closed() {
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

		$draft = self::factory()->post->create(
			array(
				'post_status'    => 'draft',
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);

		$this->comments->process_comments();

		$this->assertSame( 'open', get_post_field( 'comment_status', $draft ) );
		$this->assertSame( 'open', get_post_field( 'ping_status', $draft ) );
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
	/**
	 * -2 inherits the global age setting.
	 */
	/**
	 * Per-post-type ages: -2 inherits global, -1 disables, explicit values win.
	 *
	 * @dataProvider effective_ages
	 *
	 * @param array      $settings Settings overrides.
	 * @param string     $type     'comment' or 'ping'.
	 * @param string     $post     Post type.
	 * @param int|null   $expected Expected effective age.
	 */
	public function test_effective_age( $settings, $type, $post, $expected ) {
		if ( $settings ) {
			$this->set_settings( $settings );
		}
		$this->assertSame( $expected, $this->comments->get_effective_age( $type, $post ) );
	}

	/**
	 * Data provider for test_effective_age.
	 *
	 * @return array
	 */
	public function effective_ages() {
		return array(
			'inherits global by default' => array(
				array(),
				'comment',
				'post',
				90,
			),
			'minus one disables post type' => array(
				array(
					'comment_age'      => 90,
					'comment_age_post' => -1,
					'comment_age_page' => -2,
				),
				'comment',
				'post',
				null,
			),
			'explicit override including zero' => array(
				array(
					'comment_age'      => 90,
					'comment_age_post' => 0,
					'comment_age_page' => 5,
				),
				'comment',
				'page',
				5,
			),
		);
	}
	/**
	 * A per-post-type override closes one type while leaving another, disabled, type untouched.
	 */
	public function test_process_comments_respects_per_post_type_age_override() {
		$this->set_settings(
			array(
				'close_comment'      => 1,
				'close_pbtb'         => 0,
				'comment_post_types' => 'post,page',
				'comment_age'        => 0,
				'comment_age_page'   => -1,
			)
		);

		$post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );
		$page_id = self::factory()->post->create(
			array(
				'post_type'      => 'page',
				'comment_status' => 'open',
			)
		);

		$result = $this->comments->process_comments();

		$this->assertSame( 1, $result['comments_closed'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		$this->assertSame( 'open', get_post_field( 'comment_status', $page_id ) );
	}

	/**
	 * A post is closed by the count threshold even when its age is disabled.
	 */
	public function test_count_threshold_closes_regardless_of_disabled_age() {
		$this->set_settings(
			array(
				'close_comment'           => 1,
				'close_pbtb'              => 0,
				'comment_post_types'      => 'post',
				'comment_age_post'        => -1,
				'comment_count_threshold' => 2,
			)
		);

		$post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );
		self::factory()->comment->create_many(
			2,
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$result = $this->comments->process_comments();

		$this->assertSame( 1, $result['comments_closed'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
	}

	/**
	 * Pingbacks, spam, unapproved comments, and notes do not count toward the threshold.
	 */
	public function test_count_threshold_excludes_ineligible_comment_types() {
		$this->set_settings(
			array(
				'close_comment'           => 1,
				'close_pbtb'              => 0,
				'comment_post_types'      => 'post',
				'comment_age_post'        => -1,
				'comment_count_threshold' => 2,
			)
		);

		$post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );
		self::factory()->comment->create( array( 'comment_post_ID' => $post_id, 'comment_approved' => '1' ) );
		self::factory()->comment->create( array( 'comment_post_ID' => $post_id, 'comment_approved' => '0' ) );
		self::factory()->comment->create( array( 'comment_post_ID' => $post_id, 'comment_approved' => 'spam' ) );
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
				'comment_type'     => 'pingback',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
				'comment_type'     => 'note',
			)
		);

		$result = $this->comments->process_comments();

		$this->assertSame( 0, $result['comments_closed'] );
		$this->assertSame( 'open', get_post_field( 'comment_status', $post_id ) );
	}

	/**
	 * A post meeting both the age and count conditions is only counted once.
	 */
	public function test_post_matching_both_conditions_counted_once() {
		$this->set_settings(
			array(
				'close_comment'           => 1,
				'close_pbtb'              => 0,
				'comment_post_types'      => 'post',
				'comment_age'             => 0,
				'comment_count_threshold' => 1,
			)
		);

		$post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );
		self::factory()->comment->create( array( 'comment_post_ID' => $post_id, 'comment_approved' => '1' ) );

		$result = $this->comments->process_comments();

		$this->assertSame( 1, $result['comments_closed'] );
	}
}

/**
 * Comments processor double for failure-result tests.
 */
class CommentsResultTestDouble extends Comments {

	/**
	 * Discussion operation result.
	 *
	 * @var array
	 */
	private $result;

	/**
	 * @param array $result Discussion operation result.
	 */
	public function __construct( array $result ) {
		$this->result = $result;
	}

	/**
	 * Return the configured operation result.
	 *
	 * @param string $type   Discussion type.
	 * @param string $action Operation action.
	 * @param array  $args   Operation arguments.
	 * @return array Discussion operation result.
	 */
	public function edit_discussions_result( $type = 'comment', $action = 'open', $args = array() ): array {
		return $this->result;
	}
}
