<?php

namespace Quinrel\WordPress\Modules\ContentAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Core\Ai\AiGateway;
use Quinrel\WordPress\Modules\Schema\JsonLd;

class ContentAssistantModule {

	private EventManager $eventManager;

	public function __construct( EventManager $eventManager ) {
		$this->eventManager = $eventManager;
	}

	public function boot(): void {
		$this->eventManager->addAction( 'init', array( $this, 'registerMetaFields' ) );
		$this->eventManager->addAction( 'enqueue_block_editor_assets', array( $this, 'enqueueEditorAssets' ) );
		$this->eventManager->addAction( 'rest_api_init', array( $this, 'registerRestRoutes' ) );

		// Internal Link Index — keep the link table updated on content changes
		$linkEngine = new InternalLinkEngine();
		$this->eventManager->addAction(
			'save_post',
			function ( int $postId ) use ( $linkEngine ) {
				$post = get_post( $postId );
				if ( $post ) {
					$linkEngine->indexPostLinks( $postId, $post );
				}
			},
			20
		);
		$this->eventManager->addAction( 'deleted_post', array( $linkEngine, 'removePostLinks' ) );
	}

	/**
	 * Post meta exposed to the block editor.
	 *
	 * Sanitization runs here, on register_post_meta, so a REST write cannot
	 * skip the React form. auth_callback checks edit_post for that post.
	 *
	 * @return array<int, string>
	 */
	public static function editorMetaKeys(): array {
		return array(
			'_quinrel_meta_title',
			'_quinrel_meta_description',
			'_quinrel_is_news',
			'_quinrel_noindex',
			'_quinrel_nofollow',
			'_quinrel_canonical_url',
			'_quinrel_is_cornerstone',
			'_quinrel_og_title',
			'_quinrel_og_description',
			'_quinrel_og_image',
			'_quinrel_twitter_title',
			'_quinrel_focus_keyword',
			'_quinrel_primary_schema',
			'_quinrel_schema_product_name',
			'_quinrel_schema_product_description',
			'_quinrel_schema_product_price',
			'_quinrel_schema_product_currency',
			'_quinrel_schema_product_rating',
			'_quinrel_schema_product_availability',
			'_quinrel_schema_faq_questions',
			'_quinrel_schema_howto_name',
			'_quinrel_schema_howto_description',
			'_quinrel_schema_howto_steps',
			'_quinrel_schema_howto_supplies',
			'_quinrel_schema_howto_tools',
			'_quinrel_schema_localbusiness_name',
			'_quinrel_schema_localbusiness_telephone',
			'_quinrel_schema_localbusiness_street',
			'_quinrel_schema_localbusiness_city',
			'_quinrel_schema_localbusiness_postal',
			'_quinrel_schema_localbusiness_country',
			'_quinrel_additional_keywords',
			'_quinrel_schema_stack',
			'_quinrel_is_pillar',
			'_quinrel_pagespeed_cache',
			'_quinrel_ranking_keywords',
			'_quinrel_ranking_keywords_at',
			'_quinrel_disable_social_share',
			'_quinrel_custom_schema_properties',
			'_quinrel_schema_custom_type',
			'_quinrel_schema_recipe_name',
			'_quinrel_schema_recipe_description',
			'_quinrel_schema_recipe_ingredients',
			'_quinrel_schema_recipe_instructions',
			'_quinrel_schema_recipe_prep_time',
			'_quinrel_schema_recipe_cook_time',
			'_quinrel_schema_recipe_calories',
			'_quinrel_schema_recipe_cuisine',
			'_quinrel_schema_recipe_yield',
			'_quinrel_schema_event_name',
			'_quinrel_schema_event_description',
			'_quinrel_schema_event_start_date',
			'_quinrel_schema_event_end_date',
			'_quinrel_schema_event_venue',
			'_quinrel_schema_event_address',
			'_quinrel_schema_event_organizer',
			'_quinrel_schema_event_performer',
			'_quinrel_schema_event_price',
			'_quinrel_schema_event_currency',
			'_quinrel_schema_event_status',
			// Podcast Schema
			'_quinrel_podcast_audio_url',
			'_quinrel_podcast_title',
			'_quinrel_podcast_description',
			'_quinrel_podcast_episode_number',
			'_quinrel_podcast_season_number',
			'_quinrel_podcast_episode_type',
			'_quinrel_podcast_series_name',
			'_quinrel_podcast_series_url',
			'_quinrel_podcast_duration',
			'_quinrel_podcast_transcript',
			// Course Schema
			'_quinrel_schema_course_name',
			'_quinrel_schema_course_description',
			'_quinrel_schema_course_code',
			'_quinrel_schema_course_level',
			'_quinrel_schema_course_provider',
			'_quinrel_schema_course_price',
			'_quinrel_schema_course_currency',
			// JobPosting Schema
			'_quinrel_schema_job_title',
			'_quinrel_schema_job_description',
			'_quinrel_schema_job_company',
			'_quinrel_schema_job_company_url',
			'_quinrel_schema_job_employment_type',
			'_quinrel_schema_job_valid_through',
			'_quinrel_schema_job_location_type',
			'_quinrel_schema_job_street',
			'_quinrel_schema_job_city',
			'_quinrel_schema_job_postal',
			'_quinrel_schema_job_country',
			'_quinrel_schema_job_salary',
			'_quinrel_schema_job_salary_currency',
			'_quinrel_schema_job_salary_unit',
			// Book Schema
			'_quinrel_schema_book_name',
			'_quinrel_schema_book_author',
			'_quinrel_schema_book_isbn',
			'_quinrel_schema_book_format',
			'_quinrel_schema_book_date_published',
			'_quinrel_schema_book_pages',
			'_quinrel_schema_book_publisher',
			// SoftwareApplication Schema
			'_quinrel_schema_software_name',
			'_quinrel_schema_software_os',
			'_quinrel_schema_software_category',
			'_quinrel_schema_software_price',
			'_quinrel_schema_software_currency',
			'_quinrel_schema_software_rating',
			// Restaurant Schema
			'_quinrel_schema_restaurant_name',
			'_quinrel_schema_restaurant_cuisine',
			'_quinrel_schema_restaurant_price_range',
			'_quinrel_schema_restaurant_telephone',
			'_quinrel_schema_restaurant_street',
			'_quinrel_schema_restaurant_city',
			'_quinrel_schema_restaurant_postal',
			'_quinrel_schema_restaurant_country',
			'_quinrel_schema_restaurant_menu_url',
			'_quinrel_schema_restaurant_reservations',
			// Service Schema
			'_quinrel_schema_service_name',
			'_quinrel_schema_service_description',
			'_quinrel_schema_service_type',
			'_quinrel_schema_service_provider',
			'_quinrel_schema_service_area',
			'_quinrel_schema_service_terms',
			'_quinrel_schema_service_price',
			'_quinrel_schema_service_currency',
			// Review Schema
			'_quinrel_schema_review_item_name',
			'_quinrel_schema_review_item_type',
			'_quinrel_schema_review_rating',
			'_quinrel_schema_review_body',
			'_quinrel_schema_review_author',
			// Movie Schema
			'_quinrel_schema_movie_name',
			'_quinrel_schema_movie_director',
			'_quinrel_schema_movie_actors',
			'_quinrel_schema_movie_date',
			'_quinrel_schema_movie_duration',
			// Music Schema
			'_quinrel_schema_music_name',
			'_quinrel_schema_music_artist',
			'_quinrel_schema_music_album',
			'_quinrel_schema_music_duration',
			// Dataset Schema
			'_quinrel_schema_dataset_name',
			'_quinrel_schema_dataset_description',
			'_quinrel_schema_dataset_license',
			'_quinrel_schema_dataset_download_url',
			'_quinrel_schema_dataset_format',
			'_quinrel_schema_dataset_creator',
			// Person Schema
			'_quinrel_schema_person_name',
			'_quinrel_schema_person_job_title',
			'_quinrel_schema_person_works_for',
			'_quinrel_schema_person_same_as',
			'_quinrel_schema_person_url',
		);
	}

	public function registerMetaFields(): void {
		foreach ( array( 'post', 'page' ) as $postType ) {
			foreach ( self::editorMetaKeys() as $metaKey ) {
				register_post_meta(
					$postType,
					$metaKey,
					array(
						'show_in_rest'      => true,
						'single'            => true,
						'type'              => 'string',
						'auth_callback'     => static function ( $allowed, $key, $objectId ): bool {
							unset( $allowed, $key );
							return current_user_can( 'edit_post', (int) $objectId );
						},
						'sanitize_callback' => static function ( $value, $key ) {
							return self::sanitizeMetaValue( (string) $key, $value );
						},
					)
				);
			}
		}
	}

	/**
	 * Sanitize one editor meta value.
	 *
	 * Custom schema keeps the JsonLd result, including WP_Error, so an invalid
	 * document is rejected instead of stored as an empty string.
	 *
	 * @param mixed $value Raw REST value.
	 * @return string|\WP_Error
	 */
	public static function sanitizeMetaValue( string $metaKey, $value ) {
		if ( '_quinrel_custom_schema_properties' === $metaKey ) {
			return JsonLd::sanitizeCustomMeta( is_string( $value ) ? $value : '' );
		}
		if ( '_quinrel_schema_faq_questions' === $metaKey ) {
			return self::sanitizeFaqMeta( $value );
		}
		if ( in_array( $metaKey, array( '_quinrel_canonical_url', '_quinrel_og_image', '_quinrel_podcast_audio_url', '_quinrel_podcast_series_url', '_quinrel_schema_job_company_url', '_quinrel_schema_restaurant_menu_url', '_quinrel_schema_service_terms', '_quinrel_schema_dataset_download_url', '_quinrel_schema_person_url' ), true ) ) {
			return self::sanitizeHttpUrl( $value );
		}
		if ( in_array( $metaKey, array( '_quinrel_noindex', '_quinrel_nofollow', '_quinrel_is_news', '_quinrel_is_cornerstone', '_quinrel_is_pillar', '_quinrel_disable_social_share' ), true ) ) {
			return self::sanitizeYesNo( $value );
		}
		if ( in_array( $metaKey, array( '_quinrel_meta_description', '_quinrel_og_description', '_quinrel_schema_product_description', '_quinrel_schema_howto_description', '_quinrel_schema_recipe_description', '_quinrel_schema_event_description', '_quinrel_podcast_description', '_quinrel_podcast_transcript', '_quinrel_schema_course_description', '_quinrel_schema_job_description', '_quinrel_schema_service_description', '_quinrel_schema_review_body', '_quinrel_schema_dataset_description' ), true ) ) {
			return sanitize_textarea_field( is_string( $value ) ? $value : '' );
		}

		return sanitize_text_field( is_string( $value ) ? $value : '' );
	}

	/**
	 * An http or https URL, or an empty string.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitizeHttpUrl( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$valid = wp_http_validate_url( $value );
		if ( ! is_string( $valid ) || '' === $valid ) {
			return '';
		}
		return esc_url_raw( $valid );
	}

	/**
	 * Robots and other on/off flags. Anything other than yes is stored as no.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitizeYesNo( $value ): string {
		if ( ! is_string( $value ) ) {
			return 'no';
		}
		return strtolower( trim( $value ) ) === 'yes' ? 'yes' : 'no';
	}

	/**
	 * FAQ schema is a JSON list of question and answer strings.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitizeFaqMeta( $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '[]';
		}
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			return '[]';
		}

		$clean = array();
		foreach ( $decoded as $row ) {
			if ( count( $clean ) >= 50 || ! is_array( $row ) ) {
				continue;
			}
			$question = sanitize_text_field( isset( $row['question'] ) ? (string) $row['question'] : '' );
			$answer   = sanitize_textarea_field( isset( $row['answer'] ) ? (string) $row['answer'] : '' );
			if ( '' === $question && '' === $answer ) {
				continue;
			}
			$clean[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		$json = wp_json_encode( $clean );
		return is_string( $json ) ? $json : '[]';
	}

	public function enqueueEditorAssets(): void {
		$assetFile = QUINREL_PLUGIN_DIR . 'build/editor.asset.php';

		if ( ! file_exists( $assetFile ) ) {
			return;
		}

		$assets = require $assetFile;

		$dependencies = array_merge(
			$assets['dependencies'],
			array( 'wp-blocks', 'wp-plugins', 'wp-edit-post', 'wp-i18n', 'wp-components', 'wp-data', 'wp-core-data' )
		);

		wp_enqueue_script(
			'quinrel-editor-js',
			QUINREL_PLUGIN_URL . 'build/editor.js',
			$dependencies,
			$assets['version'],
			true
		);

		$editorConfig = array(
			'apiUrl'            => esc_url_raw( rest_url( 'quinrel/v1' ) ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'defaultShareImage' => esc_url_raw( get_option( 'quinrel_default_share_image', '' ) ),
			'cloudConfigured'   => \Quinrel\WordPress\Core\Mode\OperatingMode::isCloudConfigured() ? '1' : '0',
		);

		wp_localize_script( 'quinrel-editor-js', 'quinrelEditorConfig', $editorConfig );
	}

	public function registerRestRoutes(): void {
		register_rest_route(
			'quinrel/v1',
			'/ai/generate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generateAiContent' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/content/internal-links',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'getContentInternalLinks' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/custom-fields/discover',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleDiscoverCustomFields' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/custom-fields/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handleGetCustomFieldSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handleUpdateCustomFieldSettings' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);
	}

	public function handleDiscoverCustomFields( \WP_REST_Request $request ): \WP_REST_Response {
		$postId = absint( $request->get_param( 'post_id' ) );
		$fields = CustomFieldScanner::discoverFields( $postId );
		$active = CustomFieldScanner::getConfiguredSeoFields();

		return rest_ensure_response(
			array(
				'post_id'           => $postId,
				'discovered_fields' => $fields,
				'active_fields'     => $active,
			)
		);
	}

	public function handleGetCustomFieldSettings( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'active_fields' => CustomFieldScanner::getConfiguredSeoFields(),
			)
		);
	}

	public function handleUpdateCustomFieldSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();

		CustomFieldScanner::setConfiguredSeoFields( $fields );

		return rest_ensure_response(
			array(
				'success'       => true,
				'active_fields' => CustomFieldScanner::getConfiguredSeoFields(),
			)
		);
	}

	public function checkPermission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle AI text generation requests.
	 */
	public function generateAiContent( \WP_REST_Request $request ): \WP_REST_Response {
		$params       = $request->get_json_params();
		$action       = isset( $params['action'] ) ? sanitize_key( $params['action'] ) : '';
		$title        = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$content      = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';
		$focusKeyword = isset( $params['focus_keyword'] ) ? sanitize_text_field( $params['focus_keyword'] ) : '';

		if ( empty( $action ) ) {
			return new \WP_Error( 'missing_action', __( 'Action parameter is required.', 'quinrel' ), array( 'status' => 400 ) );
		}

		$aiGateway    = new AiGateway();
		$systemPrompt = 'You are an elite, senior WordPress SEO and AEO writing specialist. Keep responses professional, highly optimized, and concise.';

		// Build targeted prompt based on desired action
		switch ( $action ) {
			case 'generate_titles_metas':
				$prompt = "Given the article title: '$title'\nContent: " . wp_strip_all_tags( $content ) . "\nFocus Keyword: '$focusKeyword'\n\nGenerate exactly 3 high-CTR Meta Titles (under 60 characters) and 3 highly compelling Meta Descriptions (under 160 characters). You MUST format the output as a valid, parsable JSON object with keys 'titles' (array of strings) and 'descriptions' (array of strings). Do not include any explanation or markdown block backticks (like ```json), return raw JSON only.";
				break;

			case 'generate_faqs':
				$prompt = "Based on the content:\n" . wp_strip_all_tags( $content ) . "\n\nGenerate exactly 3 highly relevant FAQ questions and helpful concise answers. Format your output as a valid, parsable JSON array of objects, where each object has keys 'question' and 'answer'. Do not include markdown code block formatting (like ```json), return raw JSON only.";
				break;

			case 'generate_outline_summary':
				$prompt = "Create a high-level content summary (1-2 sentences) and a detailed, structured SEO outline (using ##, ### Markdown headers) for an article with:\nTitle: '$title'\nFocus Keyword: '$focusKeyword'\n\nProvide structural recommendations (word count target, search intent target, key headings to add). Format your reply nicely using standard Markdown.";
				break;

			case 'writing_assistant':
				$prompt = "Analyze the content for SEO best practices:\nFocus Keyword: '$focusKeyword'\nTitle: '$title'\nContent: " . wp_strip_all_tags( $content ) . "\n\nProvide exactly 3 bullet points with highly actionable suggestions to improve keyword placement, heading structure, or readability ease. Keep each bullet point short, punchy, and professional.";
				break;

			default:
				return new \WP_Error( 'invalid_action', __( 'Invalid AI action.', 'quinrel' ), array( 'status' => 400 ) );
		}

		$result = $aiGateway->queryModel( $prompt, $systemPrompt );

		if ( ! $result['success'] ) {
			return new \WP_Error( 'ai_generation_failed', $result['message'], array( 'status' => 400 ) );
		}

		$text = $result['text'];

		// Clean up markdown block wrapping if LLM returned it anyway
		if ( strpos( $text, '```' ) !== false ) {
			$text = preg_replace( '/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', $text );
			$text = trim( $text );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'result'  => $text,
			)
		);
	}

	/**
	 * Fetch related link suggestions and orphan status.
	 */
	public function getContentInternalLinks( \WP_REST_Request $request ): \WP_REST_Response {
		$params  = $request->get_json_params();
		$postId  = isset( $params['post_id'] ) ? intval( $params['post_id'] ) : 0;
		$content = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';

		if ( ! $postId ) {
			return new \WP_Error( 'missing_id', __( 'Post ID is required.', 'quinrel' ), array( 'status' => 400 ) );
		}

		$linkEngine      = new InternalLinkEngine();
		$recommendations = $linkEngine->getRecommendations( $postId, $content );
		$orphanStatus    = $linkEngine->checkOrphanStatus( $postId );

		return rest_ensure_response(
			array(
				'success'         => true,
				'recommendations' => $recommendations,
				'orphan_status'   => $orphanStatus,
			)
		);
	}
}
