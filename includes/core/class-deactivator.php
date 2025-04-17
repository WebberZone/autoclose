<?php
/**
 * Plugin deactivation logic.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Core;

/**
 * Fired during plugin deactivation.
 *
 * @since 3.0.0
 */
class Deactivator {

	/**
	 * Fired during plugin deactivation.
	 *
	 * @since 3.0.0
	 */
	public static function deactivate() {
		// Clear scheduled hooks.
		if ( wp_next_scheduled( 'acc_cron_hook' ) ) {
			wp_clear_scheduled_hook( 'acc_cron_hook' );
		}
	}
}
