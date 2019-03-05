<?php
/**
 * Tools page in the admin area.
 *
 * @since 2.0.0
 *
 * @package AutoClose
 * @subpackage Admin/Tools
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Render the Admin -> Tools page.
 *
 * @since 2.0.0
 *
 * @return void
 */
function acc_tools_page() {

	$acc_settings = acc_get_settings();

	/* Close all */
	if ( ( isset( $_POST['close_all'] ) ) && ( check_admin_referer( 'acc-tools-settings' ) ) ) {
		// Execute the main function.
		acc_main();

		$date_time_format = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );
		$current_time     = current_time( 'timestamp', 0 );

		$message = '';

		if ( $acc_settings['close_comment'] ) {
			/* translators: 1: Date. */
			$message .= sprintf(
				esc_html__( 'Comments closed up to %1$s', 'autoclose' ),
				date( $date_time_format, ( $current_time - $acc_settings['comment_age'] * DAY_IN_SECONDS ) )
			);
			$message .= '<br />';
		}

		if ( $acc_settings['close_pbtb'] ) {
			/* translators: 1: Date. */
			$message .= sprintf(
				esc_html__( 'Pingbacks/Trackbacks closed up to %1$s', 'autoclose' ),
				date( $date_time_format, ( $current_time - $acc_settings['pbtb_age'] * DAY_IN_SECONDS ) )
			);
			$message .= '<br />';
		}

		if ( $acc_settings['delete_revisions'] ) {
			$message .= esc_html__( 'Post revisions deleted', 'autoclose' );
			$message .= '<br />';
		}

		if ( ! empty( $message ) ) {
			add_settings_error( 'acc-notices', '', $message, 'updated' );
		} else {
			add_settings_error( 'acc-notices', '', esc_html__( 'Nothing to process. Visit the Settings page to select what to close/delete.', 'autoclose' ) . '<br />', 'error' );
		}
	}

	/* Delete old settings */
	if ( ( isset( $_POST['acc_delete_old_settings'] ) ) && ( check_admin_referer( 'acc-tools-settings' ) ) ) {
		$old_settings = get_option( 'ald_acc_settings' );

		if ( empty( $old_settings ) ) {
			add_settings_error( 'acc-notices', '', esc_html__( 'Old settings key does not exist', 'autoclose' ), 'error' );
		} else {
			delete_option( 'ald_acc_settings' );
			add_settings_error( 'acc-notices', '', esc_html__( 'Old settings key has been deleted', 'autoclose' ), 'updated' );
		}
	}

	/* Open comments */
	if ( ( isset( $_POST['acc_opencomments'] ) ) && ( check_admin_referer( 'acc-tools-settings' ) ) ) {
		acc_open_comments();

		add_settings_error( 'acc-notices', '', esc_html__( 'Comments opened on all post types', 'autoclose' ), 'updated' );
	}

	/* Open pingbacks/trackbacks */
	if ( ( isset( $_POST['acc_openpings'] ) ) && ( check_admin_referer( 'acc-tools-settings' ) ) ) {
		acc_open_pingtracks();

		add_settings_error( 'acc-notices', '', esc_html__( 'Pingbacks/Trackbacks opened on all post types', 'autoclose' ), 'updated' );
	}

	/* Close comments */
	if ( ( isset( $_POST['acc_closecomments'] ) ) && ( check_admin_referer( 'acc-tools-settings' ) ) ) {
		acc_close_comments();

		add_settings_error( 'acc-notices', '', esc_html__( 'Comments closed on all post types', 'autoclose' ), 'updated' );
	}

	/* Close pingbacks/trackbacks */
	if ( ( isset( $_POST['acc_closepings'] ) ) && ( check_admin_referer( 'acc-tools-settings' ) ) ) {
		acc_close_pingtracks();

		add_settings_error( 'acc-notices', '', esc_html__( 'Pingbacks/Trackbacks closed on all post types', 'autoclose' ), 'updated' );
	}

	ob_start();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Automatically Close Comments, Pingbacks and Trackbacks Tools', 'autoclose' ); ?></h1>

		<p>
			<a href="<?php echo admin_url( 'options-general.php?page=acc_options_page' ); ?>">
				<?php esc_html_e( 'Visit the Settings page', 'autoclose' ); ?>
			</a>
		<p>

		<?php settings_errors(); ?>

		<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
		<div id="post-body-content">

			<form method="post">

				<h2 style="padding-left:0px"><?php esc_html_e( 'Close Comments, Pingbacks and Trackbacks', 'autoclose' ); ?></h2>
				<p>
					<input type="submit" name="close_all" id="close_all"  value="<?php esc_attr_e( 'Run closing algorithm', 'autoclose' ); ?>" class="button button-primary" />
				</p>
				<p class="description">
					<?php esc_html_e( 'Clicking this button will execute the closing algorithm respecting the various options in the Settings page.', 'autoclose' ); ?>
				</p>

				<h2 style="padding-left:0px"><?php esc_html_e( 'Open or close Comments, Pingbacks and Trackbacks', 'autoclose' ); ?></h2>
				<p>
					<input name="acc_opencomments" type="submit" id="acc_opencomments" value="<?php _e( 'Open Comments', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open comments on all posts and post types?')) return false;" />

					<input name="acc_openpings" type="submit" id="acc_openpings" value="<?php _e( 'Open Pings', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open pings on all posts and post types?')) return false;" />
				</p>

				<p>
					<input name="acc_closecomments" type="submit" id="acc_closecomments" value="<?php _e( 'Close Comments', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to close comments on all posts and post types?')) return false;" />

					<input name="acc_closepings" type="submit" id="acc_closepings" value="<?php _e( 'Close Pings', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to close pings on all posts and post types?')) return false;" />
				</p>

				<h2 style="padding-left:0px"><?php esc_html_e( 'Other tools', 'autoclose' ); ?></h2>
				<p>
					<input name="acc_delete_old_settings" type="submit" id="acc_delete_old_settings" value="<?php esc_attr_e( 'Delete old settings', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('<?php esc_attr_e( 'This will delete the settings before v2.0.x. Proceed?', 'autoclose' ); ?>')) return false;" />
				</p>
				<p class="description">
					<?php esc_html_e( 'From v2.0.x, AutoClose stores the settings in a new key in the database. This will delete the old settings for the current site. It is recommended that you do this at the earliest after upgrade once you are comfortable with the new settings.', 'autoclose' ); ?>
				</p>

				<?php wp_nonce_field( 'acc-tools-settings' ); ?>
			</form>

		</div><!-- /#post-body-content -->

		<div id="postbox-container-1" class="postbox-container">

			<div id="side-sortables" class="meta-box-sortables ui-sortable">
				<?php include_once 'sidebar.php'; ?>
			</div><!-- /#side-sortables -->

		</div><!-- /#postbox-container-1 -->
		</div><!-- /#post-body -->
		<br class="clear" />
		</div><!-- /#poststuff -->

	</div><!-- /.wrap -->

	<?php
	echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

