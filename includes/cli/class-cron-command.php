<?php
/**
 * WP-CLI cron commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Util\Cron;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Inspect and repair AutoClose's scheduled event.
 *
 * @since 3.2.0
 */
class Cron_Command extends Base_Command {

	/**
	 * Repair the scheduled maintenance event.
	 *
	 * This only repairs the event when scheduled maintenance is enabled in the
	 * current site's settings. It does not enable or disable the feature.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Reschedule the event even when it is already registered.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp autoclose cron repair
	 *     wp autoclose cron repair --force --format=json
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function repair( $args, $assoc_args ): void {
		$format = $this->get_format( $assoc_args );
		$force  = isset( $assoc_args['force'] );
		$result = ( new Cron() )->repair( $force );

		$data = array_merge(
			array(
				'mode'     => 'run',
				'blog_id'  => (int) get_current_blog_id(),
				'site_url' => home_url( '/' ),
				'action'   => 'repair',
			),
			$result
		);

		$rows = array(
			$this->row( 'Mode', $data['mode'] ),
			$this->row( 'Outcome', $data['outcome'] ),
			$this->row( 'Site ID', $data['blog_id'] ),
			$this->row( 'Site URL', $data['site_url'] ),
			$this->row( 'Scheduled maintenance enabled', $result['enabled'] ),
			$this->row( 'Forced', $force ),
			$this->row( 'Previous event overdue', $result['overdue'] ),
			$this->row( 'Previous event', null === $result['before'] ? 'None' : wp_date( DATE_ATOM, $result['before'] ) ),
			$this->row( 'Current event', null === $result['after'] ? 'None' : wp_date( DATE_ATOM, $result['after'] ) ),
			$this->row( 'Recurrence', $result['recurrence'] ),
			$this->row( 'Errors', empty( $result['errors'] ) ? 'None' : implode( '; ', $result['errors'] ) ),
		);

		$this->output( $data, $format, $rows );
		$this->exit_for_outcome( $data['outcome'] );
	}
}
