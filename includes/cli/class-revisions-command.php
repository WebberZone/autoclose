<?php
/**
 * WP-CLI revisions commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Features\Revisions;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Preview, prune, or permanently delete post revisions.
 *
 * @since 3.2.0
 */
class Revisions_Command extends Base_Command {

	/**
	 * Revisions processor.
	 *
	 * @var Revisions
	 */
	private $revisions;

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 *
	 * @param Revisions|null $revisions Revisions processor.
	 */
	public function __construct( $revisions = null ) {
		$this->revisions = $revisions instanceof Revisions ? $revisions : new Revisions();
	}

	/**
	 * Preview revisions without changing content.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Parent post IDs. Omit to include all revisions.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated parent post IDs.
	 *
	 * [--age=<days>]
	 * : Age cutoff in days for the pruning policy. Defaults to the saved setting.
	 *
	 * [--all]
	 * : Preview the delete-all action instead of the pruning policy.
	 *
	 * [--sample=<number>]
	 * : Maximum sample rows. Default: 10. Maximum: 100.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose revisions preview
	 *     wp autoclose revisions preview --age=30
	 *     wp autoclose revisions preview 1234 --format=json
	 *     wp autoclose revisions preview --all
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function preview( $args, $assoc_args ): void {
		$mode = isset( $assoc_args['all'] ) ? 'all' : 'prune';
		$this->execute( $args, $assoc_args, $mode, true );
	}

	/**
	 * Delete revisions beyond the retention limit and older than the age cutoff.
	 *
	 * Autosaves are never removed by this command.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Parent post IDs. Omit to prune every post.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated parent post IDs.
	 *
	 * [--age=<days>]
	 * : Age cutoff in days. Defaults to the saved setting. Zero ignores age.
	 *
	 * [--limit=<number>]
	 * : Maximum revisions to delete in this run. Defaults to the filtered plugin limit.
	 *
	 * [--dry-run]
	 * : Preview the pruning without changing content.
	 *
	 * [--sample=<number>]
	 * : Maximum sample rows in dry-run output. Default: 10. Maximum: 100.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose revisions prune --dry-run
	 *     wp autoclose revisions prune --age=30 --yes
	 *     wp autoclose revisions prune 1234 --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function prune( $args, $assoc_args ): void {
		$this->execute( $args, $assoc_args, 'prune', isset( $assoc_args['dry-run'] ) );
	}

	/**
	 * Permanently delete every revision, ignoring retention limits and age.
	 *
	 * This also removes autosaves. Use `prune` for ordinary cleanup.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Parent post IDs. Omit to delete all revisions.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated parent post IDs.
	 *
	 * [--dry-run]
	 * : Preview the deletion without changing content.
	 *
	 * [--sample=<number>]
	 * : Maximum sample rows in dry-run output. Default: 10. Maximum: 100.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose revisions delete
	 *     wp autoclose revisions delete 1234 --yes
	 *     wp autoclose revisions delete --dry-run --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function delete( $args, $assoc_args ): void {
		$this->execute( $args, $assoc_args, 'all', isset( $assoc_args['dry-run'] ) );
	}

	/**
	 * Execute a revision preview, prune, or delete-all.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @param string $mode       Either `prune` or `all`.
	 * @param bool   $dry_run    Whether to preview only.
	 */
	private function execute( array $args, array $assoc_args, string $mode, bool $dry_run ): void {
		$format       = $this->get_format( $assoc_args );
		$sample_limit = $this->validate_sample_limit( $this->get_non_negative_int( $assoc_args, 'sample', 10 ) );
		$post_ids     = $this->parse_ids( $args, $assoc_args );
		$age          = isset( $assoc_args['age'] ) ? $this->get_non_negative_int( $assoc_args, 'age', 0 ) : null;
		$limit        = isset( $assoc_args['limit'] ) ? $this->get_non_negative_int( $assoc_args, 'limit', 0 ) : null;

		$preview_data = $this->get_preview_data( $sample_limit, $post_ids, $mode, $age, $limit );

		if ( 'failed' === ( $preview_data['status'] ?? 'failed' ) ) {
			$this->report( $format, $dry_run ? 'dry-run' : 'run', 'failed', $preview_data, $post_ids, 0, array() );
			return;
		}

		if ( $dry_run ) {
			$this->report(
				$format,
				'dry-run',
				'success',
				$preview_data,
				$post_ids,
				(int) ( $preview_data['affected'] ?? 0 ),
				(array) ( $preview_data['sample'] ?? array() )
			);
			return;
		}

		$affected = (int) ( $preview_data['affected'] ?? 0 );

		if ( $affected > 0 && ! isset( $assoc_args['yes'] ) ) {
			\WP_CLI::confirm( $this->get_confirmation( $mode, $post_ids ) );
		}

		$operation = 'all' === $mode
			? $this->run_delete_all( $post_ids )
			: $this->run_prune( $post_ids, $age, $limit );

		$this->report( $format, 'run', $operation['status'], $operation, $post_ids, (int) $operation['affected'], array() );
	}

	/**
	 * Fetch preview data for the requested mode.
	 *
	 * @since 3.2.0
	 *
	 * @param int      $sample_limit Maximum sample rows.
	 * @param array    $post_ids     Parent post IDs.
	 * @param string   $mode         Either `prune` or `all`.
	 * @param int|null $age          Age cutoff override in days.
	 * @param int|null $limit        Maximum candidates to collect.
	 * @return array Preview data.
	 */
	private function get_preview_data( int $sample_limit, array $post_ids, string $mode, ?int $age, ?int $limit ): array {
		if ( 'all' === $mode ) {
			return $this->revisions->get_preview( $sample_limit, $post_ids, false, 'all' );
		}

		return $this->revisions->get_prune_preview( $sample_limit, $post_ids, $age, $limit );
	}

	/**
	 * Delete every revision for the given scope.
	 *
	 * @since 3.2.0
	 *
	 * @param array $post_ids Parent post IDs.
	 * @return array Operation result.
	 */
	private function run_delete_all( array $post_ids ): array {
		$result = $this->revisions->delete_revisions( $post_ids );

		return array(
			'status'        => false === $result ? 'failed' : 'success',
			'mode'          => 'all',
			'affected'      => false === $result ? 0 : (int) $result,
			'scanned'       => false === $result ? 0 : (int) $result,
			'limit_reached' => false,
			'errors'        => false === $result ? array( $this->get_database_error() ) : array(),
		);
	}

	/**
	 * Prune revisions for the given scope.
	 *
	 * @since 3.2.0
	 *
	 * @param array    $post_ids Parent post IDs.
	 * @param int|null $age      Age cutoff override in days.
	 * @param int|null $limit    Maximum revisions to delete.
	 * @return array Operation result.
	 */
	private function run_prune( array $post_ids, ?int $age, ?int $limit ): array {
		$result = $this->revisions->prune_revisions(
			array(
				'post_ids' => $post_ids,
				'age_days' => $age,
				'limit'    => $limit,
			)
		);

		return array(
			'status'        => $result['status'],
			'mode'          => 'prune',
			'age'           => null === $age ? $this->revisions->get_revision_age() : max( 0, $age ),
			'affected'      => (int) $result['deleted'],
			'scanned'       => (int) $result['scanned'],
			'limit_reached' => (bool) $result['limit_reached'],
			'errors'        => $result['errors'],
		);
	}

	/**
	 * Build the confirmation prompt for a destructive run.
	 *
	 * @since 3.2.0
	 *
	 * @param string $mode     Either `prune` or `all`.
	 * @param array  $post_ids Parent post IDs.
	 * @return string Prompt text.
	 */
	private function get_confirmation( string $mode, array $post_ids ): string {
		if ( 'all' === $mode ) {
			return empty( $post_ids )
				? __( 'This permanently deletes every post revision, including autosaves, ignoring retention limits and age. Continue?', 'autoclose' )
				: sprintf(
					/* translators: 1: Number of posts. */
					__( 'This permanently deletes every revision for %d selected post(s), including autosaves, ignoring retention limits and age. Continue?', 'autoclose' ),
					count( $post_ids )
				);
		}

		return empty( $post_ids )
			? __( 'This permanently deletes revisions beyond the retention limit and older than the age cutoff. Continue?', 'autoclose' )
			: sprintf(
				/* translators: 1: Number of posts. */
				__( 'This permanently deletes revisions beyond the retention limit and older than the age cutoff for %d selected post(s). Continue?', 'autoclose' ),
				count( $post_ids )
			);
	}

	/**
	 * Output a normalized operation summary.
	 *
	 * @since 3.2.0
	 *
	 * @param string $format    Output format.
	 * @param string $run_mode  Either `run` or `dry-run`.
	 * @param string $outcome   Operation outcome.
	 * @param array  $operation Operation data.
	 * @param array  $post_ids  Parent post IDs.
	 * @param int    $affected  Affected count.
	 * @param array  $sample    Sample rows.
	 */
	private function report( string $format, string $run_mode, string $outcome, array $operation, array $post_ids, int $affected, array $sample ): void {
		$outcome               = 'failed' === $outcome ? 'failed' : 'success';
		$operation['post_ids'] = $post_ids;
		$operation['sample']   = $sample;
		$operation['errors']   = array_values( (array) ( $operation['errors'] ?? array() ) );

		$data = $this->operation_summary(
			$run_mode,
			$outcome,
			$operation,
			array(
				'affected' => $affected,
				'errors'   => $operation['errors'],
			)
		);

		$rows = array(
			$this->row( 'Mode', $data['mode'] ),
			$this->row( 'Policy', ( $operation['mode'] ?? 'prune' ) === 'all' ? 'delete-all' : 'retention + age' ),
			$this->row( 'Outcome', $data['outcome'] ),
			$this->row( 'Site ID', $data['blog_id'] ),
			$this->row( 'Site URL', $data['site_url'] ),
			$this->row( 'Age cutoff (days)', (int) ( $operation['age'] ?? 0 ) ),
			$this->row( 'Affected', $data['affected'] ),
			$this->row( 'Revisions scanned', (int) ( $operation['scanned'] ?? 0 ) ),
			$this->row( 'More remaining', ! empty( $operation['limit_reached'] ) ? 'yes' : 'no' ),
			$this->row( 'Parent post IDs', $post_ids ),
			$this->row( 'Sample revision IDs', $this->get_sample_ids( $sample ) ),
			$this->row( 'Errors', empty( $data['errors'] ) ? 'None' : implode( '; ', $data['errors'] ) ),
		);

		$this->output( $data, $format, $rows );
		$this->exit_for_outcome( $outcome );
	}

	/**
	 * Validate the sample limit.
	 *
	 * @since 3.2.0
	 *
	 * @param int $sample_limit Sample limit.
	 * @return int Validated sample limit.
	 */
	private function validate_sample_limit( int $sample_limit ): int {
		if ( $sample_limit < 1 || $sample_limit > 100 ) {
			\WP_CLI::error( __( 'The sample size must be between 1 and 100.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		return $sample_limit;
	}

	/**
	 * Return the latest database error.
	 *
	 * @since 3.2.0
	 *
	 * @return string Database error message.
	 */
	private function get_database_error(): string {
		global $wpdb;

		return ! empty( $wpdb->last_error ) ? $wpdb->last_error : __( 'Revision deletion failed.', 'autoclose' );
	}
}
