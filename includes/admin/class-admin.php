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
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Constructor code.
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
				'donate'     => '<a href="https://ajaydsouza.com/donate/">' . esc_html__( 'Donate', 'autoclose' ) . '</a>',
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
}
