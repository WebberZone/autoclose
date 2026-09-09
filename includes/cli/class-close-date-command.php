<?php
/**
 * WP-CLI per-post close-date commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Features\Close_Date;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manage per-post comment and ping close dates.
 *
 * @since 3.2.0
 */
class Close_Date_Command extends Base_Command {

	/**
	 * Close-date processor.
	 *
	 * @var Close_Date
	 */
	private $close_date;

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 *
	 * @param Close_Date|null $close_date Close-date processor.
	 */
	public function __construct( $close_date = null ) {
		$this->close_date = $close_date instanceof Close_Date ? $close_date : new Close_Date();
	}

	/**
	 * List configured per-post close dates.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs. Omit to list posts with a configured close date.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs.
	 *
	 * [--limit=<number>]
	 * : Maximum rows when listing all posts. Default: 100. Maximum: 1000.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose close-date list
	 *     wp autoclose close-date list 1234 --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list( $args, $assoc_args ): void {
		$format   = $this->get_format( $assoc_args );
		$post_ids = $this->parse_ids( $args, $assoc_args );
		$limit    = $this->get_non_negative_int( $assoc_args, 'limit', 100 );

		if ( $limit < 1 || $limit > 1000 ) {
			\WP_CLI::error( __( 'The limit must be between 1 and 1000.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		if ( empty( $post_ids ) ) {
			$post_ids = $this->get_configured_post_ids( $limit );
			if ( false === $post_ids ) {
				\WP_CLI::error( __( 'The close-date list query failed.', 'autoclose' ), CLI::EXIT_FAILURE );
			}
		}

		$items = array();
		foreach ( $post_ids as $post_id ) {
			$item = $this->get_item( $post_id );
			if ( $item ) {
				$items[] = $item;
			}
		}

		$data = array(
			'mode'     => 'list',
			'blog_id'  => (int) get_current_blog_id(),
			'site_url' => home_url( '/' ),
			'items'    => $items,
		);

		$this->output( $data, $format, $this->get_item_rows( $items ), array( 'ID', 'Title', 'Post type', 'Comments close', 'Pings close', 'Comment status', 'Ping status' ) );
	}

	/**
	 * Set one or both per-post close dates.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Post ID.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs. Exactly one ID is required.
	 *
	 * [--comments=<datetime>]
	 * : Site-local close date in Y-m-dTH:i format.
	 *
	 * [--pings=<datetime>]
	 * : Site-local close date in Y-m-dTH:i format.
	 *
	 * [--dry-run]
	 * : Show the normalized dates without saving or scheduling them.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose close-date set 1234 --comments=2026-10-01T09:00
	 *     wp autoclose close-date set 1234 --comments=2026-10-01T09:00 --pings=2026-10-01T09:00
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function set( $args, $assoc_args ): void {
		$format   = $this->get_format( $assoc_args );
		$post_ids = $this->parse_ids( $args, $assoc_args );

		if ( 1 !== count( $post_ids ) ) {
			\WP_CLI::error( __( 'Set requires exactly one post ID.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		$values = array();
		foreach ( array( 'comments', 'pings' ) as $type ) {
			if ( isset( $assoc_args[ $type ] ) ) {
				$values[ $type ] = $this->parse_date( $assoc_args[ $type ] );
			}
		}

		if ( empty( $values ) ) {
			\WP_CLI::error( __( 'Provide --comments, --pings, or both.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		$post_id = $post_ids[0];
		$this->require_post( $post_id );
		$dry_run = isset( $assoc_args['dry-run'] );
		$errors  = array();

		if ( ! $dry_run ) {
			foreach ( $values as $type => $date ) {
				update_post_meta( $post_id, "_acc_{$type}_date", $date );
			}
			$result = $this->close_date->maybe_schedule_or_close( $post_id );
			$errors = $result['errors'];
		}

		$item = $this->get_item( $post_id );

		$data = array(
			'mode'     => $dry_run ? 'dry-run' : 'run',
			'outcome'  => empty( $errors ) ? 'success' : 'failed',
			'blog_id'  => (int) get_current_blog_id(),
			'site_url' => home_url( '/' ),
			'post_id'  => $post_id,
			'set'      => $values,
			'item'     => $item,
			'errors'   => $errors,
		);

		$this->output( $data, $format, $this->get_action_rows( $data ) );
		$this->exit_for_outcome( $data['outcome'] );
	}

	/**
	 * Clear one or both per-post close dates.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Post IDs.
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated post IDs.
	 *
	 * [--comments]
	 * : Clear only comment close dates.
	 *
	 * [--pings]
	 * : Clear only ping close dates.
	 *
	 * [--dry-run]
	 * : Show what would be cleared without changing metadata or schedules.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose close-date clear 1234
	 *     wp autoclose close-date clear 1234 5678 --comments
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function clear( $args, $assoc_args ): void {
		$format   = $this->get_format( $assoc_args );
		$post_ids = $this->parse_ids( $args, $assoc_args );

		if ( empty( $post_ids ) ) {
			\WP_CLI::error( __( 'Clear requires at least one post ID.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		$types = array();
		if ( isset( $assoc_args['comments'] ) ) {
			$types[] = 'comments';
		}
		if ( isset( $assoc_args['pings'] ) ) {
			$types[] = 'pings';
		}
		if ( empty( $types ) ) {
			$types = array( 'comments', 'pings' );
		}

		$dry_run = isset( $assoc_args['dry-run'] );
		$items   = array();
		$errors  = array();
		foreach ( $post_ids as $post_id ) {
			$this->require_post( $post_id );
			if ( ! $dry_run ) {
				foreach ( $types as $type ) {
					delete_post_meta( $post_id, "_acc_{$type}_date" );
				}
				$result = $this->close_date->maybe_schedule_or_close( $post_id );
				$errors = array_merge( $errors, $result['errors'] );
			}
			$items[] = $this->get_item( $post_id );
		}

		$data = array(
			'mode'     => $dry_run ? 'dry-run' : 'run',
			'outcome'  => empty( $errors ) ? 'success' : 'failed',
			'blog_id'  => (int) get_current_blog_id(),
			'site_url' => home_url( '/' ),
			'clear'    => $types,
			'items'    => $items,
			'errors'   => $errors,
		);

		$this->output( $data, $format, $this->get_action_rows( $data ) );
		$this->exit_for_outcome( $data['outcome'] );
	}

	/**
	 * Find post IDs with either close-date meta key.
	 *
	 * @since 3.2.0
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, int>|false Post IDs, or false on database failure.
	 */
	private function get_configured_post_ids( int $limit ) {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_acc_comments_date', '_acc_pings_date') AND meta_value <> '' ORDER BY post_id ASC LIMIT %d",
				$limit
			)
		);

		if ( null === $ids && ! empty( $wpdb->last_error ) ) {
			return false;
		}

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Get one post's close-date state.
	 *
	 * @since 3.2.0
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Item data, or null when the post does not exist.
	 */
	private function get_item( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		return array(
			'ID'             => $post_id,
			'Title'          => get_the_title( $post_id ),
			'post_type'      => $post->post_type,
			'comments_date'  => (string) get_post_meta( $post_id, '_acc_comments_date', true ),
			'pings_date'     => (string) get_post_meta( $post_id, '_acc_pings_date', true ),
			'comment_status' => (string) $post->comment_status,
			'ping_status'    => (string) $post->ping_status,
		);
	}

	/**
	 * Ensure a post exists.
	 *
	 * @since 3.2.0
	 *
	 * @param int $post_id Post ID.
	 */
	private function require_post( int $post_id ): void {
		if ( ! get_post( $post_id ) ) {
			\WP_CLI::error( sprintf( __( 'Post %d was not found on the current site.', 'autoclose' ), $post_id ), CLI::EXIT_FAILURE );
		}
	}

	/**
	 * Normalize a site-local date.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $value Date value.
	 * @return string Normalized date.
	 */
	private function parse_date( $value ): string {
		$value  = trim( (string) $value, " \n\r\t\v\x00" );
		$format = '!Y-m-d\\TH:i';
		$utc    = \DateTimeImmutable::createFromFormat( $format, $value, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTimeImmutable::getLastErrors();

		if ( false === $utc || ( false !== $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $utc->format( 'Y-m-d\\TH:i' ) !== $value ) {
			\WP_CLI::error( __( 'Dates must use the site-local Y-m-dTH:i format, for example 2026-10-01T09:00.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		$parsed = \DateTimeImmutable::createFromFormat( $format, $value, wp_timezone() );
		if ( false === $parsed || $parsed->format( 'Y-m-d\\TH:i' ) !== $value ) {
			\WP_CLI::error( __( 'That local time does not exist in the site timezone because of a daylight-saving transition. Choose another time.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		return $parsed->format( 'Y-m-d\\TH:i' );
	}

	/**
	 * Convert item data into table rows.
	 *
	 * @since 3.2.0
	 *
	 * @param array $items Item data.
	 * @return array Output rows.
	 */
	private function get_item_rows( array $items ): array {
		$rows = array();
		foreach ( $items as $item ) {
			$rows[] = array(
				'ID'             => $item['ID'],
				'Title'          => $item['Title'],
				'Post type'      => $item['post_type'],
				'Comments close' => $item['comments_date'],
				'Pings close'    => $item['pings_date'],
				'Comment status' => $item['comment_status'],
				'Ping status'    => $item['ping_status'],
			);
		}

		return $rows;
	}

	/**
	 * Convert an action result into field/value rows.
	 *
	 * @since 3.2.0
	 *
	 * @param array $data Action data.
	 * @return array Output rows.
	 */
	private function get_action_rows( array $data ): array {
		$items = isset( $data['item'] ) ? array( $data['item'] ) : (array) ( $data['items'] ?? array() );
		$rows  = array(
			$this->row( 'Mode', $data['mode'] ),
			$this->row( 'Outcome', $data['outcome'] ),
			$this->row( 'Site ID', $data['blog_id'] ),
			$this->row( 'Site URL', $data['site_url'] ),
			$this->row( 'Post IDs', array_column( $items, 'ID' ) ),
			$this->row( 'Comments close', array_column( $items, 'comments_date' ) ),
			$this->row( 'Pings close', array_column( $items, 'pings_date' ) ),
		);

		if ( isset( $data['set'] ) ) {
			$rows[] = $this->row( 'Set', $data['set'] );
		}
		if ( isset( $data['clear'] ) ) {
			$rows[] = $this->row( 'Cleared', $data['clear'] );
		}
		$rows[] = $this->row( 'Errors', empty( $data['errors'] ?? array() ) ? 'None' : implode( '; ', $data['errors'] ) );

		return $rows;
	}
}
