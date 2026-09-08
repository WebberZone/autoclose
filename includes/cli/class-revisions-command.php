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
 * Preview or permanently delete post revisions.
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
	 * [--sample=<number>]
	 * : Maximum sample rows. Default: 10. Maximum: 100.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose revisions preview
	 *     wp autoclose revisions preview 1234 --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function preview( $args, $assoc_args ): void {
		$this->execute( $args, $assoc_args, true );
	}

	/**
	 * Permanently delete revisions.
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
		$this->execute( $args, $assoc_args, false );
	}

	/**
	 * Execute a revision preview or deletion.
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @param bool  $preview    Whether this is the preview subcommand.
	 */
	private function execute( array $args, array $assoc_args, bool $preview ): void {
		$format       = $this->get_format( $assoc_args );
		$dry_run      = $preview || isset( $assoc_args['dry-run'] );
		$sample_limit = $this->get_non_negative_int( $assoc_args, 'sample', 10 );
		$sample_limit = $this->validate_sample_limit( $sample_limit );
		$post_ids     = $this->parse_ids( $args, $assoc_args );

		if ( $dry_run ) {
			$operation = $this->revisions->get_preview( $sample_limit, $post_ids, false );
			$outcome   = 'failed' === ( $operation['status'] ?? 'failed' ) ? 'failed' : 'success';
			$affected  = (int) ( $operation['affected'] ?? 0 );
			$sample    = (array) ( $operation['sample'] ?? array() );
		} else {
			$preview_data = $this->revisions->get_preview( $sample_limit, $post_ids, false );
			if ( 'failed' === ( $preview_data['status'] ?? 'failed' ) ) {
				$operation = $preview_data;
				$outcome   = 'failed';
				$affected  = 0;
				$sample    = array();
			} else {
				$affected = (int) ( $preview_data['affected'] ?? 0 );
				$sample   = array();

				if ( $affected > 0 && ! isset( $assoc_args['yes'] ) ) {
					$message = empty( $post_ids )
						? __( 'This permanently deletes all post revisions. Continue?', 'autoclose' )
						: sprintf( __( 'This permanently deletes revisions for %d selected post(s). Continue?', 'autoclose' ), count( $post_ids ) );
					\WP_CLI::confirm( $message );
				}

				$result    = $this->revisions->delete_revisions( $post_ids );
				$operation = array(
					'status'   => false === $result ? 'failed' : 'success',
					'affected' => false === $result ? 0 : (int) $result,
					'post_ids' => $post_ids,
					'errors'   => false === $result ? array( $this->get_database_error() ) : array(),
				);
				$outcome   = 'failed' === $operation['status'] ? 'failed' : 'success';
				$affected  = (int) $operation['affected'];
			}
		}

		$operation['post_ids'] = $post_ids;
		$operation['sample']   = $sample;
		$operation['errors']   = array_values( (array) ( $operation['errors'] ?? array() ) );
		$data                  = $this->operation_summary(
			$dry_run ? 'dry-run' : 'run',
			$outcome,
			$operation,
			array(
				'affected' => $affected,
				'errors'   => $operation['errors'],
			)
		);

		$rows = array(
			$this->row( 'Mode', $data['mode'] ),
			$this->row( 'Outcome', $data['outcome'] ),
			$this->row( 'Site ID', $data['blog_id'] ),
			$this->row( 'Site URL', $data['site_url'] ),
			$this->row( 'Affected', $data['affected'] ),
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
