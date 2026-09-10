<?php
/**
 * Plugin activation logic.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Core;

use WebberZone\AutoClose\Options_API;

/**
 * Fired during plugin activation.
 *
 * @since 3.0.0
 */
class Activator {

	/**
	 * Number of sites inspected per activation batch.
	 *
	 * @var int
	 */
	private const SITE_BATCH_SIZE = 100;

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			$offset = 0;
			do {
				$site_ids = get_sites(
					array(
						'archived' => 0,
						'spam'     => 0,
						'deleted'  => 0,
						'number'   => self::SITE_BATCH_SIZE,
						'offset'   => $offset,
						'fields'   => 'ids',
					)
				);

				foreach ( $site_ids as $site_id ) {
					switch_to_blog( (int) $site_id );
					try {
						self::single_activate();
					} finally {
						restore_current_blog();
					}
				}

				$batch_count = count( $site_ids );
				$offset     += $batch_count;
			} while ( self::SITE_BATCH_SIZE === $batch_count );
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
		\WebberZone\AutoClose\Features\Close_Date::maybe_migrate();

		if ( ! Options_API::get_option( 'cron_on' ) ) {
			return;
		}

		$cron = new \WebberZone\AutoClose\Util\Cron();
		$cron->enable_run(
			(int) Options_API::get_option( 'cron_hour' ),
			(int) Options_API::get_option( 'cron_min' ),
			Options_API::get_option( 'cron_recurrence' ),
			true
		);
	}
}
