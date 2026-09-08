<?php
/**
 * Tests for discussion reopening interactions.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Close_Date;
use WebberZone\AutoClose\Features\Reopen;
use WebberZone\AutoClose\Options_API;

/**
 * Reopen interaction tests.
 */
class ReopenTest extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_settings(
			array(
				'reopen_on_update'  => 1,
				'reopen_days'       => 30,
				'comment_post_types' => 'post',
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
	 * A due close date wins over the internal save that would otherwise reopen comments.
	 */
	public function test_due_close_does_not_reopen_comments() {
		$post_id = self::factory()->post->create( array( 'comment_status' => 'closed' ) );
		update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() - MINUTE_IN_SECONDS ) );

		$close_date = new Close_Date();
		$close_date->maybe_schedule_or_close( $post_id );

		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_acc_reopen_until' ) );
	}

	/**
	 * A normal editorial update still reopens a closed post.
	 */
	public function test_editorial_update_reopens_comments() {
		$reopen  = new Reopen();
		$post_id = self::factory()->post->create( array( 'comment_status' => 'closed' ) );

		$reopen->reopen_on_update( $post_id, get_post( $post_id ) );

		$this->assertSame( 'open', get_post_field( 'comment_status', $post_id ) );
		$this->assertGreaterThan( time(), (int) get_post_meta( $post_id, '_acc_reopen_until', true ) );
	}
}
