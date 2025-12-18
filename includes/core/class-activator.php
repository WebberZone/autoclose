<?php
/**
 * Plugin activation logic.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Core;

use WebberZone\AutoClose\Util\Options;

/**
 * Fired during plugin activation.
 *
 * @since 3.0.0
 */
class Activator {

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		global $wpdb;

		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network and activate plugin on each one.
			$sites = get_sites(
				array(
					'archived' => 0,
					'spam'     => 0,
					'deleted'  => 0,
				)
			);

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::single_activate();
				restore_current_blog();
			}
		} else {
			self::single_activate();
		}
	}

	/**
	 * Activation function for single blogs.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function single_activate() {
		Options::get_options();
	}
}
