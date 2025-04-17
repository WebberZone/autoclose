<?php
/**
 * Tools page functionality.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Admin;

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;
use WebberZone\AutoClose\Utilities\Options;

/**
 * Tools class.
 *
 * @since 3.0.0
 */
class Tools {

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Constructor code.
	}

	/**
	 * Add tools page.
	 *
	 * @since 3.0.0
	 */
	public function add_tools_page() {
		$page = add_management_page(
			esc_html__( 'AutoClose Tools', 'autoclose' ),
			esc_html__( 'AutoClose Tools', 'autoclose' ),
			'manage_options',
			'acc_tools_page',
			array( $this, 'render_tools_page' )
		);

		add_action( 'load-' . $page, array( $this, 'tools_help' ) );
	}

	/**
	 * Function to add the contextual help in the settings page.
	 *
	 * @since 3.0.0
	 */
	public function tools_help() {
		$screen = get_current_screen();

		$screen->set_help_sidebar(
			/* translators: 1: Plugin support site link. */
			'<p>' . sprintf( __( 'For more information or how to get support visit the <a href="%s">support site</a>.', 'autoclose' ), esc_url( 'https://webberzone.com/support/' ) ) . '</p>' .
			/* translators: 1: WordPress.org support forums link. */
			'<p>' . sprintf( __( 'Support queries should be posted in the <a href="%s">WordPress.org support forums</a>.', 'autoclose' ), esc_url( 'https://wordpress.org/support/plugin/autoclose' ) ) . '</p>' .
			'<p>' . sprintf(
				/* translators: 1: Github issues link, 2: Github plugin page link. */
				__( '<a href="%1$s">Post an issue</a> on <a href="%2$s">GitHub</a> (bug reports only).', 'autoclose' ),
				esc_url( 'https://github.com/ajaydsouza/autoclose/issues' ),
				esc_url( 'https://github.com/ajaydsouza/autoclose' )
			) . '</p>'
		);

		$screen->add_help_tab(
			array(
				'id'      => 'acc-tools-general',
				'title'   => __( 'Tools', 'autoclose' ),
				'content' =>
				'<p>' . __( 'This screen gives you a few tools namely one click buttons to run the closing algorithm or open comments, pingbacks/trackbacks.', 'autoclose' ) . '</p>' .
					'<p>' . __( 'You can also delete the old settings from prior to v2.0.0', 'autoclose' ) . '</p>',
			)
		);
	}

	/**
	 * Render the tools page.
	 *
	 * @since 3.0.0
	 */
	public function render_tools_page() {
		$comments  = new Comments();
		$revisions = new Revisions();

		/* Close all */
		if ( isset( $_POST['close_all'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			// Execute the main function.
			$this->process_all();

			$date_time_format = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );
			$current_time     = strtotime( current_time( 'mysql' ) );

			$message = '';

			if ( Options::get_option( 'close_comment' ) ) {
				/* translators: 1: Date. */
				$message .= sprintf(
					/* translators: 1. Date */
					esc_html__( 'Comments closed up to %1$s', 'autoclose' ),
					gmdate( $date_time_format, $current_time - Options::get_option( 'comment_age' ) * DAY_IN_SECONDS )
				);
				$message .= '<br />';
			}

			if ( Options::get_option( 'close_pbtb' ) ) {
				/* translators: 1: Date. */
				$message .= sprintf(
					/* translators: 1. Date */
					esc_html__( 'Pingbacks/Trackbacks closed up to %1$s', 'autoclose' ),
					gmdate( $date_time_format, $current_time - Options::get_option( 'pbtb_age' ) * DAY_IN_SECONDS )
				);
				$message .= '<br />';
			}

			if ( Options::get_option( 'delete_revisions' ) ) {
				$message .= esc_html__( 'Post revisions deleted', 'autoclose' );
				$message .= '<br />';
			}

			if ( ! empty( $message ) ) {
				add_settings_error( 'acc-notices', '', $message, 'updated' );
			} else {
				add_settings_error( 'acc-notices', '', esc_html__( 'Nothing to process. Visit the Settings page to select what to close/delete.', 'autoclose' ) . '<br />', 'error' );
			}
		}

		/* Open comments */
		if ( isset( $_POST['acc_opencomments'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$comments->open_comments();
			add_settings_error( 'acc-notices', '', esc_html__( 'Comments opened on all post types', 'autoclose' ), 'updated' );
		}

		/* Open pingbacks/trackbacks */
		if ( isset( $_POST['acc_openpings'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$comments->open_pingbacks();
			add_settings_error( 'acc-notices', '', esc_html__( 'Pingbacks/Trackbacks opened on all post types', 'autoclose' ), 'updated' );
		}

		/* Close comments */
		if ( isset( $_POST['acc_closecomments'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$comments->close_comments();
			add_settings_error( 'acc-notices', '', esc_html__( 'Comments closed on all post types', 'autoclose' ), 'updated' );
		}

		/* Close pingbacks/trackbacks */
		if ( isset( $_POST['acc_closepings'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$comments->close_pingbacks();
			add_settings_error( 'acc-notices', '', esc_html__( 'Pingbacks/Trackbacks closed on all post types', 'autoclose' ), 'updated' );
		}

		/* Delete pingbacks/trackbacks */
		if ( isset( $_POST['acc_delete_pingtracks'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$comments->delete_pingbacks();
			add_settings_error( 'acc-notices', '', esc_html__( 'Pingbacks/Trackbacks deleted on all post types', 'autoclose' ), 'updated' );
		}

		/* Delete revisions */
		if ( isset( $_POST['acc_delete_revisions'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$revisions->delete_revisions();
			add_settings_error( 'acc-notices', '', esc_html__( 'Revisions deleted on all post types', 'autoclose' ), 'updated' );
		}

		// Include the view file.
		include_once ACC_PLUGIN_DIR . 'includes/admin/views/tools-page.php';
	}

	/**
	 * Process all actions based on settings.
	 *
	 * @since 3.0.0
	 */
	public function process_all() {
		$comments = new Comments();
		$comments->process_comments();

		$revisions = new Revisions();
		$revisions->process_revisions();
	}
}
