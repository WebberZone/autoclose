<?php
/**
 * WP-CLI command registration.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Registers the complete AutoClose WP-CLI command tree.
 *
 * @since 3.2.0
 */
class CLI_Manager {

	/**
	 * Register all AutoClose commands.
	 *
	 * @since 3.2.0
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'autoclose', new CLI() );
		\WP_CLI::add_command( 'autoclose settings', new Settings_Command() );
		\WP_CLI::add_command( 'autoclose comments', new Comments_Command() );
		\WP_CLI::add_command( 'autoclose pings', new Pings_Command() );
		\WP_CLI::add_command( 'autoclose revisions', new Revisions_Command() );
		\WP_CLI::add_command( 'autoclose pingbacks', new Pingbacks_Command() );
		\WP_CLI::add_command( 'autoclose close-date', new Close_Date_Command() );
		\WP_CLI::add_command( 'autoclose cron', new Cron_Command() );
	}
}
