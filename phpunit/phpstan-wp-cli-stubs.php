<?php
/**
 * Minimal WP-CLI declarations for PHPStan.
 *
 * @package AutoClose
 */

namespace {
	if ( ! class_exists( 'WP_CLI' ) ) {
		/**
		 * @phpstan-type Command object
		 */
		class WP_CLI {

			/**
			 * @param string $name Command name.
			 * @param object $command Command implementation.
			 */
			public static function add_command( string $name, object $command ): void {}

			/**
			 * @param string $message Error message.
			 * @param int    $exit_code Exit code.
			 */
			public static function error( string $message, int $exit_code = 1 ): void {}

			/**
			 * @param string $question Confirmation question.
			 */
			public static function confirm( string $question ): void {}

			/**
			 * @param string $message Output message.
			 */
			public static function line( string $message ): void {}
		}
	}
}

namespace WP_CLI\Utils {

	/**
	 * @param string $format Output format.
	 * @param array  $items Output rows.
	 * @param array  $fields Output fields.
	 */
	function format_items( string $format, array $items, array $fields ): void {}
}
