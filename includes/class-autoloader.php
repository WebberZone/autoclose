<?php
/**
 * Autoloader for AutoClose plugin.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose;

/**
 * Autoloader class.
 *
 * @since 3.0.0
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @since 3.0.0
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload function.
	 *
	 * @since 3.0.0
	 *
	 * @param string $class_name The class name to autoload.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		// If the class doesn't use our namespace, skip it.
		$namespace_prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $namespace_prefix ) ) {
			return;
		}

		// Remove the namespace.
		$class_name = str_replace( $namespace_prefix, '', $class_name );

		// Convert namespace separators to directory separators.
		$class_path = str_replace( '\\', '/', $class_name );

		// Convert class name format to file name format.
		$class_file = 'class-' . strtolower( str_replace( '_', '-', basename( $class_path ) ) ) . '.php';

		// Get the file path.
		$file_path = dirname( $class_path ) !== '.'
			? ACC_PLUGIN_DIR . 'includes/' . strtolower( dirname( $class_path ) ) . '/' . $class_file
			: ACC_PLUGIN_DIR . 'includes/' . $class_file;

		// If the file exists, require it.
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}

Autoloader::register();
