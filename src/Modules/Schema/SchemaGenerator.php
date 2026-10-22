<?php

namespace Quinrel\WordPress\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles schema graph building, automated video markup inclusion,
 * and handles global conditional schema templates.
 */
class SchemaGenerator {

	private array $schemaTypes = array();

	public function __construct() {
		$defaultTypes = array(
			new Types\ArticleSchema(),
			new Types\BreadcrumbSchema(),
			new Types\CustomSchema(),
		);

		/**
		 * Filters the active schema type instances for schema generation.
		 *
		 * @param array<int, Contracts\SchemaTypeInterface> $defaultTypes
		 */
		$this->schemaTypes = apply_filters( 'quinrel_schema_types', $defaultTypes );
	}

	/**
	 * Echoes the compiled JSON-LD graph to the footer of the singular post page.
	 */
	public function outputSchema(): void {
		if ( ! is_singular() || is_admin() ) {
			return;
		}

		$postId = get_the_ID();
		if ( ! $postId ) {
			return;
		}

		$schema = $this->getSchemaForPost( $postId );
		if ( empty( $schema ) || empty( $schema['@graph'] ) ) {
			return;
		}

		$json = JsonLd::encode( $schema );
		if ( '' === $json ) {
			return;
		}

		echo "<!-- Quinrel AI-Ready Schema -->\n";
		// JSON_HEX_TAG, JSON_HEX_AMP, and JSON_HEX_APOS already encode the payload. esc_html() would break the JSON.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_get_inline_script_tag(
			$json,
			array(
				'type' => 'application/ld+json',
			)
		);
		echo "<!-- /Quinrel Schema -->\n";
	}

	/**
	 * Compiles the full schema graph for a specific post.
	 */
	public function getSchemaForPost( int $postId ): array {
		$graphs = array();

		// Loop through standard schema types (Article, Breadcrumbs, Custom)
		foreach ( $this->schemaTypes as $type ) {
			if ( $type->isApplicable( $postId ) ) {
				$payload = $type->generate( $postId );
				if ( JsonLd::isListOfNodes( $payload ) ) {
					foreach ( $payload as $node ) {
						$graphs[] = $node;
					}
				} elseif ( ! empty( $payload ) ) {
					$graphs[] = $payload;
				}
			}
		}

		// Task 2.15: Automated VideoObject Schema scraper integration
		$videoObjects = VideoExtractor::extractVideoObjects( $postId );
		if ( ! empty( $videoObjects ) ) {
			foreach ( $videoObjects as $videoObj ) {
				$graphs[] = $videoObj;
			}
		}

		// Gutenberg Block-Level Schema extraction (FAQ, HowTo, Review, TOC)
		$blockSchemas = \Quinrel\WordPress\Modules\Blocks\BlockSchemaExtractor::extractBlockSchemas( $postId );
		if ( ! empty( $blockSchemas ) ) {
			foreach ( $blockSchemas as $blockSchema ) {
				$graphs[] = $blockSchema;
			}
		}

		if ( empty( $graphs ) ) {
			return array();
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graphs,
		);

		$graph = $this->resolveDynamicFieldBindings( $graph, $postId );

		/**
		 * Filters the final compiled JSON-LD graph before output.
		 *
		 * @param array<string, mixed> $graph  Compiled schema graph.
		 * @param int                  $postId Post ID.
		 */
		return apply_filters( 'quinrel_schema_graph', $graph, $postId );
	}

	/**
	 * Resolves effective primary schema based on local or global templates.
	 */
	public static function getEffectivePrimarySchema( int $postId ): string {
		$primarySchema = get_post_meta( $postId, '_quinrel_primary_schema', true );
		if ( ! empty( $primarySchema ) && $primarySchema !== 'none' ) {
			return $primarySchema;
		}

		// Apply global conditional display rules
		$globalRulesJson = get_option( 'quinrel_global_schema_rules' );
		if ( ! empty( $globalRulesJson ) ) {
			$rules = json_decode( $globalRulesJson, true );
			if ( is_array( $rules ) ) {
				$post = get_post( $postId );
				if ( $post ) {
					foreach ( $rules as $rule ) {
						if ( isset( $rule['post_type'] ) && $rule['post_type'] === $post->post_type ) {
							if ( isset( $rule['category'] ) && $rule['category'] !== 'all' ) {
								$cats = wp_get_post_categories( $postId );
								if ( in_array( intval( $rule['category'] ), $cats, true ) ) {
									return $rule['schema_type'];
								}
							} else {
								return $rule['schema_type'];
							}
						}
					}
				}
			}
		}

		return 'none';
	}

	/**
	 * Resolves dynamic ACF or custom post meta references inside schema graphs.
	 *
	 * @param array<string, mixed> $data
	 * @param int                  $postId
	 * @return array<string, mixed>
	 */
	private function resolveDynamicFieldBindings( array $data, int $postId ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->resolveDynamicFieldBindings( $value, $postId );
			} elseif ( is_string( $value ) ) {
				if ( 1 === preg_match( '/^(?:acf|cf):([a-zA-Z0-9_\-]+)$/i', trim( $value ), $matches ) ) {
					$resolved = \Quinrel\WordPress\Modules\Seo\SmartTagResolver::resolveCustomField( $matches[1], $postId );
					if ( '' !== $resolved ) {
						if ( is_numeric( $resolved ) && false === strpos( $resolved, '.' ) ) {
							$data[ $key ] = (int) $resolved;
						} elseif ( is_numeric( $resolved ) ) {
							$data[ $key ] = (float) $resolved;
						} else {
							$data[ $key ] = $resolved;
						}
					}
				} elseif ( false !== strpos( $value, 'acf:' ) || false !== strpos( $value, 'cf:' ) ) {
					$data[ $key ] = \Quinrel\WordPress\Modules\Seo\SmartTagResolver::resolve( $value, $postId );
				}
			}
		}

		return $data;
	}
}
