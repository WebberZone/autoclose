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
     * Only revisions older than the cutoff are pruned.
     */
    public function test_age_boundary_separates_either_side_of_the_cutoff()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => 0,
            )
        );

        $parent    = $this->create_parent();
        $past_edge = $this->create_revision($parent, 31);
        $at_edge   = $this->create_revision($parent, 29);

        $result = $this->revisions->prune_revisions();

        $remaining = $this->get_revision_ids($parent);
        $this->assertSame(1, $result['deleted']);
        $this->assertNotContains($past_edge, $remaining);
        $this->assertContains($at_edge, $remaining);
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
     * A limit of 0 prunes every revision past the age cutoff.
     */
    public function test_zero_limit_prunes_all_old_revisions()
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
        $recent = $this->create_revision($parent, 2);

        $result = $this->revisions->prune_revisions();

        $this->assertSame(2, $result['deleted']);
        $this->assertSame(array( $recent ), $this->get_revision_ids($parent));
    }

    /**
     * The -2 sentinel defers to whatever WordPress decides.
     */
    public function test_inherited_limit_defers_to_core()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            'revision_post'    => -2,
            )
        );

        $parent = $this->create_parent();
        $this->create_revision($parent, 400);
        $this->create_revision($parent, 300);

        $keep_one = static function () {
            return 1;
        };
        add_filter('wp_post_revisions_to_keep', $keep_one, 20);

        $result = $this->revisions->prune_revisions();

        remove_filter('wp_post_revisions_to_keep', $keep_one, 20);

        $this->assertSame(1, $result['deleted']);
        $this->assertCount(1, $this->get_revision_ids($parent));
    }

    /**
     * An unset limit falls back to the -2 default and is not treated as zero.
     */
    public function test_unset_limit_uses_the_default_sentinel()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 30,
            )
        );

        $parent = $this->create_parent();
        $this->create_revision($parent, 400);

        $keep_all = static function () {
            return -1;
        };
        add_filter('wp_post_revisions_to_keep', $keep_all, 20);

        $result = $this->revisions->prune_revisions();

        remove_filter('wp_post_revisions_to_keep', $keep_all, 20);

        $this->assertSame(0, $result['deleted']);
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
     * An age of zero prunes by retention alone.
     */
    public function test_zero_age_prunes_by_retention_only()
    {
        $this->set_settings(
            array(
            'delete_revisions' => 1,
            'revision_age'     => 0,
            'revision_post'    => 1,
            )
        );

        $parent = $this->create_parent();
        $this->create_revision($parent, 2);
        $newest = $this->create_revision($parent, 1);

        $result = $this->revisions->prune_revisions();

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(array( $newest ), $this->get_revision_ids($parent));
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
     * The preview is skipped when scheduled deletion is disabled.
     */
    public function test_preview_is_skipped_when_deletion_is_disabled()
    {
        $this->set_settings(array( 'delete_revisions' => 0 ));

        $parent = $this->create_parent();
        $this->create_revision($parent, 400);

        $preview = $this->revisions->get_preview();

        $this->assertSame('skipped', $preview['status']);
        $this->assertFalse($preview['enabled']);
        $this->assertSame(0, $preview['affected']);
    }

    /**
     * Scheduled processing prunes rather than deleting everything.
     */
    public function test_process_revisions_prunes_instead_of_deleting_all()
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
        $kept = $this->create_revision($parent, 300);

        $result = $this->revisions->process_revisions();

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['revisions_deleted']);
        $this->assertSame(array( $kept ), $this->get_revision_ids($parent));
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
