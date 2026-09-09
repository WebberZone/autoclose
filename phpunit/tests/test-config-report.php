<?php
/**
 * Tests for the on-demand configuration report.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Maintenance\Config_Report;
use WebberZone\AutoClose\Admin\Tools;
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
				'email_notify_address'     => 'private@example.com',
			)
		);
		Options_API::flush_cache();

		$report = Config_Report::build();

		$this->assertSame( 3, $report['comments']['keep_open_post_id_count'] );
		$this->assertSame( 2, $report['comments']['exclude_term_id_count'] );
		$this->assertArrayNotHasKey( 'keep_open_post_ids', $report['comments'] );
		$this->assertArrayNotHasKey( 'address', $report['notifications'] );
		$this->assertStringNotContainsString( 'private@example.com', wp_json_encode( $report ) );
	}

	/**
	 * Explicit close dates and active reopen windows are counted.
	 */
	public function test_report_counts_close_dates_and_reopen_windows() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_acc_comments_date', '2030-01-01T00:00' );

		$other_id = self::factory()->post->create();
		update_post_meta( $other_id, '_acc_reopen_until', time() + DAY_IN_SECONDS );
		add_post_meta( $other_id, '_acc_reopen_until', time() + ( 2 * DAY_IN_SECONDS ), false );

		$expired_id = self::factory()->post->create();
		update_post_meta( $expired_id, '_acc_reopen_until', time() - DAY_IN_SECONDS );

		$report = Config_Report::build();

		$this->assertSame( 1, $report['close_dates']['posts_with_explicit_close_dates'] );
		$this->assertSame( 1, $report['reopen']['posts_with_active_reopen_window'] );
	}

	/**
	 * The rendered report includes per-post-type discussion ages.
	 */
	public function test_rendered_report_includes_effective_type_ages() {
		update_option(
			Options_API::SETTINGS_OPTION,
			array(
				'comment_post_types'  => 'post',
				'comment_age'         => 90,
				'comment_age_post'    => 30,
				'pbtb_post_types'     => 'post',
				'pbtb_age'            => 90,
				'pbtb_age_post'       => -1,
			)
		);
		Options_API::flush_cache();

		$html = ( new Tools() )->render_config_report( Config_Report::build() );

		$this->assertStringContainsString( 'Comments age by post type', $html );
		$this->assertStringContainsString( 'post: 30 days', $html );
		$this->assertStringContainsString( 'Pings age by post type', $html );
		$this->assertStringContainsString( 'post: never', $html );
	}
}
