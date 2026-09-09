<?php
/**
 * Tools page view.
 *
 * @package    AutoClose
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Automatically Close Comments, Pingbacks and Trackbacks Tools', 'autoclose' ); ?></h1>

	<p>
		<a class="button button-primary" style="color: #0A0A0A; background: #FFBD59; border: 1px solid #FFA500;" href="<?php echo esc_url( admin_url( 'options-general.php?page=acc_options_page' ) ); ?>">
			<?php esc_html_e( 'Visit the Settings page', 'autoclose' ); ?>
		</a>
	</p>

	<?php settings_errors(); ?>

	<div id="poststuff">
	<div id="post-body" class="metabox-holder columns-2">
	<div id="post-body-content">

		<form method="post">

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle ui-sortable-handle"><?php esc_html_e( 'AutoClose Status', 'autoclose' ); ?></h2>
				</div>
				<div class="inside">
					<?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped in Tools::render_status(). ?>
					<p>
						<input type="submit" name="acc_repair_schedule" id="acc_repair_schedule" value="<?php esc_attr_e( 'Repair schedule', 'autoclose' ); ?>" class="button button-secondary" />
					</p>
					<p class="description">
						<?php esc_html_e( 'Reschedules the AutoClose cron event using the current time and recurrence settings. Only takes effect when scheduled maintenance is enabled.', 'autoclose' ); ?>
					</p>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle ui-sortable-handle"><?php esc_html_e( 'Close Comments, Pingbacks and Trackbacks', 'autoclose' ); ?></h2>
				</div>
				<div class="inside">
					<p>
						<input type="submit" name="acc_preview" id="acc_preview" value="<?php esc_attr_e( 'Preview changes', 'autoclose' ); ?>" class="button button-secondary" />
						<input type="submit" name="close_all" id="close_all"  value="<?php esc_attr_e( 'Run closing algorithm', 'autoclose' ); ?>" class="button button-primary" />
					</p>
					<p class="description">
						<?php esc_html_e( 'Clicking this button will execute the closing algorithm respecting the various options in the Settings page.', 'autoclose' ); ?>
					</p>
					<?php if ( 'dry-run' === $preview_mode ) : ?>
						<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped in Tools::render_preview(). ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle ui-sortable-handle"><?php esc_html_e( 'Open or close Comments, Pingbacks and Trackbacks', 'autoclose' ); ?></h2>
				</div>
				<div class="inside">
					<p>
						<input name="acc_opencomments" type="submit" id="acc_opencomments" value="<?php esc_html_e( 'Open Comments', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open comments on all posts and post types?')) return false;" />

						<input name="acc_openpings" type="submit" id="acc_openpings" value="<?php esc_html_e( 'Open Pings', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to open pings on all posts and post types?')) return false;" />
					</p>
					<p>
						<input name="acc_closecomments" type="submit" id="acc_closecomments" value="<?php esc_html_e( 'Close Comments', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to close comments on all posts and post types?')) return false;" />

						<input name="acc_closepings" type="submit" id="acc_closepings" value="<?php esc_html_e( 'Close Pings', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('Do you want to close pings on all posts and post types?')) return false;" />
					</p>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle ui-sortable-handle"><?php esc_html_e( 'Delete Pingbacks / Trackbacks', 'autoclose' ); ?></h2>
				</div>
				<div class="inside">
					<p>
						<input name="acc_preview_pingtracks" type="submit" id="acc_preview_pingtracks" value="<?php esc_attr_e( 'Preview changes', 'autoclose' ); ?>" class="button button-secondary" />
						<input name="acc_delete_pingtracks" type="submit" id="acc_delete_pingtracks" value="<?php esc_attr_e( 'Delete pingbacks/trackbacks', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('<?php esc_attr_e( 'This will delete all pingbacks/trackbacks permanently. Proceed?', 'autoclose' ); ?>')) return false;" />
					</p>
					<p class="description">
						<?php esc_html_e( 'This is a permanent change. Once you go through with this, there is no way to restore your pingbacks/trackbacks. Please backup your database before proceeding.', 'autoclose' ); ?>
					</p>
					<?php if ( 'delete-pingtracks' === $preview_mode ) : ?>
						<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped in Tools::render_preview(). ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle ui-sortable-handle"><?php esc_html_e( 'Delete All Revisions', 'autoclose' ); ?></h2>
				</div>
				<div class="inside">
					<p>
						<input name="acc_preview_revisions" type="submit" id="acc_preview_revisions" value="<?php esc_attr_e( 'Preview changes', 'autoclose' ); ?>" class="button button-secondary" />
						<input name="acc_delete_revisions" type="submit" id="acc_delete_revisions" value="<?php esc_attr_e( 'Delete all revisions', 'autoclose' ); ?>" class="button button-secondary" onclick="if (!confirm('<?php esc_attr_e( 'This deletes every revision permanently, ignoring retention limits and the age setting, and includes autosaves. Proceed?', 'autoclose' ); ?>')) return false;" />
					</p>
					<p class="description">
						<?php esc_html_e( 'This is a permanent change. Once you go through with this, there is no way to restore your revisions. Please backup your database before proceeding.', 'autoclose' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Unlike scheduled cleanup, this button ignores the number of revisions each post type keeps and the age setting, and it also removes autosaves. To delete only revisions beyond the retention limit and older than the configured age, use the Run closing algorithm button above.', 'autoclose' ); ?>
					</p>
					<?php if ( 'delete-revisions' === $preview_mode ) : ?>
						<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped in Tools::render_preview(). ?>
					<?php endif; ?>
				</div>
			</div>

			<?php wp_nonce_field( 'acc-tools-settings' ); ?>
		</form>

	</div><!-- /#post-body-content -->

	<div id="postbox-container-1" class="postbox-container">

		<div id="side-sortables" class="meta-box-sortables ui-sortable">
			<?php require_once dirname( __DIR__ ) . '/sidebar.php'; ?>
		</div><!-- /#side-sortables -->

	</div><!-- /#postbox-container-1 -->
	</div><!-- /#post-body -->
	<br class="clear" />
	</div><!-- /#poststuff -->

</div><!-- /.wrap -->
