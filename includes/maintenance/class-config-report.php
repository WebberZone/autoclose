<?php
/**
 * On-demand configuration report.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\Maintenance;

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;
use WebberZone\AutoClose\Options_API;
use WebberZone\AutoClose\Util\Helpers;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Builds a bounded, non-personal snapshot of effective AutoClose configuration.
 *
 * The report never includes comment content, post content, credentials, or
 * other personal information: post scoping is reported as bounded counts, not
 * lists of IDs or titles.
 *
 * @since 3.2.0
 */
class Config_Report {

	/**
	 * Build the effective configuration report for the current site.
	 *
	 * @since 3.2.0
	 *
	 * @return array Configuration report.
	 */
	public static function build(): array {
		$status             = Status::get();
		$comments           = new Comments();
		$revisions          = new Revisions();
		$revision_types     = $revisions->get_revision_post_types();
		$revision_keep      = array();
		$comment_post_types = Helpers::parse_post_types( Options_API::get_option( 'comment_post_types' ) );
		$pbtb_post_types    = Helpers::parse_post_types( Options_API::get_option( 'pbtb_post_types' ) );
		$comment_age_types  = array();
		$pbtb_age_types     = array();

		foreach ( $revision_types as $post_type => $label ) {
			$revision_keep[ $post_type ] = array(
				'label' => $label,
				'keep'  => (int) Options_API::get_option( "revision_{$post_type}" ),
			);
		}

		foreach ( $comment_post_types as $post_type ) {
			$comment_age_types[ $post_type ] = $comments->get_effective_age( 'comment', $post_type );
		}

		foreach ( $pbtb_post_types as $post_type ) {
			$pbtb_age_types[ $post_type ] = $comments->get_effective_age( 'ping', $post_type );
		}

		return array(
			'generated_at'  => gmdate( 'c' ),
			'site'          => $status['site'],
			'schedule'      => array(
				'enabled'    => (bool) Options_API::get_option( 'cron_on' ),
				'hour'       => (int) Options_API::get_option( 'cron_hour' ),
				'minute'     => (int) Options_API::get_option( 'cron_min' ),
				'timezone'   => 'UTC',
				'recurrence' => (string) Options_API::get_option( 'cron_recurrence' ),
			),
			'comments'      => array(
				'enabled'                 => (bool) Options_API::get_option( 'close_comment' ),
				'post_types'              => $comment_post_types,
				'age_days'                => (int) Options_API::get_option( 'comment_age' ),
				'age_days_by_type'        => $comment_age_types,
				'count_threshold'         => $comments->get_count_threshold(),
				'keep_open_post_id_count' => count( wp_parse_id_list( Options_API::get_option( 'comment_pids' ) ) ),
				'exclude_term_id_count'   => count( wp_parse_id_list( Options_API::get_option( 'comment_exclude_term_ids' ) ) ),
				'reopen_on_update'        => (bool) Options_API::get_option( 'reopen_on_update' ),
				'reopen_days'             => (int) Options_API::get_option( 'reopen_days' ),
			),
			'pings'         => array(
				'enabled'                 => (bool) Options_API::get_option( 'close_pbtb' ),
				'post_types'              => $pbtb_post_types,
				'age_days'                => (int) Options_API::get_option( 'pbtb_age' ),
				'age_days_by_type'        => $pbtb_age_types,
				'keep_open_post_id_count' => count( wp_parse_id_list( Options_API::get_option( 'pbtb_pids' ) ) ),
				'exclude_term_id_count'   => count( wp_parse_id_list( Options_API::get_option( 'pbtb_exclude_term_ids' ) ) ),
				'block_self_pings'        => (bool) Options_API::get_option( 'block_self_pings' ),
				'block_ping_url_count'    => count( self::get_lines( Options_API::get_option( 'block_ping_urls' ) ) ),
			),
			'revisions'     => array(
				'delete_all' => (bool) Options_API::get_option( 'delete_revisions' ),
				'age_days'   => $revisions->get_revision_age(),
				'retention'  => $revision_keep,
			),
			'close_dates'   => array(
				'posts_with_explicit_close_dates' => self::count_close_date_posts(),
			),
			'reopen'        => array(
				'posts_with_active_reopen_window' => self::count_active_reopen_windows(),
			),
			'notifications' => array(
				'enabled' => (bool) Options_API::get_option( 'email_notify' ),
			),
		);
	}

	/**
	 * Count posts with an explicit comments or pings close date, bounded to avoid
	 * an unbounded full-table scan on very large sites.
	 *
	 * @since 3.2.0
	 *
	 * @return int Count. Reports the bound as a lower bound when reached.
	 */
	private static function count_close_date_posts(): int {
		global $wpdb;

		$bound = (int) apply_filters( 'acc_config_report_count_bound', 100000 );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM ( SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_acc_comments_date', '_acc_pings_date') AND meta_value <> '' LIMIT %d ) AS bounded",
				$bound
			)
		);
	}

	/**
	 * Count posts with an active (future) reopen window, bounded to avoid an
	 * unbounded full-table scan on very large sites.
	 *
	 * @since 3.2.0
	 *
	 * @return int Count. Reports the bound as a lower bound when reached.
	 */
	private static function count_active_reopen_windows(): int {
		global $wpdb;

		$bound = (int) apply_filters( 'acc_config_report_count_bound', 100000 );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM ( SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_acc_reopen_until' AND CAST(meta_value AS UNSIGNED) > UNIX_TIMESTAMP() LIMIT %d ) AS bounded",
				$bound
			)
		);
	}

	/**
	 * Split newline-separated URLs into a clean list.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $value Stored URL list.
	 * @return array<int, string> URLs.
	 */
	private static function get_lines( $value ): array {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $value ) ) ) );
	}
}
