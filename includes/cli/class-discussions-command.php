<?php
/**
 * WP-CLI comments and pings commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Features\Comments;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Open or close comments or pingbacks/trackbacks with explicit filters.
 *
 * @since 3.2.0
 */
class Discussions_Command extends Base_Command {

	/**
	 * Discussion processor.
	 *
	 * @var Comments
	 */
	private $comments;

	/**
	 * Discussion type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 *
	 * @param string        $type     Discussion type: comment or ping.
	 * @param Comments|null $comments Discussion processor.
	 */
	public function __construct( string $type, $comments = null ) {
		$this->type     = in_array( $type, array( 'comment', 'ping' ), true ) ? $type : 'comment';
		$this->comments = $comments instanceof Comments ? $comments : new Comments();
	}

	/**
	 * Human-readable discussion label.
	 *
	 * Resolved on use, not in the constructor: commands are registered on
	 * plugins_loaded, before the textdomain is loaded on init.
	 *
	 * @since 3.2.0
	 *
	 * @return string Discussion label.
	 */
	private function get_label(): string {
		return 'comment' === $this->type ? __( 'comments', 'autoclose' ) : __( 'pingbacks/trackbacks', 'autoclose' );
	}

	/**
	 * Close matching discussions.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Omit to operate on every matching public non-attachment post type supporting the selected discussion type.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs. Can be used instead of positional IDs.
	 *
	 * [--age=<days>]
	 * : Only match posts older than this many days. Default: 0 (no age filter).
	 *
	 * [--post-types=<types>]
	 * : Comma-separated post types to include. Default: public post types supporting the selected discussion type.
	 *
	 * [--exclude-terms=<ids>]
	 * : Comma-separated term taxonomy IDs to exclude.
	 *
	 * [--dry-run]
	 * : Report matching posts without changing content.
	 *
	 * [--sample=<number>]
	 * : Maximum sample rows in dry-run output. Default: 10. Maximum: 100. Requires --dry-run; errors otherwise.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose comments close --age=90 --post-types=post,page
	 *     wp autoclose comments close 1234 5678
	 *     wp autoclose comments close --dry-run --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function close( $args, $assoc_args ): void {
		$this->execute( 'close', $args, $assoc_args );
	}

	/**
	 * Open matching discussions.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Omit to operate on every matching public non-attachment post type supporting the selected discussion type.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs. Can be used instead of positional IDs.
	 *
	 * [--age=<days>]
	 * : Only match posts older than this many days. Default: 0 (no age filter).
	 *
	 * [--post-types=<types>]
	 * : Comma-separated post types to include. Default: public post types supporting the selected discussion type.
	 *
	 * [--exclude-terms=<ids>]
	 * : Comma-separated term taxonomy IDs to exclude.
	 *
	 * [--dry-run]
	 * : Report matching posts without changing content.
	 *
	 * [--sample=<number>]
	 * : Maximum sample rows in dry-run output. Default: 10. Maximum: 100. Requires --dry-run; errors otherwise.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose comments open 1234
	 *     wp autoclose pings open --post-types=post
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function open( $args, $assoc_args ): void {
		$this->execute( 'open', $args, $assoc_args );
	}

	/**
	 * Execute an open or close operation.
	 *
	 * @since 3.2.0
	 *
	 * @param string $action     Operation: open or close.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 */
	private function execute( string $action, array $args, array $assoc_args ): void {
		$format  = $this->get_format( $assoc_args );
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( ! $dry_run && isset( $assoc_args['sample'] ) ) {
			\WP_CLI::error( __( 'The sample option is only available with --dry-run.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		$sample_limit       = $this->get_non_negative_int( $assoc_args, 'sample', 10 );
		$age                = $this->get_non_negative_int( $assoc_args, 'age' );
		$sample_limit       = $this->validate_sample_limit( $sample_limit );
		$post_ids           = $this->parse_ids( $args, $assoc_args );
		$term_ids           = isset( $assoc_args['exclude-terms'] )
			? $this->parse_integer_list( $assoc_args['exclude-terms'], __( 'Exclude term IDs must be positive integers.', 'autoclose' ) )
			: array();
		$post_types         = $this->get_post_types( $assoc_args );
		$discussion_support = 'comment' === $this->type ? 'comments' : 'trackbacks';
		$post_types         = empty( $post_types ) ? $this->get_discussion_post_types( $discussion_support ) : $post_types;
		$query_args         = array(
			'age'           => $age,
			'post_types'    => $post_types,
			'post_ids'      => implode( ',', $post_ids ),
			'exclude_terms' => implode( ',', $term_ids ),
		);

		if ( $dry_run ) {
			$operation = $this->comments->preview_discussions( $this->type, $action, $query_args, $sample_limit );
			$outcome   = $this->normalize_outcome( $operation['status'] ?? 'failed' );
			$affected  = (int) ( $operation['affected'] ?? 0 );
			$sample    = (array) ( $operation['sample'] ?? array() );
		} else {
			$result = 'comment' === $this->type
				? $this->comments->edit_discussions_result( 'comment', $action, $query_args )
				: $this->comments->edit_discussions_result( 'ping', $action, $query_args );

			$operation           = $result;
			$operation['action'] = $action;
			$operation['type']   = $this->type;
			$outcome             = $this->normalize_outcome( $operation['status'] ?? 'failed' );
			$affected            = (int) $operation['affected'];
			$sample              = array();
		}

		$operation['filters']    = array(
			'age_days'      => $age,
			'post_types'    => $post_types,
			'post_ids'      => $post_ids,
			'exclude_terms' => $term_ids,
		);
		$operation['cutoff_gmt'] = $operation['cutoff_gmt'] ?? $this->get_cutoff( $age );
		$operation['sample']     = $sample;
		$operation['errors']     = array_values( (array) ( $operation['errors'] ?? array() ) );

		$data = $this->operation_summary(
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
			$this->row( 'Type', $this->get_label() ),
			$this->row( 'Action', $action ),
			$this->row( 'Affected', $data['affected'] ),
			$this->row( 'Age (days)', $age ),
			$this->row( 'Cutoff (GMT)', $operation['cutoff_gmt'] ?? 'None' ),
			$this->row( 'Post types', $post_types ),
			$this->row( 'Post IDs', $post_ids ),
			$this->row( 'Excluded term IDs', $term_ids ),
			$this->row( 'Sample IDs', $this->get_sample_ids( $sample ) ),
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
