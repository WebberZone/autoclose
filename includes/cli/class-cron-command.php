<?php
/**
 * WP-CLI cron commands.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

use WebberZone\AutoClose\Options_API;
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
		$format  = $this->get_format( $assoc_args );
		$force   = isset( $assoc_args['force'] );
		$before  = wp_next_scheduled( 'acc_cron_hook' );
		$enabled = (bool) Options_API::get_option( 'cron_on' );
		$overdue = false !== $before && (int) $before < time();
		$errors  = array();
		$outcome = 'success';

		if ( ! $enabled ) {
			$outcome  = 'failed';
			$errors[] = __( 'Scheduled maintenance is disabled for the current site.', 'autoclose' );
		} elseif ( false === $before || $overdue || $force ) {
			$cron = new Cron();
			$cron->enable_run(
				(int) Options_API::get_option( 'cron_hour' ),
				(int) Options_API::get_option( 'cron_min' ),
				(string) Options_API::get_option( 'cron_recurrence' ),
				true
			);
		}

		$after = wp_next_scheduled( 'acc_cron_hook' );
		if ( $enabled && false === $after ) {
			$outcome  = 'failed';
			$errors[] = __( 'The AutoClose cron event could not be registered.', 'autoclose' );
		}

		$data = array(
			'mode'       => 'run',
			'outcome'    => $outcome,
			'blog_id'    => (int) get_current_blog_id(),
			'site_url'   => home_url( '/' ),
			'action'     => 'repair',
			'forced'     => $force,
			'overdue'    => $overdue,
			'enabled'    => $enabled,
			'before'     => false === $before ? null : (int) $before,
			'after'      => false === $after ? null : (int) $after,
			'recurrence' => (string) Options_API::get_option( 'cron_recurrence' ),
			'errors'     => $errors,
		);

		$rows = array(
			$this->row( 'Mode', $data['mode'] ),
			$this->row( 'Outcome', $data['outcome'] ),
			$this->row( 'Site ID', $data['blog_id'] ),
			$this->row( 'Site URL', $data['site_url'] ),
			$this->row( 'Scheduled maintenance enabled', $enabled ),
			$this->row( 'Forced', $force ),
			$this->row( 'Previous event overdue', $overdue ),
			$this->row( 'Previous event', false === $before ? 'None' : wp_date( DATE_ATOM, (int) $before ) ),
			$this->row( 'Current event', false === $after ? 'None' : wp_date( DATE_ATOM, (int) $after ) ),
			$this->row( 'Recurrence', $data['recurrence'] ),
			$this->row( 'Errors', empty( $errors ) ? 'None' : implode( '; ', $errors ) ),
		);

		$this->output( $data, $format, $rows );
		$this->exit_for_outcome( $outcome );
	}
}
