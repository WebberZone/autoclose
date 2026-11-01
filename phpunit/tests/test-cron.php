<?php
/**
 * Tests for cron and per-post close-date scheduling.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Close_Date;
use WebberZone\AutoClose\Options_API;
use WebberZone\AutoClose\Util\Cron;

/**
 * Cron tests.
 */
class CronTest extends WP_UnitTestCase {

	/**
	 * Clear plugin events after each test.
	 */
	public function tear_down() {
		wp_clear_scheduled_hook( 'acc_cron_hook' );
		wp_unschedule_hook( Close_Date::LEGACY_EVENT_HOOK );
		wp_unschedule_hook( Close_Date::SWEEP_HOOK );
		delete_option( Close_Date::MIGRATED_OPTION );

		parent::tear_down();
	}

	/**
	 * An invalid recurrence does not replace an existing event.
	 */
	public function test_invalid_recurrence_is_rejected_without_changing_event() {
		$cron = new Cron();
		$this->assertTrue( $cron->enable_run( 0, 0, 'daily', true ) );
		$before = wp_get_scheduled_event( 'acc_cron_hook' );

		$this->assertFalse( $cron->enable_run( 0, 0, 'not-a-schedule', true ) );
		$after = wp_get_scheduled_event( 'acc_cron_hook' );

		$this->assertSame( $before->timestamp, $after->timestamp );
		$this->assertSame( 'daily', $after->schedule );
	}

	/**
	 * Local close dates are converted to the site's timezone before scheduling.
	 */
	public function test_close_date_uses_site_timezone_and_keeps_types_independent() {
		$original_timezone = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'America/New_York' );

		$post_id = self::factory()->post->create(
			array(
				'comment_status' => 'open',
				'ping_status'    => 'open',
			)
		);
		update_post_meta( $post_id, '_acc_comments_date', '2027-01-15T09:00' );
		update_post_meta( $post_id, '_acc_pings_date', '2027-01-15T10:00' );

		$result = ( new Close_Date() )->maybe_schedule_or_close( $post_id );

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 2, $result['scheduled'] );
		$this->assertSame( 0, $result['closed'] );
		$this->assertNotFalse( wp_next_scheduled( Close_Date::SWEEP_HOOK ) );

		update_post_meta( $post_id, '_acc_pings_date', '2026-01-01T00:00' );
		( new Close_Date() )->process_due_dates();

		$this->assertSame( 'open', get_post_field( 'comment_status', $post_id ) );
		$this->assertSame( 'closed', get_post_field( 'ping_status', $post_id ) );

		if ( empty( $original_timezone ) ) {
			delete_option( 'timezone_string' );
		} else {
			update_option( 'timezone_string', $original_timezone );
		}
	}

	/**
	 * Non-existent local times are rejected instead of silently normalized.
	 */
	public function test_close_date_rejects_dst_gap() {
		$original_timezone = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'Europe/London' );

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_acc_comments_date', '2027-03-28T01:30' );

		$result = ( new Close_Date() )->maybe_schedule_or_close( $post_id );

		$this->assertSame( 'failed', $result['status'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertTrue( metadata_exists( 'post', $post_id, '_acc_comments_date' ) );

		if ( empty( $original_timezone ) ) {
			delete_option( 'timezone_string' );
		} else {
			update_option( 'timezone_string', $original_timezone );
		}
	}

	/**
	 * Repair registers a missing event when maintenance is enabled.
	 */
	public function test_repair_registers_missing_event() {
		update_option(
			Options_API::SETTINGS_OPTION,
			array(
				'cron_on'         => 1,
				'cron_hour'       => 0,
				'cron_min'        => 0,
				'cron_recurrence' => 'daily',
			)
		);
		Options_API::flush_cache();
		wp_clear_scheduled_hook( 'acc_cron_hook' );

		$result = ( new Cron() )->repair();

		$this->assertSame( 'success', $result['outcome'] );
		$this->assertTrue( $result['enabled'] );
		$this->assertNull( $result['before'] );
		$this->assertNotNull( $result['after'] );

		delete_option( Options_API::SETTINGS_OPTION );
		Options_API::flush_cache();
	}
}
