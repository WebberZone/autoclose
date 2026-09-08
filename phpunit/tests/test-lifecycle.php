<?php
/**
 * Tests for plugin lifecycle reconciliation.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Close_Date;
use WebberZone\AutoClose\Options_API;

/**
 * Lifecycle tests.
 */
class LifecycleTest extends WP_UnitTestCase {

	/**
	 * Clear plugin events and settings after each test.
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( 'autoclose_close_comments_pings_event' );
		delete_option( Options_API::SETTINGS_OPTION );
		Options_API::flush_cache();

		parent::tear_down();
	}

	/**
	 * Persisted future dates are restored without creating duplicate events.
	 */
	public function test_restore_scheduled_events_reconciles_future_dates() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() + DAY_IN_SECONDS ) );
		update_post_meta( $post_id, '_acc_pings_date', wp_date( 'Y-m-d\\TH:i', time() + ( 2 * DAY_IN_SECONDS ) ) );

		$close_date = new Close_Date();
		$first      = $close_date->restore_scheduled_events();
		$comments   = wp_get_scheduled_event( 'autoclose_close_comments_pings_event', array( $post_id, 'comments' ) );
		$pings      = wp_get_scheduled_event( 'autoclose_close_comments_pings_event', array( $post_id, 'pings' ) );
		$second     = $close_date->restore_scheduled_events();

		$this->assertSame( 'success', $first['status'] );
		$this->assertSame( 1, $first['posts'] );
		$this->assertSame( 2, $first['scheduled'] );
		$this->assertSame( 2, $second['scheduled'] );
		$this->assertNotFalse( $comments );
		$this->assertNotFalse( $pings );
	}

	/**
	 * Overdue persisted dates close content during reconciliation.
	 */
	public function test_restore_scheduled_events_closes_overdue_dates() {
		update_option(
			Options_API::SETTINGS_OPTION,
			array(
				'reopen_on_update' => 1,
				'reopen_days'      => 30,
			)
		);
		Options_API::flush_cache();

		$post_id = self::factory()->post->create(
			array(
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);
		update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() - DAY_IN_SECONDS ) );

		$result = ( new Close_Date() )->restore_scheduled_events();

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 1, $result['closed'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_acc_reopen_until' ) );
		$this->assertFalse( wp_get_scheduled_event( 'autoclose_close_comments_pings_event', array( $post_id, 'comments' ) ) );
	}
}
