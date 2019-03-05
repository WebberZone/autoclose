<?php
/**
 * Auto-Close Comments, Pingbacks and Trackbacks
 *
 * Automatically close Comments, Pingbacks and Trackbacks after certain amount of days.
 *
 * @package AutoClose
 * @author  Ajay D'Souza
 * @license GPL-2.0+
 * @link    http://ajaydsouza.com
 * @copyright   2008-2019 Ajay D'Souza
 *
 * @wordpress-plugin
 * Plugin Name: Auto-Close Comments, Pingbacks and Trackbacks
 * Plugin URI:  http://ajaydsouza.com/wordpress/plugins/autoclose/
 * Description: Automatically close Comments, Pingbacks and Trackbacks after certain amount of days.
 * Version:     2.0.0-beta1
 * Author:      Ajay D'Souza
 * Author URI:  http://ajaydsouza.com/
 * Text Domain: autoclose
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/ajaydsouza/autoclose/
 */

// If this file is called directly, then abort execution.
if ( ! defined( 'WPINC' ) ) {
	die( "Aren't you supposed to come here via WP-Admin?" );
}


/**
 * Holds the filesystem directory path (with trailing slash) for AutoClose
 *
 * @since 2.0.0
 *
 * @var string Plugin folder path
 */
if ( ! defined( 'ACC_PLUGIN_DIR' ) ) {
	define( 'ACC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Holds the filesystem directory path (with trailing slash) for AutoClose
 *
 * @since 2.0.0
 *
 * @var string Plugin folder URL
 */
if ( ! defined( 'ACC_PLUGIN_URL' ) ) {
	define( 'ACC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Holds the filesystem directory path (with trailing slash) for AutoClose
 *
 * @since 2.0.0
 *
 * @var string Plugin Root File
 */
if ( ! defined( 'ACC_PLUGIN_FILE' ) ) {
	define( 'ACC_PLUGIN_FILE', __FILE__ );
}

/**
 * Global variable holding the current settings for AutoClose
 *
 * @since 2.0.0
 *
 * @var array
 */
global $acc_settings;
$acc_settings = acc_get_settings();


/**
 * Get Settings.
 *
 * Retrieves all plugin settings
 *
 * @since  2.5.0
 * @return array AutoClose settings
 */
function acc_get_settings() {

	$settings = get_option( 'acc_settings' );

	/**
	 * Settings array
	 *
	 * Retrieves all plugin settings
	 *
	 * @since 2.0.0
	 * @param array $settings Settings array
	 */
	return apply_filters( 'acc_get_settings', $settings );
}


/*
 *---------------------------------------------------------------------------*
 * AutoClose modules
 *---------------------------------------------------------------------------*
 */

require_once ACC_PLUGIN_DIR . 'includes/admin/default-settings.php';
require_once ACC_PLUGIN_DIR . 'includes/admin/register-settings.php';
require_once ACC_PLUGIN_DIR . 'includes/main.php';
require_once ACC_PLUGIN_DIR . 'includes/comments.php';
require_once ACC_PLUGIN_DIR . 'includes/revisions.php';
require_once ACC_PLUGIN_DIR . 'includes/cron.php';
require_once ACC_PLUGIN_DIR . 'includes/l10n.php';
require_once ACC_PLUGIN_DIR . 'includes/helpers.php';


/*
 *---------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *---------------------------------------------------------------------------*
 */

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

	require_once ACC_PLUGIN_DIR . 'includes/admin/admin.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/settings-page.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/save-settings.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/help-tab.php';
	require_once ACC_PLUGIN_DIR . 'includes/admin/tools.php';

}

/*
 *---------------------------------------------------------------------------*
 * Deprecated functions
 *---------------------------------------------------------------------------*
 */

require_once ACC_PLUGIN_DIR . 'includes/deprecated.php';

