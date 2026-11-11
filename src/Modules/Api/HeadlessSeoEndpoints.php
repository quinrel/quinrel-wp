<?php

namespace Quinrel\WordPress\Modules\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HeadlessSeoEndpoints
 *
 * Provides clean REST endpoints for headless WordPress deployments:
 *   GET  /quinrel/v1/seo/{post_id}  — all SEO meta for a post
 *   PUT  /quinrel/v1/seo/{post_id}  — update SEO meta
 *   GET  /quinrel/v1/seo/global     — site-wide SEO defaults
 *
 * BL-027
 */
class HeadlessSeoEndpoints {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		// GET/PUT /seo/{post_id}
		register_rest_route(
			'quinrel/v1',
			'/seo/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getPostSeo' ),
					'permission_callback' => '__return_true',  // public read — SEO data is public
					'args'                => array( 'post_id' => array( 'sanitize_callback' => 'absint' ) ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'updatePostSeo' ),
					'permission_callback' => fn( \WP_REST_Request $request ) => current_user_can( 'edit_post', (int) $request->get_param( 'post_id' ) ),
					'args'                => array(
						'post_id' => array(
							'sanitize_callback' => 'absint',
							'validate_callback' => fn( $value ) => (int) $value > 0,
						),
					),
				),
			)
		);

		// GET /seo/global
		register_rest_route(
			'quinrel/v1',
			'/seo/global',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getGlobalSeo' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// ── GET /seo/{post_id} ────────────────────────────────────────────────────

	public function getPostSeo( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$postId = (int) $request->get_param( 'post_id' );
		$post   = get_post( $postId );

		if ( ! $post || $post->post_status !== 'publish' ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'quinrel' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->buildPostSeoPayload( $postId ) );
	}

	private function buildPostSeoPayload( int $postId ): array {
		$post   = get_post( $postId );
		$schema = ( new \Quinrel\WordPress\Modules\Schema\SchemaGenerator() )->getSchemaForPost( $postId );

		$rawMetaTitle = (string) get_post_meta( $postId, '_quinrel_meta_title', true );
		$metaTitle    = '' !== trim( $rawMetaTitle )
			? \Quinrel\WordPress\Modules\Seo\SmartTagResolver::resolve( $rawMetaTitle, $postId )
			: '';
		if ( '' === $metaTitle ) {
			$metaTitle = (string) $post->post_title;
		}

		$rawMetaDesc = (string) get_post_meta( $postId, '_quinrel_meta_description', true );
		$metaDesc    = '' !== trim( $rawMetaDesc )
			? \Quinrel\WordPress\Modules\Seo\SmartTagResolver::resolve( $rawMetaDesc, $postId )
			: '';
		if ( '' === $metaDesc ) {
			$excerpt  = (string) ( $post->post_excerpt ?? '' );
			$metaDesc = '' !== trim( $excerpt )
				? \Quinrel\WordPress\Modules\Seo\SmartTagResolver::cleanContentSnippet( $excerpt, 25 )
				: \Quinrel\WordPress\Modules\Seo\SmartTagResolver::cleanContentSnippet( (string) ( $post->post_content ?? '' ), 25 );
		}

		$ogTitle = (string) get_post_meta( $postId, '_quinrel_og_title', true ) ?: $metaTitle;
		$ogDesc  = (string) get_post_meta( $postId, '_quinrel_og_description', true ) ?: $metaDesc;
		$ogImage = (string) get_post_meta( $postId, '_quinrel_og_image', true );
		if ( '' === $ogImage && function_exists( 'get_the_post_thumbnail_url' ) ) {
			$ogImage = (string) get_the_post_thumbnail_url( $postId, 'large' );
		}

		$twitterTitle = (string) get_post_meta( $postId, '_quinrel_twitter_title', true ) ?: $ogTitle;
		$twitterDesc  = (string) get_post_meta( $postId, '_quinrel_twitter_description', true ) ?: $ogDesc;
		$twitterImage = (string) get_post_meta( $postId, '_quinrel_twitter_image', true ) ?: $ogImage;

		return array(
			'post_id'                => $postId,
			'post_type'              => $post->post_type,
			'url'                    => get_permalink( $postId ),
			'meta_title'             => $metaTitle,
			'meta_description'       => $metaDesc,
			'canonical_url'          => (string) get_post_meta( $postId, '_quinrel_canonical_url', true ) ?: get_permalink( $postId ),
			'robots'                 => array(
				'noindex'  => get_post_meta( $postId, '_quinrel_noindex', true ) === 'yes',
				'nofollow' => get_post_meta( $postId, '_quinrel_nofollow', true ) === 'yes',
			),
			'open_graph'             => array(
				'title'       => $ogTitle,
				'description' => $ogDesc,
				'image'       => $ogImage,
			),
			'twitter_card'           => array(
				'title'       => $twitterTitle,
				'description' => $twitterDesc,
				'image'       => $twitterImage,
			),
			'structured_data'        => $schema ?: null,
			'custom_fields_included' => \Quinrel\WordPress\Modules\ContentAssistant\CustomFieldScanner::getConfiguredSeoFields(),
			'word_count'             => str_word_count( wp_strip_all_tags( \Quinrel\WordPress\Modules\ContentAssistant\CustomFieldScanner::getAggregatedContent( $postId ) ) ),
			'modified'               => $post->post_modified,
		);
	}

	// ── PUT /seo/{post_id} ────────────────────────────────────────────────────

	public function updatePostSeo( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$postId = (int) $request->get_param( 'post_id' );
		$post   = get_post( $postId );

		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'quinrel' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'quinrel' ), array( 'status' => 403 ) );
		}

		$params  = $request->get_json_params();
		$updated = array();

		$stringFields = array(
			'meta_title'       => '_quinrel_meta_title',
			'meta_description' => '_quinrel_meta_description',
			'focus_keyword'    => '_quinrel_focus_keyword',
			'canonical_url'    => '_quinrel_canonical_url',
			'schema_type'      => '_quinrel_primary_schema',
			'primary_schema'   => '_quinrel_primary_schema',
		);

		foreach ( $stringFields as $param => $metaKey ) {
			if ( array_key_exists( $param, $params ) ) {
				update_post_meta( $postId, $metaKey, sanitize_text_field( $params[ $param ] ) );
				$updated[] = $param;
			}
		}

		if ( isset( $params['robots']['noindex'] ) ) {
			update_post_meta( $postId, '_quinrel_noindex', $params['robots']['noindex'] ? 'yes' : 'no' );
			$updated[] = 'robots.noindex';
		}
		if ( isset( $params['robots']['nofollow'] ) ) {
			update_post_meta( $postId, '_quinrel_nofollow', $params['robots']['nofollow'] ? 'yes' : 'no' );
			$updated[] = 'robots.nofollow';
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'updated_fields' => $updated,
				'post_id'        => $postId,
			)
		);
	}

	// ── GET /seo/global ───────────────────────────────────────────────────────

	public function getGlobalSeo( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'site_name'        => get_bloginfo( 'name' ),
				'site_description' => get_bloginfo( 'description' ),
				'site_url'         => site_url(),
				'home_url'         => home_url(),
				'language'         => get_bloginfo( 'language' ),
				'charset'          => get_bloginfo( 'charset' ),
				'sitemap_url'      => home_url( '/sitemap.xml' ),
				'robots_txt_url'   => home_url( '/robots.txt' ),
				'llms_txt_url'     => home_url( '/llms.txt' ),
				'schema_defaults'  => array(
					'author'       => get_bloginfo( 'name' ),
					'publisher'    => get_bloginfo( 'name' ),
					'logo'         => get_site_icon_url(),
					'type_default' => get_option( 'quinrel_default_schema_type', 'Article' ),
				),
				'version'          => QUINREL_VERSION ?? '1.0.1',
			)
		);
	}
}
