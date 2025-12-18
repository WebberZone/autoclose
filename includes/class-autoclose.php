<?php
/**
 * The main plugin class.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose;

use WebberZone\AutoClose\Util\Hook_Registry;

/**
 * The main plugin class.
 *
 * @since 3.0.0
 */
class AutoClose {

	/**
	 * Instance of this class.
	 *
	 * @since 3.0.0
	 * @var   AutoClose
	 */
	private static $instance;


	/**
	 * The settings instance.
	 *
	 * @since 3.0.0
	 * @var   Admin\Settings
	 */
	public $settings;

	/**
	 * Plugin options.
	 *
	 * @since 3.0.0
	 * @var   array
	 */
	public $options;

	/**
	 * Get a singleton instance of this class.
	 *
	 * @since 3.0.0
	 * @return AutoClose
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 3.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_feature_hooks();
	}

	/**
	 * Load the required dependencies.
	 *
	 * @since 3.0.0
	 */
	private function load_dependencies() {
		$this->settings = new Admin\Settings();
	}

	/**
	 * Set the locale for internationalization.
	 *
	 * @since 3.0.0
	 */
	private function set_locale() {
		$l10n = new Util\L10n();
		Hook_Registry::add_action( 'init', array( $l10n, 'load_plugin_textdomain' ) );
	}

	/**
	 * Register all of the hooks related to the admin area.
	 *
	 * @since 3.0.0
	 */
	private function define_admin_hooks() {
		$admin = new Admin\Admin();
		$tools = new Admin\Tools();

		// Plugin links.
		Hook_Registry::add_filter( 'plugin_row_meta', array( $admin, 'plugin_row_meta' ), 10, 2 );
		Hook_Registry::add_filter( 'plugin_action_links_' . plugin_basename( ACC_PLUGIN_FILE ), array( $admin, 'plugin_actions_links' ) );

		// Tools page hooks.
		Hook_Registry::add_action( 'admin_menu', array( $tools, 'add_tools_page' ) );
	}

	/**
	 * Register all of the hooks related to the features.
	 *
	 * @since 3.0.0
	 */
	private function define_feature_hooks() {
		$comments    = new Features\Comments();
		$revisions   = new Features\Revisions();
		$block_pings = new Features\Block_Pings();
		$close_date  = new Features\Close_Date();

		// Register cron hooks.
		Hook_Registry::add_action( 'acc_cron_hook', array( $comments, 'process_comments' ) );
		Hook_Registry::add_action( 'acc_cron_hook', array( $revisions, 'process_revisions' ) );

		// Register revisions hooks.
		Hook_Registry::add_filter( 'wp_revisions_to_keep', array( $revisions, 'revisions_to_keep' ), 999999, 2 );

		// Register ping hooks.
		Hook_Registry::add_action( 'pre_ping', array( $block_pings, 'block_pings' ) );
	}

	/**
	 * Run the loader to execute all the hooks.
	 *
	 * @since 3.0.0
	 */
	public function run() {
		// Hook_Registry registers hooks immediately, so nothing to do here.
	}

	/**
	 * Activate the plugin.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $network_wide Whether to activate network-wide.
	 */
	public static function activate( $network_wide ) {
		Core\Activator::activate( $network_wide );
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @since 3.0.0
	 */
	public static function deactivate() {
		Core\Deactivator::deactivate();
	}
}
