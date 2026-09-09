<?php
/**
 * Maintenance execution and preview orchestration.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\Maintenance;

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Runs the same maintenance processors used by cron and the CLI.
 *
 * @since 3.2.0
 */
class Runner {

	/**
	 * Comments processor.
	 *
	 * @since 3.2.0
	 * @var Comments
	 */
	private $comments;

	/**
	 * Revisions processor.
	 *
	 * @since 3.2.0
	 * @var Revisions
	 */
	private $revisions;

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 *
	 * @param Comments|null  $comments  Comments processor.
	 * @param Revisions|null $revisions Revisions processor.
	 */
	public function __construct( $comments = null, $revisions = null ) {
		$this->comments  = $comments instanceof Comments ? $comments : new Comments();
		$this->revisions = $revisions instanceof Revisions ? $revisions : new Revisions();
	}

	/**
	 * Run maintenance or return a read-only preview.
	 *
	 * @since 3.2.0
	 *
	 * @param bool $dry_run       Whether to preview without writes.
	 * @param int  $sample_limit  Maximum sample rows per operation.
	 * @return array Run or preview summary.
	 */
	public function run( bool $dry_run = false, int $sample_limit = 10 ): array {
		if ( $dry_run ) {
			return $this->preview( $sample_limit );
		}

		$started_at = time();
		$run_id     = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'acc-', true );

		Status::record_attempt( $started_at, $run_id );
		do_action( 'acc_maintenance_run_started' );

		$comments     = $this->run_processor( $this->comments, 'comments' );
		$revisions    = $this->run_processor( $this->revisions, 'revisions' );
		$completed_at = time();

		$summary = $this->build_summary(
			$comments,
			$revisions,
			$run_id,
			$started_at,
			$completed_at
		);

		Status::record_run( $summary );

		return $summary;
	}

	/**
	 * Return a read-only preview using the processors' eligibility queries.
	 *
	 * @since 3.2.0
	 *
	 * @param int $sample_limit Maximum sample rows per operation.
	 * @return array Preview summary.
	 */
	public function preview( int $sample_limit = 10 ): array {
		$sample_limit = max( 1, min( 100, $sample_limit ) );
		$started_at   = time();
		$comments     = $this->preview_processor( $this->comments, $sample_limit, 'comments' );
		$revisions    = $this->preview_processor( $this->revisions, $sample_limit, 'revisions' );

		$summary         = $this->build_summary(
			$comments,
			$revisions,
			null,
			$started_at,
			time()
		);
		$summary['mode'] = 'dry-run';

		return $summary;
	}

	/**
	 * Whether the configured run can delete data.
	 *
	 * @since 3.2.0
	 *
	 * @return bool Whether revision deletion is enabled.
	 */
	public function deletes_data(): bool {
		return (bool) \WebberZone\AutoClose\Options_API::get_option( 'delete_revisions' );
	}

	/**
	 * Invoke a processor and normalize unexpected failures.
	 *
	 * @since 3.2.0
	 *
	 * @param object $processor Processor instance.
	 * @param string $name      Processor name.
	 * @return array Processor result.
	 */
	private function run_processor( $processor, string $name ): array {
		try {
			$result = 'comments' === $name ? $processor->process_comments() : $processor->process_revisions();
			return is_array( $result ) ? $result : $this->failed_processor_result( $name, __( 'Processor returned an invalid result.', 'autoclose' ) );
		} catch ( \Throwable $exception ) {
			return $this->failed_processor_result( $name, $exception->getMessage() );
		}
	}

	/**
	 * Invoke a processor preview and normalize unexpected failures.
	 *
	 * @since 3.2.0
	 *
	 * @param object $processor    Processor instance.
	 * @param int    $sample_limit Maximum sample rows.
	 * @param string $name         Processor name.
	 * @return array Processor preview.
	 */
	private function preview_processor( $processor, int $sample_limit, string $name ): array {
		try {
			$result = $processor->get_preview( $sample_limit );
			return is_array( $result ) ? $result : $this->failed_processor_result( $name, __( 'Preview returned an invalid result.', 'autoclose' ) );
		} catch ( \Throwable $exception ) {
			return $this->failed_processor_result( $name, $exception->getMessage() );
		}
	}

	/**
	 * Build a common run/preview summary.
	 *
	 * @since 3.2.0
	 *
	 * @param array       $comments    Comments result.
	 * @param array       $revisions   Revisions result.
	 * @param string|null $run_id      Run identifier.
	 * @param int         $started_at  Start timestamp.
	 * @param int         $completed_at Completion timestamp.
	 * @return array Summary.
	 */
	private function build_summary( array $comments, array $revisions, $run_id, int $started_at, int $completed_at ): array {
		$components = array(
			'comments'  => $comments,
			'revisions' => $revisions,
		);
		$errors     = array();
		$successful = 0;
		$failed     = 0;
		$partial    = 0;
		$attempted  = 0;

		foreach ( $components as $component ) {
			$status = $component['status'] ?? 'failed';

			if ( 'skipped' !== $status ) {
				++$attempted;
			}
			if ( 'failed' === $status ) {
				++$failed;
			}
			if ( 'partial' === $status ) {
				++$partial;
			}
			if ( 'success' === $status ) {
				++$successful;
			}
			$errors = array_merge( $errors, array_values( (array) ( $component['errors'] ?? array() ) ) );
		}

		$outcome = 'success';
		if ( $failed > 0 ) {
			$outcome = ( $successful > 0 || $partial > 0 ) ? 'partial' : 'failed';
		} elseif ( $partial > 0 ) {
			$outcome = 'partial';
		} elseif ( 0 === $attempted ) {
			$outcome = empty( $errors ) ? 'skipped' : 'failed';
		}

		$summary = array(
			'mode'                     => 'run',
			'outcome'                  => $outcome,
			'run_id'                   => $run_id,
			'blog_id'                  => (int) get_current_blog_id(),
			'site_url'                 => home_url( '/' ),
			'started_at_timestamp'     => $started_at,
			'started_at'               => gmdate( 'c', $started_at ),
			'completed_at_timestamp'   => $completed_at,
			'completed_at'             => gmdate( 'c', $completed_at ),
			'comments_closed'          => (int) ( $comments['comments_closed'] ?? 0 ),
			'pings_closed'             => (int) ( $comments['pings_closed'] ?? 0 ),
			'comments_opened'          => (int) ( $comments['comments_opened'] ?? 0 ),
			'pings_opened'             => (int) ( $comments['pings_opened'] ?? 0 ),
			'comments_migrated'        => (int) ( $comments['comments_migrated'] ?? 0 ),
			'pings_migrated'           => (int) ( $comments['pings_migrated'] ?? 0 ),
			'legacy_statuses_migrated' => (int) ( $comments['comments_migrated'] ?? 0 ) + (int) ( $comments['pings_migrated'] ?? 0 ),
			'revisions_deleted'        => (int) ( $revisions['revisions_deleted'] ?? 0 ),
			'revisions_affected'       => (int) ( $revisions['revisions_deleted'] ?? $revisions['affected'] ?? 0 ),
			'revisions_scanned'        => (int) ( $revisions['revisions_scanned'] ?? $revisions['scanned'] ?? 0 ),
			'revisions_remaining'      => (bool) ( $revisions['revisions_limit_reached'] ?? $revisions['limit_reached'] ?? false ),
			'errors'                   => $errors,
			'components'               => $components,
		);

		return $summary;
	}

	/**
	 * Create a normalized failed processor result.
	 *
	 * @since 3.2.0
	 *
	 * @param string $name    Processor name.
	 * @param string $message Failure message.
	 * @return array Failed result.
	 */
	private function failed_processor_result( string $name, string $message ): array {
		return array(
			'status' => 'failed',
			'errors' => array( sprintf( '%s: %s', ucfirst( $name ), $message ) ),
		);
	}
}
