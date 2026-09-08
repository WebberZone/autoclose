<?php
/**
 * WP-CLI pingback/trackback commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Features\Comments;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Preview or permanently delete pingbacks and trackbacks.
 *
 * @since 3.2.0
 */
class Pingbacks_Command extends Base_Command {

	/**
	 * Comments processor.
	 *
	 * @var Comments
	 */
	private $comments;

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 *
	 * @param Comments|null $comments Comments processor.
	 */
	public function __construct( $comments = null ) {
		$this->comments = $comments instanceof Comments ? $comments : new Comments();
	}

	/**
	 * Preview pingback/trackback deletion.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Omit to include all pingbacks and trackbacks.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs.
	 *
	 * [--sample=<number>]
	 * : Maximum sample rows. Default: 10. Maximum: 100.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose pingbacks preview
	 *     wp autoclose pingbacks preview 1234 --format=json
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
	 * Permanently delete pingbacks and trackbacks.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Omit to delete all pingbacks and trackbacks.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs.
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
	 *     wp autoclose pingbacks delete
	 *     wp autoclose pingbacks delete 1234 --yes
	 *     wp autoclose pingbacks delete --dry-run --format=json
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
	 * Execute a pingback/trackback preview or deletion.
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
			$operation = $this->comments->preview_pingbacks( $post_ids, $sample_limit );
			$outcome   = $this->normalize_outcome( $operation['status'] ?? 'failed' );
			$affected  = (int) ( $operation['affected'] ?? 0 );
			$sample    = (array) ( $operation['sample'] ?? array() );
		} else {
			$preview_data = $this->comments->preview_pingbacks( $post_ids, $sample_limit );
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
						? __( 'This permanently deletes all pingbacks and trackbacks. Continue?', 'autoclose' )
						: sprintf( __( 'This permanently deletes pingbacks and trackbacks for %d selected post(s). Continue?', 'autoclose' ), count( $post_ids ) );
					\WP_CLI::confirm( $message );
				}

				$operation             = $this->comments->delete_pingbacks_result( $post_ids );
				$operation['post_ids'] = $post_ids;
				$operation['affected'] = (int) $operation['deleted'];
				$outcome               = $this->normalize_outcome( $operation['status'] ?? 'failed' );
				$affected              = (int) $operation['affected'];
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
			$this->row( 'Post IDs', $post_ids ),
			$this->row( 'Sample comment IDs', $this->get_sample_ids( $sample ) ),
			$this->row( 'Errors', empty( $data['errors'] ) ? 'None' : implode( '; ', $data['errors'] ) ),
		);

		$this->output( $data, $format, $rows );
		$this->exit_for_outcome( $outcome );
	}

	/**
	 * Normalize a processor result to a supported command outcome.
	 *
	 * @since 3.2.0
	 *
	 * @param string $status Processor status.
	 * @return string Command outcome.
	 */
	private function normalize_outcome( string $status ): string {
		return in_array( $status, array( 'success', 'partial', 'failed', 'skipped' ), true ) ? $status : 'failed';
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
}
