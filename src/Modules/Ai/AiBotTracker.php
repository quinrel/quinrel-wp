<?php

namespace Quinrel\WordPress\Modules\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks verified and self-identified AI search crawlers (GPTBot, ClaudeBot, Perplexity, etc.)
 * using transient buffering to guarantee zero database blocking on frontend requests.
 */
class AiBotTracker {

	public const TRANSIENT_KEY = 'quinrel_ai_bot_activity';
	public const TTL_SECONDS   = 2592000; // 30 days

	private const KNOWN_BOTS = array(
		'oai_searchbot'    => array( 'OAI-SearchBot' ),
		'claude_searchbot' => array( 'Claude-SearchBot' ),
		'gptbot'           => array( 'GPTBot', 'ChatGPT-User' ),
		'claudebot'        => array( 'ClaudeBot', 'Anthropic-AI', 'Claude-Web' ),
		'perplexitybot'    => array( 'PerplexityBot' ),
		'google_extended'  => array( 'Google-Extended' ),
		'applebot'         => array( 'Applebot-Extended' ),
		'other_ai'         => array( 'CCBot', 'cohere-ai', 'YouBot', 'Omgilibot' ),
	);

	/**
	 * Hooked to init to record crawler visits lightweightly.
	 */
	public function trackRequest(): void {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( '' === $userAgent ) {
			return;
		}

		$botKey = $this->detectBot( $userAgent );
		if ( null === $botKey ) {
			return;
		}

		$this->incrementCount( $botKey );
	}

	/**
	 * Detect if the User-Agent belongs to a known AI crawler.
	 */
	public function detectBot( string $userAgent ): ?string {
		foreach ( self::KNOWN_BOTS as $key => $signatures ) {
			foreach ( $signatures as $sig ) {
				if ( stripos( $userAgent, $sig ) !== false ) {
					return $key;
				}
			}
		}

		return null;
	}

	/**
	 * Increment buffered count for a specific bot.
	 */
	public function incrementCount( string $botKey ): void {
		$data = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $data ) ) {
			$data = $this->emptyActivity();
		}

		if ( ! isset( $data[ $botKey ] ) ) {
			$data[ $botKey ] = 0;
		}

		$data[ $botKey ]   = (int) $data[ $botKey ] + 1;
		$data['total']     = (int) ( $data['total'] ?? 0 ) + 1;
		$data['last_seen'] = gmdate( 'Y-m-d H:i:s' );
		$data['last_bot']  = $botKey;

		set_transient( self::TRANSIENT_KEY, $data, self::TTL_SECONDS );
	}

	/**
	 * Retrieve activity statistics.
	 *
	 * @return array<string, mixed>
	 */
	public function getActivity(): array {
		$data = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $data ) ) {
			return $this->emptyActivity();
		}

		return array_merge( $this->emptyActivity(), $data );
	}

	/**
	 * Zeroed counters, including crawlers recorded after older transients were stored.
	 *
	 * @return array<string, int|string>
	 */
	private function emptyActivity(): array {
		return array(
			'gptbot'           => 0,
			'oai_searchbot'    => 0,
			'claudebot'        => 0,
			'claude_searchbot' => 0,
			'perplexitybot'    => 0,
			'google_extended'  => 0,
			'applebot'         => 0,
			'other_ai'         => 0,
			'total'            => 0,
			'last_seen'        => '',
			'last_bot'         => '',
		);
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'quinrel/v1',
			'/ai-bot-activity',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getActivityEndpoint' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
	}

	public function getActivityEndpoint(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->getActivity(),
			)
		);
	}

	public function checkPermission(): bool {
		return current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );
	}
}
