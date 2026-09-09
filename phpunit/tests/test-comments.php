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
	 * Legacy status repairs have separate counts from newly closed posts.
	 */
	public function test_legacy_status_repairs_do_not_inflate_close_counts() {
		global $wpdb;

		$post_id = self::factory()->post->create();
		$wpdb->update(
			$wpdb->posts,
			array(
				'comment_status' => 'close',
				'ping_status'    => 'close',
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$result = $this->comments->process_comments();

		$this->assertSame( 0, $result['comments_closed'] );
		$this->assertSame( 0, $result['pings_closed'] );
		$this->assertSame( 1, $result['comments_migrated'] );
		$this->assertSame( 1, $result['pings_migrated'] );
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
	 * A database selection failure is reported instead of as zero work.
	 */
	public function test_discussion_selection_failure_is_reported() {
		global $wpdb;

		$posts_table = $wpdb->posts;
		$suppressed  = $wpdb->suppress_errors( true );
		$wpdb->posts = $wpdb->prefix . 'missing_autoclose_posts';

		try {
			$result = $this->comments->edit_discussions_result( 'comment', 'close' );
		} finally {
			$wpdb->posts = $posts_table;
			$wpdb->suppress_errors( $suppressed );
		}

		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( 0, $result['affected'] );
		$this->assertNotEmpty( $result['errors'] );
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
	 * Age cutoffs use the configured number of complete elapsed days.
	 */
	public function test_age_cutoff_uses_configured_days() {
		$method = new ReflectionMethod( Comments::class, 'get_cutoff' );
		$method->setAccessible( true );
		$now = time();

		$this->assertSame(
			gmdate( 'Y-m-d H:i:s', $now - ( 90 * DAY_IN_SECONDS ) ),
			$method->invoke( $this->comments, 90 )
		);
		$this->assertNull( $method->invoke( $this->comments, 0 ) );
		$this->assertNull( $method->invoke( $this->comments, -1 ) );
	}

	/**
	 * A failed configured operation is not reported as successful no-change work.
	 */
	public function test_failed_operation_is_reported_by_the_processor() {
		$this->set_settings(
			array(
				'close_comment'      => 1,
				'close_pbtb'         => 0,
				'comment_post_types' => 'post',
			)
		);

		$comments = new CommentsResultTestDouble(
			array(
				'status'   => 'failed',
				'affected' => 0,
				'errors'   => array( 'The discussion update failed.' ),
			)
		);
		$result = $comments->process_comments();

		$this->assertSame( 'failed', $result['status'] );
		$this->assertSame( 0, $result['comments_closed'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * A run with no configured discussion work is reported as skipped.
	 */
	public function test_no_configured_operations_are_skipped() {
		$result = $this->comments->process_comments();

		$this->assertSame( 'skipped', $result['status'] );
		$this->assertSame( 0, $result['comments_closed'] );
		$this->assertSame( 0, $result['pings_closed'] );
	}

	/**
	 * -2 inherits the global age setting.
	 */
	public function test_effective_age_inherits_global_by_default() {
		$this->assertSame( 90, $this->comments->get_effective_age( 'comment', 'post' ) );
		$this->assertSame( 90, $this->comments->get_effective_age( 'ping', 'post' ) );
	}

	/**
	 * -1 disables closing for a single post type without affecting others.
	 */
	public function test_effective_age_minus_one_disables_post_type() {
		$this->set_settings(
			array(
				'comment_age'      => 90,
				'comment_age_post' => -1,
				'comment_age_page' => -2,
			)
		);

		$this->assertNull( $this->comments->get_effective_age( 'comment', 'post' ) );
		$this->assertSame( 90, $this->comments->get_effective_age( 'comment', 'page' ) );
	}

	/**
	 * A non-negative override is used verbatim, including zero (immediate).
	 */
	public function test_effective_age_explicit_override_including_zero() {
		$this->set_settings(
			array(
				'comment_age'      => 90,
				'comment_age_post' => 0,
				'comment_age_page' => 5,
			)
		);

		$this->assertSame( 0, $this->comments->get_effective_age( 'comment', 'post' ) );
		$this->assertSame( 5, $this->comments->get_effective_age( 'comment', 'page' ) );
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

	/**
	 * Zero disables the count threshold.
	 */
	public function test_zero_count_threshold_is_disabled() {
		$this->assertSame( 0, $this->comments->get_count_threshold() );
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
