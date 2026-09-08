<?php
/**
 * Tools page functionality.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\Admin;

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;
use WebberZone\AutoClose\Maintenance\Runner;
use WebberZone\AutoClose\Options_API;
use WebberZone\AutoClose\Util\Hook_Registry;

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

		Hook_Registry::add_action( 'load-' . $page, array( $this, 'tools_help' ) );
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
			$summary = $this->process_all();
			$outcome = $summary['outcome'] ?? 'failed';
			$message = sprintf(
				/* translators: 1: Posts with comments affected, 2: Posts with pings affected, 3: Revisions deleted. */
				esc_html__( 'AutoClose processed %1$s posts with comments, %2$s posts with pingbacks/trackbacks, and %3$s revisions.', 'autoclose' ),
				number_format_i18n( (int) ( $summary['comments_closed'] ?? 0 ) ),
				number_format_i18n( (int) ( $summary['pings_closed'] ?? 0 ) ),
				number_format_i18n( (int) ( $summary['revisions_deleted'] ?? 0 ) )
			);
			$cutoffs = array();
			$format  = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );

			if ( Options_API::get_option( 'close_comment' ) ) {
				$cutoffs[] = sprintf(
					/* translators: 1: Date. */
					esc_html__( 'Comments cutoff: %1$s.', 'autoclose' ),
					wp_date( $format, time() - max( 0, (int) Options_API::get_option( 'comment_age' ) ) * DAY_IN_SECONDS )
				);
			}

			if ( Options_API::get_option( 'close_pbtb' ) ) {
				$cutoffs[] = sprintf(
					/* translators: 1: Date. */
					esc_html__( 'Pingbacks/Trackbacks cutoff: %1$s.', 'autoclose' ),
					wp_date( $format, time() - max( 0, (int) Options_API::get_option( 'pbtb_age' ) ) * DAY_IN_SECONDS )
				);
			}

			if ( Options_API::get_option( 'delete_revisions' ) && (int) Options_API::get_option( 'revision_age' ) > 0 ) {
				$cutoffs[] = sprintf(
					/* translators: 1: Date. */
					esc_html__( 'Revision cutoff: %1$s.', 'autoclose' ),
					wp_date( $format, time() - (int) Options_API::get_option( 'revision_age' ) * DAY_IN_SECONDS )
				);
			}

			if ( ! empty( $cutoffs ) ) {
				$message .= '<br />' . implode( ' ', $cutoffs );
			}

			if ( in_array( $outcome, array( 'success', 'skipped' ), true ) ) {
				add_settings_error( 'acc-notices', '', $message, 'updated' );
			} else {
				$errors = implode( ' ', array_values( (array) ( $summary['errors'] ?? array() ) ) );
				add_settings_error( 'acc-notices', '', $message . '<br />' . esc_html( $errors ), 'error' );
			}
		}

		/* Open comments */
		if ( isset( $_POST['acc_opencomments'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$result = $comments->edit_discussions_result( 'comment', 'open' );
			$this->add_discussion_notice( $result, __( 'Comments opened', 'autoclose' ) );
		}

		/* Open pingbacks/trackbacks */
		if ( isset( $_POST['acc_openpings'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$result = $comments->edit_discussions_result( 'ping', 'open' );
			$this->add_discussion_notice( $result, __( 'Pingbacks/Trackbacks opened', 'autoclose' ) );
		}

		/* Close comments */
		if ( isset( $_POST['acc_closecomments'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$result = $comments->edit_discussions_result( 'comment', 'close' );
			$this->add_discussion_notice( $result, __( 'Comments closed', 'autoclose' ) );
		}

		/* Close pingbacks/trackbacks */
		if ( isset( $_POST['acc_closepings'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$result = $comments->edit_discussions_result( 'ping', 'close' );
			$this->add_discussion_notice( $result, __( 'Pingbacks/Trackbacks closed', 'autoclose' ) );
		}

		/* Delete pingbacks/trackbacks */
		if ( isset( $_POST['acc_delete_pingtracks'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$result             = $comments->delete_pingbacks_result();
			$result['affected'] = (int) ( $result['deleted'] ?? 0 );
			$this->add_discussion_notice( $result, __( 'Pingbacks/Trackbacks deleted', 'autoclose' ) );
		}

		/* Delete revisions */
		if ( isset( $_POST['acc_delete_revisions'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$result  = $revisions->delete_all_revisions();
			$deleted = (int) $result['deleted'];
			$message = sprintf(
			/* translators: 1: Number of revisions. */
				esc_html( _n( '%s revision deleted on all post types, ignoring retention limits and age', '%s revisions deleted on all post types, ignoring retention limits and age', $deleted, 'autoclose' ) ),
				number_format_i18n( $deleted )
			);

			if ( 'success' === $result['status'] ) {
				add_settings_error( 'acc-notices', '', $message, 'updated' );
			} else {
				add_settings_error(
					'acc-notices',
					'',
					$message . '<br />' . esc_html( implode( ' ', $result['errors'] ) ),
					'error'
				);
			}
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
		$runner = new Runner();

		return $runner->run();
	}

	/**
	 * Display a truthful result for a discussion operation.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $result  Operation result.
	 * @param string $label   Success label.
	 */
	private function add_discussion_notice( array $result, string $label ): void {
		$affected = number_format_i18n( (int) ( $result['affected'] ?? 0 ) );
		$message  = sprintf( '%s: %s.', $label, $affected );
		$status   = $result['status'] ?? 'failed';

		if ( 'success' === $status ) {
			add_settings_error( 'acc-notices', '', esc_html( $message ), 'updated' );
			return;
		}

		$errors = implode( ' ', array_values( (array) ( $result['errors'] ?? array() ) ) );
		add_settings_error( 'acc-notices', '', esc_html( $message . ' ' . $errors ), 'error' );
	}
}
