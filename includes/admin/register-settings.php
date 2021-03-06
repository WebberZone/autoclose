<?php
/**
 * Register settings.
 *
 * Functions to register, read, write and update settings.
 * Portions of this code have been inspired by Easy Digital Downloads, WordPress Settings Sandbox, etc.
 *
 * @since 2.0.0
 *
 * @package AutoClose
 * @subpackage Admin/Register_Settings
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Get an option
 *
 * Looks to see if the specified setting exists, returns default if not
 *
 * @since 2.0.0
 *
 * @param string $key Key of the option to fetch.
 * @param mixed  $default Default value to fetch if option is missing.
 * @return mixed
 */
function acc_get_option( $key = '', $default = null ) {

	global $acc_settings;

	if ( is_null( $default ) ) {
		$default = acc_get_default_option( $key );
	}

	$value = isset( $acc_settings[ $key ] ) ? $acc_settings[ $key ] : $default;

	/**
	 * Filter the value for the option being fetched.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value  Value of the option
	 * @param mixed $key  Name of the option
	 * @param mixed $default Default value
	 */
	$value = apply_filters( 'acc_get_option', $value, $key, $default );

	/**
	 * Key specific filter for the value of the option being fetched.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value  Value of the option
	 * @param mixed $key  Name of the option
	 * @param mixed $default Default value
	 */
	return apply_filters( 'acc_get_option_' . $key, $value, $key, $default );
}


/**
 * Update an option
 *
 * Updates an AutoClose setting value in both the db and the global variable.
 * Warning: Passing a null value will remove
 *          the key from the acc_settings array.
 *
 * @since 2.0.0
 *
 * @param  string          $key   The Key to update.
 * @param  string|bool|int $value The value to set the key to.
 * @return boolean   True if updated, false if not.
 */
function acc_update_option( $key = '', $value = null ) {

	// If no key, exit.
	if ( empty( $key ) ) {
		return false;
	}

	// If null value, delete.
	if ( is_null( $value ) ) {
		$remove_option = acc_delete_option( $key );
		return $remove_option;
	}

	// First let's grab the current settings.
	$options = get_option( 'acc_settings' );

	/**
	 * Filters the value before it is updated
	 *
	 * @since 2.0.0
	 *
	 * @param  string|bool|int $value The value to set the key to
	 * @param  string          $key   The Key to update
	 */
	$value = apply_filters( 'acc_update_option', $value, $key );

	// Next let's try to update the value.
	$options[ $key ] = $value;
	$did_update      = update_option( 'acc_settings', $options );

	// If it updated, let's update the global variable.
	if ( $did_update ) {
		global $acc_settings;
		$acc_settings[ $key ] = $value;
	}
	return $did_update;
}


/**
 * Remove an option
 *
 * Removes an AutoClose setting value in both the db and the global variable.
 *
 * @since 2.0.0
 *
 * @param  string $key The Key to update.
 * @return boolean   True if updated, false if not.
 */
function acc_delete_option( $key = '' ) {

	// If no key, exit.
	if ( empty( $key ) ) {
		return false;
	}

	// First let's grab the current settings.
	$options = get_option( 'acc_settings' );

	// Next let's try to update the value.
	if ( isset( $options[ $key ] ) ) {
		unset( $options[ $key ] );
	}

	$did_update = update_option( 'acc_settings', $options );

	// If it updated, let's update the global variable.
	if ( $did_update ) {
		global $acc_settings;
		$acc_settings = $options;
	}
	return $did_update;
}


/**
 * Register settings function
 *
 * @since 2.0.0
 *
 * @return void
 */
function acc_register_settings() {

	if ( false === get_option( 'acc_settings' ) ) {
		add_option( 'acc_settings', acc_settings_defaults() );
	}

	foreach ( acc_get_registered_settings() as $section => $settings ) {

		add_settings_section(
			'acc_settings_' . $section, // ID used to identify this section and with which to register options, e.g. acc_settings_general.
			__return_null(), // No title, we will handle this via a separate function.
			'__return_false', // No callback function needed. We'll process this separately.
			'acc_settings_' . $section  // Page on which these options will be added.
		);

		foreach ( $settings as $setting ) {

			$args = wp_parse_args(
				$setting,
				array(
					'section'          => $section,
					'id'               => null,
					'name'             => '',
					'desc'             => '',
					'type'             => null,
					'options'          => '',
					'max'              => null,
					'min'              => null,
					'step'             => null,
					'size'             => null,
					'field_class'      => '',
					'field_attributes' => '',
					'placeholder'      => '',
				)
			);

			add_settings_field(
				'acc_settings[' . $args['id'] . ']', // ID of the settings field. We save it within the acc_settings array.
				$args['name'],     // Label of the setting.
				function_exists( 'acc_' . $args['type'] . '_callback' ) ? 'acc_' . $args['type'] . '_callback' : 'acc_missing_callback', // Function to handle the setting.
				'acc_settings_' . $section,    // Page to display the setting. In our case it is the section as defined above.
				'acc_settings_' . $section,    // Name of the section.
				$args
			);
		}
	}

	// Register the settings into the options table.
	register_setting( 'acc_settings', 'acc_settings', 'acc_settings_sanitize' );
}
add_action( 'admin_init', 'acc_register_settings' );


/**
 * Flattens acc_get_registered_settings() into $setting[id] => $setting[type] format.
 *
 * @since 2.0.0
 *
 * @return array Default settings
 */
function acc_get_registered_settings_types() {

	$options = array();

	// Populate some default values.
	foreach ( acc_get_registered_settings() as $tab => $settings ) {
		foreach ( $settings as $option ) {
			$options[ $option['id'] ] = $option['type'];
		}
	}

	/**
	 * Filters the settings array.
	 *
	 * @since 2.0.0
	 *
	 * @param array $options Default settings.
	 */
	return apply_filters( 'acc_get_settings_types', $options );
}


/**
 * Default settings.
 *
 * @since 2.0.0
 *
 * @return array Default settings
 */
function acc_settings_defaults() {

	$options = array();

	// Populate some default values.
	foreach ( acc_get_registered_settings() as $tab => $settings ) {
		foreach ( $settings as $option ) {
			// When checkbox is set to true, set this to 1 else set to 0.
			if ( 'checkbox' === $option['type'] && ! empty( $option['options'] ) ) {
				$options[ $option['id'] ] = 1;
			} else {
				$options[ $option['id'] ] = 0;
			}
			// If an option is set.
			if ( in_array( $option['type'], array( 'textarea', 'text', 'csv', 'numbercsv', 'posttypes', 'number' ), true ) && isset( $option['options'] ) ) {
				$options[ $option['id'] ] = $option['options'];
			}
			if ( in_array( $option['type'], array( 'multicheck', 'radio', 'select', 'radiodesc', 'thumbsizes' ), true ) && isset( $option['default'] ) ) {
				$options[ $option['id'] ] = $option['default'];
			}
		}
	}

	$upgraded_settings = acc_upgrade_settings();

	if ( false !== $upgraded_settings ) {
		$options = array_merge( $options, $upgraded_settings );
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
 * Get the default option for a specific key
 *
 * @since 2.0.0
 *
 * @param string $key Key of the option to fetch.
 * @return mixed
 */
function acc_get_default_option( $key = '' ) {

	$default_settings = acc_settings_defaults();

	if ( array_key_exists( $key, $default_settings ) ) {
		return $default_settings[ $key ];
	} else {
		return false;
	}

}


/**
 * Reset settings.
 *
 * @since 2.0.0
 *
 * @return void
 */
function acc_settings_reset() {
	delete_option( 'acc_settings' );
}
