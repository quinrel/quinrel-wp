<?php

namespace Quinrel\WordPress\Core\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Mode\OperatingMode;
use Quinrel\WordPress\Core\Security\KeyVault;

/**
 * BackendApiClient
 *
 * Central HTTP client connecting the Quinrel WordPress plugin (Distribution &
 * Data-Collection Layer) to the Quinrel Backend API.
 * Offloads heavy computational workloads (AI generation, full-site auditing,
 * content gap analysis, keyword cannibalization, and rank tracking).
 */
class BackendApiClient {

	private const DEFAULT_API_URL = 'https://api.quinrel.com/v1';
	private const TIMEOUT         = 30;

	/**
	 * Quinrel API base URL.
	 *
	 * The host is https://api.quinrel.com. The settings screen cannot replace it.
	 * Code may override the base URL with the quinrel_backend_api_url filter.
	 */
	public function getApiUrl(): string {
		$filtered = apply_filters( 'quinrel_backend_api_url', self::DEFAULT_API_URL );
		if ( ! is_string( $filtered ) || ! $this->isHttpsBaseUrl( $filtered ) ) {
			return untrailingslashit( self::DEFAULT_API_URL );
		}

		return untrailingslashit( $filtered );
	}

	private function isHttpsBaseUrl( string $url ): bool {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		return 'https' === $scheme && '' !== $host;
	}

	/**
	 * Get the decrypted API key for Quinrel backend.
	 */
	public function getApiKey(): string {
		$encryptedKey = get_option( 'quinrel_api_key', '' );

		if ( empty( $encryptedKey ) ) {
			return '';
		}

		return KeyVault::decrypt( $encryptedKey );
	}

	/**
	 * Whether this site has a stored Quinrel credential that has not been revoked.
	 */
	public function isConfigured(): bool {
		return OperatingMode::isCloudConfigured();
	}

	/**
	 * Test connection to the Quinrel Backend API.
	 */
	public function testConnection(): array {
		if ( ! $this->isConfigured() ) {
			return array(
				'success' => false,
				'message' => __( 'No Quinrel API key configured. Operating in local / BYOK fallback mode.', 'quinrel' ),
				'mode'    => 'fallback',
			);
		}

		$result = $this->get( '/health' );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
				'mode'    => 'fallback',
			);
		}

		if ( ! empty( $result['success'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'Successfully connected to Quinrel Backend API.', 'quinrel' ),
				'mode'    => 'cloud',
				'data'    => $result['data'] ?? array(),
			);
		}

		return array(
			'success' => false,
			'message' => $result['message'] ?? __( 'Failed to connect to Quinrel Backend API.', 'quinrel' ),
			'mode'    => 'fallback',
		);
	}

	/**
	 * Send a POST request to the Backend API.
	 */
	public function post( string $endpoint, array $payload = array() ): array|\WP_Error {
		$blocked = $this->cloudRequired();
		if ( is_wp_error( $blocked ) ) {
			return $blocked;
		}

		$url     = $this->getApiUrl() . '/' . ltrim( $endpoint, '/' );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => 'Quinrel-WP/' . ( defined( 'QUINREL_VERSION' ) ? QUINREL_VERSION : '1.0.1' ),
			'Authorization' => 'Bearer ' . OperatingMode::credential(),
		);

		// Include client origin metadata for license/rate-limit verification
		$payload['site_url'] = get_site_url();

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => self::TIMEOUT,
			)
		);

		return $this->handleResponse( $response );
	}

	/**
	 * Send a GET request to the Backend API.
	 */
	public function get( string $endpoint, array $params = array() ): array|\WP_Error {
		$blocked = $this->cloudRequired();
		if ( is_wp_error( $blocked ) ) {
			return $blocked;
		}

		$url = $this->getApiUrl() . '/' . ltrim( $endpoint, '/' );
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$headers = array(
			'Accept'        => 'application/json',
			'User-Agent'    => 'Quinrel-WP/' . ( defined( 'QUINREL_VERSION' ) ? QUINREL_VERSION : '1.0.1' ),
			'Authorization' => 'Bearer ' . OperatingMode::credential(),
		);

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => self::TIMEOUT,
			)
		);

		return $this->handleResponse( $response );
	}

	/**
	 * Offload AI Generation request to Backend API.
	 */
	public function generateAi( array $payload ): array|\WP_Error {
		if ( ! $this->isConfigured() ) {
			return OperatingMode::disconnected( __( 'Cloud AI generation', 'quinrel' ) );
		}

		return $this->post( '/ai/generate', $payload );
	}

	/**
	 * Offload Content Gap Analysis to Backend API.
	 */
	public function analyzeContentGap( string $keyword, array $existingTopics ): array|\WP_Error {
		if ( ! $this->isConfigured() ) {
			return OperatingMode::disconnected( __( 'Cloud content-gap analysis', 'quinrel' ) );
		}

		return $this->post(
			'/analysis/content-gap',
			array(
				'keyword'         => $keyword,
				'existing_topics' => $existingTopics,
			)
		);
	}

	/**
	 * Offload Keyword Cannibalization computation to Backend API.
	 */
	public function analyzeCannibalization( array $postKeywordMap ): array|\WP_Error {
		if ( ! $this->isConfigured() ) {
			return OperatingMode::disconnected( __( 'Cloud cannibalization analysis', 'quinrel' ) );
		}

		return $this->post(
			'/analysis/cannibalization',
			array(
				'posts' => $postKeywordMap,
			)
		);
	}

	/**
	 * Offload Technical SEO Site Audit to Backend API.
	 */
	public function dispatchTechnicalAudit( array $siteMetadata ): array|\WP_Error {
		if ( ! $this->isConfigured() ) {
			return OperatingMode::disconnected( __( 'Cloud technical audit', 'quinrel' ) );
		}

		return $this->post(
			'/audit/technical/dispatch',
			array(
				'site' => $siteMetadata,
			)
		);
	}

	/**
	 * Rank tracking stays on this site until a cloud rank API exists.
	 *
	 * A site that is not connected gets the shared not-connected error and
	 * this method does not call api.quinrel.com.
	 *
	 * @param array<int, string> $keywords
	 */
	public function trackRanks( array $keywords ): \WP_Error {
		$keywords = array_values( array_filter( $keywords, 'is_string' ) );
		if ( ! $this->isConfigured() ) {
			return OperatingMode::disconnected( __( 'Cloud rank tracking', 'quinrel' ) );
		}

		if ( $keywords === array() ) {
			return new \WP_Error( 'no_keywords', __( 'No keywords to check.', 'quinrel' ), array( 'status' => 400 ) );
		}

		return new \WP_Error(
			'quinrel_rank_local',
			__( 'Add a SerpApi key in Quinrel settings to check rankings on this site.', 'quinrel' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Stop a Quinrel HTTP call when the site is not connected.
	 */
	private function cloudRequired(): ?\WP_Error {
		if ( $this->isConfigured() ) {
			return null;
		}

		return OperatingMode::disconnected( __( 'the Quinrel cloud', 'quinrel' ) );
	}

	/**
	 * Parse HTTP response into standard associative array.
	 */
	private function handleResponse( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		$errorMessage = $data['message'] ?? $data['error'] ?? sprintf( /* translators: %d: HTTP status code */ __( 'API returned error code %d', 'quinrel' ), $code );
		return array(
			'success' => false,
			'message' => $errorMessage,
			'code'    => $code,
		);
	}
}
