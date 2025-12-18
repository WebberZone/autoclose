<?php
/**
 * Block Self-Pings and Custom Ping URLs
 *
 * @since      3.0.0
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Util\Options;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Block_Pings
 *
 * Blocks self-pings and user-defined URLs from receiving pings.
 *
 * @since 3.0.0
 */
class Block_Pings {
	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Remove self-pings and user-defined URLs from ping list.
	 *
	 * @param array $links List of ping URLs (passed by reference).
	 *
	 * @since 3.0.0
	 */
	public function block_pings( &$links ) {
		$home = home_url();

		// Retrieve user-defined blocked URLs and self-ping setting using the Options utility class.
		$block_self_pings = Options::get_option( 'block_self_pings', true );
		$extra_urls       = Options::get_option( 'block_ping_urls', '' );

		$url_array = array_filter( array_map( 'trim', explode( PHP_EOL, $extra_urls ) ) );

		foreach ( $links as $l => $link ) {
			if ( $block_self_pings && 0 === strpos( $link, $home ) ) {
				unset( $links[ $l ] );
				continue;
			}
			foreach ( $url_array as $url ) {
				if ( '' !== $url && 0 === strpos( $link, $url ) ) {
					unset( $links[ $l ] );
					break;
				}
			}
		}
	}
}
