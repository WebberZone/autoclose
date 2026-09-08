<?php
/**
 * Shared WP-CLI command helpers.
 *
 * @package AutoClose
 */

namespace WebberZone\AutoClose\CLI;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Common formatting and argument helpers for AutoClose commands.
 *
 * @since 3.2.0
 */
abstract class Base_Command {

	/**
	 * Return the requested output format.
	 *
	 * @since 3.2.0
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return string Output format.
	 */
	protected function get_format( array $assoc_args ): string {
		$format = isset( $assoc_args['format'] ) ? strtolower( (string) $assoc_args['format'] ) : 'table';

		if ( ! in_array( $format, array( 'table', 'json', 'csv' ), true ) ) {
			\WP_CLI::error( __( 'The format must be table, json, or csv.', 'autoclose' ), CLI::EXIT_INVALID );
		}

		return $format;
	}

	/**
	 * Output structured data in a WP-CLI format.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $data   Structured output.
	 * @param string $format Output format.
	 * @param array  $rows   Human-readable rows.
	 * @param array  $fields Table/CSV fields.
	 */
	protected function output( array $data, string $format, array $rows, array $fields = array( 'Field', 'Value' ) ): void {
		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $rows, $fields );
	}

	/**
	 * Create a field/value output row.
	 *
	 * @since 3.2.0
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return array Output row.
	 */
	protected function row( string $field, $value ): array {
		return array(
			'Field' => $field,
			'Value' => $this->format_value( $value ),
		);
	}

	/**
	 * Format a value for human-readable output.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $value Value.
	 * @return string Formatted value.
	 */
	protected function format_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'Yes' : 'No';
		}
		if ( null === $value || '' === $value ) {
			return 'None';
		}
		if ( is_array( $value ) ) {
			return empty( $value ) ? 'None' : wp_json_encode( $value );
		}

		return (string) $value;
	}

	/**
	 * Parse positive integer IDs from positional arguments and --post-ids.
	 *
	 * @since 3.2.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return array<int, int> Parsed IDs.
	 */
	protected function parse_ids( array $args, array $assoc_args = array() ): array {
		$values = $args;

		if ( isset( $assoc_args['post-ids'] ) ) {
			$values[] = $assoc_args['post-ids'];
		}

		return $this->parse_integer_list( $values, __( 'Post IDs must be positive integers.', 'autoclose' ) );
	}

	/**
	 * Parse positive integer IDs from a value or list of values.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed  $values        Value or values to parse.
	 * @param string $error_message Error message for invalid values.
	 * @return array<int, int> Parsed IDs.
	 */
	protected function parse_integer_list( $values, string $error_message ): array {
		$values = is_array( $values ) ? $values : array( $values );
		$ids    = array();

		foreach ( $values as $value ) {
			$parts = preg_split( '/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );

			foreach ( $parts as $part ) {
				if ( ! ctype_digit( $part ) || (int) $part < 1 ) {
					\WP_CLI::error( $error_message, CLI::EXIT_INVALID );
				}

				$ids[] = (int) $part;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Parse a non-negative integer option.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $key        Option key.
	 * @param int    $default_value Default value.
	 * @return int Parsed value.
	 */
	protected function get_non_negative_int( array $assoc_args, string $key, int $default_value = 0 ): int {
		if ( ! isset( $assoc_args[ $key ] ) || '' === (string) $assoc_args[ $key ] ) {
			return $default_value;
		}

		$value = (string) $assoc_args[ $key ];
		if ( ! ctype_digit( $value ) ) {
			\WP_CLI::error( sprintf( __( 'The %s value must be a non-negative integer.', 'autoclose' ), $key ), CLI::EXIT_INVALID );
		}

		return (int) $value;
	}

	/**
	 * Parse a comma-separated list of post types.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $key        Option key.
	 * @return array<int, string> Post types.
	 */
	protected function get_post_types( array $assoc_args, string $key = 'post-types' ): array {
		if ( ! isset( $assoc_args[ $key ] ) ) {
			return array();
		}

		$post_types = array_map( 'sanitize_key', wp_parse_list( (string) $assoc_args[ $key ] ) );

		return array_values( array_filter( $post_types ) );
	}

	/**
	 * Return public post types that support comments.
	 *
	 * @since 3.2.0
	 *
	 * @return array<int, string> Default discussion post types.
	 */
	protected function get_discussion_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$default    = array();

		foreach ( $post_types as $post_type ) {
			if ( ! in_array( $post_type->name, array( 'attachment', 'revision' ), true ) && post_type_supports( $post_type->name, 'comments' ) ) {
				$default[] = $post_type->name;
			}
		}

		return $default;
	}

	/**
	 * Get IDs from a preview sample.
	 *
	 * @since 3.2.0
	 *
	 * @param array $sample Sample rows.
	 * @return string Sample IDs.
	 */
	protected function get_sample_ids( array $sample ): string {
		$ids = array();

		foreach ( $sample as $row ) {
			if ( isset( $row['ID'] ) ) {
				$ids[] = (string) $row['ID'];
			} elseif ( isset( $row['comment_ID'] ) ) {
				$ids[] = (string) $row['comment_ID'];
			}
		}

		return empty( $ids ) ? 'None' : implode( ', ', $ids );
	}

	/**
	 * Format a Unix timestamp in the site's timezone.
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $timestamp Unix timestamp.
	 * @return string Formatted timestamp.
	 */
	protected function format_timestamp( $timestamp ): string {
		return empty( $timestamp ) ? 'Never' : wp_date( 'Y-m-d H:i:s T', (int) $timestamp );
	}

	/**
	 * Return a GMT age cutoff.
	 *
	 * @since 3.2.0
	 *
	 * @param int $age Age in days.
	 * @return string|null GMT cutoff, or null when age filtering is off.
	 */
	protected function get_cutoff( int $age ) {
		return $age > 0 ? gmdate( 'Y-m-d H:i:s', time() - ( $age * DAY_IN_SECONDS ) ) : null;
	}

	/**
	 * Return a standard operation summary.
	 *
	 * @since 3.2.0
	 *
	 * @param string $mode      Run mode.
	 * @param string $outcome   Operation outcome.
	 * @param array  $operation Operation data.
	 * @param array  $extra     Additional data.
	 * @return array Summary.
	 */
	protected function operation_summary( string $mode, string $outcome, array $operation, array $extra = array() ): array {
		$summary = array_merge(
			array(
				'mode'      => $mode,
				'outcome'   => $outcome,
				'blog_id'   => (int) get_current_blog_id(),
				'site_url'  => home_url( '/' ),
				'operation' => $operation,
			),
			$extra
		);

		return $summary;
	}

	/**
	 * Exit with a documented operation code.
	 *
	 * @since 3.2.0
	 *
	 * @param string $outcome Operation outcome.
	 */
	protected function exit_for_outcome( string $outcome ): void {
		if ( 'partial' === $outcome ) {
			\WP_CLI::error( __( 'AutoClose completed partially. Review the reported errors.', 'autoclose' ), CLI::EXIT_PARTIAL );
		}

		if ( 'failed' === $outcome ) {
			\WP_CLI::error( __( 'AutoClose failed. Review the reported errors.', 'autoclose' ), CLI::EXIT_FAILURE );
		}
	}
}
