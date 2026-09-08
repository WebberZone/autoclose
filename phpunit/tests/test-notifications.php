<?php
/**
 * Tests for cron notification counters.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Notifications;

/**
 * Notification counter tests.
 */
class NotificationsTest extends WP_UnitTestCase {

	/**
	 * Counts are cleared for the current site without affecting another site.
	 */
	public function test_counts_are_isolated_per_blog_and_reset_per_run() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$main_blog_id  = get_current_blog_id();
		$secondary_id  = self::factory()->blog->create();
		$notifications = new Notifications();
		$notifications->collect_comments( 2, 3 );
		$notifications->collect_revisions( 4 );

		switch_to_blog( $secondary_id );
		$notifications->collect_comments( 5, 6 );
		$notifications->collect_revisions( 7 );
		$notifications->reset_counts();
		restore_current_blog();

		$property = new ReflectionProperty( Notifications::class, 'comments_closed' );
		$property->setAccessible( true );
		$comments = $property->getValue( $notifications );

		$pings_property = new ReflectionProperty( Notifications::class, 'pings_closed' );
		$pings_property->setAccessible( true );
		$pings = $pings_property->getValue( $notifications );

		$revisions_property = new ReflectionProperty( Notifications::class, 'revisions_deleted' );
		$revisions_property->setAccessible( true );
		$revisions = $revisions_property->getValue( $notifications );

		$this->assertSame( array( $main_blog_id => 2 ), $comments );
		$this->assertSame( array( $main_blog_id => 3 ), $pings );
		$this->assertSame( array( $main_blog_id => 4 ), $revisions );

		$notifications->reset_counts();
		$this->assertSame( array(), $property->getValue( $notifications ) );
		$this->assertSame( array(), $pings_property->getValue( $notifications ) );
		$this->assertSame( array(), $revisions_property->getValue( $notifications ) );
	}
}
