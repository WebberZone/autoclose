<?php
/**
 * Tests for plugin lifecycle reconciliation.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Close_Date;
use WebberZone\AutoClose\Features\Reopen;
use WebberZone\AutoClose\Core\Activator;
use WebberZone\AutoClose\Options_API;

/**
 * Lifecycle tests.
 */
class LifecycleTest extends WP_UnitTestCase {

	/**
	 * Clear plugin events and settings after each test.
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( 'acc_cron_hook' );
		wp_clear_scheduled_hook( 'autoclose_close_comments_pings_event' );
		wp_clear_scheduled_hook( Close_Date::RESTORE_HOOK );
		delete_option( Options_API::SETTINGS_OPTION );
		delete_option( 'acc_close_date_restore_cursor' );
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

		$post_id = 0;
		Reopen::without_reopening(
			static function () use ( &$post_id ) {
				$post_id = self::factory()->post->create(
					array(
						'comment_status' => 'open',
						'ping_status'    => 'open',
					)
				);
			}
		);
		update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() - DAY_IN_SECONDS ) );

		$result = ( new Close_Date() )->restore_scheduled_events();

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 1, $result['closed'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_acc_reopen_until' ) );
		$this->assertFalse( wp_get_scheduled_event( 'autoclose_close_comments_pings_event', array( $post_id, 'comments' ) ) );
	}

	/**
	 * Large close-date restores resume through bounded continuation requests.
	 */
	public function test_restore_scheduled_events_resumes_after_run_limit() {
		$post_ids = array();
		Reopen::without_reopening(
			static function () use ( &$post_ids ) {
				$post_ids = self::factory()->post->create_many( 501 );
			}
		);

		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() + DAY_IN_SECONDS ) );
		}

		$close_date = new Close_Date();
		$first      = $close_date->restore_scheduled_events();

		$this->assertSame( 'success', $first['status'] );
		$this->assertTrue( $first['pending'] );
		$this->assertSame( 500, $first['posts'] );
		$this->assertNotFalse( get_option( 'acc_close_date_restore_cursor', false ) );
		$this->assertNotFalse( wp_get_scheduled_event( Close_Date::RESTORE_HOOK ) );

		wp_clear_scheduled_hook( Close_Date::RESTORE_HOOK );
		$second = $close_date->restore_scheduled_events();

		$this->assertSame( 'success', $second['status'] );
		$this->assertFalse( $second['pending'] );
		$this->assertSame( 1, $second['posts'] );
		$this->assertFalse( get_option( 'acc_close_date_restore_cursor', false ) );
	}

	/**
	 * A database failure during close-date restoration is reported.
	 */
	public function test_restore_scheduled_events_reports_selection_failure() {
		global $wpdb;

		$posts_table = $wpdb->posts;
		$suppressed  = $wpdb->suppress_errors( true );
		$wpdb->posts = $wpdb->prefix . 'missing_autoclose_posts';

		try {
			$result = ( new Close_Date() )->restore_scheduled_events();
		} finally {
			$wpdb->posts = $posts_table;
			$wpdb->suppress_errors( $suppressed );
		}

		$this->assertSame( 'failed', $result['status'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Activation defers close-date reconciliation to a scheduled request.
	 */
	public function test_activation_defers_close_date_restoration() {
		$post_id = self::factory()->post->create( array( 'comment_status' => 'open' ) );
		update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() - DAY_IN_SECONDS ) );

		Activator::activate( false );

		$event = wp_get_scheduled_event( Close_Date::RESTORE_HOOK );

		$this->assertNotFalse( $event );
		$this->assertGreaterThan( time(), (int) $event->timestamp );
		$this->assertSame( 'open', get_post_field( 'comment_status', $post_id ) );
	}
}
