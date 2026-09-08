<?php
/**
 * WP-CLI commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Maintenance\Runner;
use WebberZone\AutoClose\Maintenance\Status;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manage AutoClose maintenance from the command line.
 *
 * @since 3.2.0
 */
class CLI extends Base_Command {

	/**
	 * Successful command exit code.
	 *
	 * @since 3.2.0
	 */
	const EXIT_SUCCESS = 0;

	/**
	 * Failed command exit code.
	 *
	 * @since 3.2.0
	 */
	const EXIT_FAILURE = 1;

	/**
	 * Partial command exit code.
	 *
	 * @since 3.2.0
	 */
	const EXIT_PARTIAL = 2;

	/**
	 * Invalid argument exit code.
	 *
	 * @since 3.2.0
	 */
	const EXIT_INVALID = 3;

	/**
	 * Maintenance runner.
	 *
	 * @since 3.2.0
	 * @var Runner
	 */
	private $runner;

	/**
	 * Constructor.
	 *
	 * @since 3.2.0
	 *
	 * @param Runner|null $runner Maintenance runner.
	 */
	public function __construct( $runner = null ) {
		$this->runner = $runner instanceof Runner ? $runner : new Runner();
	}

	/**
	 * Show plugin and cron health status.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose status
	 *     wp autoclose status --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$format = $this->get_format( $assoc_args );
		$data   = Status::get();

		$this->output( $data, $format, $this->get_status_rows( $data ) );
	}

	/**
	 * Run configured maintenance or show a read-only preview.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show the effective filters, counts, and a sample without changing content, settings, events, or notifications.
	 *
	 * [--sample=<number>]
	 * : Maximum sample rows per operation in dry-run output. Only available with --dry-run. Default: 10. Maximum: 100.
	 *
	 * [--yes]
	 * : Skip confirmation when configured revision deletion makes the run destructive. Use this for unattended runs.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose run
	 *     wp autoclose run --dry-run
	 *     wp autoclose run --dry-run --sample=25 --format=json
	 *     wp autoclose run --yes
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function run( $args, $assoc_args ) {
		$format       = $this->get_format( $assoc_args );
		$dry_run      = isset( $assoc_args['dry-run'] );
		$sample_limit = isset( $assoc_args['sample'] ) ? absint( $assoc_args['sample'] ) : 10;

		if ( ! $dry_run && isset( $assoc_args['sample'] ) ) {
			\WP_CLI::error( __( 'The sample option is only available with --dry-run.', 'autoclose' ), self::EXIT_INVALID );
		}

		if ( $sample_limit < 1 || $sample_limit > 100 ) {
			\WP_CLI::error( __( 'The sample size must be between 1 and 100.', 'autoclose' ), self::EXIT_INVALID );
		}

		if ( ! $dry_run && $this->runner->deletes_data() && ! isset( $assoc_args['yes'] ) ) {
			\WP_CLI::confirm( __( 'This run is configured to permanently delete post revisions. Continue?', 'autoclose' ) );
		}

		$data = $this->runner->run( $dry_run, $sample_limit );
		$this->output( $data, $format, $this->get_run_rows( $data ) );

		$this->exit_for_outcome( $data['outcome'] ?? 'failed' );
	}

	/**
	 * Flatten status data for table and CSV output.
	 *
	 * @since 3.2.0
	 *
	 * @param array $status Status report.
	 * @return array Output rows.
	 */
	private function get_status_rows( array $status ): array {
		$schedule = $status['schedule'] ?? array();
		$features = $status['features'] ?? array();
		$last_run = $status['last_run'] ?? array();
		$counts   = $last_run['counts'] ?? array();

		return array(
			$this->row( 'Health', $status['health'] ?? 'unknown' ),
			$this->row( 'Plugin version', $status['plugin']['version'] ?? 'unknown' ),
			$this->row( 'Site', $status['site']['name'] ?? '' ),
			$this->row( 'Site ID', $status['site']['id'] ?? '' ),
			$this->row( 'Site URL', $status['site']['url'] ?? '' ),
			$this->row( 'Timezone', $status['site']['timezone'] ?? 'UTC' ),
			$this->row( 'Multisite', $this->format_value( $status['site']['multisite'] ?? false ) ),
			$this->row( 'Schedule enabled', $this->format_value( $schedule['enabled'] ?? false ) ),
			$this->row( 'Cron event registered', $this->format_value( $schedule['event_exists'] ?? false ) ),
			$this->row( 'Schedule timezone', $schedule['timezone'] ?? 'UTC' ),
			$this->row( 'Next run', $schedule['next_run_at'] ?? 'Never' ),
			$this->row( 'Recurrence', $schedule['recurrence'] ?? '' ),
			$this->row( 'Schedule overdue', $this->format_value( $schedule['overdue'] ?? false ) ),
			$this->row( 'DISABLE_WP_CRON', $this->format_value( $schedule['wp_cron_disabled'] ?? false ) ),
			$this->row( 'Close comments', $this->format_value( $features['close_comments'] ?? false ) ),
			$this->row( 'Close pings', $this->format_value( $features['close_pings'] ?? false ) ),
			$this->row( 'Delete revisions', $this->format_value( $features['delete_revisions'] ?? false ) ),
			$this->row( 'Last attempt', $this->format_timestamp( $last_run['attempt'] ?? null ) ),
			$this->row( 'Last completed', $this->format_timestamp( $last_run['completed'] ?? null ) ),
			$this->row( 'Last successful completion', $this->format_timestamp( $last_run['success'] ?? null ) ),
			$this->row( 'Last outcome', $last_run['outcome'] ?? 'Never' ),
			$this->row( 'Comments closed', $counts['comments_closed'] ?? 0 ),
			$this->row( 'Pings closed', $counts['pings_closed'] ?? 0 ),
			$this->row( 'Comments opened', $counts['comments_opened'] ?? 0 ),
			$this->row( 'Pings opened', $counts['pings_opened'] ?? 0 ),
			$this->row( 'Revisions deleted', $counts['revisions_deleted'] ?? 0 ),
			$this->row( 'Last errors', empty( $last_run['errors'] ?? array() ) ? 'None' : implode( '; ', $last_run['errors'] ) ),
			$this->row( 'Warnings', empty( $status['warnings'] ?? array() ) ? 'None' : implode( '; ', $status['warnings'] ) ),
		);
	}

	/**
	 * Flatten run data for table and CSV output.
	 *
	 * @since 3.2.0
	 *
	 * @param array $data Run or preview data.
	 * @return array Output rows.
	 */
	private function get_run_rows( array $data ): array {
		$components = $data['components'] ?? array();
		$rows       = array(
			$this->row( 'Mode', $data['mode'] ?? 'run' ),
			$this->row( 'Outcome', $data['outcome'] ?? 'failed' ),
			$this->row( 'Site ID', $data['blog_id'] ?? '' ),
			$this->row( 'Site URL', $data['site_url'] ?? '' ),
			$this->row( 'Started', $data['started_at'] ?? '' ),
			$this->row( 'Completed', $data['completed_at'] ?? '' ),
			$this->row( 'Comments closed', $data['comments_closed'] ?? 0 ),
			$this->row( 'Pings closed', $data['pings_closed'] ?? 0 ),
			$this->row( 'Comments opened', $data['comments_opened'] ?? 0 ),
			$this->row( 'Pings opened', $data['pings_opened'] ?? 0 ),
			$this->row( 'Revisions deleted', $data['revisions_deleted'] ?? 0 ),
			$this->row( 'Comments processor', $components['comments']['status'] ?? 'unknown' ),
			$this->row( 'Revisions processor', $components['revisions']['status'] ?? 'unknown' ),
			$this->row( 'Errors', empty( $data['errors'] ?? array() ) ? 'None' : implode( '; ', $data['errors'] ) ),
		);

		if ( 'dry-run' === ( $data['mode'] ?? '' ) ) {
			$rows = array_merge( $rows, $this->get_preview_rows( $components ) );
		}

		return $rows;
	}

	/**
	 * Flatten preview operations for table and CSV output.
	 *
	 * @since 3.2.0
	 *
	 * @param array $components Preview components.
	 * @return array Output rows.
	 */
	private function get_preview_rows( array $components ): array {
		$rows = array();

		foreach ( $components as $component_name => $component ) {
			foreach ( (array) ( $component['operations'] ?? array() ) as $operation_name => $operation ) {
				$rows[] = $this->row( ucfirst( str_replace( '_', ' ', $component_name . ' ' . $operation_name ) ) . ' affected', $operation['affected'] ?? 0 );
				$rows[] = $this->row( ucfirst( str_replace( '_', ' ', $component_name . ' ' . $operation_name ) ) . ' cutoff', $operation['cutoff_gmt'] ?? 'None' );
				$rows[] = $this->row( ucfirst( str_replace( '_', ' ', $component_name . ' ' . $operation_name ) ) . ' sample IDs', $this->get_sample_ids( $operation['sample'] ?? array() ) );
			}

			if ( 'revisions' === $component_name ) {
				$rows[] = $this->row( 'Revisions affected', $component['affected'] ?? 0 );
				$rows[] = $this->row( 'Revisions sample IDs', $this->get_sample_ids( $component['sample'] ?? array() ) );
			}
		}

		return $rows;
	}
}
