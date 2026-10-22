<?php

namespace Quinrel\WordPress\Modules\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LlmsTxtGenerator: Serves a machine-readable /llms.txt file for LLM ingestion.
 *
 * The llms.txt standard (proposed by Answer.AI) gives AI assistants
 * a structured overview of the site's content and permission guidelines.
 *
 * BL-033 enhancements:
 * - Auto-populates cornerstone content, Disallow/Allow sections from per-post meta
 * - Manual override mode (stored content served instead of auto-generated)
 * - Cache invalidation on save_post and option changes
 * - Preview REST endpoint
 *
 * @see https://llmstxt.org/
 */
class LlmsTxtGenerator {

	private const CACHE_TRANSIENT       = 'quinrel_llms_txt_content';
	private const FULL_CACHE_TRANSIENT  = 'quinrel_llms_full_txt_content';
	private const CONFIG_OPTION         = 'quinrel_llms_txt_config';
	private const OVERRIDE_OPTION       = 'quinrel_llms_txt_manual_override';
	private const OVERRIDE_CONTENT      = 'quinrel_llms_txt_manual_content';
	private const FULL_OVERRIDE_OPTION  = 'quinrel_llms_full_txt_manual_override';
	private const FULL_OVERRIDE_CONTENT = 'quinrel_llms_full_txt_manual_content';

	public function boot(): void {
		add_action( 'init', array( $this, 'registerRewriteRule' ) );
		add_action( 'template_redirect', array( $this, 'serveFile' ) );
		add_filter( 'query_vars', array( $this, 'registerQueryVar' ) );

		// BL-033: Cache invalidation hooks
		add_action( 'save_post', array( $this, 'invalidateCache' ) );
		add_action( 'trashed_post', array( $this, 'invalidateCache' ) );
		add_action( 'update_option_quinrel_llms_txt_config', array( $this, 'invalidateCache' ) );
	}

	public function invalidateCache(): void {
		delete_transient( self::CACHE_TRANSIENT );
		delete_transient( self::FULL_CACHE_TRANSIENT );
	}

	public function registerRewriteRule(): void {
		add_rewrite_rule( '^llms\\.txt$', 'index.php?quinrel_llms_txt=1', 'top' );
		add_rewrite_rule( '^llms-full\\.txt$', 'index.php?quinrel_llms_txt=full', 'top' );
	}

	public function registerQueryVar( array $vars ): array {
		$vars[] = 'quinrel_llms_txt';
		return $vars;
	}

	public function serveFile(): void {
		$queryVar = get_query_var( 'quinrel_llms_txt' );
		$isFull   = ( 'full' === $queryVar );

		if ( empty( $queryVar ) ) {
			$reqUri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$path   = (string) wp_parse_url( $reqUri, PHP_URL_PATH );
			if ( preg_match( '#/llms-full\.txt$#i', $path ) ) {
				$isFull = true;
			} elseif ( ! preg_match( '#/llms\.txt$#i', $path ) ) {
				return;
			}
		}

		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		$config = (array) get_option( self::CONFIG_OPTION, array() );

		if ( $isFull ) {
			if ( get_option( self::FULL_OVERRIDE_OPTION, false ) ) {
				header( 'X-Quinrel-Cache: BYPASS' );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo (string) get_option( self::FULL_OVERRIDE_CONTENT, '' );
				exit;
			}

			$cachedFull = get_transient( self::FULL_CACHE_TRANSIENT );
			if ( false !== $cachedFull ) {
				header( 'X-Quinrel-Cache: HIT' );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $cachedFull;
				exit;
			}

			$fullContent = $this->buildFullContent( $config );
			set_transient( self::FULL_CACHE_TRANSIENT, $fullContent, 12 * HOUR_IN_SECONDS );
			header( 'X-Quinrel-Cache: MISS' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $fullContent;
			exit;
		}

		// Standard /llms.txt
		if ( get_option( self::OVERRIDE_OPTION, false ) ) {
			header( 'X-Quinrel-Cache: BYPASS' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo (string) get_option( self::OVERRIDE_CONTENT, '' );
			exit;
		}

		$cached = get_transient( self::CACHE_TRANSIENT );
		if ( false !== $cached ) {
			header( 'X-Quinrel-Cache: HIT' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $cached;
			exit;
		}

		$content = $this->buildContent( $config );
		set_transient( self::CACHE_TRANSIENT, $content, 12 * HOUR_IN_SECONDS );
		header( 'X-Quinrel-Cache: MISS' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}

	public function buildContent( array $config ): string {
		$siteName    = get_bloginfo( 'name' );
		$siteUrl     = rtrim( home_url(), '/' );
		$description = $config['description'] ?? get_bloginfo( 'description' );
		$allowIndex  = $config['allow_index'] ?? true;
		$allowTrain  = $config['allow_training'] ?? false;
		$extraNotes  = $config['extra_notes'] ?? '';

		// BL-033: find contact page automatically
		$contactPage = function_exists( 'get_pages' ) ? get_pages(
			array(
				'meta_key' => '_wp_page_template',
				'number'   => 1,
			)
		) : array();
		$contactUrl  = $config['contact_url'] ?? '';
		if ( empty( $contactUrl ) ) {
			$pages      = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'name'           => 'contact',
					'posts_per_page' => 1,
				)
			);
			$contactUrl = ! empty( $pages ) ? get_permalink( $pages[0]->ID ) : get_option( 'admin_email' );
		}

		$output  = "# {$siteName}\n\n";
		$output .= "> {$description}\n\n";
		$output .= "## Site\n\n";
		$output .= "- URL: {$siteUrl}\n";
		$output .= "- Contact: {$contactUrl}\n\n";

		$output .= "## Permissions\n\n";
		$output .= '- Indexing: ' . ( $allowIndex ? 'Allowed' : 'Not Allowed' ) . "\n";
		$output .= '- Training data use: ' . ( $allowTrain ? 'Allowed' : 'Not Allowed' ) . "\n\n";

		// Structured data link
		$output .= "## Structured Data\n\n";
		$output .= "- Schema Map: {$siteUrl}/wp-json/quinrel/v1/schemamap\n";
		$output .= "- Full Digest: {$siteUrl}/llms-full.txt\n\n";

		// BL-033: Cornerstone content (auto-populated)
		$output   .= "## Cornerstone Content\n\n";
		$postTypes = ! empty( $config['post_types'] ) && is_array( $config['post_types'] )
			? array_map( 'sanitize_key', $config['post_types'] )
			: array( 'post', 'page' );

		$cornerstones = get_posts(
			array(
				'post_type'      => $postTypes,
				'post_status'    => 'publish',
				'posts_per_page' => 25,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => '_quinrel_is_cornerstone',
						'value' => 'yes',
					),
				),
			)
		);

		if ( ! empty( $cornerstones ) ) {
			foreach ( $cornerstones as $post ) {
				$excerpt = $this->resolveExcerpt( $post );
				$suffix  = '' !== $excerpt ? ': ' . $excerpt : '';
				$output .= "- [{$post->post_title}](" . get_permalink( $post->ID ) . "){$suffix}\n";
			}
		} else {
			// Fall back to recent posts if no cornerstone content set
			$recent = get_posts(
				array(
					'post_type'      => $postTypes,
					'post_status'    => 'publish',
					'posts_per_page' => 15,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				)
			);
			foreach ( $recent as $post ) {
				$excerpt = $this->resolveExcerpt( $post );
				$suffix  = '' !== $excerpt ? ': ' . $excerpt : '';
				$output .= "- [{$post->post_title}](" . get_permalink( $post->ID ) . "){$suffix}\n";
			}
		}
		$output .= "\n";

		// BL-033: Disallow section (posts explicitly opting out of AI training)
		$disallowed = get_posts(
			array(
				'post_type'      => get_post_types( array( 'public' => true ) ),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'meta_query'     => array(
					array(
						'key'   => '_quinrel_ai_training_allow',
						'value' => 'no',
					),
				),
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $disallowed ) ) {
			$output .= "## Disallow\n\n";
			foreach ( $disallowed as $id ) {
				$output .= '- ' . get_permalink( $id ) . "\n";
			}
			$output .= "\n";
		}

		// BL-033: Allow section (posts explicitly opting into AI training)
		$allowed = get_posts(
			array(
				'post_type'      => get_post_types( array( 'public' => true ) ),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'meta_query'     => array(
					array(
						'key'   => '_quinrel_ai_training_allow',
						'value' => 'yes',
					),
				),
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $allowed ) ) {
			$output .= "## Allow\n\n";
			foreach ( $allowed as $id ) {
				$output .= '- ' . get_permalink( $id ) . "\n";
			}
			$output .= "\n";
		}

		if ( ! empty( $extraNotes ) ) {
			$output .= "## Notes\n\n";
			$output .= $extraNotes . "\n";
		}

		return $output;
	}

	/**
	 * Resolves a concise plain-text excerpt for an article.
	 */
	private function resolveExcerpt( \WP_Post $post ): string {
		$metaDesc = get_post_meta( $post->ID, '_quinrel_meta_description', true );
		if ( ! empty( $metaDesc ) && is_string( $metaDesc ) ) {
			return wp_strip_all_tags( $metaDesc );
		}

		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		$raw = wp_strip_all_tags( (string) $post->post_content );
		if ( '' === $raw ) {
			return '';
		}

		return wp_trim_words( $raw, 24, '...' );
	}

	/**
	 * Builds full synthesized markdown content digest (/llms-full.txt).
	 */
	public function buildFullContent( array $config ): string {
		$siteName    = get_bloginfo( 'name' );
		$siteUrl     = rtrim( home_url(), '/' );
		$description = $config['description'] ?? get_bloginfo( 'description' );

		$output  = "# {$siteName} — Full Content Digest\n\n";
		$output .= "> {$description}\n\n";
		$output .= "Canonical Site URL: {$siteUrl}\n\n";
		$output .= "---\n\n";

		$postTypes = ! empty( $config['post_types'] ) && is_array( $config['post_types'] )
			? array_map( 'sanitize_key', $config['post_types'] )
			: array( 'post', 'page' );

		// Retrieve cornerstone posts first, then recent published posts
		$posts = get_posts(
			array(
				'post_type'      => $postTypes,
				'post_status'    => 'publish',
				'posts_per_page' => 25,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		if ( empty( $posts ) ) {
			$output .= "No published articles available.\n";
			return $output;
		}

		foreach ( $posts as $post ) {
			// Skip posts explicitly opting out of AI training/indexing
			$optOut = get_post_meta( $post->ID, '_quinrel_ai_training_allow', true );
			if ( 'no' === $optOut ) {
				continue;
			}

			$permalink = get_permalink( $post->ID );
			$date      = get_the_date( 'Y-m-d', $post );
			$cleanBody = $this->convertContentToMarkdown( $post->post_content );

			$output .= "## {$post->post_title}\n\n";
			$output .= "- URL: {$permalink}\n";
			$output .= "- Published: {$date}\n\n";
			$output .= $cleanBody . "\n\n";
			$output .= "---\n\n";
		}

		return rtrim( $output ) . "\n";
	}

	/**
	 * Converts HTML post body into clean, LLM-digestible markdown.
	 */
	private function convertContentToMarkdown( string $content ): string {
		// Strip scripts, styles, and comments
		$clean = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $content );
		$clean = preg_replace( '/<!--(.|\s)*?-->/', '', (string) $clean );

		// Convert basic headings
		$clean = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/i', "\n# $1\n", (string) $clean );
		$clean = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/i', "\n## $1\n", (string) $clean );
		$clean = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/i', "\n### $1\n", (string) $clean );
		$clean = preg_replace( '/<h4[^>]*>(.*?)<\/h4>/i', "\n#### $1\n", (string) $clean );

		// Convert list items
		$clean = preg_replace( '/<li[^>]*>(.*?)<\/li>/i', "- $1\n", (string) $clean );

		// Convert paragraphs and line breaks
		$clean = preg_replace( '/<p[^>]*>(.*?)<\/p>/i', "$1\n\n", (string) $clean );
		$clean = preg_replace( '/<br\s*\/?>/i', "\n", (string) $clean );

		// Strip remaining HTML tags
		$clean = wp_strip_all_tags( (string) $clean );

		// Normalize multiple empty lines
		$clean = preg_replace( "/\n{3,}/", "\n\n", (string) $clean );

		return trim( (string) $clean );
	}

	public function registerRestRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/llms-txt/config',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getConfig' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveConfig' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		// BL-033: preview endpoint
		register_rest_route(
			'quinrel/v1',
			'/ai/llms-preview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'previewContent' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function getConfig(): \WP_REST_Response {
		$config = get_option(
			self::CONFIG_OPTION,
			array(
				'description'    => get_bloginfo( 'description' ),
				'allow_index'    => true,
				'allow_training' => false,
				'contact_url'    => get_option( 'admin_email' ),
				'extra_notes'    => '',
				'post_types'     => array( 'post', 'page' ),
			)
		);

		$config['manual_override']      = (bool) get_option( self::OVERRIDE_OPTION, false );
		$config['manual_content']       = (string) get_option( self::OVERRIDE_CONTENT, '' );
		$config['full_manual_override'] = (bool) get_option( self::FULL_OVERRIDE_OPTION, false );
		$config['full_manual_content']  = (string) get_option( self::FULL_OVERRIDE_CONTENT, '' );
		$config['preview']              = $this->buildContent( $config );

		return rest_ensure_response( $config );
	}

	public function saveConfig( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		$postTypes = isset( $params['post_types'] ) && is_array( $params['post_types'] )
			? array_map( 'sanitize_key', $params['post_types'] )
			: array( 'post', 'page' );

		$config = array(
			'description'    => sanitize_text_field( $params['description'] ?? '' ),
			'allow_index'    => (bool) ( $params['allow_index'] ?? true ),
			'allow_training' => (bool) ( $params['allow_training'] ?? false ),
			'contact_url'    => sanitize_text_field( $params['contact_url'] ?? '' ),
			'extra_notes'    => sanitize_textarea_field( $params['extra_notes'] ?? '' ),
			'post_types'     => $postTypes,
		);

		update_option( self::CONFIG_OPTION, $config );

		// BL-033: manual overrides
		if ( isset( $params['manual_override'] ) ) {
			update_option( self::OVERRIDE_OPTION, (bool) $params['manual_override'] );
		}
		if ( isset( $params['manual_content'] ) ) {
			update_option( self::OVERRIDE_CONTENT, sanitize_textarea_field( $params['manual_content'] ) );
		}
		if ( isset( $params['full_manual_override'] ) ) {
			update_option( self::FULL_OVERRIDE_OPTION, (bool) $params['full_manual_override'] );
		}
		if ( isset( $params['full_manual_content'] ) ) {
			update_option( self::FULL_OVERRIDE_CONTENT, sanitize_textarea_field( $params['full_manual_content'] ) );
		}

		$this->invalidateCache();
		flush_rewrite_rules();

		$config['preview'] = $this->buildContent( $config );
		return rest_ensure_response(
			array(
				'success' => true,
				'config'  => $config,
			)
		);
	}

	// BL-033: Preview without saving
	public function previewContent( \WP_REST_Request $request ): \WP_REST_Response {
		$config  = get_option( self::CONFIG_OPTION, array() );
		$type    = (string) $request->get_param( 'type' );
		$content = ( 'full' === $type )
			? $this->buildFullContent( $config )
			: $this->buildContent( $config );

		return rest_ensure_response( array( 'content' => $content ) );
	}
}
