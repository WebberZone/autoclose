<?php
/**
 * WP-CLI pings commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Features\Comments;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Open or close pingbacks and trackbacks from the command line.
 *
 * @since 3.2.0
 */
class Pings_Command extends Discussions_Command {

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 *
	 * @param Comments|null $comments Discussion processor.
	 */
	public function __construct( $comments = null ) {
		parent::__construct( 'ping', $comments );
	}

	/**
	 * Close matching pingbacks and trackbacks.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Omit to operate on every matching public non-attachment post type supporting trackbacks.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs. Can be used instead of positional IDs.
	 *
	 * [--age=<days>]
	 * : Only match posts older than this many days. Default: 0 (no age filter).
	 *
	 * [--post-types=<types>]
	 * : Comma-separated post types to include. Overrides the default post types.
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
	 *     wp autoclose pings close --age=90 --post-types=post,page
	 *     wp autoclose pings close 1234 5678
	 *     wp autoclose pings close --dry-run --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function close( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		parent::close( $args, $assoc_args );
	}

	/**
	 * Open matching pingbacks and trackbacks.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Omit to operate on every matching public non-attachment post type supporting trackbacks.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs. Can be used instead of positional IDs.
	 *
	 * [--age=<days>]
	 * : Only match posts older than this many days. Default: 0 (no age filter).
	 *
	 * [--post-types=<types>]
	 * : Comma-separated post types to include. Overrides the default post types.
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
	 *     wp autoclose pings open 1234
	 *     wp autoclose pings open --post-types=post
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function open( $args, $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		parent::open( $args, $assoc_args );
	}
}
