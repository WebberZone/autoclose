<?php
/**
 * Plugin deactivation logic.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\Core;

/**
 * Fired during plugin deactivation.
 *
 * @since 3.0.0
 */
class Deactivator {

	/**
	 * Number of sites inspected per deactivation batch.
	 *
	 * @var int
	 */
	private const SITE_BATCH_SIZE = 100;


	/**
	 * Fired during plugin deactivation.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $network_wide Whether to deactivate network-wide.
	 */
	public static function deactivate( $network_wide = false ) {
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
						self::single_deactivate();
					} finally {
						restore_current_blog();
					}
				}

				$batch_count = count( $site_ids );
				$offset     += $batch_count;
			} while ( self::SITE_BATCH_SIZE === $batch_count );
			return;
		}

		self::single_deactivate();
	}

	/**
	 * Clear scheduled hooks for the current site.
	 *
	 * @since 3.2.0
	 */
	private static function single_deactivate() {
		wp_clear_scheduled_hook( 'acc_cron_hook' );
		wp_unschedule_hook( 'autoclose_close_comments_pings_event' );
	}
}
