<?php
/**
 * Tests for the on-demand configuration report.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Maintenance\Config_Report;
use WebberZone\AutoClose\Options_API;

/**
 * Configuration report tests.
 */
class ConfigReportTest extends WP_UnitTestCase {

	/**
	 * Tear down each test.
	 */
	public function tear_down() {
		delete_option( Options_API::SETTINGS_OPTION );
		Options_API::flush_cache();

		parent::tear_down();
	}

	/**
	 * The report exposes bounded counts, not individual post IDs or content.
	 */
	public function test_report_reports_counts_not_ids_or_content() {
		update_option(
			Options_API::SETTINGS_OPTION,
			array(
				'comment_pids'             => '1,2,3',
				'comment_exclude_term_ids' => '4,5',
			)
		);
		Options_API::flush_cache();

		$report = Config_Report::build();

		$this->assertSame( 3, $report['comments']['keep_open_post_id_count'] );
		$this->assertSame( 2, $report['comments']['exclude_term_id_count'] );
		$this->assertArrayNotHasKey( 'keep_open_post_ids', $report['comments'] );
	}

	/**
	 * Explicit close dates and active reopen windows are counted.
	 */
	public function test_report_counts_close_dates_and_reopen_windows() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_acc_comments_date', '2030-01-01T00:00' );

		$other_id = self::factory()->post->create();
		update_post_meta( $other_id, '_acc_reopen_until', time() + DAY_IN_SECONDS );

		$expired_id = self::factory()->post->create();
		update_post_meta( $expired_id, '_acc_reopen_until', time() - DAY_IN_SECONDS );

		$report = Config_Report::build();

		$this->assertSame( 1, $report['close_dates']['posts_with_explicit_close_dates'] );
		$this->assertSame( 1, $report['reopen']['posts_with_active_reopen_window'] );
	}
}
