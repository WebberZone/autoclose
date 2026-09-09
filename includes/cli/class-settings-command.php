<?php
/**
 * WP-CLI settings command.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;
use WebberZone\AutoClose\Options_API;
use WebberZone\AutoClose\Maintenance\Status;
use WebberZone\AutoClose\Util\Helpers;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Inspect AutoClose's effective configuration.
 *
 * @since 3.2.0
 */
class Settings_Command extends Base_Command {

	/**
	 * Display the effective settings for the current site.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose settings
	 *     wp autoclose settings --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ): void {
		$data = $this->get_settings();
		$this->output( $data, $this->get_format( $assoc_args ), $this->get_rows( $data ) );
	}

	/**
	 * Build the effective settings report.
	 *
	 * @since 3.2.0
	 *
	 * @return array Settings report.
	 */
	private function get_settings(): array {
		$status         = Status::get();
		$revision_types = ( new Revisions() )->get_revision_post_types();
		$revision_keep  = array();

		foreach ( $revision_types as $post_type => $label ) {
			$revision_keep[ $post_type ] = array(
				'label' => $label,
				'keep'  => (int) Options_API::get_option( "revision_{$post_type}" ),
			);
		}

		$comments           = new Comments();
		$comment_post_types = Helpers::parse_post_types( Options_API::get_option( 'comment_post_types' ) );
		$pbtb_post_types    = Helpers::parse_post_types( Options_API::get_option( 'pbtb_post_types' ) );
		$comment_age_types  = array();
		$pbtb_age_types     = array();

		foreach ( $comment_post_types as $post_type ) {
			$comment_age_types[ $post_type ] = $comments->get_effective_age( 'comment', $post_type );
		}

		foreach ( $pbtb_post_types as $post_type ) {
			$pbtb_age_types[ $post_type ] = $comments->get_effective_age( 'ping', $post_type );
		}

		return array(
			'site'          => $status['site'],
			'schedule'      => array(
				'enabled'    => (bool) Options_API::get_option( 'cron_on' ),
				'hour'       => (int) Options_API::get_option( 'cron_hour' ),
				'minute'     => (int) Options_API::get_option( 'cron_min' ),
				'timezone'   => 'UTC',
				'recurrence' => (string) Options_API::get_option( 'cron_recurrence' ),
			),
			'comments'      => array(
				'enabled'            => (bool) Options_API::get_option( 'close_comment' ),
				'post_types'         => $comment_post_types,
				'age_days'           => (int) Options_API::get_option( 'comment_age' ),
				'age_days_by_type'   => $comment_age_types,
				'count_threshold'    => $comments->get_count_threshold(),
				'keep_open_post_ids' => wp_parse_id_list( Options_API::get_option( 'comment_pids' ) ),
				'exclude_term_ids'   => wp_parse_id_list( Options_API::get_option( 'comment_exclude_term_ids' ) ),
				'reopen_on_update'   => (bool) Options_API::get_option( 'reopen_on_update' ),
				'reopen_days'        => (int) Options_API::get_option( 'reopen_days' ),
			),
			'pings'         => array(
				'enabled'            => (bool) Options_API::get_option( 'close_pbtb' ),
				'post_types'         => $pbtb_post_types,
				'age_days'           => (int) Options_API::get_option( 'pbtb_age' ),
				'age_days_by_type'   => $pbtb_age_types,
				'keep_open_post_ids' => wp_parse_id_list( Options_API::get_option( 'pbtb_pids' ) ),
				'exclude_term_ids'   => wp_parse_id_list( Options_API::get_option( 'pbtb_exclude_term_ids' ) ),
				'block_self_pings'   => (bool) Options_API::get_option( 'block_self_pings' ),
				'block_ping_urls'    => $this->get_lines( Options_API::get_option( 'block_ping_urls' ) ),
			),
			'revisions'     => array(
				'delete_all' => (bool) Options_API::get_option( 'delete_revisions' ),
				'retention'  => $revision_keep,
			),
			'notifications' => array(
				'enabled' => (bool) Options_API::get_option( 'email_notify' ),
				'address' => (string) Options_API::get_option( 'email_notify_address' ),
			),
		);
	}

	/**
	 * Convert the settings report into table/CSV rows.
	 *
	 * @since 3.2.0
	 *
	 * @param array $data Settings report.
	 * @return array Output rows.
	 */
	private function get_rows( array $data ): array {
		$rows = array();
		$this->flatten_rows( $data, '', $rows );

		return $rows;
	}

	/**
	 * Flatten nested settings into field/value rows.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $data   Settings data.
	 * @param string $prefix Field prefix.
	 * @param array  $rows   Output rows passed by reference.
	 */
	private function flatten_rows( array $data, string $prefix, array &$rows ): void {
		foreach ( $data as $key => $value ) {
			$field = '' === $prefix ? (string) $key : $prefix . '.' . $key;

			if ( is_array( $value ) && ! empty( $value ) && ! $this->is_list( $value ) ) {
				$this->flatten_rows( $value, $field, $rows );
			} else {
				$rows[] = $this->row( $field, $value );
			}
		}
	}

	/**
	 * Return whether an array has sequential numeric keys.
	 *
	 * @since 3.2.0
	 *
	 * @param array $value Value.
	 * @return bool Whether the array is a list.
	 */
	private function is_list( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Split newline-separated URLs into a clean list.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $value Stored URL list.
	 * @return array<int, string> URLs.
	 */
	private function get_lines( $value ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $value ) ) ) );
	}
}
