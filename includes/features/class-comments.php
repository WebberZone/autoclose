<?php
/**
 * Comments management.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Options_API;
use WebberZone\AutoClose\Util\Helpers;

/**
 * Comments class.
 *
 * @since 3.0.0
 */
class Comments {

	/**
	 * Maximum number of posts to update and invalidate in one batch.
	 *
	 * @since 3.2.0
	 */
	private const EDIT_BATCH_SIZE = 500;

	/**
	 * Maximum number of legacy status rows migrated in one batch.
	 *
	 * @since 3.2.0
	 */
	private const STATUS_MIGRATION_BATCH_SIZE = 500;

	/**
	 * Option recording that legacy discussion statuses have been migrated.
	 *
	 * @since 3.2.0
	 */
	private const STATUS_MIGRATION_OPTION = 'acc_legacy_status_migration_complete';

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Constructor code.
	}

	/**
	 * Resolve the effective closing age for a post type.
	 *
	 * -2 (default) inherits the global comment_age/pbtb_age setting; -1 disables
	 * closing for this post type; 0 or higher is an explicit age in days.
	 *
	 * @since 3.2.0
	 *
	 * @param string $type      Discussion type: comment or ping.
	 * @param string $post_type Post type.
	 * @return int|null Age in days, or null when closing is disabled for this post type.
	 */
	public function get_effective_age( string $type, string $post_type ): ?int {
		$prefix   = 'ping' === $type ? 'pbtb' : 'comment';
		$override = Options_API::get_option( "{$prefix}_age_{$post_type}" );
		$override = ( null === $override || '' === $override ) ? -2 : (int) $override;

		if ( -2 === $override ) {
			return max( 0, (int) Options_API::get_option( "{$prefix}_age" ) );
		}

		return -1 === $override ? null : max( 0, $override );
	}

	/**
	 * Resolve the effective closing age for each of the given post types.
	 *
	 * @since 3.2.0
	 *
	 * @param string $type       Discussion type: comment or ping.
	 * @param array  $post_types Post types.
	 * @return array<string, int|null> Post type to effective age, null meaning disabled.
	 */
	public function get_type_ages( string $type, array $post_types ): array {
		$ages = array();

		foreach ( $post_types as $post_type ) {
			$ages[ $post_type ] = $this->get_effective_age( $type, $post_type );
		}

		return $ages;
	}

	/**
	 * Approved-comment count threshold for closing comments.
	 *
	 * Opt-in; zero disables it. Only ordinary approved comments count: pingbacks,
	 * trackbacks, spam, unapproved entries, and internal editor notes are excluded.
	 * Scheduled checking evaluates this once per run, not atomically, so concurrent
	 * or between-run submissions can push a post's count past the threshold before
	 * the next run closes it.
	 *
	 * @since 3.2.0
	 *
	 * @return int Threshold. Zero disables it.
	 */
	public function get_count_threshold(): int {
		return max( 0, (int) Options_API::get_option( 'comment_count_threshold' ) );
	}

	/**
	 * Retrieve public post types eligible for per-post-type age overrides.
	 *
	 * Matches the universe offered by the comment_post_types/pbtb_post_types selectors.
	 *
	 * @since 3.2.0
	 *
	 * @return array Array of post types in the format name => label.
	 */
	public function get_supported_post_types(): array {
		$supported  = array();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			$label                         = property_exists( $post_type->labels, 'name' ) ? $post_type->labels->name : $post_type->name;
			$supported[ $post_type->name ] = $label;
		}

		return $supported;
	}

	/**
	 * Process comments based on settings.
	 *
	 * @since 3.0.0
	 */
	public function process_comments(): array {
		$comment_pids      = Options_API::get_option( 'comment_pids' );
		$pbtb_pids         = Options_API::get_option( 'pbtb_pids' );
		$comments_closed   = 0;
		$pings_closed      = 0;
		$comments_opened   = 0;
		$pings_opened      = 0;
		$comments_migrated = 0;
		$pings_migrated    = 0;
		$operations        = 0;
		$errors            = array();
		$progress          = false;
		$migrated          = $this->migrate_legacy_statuses();

		if ( ! empty( $migrated['errors'] ) ) {
			$errors = array_merge( $errors, $migrated['errors'] );
		}
		if ( $migrated['updated'] > 0 ) {
			++$operations;
			$comments_migrated = (int) $migrated['comments'];
			$pings_migrated    = (int) $migrated['pings'];
			$progress          = true;
		}

		// Get the post types.
		$comment_post_types = Helpers::parse_post_types( Options_API::get_option( 'comment_post_types' ) );
		$pbtb_post_types    = Helpers::parse_post_types( Options_API::get_option( 'pbtb_post_types' ) );

		// Use the sanitized term ID fields saved by the settings handler.
		$comment_exclude_terms = Options_API::get_option( 'comment_exclude_term_ids' );
		$pbtb_exclude_terms    = Options_API::get_option( 'pbtb_exclude_term_ids' );

		// Close Comments on posts.
		if ( Options_API::get_option( 'close_comment' ) ) {
			++$operations;
			$result = $this->edit_discussions_result(
				'comment',
				'close',
				array(
					'type_ages'       => $this->get_type_ages( 'comment', $comment_post_types ),
					'count_threshold' => $this->get_count_threshold(),
					'post_types'      => $comment_post_types,
					'exclude_terms'   => $comment_exclude_terms,
				)
			);
			if ( ! empty( $result['errors'] ) ) {
				$errors = array_merge( $errors, $result['errors'] );
			}
			$comments_closed += (int) $result['affected'];
			$progress         = $progress || ( (int) $result['affected'] > 0 );
		}

		// Close Pingbacks/Trackbacks on posts.
		if ( Options_API::get_option( 'close_pbtb' ) ) {
			++$operations;
			$result = $this->edit_discussions_result(
				'ping',
				'close',
				array(
					'type_ages'     => $this->get_type_ages( 'ping', $pbtb_post_types ),
					'post_types'    => $pbtb_post_types,
					'exclude_terms' => $pbtb_exclude_terms,
				)
			);
			if ( ! empty( $result['errors'] ) ) {
				$errors = array_merge( $errors, $result['errors'] );
			}
			$pings_closed += (int) $result['affected'];
			$progress      = $progress || ( (int) $result['affected'] > 0 );
		}

		// Open Comments on these posts.
		if ( ! empty( $comment_pids ) ) {
			++$operations;
			$result = $this->edit_discussions_result(
				'comment',
				'open',
				array(
					'post_ids' => $comment_pids,
				)
			);
			if ( ! empty( $result['errors'] ) ) {
				$errors = array_merge( $errors, $result['errors'] );
			}
			$comments_opened = (int) $result['affected'];
			$progress        = $progress || ( (int) $result['affected'] > 0 );
		}

		// Open Pingbacks / Trackbacks on these posts.
		if ( ! empty( $pbtb_pids ) ) {
			++$operations;
			$result = $this->edit_discussions_result(
				'ping',
				'open',
				array(
					'post_ids' => $pbtb_pids,
				)
			);
			if ( ! empty( $result['errors'] ) ) {
				$errors = array_merge( $errors, $result['errors'] );
			}
			$pings_opened = (int) $result['affected'];
			$progress     = $progress || ( (int) $result['affected'] > 0 );
		}

		/**
		 * Fires after comments and pings have been processed by the cron.
		 *
		 * @since 3.1.0
		 *
		 * @param int $comments_closed Number of posts whose comments were closed.
		 * @param int $pings_closed    Number of posts whose pings were closed.
		 */
		do_action( 'acc_comments_processed', $comments_closed, $pings_closed );

		$status = empty( $errors ) ? ( $operations > 0 ? 'success' : 'skipped' ) : ( $progress ? 'partial' : 'failed' );

		return array(
			'status'            => $status,
			'operations'        => $operations,
			'comments_closed'   => $comments_closed,
			'pings_closed'      => $pings_closed,
			'comments_opened'   => $comments_opened,
			'pings_opened'      => $pings_opened,
			'comments_migrated' => $comments_migrated,
			'pings_migrated'    => $pings_migrated,
			'errors'            => $errors,
		);
	}

	/**
	 * Migrate the legacy `close` status to WordPress's canonical `closed` value.
	 *
	 * @since 3.2.0
	 *
	 * @return array Migration result.
	 */
	public function migrate_legacy_statuses(): array {
		global $wpdb;

		if ( get_option( self::STATUS_MIGRATION_OPTION, false ) ) {
			return array(
				'status'   => 'success',
				'updated'  => 0,
				'comments' => 0,
				'pings'    => 0,
				'errors'   => array(),
			);
		}

		$updated  = 0;
		$comments = 0;
		$pings    = 0;
		$errors   = array();

		foreach ( array( 'comment', 'ping' ) as $type ) {
			$status_column = 'comment' === $type ? 'comment_status' : 'ping_status';
			$last_id       = 0;

			do {
				$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE {$status_column} = 'close' AND ID > %d ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$last_id,
						self::STATUS_MIGRATION_BATCH_SIZE
					)
				);

				if ( ! empty( $wpdb->last_error ) ) {
					$errors[] = $this->get_database_error( __( 'Legacy discussion status migration failed.', 'autoclose' ) );
					break 2;
				}

				if ( empty( $ids ) ) {
					break;
				}

				$ids    = array_map( 'intval', $ids );
				$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"UPDATE {$wpdb->posts} SET {$status_column} = 'closed' WHERE {$status_column} = 'close' AND ID IN (" . implode( ',', $ids ) . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);

				if ( false === $result ) {
					$errors[] = $this->get_database_error( __( 'Legacy discussion status migration failed.', 'autoclose' ) );
					break 2;
				}

				$result      = (int) $result;
				$batch_count = count( $ids );
				$updated    += $result;
				if ( 'comment' === $type ) {
					$comments += $result;
				} else {
					$pings += $result;
				}

				$this->clean_discussion_caches( $ids );
				$last_id = (int) end( $ids );
			} while ( self::STATUS_MIGRATION_BATCH_SIZE === $batch_count );
		}

		if ( empty( $errors ) ) {
			update_option( self::STATUS_MIGRATION_OPTION, true, false );
		}

		$status = empty( $errors ) ? 'success' : ( $updated > 0 ? 'partial' : 'failed' );

		return array(
			'status'   => $status,
			'updated'  => $updated,
			'comments' => $comments,
			'pings'    => $pings,
			'errors'   => $errors,
		);
	}

	/**
	 * Function to open/close comments or pingback/trackbacks
	 *
	 * @since 3.0.0
	 *
	 * @param string       $type   'comment' or 'ping'.
	 * @param string       $action 'open' or 'close'.
	 * @param string|array $args   Optional arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function edit_discussions( $type = 'comment', $action = 'open', $args = array() ) {
		$result = $this->edit_discussions_result( $type, $action, $args );

		return 'success' === $result['status'] ? $result['affected'] : false;
	}

	/**
	 * Open or close discussions and preserve partial progress on failure.
	 *
	 * @since 3.2.0
	 *
	 * @param string       $type   'comment' or 'ping'.
	 * @param string       $action 'open' or 'close'.
	 * @param string|array $args   Optional arguments.
	 * @return array Discussion operation result.
	 */
	public function edit_discussions_result( $type = 'comment', $action = 'open', $args = array() ): array {
		global $wpdb;

		if ( ! in_array( $type, array( 'comment', 'ping' ), true ) || ! in_array( $action, array( 'open', 'close' ), true ) ) {
			return $this->failed_discussion_result( 0, __( 'Invalid discussion operation.', 'autoclose' ) );
		}

		$defaults = array(
			'age'           => 0,
			'post_types'    => array(),
			'post_ids'      => '',
			'exclude_terms' => '',
		);

		$args  = wp_parse_args( $args, $defaults );
		$where = $this->get_discussion_where_sql( $type, $action, $args );

		if ( false === $where ) {
			return $this->failed_discussion_result( 0, __( 'Invalid discussion operation.', 'autoclose' ) );
		}

		$new_status = 'close' === $action ? 'closed' : 'open';
		$last_id    = 0;
		$affected   = 0;

		do {
			$batch_where = $where;
			if ( $last_id > 0 ) {
				$batch_where .= $wpdb->prepare( ' AND ID > %d', $last_id );
			}

			$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT ID FROM {$wpdb->posts} {$batch_where} ORDER BY ID ASC LIMIT %d",
					self::EDIT_BATCH_SIZE
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				return $this->failed_discussion_result( $affected, $this->get_database_error( __( 'The discussion selection failed.', 'autoclose' ) ) );
			}

			$post_ids    = array_map( 'intval', $post_ids );
			$batch_count = count( $post_ids );
			if ( empty( $post_ids ) ) {
				break;
			}

			$sql = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				"UPDATE {$wpdb->posts} SET {$type}_status = %s WHERE ID IN (" . implode( ',', $post_ids ) . ')',
				$new_status
			);
			$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

			if ( false === $result ) {
				return $this->failed_discussion_result( $affected, $this->get_database_error( __( 'The discussion update failed.', 'autoclose' ) ) );
			}

			$affected += (int) $result;
			$this->clean_discussion_caches( $post_ids );
			$last_id = (int) end( $post_ids );
		} while ( self::EDIT_BATCH_SIZE === $batch_count );

		return array(
			'status'   => 'success',
			'affected' => $affected,
			'errors'   => array(),
		);
	}

	/**
	 * Build a failed discussion operation result.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $affected Number of successful updates.
	 * @param string $error    Error message.
	 * @return array Discussion operation result.
	 */
	private function failed_discussion_result( int $affected, string $error ): array {
		return array(
			'status'   => $affected > 0 ? 'partial' : 'failed',
			'affected' => $affected,
			'errors'   => array( $error ),
		);
	}

	/**
	 * Return a database error or fallback message.
	 *
	 * @since 3.2.0
	 *
	 * @param string $fallback Fallback message.
	 * @return string Error message.
	 */
	private function get_database_error( string $fallback ): string {
		global $wpdb;

		return ! empty( $wpdb->last_error ) ? $wpdb->last_error : $fallback;
	}

	/**
	 * Preview a discussion operation without changing content.
	 *
	 * @since 3.2.0
	 *
	 * @param string $type         Discussion type: comment or ping.
	 * @param string $action       Operation: open or close.
	 * @param array  $args         Operation arguments.
	 * @param int    $sample_limit Maximum sample rows.
	 * @return array Preview data.
	 */
	public function preview_discussions( string $type, string $action, array $args = array(), int $sample_limit = 10 ): array {
		global $wpdb;

		$where = $this->get_discussion_where_sql( $type, $action, wp_parse_args( $args, $this->get_discussion_defaults() ) );

		if ( false === $where ) {
			return array(
				'status' => 'failed',
				'errors' => array( __( 'Invalid discussion preview arguments.', 'autoclose' ) ),
			);
		}

		$sample_limit = max( 1, min( 100, $sample_limit ) );
		$count        = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $count && ! empty( $wpdb->last_error ) ) {
			return array(
				'status' => 'failed',
				'errors' => array( $wpdb->last_error ),
			);
		}

		$sample = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT ID, post_title, post_type, post_date, comment_status, ping_status FROM {$wpdb->posts} {$where} ORDER BY ID ASC LIMIT %d",
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

		$type_ages = is_array( $args['type_ages'] ?? null ) ? $args['type_ages'] : null;

		return array(
			'status'          => 'success',
			'action'          => $action,
			'type'            => $type,
			'affected'        => (int) $count,
			'age_days'        => null === $type_ages ? max( 0, (int) ( $args['age'] ?? 0 ) ) : null,
			'cutoff_gmt'      => null === $type_ages ? $this->get_cutoff( (int) ( $args['age'] ?? 0 ) ) : null,
			'type_ages'       => $type_ages,
			'count_threshold' => 'comment' === $type ? max( 0, (int) ( $args['count_threshold'] ?? 0 ) ) : 0,
			'post_types'      => array_values( (array) ( $args['post_types'] ?? array() ) ),
			'post_ids'        => wp_parse_id_list( $args['post_ids'] ?? '' ),
			'exclude_terms'   => wp_parse_id_list( $args['exclude_terms'] ?? '' ),
			'sample'          => is_array( $sample ) ? $sample : array(),
			'errors'          => array(),
		);
	}

	/**
	 * Preview every configured comments operation.
	 *
	 * @since 3.2.0
	 *
	 * @param int $sample_limit Maximum sample rows per operation.
	 * @return array Preview data.
	 */
	public function get_preview( int $sample_limit = 10 ): array {
		$operations = array();
		$errors     = array();
		$settings   = array(
			'comment_post_types'    => Helpers::parse_post_types( Options_API::get_option( 'comment_post_types' ) ),
			'pbtb_post_types'       => Helpers::parse_post_types( Options_API::get_option( 'pbtb_post_types' ) ),
			'comment_exclude_terms' => Options_API::get_option( 'comment_exclude_term_ids' ),
			'pbtb_exclude_terms'    => Options_API::get_option( 'pbtb_exclude_term_ids' ),
		);

		if ( Options_API::get_option( 'close_comment' ) ) {
			$operations['comments_close'] = $this->preview_discussions(
				'comment',
				'close',
				array(
					'type_ages'       => $this->get_type_ages( 'comment', $settings['comment_post_types'] ),
					'count_threshold' => $this->get_count_threshold(),
					'post_types'      => $settings['comment_post_types'],
					'exclude_terms'   => $settings['comment_exclude_terms'],
				),
				$sample_limit
			);
		}

		if ( Options_API::get_option( 'close_pbtb' ) ) {
			$operations['pings_close'] = $this->preview_discussions(
				'ping',
				'close',
				array(
					'type_ages'     => $this->get_type_ages( 'ping', $settings['pbtb_post_types'] ),
					'post_types'    => $settings['pbtb_post_types'],
					'exclude_terms' => $settings['pbtb_exclude_terms'],
				),
				$sample_limit
			);
		}

		if ( ! empty( Options_API::get_option( 'comment_pids' ) ) ) {
			$operations['comments_open'] = $this->preview_discussions(
				'comment',
				'open',
				array( 'post_ids' => Options_API::get_option( 'comment_pids' ) ),
				$sample_limit
			);
		}

		if ( ! empty( Options_API::get_option( 'pbtb_pids' ) ) ) {
			$operations['pings_open'] = $this->preview_discussions(
				'ping',
				'open',
				array( 'post_ids' => Options_API::get_option( 'pbtb_pids' ) ),
				$sample_limit
			);
		}

		foreach ( $operations as $operation ) {
			$errors = array_merge( $errors, array_values( (array) ( $operation['errors'] ?? array() ) ) );
		}

		return array(
			'status'     => empty( $errors ) ? ( empty( $operations ) ? 'skipped' : 'success' ) : 'failed',
			'operations' => $operations,
			'errors'     => $errors,
		);
	}

	/**
	 * Get the default arguments for discussion queries.
	 *
	 * @since 3.2.0
	 *
	 * @return array Query defaults.
	 */
	private function get_discussion_defaults(): array {
		return array(
			'age'             => 0,
			'type_ages'       => null,
			'count_threshold' => 0,
			'post_types'      => array(),
			'post_ids'        => '',
			'exclude_terms'   => '',
		);
	}

	/**
	 * Build the eligibility WHERE clause used by execution and preview.
	 *
	 * @since 3.2.0
	 *
	 * @param string $type   Discussion type.
	 * @param string $action Operation.
	 * @param array  $args   Query arguments.
	 * @return string|false Prepared WHERE clause, or false for invalid input.
	 */
	private function get_discussion_where_sql( string $type, string $action, array $args ) {
		global $wpdb;

		if ( ! in_array( $type, array( 'comment', 'ping' ), true ) || ! in_array( $action, array( 'open', 'close' ), true ) ) {
			return false;
		}

		$old_statuses = 'close' === $action ? array( 'open' ) : array( 'closed', 'close' );
		$statuses     = array_map(
			static function ( $status ) {
				return "'" . esc_sql( $status ) . "'";
			},
			$old_statuses
		);
		$where        = array( "{$type}_status IN (" . implode( ', ', $statuses ) . ')' );
		$type_ages    = is_array( $args['type_ages'] ?? null ) ? $args['type_ages'] : null;

		if ( null !== $type_ages ) {
			$count_threshold = 'comment' === $type ? max( 0, (int) ( $args['count_threshold'] ?? 0 ) ) : 0;
			$where[]         = $this->get_eligibility_where( $type_ages, $count_threshold );
		} else {
			$age = max( 0, (int) ( $args['age'] ?? 0 ) );

			if ( $age > 0 ) {
				$where[] = $wpdb->prepare( 'post_date_gmt < %s', $this->get_cutoff( $age ) );
			}

			if ( ! empty( $args['post_types'] ) ) {
				$post_types = array_map( 'sanitize_key', wp_parse_list( $args['post_types'] ) );
				$post_types = array_filter( $post_types );

				if ( ! empty( $post_types ) ) {
					$quoted_post_types = array_map(
						static function ( $post_type ) {
							return "'" . esc_sql( $post_type ) . "'";
						},
						$post_types
					);
					$where[]           = 'post_type IN (' . implode( ', ', $quoted_post_types ) . ')';
				}
			}
		}

		$post_ids = wp_parse_id_list( $args['post_ids'] ?? '' );
		if ( ! empty( $post_ids ) ) {
			$where[] = 'ID IN (' . implode( ',', $post_ids ) . ')';
		}

		$term_ids = wp_parse_id_list( $args['exclude_terms'] ?? '' );
		if ( ! empty( $term_ids ) ) {
			$where[] = "ID NOT IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', $term_ids ) . '))';
		}

		if ( 'close' === $action && 'comment' === $type ) {
			$where[] = "ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_acc_reopen_until' AND CAST(meta_value AS UNSIGNED) > UNIX_TIMESTAMP())";
		}

		return 'WHERE ' . implode( ' AND ', $where );
	}

	/**
	 * Build a post-type eligibility clause from per-post-type effective ages
	 * and an optional approved-comment count threshold.
	 *
	 * A post type is eligible when its age condition is met OR, for comments,
	 * when the approved-comment count threshold is met. A post type whose age
	 * is disabled (null) and that the count threshold doesn't cover is excluded
	 * entirely. A post matching both conditions is still selected once, since
	 * this only builds a WHERE clause for a single UPDATE per post.
	 *
	 * @since 3.2.0
	 *
	 * @param array<string, int|null> $type_ages       Post type to effective age, null meaning disabled.
	 * @param int                     $count_threshold  Approved-comment count threshold. Zero disables it.
	 * @return string SQL clause. `1=0` when nothing is eligible.
	 */
	private function get_eligibility_where( array $type_ages, int $count_threshold = 0 ): string {
		global $wpdb;

		$groups = array();

		foreach ( $type_ages as $post_type => $age ) {
			if ( null === $age ) {
				continue;
			}

			$cutoff                         = $this->get_cutoff( $age );
			$key                            = null === $cutoff ? '_immediate' : $cutoff;
			$groups[ $key ]['cutoff']       = $cutoff;
			$groups[ $key ]['post_types'][] = sanitize_key( (string) $post_type );
		}

		$clauses = array();

		foreach ( $groups as $group ) {
			$quoted_post_types = array_map(
				static function ( $post_type ) {
					return "'" . esc_sql( $post_type ) . "'";
				},
				$group['post_types']
			);

			$clause = 'post_type IN (' . implode( ', ', $quoted_post_types ) . ')';

			if ( null !== $group['cutoff'] ) {
				$clause .= $wpdb->prepare( ' AND post_date_gmt < %s', $group['cutoff'] );
			}

			$clauses[] = '(' . $clause . ')';
		}

		if ( $count_threshold > 0 && ! empty( $type_ages ) ) {
			$quoted_post_types = array_map(
				static function ( $post_type ) {
					return "'" . esc_sql( sanitize_key( (string) $post_type ) ) . "'";
				},
				array_keys( $type_ages )
			);

			$clauses[] = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'(post_type IN (' . implode( ', ', $quoted_post_types ) . ") AND ( SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = {$wpdb->posts}.ID AND comment_approved = '1' AND comment_type IN ( '', 'comment' ) ) >= %d)",
				$count_threshold
			);
		}

		if ( empty( $clauses ) ) {
			return '1=0';
		}

		return '(' . implode( ' OR ', $clauses ) . ')';
	}

	/**
	 * Return a GMT cutoff for an age, or null when age-based filtering is off.
	 *
	 * @since 3.2.0
	 *
	 * @param int $age Age in days.
	 * @return string|null GMT cutoff.
	 */
	private function get_cutoff( int $age ) {
		$age = max( 0, $age );

		return $age > 0 ? gmdate( 'Y-m-d H:i:s', time() - ( $age * DAY_IN_SECONDS ) ) : null;
	}

	/**
	 * Invalidate caches for posts affected by a bulk status update.
	 *
	 * @since 3.2.0
	 *
	 * @param array<int, int> $post_ids Affected post IDs.
	 */
	private function clean_discussion_caches( array $post_ids ): void {
		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'delete_multiple' ) ) {
			$post_parent_keys = array_map(
				static function ( $post_id ) {
					return 'post_parent:' . (string) $post_id;
				},
				$post_ids
			);

			wp_cache_delete_multiple( $post_ids, 'posts' );
			wp_cache_delete_multiple( $post_parent_keys, 'posts' );
			wp_cache_delete_multiple( $post_ids, 'post_meta' );
			$this->fire_clean_post_cache_actions( $post_ids );
			wp_cache_set_posts_last_changed();
			return;
		}

		foreach ( $post_ids as $post_id ) {
			clean_post_cache( $post_id );
		}
	}

	/**
	 * Preserve the clean_post_cache hook for bulk discussion updates.
	 *
	 * The bulk path above replaces clean_post_cache()'s cache work, but the
	 * action is also used by page-cache purgers and search-index integrations.
	 * Load the affected posts in one query only when an integration is listening.
	 *
	 * @since 3.2.0
	 *
	 * @param array<int, int> $post_ids Affected post IDs.
	 */
	private function fire_clean_post_cache_actions( array $post_ids ): void {
		global $wpdb;

		if ( false === has_action( 'clean_post_cache' ) ) {
			return;
		}

		$post_id_list = implode( ',', array_map( 'absint', $post_ids ) );
		$posts        = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT * FROM {$wpdb->posts} WHERE ID IN ({$post_id_list})", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			OBJECT
		);

		foreach ( $posts as $post ) {
			do_action( 'clean_post_cache', (int) $post->ID, new \WP_Post( $post ) );
		}
	}

	/**
	 * Open comments.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function open_comments( $args = array() ) {
		return $this->edit_discussions( 'comment', 'open', $args );
	}

	/**
	 * Close comments.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function close_comments( $args = array() ) {
		return $this->edit_discussions( 'comment', 'close', $args );
	}

	/**
	 * Open pingbacks/trackbacks.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function open_pingbacks( $args = array() ) {
		return $this->edit_discussions( 'ping', 'open', $args );
	}

	/**
	 * Close pingbacks/trackbacks.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args Array of arguments.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function close_pingbacks( $args = array() ) {
		return $this->edit_discussions( 'ping', 'close', $args );
	}

	/**
	 * Delete pingbacks/trackbacks.
	 *
	 * @since 3.0.0
	 *
	 * @param array|string $post_ids Optional post IDs to limit the deletion.
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function delete_pingbacks( $post_ids = array() ) {
		$result = $this->delete_pingbacks_result( $post_ids );

		return 'success' === $result['status'] ? $result['deleted'] : false;
	}

	/**
	 * Delete pingbacks/trackbacks and preserve partial progress.
	 *
	 * @since 3.2.0
	 *
	 * @param array|string $post_ids Optional post IDs to limit the deletion.
	 * @return array Deletion result.
	 */
	public function delete_pingbacks_result( $post_ids = array() ): array {
		global $wpdb;

		$ids   = wp_parse_id_list( $post_ids );
		$where = "WHERE comment_type IN ('pingback', 'trackback')";

		if ( ! empty( $ids ) ) {
			$where .= ' AND comment_post_ID IN (' . implode( ',', $ids ) . ')';
		}

		$comments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT comment_ID, comment_post_ID FROM {$wpdb->comments} {$where}",
			ARRAY_A
		);

		if ( null === $comments && ! empty( $wpdb->last_error ) ) {
			return array(
				'status'  => 'failed',
				'deleted' => 0,
				'scanned' => 0,
				'errors'  => array( $this->get_database_error( __( 'Pingback/trackback deletion failed.', 'autoclose' ) ) ),
			);
		}

		$deleted           = 0;
		$failed            = 0;
		$affected_post_ids = array();
		if ( $comments ) {
			foreach ( $comments as $comment ) {
				if ( wp_delete_comment( $comment['comment_ID'], true ) ) {
					++$deleted;
					$affected_post_ids[] = $comment['comment_post_ID'];
				} else {
					++$failed;
				}
			}
			$affected_post_ids = array_unique( $affected_post_ids );
			foreach ( $affected_post_ids as $post_id ) {
				clean_post_cache( $post_id );
			}
		}

		$errors = array();
		if ( $failed > 0 ) {
			$errors[] = sprintf(
			/* translators: 1: Number of pingbacks/trackbacks. */
				_n( '%d pingback or trackback could not be deleted.', '%d pingbacks or trackbacks could not be deleted.', $failed, 'autoclose' ),
				$failed
			);
		}

		return array(
			'status'  => empty( $errors ) ? 'success' : ( $deleted > 0 ? 'partial' : 'failed' ),
			'deleted' => $deleted,
			'scanned' => count( $comments ),
			'errors'  => $errors,
		);
	}

	/**
	 * Preview pingback/trackback deletion without changing content.
	 *
	 * @since 3.2.0
	 *
	 * @param array|string $post_ids     Optional post IDs to limit the preview.
	 * @param int          $sample_limit Maximum sample rows.
	 * @return array Preview data.
	 */
	public function preview_pingbacks( $post_ids = array(), int $sample_limit = 10 ): array {
		global $wpdb;

		$ids          = wp_parse_id_list( $post_ids );
		$sample_limit = max( 1, min( 100, $sample_limit ) );
		$where        = "WHERE comment_type IN ('pingback', 'trackback')";

		if ( ! empty( $ids ) ) {
			$where .= ' AND comment_post_ID IN (' . implode( ',', $ids ) . ')';
		}

		$count = $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} {$where}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $count && ! empty( $wpdb->last_error ) ) {
			return array(
				'status' => 'failed',
				'errors' => array( $wpdb->last_error ),
			);
		}

		$sample = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT comment_ID, comment_post_ID, comment_type, comment_date FROM {$wpdb->comments} {$where} ORDER BY comment_ID ASC LIMIT %d",
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
			'affected' => (int) $count,
			'post_ids' => $ids,
			'sample'   => is_array( $sample ) ? $sample : array(),
			'errors'   => array(),
		);
	}
}
