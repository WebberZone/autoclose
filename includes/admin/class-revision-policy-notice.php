<?php
/**
 * One-time notice explaining the revision cleanup policy change.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Admin;

use WebberZone\AutoClose\Options_API;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Revision policy notice class.
 *
 * @since 3.2.0
 */
class Revision_Policy_Notice {

	/**
	 * Option recording that the policy change has been acknowledged.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const ACK_OPTION = 'acc_revision_policy_ack';

	/**
	 * Query argument used to dismiss the notice.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const DISMISS_ARG = 'acc_dismiss_revision_policy';

	/**
	 * Whether the notice should be shown to the current user.
	 *
	 * Only sites with scheduled revision deletion already enabled are affected by
	 * the policy change, so the notice is limited to those.
	 *
	 * @since 3.2.0
	 *
	 * @return bool True when the notice applies.
	 */
	public function should_display(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! Options_API::get_option( 'delete_revisions' ) ) {
			return false;
		}

		return ! get_option( self::ACK_OPTION );
	}

	/**
	 * Handle the dismissal request.
	 *
	 * @since 3.2.0
	 */
	public function maybe_dismiss(): void {
		if ( ! isset( $_GET[ self::DISMISS_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( self::DISMISS_ARG );

		update_option( self::ACK_OPTION, 1 );

		wp_safe_redirect( remove_query_arg( array( self::DISMISS_ARG, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Render the notice.
	 *
	 * @since 3.2.0
	 */
	public function display(): void {
		if ( ! $this->should_display() ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( self::DISMISS_ARG, 1 ), self::DISMISS_ARG );

		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong></p><p>%2$s</p><p>%3$s</p><p><a href="%4$s" class="button button-secondary">%5$s</a> <a href="%6$s">%7$s</a></p></div>',
			esc_html__( 'AutoClose: scheduled revision cleanup has changed', 'autoclose' ),
			esc_html__( 'Delete post revisions used to remove every revision on each scheduled run. It now deletes a revision only when it is beyond the number of revisions its post keeps and is older than the age cutoff, which defaults to 90 days. Autosaves are never deleted by scheduled cleanup.', 'autoclose' ),
			esc_html__( 'Your settings have not been changed. If you want the old behaviour, delete every revision from the AutoClose Tools page, or lower the age cutoff to 0 and set the revisions to keep to 0.', 'autoclose' ),
			esc_url( admin_url( 'options-general.php?page=acc_options_page' ) ),
			esc_html__( 'Review revision settings', 'autoclose' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'autoclose' )
		);
	}
}
