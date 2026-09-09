<?php
/**
 * Tests for maintenance result aggregation.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;
use WebberZone\AutoClose\Maintenance\Runner;
use WebberZone\AutoClose\Maintenance\Status;

/**
 * Maintenance runner tests.
 */
class RunnerTest extends WP_UnitTestCase {

	/**
	 * Clear persisted runner status after each test.
	 */
	public function tear_down() {
		delete_option( Status::OPTION_NAME );

		parent::tear_down();
	}

	/**
	 * A partial processor result is preserved in the aggregate summary.
	 */
	public function test_partial_processor_result_is_not_reported_as_success() {
		$comments  = new RunnerCommentsTestDouble(
			array(
				'status'          => 'partial',
				'comments_closed' => 2,
				'pings_closed'    => 0,
				'errors'          => array( 'One discussion batch failed.' ),
			)
		);
		$revisions = new RunnerRevisionsTestDouble(
			array(
				'status'                  => 'success',
				'revisions_deleted'       => 1,
				'revisions_scanned'       => 3,
				'revisions_limit_reached' => false,
				'errors'                  => array(),
			)
		);

		$summary = ( new Runner( $comments, $revisions ) )->run();

		$this->assertSame( 'partial', $summary['outcome'] );
		$this->assertSame( 2, $summary['comments_closed'] );
		$this->assertSame( 1, $summary['revisions_deleted'] );
		$this->assertSame( array( 'One discussion batch failed.' ), $summary['errors'] );
	}

	/**
	 * Two skipped processors produce a no-op result, not a successful run.
	 */
	public function test_all_skipped_processors_are_reported_as_skipped() {
		$comments  = new RunnerCommentsTestDouble( array( 'status' => 'skipped', 'errors' => array() ) );
		$revisions = new RunnerRevisionsTestDouble( array( 'status' => 'skipped', 'errors' => array() ) );

		$summary = ( new Runner( $comments, $revisions ) )->run();

		$this->assertSame( 'skipped', $summary['outcome'] );
		$this->assertSame( array(), $summary['errors'] );
	}

	/**
	 * A preview does not persist an attempt or completed run.
	 */
	public function test_preview_does_not_update_run_history() {
		$comments  = new RunnerCommentsTestDouble( array( 'status' => 'skipped', 'errors' => array() ) );
		$revisions = new RunnerRevisionsTestDouble( array( 'status' => 'skipped', 'errors' => array() ) );

		$summary = ( new Runner( $comments, $revisions ) )->preview();

		$this->assertSame( 'dry-run', $summary['mode'] );
		$this->assertSame( 'skipped', $summary['outcome'] );
		$this->assertSame( array(), Status::get_saved() );
	}

	/**
	 * Each run summary is built from the current processor results only.
	 */
	public function test_repeated_runs_do_not_reuse_previous_counts() {
		$comments  = new RunnerCommentsTestDouble(
			array(
				array(
					'status'          => 'success',
					'comments_closed' => 3,
					'pings_closed'    => 0,
					'errors'          => array(),
				),
				array(
					'status'          => 'success',
					'comments_closed' => 0,
					'pings_closed'    => 0,
					'errors'          => array(),
				),
			)
		);
		$revisions = new RunnerRevisionsTestDouble(
			array(
				array(
					'status'            => 'success',
					'revisions_deleted' => 4,
					'revisions_scanned' => 4,
					'errors'            => array(),
				),
				array(
					'status'            => 'success',
					'revisions_deleted' => 0,
					'revisions_scanned' => 0,
					'errors'            => array(),
				),
			)
		);
		$runner = new Runner( $comments, $revisions );

		$first  = $runner->run();
		$second = $runner->run();

		$this->assertSame( 3, $first['comments_closed'] );
		$this->assertSame( 4, $first['revisions_deleted'] );
		$this->assertSame( 0, $second['comments_closed'] );
		$this->assertSame( 0, $second['revisions_deleted'] );
	}
}

/**
 * Comments processor double for runner tests.
 */
class RunnerCommentsTestDouble extends Comments {

	/**
	 * Results returned by process_comments().
	 *
	 * @var array
	 */
	private $results;

	/**
	 * @param array $results Processor results.
	 */
	public function __construct( array $results ) {
		$this->results = $results;
	}

	/**
	 * Return the next configured result.
	 *
	 * @return array Processor result.
	 */
	public function process_comments(): array {
		$result = is_array( reset( $this->results ) ) ? array_shift( $this->results ) : $this->results;

		return $result;
	}

	/**
	 * Return an empty preview result.
	 *
	 * @param int $sample_limit Sample limit.
	 * @return array Preview result.
	 */
	public function get_preview( int $sample_limit = 10 ): array {
		return array( 'status' => 'skipped', 'errors' => array() );
	}
}

/**
 * Revisions processor double for runner tests.
 */
class RunnerRevisionsTestDouble extends Revisions {

	/**
	 * Results returned by process_revisions().
	 *
	 * @var array
	 */
	private $results;

	/**
	 * @param array $results Processor results.
	 */
	public function __construct( array $results ) {
		$this->results = $results;
	}

	/**
	 * Return the next configured result.
	 *
	 * @return array Processor result.
	 */
	public function process_revisions(): array {
		$result = is_array( reset( $this->results ) ) ? array_shift( $this->results ) : $this->results;

		return $result;
	}

	/**
	 * Return an empty preview result.
	 *
	 * @param int          $sample_limit    Sample limit.
	 * @param array|string $post_ids        Parent post IDs.
	 * @param bool         $respect_setting Whether to respect the deletion setting.
	 * @param string       $mode            Preview mode.
	 * @return array Preview result.
	 */
	public function get_preview( int $sample_limit = 10, $post_ids = array(), bool $respect_setting = true, string $mode = 'prune' ): array {
		return array( 'status' => 'skipped', 'errors' => array() );
	}
}
