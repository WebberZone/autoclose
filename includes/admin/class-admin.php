<?php
/**
 * Admin functionality.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Admin;

/**
 * Admin class.
 *
 * @since 3.0.0
 */
class Admin {

	/**
	 * Instance of Metabox.
	 *
	 * @var Metabox
	 */
	public $metabox;

	/**
	 * Admin banner helper instance.
	 *
	 * @since 3.0.0
	 *
	 * @var Admin_Banner
	 */
	public $admin_banner;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->metabox      = new Metabox();
		$this->admin_banner = new Admin_Banner( $this->get_admin_banner_config() );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 3.0.0
	 */
	public function admin_menu() {
		// Admin menu is handled by the Settings class.
	}

	/**
	 * Add meta links on Plugins page.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $links Array of Links.
	 * @param string $file  Current file.
	 * @return array Modified links
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( false !== strpos( $file, 'autoclose.php' ) ) {
			$new_links = array(
				'support'    => '<a href="https://wordpress.org/support/plugin/autoclose">' . esc_html__( 'Support', 'autoclose' ) . '</a>',
				'donate'     => '<a href="https://wzn.io/donate-wz">' . esc_html__( 'Donate', 'autoclose' ) . '</a>',
				'contribute' => '<a href="https://github.com/WebberZone/autoclose">' . esc_html__( 'Contribute', 'autoclose' ) . '</a>',
			);

			$links = array_merge( $links, $new_links );
		}
		return $links;
	}

	/**
	 * Add plugin actions links.
	 *
	 * @since 3.0.0
	 *
	 * @param array $links Array of links.
	 * @return array Modified array of links.
	 */
	public function plugin_actions_links( $links ) {
		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=acc_options_page' ) . '">' . esc_html__( 'Settings', 'autoclose' ) . '</a>',
			),
			$links
		);
	}

	/**
	 * Retrieve the configuration array for the admin banner.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_admin_banner_config(): array {
		return array(
			'capability' => 'manage_options',
			'prefix'     => 'acc',
			'style'      => array(
				'version' => ACC_PLUGIN_VERSION,
			),
			'screen_ids' => array(
				'settings_page_acc_options_page',
			),
			'page_slugs' => array(
				'acc_options_page',
			),
			'strings'    => array(
				'region_label' => esc_html__( 'AutoClose quick links', 'autoclose' ),
				'nav_label'    => esc_html__( 'AutoClose admin shortcuts', 'autoclose' ),
				'eyebrow'      => esc_html__( 'WebberZone AutoClose', 'autoclose' ),
				'title'        => esc_html__( 'Automatically close comments, pingbacks and trackbacks.', 'autoclose' ),
				'text'         => esc_html__( 'Manage your AutoClose settings and explore more WebberZone plugins.', 'autoclose' ),
			),
			'sections'   => array(
				'settings' => array(
					'label'      => esc_html__( 'Settings', 'autoclose' ),
					'url'        => admin_url( 'options-general.php?page=acc_options_page' ),
					'screen_ids' => array( 'settings_page_acc_options_page' ),
					'page_slugs' => array( 'acc_options_page' ),
				),
				'plugins'  => array(
					'label'  => esc_html__( 'WebberZone Plugins', 'autoclose' ),
					'url'    => 'https://webberzone.com/plugins/',
					'type'   => 'secondary',
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				),
			),
		);
	}
}
