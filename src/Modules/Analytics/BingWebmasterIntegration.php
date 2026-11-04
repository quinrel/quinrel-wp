<?php

namespace Quinrel\WordPress\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Security\KeyVault;

/**
 * BingWebmasterIntegration
 *
 * Connects to the Bing Webmaster API via an API Key to fetch
 * traffic metrics (impressions and clicks) for the last 28 days.
 */
class BingWebmasterIntegration {

	private const API_KEY_OPTION = 'quinrel_bing_api_key';
	private const CACHE_TTL      = 6 * HOUR_IN_SECONDS;
	private const TRAFFIC_URL    = 'https://ssl.bing.com/webmaster/api.svc/json/GetRankAndTrafficStats';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/analytics/bing/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getSettings' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => 'POST, PUT, PATCH',
					'callback'            => array( $this, 'saveSettings' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/analytics/bing/traffic',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getTraffic' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function getSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$stored = (string) get_option( self::API_KEY_OPTION, '' );
		return rest_ensure_response(
			array(
				'api_key' => $stored !== '' ? '••••••••••••' : '',
			)
		);
	}

	public function saveSettings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$key = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
		if ( $key === '••••••••••••' ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		$stored = self::storeKey( $key );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Decrypt the stored Bing key. Plaintext values from older installs are returned unchanged.
	 */
	public static function storedKey(): string {
		$stored = (string) get_option( self::API_KEY_OPTION, '' );
		return $stored === '' ? '' : KeyVault::decrypt( $stored );
	}

	/**
	 * Store the Bing key as ciphertext. A failed encrypt does not write the plaintext.
	 *
	 * @return true|\WP_Error
	 */
	public static function storeKey( string $plaintext ): true|\WP_Error {
		self::forgetTrafficCache();
		if ( $plaintext === '' ) {
			update_option( self::API_KEY_OPTION, '', false );
			return true;
		}

		$encrypted = KeyVault::encrypt( $plaintext );
		if ( is_wp_error( $encrypted ) || ! is_string( $encrypted ) || ! KeyVault::isCiphertext( $encrypted ) ) {
			return is_wp_error( $encrypted )
				? $encrypted
				: new \WP_Error(
					'quinrel_encryption_failed',
					__( 'Quinrel could not encrypt this secret. The value was not saved.', 'quinrel' ),
					array( 'status' => 500 )
				);
		}

		update_option( self::API_KEY_OPTION, $encrypted, false );
		return true;
	}

	public function getTraffic( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		unset( $request );
		$apiKey = self::storedKey();
		if ( '' === $apiKey ) {
			return new \WP_Error(
				'bing_unconfigured',
				__( 'Save a Bing Webmaster API key first. In Bing Webmaster Tools, open Settings, then API Access, and generate a key.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		$cacheKey = self::trafficCacheKey();
		$cached   = get_transient( $cacheKey );
		if ( is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$response = wp_safe_remote_get(
			add_query_arg(
				array(
					'siteUrl' => home_url( '/' ),
					'apikey'  => $apiKey,
				),
				self::TRAFFIC_URL
			),
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'bing_http_failed',
				__( 'Bing did not respond. Try again in a few minutes.', 'quinrel' ),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'bing_api_error',
				self::bingErrorMessage( $code, $body, $apiKey ),
				array( 'status' => 502 )
			);
		}

		$decoded = json_decode( $body, true );
		$rows    = is_array( $decoded ) && isset( $decoded['d'] ) && is_array( $decoded['d'] ) ? $decoded['d'] : array();
		$summary = self::summarizeTraffic( $rows );
		set_transient( $cacheKey, $summary, self::CACHE_TTL );

		return rest_ensure_response( $summary );
	}

	/**
	 * Sum the newest 28 dated rows, and the 28 before them when they exist.
	 *
	 * @param array<int, mixed> $rows
	 * @return array<string, mixed>
	 */
	public static function summarizeTraffic( array $rows ): array {
		$dated = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$stamp = self::bingDateStamp( $row['Date'] ?? '' );
			if ( null === $stamp ) {
				continue;
			}
			$dated[] = array(
				'stamp'       => $stamp,
				'clicks'      => (int) ( $row['Clicks'] ?? 0 ),
				'impressions' => (int) ( $row['Impressions'] ?? 0 ),
			);
		}

		usort(
			$dated,
			static function ( array $left, array $right ): int {
				return $left['stamp'] <=> $right['stamp'];
			}
		);

		$count       = count( $dated );
		$current     = array_slice( $dated, -28 );
		$clicks      = 0;
		$impressions = 0;
		foreach ( $current as $row ) {
			$clicks      += $row['clicks'];
			$impressions += $row['impressions'];
		}

		$previousClicks      = null;
		$previousImpressions = null;
		$message             = '';
		if ( 0 === $count ) {
			$message = __( 'Bing has no traffic rows for this site yet. Add this site in Bing, or check that its address matches.', 'quinrel' );
		} elseif ( $count < 29 ) {
			$message = __( 'Bing has not returned enough days for a comparison.', 'quinrel' );
		} else {
			$older               = array_slice( $dated, 0, $count - count( $current ) );
			$previous            = array_slice( $older, -28 );
			$previousClicks      = 0;
			$previousImpressions = 0;
			foreach ( $previous as $row ) {
				$previousClicks      += $row['clicks'];
				$previousImpressions += $row['impressions'];
			}
		}

		return array(
			'success'                    => true,
			'site_url'                   => home_url( '/' ),
			'window_days'                => 28,
			'measured_days'              => count( $current ),
			'clicks'                     => $clicks,
			'impressions'                => $impressions,
			'previous_clicks'            => $previousClicks,
			'previous_impressions'       => $previousImpressions,
			'clicks_change_percent'      => self::changePercent( $clicks, $previousClicks ),
			'impressions_change_percent' => self::changePercent( $impressions, $previousImpressions ),
			'message'                    => $message,
		);
	}

	/**
	 * @param mixed $value
	 */
	private static function bingDateStamp( $value ): ?int {
		if ( ! is_string( $value ) || 1 !== preg_match( '#/Date\((-?\d+)#', $value, $matches ) ) {
			return null;
		}

		return (int) floor( ( (int) $matches[1] ) / 1000 );
	}

	private static function changePercent( int $current, ?int $previous ): ?float {
		if ( ! is_int( $previous ) || $previous <= 0 ) {
			return null;
		}

		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}

	private static function bingErrorMessage( int $code, string $body, string $apiKey ): string {
		$detail  = '';
		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) && isset( $decoded['Message'] ) && is_string( $decoded['Message'] ) ) {
			$detail = trim( str_replace( $apiKey, '', $decoded['Message'] ) );
			if ( strlen( $detail ) > 180 ) {
				$detail = '';
			}
		}

		$message = sprintf(
			/* translators: %d: HTTP status code returned by Bing. */
			__( 'Bing returned status %d.', 'quinrel' ),
			$code
		);
		if ( '' !== $detail ) {
			$message .= ' ' . $detail;
		}

		return $message;
	}

	private static function trafficCacheKey(): string {
		return 'quinrel_bing_traffic_28_' . md5( home_url( '/' ) );
	}

	private static function forgetTrafficCache(): void {
		delete_transient( self::trafficCacheKey() );
	}
}
