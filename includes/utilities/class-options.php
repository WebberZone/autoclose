<?php
/**
 * Options API.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Utilities;

use WebberZone\AutoClose\Admin\Settings;

/**
 * Options class.
 *
 * @since 3.0.0
 */
class Options {

	/**
	 * Settings key.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private static $settings_key = 'acc_settings';

	/**
	 * Holds the plugin options array.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private static $options;

	/**
	 * Get the plugin options.
	 *
	 * @since 3.0.0
	 * @return array Plugin options
	 */
	public static function get_options() {
		if ( ! isset( self::$options ) ) {
			self::$options = get_option( self::$settings_key, self::get_defaults() );
		}

		/**
		 * Filter the options array.
		 *
		 * @since 2.0.0
		 * @param array $options Options array.
		 */
		return apply_filters( 'acc_get_settings', self::$options );
	}

	/**
	 * Get a specific option.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key           Option to fetch.
	 * @param mixed  $default_value Default option.
	 * @return mixed Option value
	 */
	public static function get_option( $key = '', $default_value = null ) {
		$options = self::get_options();

		if ( null === $default_value ) {
			$default_value = self::get_default_option( $key );
		}

		$value = $options[ $key ] ?? $default_value;

		/**
		 * Filter the value for the option being fetched.
		 *
		 * @since 2.0.0
		 *
		 * @param mixed $value         Value of the option.
		 * @param mixed $key           Name of the option.
		 * @param mixed $default_value Default value.
		 */
		$value = apply_filters( 'acc_get_option', $value, $key, $default_value );

		/**
		 * Key specific filter for the value of the option being fetched.
		 *
		 * @since 2.0.0
		 *
		 * @param mixed $value         Value of the option.
		 * @param mixed $key           Name of the option.
		 * @param mixed $default_value Default value.
		 */
		return apply_filters( "acc_get_option_{$key}", $value, $key, $default_value );
	}

	/**
	 * Update an option.
	 *
	 * @since 3.0.0
	 *
	 * @param string          $key   The Key to update.
	 * @param string|bool|int $value The value to set the key to.
	 * @return boolean True if updated, false if not.
	 */
	public static function update_option( $key = '', $value = false ) {
		// If no key, exit.
		if ( empty( $key ) ) {
			return false;
		}

		// If no value, delete.
		if ( empty( $value ) ) {
			$remove_option = self::delete_option( $key );
			return $remove_option;
		}

		// First let's grab the current settings.
		$options = self::get_options();

		// Let's let devs alter that value coming in.
		$value = apply_filters( 'acc_update_option', $value, $key );

		// Next let's try to update the value.
		$options[ $key ] = $value;
		$did_update      = update_option( self::$settings_key, $options );

		// If it updated, let's update the global variable.
		if ( $did_update ) {
			self::$options[ $key ] = $value;
		}
		return $did_update;
	}

	/**
	 * Remove an option.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key The Key to delete.
	 * @return boolean True if updated, false if not.
	 */
	public static function delete_option( $key = '' ) {
		// If no key, exit.
		if ( empty( $key ) ) {
			return false;
		}

		// First let's grab the current settings.
		$options = self::get_options();

		// Next let's try to update the value.
		if ( isset( $options[ $key ] ) ) {
			unset( $options[ $key ] );
		}

		$did_update = update_option( self::$settings_key, $options );

		// If it updated, let's update the global variable.
		if ( $did_update ) {
			self::$options = $options;
		}
		return $did_update;
	}

	/**
	 * Reset settings.
	 *
	 * @since 3.0.0
	 */
	public static function reset_settings() {
		$settings = self::get_defaults();
		update_option( self::$settings_key, $settings );
		self::$options = $settings;
	}

	/**
	 * Get default settings.
	 *
	 * @since 3.0.0
	 * @return array Default settings
	 */
	public static function get_defaults() {
		$options = array();

		// Always use Settings class to get registered settings.
		$registered_settings = Settings::get_registered_settings();

		// Populate default values.
		foreach ( $registered_settings as $tab => $settings ) {
			foreach ( $settings as $option ) {
				// When checkbox is set to true, set this to 1.
				if ( 'checkbox' === $option['type'] && ! empty( $option['options'] ) ) {
					$options[ $option['id'] ] = 1;
				} else {
					$options[ $option['id'] ] = 0;
				}
				// If an option is set.
				if ( in_array( $option['type'], array( 'textarea', 'css', 'html', 'text', 'url', 'csv', 'color', 'numbercsv', 'postids', 'posttypes', 'number', 'wysiwyg', 'file', 'password' ), true ) && isset( $option['options'] ) ) {
					$options[ $option['id'] ] = $option['options'];
				}
				if ( in_array( $option['type'], array( 'multicheck', 'radio', 'select', 'radiodesc', 'thumbsizes' ), true ) && isset( $option['default'] ) ) {
					$options[ $option['id'] ] = $option['default'];
				}
			}
		}

		/**
		 * Filters the default settings array.
		 *
		 * @since 2.0.0
		 *
		 * @param array $options Default settings.
		 */
		return apply_filters( 'acc_settings_defaults', $options );
	}

	/**
	 * Get the default option for a specific key.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key Key of the option to fetch.
	 * @return mixed Default value
	 */
	public static function get_default_option( $key = '' ) {
		$default_settings = self::get_defaults();

		if ( array_key_exists( $key, $default_settings ) ) {
			return $default_settings[ $key ];
		}

		return null;
	}
}
