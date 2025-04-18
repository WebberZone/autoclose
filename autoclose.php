<?php
/**
 * Auto-Close Comments, Pingbacks and Trackbacks
 *
 * Automatically close Comments, Pingbacks and Trackbacks. Manage and delete revisions.
 *
 * @package    AutoClose
 * @author     Ajay D'Souza
 * @license    GPL-2.0+
 * @link       https://webberzone.com
 * @copyright  2008-2025 Ajay D'Souza
 *
 * @wordpress-plugin
 * Plugin Name: Auto-Close Comments, Pingbacks and Trackbacks
 * Plugin URI:  https://webberzone.com/plugins/autoclose/
 * Description: Automatically close Comments, Pingbacks and Trackbacks. Manage and delete revisions.
 * Version:     3.0.0
 * Author:      Ajay D'Souza
 * Author URI:  https://webberzone.com
 * Text Domain: autoclose
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/WebberZone/autoclose/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( "Aren't you supposed to come here via WP-Admin?" );
}

/**
 * Holds the AutoClose plugin version
 *
 * @since 2.2.0
 *
 * @var string Plugin version
 */
if ( ! defined( 'ACC_PLUGIN_VERSION' ) ) {
	define( 'ACC_PLUGIN_VERSION', '3.0.0' );
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

// Include the autoloader.
require_once ACC_PLUGIN_DIR . 'includes/class-autoloader.php';

// Backward compatibility - Include functions for backward compatibility.
require_once ACC_PLUGIN_DIR . 'includes/backward-compatibility.php';

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'WebberZone\\AutoClose\\AutoClose', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WebberZone\\AutoClose\\AutoClose', 'deactivate' ) );

/**
 * Initialize the plugin.
 *
 * @since 3.0.0
 */
function acc_init() {
	$autoclose = WebberZone\AutoClose\AutoClose::get_instance();
	$autoclose->run();
}
add_action( 'plugins_loaded', 'acc_init' );
