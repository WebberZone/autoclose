<?php
/**
 * The main plugin class.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose;

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
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since 3.0.0
	 * @var   Core\Loader
	 */
	protected $loader;

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
		$this->loader   = new Core\Loader();
		$this->settings = new Admin\Settings();
	}

	/**
	 * Set the locale for internationalization.
	 *
	 * @since 3.0.0
	 */
	private function set_locale() {
		$l10n = new Utilities\L10n();
		$this->loader->add_action( 'init', $l10n, 'load_plugin_textdomain' );
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
		$this->loader->add_filter( 'plugin_row_meta', $admin, 'plugin_row_meta', 10, 2 );
		$this->loader->add_filter( 'plugin_action_links_' . plugin_basename( ACC_PLUGIN_FILE ), $admin, 'plugin_actions_links' );

		// Tools page hooks.
		$this->loader->add_action( 'admin_menu', $tools, 'add_tools_page' );
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

		// Register cron hooks.
		$this->loader->add_action( 'acc_cron_hook', $comments, 'process_comments' );
		$this->loader->add_action( 'acc_cron_hook', $revisions, 'process_revisions' );

		// Register revisions hooks.
		$this->loader->add_filter( 'wp_revisions_to_keep', $revisions, 'revisions_to_keep', 999999, 2 );

		// Register ping hooks.
		$this->loader->add_action( 'pre_ping', $block_pings, 'block_pings' );
	}

	/**
	 * Run the loader to execute all the hooks.
	 *
	 * @since 3.0.0
	 */
	public function run() {
		$this->loader->run();
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
