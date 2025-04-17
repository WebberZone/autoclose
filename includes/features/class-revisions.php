<?php
/**
 * Post revisions management.
 *
 * @package    AutoClose
 */

namespace WebberZone\AutoClose\Features;

use WebberZone\AutoClose\Utilities\Options;

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
	public function process_revisions() {
		if ( Options::get_option( 'delete_revisions' ) ) {
			$this->delete_revisions();
		}
	}

	/**
	 * Delete post revisions.
	 *
	 * @since 3.0.0
	 *
	 * @return int|bool Number of rows affected/selected for all other queries. Boolean false on error.
	 */
	public function delete_revisions() {
		global $wpdb;

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"
			DELETE FROM {$wpdb->posts}
			WHERE post_type = 'revision'
			"
		);

		return $result;
	}

	/**
	 * Sets the number of revisions to keep for a specific post type.
	 *
	 * @since 3.0.0
	 *
	 * @param int      $num  Number of revisions to store.
	 * @param \WP_Post $post Post object.
	 * @return int Number of revisions to keep.
	 */
	public function revisions_to_keep( $num, $post ) {
		$post_type           = $post->post_type;
		$revision_post_types = array_keys( $this->get_revision_post_types() );

		$revisions_to_keep = Options::get_option( "revision_{$post_type}" );

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
				if ( property_exists( $post_type, 'labels' ) && property_exists( $post_type->labels, 'name' ) ) {
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
