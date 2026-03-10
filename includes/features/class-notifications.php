<?php
/**
 * Feature: Email notifications after cron run.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Util\Hook_Registry;
use WebberZone\AutoClose\Util\Options;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Notifications class.
 *
 * Listens for the acc_comments_processed and acc_revisions_processed actions
 * fired by Comments::process_comments() and Revisions::process_revisions(),
 * accumulates the counts, then sends a summary email at the end of the cron run
 * (priority 20 on acc_cron_hook, after the default priority-10 processors).
 *
 * @since 3.1.0
 */
class Notifications {

	/**
	 * Number of posts whose comments were closed during this run.
	 *
	 * @since 3.1.0
	 * @var int
	 */
	private int $comments_closed = 0;

	/**
	 * Number of posts whose pings were closed during this run.
	 *
	 * @since 3.1.0
	 * @var int
	 */
	private int $pings_closed = 0;

	/**
	 * Number of revisions deleted during this run.
	 *
	 * @since 3.1.0
	 * @var int
	 */
	private int $revisions_deleted = 0;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 */
	public function __construct() {
		Hook_Registry::add_action( 'acc_comments_processed', array( $this, 'collect_comments' ), 10, 2 );
		Hook_Registry::add_action( 'acc_revisions_processed', array( $this, 'collect_revisions' ) );
		Hook_Registry::add_action( 'acc_cron_hook', array( $this, 'maybe_send_email' ), 20 );
	}

	/**
	 * Collect comment/ping close counts.
	 *
	 * @since 3.1.0
	 *
	 * @param int $comments Number of posts whose comments were closed.
	 * @param int $pings    Number of posts whose pings were closed.
	 */
	public function collect_comments( int $comments, int $pings ): void {
		$this->comments_closed += $comments;
		$this->pings_closed    += $pings;
	}

	/**
	 * Collect revision delete count.
	 *
	 * @since 3.1.0
	 *
	 * @param int $deleted Number of revisions deleted.
	 */
	public function collect_revisions( int $deleted ): void {
		$this->revisions_deleted += $deleted;
	}

	/**
	 * Send a summary email if the option is enabled.
	 *
	 * Runs at priority 20 on acc_cron_hook, after the comments (priority 10)
	 * and revisions (priority 10) processors have completed.
	 *
	 * @since 3.1.0
	 */
	public function maybe_send_email(): void {
		if ( ! Options::get_option( 'email_notify' ) ) {
			return;
		}

		$to = Options::get_option( 'email_notify_address' );
		if ( ! $to || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] AutoClose Cron Summary', 'autoclose' ), $site_name );

		$rows = array(
			__( 'Comments closed', 'autoclose' )   => $this->comments_closed,
			__( 'Pings closed', 'autoclose' )      => $this->pings_closed,
			__( 'Revisions deleted', 'autoclose' ) => $this->revisions_deleted,
		);

		ob_start();
		include __DIR__ . '/views/email-cron-summary.php';
		$body = ob_get_clean();

		wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}
