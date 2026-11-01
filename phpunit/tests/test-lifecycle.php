<?php
/**
 * Tests for plugin lifecycle reconciliation.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Close_Date;
use WebberZone\AutoClose\Features\Reopen;
use WebberZone\AutoClose\Core\Activator;
use WebberZone\AutoClose\Core\Deactivator;
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
		wp_unschedule_hook( Close_Date::LEGACY_EVENT_HOOK );
		wp_unschedule_hook( Close_Date::SWEEP_HOOK );
		delete_option( Options_API::SETTINGS_OPTION );
		delete_option( Close_Date::MIGRATED_OPTION );
		Options_API::flush_cache();

		parent::tear_down();
	}

	/**
	 * Create posts without the reopen handler rewriting their discussion state.
	 *
	 * @param int $count Number of posts.
	 * @return array<int> Post IDs.
	 */
	private function create_posts( int $count ): array {
		$post_ids = array();
		Reopen::without_reopening(
			function () use ( &$post_ids, $count ) {
				$post_ids = self::factory()->post->create_many( $count, array( 'comment_status' => 'open' ) );
			}
		);

		return $post_ids;
	}

	/**
	 * The number of scheduled events does not grow with the number of close dates.
	 */
	public function test_close_dates_do_not_create_per_post_events() {
		$post_ids = $this->create_posts( 25 );
		$due      = wp_date( 'Y-m-d\\TH:i', time() + DAY_IN_SECONDS );

		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_acc_comments_date', $due );
			update_post_meta( $post_id, '_acc_pings_date', $due );
			( new Close_Date() )->maybe_schedule_or_close( $post_id );
		}

		$events = 0;
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( $hooks as $hook => $registered ) {
				if ( Close_Date::SWEEP_HOOK === $hook || Close_Date::LEGACY_EVENT_HOOK === $hook ) {
					$events += count( $registered );
				}
			}
		}

		$this->assertSame( 1, $events );
	}

	/**
	 * The sweep closes due dates and clears the meta it applied.
	 */
	public function test_sweep_closes_due_dates_and_clears_applied_meta() {
		$post_ids = $this->create_posts( 2 );
		update_post_meta( $post_ids[0], '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() - HOUR_IN_SECONDS ) );
		update_post_meta( $post_ids[0], '_acc_pings_date', wp_date( 'Y-m-d\\TH:i', time() - HOUR_IN_SECONDS ) );
		update_post_meta( $post_ids[1], '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() + DAY_IN_SECONDS ) );

		$result = ( new Close_Date() )->process_due_dates();

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 1, $result['posts'] );
		$this->assertSame( 2, $result['closed'] );
		$this->assertFalse( $result['pending'] );
		$this->assertSame( 'closed', get_post_field( 'comment_status', $post_ids[0] ) );
		$this->assertSame( 'closed', get_post_field( 'ping_status', $post_ids[0] ) );
		$this->assertFalse( metadata_exists( 'post', $post_ids[0], '_acc_comments_date' ) );
		$this->assertFalse( metadata_exists( 'post', $post_ids[0], '_acc_pings_date' ) );
		$this->assertSame( 'open', get_post_field( 'comment_status', $post_ids[1] ) );
		$this->assertTrue( metadata_exists( 'post', $post_ids[1], '_acc_comments_date' ) );
	}

	/**
	 * A second sweep finds nothing left to do.
	 */
	public function test_sweep_is_idempotent() {
		$post_id = $this->create_posts( 1 )[0];
		update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() - HOUR_IN_SECONDS ) );

		$close_date = new Close_Date();
		$first      = $close_date->process_due_dates();
		$second     = $close_date->process_due_dates();

		$this->assertSame( 1, $first['closed'] );
		$this->assertSame( 0, $second['posts'] );
		$this->assertSame( 0, $second['closed'] );
	}

	/**
	 * A long backlog is applied over several bounded requests.
	 */
	public function test_sweep_defers_the_remainder_of_a_large_backlog() {
		$post_ids = $this->create_posts( 4 );
		$due      = wp_date( 'Y-m-d\\TH:i', time() - HOUR_IN_SECONDS );

		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_acc_comments_date', $due );
		}

		$small_batches = static function () {
			return 2;
		};
		add_filter( 'acc_close_dates_batch_size', $small_batches );
		add_filter( 'acc_close_dates_sweep_limit', $small_batches );

		try {
			$close_date = new Close_Date();
			$first      = $close_date->process_due_dates();
			$this->assertTrue( $first['pending'] );
			$this->assertSame( 2, $first['closed'] );
			$this->assertNotFalse( wp_next_scheduled( Close_Date::SWEEP_HOOK ) );

			$second = $close_date->process_due_dates();
			$this->assertSame( 2, $second['closed'] );

			$third = $close_date->process_due_dates();
			$this->assertFalse( $third['pending'] );
			$this->assertSame( 0, $third['posts'] );
		} finally {
			remove_filter( 'acc_close_dates_batch_size', $small_batches );
			remove_filter( 'acc_close_dates_sweep_limit', $small_batches );
		}

		foreach ( $post_ids as $post_id ) {
			$this->assertSame( 'closed', get_post_field( 'comment_status', $post_id ) );
		}
	}

	/**
	 * A database failure is reported instead of being treated as no work.
	 */
	public function test_sweep_reports_a_database_failure() {
		global $wpdb;

		$postmeta       = $wpdb->postmeta;
		$suppressed     = $wpdb->suppress_errors( true );
		$wpdb->postmeta = $wpdb->prefix . 'missing_autoclose_meta';

		try {
			$result = ( new Close_Date() )->process_due_dates();
		} finally {
			$wpdb->postmeta = $postmeta;
			$wpdb->suppress_errors( $suppressed );
		}

		$this->assertSame( 'failed', $result['status'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertFalse( $result['pending'] );
	}

	/**
	 * Upgrading removes the per-post events scheduled by earlier versions.
	 */
	public function test_migration_clears_legacy_per_post_events() {
		$post_ids = $this->create_posts( 2 );

		foreach ( $post_ids as $post_id ) {
			wp_schedule_single_event( time() + DAY_IN_SECONDS, Close_Date::LEGACY_EVENT_HOOK, array( $post_id, 'comments' ) );
		}

		$this->assertNotFalse( wp_get_scheduled_event( Close_Date::LEGACY_EVENT_HOOK, array( $post_ids[0], 'comments' ) ) );

		delete_option( Close_Date::MIGRATED_OPTION );
		Close_Date::maybe_migrate();

		foreach ( $post_ids as $post_id ) {
			$this->assertFalse( wp_get_scheduled_event( Close_Date::LEGACY_EVENT_HOOK, array( $post_id, 'comments' ) ) );
		}

		$this->assertTrue( (bool) get_option( Close_Date::MIGRATED_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( Close_Date::SWEEP_HOOK ) );
	}

	/**
	 * Activation registers the sweep and leaves existing content untouched.
	 */
	public function test_activation_registers_the_sweep() {
		$post_id = $this->create_posts( 1 )[0];
		update_post_meta( $post_id, '_acc_comments_date', wp_date( 'Y-m-d\\TH:i', time() - DAY_IN_SECONDS ) );

		Activator::activate( false );

		$this->assertNotFalse( wp_next_scheduled( Close_Date::SWEEP_HOOK ) );
		$this->assertSame( 'open', get_post_field( 'comment_status', $post_id ) );
	}

	/**
	 * Deactivation clears the sweep and any legacy events left behind.
	 */
	public function test_deactivation_clears_scheduled_work() {
		$post_id = $this->create_posts( 1 )[0];
		wp_schedule_single_event( time() + DAY_IN_SECONDS, Close_Date::LEGACY_EVENT_HOOK, array( $post_id, 'comments' ) );
		Close_Date::maybe_migrate();

		Deactivator::deactivate( false );

		$this->assertFalse( wp_next_scheduled( Close_Date::SWEEP_HOOK ) );
		$this->assertFalse( wp_get_scheduled_event( Close_Date::LEGACY_EVENT_HOOK, array( $post_id, 'comments' ) ) );
		$this->assertFalse( get_option( Close_Date::MIGRATED_OPTION, false ) );
	}
}
