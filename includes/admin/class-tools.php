<?php
/**
 * Tools page functionality.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\Admin;

use WebberZone\AutoClose\Features\Comments;
use WebberZone\AutoClose\Features\Revisions;
use WebberZone\AutoClose\Maintenance\Config_Report;
use WebberZone\AutoClose\Maintenance\Runner;
use WebberZone\AutoClose\Maintenance\Status;
use WebberZone\AutoClose\Options_API;
use WebberZone\AutoClose\Util\Cron;
use WebberZone\AutoClose\Util\Hook_Registry;

if ( ! defined( 'WPINC' ) ) {
	die;
}

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
		$comments      = new Comments();
		$revisions     = new Revisions();
		$preview       = null;
		$config_report = null;

		/* Generate configuration report */
		if ( isset( $_POST['acc_config_report'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$config_report = Config_Report::build();
		}

		/* Repair scheduled maintenance event */
		if ( isset( $_POST['acc_repair_schedule'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$result = ( new Cron() )->repair();

			if ( 'success' === $result['outcome'] ) {
				add_settings_error( 'acc-notices', '', esc_html__( 'The scheduled maintenance event has been repaired.', 'autoclose' ), 'updated' );
			} else {
				add_settings_error( 'acc-notices', '', esc_html( implode( ' ', $result['errors'] ) ), 'error' );
			}
		}

		/* Preview configured maintenance */
		if ( isset( $_POST['acc_preview'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$preview = ( new Runner( $comments, $revisions ) )->preview( 10 );
		}

		/* Preview pingback/trackback deletion */
		if ( isset( $_POST['acc_preview_pingtracks'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$preview = array(
				'mode'    => 'delete-pingtracks',
				'outcome' => 'preview',
				'result'  => $comments->preview_pingbacks( array(), 10 ),
			);
		}

		/* Preview delete-all revisions */
		if ( isset( $_POST['acc_preview_revisions'] ) && check_admin_referer( 'acc-tools-settings' ) ) {
			$preview = array(
				'mode'    => 'delete-revisions',
				'outcome' => 'preview',
				'result'  => $revisions->get_preview( 10, array(), false, 'all' ),
			);
		}

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

			if ( 'skipped' === $outcome ) {
				add_settings_error(
					'acc-notices',
					'',
					$message . '<br />' . esc_html__( 'Nothing to process. Enable a maintenance feature in AutoClose settings.', 'autoclose' ),
					'warning'
				);
			} elseif ( 'success' === $outcome ) {
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

		$preview_mode       = null !== $preview ? (string) ( $preview['mode'] ?? '' ) : '';
		$preview_html       = null !== $preview ? $this->render_preview( $preview ) : '';
		$status_html        = $this->render_status( Status::get() );
		$config_report_html = null !== $config_report ? $this->render_config_report( $config_report ) : '';

		// Include the view file.
		include_once ACC_PLUGIN_DIR . 'includes/admin/views/tools-page.php';
	}

	/**
	 * Render the on-demand configuration report.
	 *
	 * Covers effective settings, ID/term exception counts, explicit closing
	 * date and reopen-window counts, and revision policies. Never includes
	 * post content, comment text, credentials, or individual post IDs.
	 *
	 * @since 3.2.0
	 *
	 * @param array $report Report data from Config_Report::build().
	 * @return string Rendered HTML.
	 */
	public function render_config_report( array $report ): string {
		ob_start();

		echo '<table class="widefat striped" style="max-width:640px;"><tbody>';

		$this->render_status_row( __( 'Generated at (UTC)', 'autoclose' ), (string) ( $report['generated_at'] ?? '' ) );
		$this->render_status_row( __( 'Site', 'autoclose' ), (string) ( $report['site']['url'] ?? '' ) );
		$this->render_status_row( __( 'Multisite', 'autoclose' ), ! empty( $report['site']['multisite'] ) ? __( 'Yes', 'autoclose' ) : __( 'No', 'autoclose' ) );

		$schedule = (array) ( $report['schedule'] ?? array() );
		$this->render_status_row(
			__( 'Scheduled maintenance', 'autoclose' ),
			! empty( $schedule['enabled'] )
				? sprintf(
					/* translators: 1: Recurrence. */
					__( 'Enabled (%s)', 'autoclose' ),
					(string) ( $schedule['recurrence'] ?? '' )
				)
				: __( 'Disabled', 'autoclose' )
		);

		$comments = (array) ( $report['comments'] ?? array() );
		$this->render_status_row(
			__( 'Close comments', 'autoclose' ),
			! empty( $comments['enabled'] )
				? sprintf(
					/* translators: 1: Comma-separated post types, 2: Age in days, 3: Count threshold. */
					__( 'Enabled — %1$s, age %2$d day(s), count threshold %3$d', 'autoclose' ),
					implode( ', ', (array) ( $comments['post_types'] ?? array() ) ),
					(int) ( $comments['age_days'] ?? 0 ),
					(int) ( $comments['count_threshold'] ?? 0 )
				)
				: __( 'Disabled', 'autoclose' )
		);
		$this->render_status_row( __( 'Comments kept open (ID exceptions)', 'autoclose' ), (string) (int) ( $comments['keep_open_post_id_count'] ?? 0 ) );
		$this->render_status_row( __( 'Comments excluded terms', 'autoclose' ), (string) (int) ( $comments['exclude_term_id_count'] ?? 0 ) );
		$this->render_status_row( __( 'Comments age by post type', 'autoclose' ), $this->format_type_ages( (array) ( $comments['age_days_by_type'] ?? array() ) ) );
		$this->render_status_row(
			__( 'Reopen on post update', 'autoclose' ),
			! empty( $comments['reopen_on_update'] ) ? sprintf(
				/* translators: 1: Days. */
				__( 'Enabled, %d day(s)', 'autoclose' ),
				(int) ( $comments['reopen_days'] ?? 0 )
			) : __( 'Disabled', 'autoclose' )
		);

		$pings = (array) ( $report['pings'] ?? array() );
		$this->render_status_row(
			__( 'Close pingbacks/trackbacks', 'autoclose' ),
			! empty( $pings['enabled'] )
				? sprintf(
					/* translators: 1: Comma-separated post types, 2: Age in days. */
					__( 'Enabled — %1$s, age %2$d day(s)', 'autoclose' ),
					implode( ', ', (array) ( $pings['post_types'] ?? array() ) ),
					(int) ( $pings['age_days'] ?? 0 )
				)
				: __( 'Disabled', 'autoclose' )
		);
		$this->render_status_row( __( 'Pings kept open (ID exceptions)', 'autoclose' ), (string) (int) ( $pings['keep_open_post_id_count'] ?? 0 ) );
		$this->render_status_row( __( 'Pings excluded terms', 'autoclose' ), (string) (int) ( $pings['exclude_term_id_count'] ?? 0 ) );
		$this->render_status_row( __( 'Pings age by post type', 'autoclose' ), $this->format_type_ages( (array) ( $pings['age_days_by_type'] ?? array() ) ) );
		$this->render_status_row( __( 'Block self-pings', 'autoclose' ), ! empty( $pings['block_self_pings'] ) ? __( 'Yes', 'autoclose' ) : __( 'No', 'autoclose' ) );

		$revisions = (array) ( $report['revisions'] ?? array() );
		$this->render_status_row(
			__( 'Scheduled revision cleanup', 'autoclose' ),
			! empty( $revisions['delete_all'] )
				? sprintf(
					/* translators: 1: Age in days. */
					__( 'Enabled, age %d day(s)', 'autoclose' ),
					(int) ( $revisions['age_days'] ?? 0 )
				)
				: __( 'Disabled', 'autoclose' )
		);

		$retention_summary = array();
		foreach ( (array) ( $revisions['retention'] ?? array() ) as $post_type => $data ) {
			$retention_summary[] = $post_type . ': ' . (int) ( $data['keep'] ?? 0 );
		}
		$this->render_status_row( __( 'Revisions kept per post type', 'autoclose' ), implode( ', ', $retention_summary ) );

		$this->render_status_row( __( 'Posts with an explicit close date', 'autoclose' ), (string) (int) ( $report['close_dates']['posts_with_explicit_close_dates'] ?? 0 ) );
		$this->render_status_row( __( 'Posts with an active reopen window', 'autoclose' ), (string) (int) ( $report['reopen']['posts_with_active_reopen_window'] ?? 0 ) );

		$notifications = (array) ( $report['notifications'] ?? array() );
		$this->render_status_row( __( 'Cron summary email', 'autoclose' ), ! empty( $notifications['enabled'] ) ? __( 'Enabled', 'autoclose' ) : __( 'Disabled', 'autoclose' ) );

		echo '</tbody></table>';

		echo '<p class="description">' . esc_html__( 'This report contains no post content, comment text, or credentials. It is shown here only and is not saved to a file.', 'autoclose' ) . '</p>';

		return (string) ob_get_clean();
	}

	/**
	 * Format effective age overrides for the configuration report.
	 *
	 * @since 3.2.0
	 *
	 * @param array $ages Post type to age mapping.
	 * @return string Formatted age mapping.
	 */
	private function format_type_ages( array $ages ): string {
		$formatted = array();

		foreach ( $ages as $post_type => $age ) {
			$formatted[] = sprintf(
				/* translators: 1: Post type, 2: Age in days or never. */
				__( '%1$s: %2$s', 'autoclose' ),
				(string) $post_type,
				null === $age ? __( 'never', 'autoclose' ) : sprintf( _n( '%d day', '%d days', (int) $age, 'autoclose' ), (int) $age )
			);
		}

		return empty( $formatted ) ? __( 'None configured', 'autoclose' ) : implode( ', ', $formatted );
	}

	/**
	 * Render the cron health status panel.
	 *
	 * @since 3.2.0
	 *
	 * @param array $status Status report from Status::get().
	 * @return string Rendered HTML.
	 */
	public function render_status( array $status ): string {
		ob_start();

		$schedule = (array) ( $status['schedule'] ?? array() );
		$last_run = (array) ( $status['last_run'] ?? array() );
		$warnings = (array) ( $status['warnings'] ?? array() );
		$health   = (string) ( $status['health'] ?? 'unknown' );

		if ( ! empty( $warnings ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( implode( ' ', $warnings ) ) . '</p></div>';
		} elseif ( 'healthy' === $health ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Scheduling looks healthy.', 'autoclose' ) . '</p></div>';
		}

		echo '<table class="widefat striped" style="max-width:640px;"><tbody>';

		$this->render_status_row( __( 'Scheduled maintenance', 'autoclose' ), ! empty( $schedule['enabled'] ) ? __( 'Enabled', 'autoclose' ) : __( 'Disabled', 'autoclose' ) );
		$this->render_status_row( __( 'Cron event registered', 'autoclose' ), ! empty( $schedule['event_exists'] ) ? __( 'Yes', 'autoclose' ) : __( 'No', 'autoclose' ) );

		if ( ! empty( $schedule['next_run_at'] ) ) {
			$this->render_status_row(
				__( 'Next run', 'autoclose' ),
				sprintf(
					/* translators: 1: Date/time, 2: Timezone. */
					__( '%1$s (%2$s)', 'autoclose' ),
					wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), (int) $schedule['next_run'] ),
					(string) ( $status['site']['timezone'] ?? 'UTC' )
				)
			);
		} else {
			$this->render_status_row( __( 'Next run', 'autoclose' ), __( 'Not scheduled', 'autoclose' ) );
		}

		$this->render_status_row( __( 'Recurrence', 'autoclose' ), (string) ( $schedule['recurrence'] ?? '' ) );
		$this->render_status_row( __( 'WP-Cron disabled (DISABLE_WP_CRON)', 'autoclose' ), ! empty( $schedule['wp_cron_disabled'] ) ? __( 'Yes', 'autoclose' ) : __( 'No', 'autoclose' ) );

		$this->render_status_row(
			__( 'Last attempt', 'autoclose' ),
			! empty( $last_run['attempt'] ) ? wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), (int) $last_run['attempt'] ) : __( 'Never', 'autoclose' )
		);
		$this->render_status_row(
			__( 'Last completed', 'autoclose' ),
			! empty( $last_run['completed'] ) ? wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), (int) $last_run['completed'] ) : __( 'Never', 'autoclose' )
		);
		$this->render_status_row( __( 'Last outcome', 'autoclose' ), null !== ( $last_run['outcome'] ?? null ) ? (string) $last_run['outcome'] : __( 'None yet', 'autoclose' ) );

		$counts = (array) ( $last_run['counts'] ?? array() );
		if ( ! empty( $counts ) ) {
			$summary = array();
			foreach ( $counts as $key => $value ) {
				if ( is_bool( $value ) ) {
					continue;
				}
				$summary[] = $key . ': ' . number_format_i18n( (int) $value );
			}
			$this->render_status_row( __( 'Last run counts', 'autoclose' ), implode( ', ', $summary ) );
		}

		if ( ! empty( $last_run['errors'] ) ) {
			$this->render_status_row( __( 'Last run errors', 'autoclose' ), implode( ' ', (array) $last_run['errors'] ) );
		}

		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	/**
	 * Render one status table row.
	 *
	 * @since 3.2.0
	 *
	 * @param string $label Row label.
	 * @param string $value Row value.
	 */
	private function render_status_row( string $label, string $value ): void {
		echo '<tr><th scope="row" style="width:220px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
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
	 * Render the read-only preview panel.
	 *
	 * The preview reflects the same eligibility queries execution uses, but it is
	 * a snapshot: matching content or settings can change before Run is clicked,
	 * and this panel never writes content, settings, run history, or emails.
	 *
	 * @since 3.2.0
	 *
	 * @param array $preview Preview data from Runner::preview() or a single-operation preview.
	 * @return string Rendered HTML.
	 */
	public function render_preview( array $preview ): string {
		ob_start();

		$mode = $preview['mode'] ?? 'run';

		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Preview only. Nothing has been changed. Matching content can change before you click Run — recheck eligibility happens at execution time.', 'autoclose' ) . '</p></div>';

		if ( 'delete-pingtracks' === $mode || 'delete-revisions' === $mode ) {
			$this->render_single_operation_preview( (array) ( $preview['result'] ?? array() ) );
			return (string) ob_get_clean();
		}

		$components = (array) ( $preview['components'] ?? array() );
		$comments   = (array) ( $components['comments']['operations'] ?? array() );
		$revisions  = (array) ( $components['revisions'] ?? array() );

		foreach ( $comments as $key => $operation ) {
			$this->render_single_operation_preview( (array) $operation, $this->get_operation_label( (string) $key ) );
		}

		if ( ! empty( $revisions ) ) {
			$this->render_single_operation_preview( $revisions, __( 'Revisions (scheduled cleanup)', 'autoclose' ) );
		}

		return (string) ob_get_clean();
	}

	/**
	 * Human-readable label for a comments preview operation key.
	 *
	 * @since 3.2.0
	 *
	 * @param string $key Operation key.
	 * @return string Label.
	 */
	private function get_operation_label( string $key ): string {
		$labels = array(
			'comments_close' => __( 'Close comments', 'autoclose' ),
			'pings_close'    => __( 'Close pingbacks/trackbacks', 'autoclose' ),
			'comments_open'  => __( 'Reopen comments (kept-open list)', 'autoclose' ),
			'pings_open'     => __( 'Reopen pingbacks/trackbacks (kept-open list)', 'autoclose' ),
		);

		return $labels[ $key ] ?? $key;
	}

	/**
	 * Render one operation's preview: counts, scope, and a sample of affected posts.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $operation Operation preview data.
	 * @param string $label     Optional heading.
	 */
	private function render_single_operation_preview( array $operation, string $label = '' ): void {
		if ( 'skipped' === ( $operation['status'] ?? '' ) || false === ( $operation['enabled'] ?? true ) ) {
			return;
		}

		$affected = (int) ( $operation['affected'] ?? 0 );

		echo '<div class="acc-preview-operation" style="margin-bottom:16px;">';

		if ( '' !== $label ) {
			echo '<h4 style="margin-bottom:4px;">' . esc_html( $label ) . '</h4>';
		}

		echo '<p>' . sprintf(
			/* translators: 1: Number of matching posts. */
			esc_html__( 'Matching posts: %s', 'autoclose' ),
			'<strong>' . esc_html( number_format_i18n( $affected ) ) . '</strong>'
		) . '</p>';

		$scope = array();

		if ( isset( $operation['post_types'] ) && ! empty( $operation['post_types'] ) ) {
			$scope[] = sprintf(
				/* translators: 1: Comma-separated post types. */
				esc_html__( 'Post types: %s', 'autoclose' ),
				esc_html( implode( ', ', (array) $operation['post_types'] ) )
			);
		}

		if ( array_key_exists( 'age', $operation ) && 'prune' === ( $operation['mode'] ?? 'prune' ) ) {
			$scope[] = sprintf(
				/* translators: 1: Age in days. */
				esc_html__( 'Age cutoff: %s day(s)', 'autoclose' ),
				esc_html( (string) (int) $operation['age'] )
			);
		} elseif ( ! empty( $operation['type_ages'] ) ) {
			$per_type = array();
			foreach ( (array) $operation['type_ages'] as $post_type => $age ) {
				$per_type[] = $post_type . ': ' . ( null === $age ? __( 'never', 'autoclose' ) : sprintf( _n( '%d day', '%d days', (int) $age, 'autoclose' ), (int) $age ) );
			}
			$scope[] = esc_html__( 'Age cutoff by post type: ', 'autoclose' ) . esc_html( implode( ', ', $per_type ) );
		} elseif ( isset( $operation['cutoff_gmt'] ) && $operation['cutoff_gmt'] ) {
			$scope[] = sprintf(
				/* translators: 1: Date. */
				esc_html__( 'Age cutoff: %s', 'autoclose' ),
				esc_html( (string) $operation['cutoff_gmt'] )
			);
		}

		if ( ! empty( $operation['count_threshold'] ) ) {
			$scope[] = sprintf(
				/* translators: 1: Approved comment count. */
				esc_html__( 'Or approved comments ≥ %d', 'autoclose' ),
				(int) $operation['count_threshold']
			);
		}

		if ( ! empty( $scope ) ) {
			echo '<p class="description">' . wp_kses_post( implode( ' &middot; ', $scope ) ) . '</p>';
		}

		$sample = (array) ( $operation['sample'] ?? array() );

		if ( ! empty( $sample ) ) {
			echo '<ul style="list-style:disc; margin-left:20px;">';
			foreach ( array_slice( $sample, 0, 10 ) as $row ) {
				$row_id    = (int) ( $row['ID'] ?? $row['comment_ID'] ?? 0 );
				$row_title = (string) ( $row['post_title'] ?? '' );
				echo '<li>' . esc_html( '#' . $row_id . ( '' !== $row_title ? ' — ' . $row_title : '' ) ) . '</li>';
			}
			echo '</ul>';

			if ( $affected > count( $sample ) ) {
				echo '<p class="description">' . esc_html(
					sprintf(
						/* translators: 1: Number shown, 2: Total matching. */
						__( 'Showing %1$s of %2$s matching posts.', 'autoclose' ),
						number_format_i18n( count( $sample ) ),
						number_format_i18n( $affected )
					)
				) . '</p>';
			}
		}

		if ( ! empty( $operation['errors'] ) ) {
			echo '<p class="description" style="color:#a00;">' . esc_html( implode( ' ', (array) $operation['errors'] ) ) . '</p>';
		}

		echo '</div>';
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
