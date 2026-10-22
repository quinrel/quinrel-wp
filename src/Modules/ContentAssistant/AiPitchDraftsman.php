<?php

namespace Quinrel\WordPress\Modules\ContentAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Modules\Ai\ModelGateway;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * AiPitchDraftsman
 *
 * TASK-LNK-04: Generates personalized, high-converting, non-spammy outreach pitches
 * using local or cloud LLM gateways via ModelGateway.
 */
class AiPitchDraftsman {

	private ModelGateway $gateway;

	public function __construct( ?ModelGateway $gateway = null ) {
		$this->gateway = $gateway ?? new ModelGateway();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$cap = static fn() => current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/outreach/generate-pitch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restGeneratePitch' ),
				'permission_callback' => $cap,
			)
		);
	}

	/**
	 * REST handler for pitch generation.
	 */
	public function restGeneratePitch( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: array();

		$result = $this->draftPitch( $params );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * Drafts an outreach pitch using AI or authoritative editorial fallbacks.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function draftPitch( array $args ): array {
		$targetDomain = sanitize_text_field( (string) ( $args['target_domain'] ?? '' ) );
		$targetTopic  = sanitize_text_field( (string) ( $args['target_topic'] ?? 'your recent article' ) );
		$targetName   = sanitize_text_field( (string) ( $args['contact_name'] ?? 'Editor' ) );
		$targetEmail  = sanitize_email( (string) ( $args['contact_email'] ?? '' ) );
		$pitchType    = sanitize_key( (string) ( $args['pitch_type'] ?? 'resource_addition' ) );

		$ourPostId = (int) ( $args['our_article_id'] ?? 0 );
		$ourTitle  = '';
		$ourUrl    = '';

		if ( $ourPostId > 0 ) {
			$ourTitle = (string) get_the_title( $ourPostId );
			$ourUrl   = (string) get_permalink( $ourPostId );
		}

		if ( '' === $ourTitle && ! empty( $args['our_article_title'] ) ) {
			$ourTitle = sanitize_text_field( (string) $args['our_article_title'] );
		}
		if ( '' === $ourUrl && ! empty( $args['our_article_url'] ) ) {
			$ourUrl = esc_url_raw( (string) $args['our_article_url'] );
		}

		$siteName = (string) get_bloginfo( 'name' );

		// Check if an AI provider API key is saved on this site
		$hasAiKey = ! empty( get_option( 'quinrel_ai_api_key' ) )
			|| ! empty( get_option( 'quinrel_openai_api_key' ) )
			|| ! empty( get_option( 'quinrel_anthropic_api_key' ) );

		if ( $hasAiKey && ! defined( 'QUINREL_TESTS' ) ) {
			$prompt = sprintf(
				"Draft an outreach email pitch.\nRecipient: %s at %s\nTarget Topic: %s\nOur Publication: %s\nOur Asset: %s (%s)\nPitch Type: %s\n\nRequirements:\n- Subject line under 50 characters.\n- Opening referencing their work.\n- Concrete value proposition for their readers.\n- Low friction call to action.\n- No buzzwords or spam.\nFormat response as:\nSubject: <subject>\n\n<body>",
				$targetName,
				$targetDomain,
				$targetTopic,
				$siteName,
				$ourTitle,
				$ourUrl,
				$pitchType
			);

			$aiRes = $this->gateway->complete( $prompt, array( 'system' => 'You are an ethical, professional editorial relations specialist.' ) );
			if ( ! empty( $aiRes['success'] ) && ! empty( $aiRes['content'] ) ) {
				$parsed = $this->parseAiResponse( (string) $aiRes['content'] );
				return $this->formatResult( $parsed['subject'], $parsed['body'], $targetEmail, $pitchType, $aiRes['provider'] ?? 'ai' );
			}
		}

		// Editorial deterministic fallback template
		return $this->deterministicTemplate( $targetName, $targetDomain, $targetTopic, $siteName, $ourTitle, $ourUrl, $targetEmail, $pitchType );
	}

	/**
	 * Parses Subject and Body from AI text response.
	 *
	 * @return array{subject: string, body: string}
	 */
	private function parseAiResponse( string $raw ): array {
		$subject = 'Editorial resource for ' . get_bloginfo( 'name' );
		$body    = trim( $raw );

		if ( 1 === preg_match( '/^Subject:\s*(.+)$/mi', $raw, $matches ) ) {
			$subject = trim( $matches[1] );
			$body    = trim( (string) preg_replace( '/^Subject:\s*.+$/mi', '', $raw ) );
		}

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * High-converting deterministic outreach template.
	 *
	 * @return array<string, mixed>
	 */
	private function deterministicTemplate(
		string $name,
		string $domain,
		string $topic,
		string $siteName,
		string $ourTitle,
		string $ourUrl,
		string $email,
		string $pitchType
	): array {
		$greeting = '' !== $name && 'Editor' !== $name ? "Hi {$name}," : 'Hi there,';

		switch ( $pitchType ) {
			case 'broken_link':
				$subject = "Broken link replacement for {$domain}";
				$body    = "{$greeting}\n\nI was reading your piece on {$topic} and noticed one of the external reference links appears to be broken/returning a 404 error.\n\nWe recently published a comprehensive, up-to-date guide on this exact subject: \"{$ourTitle}\" ({$ourUrl}).\n\nIf you're updating the article, it might make a helpful replacement for your readers.\n\nEither way, keep up the great editorial work!\n\nBest regards,\nEditorial Team at {$siteName}";
				break;

			case 'guest_post':
				$subject = "Editorial contribution idea for {$domain}";
				$body    = "{$greeting}\n\nI really enjoyed your recent coverage of {$topic} on {$domain}.\n\nGiven your readers' focus, I had an idea for a practical, data-backed piece around modern search architecture that hasn't been widely covered yet.\n\nAs a quick example of the technical depth we publish, here is our latest analysis: \"{$ourTitle}\" ({$ourUrl}).\n\nWould you be open to an outline or brief pitch?\n\nBest regards,\nEditorial Team at {$siteName}";
				break;

			case 'resource_addition':
			default:
				$subject = "Quick resource suggestion for {$topic}";
				$body    = "{$greeting}\n\nI came across your guide on {$topic} while researching best practices for our team at {$siteName}.\n\nWe just released an in-depth breakdown covering \"{$ourTitle}\":\n{$ourUrl}\n\nIt includes recent benchmarks and step-by-step implementation details that could serve as a valuable supplementary reference for your audience.\n\nThanks for your time and excellent reporting.\n\nBest regards,\nEditorial Team at {$siteName}";
				break;
		}

		return $this->formatResult( $subject, $body, $email, $pitchType, 'deterministic' );
	}

	/**
	 * Formats standardized return array with mailto link.
	 *
	 * @return array<string, mixed>
	 */
	private function formatResult( string $subject, string $body, string $email, string $pitchType, string $provider ): array {
		$mailto = 'mailto:' . rawurlencode( $email )
			. '?subject=' . rawurlencode( $subject )
			. '&body=' . rawurlencode( $body );

		return array(
			'subject'    => $subject,
			'body'       => $body,
			'mailto_url' => $mailto,
			'pitch_type' => $pitchType,
			'provider'   => $provider,
		);
	}
}
