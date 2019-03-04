<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since 2.0.0
 *
 * @package    AutoClose
 * @subpackage Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Creates the admin submenu pages under the Downloads menu and assigns their
 * links to global variables
 *
 * @since 2.0.0
 *
 * @global $acc_settings_page
 * @return void
 */
function acc_add_admin_pages_links() {
	global $acc_settings_page, $acc_settings_tools;

	$acc_settings_page = add_options_page( esc_html__( 'AutoClose', 'autoclose' ), esc_html__( 'AutoClose', 'autoclose' ), 'manage_options', 'acc_options_page', 'acc_options_page' );
	add_action( "load-$acc_settings_page", 'acc_settings_help' ); // Load the settings contextual help.
	add_action( "admin_head-$acc_settings_page", 'acc_adminhead' ); // Load the admin head.

	$acc_settings_tools = add_submenu_page( null, esc_html__( 'AutoClose Tools', 'autoclose' ), esc_html__( 'Tools', 'autoclose' ), 'manage_options', 'acc_tools_page', 'acc_tools_page' );
	add_action( "load-$acc_settings_tools", 'acc_settings_tools_help' );
	add_action( "admin_head-$acc_settings_tools", 'acc_adminhead' );

}
add_action( 'admin_menu', 'acc_add_admin_pages_links' );


/**
 * Function to add CSS and JS to the Admin header.
 *
 * @since 2.0.0
 * @return void
 */
function acc_adminhead() {

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'jquery-ui-tabs' );
	?>
	<script type="text/javascript">
	//<![CDATA[
		// Function to add auto suggest.
		jQuery(document).ready(function($) {

			// Prompt the user when they leave the page without saving the form.
			formmodified=0;

			$('form *').change(function(){
				formmodified=1;
			});

			window.onbeforeunload = confirmExit;

			function confirmExit() {
				if (formmodified == 1) {
					return "<?php esc_html__( 'New information not saved. Do you wish to leave the page?', 'autoclose' ); ?>";
				}
			}

			$( "input[name='submit']" ).click( function() {
				formmodified = 0;
			});

			$( function() {
				$( "#post-body-content" ).tabs({
					create: function( event, ui ) {
						$( ui.tab.find("a") ).addClass( "nav-tab-active" );
					},
					activate: function( event, ui ) {
						$( ui.oldTab.find("a") ).removeClass( "nav-tab-active" );
						$( ui.newTab.find("a") ).addClass( "nav-tab-active" );
					}
				});
			});

		});

	//]]>
	</script>
	<?php
}


/**
 * Add rating links to the admin dashboard
 *
 * @since 2.0.0
 *
 * @param string $footer_text The existing footer text.
 * @return string Updated Footer text
 */
function acc_admin_footer( $footer_text ) {

	global $acc_settings_page;

	if ( get_current_screen()->id === $acc_settings_page ) {

		$text = sprintf(
			/* translators: 1: Plugin website, 2: Plugin reviews link. */
			__( 'Thank you for using <a href="%1$s" target="_blank">AutoClose</a>! Please <a href="%2$s" target="_blank">rate us</a> on <a href="%2$s" target="_blank">WordPress.org</a>', 'autoclose' ),
			'https://ajaydsouza.com/wordpress/plugins/autoclose',
			'https://wordpress.org/support/plugin/autoclose/reviews/#new-post'
		);

		return str_replace( '</span>', '', $footer_text ) . ' | ' . $text . '</span>';

	} else {

		return $footer_text;

	}
}
add_filter( 'admin_footer_text', 'acc_admin_footer' );


/**
 * Adding WordPress plugin action links.
 *
 * @since 2.0.0
 *
 * @param array $links Array of links.
 * @return array
 */
function acc_plugin_actions_links( $links ) {

	return array_merge(
		array(
			'settings' => '<a href="' . admin_url( 'options-general.php?page=acc_options_page' ) . '">' . esc_html__( 'Settings', 'autoclose' ) . '</a>',
		),
		$links
	);

}
add_filter( 'plugin_action_links_' . plugin_basename( ACC_PLUGIN_FILE ), 'acc_plugin_actions_links' );


/**
 * Add meta links on Plugins page.
 *
 * @since 2.0.0
 *
 * @param array  $links Array of Links.
 * @param string $file Current file.
 * @return array
 */
function acc_plugin_actions( $links, $file ) {

	if ( false !== strpos( $file, 'autoclose.php' ) ) {
		$links[] = '<a href="http://wordpress.org/support/plugin/autoclose">' . esc_html__( 'Support', 'autoclose' ) . '</a>';
		$links[] = '<a href="https://ajaydsouza.com/donate/">' . esc_html__( 'Donate', 'autoclose' ) . '</a>';
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'acc_plugin_actions', 10, 2 );

