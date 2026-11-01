<?php
/**
 * Tests for revision pruning and deletion.
 *
 * @package AutoClose
 */

use WebberZone\AutoClose\Features\Revisions;
use WebberZone\AutoClose\Options_API;

/**
 * Revision cleanup tests.
 */
class RevisionsTest extends WP_UnitTestCase
{

    /**
     * Revisions processor.
     *
     * @var Revisions
     */
    private $revisions;

    /**
     * Set up each test.
     */
    public function set_up()
    {
        parent::set_up();

        $this->revisions = new Revisions();
        add_filter('wp_revisions_to_keep', array( $this->revisions, 'revisions_to_keep' ), 10, 2);
        $this->set_settings(array());
    }

    /**
     * Tear down each test.
     */
    public function tear_down()
    {
        remove_filter('wp_revisions_to_keep', array( $this->revisions, 'revisions_to_keep' ), 10);
        delete_option(Options_API::SETTINGS_OPTION);
        Options_API::flush_cache();

        parent::tear_down();
    }

    /**
     * Save plugin settings and flush the request cache.
     *
     * @param array $settings Settings to save.
     */
    private function set_settings( array $settings )
    {
        update_option(Options_API::SETTINGS_OPTION, $settings);
        Options_API::flush_cache();
    }

    /**
     * Create a parent post.
     *
     * @return int Post ID.
     */
    private function create_parent(): int
    {
        return self::factory()->post->create(
            array(
            'post_type'   => 'post',
            'post_status' => 'publish',
            )
        );
    }

    /**
     * Insert a revision row of a given age.
     *
     * @param  int  $parent_id   Parent post ID.
     * @param  int  $days_old    Age in days.
     * @param  bool $is_autosave Whether to create an autosave.
     * @return int Revision ID.
     */
    private function create_revision( int $parent_id, int $days_old, bool $is_autosave = false ): int
    {
        $timestamp = time() - ( $days_old * DAY_IN_SECONDS );
        $suffix    = $is_autosave ? 'autosave-v1' : 'revision-v' . wp_rand(1, 100000);

        return wp_insert_post(
            array(
            'post_type'     => 'revision',
            'post_status'   => 'inherit',
            'post_parent'   => $parent_id,
            'post_name'     => $parent_id . '-' . $suffix,
            'post_title'    => 'Revision ' . $days_old,
            'post_content'  => 'Revision content',
            'post_date'     => get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp)),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $timestamp),
            )
        );
    }

    /**
     * Pin a revision's dates to an exact UTC timestamp.
     *
     * @param int $revision_id Revision ID.
     * @param int $timestamp   UTC timestamp.
     */
    private function set_revision_date( int $revision_id, int $timestamp )
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->posts,
            array(
                'post_date'     => get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp)),
                'post_date_gmt' => gmdate('Y-m-d H:i:s', $timestamp),
            ),
            array( 'ID' => $revision_id )
        );
        clean_post_cache($revision_id);
    }

    /**
     * Return the surviving revision IDs for a post.
     *
     * @param  int $parent_id Parent post ID.
     * @return array<int, int> Revision IDs.
     */
    private function get_revision_ids( int $parent_id ): array
    {
        global $wpdb;

        return array_map(
            'intval',
            $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent = %d ORDER BY ID ASC", $parent_id)) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        );
    }

    /**
     * Revisions inside the retention limit survive even when old.
     */
    public function test_retention_limit_is_respected()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 2,
            )
        );

        $parent = $this->create_parent();
        $oldest = $this->create_revision($parent, 400);
        $middle = $this->create_revision($parent, 300);
        $newer  = $this->create_revision($parent, 200);
        $newest = $this->create_revision($parent, 100);

        $result = $this->revisions->prune_revisions();

        $this->assertSame('success', $result['status']);
        $this->assertSame(2, $result['deleted']);

        $remaining = $this->get_revision_ids($parent);
        $this->assertContains($newer, $remaining);
        $this->assertContains($newest, $remaining);
        $this->assertNotContains($oldest, $remaining);
        $this->assertNotContains($middle, $remaining);
    }

    /**
     * Excess revisions newer than the cutoff survive.
     */
    public function test_age_cutoff_protects_recent_revisions()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 1,
            )
        );

        $parent = $this->create_parent();
        $recent = $this->create_revision($parent, 5);
        $latest = $this->create_revision($parent, 1);

        $result = $this->revisions->prune_revisions();

        $this->assertSame(0, $result['deleted']);
        $this->assertContains($recent, $this->get_revision_ids($parent));
        $this->assertContains($latest, $this->get_revision_ids($parent));
    }
    /**
     * A revision exactly at the cutoff is kept; one second older is pruned.
     */
    public function test_revision_exactly_at_the_cutoff_is_kept()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent = $this->create_parent();
        $at_cutoff = $this->create_revision($parent, 30);
        $older     = $this->create_revision($parent, 30);

        // Pin both rows either side of a fixed instant so the boundary is exact.
        $cutoff = strtotime('2026-01-01 00:00:00 +0000');
        $this->set_revision_date($at_cutoff, $cutoff);
        $this->set_revision_date($older, $cutoff - 1);

        $pin = static function () use ($cutoff) {
            return $cutoff;
        };
        add_filter('acc_revisions_prune_cutoff', $pin);

        $result = $this->revisions->prune_revisions();

        remove_filter('acc_revisions_prune_cutoff', $pin);

        $remaining = $this->get_revision_ids($parent);
        $this->assertSame(1, $result['deleted']);
        $this->assertContains($at_cutoff, $remaining);
        $this->assertNotContains($older, $remaining);
    }

    /**
     * A limit of -1 keeps every revision.
     */
    public function test_unlimited_limit_prunes_nothing()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 1,
            'revision_post'    => -1,
            )
        );

        $parent = $this->create_parent();
        $this->create_revision($parent, 400);
        $this->create_revision($parent, 300);

        $result = $this->revisions->prune_revisions();

        $this->assertSame(0, $result['deleted']);
        $this->assertCount(2, $this->get_revision_ids($parent));
    }
    /**
     * Autosaves survive ordinary pruning.
     */
    public function test_autosaves_survive_pruning()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent   = $this->create_parent();
        $autosave = $this->create_revision($parent, 400, true);
        $revision = $this->create_revision($parent, 400);

        $this->revisions->prune_revisions();

        $remaining = $this->get_revision_ids($parent);
        $this->assertContains($autosave, $remaining);
        $this->assertNotContains($revision, $remaining);
    }
    /**
     * The per-run limit bounds deletion and reports that more remain.
     */
    public function test_prune_limit_bounds_the_run()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent = $this->create_parent();
        $this->create_revision($parent, 400);
        $this->create_revision($parent, 300);
        $this->create_revision($parent, 200);

        $result = $this->revisions->prune_revisions(array( 'limit' => 2 ));

        $this->assertSame(2, $result['deleted']);
        $this->assertTrue($result['limit_reached']);
        $this->assertCount(1, $this->get_revision_ids($parent));
    }
    /**
     * A partially failed run still reports what it deleted.
     */
    public function test_partial_failures_keep_their_counts()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent   = $this->create_parent();
        $doomed   = $this->create_revision($parent, 400);
        $blocked  = $this->create_revision($parent, 300);
        $blocker  = static function ($delete, $post) use ($blocked) {
            return (int) $post->ID === $blocked ? false : $delete;
        };

        add_filter('pre_delete_post', $blocker, 10, 2);

        $result = $this->revisions->process_revisions();

        remove_filter('pre_delete_post', $blocker, 10);

        $this->assertSame('partial', $result['status']);
        $this->assertSame(1, $result['revisions_deleted']);
        $this->assertSame(2, $result['revisions_scanned']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(array( $blocked ), $this->get_revision_ids($parent));
        $this->assertNotContains($doomed, $this->get_revision_ids($parent));
    }
    /**
     * Warmed taxonomy relationship caches are invalidated by the deletion.
     */
    public function test_warmed_term_relationship_cache_is_invalidated()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent   = $this->create_parent();
        $revision = $this->create_revision($parent, 400);
        $term     = self::factory()->term->create(array( 'taxonomy' => 'post_tag' ));
        wp_set_object_terms($revision, array( $term ), 'post_tag');

        // Warm the relationship cache that clean_object_term_cache() cannot reach for revisions.
        update_object_term_cache(array( $revision ), 'post');
        $this->assertNotFalse(wp_cache_get($revision, 'post_tag_relationships'));

        $this->revisions->prune_revisions();

        $this->assertFalse(wp_cache_get($revision, 'post_tag_relationships'));
        $this->assertSame(array(), wp_get_object_terms($revision, 'post_tag', array( 'fields' => 'ids' )));
    }

    /**
     * The cutoff comparison uses UTC even when the site runs another timezone.
     */
    public function test_cutoff_uses_utc_in_a_non_utc_timezone()
    {
        global $wpdb;

        update_option('timezone_string', 'Asia/Kolkata');

        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent = $this->create_parent();
        $old    = $this->create_revision($parent, 31);
        $recent = $this->create_revision($parent, 29);

        // A revision whose GMT date was never populated must fall back to post_date.
        $zeroed = $this->create_revision($parent, 400);
        $wpdb->update($wpdb->posts, array( 'post_date_gmt' => '0000-00-00 00:00:00' ), array( 'ID' => $zeroed ));
        clean_post_cache($zeroed);

        $result = $this->revisions->prune_revisions();

        update_option('timezone_string', '');

        $remaining = $this->get_revision_ids($parent);
        $this->assertSame(2, $result['deleted']);
        $this->assertNotContains($old, $remaining);
        $this->assertNotContains($zeroed, $remaining);
        $this->assertContains($recent, $remaining);
    }

    /**
     * Pruning is limited to the requested parent posts.
     */
    public function test_pruning_respects_post_id_scope()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $in_scope     = $this->create_parent();
        $out_of_scope = $this->create_parent();
        $this->create_revision($in_scope, 400);
        $untouched = $this->create_revision($out_of_scope, 400);

        $result = $this->revisions->prune_revisions(array( 'post_ids' => array( $in_scope ) ));

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(array( $untouched ), $this->get_revision_ids($out_of_scope));
    }

    /**
     * The preview reports exactly what pruning would delete and changes nothing.
     */
    public function test_preview_matches_pruning_and_is_read_only()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 1,
            )
        );

        $parent = $this->create_parent();
        $this->create_revision($parent, 400);
        $this->create_revision($parent, 300);
        $this->create_revision($parent, 2);

        $preview = $this->revisions->get_preview(10, array(), true);

        $this->assertSame('success', $preview['status']);
        $this->assertSame('prune', $preview['mode']);
        $this->assertSame(30, $preview['age']);
        $this->assertCount(3, $this->get_revision_ids($parent));

        $result = $this->revisions->prune_revisions();

        $this->assertSame($preview['affected'], $result['deleted']);
    }
    /**
     * Delete-all removes every revision, autosaves included.
     */
    public function test_delete_all_removes_everything()
    {
        $parent = $this->create_parent();
        $this->create_revision($parent, 400);
        $this->create_revision($parent, 1);
        $this->create_revision($parent, 1, true);

        $deleted = $this->revisions->delete_revisions();

        $this->assertSame(3, $deleted);
        $this->assertSame(array(), $this->get_revision_ids($parent));
    }

    /**
     * Deletion clears metadata, term relationships, caches, and fires the core hook.
     */
    public function test_deletion_cleans_up_metadata_hooks_and_cache()
    {
        global $wpdb;

        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent   = $this->create_parent();
        $revision = $this->create_revision($parent, 400);

        // add_post_meta() redirects a revision ID to its parent, so write to the revision itself.
        add_metadata('post', $revision, '_acc_test_meta', 'value');
        $term = self::factory()->term->create(array( 'taxonomy' => 'post_tag' ));
        wp_set_object_terms($revision, array( $term ), 'post_tag');

        $this->assertSame(
            '1',
            $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $revision)) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        );

        // Warm the caches the deletion must invalidate.
        get_post($revision);
        get_metadata('post', $revision, '_acc_test_meta', true);

        $fired = 0;
        $spy   = static function () use ( &$fired ) {
            ++$fired;
        };
        add_action('wp_delete_post_revision', $spy);

        $this->revisions->prune_revisions();

        remove_action('wp_delete_post_revision', $spy);

        $this->assertSame(1, $fired);
        $this->assertSame(array(), wp_get_object_terms($revision, 'post_tag', array( 'fields' => 'ids' )));
        $this->assertNull(get_post($revision));
        $this->assertFalse(wp_cache_get($revision, 'posts'));
        $this->assertSame(
            '0',
            $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $revision)) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        );
        $this->assertSame(
            '0',
            $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $revision)) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        );
    }

    /**
     * Legacy settings keep working without the new age option.
     */
    public function test_legacy_settings_do_not_delete_everything()
    {
        $this->set_settings(array( 'delete_revisions' => 1 ));

        $parent = $this->create_parent();
        $recent = $this->create_revision($parent, 5);
        $old    = $this->create_revision($parent, 400);

        $keep_none = static function () {
            return 0;
        };
        add_filter('wp_post_revisions_to_keep', $keep_none, 20);

        $this->revisions->process_revisions();

        remove_filter('wp_post_revisions_to_keep', $keep_none, 20);

        $remaining = $this->get_revision_ids($parent);
        $this->assertContains($recent, $remaining);
        $this->assertNotContains($old, $remaining);
    }
}
