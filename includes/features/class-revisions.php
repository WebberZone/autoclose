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
	 * Number of parent posts examined per scan batch.
	 *
	 * @since 3.2.0
	 * @var   int
	 */
	const SCAN_BATCH_SIZE = 200;

	/**
	 * Number of revisions fetched per delete-all batch.
	 *
	 * @since 3.2.0
	 * @var   int
	 */
	const DELETE_BATCH_SIZE = 200;

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
		$scanned    = 0;
		$remaining  = false;
		$operations = 0;
		$errors     = array();

		if ( Options_API::get_option( 'delete_revisions' ) ) {
			++$operations;
			$result = $this->prune_revisions();

			// Counts are reported even on failure: a partial run still deleted what it deleted.
			$deleted   = (int) $result['deleted'];
			$scanned   = (int) $result['scanned'];
			$remaining = (bool) $result['limit_reached'];

			if ( 'failed' === $result['status'] ) {
				$errors = array_merge( $errors, $result['errors'] );
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
			'status'                  => empty( $errors ) ? ( $operations > 0 ? 'success' : 'skipped' ) : 'failed',
			'operations'              => $operations,
			'revisions_deleted'       => $deleted,
			'revisions_scanned'       => $scanned,
			'revisions_limit_reached' => $remaining,
			'errors'                  => $errors,
		);
	}

	/**
	 * Number of days a revision must exceed before ordinary pruning removes it.
	 *
	 * @since 3.2.0
	 *
	 * @return int Age in days. Zero disables the age condition.
	 */
	public function get_revision_age(): int {
		return max( 0, (int) Options_API::get_option( 'revision_age' ) );
	}

	/**
	 * Maximum number of revisions a single pruning run may delete.
	 *
	 * @since 3.2.0
	 *
	 * @return int Bound for one run. Zero removes the bound.
	 */
	public function get_prune_limit(): int {
		/**
		 * Filters the maximum number of revisions deleted by a single pruning run.
		 *
		 * @since 3.2.0
		 *
		 * @param int $limit Maximum revisions per run. Zero removes the bound.
		 */
		return max( 0, (int) apply_filters( 'acc_revisions_prune_limit', 1000 ) );
	}

	/**
	 * Preview revision cleanup without changing content.
	 *
	 * @since 3.2.0
	 *
	 * @param  int          $sample_limit    Maximum sample rows.
	 * @param  array|string $post_ids        Optional parent post IDs to limit the preview.
	 * @param  bool         $respect_setting Whether to skip when deletion is disabled.
	 * @param  string       $mode            Either `prune` for the retention and age policy, or `all` for delete-all.
	 * @return array Preview data.
	 */
	public function get_preview( int $sample_limit = 10, $post_ids = array(), bool $respect_setting = true, string $mode = 'prune' ): array {
		$sample_limit = max( 1, min( 100, $sample_limit ) );
		$ids          = wp_parse_id_list( $post_ids );

		if ( $respect_setting && ! Options_API::get_option( 'delete_revisions' ) ) {
			return array(
				'status'        => 'skipped',
				'enabled'       => false,
				'mode'          => $mode,
				'affected'      => 0,
				'scanned'       => 0,
				'age'           => $this->get_revision_age(),
				'limit_reached' => false,
				'post_ids'      => $ids,
				'sample'        => array(),
				'errors'        => array(),
			);
		}

		return 'all' === $mode
		? $this->preview_all( $sample_limit, $ids )
		: $this->get_prune_preview( $sample_limit, $ids );
	}

	/**
	 * Preview the revisions an ordinary pruning run would delete.
	 *
	 * @since 3.2.0
	 *
	 * @param  int          $sample_limit Maximum sample rows.
	 * @param  array|string $post_ids     Parent post IDs.
	 * @param  int|null     $age_days     Age cutoff in days. Null uses the saved setting.
	 * @param  int|null     $limit        Maximum candidates to collect. Null uses the filtered default.
	 * @return array Preview data.
	 */
	public function get_prune_preview( int $sample_limit = 10, $post_ids = array(), ?int $age_days = null, ?int $limit = null ): array {
		$sample_limit = max( 1, min( 100, $sample_limit ) );
		$ids          = wp_parse_id_list( $post_ids );

		$scan = $this->get_candidates(
			array(
				'post_ids' => $ids,
				'age_days' => $age_days,
				'limit'    => null === $limit ? $this->get_prune_limit() : max( 0, $limit ),
			)
		);

		if ( null !== $scan['error'] ) {
			return array(
				'status' => 'failed',
				'errors' => array( $scan['error'] ),
			);
		}

		return array(
			'status'        => 'success',
			'enabled'       => true,
			'mode'          => 'prune',
			'affected'      => count( $scan['candidates'] ),
			'scanned'       => $scan['scanned'],
			'age'           => null === $age_days ? $this->get_revision_age() : max( 0, $age_days ),
			'limit_reached' => $scan['limit_reached'],
			'post_ids'      => $ids,
			'sample'        => array_slice( $scan['candidates'], 0, $sample_limit ),
			'errors'        => array(),
		);
	}

	/**
	 * Preview every revision, matching the explicit delete-all action.
	 *
	 * @since 3.2.0
	 *
	 * @param  int             $sample_limit Maximum sample rows.
	 * @param  array<int, int> $ids          Parent post IDs.
	 * @return array Preview data.
	 */
	private function preview_all( int $sample_limit, array $ids ): array {
		global $wpdb;

		$where = "WHERE post_type = 'revision'";

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
			'status'        => 'success',
			'enabled'       => true,
			'mode'          => 'all',
			'affected'      => (int) $count,
			'scanned'       => (int) $count,
			'age'           => 0,
			'limit_reached' => false,
			'post_ids'      => $ids,
			'sample'        => is_array( $sample ) ? $sample : array(),
			'errors'        => array(),
		);
	}

	/**
	 * Delete revisions that exceed the retention limit and the age cutoff.
	 *
	 * A revision is pruned only when it is beyond the effective number of
	 * revisions the post keeps and is strictly older than the age cutoff.
	 * Autosaves are never pruned, matching how core skips them when trimming.
	 *
	 * @since 3.2.0
	 *
	 * @param array $args {
	 *                    Optional. Pruning arguments.
	 *
	 * @type   array|string $post_ids Parent post IDs to limit the run.
	 * @type   int|null     $age_days Age cutoff in days. Null uses the saved setting.
	 * @type   int|null     $limit    Maximum revisions to delete. Null uses the filtered default.
	 * }
	 * @return array Pruning result.
	 */
	public function prune_revisions( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'post_ids' => array(),
				'age_days' => null,
				'limit'    => null,
			)
		);

		$scan = $this->get_candidates(
			array(
				'post_ids' => $args['post_ids'],
				'age_days' => $args['age_days'],
				'limit'    => null === $args['limit'] ? $this->get_prune_limit() : max( 0, (int) $args['limit'] ),
			)
		);

		if ( null !== $scan['error'] ) {
			return array(
				'status'        => 'failed',
				'deleted'       => 0,
				'scanned'       => $scan['scanned'],
				'limit_reached' => false,
				'errors'        => array( $scan['error'] ),
			);
		}

		$deleted     = 0;
		$failed      = 0;
		$deleted_ids = array();

		foreach ( $scan['candidates'] as $candidate ) {
			$revision_id = (int) $candidate['ID'];

			if ( wp_delete_post_revision( $revision_id ) ) {
				++$deleted;
				$deleted_ids[] = $revision_id;
			} else {
				++$failed;
			}
		}

		$errors = array();

		if ( ! $this->remove_orphaned_term_relationships( $deleted_ids ) ) {
			$errors[] = __( 'Removing term relationships left by deleted revisions failed.', 'autoclose' );
		}

		if ( $failed > 0 ) {
			$errors[] = sprintf(
			/* translators: 1: Number of revisions. */
				_n( '%d revision could not be deleted.', '%d revisions could not be deleted.', $failed, 'autoclose' ),
				$failed
			);
		}

		return array(
			'status'        => empty( $errors ) ? 'success' : 'failed',
			'deleted'       => $deleted,
			'scanned'       => $scan['scanned'],
			'limit_reached' => $scan['limit_reached'],
			'errors'        => $errors,
		);
	}

	/**
	 * Collect the revisions eligible for ordinary pruning.
	 *
	 * @since 3.2.0
	 *
	 * @param array $args {
	 *                    Optional. Selection arguments.
	 *
	 * @type   array|string $post_ids Parent post IDs to limit the scan.
	 * @type   int|null     $age_days Age cutoff in days. Null uses the saved setting.
	 * @type   int          $limit    Maximum candidates to collect. Zero removes the bound.
	 * }
	 * @return array{candidates: array<int, array<string, mixed>>, scanned: int, limit_reached: bool, error: string|null} Selection result.
	 */
	private function get_candidates( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'post_ids' => array(),
				'age_days' => null,
				'limit'    => 0,
			)
		);

		$ids       = wp_parse_id_list( $args['post_ids'] );
		$age_days  = null === $args['age_days'] ? $this->get_revision_age() : max( 0, (int) $args['age_days'] );
		$cutoff    = $age_days > 0 ? time() - ( $age_days * DAY_IN_SECONDS ) : null;
		$limit     = max( 0, (int) $args['limit'] );
		$id_clause = empty( $ids ) ? '' : ' AND post_parent IN (' . implode( ',', $ids ) . ')';

		$candidates    = array();
		$scanned       = 0;
		$limit_reached = false;
		$offset        = 0;

		while ( true ) {
			$parents = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
           // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent > 0{$id_clause} ORDER BY post_parent ASC LIMIT %d OFFSET %d",
					self::SCAN_BATCH_SIZE,
					$offset
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
					return $this->failed_scan( $scanned, $wpdb->last_error );
			}

			if ( empty( $parents ) ) {
				break;
			}

			$parents      = array_map( 'intval', $parents );
			$parent_count = count( $parents );
			$offset      += $parent_count;
			$grouped      = $this->get_revisions_for_parents( $parents );

			if ( null === $grouped ) {
				return $this->failed_scan( $scanned, $wpdb->last_error );
			}

			_prime_post_caches( $parents, false, false );

			foreach ( $grouped as $parent_id => $revisions ) {
				$scanned += count( $revisions );
				$parent   = get_post( $parent_id );

				if ( ! $parent instanceof \WP_Post ) {
					continue;
				}

				$keep = (int) wp_revisions_to_keep( $parent );

				if ( $keep < 0 ) {
					continue;
				}

				$excess = count( $revisions ) - $keep;

				if ( $excess < 1 ) {
					continue;
				}

				foreach ( array_slice( $revisions, 0, $excess ) as $revision ) {
					if ( $this->is_autosave( $revision ) ) {
						continue;
					}

					if ( null !== $cutoff && $this->get_revision_timestamp( $revision ) >= $cutoff ) {
						continue;
					}

					$candidates[] = $revision;

					// Collect one past the limit so "more remaining" reflects a real extra candidate.
					if ( $limit > 0 && count( $candidates ) > $limit ) {
						array_pop( $candidates );
						$limit_reached = true;
						break 3;
					}
				}
			}

			if ( $parent_count < self::SCAN_BATCH_SIZE ) {
				break;
			}
		}

		return array(
			'candidates'    => $candidates,
			'scanned'       => $scanned,
			'limit_reached' => $limit_reached,
			'error'         => null,
		);
	}

	/**
	 * Fetch the revisions of several parent posts, oldest first, grouped by parent.
	 *
	 * @since 3.2.0
	 *
	 * @param  array<int, int> $parents Parent post IDs.
	 * @return array<int, array<int, array<string, mixed>>>|null Revisions grouped by parent, or null on error.
	 */
	private function get_revisions_for_parents( array $parents ): ?array {
		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $parents ), '%d' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
       // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"SELECT ID, post_parent, post_name, post_title, post_date, post_date_gmt FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent IN ({$placeholders}) ORDER BY post_parent ASC, post_date ASC, ID ASC",
				$parents
			),
			ARRAY_A
		);

		if ( null === $rows || ! empty( $wpdb->last_error ) ) {
			return null;
		}

		$grouped = array();

		foreach ( (array) $rows as $row ) {
			$grouped[ (int) $row['post_parent'] ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Whether a revision row is an autosave.
	 *
	 * @since 3.2.0
	 *
	 * @param  array<string, mixed> $revision Revision row.
	 * @return bool True when the row is an autosave.
	 */
	private function is_autosave( array $revision ): bool {
		return false !== strpos( (string) ( $revision['post_name'] ?? '' ), 'autosave' );
	}

	/**
	 * Resolve a revision's creation time as a UTC timestamp.
	 *
	 * @since 3.2.0
	 *
	 * @param  array<string, mixed> $revision Revision row.
	 * @return int UTC timestamp.
	 */
	private function get_revision_timestamp( array $revision ): int {
		$gmt = (string) ( $revision['post_date_gmt'] ?? '' );

		if ( '' !== $gmt && '0000-00-00 00:00:00' !== $gmt ) {
			return (int) strtotime( $gmt . ' +0000' );
		}

		return (int) get_gmt_from_date( (string) ( $revision['post_date'] ?? '' ), 'U' );
	}

	/**
	 * Build a failed selection result.
	 *
	 * @since 3.2.0
	 *
	 * @param  int    $scanned Revisions examined before the failure.
	 * @param  string $error   Database error.
	 * @return array Failed selection result.
	 */
	private function failed_scan( int $scanned, string $error ): array {
		return array(
			'candidates'    => array(),
			'scanned'       => $scanned,
			'limit_reached' => false,
			'error'         => '' !== $error ? $error : __( 'Revision selection failed.', 'autoclose' ),
		);
	}

	/**
	 * Remove term relationships left behind by deleted revisions.
	 *
	 * WordPress only clears relationships for taxonomies registered against
	 * the post's own type, and no taxonomy is registered for `revision`, so rows
	 * attached to a revision survive the delete. Revisions are excluded from term
	 * counts, so the rows can be dropped without recounting. clean_object_term_cache()
	 * is likewise a no-op for `revision`, so relationship caches are cleared for every
	 * registered taxonomy instead.
	 *
	 * @since 3.2.0
	 *
	 * @param  array<int, int> $revision_ids Deleted revision IDs.
	 * @return bool True on success, false when the cleanup query failed.
	 */
	private function remove_orphaned_term_relationships( array $revision_ids ): bool {
		global $wpdb;

		$revision_ids = array_values( array_unique( array_map( 'intval', $revision_ids ) ) );

		if ( empty( $revision_ids ) ) {
			return true;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $revision_ids ), '%d' ) );

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$placeholders})",
				$revision_ids
			)
		);

		foreach ( get_taxonomies() as $taxonomy ) {
			foreach ( $revision_ids as $revision_id ) {
				wp_cache_delete( $revision_id, $taxonomy . '_relationships' );
			}
		}

		wp_cache_set_terms_last_changed();

		return false !== $result;
	}

	/**
	 * Delete every post revision, ignoring retention limits and the age cutoff.
	 *
	 * This is the explicit delete-all action. Ordinary scheduled cleanup uses
	 * prune_revisions() instead.
	 *
	 * @since 3.0.0
	 *
	 * @param  array|string $post_ids Optional parent post IDs to limit the deletion.
	 * @return int|bool Number of revisions deleted. Boolean false when anything failed.
	 */
	public function delete_revisions( $post_ids = array() ) {
		$result = $this->delete_all_revisions( $post_ids );

		return 'success' === $result['status'] ? $result['deleted'] : false;
	}

	/**
	 * Delete every post revision and report what happened.
	 *
	 * Same operation as delete_revisions(), but reports partial progress and the
	 * reason for a failure instead of collapsing to boolean false.
	 *
	 * @since 3.2.0
	 *
	 * @param  array|string $post_ids Optional parent post IDs to limit the deletion.
	 * @return array Deletion result.
	 */
	public function delete_all_revisions( $post_ids = array() ): array {
		global $wpdb;

		$ids     = wp_parse_id_list( $post_ids );
		$where   = "WHERE post_type = 'revision'";
		$deleted = 0;
		$errors  = array();

		if ( ! empty( $ids ) ) {
			$where .= ' AND post_parent IN (' . implode( ',', $ids ) . ')';
		}

		while ( true ) {
			$revision_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
           // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT ID FROM {$wpdb->posts} {$where} ORDER BY ID ASC LIMIT %d",
					self::DELETE_BATCH_SIZE
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				$errors[] = $wpdb->last_error;
				break;
			}

			if ( empty( $revision_ids ) ) {
				break;
			}

			$batch_deleted = 0;
			$deleted_ids   = array();

			foreach ( $revision_ids as $revision_id ) {
				$revision_id = (int) $revision_id;

				if ( wp_delete_post_revision( $revision_id ) ) {
					++$batch_deleted;
					$deleted_ids[] = $revision_id;
				}
			}

			if ( ! $this->remove_orphaned_term_relationships( $deleted_ids ) ) {
				$errors[] = __( 'Removing term relationships left by deleted revisions failed.', 'autoclose' );
			}

			$deleted += $batch_deleted;

			// Nothing was removed, so the same rows would be selected forever.
			if ( $batch_deleted < count( $revision_ids ) ) {
				$errors[] = sprintf(
					/* translators: 1: Number of revisions. */
					_n( '%d revision could not be deleted.', '%d revisions could not be deleted.', count( $revision_ids ) - $batch_deleted, 'autoclose' ),
					count( $revision_ids ) - $batch_deleted
				);
				break;
			}
		}

		return array(
			'status'  => empty( $errors ) ? 'success' : 'failed',
			'deleted' => $deleted,
			'errors'  => $errors,
		);
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
