<?php

namespace Quinrel\WordPress\Modules\ContentAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans, discovers, and aggregates Advanced Custom Fields (ACF), Meta Box,
 * and native post meta fields for SEO, Readability, and Schema binding.
 */
class CustomFieldScanner {

	public const OPTION_SEO_FIELDS = 'quinrel_custom_fields_for_seo';

	/**
	 * Discovers all text-bearing custom fields for a specific post.
	 *
	 * @return array<int, array<string, string>> Array of field definitions ['name', 'label', 'type', 'source', 'preview'].
	 */
	public static function discoverFields( int $postId ): array {
		$discovered = array();
		$seenKeys   = array();

		// 1. ACF Integration
		if ( function_exists( 'get_field_objects' ) ) {
			$acfFields = get_field_objects( $postId );
			if ( is_array( $acfFields ) ) {
				foreach ( $acfFields as $name => $field ) {
					if ( empty( $name ) || isset( $seenKeys[ $name ] ) ) {
						continue;
					}
					$type = $field['type'] ?? 'text';
					// Skip purely non-textual assets unless they carry text descriptions
					if ( in_array( $type, array( 'image', 'file', 'relationship', 'post_object', 'user', 'taxonomy' ), true ) ) {
						continue;
					}
					$seenKeys[ $name ] = true;
					$rawVal            = $field['value'] ?? '';
					$preview           = is_scalar( $rawVal ) ? wp_strip_all_tags( (string) $rawVal ) : '';
					$discovered[]      = array(
						'name'    => (string) $name,
						'label'   => ! empty( $field['label'] ) ? (string) $field['label'] : (string) $name,
						'type'    => (string) $type,
						'source'  => 'ACF',
						'preview' => mb_substr( $preview, 0, 80 ),
					);
				}
			}
		}

		// 2. Native Post Meta
		$meta = get_post_custom( $postId );
		if ( is_array( $meta ) ) {
			foreach ( $meta as $key => $values ) {
				if ( empty( $key ) || isset( $seenKeys[ $key ] ) ) {
					continue;
				}
				// Skip internal WordPress or Quinrel private meta (starts with _)
				if ( str_starts_with( $key, '_' ) ) {
					continue;
				}
				$firstVal = is_array( $values ) ? reset( $values ) : $values;
				if ( ! is_scalar( $firstVal ) ) {
					continue;
				}
				$preview = wp_strip_all_tags( (string) $firstVal );
				if ( '' === trim( $preview ) ) {
					continue;
				}
				$seenKeys[ $key ] = true;
				$discovered[]     = array(
					'name'    => (string) $key,
					'label'   => ucwords( str_replace( array( '-', '_' ), ' ', $key ) ),
					'type'    => 'custom_field',
					'source'  => 'Post Meta',
					'preview' => mb_substr( $preview, 0, 80 ),
				);
			}
		}

		/**
		 * Filter discovered custom fields for a post.
		 *
		 * @param array<int, array<string, string>> $discovered
		 * @param int $postId
		 */
		return apply_filters( 'quinrel_discovered_custom_fields', $discovered, $postId );
	}

	/**
	 * Returns the configured custom field names whitelisted for SEO and Readability analysis.
	 *
	 * @return array<int, string>
	 */
	public static function getConfiguredSeoFields(): array {
		$saved = get_option( self::OPTION_SEO_FIELDS, array() );
		if ( is_string( $saved ) ) {
			$saved = json_decode( $saved, true );
		}
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		/**
		 * Filters the active custom fields evaluated for SEO content analysis.
		 *
		 * @param array<int, string> $saved
		 */
		return apply_filters( 'quinrel_seo_analyzed_custom_fields', $saved );
	}

	/**
	 * Sets the custom fields whitelisted for SEO and Readability analysis.
	 *
	 * @param array<int, string> $fieldNames
	 */
	public static function setConfiguredSeoFields( array $fieldNames ): bool {
		$sanitized = array_values(
			array_filter(
				array_map( 'sanitize_key', $fieldNames ),
				static fn( $k ) => '' !== $k
			)
		);
		return update_option( self::OPTION_SEO_FIELDS, $sanitized );
	}

	/**
	 * Aggregates post content with configured custom fields into a single text body.
	 */
	public static function getAggregatedContent( int $postId ): string {
		$post        = get_post( $postId );
		$baseContent = $post ? (string) $post->post_content : '';

		$fields = self::getConfiguredSeoFields();
		if ( empty( $fields ) ) {
			return $baseContent;
		}

		$parts = array( $baseContent );

		foreach ( $fields as $fieldName ) {
			$val = self::getFieldValue( $postId, $fieldName );
			if ( ! empty( $val ) ) {
				$parts[] = $val;
			}
		}

		return implode( "\n\n", array_filter( $parts ) );
	}

	/**
	 * Extracts a clean text value for a given field name on a post.
	 * Supports ACF fields, flexible content, repeaters, and raw post meta.
	 */
	public static function getFieldValue( int $postId, string $fieldName ): string {
		// Try ACF first
		if ( function_exists( 'get_field' ) ) {
			$acfVal = get_field( $fieldName, $postId, false );
			if ( ! empty( $acfVal ) ) {
				return self::flattenValue( $acfVal );
			}
		}

		// Fallback to post meta
		$metaVal = get_post_meta( $postId, $fieldName, true );
		if ( ! empty( $metaVal ) ) {
			return self::flattenValue( $metaVal );
		}

		return '';
	}

	/**
	 * Recursively flattens complex structures (like ACF Flexible Content, Repeaters, Arrays)
	 * into a single clean text string for textual analysis.
	 *
	 * @param mixed $value
	 */
	public static function flattenValue( $value ): string {
		if ( is_string( $value ) || is_numeric( $value ) ) {
			return trim( (string) $value );
		}

		if ( is_array( $value ) ) {
			$strings = array();
			foreach ( $value as $item ) {
				$flat = self::flattenValue( $item );
				if ( '' !== $flat ) {
					$strings[] = $flat;
				}
			}
			return implode( ' ', $strings );
		}

		return '';
	}
}
