<?php
/**
 * Localization functions.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Utilities;

/**
 * Internationalization class.
 *
 * @since 3.0.0
 */
class L10n {

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Constructor code.
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 3.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'autoclose',
			false,
			dirname( plugin_basename( ACC_PLUGIN_FILE ) ) . '/languages/'
		);
	}
}
