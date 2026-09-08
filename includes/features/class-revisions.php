<?php
/**
 * Post revisions management.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Options_API;

/**
 * Revisions class.
 *
 * @since 3.0.0
 */
class Revisions {

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Constructor code.
	}

	/**
	 * Process revisions based on settings.
	 *
	 * @since 3.0.0
	 */
	public function process_revisions(): array {
		$deleted    = 0;
		$operations = 0;
		$errors     = array();

		if ( Options_API::get_option( 'delete_revisions' ) ) {
			++$operations;
			$result = $this->delete_revisions();
			if ( false === $result ) {
				$errors[] = __( 'Deleting revisions failed.', 'autoclose' );
			} else {
				$deleted = (int) $result;
			}
		}

		/**
		 * Fires after revisions have been processed by the cron.
		 *
		 * @since 3.1.0
		 *
		 * @param int $deleted Number of revisions deleted.
		 */
		do_action( 'acc_revisions_processed', $deleted );

		return array(
			'status'            => empty( $errors ) ? ( $operations > 0 ? 'success' : 'skipped' ) : 'failed',
			'operations'        => $operations,
			'revisions_deleted' => $deleted,
			'errors'            => $errors,
		);
	}

	/**
	 * Preview revision deletion without changing content.
	 *
	 * @since 3.2.0
	 *
	 * @param int          $sample_limit   Maximum sample rows.
	 * @param array|string $post_ids       Optional parent post IDs to limit the preview.
	 * @param bool         $respect_setting Whether to skip when deletion is disabled.
	 * @return array Preview data.
	 */
	public function get_preview( int $sample_limit = 10, $post_ids = array(), bool $respect_setting = true ): array {
		global $wpdb;

		if ( $respect_setting && ! Options_API::get_option( 'delete_revisions' ) ) {
			return array(
				'status'   => 'skipped',
				'enabled'  => false,
				'affected' => 0,
				'post_ids' => wp_parse_id_list( $post_ids ),
				'sample'   => array(),
				'errors'   => array(),
			);
		}

		$sample_limit = max( 1, min( 100, $sample_limit ) );
		$ids          = wp_parse_id_list( $post_ids );
		$where        = "WHERE post_type = 'revision'";

		if ( ! empty( $ids ) ) {
			$where .= ' AND post_parent IN (' . implode( ',', $ids ) . ')';
		}

		$count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $count && ! empty( $wpdb->last_error ) ) {
			return array(
				'status' => 'failed',
				'errors' => array( $wpdb->last_error ),
			);
		}

		$sample = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT ID, post_parent, post_title, post_date FROM {$wpdb->posts} {$where} ORDER BY ID ASC LIMIT %d",
				$sample_limit
			),
			ARRAY_A
		);

		if ( null === $sample && ! empty( $wpdb->last_error ) ) {
			return array(
				'status' => 'failed',
				'errors' => array( $wpdb->last_error ),
			);
		}

		return array(
			'status'   => 'success',
			'enabled'  => true,
			'affected' => (int) $count,
			'post_ids' => $ids,
			'sample'   => is_array( $sample ) ? $sample : array(),
			'errors'   => array(),
		);
	}

	/**
	 * Delete post revisions.
	 *
	 * @since 3.0.0
	 *
	 * @param array|string $post_ids Optional parent post IDs to limit the deletion.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function delete_revisions( $post_ids = array() ) {
		global $wpdb;

		$ids   = wp_parse_id_list( $post_ids );
		$where = "WHERE post_type = 'revision'";

		if ( ! empty( $ids ) ) {
			$where .= ' AND post_parent IN (' . implode( ',', $ids ) . ')';
		}

		$revisions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT ID, post_parent FROM {$wpdb->posts} {$where}",
			ARRAY_A
		);

		if ( null === $revisions && ! empty( $wpdb->last_error ) ) {
			return false;
		}

		$revision_ids = array();
		$parent_ids   = array();
		foreach ( (array) $revisions as $revision ) {
			$revision_ids[] = (int) $revision['ID'];
			if ( ! empty( $revision['post_parent'] ) ) {
				$parent_ids[] = (int) $revision['post_parent'];
			}
		}

		$this->clean_revision_caches( $revision_ids, $parent_ids );
		if ( ! empty( $revision_ids ) ) {
			$revision_id_list = implode( ',', $revision_ids );

			$meta_result = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$revision_id_list})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $meta_result ) {
				return false;
			}

			$term_result = $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$revision_id_list})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $term_result ) {
				return false;
			}
		}

		$result = $wpdb->query( "DELETE FROM {$wpdb->posts} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result;
	}

	/**
	 * Invalidate revision and parent post caches before deletion.
	 *
	 * Clean_post_cache() cannot invalidate a revision after it has been deleted,
	 * because get_post() can no longer load the revision object. Revision cache
	 * keys are therefore deleted directly, while parent posts use WordPress's
	 * complete cache invalidation routine.
	 *
	 * @since 3.2.0
	 *
	 * @param array<int, int> $revision_ids Revision IDs.
	 * @param array<int, int> $parent_ids   Parent post IDs.
	 */
	private function clean_revision_caches( array $revision_ids, array $parent_ids ): void {
		$revision_ids = array_values( array_unique( array_map( 'intval', $revision_ids ) ) );
		$parent_ids   = array_values( array_unique( array_map( 'intval', $parent_ids ) ) );

		if ( ! empty( $revision_ids ) ) {
			$post_parent_keys = array_map(
				static function ( $revision_id ) {
					return 'post_parent:' . (string) $revision_id;
				},
				$revision_ids
			);

			if ( function_exists( 'wp_cache_delete_multiple' ) ) {
				wp_cache_delete_multiple( $revision_ids, 'posts' );
				wp_cache_delete_multiple( $post_parent_keys, 'posts' );
				wp_cache_delete_multiple( $revision_ids, 'post_meta' );
			} else {
				foreach ( $revision_ids as $revision_id ) {
					wp_cache_delete( $revision_id, 'posts' );
					wp_cache_delete( 'post_parent:' . (string) $revision_id, 'posts' );
					wp_cache_delete( $revision_id, 'post_meta' );
				}
			}

			clean_object_term_cache( $revision_ids, 'revision' );
		}

		foreach ( $parent_ids as $parent_id ) {
			clean_post_cache( $parent_id );
		}

		if ( ! empty( $revision_ids ) && function_exists( 'wp_cache_set_posts_last_changed' ) ) {
			wp_cache_set_posts_last_changed();
		}
	}

	/**
	 * Sets the number of revisions to keep for a specific post type.
	 *
	 * @since 3.0.0
	 *
	 * @param  int      $num  Number of revisions to store.
	 * @param  \WP_Post $post Post object.
	 * @return int Number of revisions to keep.
	 */
	public function revisions_to_keep( $num, $post ) {
		$post_type           = $post->post_type;
		$revision_post_types = array_keys( $this->get_revision_post_types() );

		$revisions_to_keep = Options_API::get_option( "revision_{$post_type}" );

		// If revisions to keep is -2, then we ignore.
		if ( -2 === (int) $revisions_to_keep ) {
			return $num;
		}

		$is_target_type = in_array( $post_type, $revision_post_types, true );

		return $is_target_type ? $revisions_to_keep : $num;
	}

	/**
	 * Retrieve the post types that have revisions.
	 *
	 * @since 3.0.0
	 *
	 * @return array Array of post types that support revisisions in the format name => label/name
	 */
	public function get_revision_post_types() {
		$revision_post_types = array();

		$post_types = get_post_types( array(), 'objects' );

		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type->name, 'revisions' ) ) {
				if ( property_exists( $post_type->labels, 'name' ) ) {
					$name = $post_type->labels->name;
				} else {
					$name = $post_type->name;
				}
				$revision_post_types[ $post_type->name ] = $name;
			}
		}

		return $revision_post_types;
	}
}
