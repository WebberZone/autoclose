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
		wp_clear_scheduled_hook( 'autoclose_close_comments_pings_event' );
		wp_clear_scheduled_hook( Close_Date::RESTORE_HOOK );
		delete_option( Close_Date::RESTORE_DONE_OPTION );

		parent::tear_down();
	}

	/**
	 * AutoClose registers every recurrence exposed by its settings.
	 */
	public function test_custom_recurrences_are_registered() {
		new Cron();
		$schedules = wp_get_schedules();

		$this->assertSame( 14 * DAY_IN_SECONDS, $schedules['fortnightly']['interval'] );
		$this->assertSame( 30 * DAY_IN_SECONDS, $schedules['monthly']['interval'] );
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
	 * Re-saving an unchanged future schedule keeps the existing event.
	 */
	public function test_future_schedule_is_idempotent_by_time_and_recurrence() {
		$cron      = new Cron();
		$target    = time() + HOUR_IN_SECONDS;
		$hour      = (int) gmdate( 'G', $target );
		$minute    = (int) gmdate( 'i', $target );
		$recurrence = 'daily';

		$this->assertTrue( $cron->enable_run( $hour, $minute, $recurrence, true ) );
		$before = wp_get_scheduled_event( 'acc_cron_hook' );

		$this->assertTrue( $cron->enable_run( $hour, $minute, $recurrence, true ) );
		$after = wp_get_scheduled_event( 'acc_cron_hook' );

		$this->assertSame( $before->timestamp, $after->timestamp );
		$this->assertSame( $before->schedule, $after->schedule );
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
		$event  = wp_get_scheduled_event( 'autoclose_close_comments_pings_event', array( $post_id, 'comments' ) );

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 2, $result['scheduled'] );
		$this->assertSame(
			( new DateTimeImmutable( '2027-01-15T09:00:00', new DateTimeZone( 'America/New_York' ) ) )->getTimestamp(),
			$event->timestamp
		);

		update_post_meta( $post_id, '_acc_pings_date', '2026-01-01T00:00' );
		( new Close_Date() )->maybe_close_due_comments_pings( $post_id, 'pings' );

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
		$this->assertFalse( wp_get_scheduled_event( 'autoclose_close_comments_pings_event', array( $post_id, 'comments' ) ) );

		if ( empty( $original_timezone ) ) {
			delete_option( 'timezone_string' );
		} else {
			update_option( 'timezone_string', $original_timezone );
		}
	}

	/**
	 * Scheduling failures are returned to callers.
	 */
	public function test_close_date_reports_scheduling_failure() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_acc_comments_date', '2027-01-15T09:00' );
		$filter = static function () {
			return false;
		};
		add_filter( 'pre_schedule_event', $filter, 10, 3 );

		$result = ( new Close_Date() )->maybe_schedule_or_close( $post_id );

		remove_filter( 'pre_schedule_event', $filter, 10 );
		$this->assertSame( 'failed', $result['status'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Repair does nothing and fails when scheduled maintenance is disabled.
	 */
	public function test_repair_fails_when_maintenance_disabled() {
		update_option( Options_API::SETTINGS_OPTION, array( 'cron_on' => 0 ) );
		Options_API::flush_cache();

		$result = ( new Cron() )->repair();

		$this->assertSame( 'failed', $result['outcome'] );
		$this->assertFalse( $result['enabled'] );
		$this->assertNotEmpty( $result['errors'] );

		delete_option( Options_API::SETTINGS_OPTION );
		Options_API::flush_cache();
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

	/**
	 * Repair reports a failure when an existing event cannot be rescheduled.
	 */
	public function test_repair_reports_reschedule_failure() {
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

		$cron = new Cron();
		$this->assertTrue( $cron->enable_run( 0, 0, 'daily', true ) );

		$filter = static function ( $pre, $hook ) {
			return 'acc_cron_hook' === $hook ? false : $pre;
		};
		add_filter( 'pre_clear_scheduled_hook', $filter, 10, 2 );

		$result = $cron->repair( true );

		remove_filter( 'pre_clear_scheduled_hook', $filter, 10 );

		$this->assertSame( 'failed', $result['outcome'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertNotNull( $result['after'] );

		delete_option( Options_API::SETTINGS_OPTION );
		Options_API::flush_cache();
	}

	/**
	 * Repair reschedules a missing close-date restore event, e.g. after a
	 * failed activation-time schedule.
	 */
	public function test_repair_reschedules_missing_restore_event() {
		wp_clear_scheduled_hook( Close_Date::RESTORE_HOOK );
		delete_option( Close_Date::RESTORE_DONE_OPTION );

		( new Cron() )->repair();

		$this->assertFalse( Close_Date::is_restore_done() );
		$this->assertNotFalse( wp_next_scheduled( Close_Date::RESTORE_HOOK ) );
	}

	/**
	 * Repair does not reschedule the restore event once it has completed.
	 */
	public function test_repair_does_not_reschedule_completed_restore_event() {
		wp_clear_scheduled_hook( Close_Date::RESTORE_HOOK );
		update_option( Close_Date::RESTORE_DONE_OPTION, true, false );

		( new Cron() )->repair();

		$this->assertFalse( wp_next_scheduled( Close_Date::RESTORE_HOOK ) );
	}
}
