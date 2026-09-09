<?php
/**
 * Tests for maintenance status health reporting.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Maintenance\Status;
use WebberZone\AutoClose\Options_API;

/**
 * Maintenance status tests.
 */
class StatusTest extends WP_UnitTestCase {

	/**
	 * Clear persisted status after each test.
	 */
	public function tear_down() {
		delete_option( Status::OPTION_NAME );
		delete_option( Options_API::SETTINGS_OPTION );
		Options_API::flush_cache();

		parent::tear_down();
	}

	/**
	 * A failed last run is reported as unhealthy, not silently healthy.
	 */
	public function test_failed_last_run_is_reported_as_unhealthy() {
		Status::record_run(
			array(
				'run_id'                 => 'test-run',
				'outcome'                => 'failed',
				'started_at_timestamp'   => time(),
				'completed_at_timestamp' => time(),
				'errors'                 => array( 'The discussion update failed.' ),
			)
		);

		$status = Status::get();

		$this->assertSame( 'warning', $status['health'] );
		$this->assertContains( 'The last maintenance run did not complete successfully.', $status['warnings'] );
	}

	/**
	 * A partial last run is also reported as unhealthy.
	 */
	public function test_partial_last_run_is_reported_as_unhealthy() {
		Status::record_run(
			array(
				'run_id'                 => 'test-run',
				'outcome'                => 'partial',
				'started_at_timestamp'   => time(),
				'completed_at_timestamp' => time(),
				'errors'                 => array( 'One discussion batch failed.' ),
			)
		);

		$status = Status::get();

		$this->assertSame( 'warning', $status['health'] );
	}

	/**
	 * A run that started but never recorded completion (e.g. a fatal error
	 * or timeout) is reported as unhealthy once it's stale.
	 */
	public function test_stale_running_state_is_reported_as_unhealthy() {
		Status::record_attempt( time() - ( 2 * HOUR_IN_SECONDS ), 'stuck-run' );

		$status = Status::get();

		$this->assertSame( 'warning', $status['health'] );
		$this->assertContains( 'The last maintenance run started but never reported completion.', $status['warnings'] );
	}

	/**
	 * A run still recently in progress is not yet reported as unhealthy.
	 */
	public function test_recent_running_state_is_not_reported_as_unhealthy() {
		Status::record_attempt( time(), 'in-progress-run' );

		$status = Status::get();

		$this->assertSame( 'healthy', $status['health'] );
	}

	/**
	 * A successful last run stays healthy.
	 */
	public function test_successful_last_run_is_reported_as_healthy() {
		Status::record_run(
			array(
				'run_id'                 => 'test-run',
				'outcome'                => 'success',
				'started_at_timestamp'   => time(),
				'completed_at_timestamp' => time(),
				'errors'                 => array(),
			)
		);

		$status = Status::get();

		$this->assertSame( 'healthy', $status['health'] );
		$this->assertSame( array(), $status['warnings'] );
	}
}
