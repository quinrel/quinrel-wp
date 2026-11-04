<?php

namespace Quinrel\WordPress\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Security\KeyVault;

/**
 * GoogleSearchConsoleIntegration
 *
 * BL-030: OAuth2 Google Search Console connection.
 * Fetches impressions, clicks, CTR, position per URL and
 * "Top Declining Keywords" trend data.
 */
class GoogleSearchConsoleIntegration {

	private const TOKEN_OPTION  = 'quinrel_gsc_token';
	private const CONFIG_OPTION = 'quinrel_gsc_config';
	private const CACHE_TTL     = 6 * HOUR_IN_SECONDS;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$adminCap  = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/gsc/auth-url',
			array(
				'methods'             => array( \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ),
				'callback'            => array( $this, 'getAuthUrl' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/gsc/callback',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleCallback' ),
				'permission_callback' => function () use ( $adminCap ): bool|\WP_Error {
					// Direct OAuth redirects from Google arrive via browser navigation
					// with WordPress session cookies, but without the REST API 'wp_rest' nonce.
					// Validate the auth cookie to restore the administrator's identity.
					if ( ! is_user_logged_in() ) {
						$userId = wp_validate_auth_cookie( '', 'logged_in' );
						if ( $userId ) {
							wp_set_current_user( $userId );
						}
					}
					if ( $adminCap() ) {
						return true;
					}
					if ( ! isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
						$loginUrl = wp_login_url( admin_url( 'admin.php?page=quinrel&tab=settings_gsc' ) );
						wp_safe_redirect( $loginUrl );
						exit;
					}
					return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'quinrel' ), array( 'status' => 401 ) );
				},
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/gsc/disconnect',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/gsc/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getStatus' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/gsc/sites',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getSites' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/gsc/property',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'setProperty' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/gsc/performance',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPerformance' ),
				'permission_callback' => $reportCap,
				'args'                => array(
					'days'    => array(
						'default'           => 28,
						'sanitize_callback' => 'absint',
					),
					'page'    => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_url',
					),
					'keyword' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/gsc/declining-keywords',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDecliningKeywords' ),
				'permission_callback' => $reportCap,
			)
		);
	}

	public function getAuthUrl( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$config = $this->getConfig();

		$paramClientId = sanitize_text_field( wp_unslash( $request->get_param( 'client_id' ) ?? '' ) );
		$paramSecret   = sanitize_text_field( wp_unslash( $request->get_param( 'client_secret' ) ?? '' ) );

		if ( ! empty( $paramClientId ) ) {
			$config['client_id'] = $paramClientId;
			if ( ! empty( $paramSecret ) && $paramSecret !== '••••••••••••' ) {
				$config['client_secret'] = $paramSecret;
			}
			$saved = $this->storeConfig( $config );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		if ( empty( $config['client_id'] ) ) {
			return new \WP_Error(
				'no_config',
				__( 'Please provide your Google OAuth Client ID and Client Secret in the fields below before connecting.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $config['client_secret'] ) ) {
			return new \WP_Error(
				'no_secret',
				__( 'Please provide your Google OAuth Client Secret in the fields below before connecting.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		$state       = wp_create_nonce( 'quinrel_gsc_oauth' );
		$redirectUri = rest_url( 'quinrel/v1/gsc/callback' );

		$params = http_build_query(
			array(
				'client_id'     => $config['client_id'],
				'redirect_uri'  => $redirectUri,
				'response_type' => 'code',
				'scope'         => 'https://www.googleapis.com/auth/webmasters.readonly',
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			)
		);

		return rest_ensure_response(
			array(
				'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . $params,
			)
		);
	}

	public function handleCallback( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$code  = sanitize_text_field( $request->get_param( 'code' ) ?? '' );
		$state = sanitize_text_field( $request->get_param( 'state' ) ?? '' );
		$error = sanitize_text_field( $request->get_param( 'error_description' ) ?: ( $request->get_param( 'error' ) ?? '' ) );

		$redirectOrRespond = function ( bool $isSuccess, string $message ) use ( $request ) {
			if ( $request->get_header( 'X-WP-Nonce' ) ) {
				if ( $isSuccess ) {
					return rest_ensure_response(
						array(
							'success' => true,
							'message' => $message,
						)
					);
				}
				return new \WP_Error( 'oauth_error', $message, array( 'status' => 400 ) );
			}

			$queryArgs = array(
				'page' => 'quinrel',
				'tab'  => 'settings_gsc',
			);
			if ( $isSuccess ) {
				$queryArgs['gsc_connected'] = '1';
			} else {
				$queryArgs['gsc_error'] = $message;
			}

			$targetUrl = add_query_arg( $queryArgs, admin_url( 'admin.php' ) );
			wp_safe_redirect( $targetUrl );
			exit;
		};

		if ( ! empty( $error ) ) {
			/* translators: %s: Error message from Google OAuth */
			return $redirectOrRespond( false, sprintf( __( 'Google authorization error: %s', 'quinrel' ), $error ) );
		}

		if ( ! wp_verify_nonce( $state, 'quinrel_gsc_oauth' ) ) {
			return $redirectOrRespond( false, __( 'OAuth security verification failed (state mismatch). Please try connecting again.', 'quinrel' ) );
		}

		if ( empty( $code ) ) {
			return $redirectOrRespond( false, __( 'No authorization code received from Google.', 'quinrel' ) );
		}

		$config = $this->getConfig();
		$token  = $this->exchangeCode( $code, $config );

		if ( is_wp_error( $token ) ) {
			return $redirectOrRespond( false, $token->get_error_message() );
		}

		$written = $this->writeToken( $token );
		if ( is_wp_error( $written ) ) {
			return $redirectOrRespond( false, $written->get_error_message() );
		}
		delete_transient( 'gsc_perf_summary' );
		delete_transient( 'quinrel_gsc_dashboard_v1' );

		return $redirectOrRespond( true, __( 'Google Search Console connected successfully.', 'quinrel' ) );
	}

	public function disconnect( \WP_REST_Request $request ): \WP_REST_Response {
		delete_option( self::TOKEN_OPTION );
		delete_transient( 'gsc_perf_summary' );
		delete_transient( 'quinrel_gsc_dashboard_v1' );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function getStatus( \WP_REST_Request $request ): \WP_REST_Response {
		$token  = get_option( self::TOKEN_OPTION );
		$config = $this->getConfig();
		return rest_ensure_response(
			array(
				'connected'         => ! empty( $token['access_token'] ),
				'expires_at'        => $token['expires_at'] ?? null,
				'site_url'          => home_url(),
				'selected_property' => $config['selected_property'] ?? home_url( '/' ),
			)
		);
	}

	public function getSites( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$token = $this->getValidToken();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_safe_remote_get(
			'https://www.googleapis.com/webmasters/v3/sites',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$sites = is_array( $data ) && isset( $data['siteEntry'] ) ? $data['siteEntry'] : array();

		return rest_ensure_response(
			array(
				'sites' => $sites,
			)
		);
	}

	public function setProperty( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$property = sanitize_text_field( $request->get_param( 'property' ) ?? '' );
		if ( empty( $property ) ) {
			return new \WP_Error( 'missing_property', __( 'Property identifier is required.', 'quinrel' ), array( 'status' => 400 ) );
		}

		$config                      = $this->getConfig();
		$config['selected_property'] = $property;
		$saved                       = $this->storeConfig( $config );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		delete_transient( 'gsc_perf_summary' );

		return rest_ensure_response(
			array(
				'success'           => true,
				'selected_property' => $property,
				'message'           => __( 'Google Search Console property updated successfully.', 'quinrel' ),
			)
		);
	}

	public function getPerformance( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$days    = min( 90, max( 1, (int) $request->get_param( 'days' ) ) );
		$page    = $request->get_param( 'page' );
		$keyword = $request->get_param( 'keyword' );

		$cacheKey = 'gsc_perf_' . md5( $days . $page . $keyword );
		$cached   = get_transient( $cacheKey );
		if ( $cached !== false ) {
			return rest_ensure_response( array_merge( $cached, array( 'cached' => true ) ) );
		}

		$token = $this->getValidToken();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$endDate   = gmdate( 'Y-m-d' );
		$startDate = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$body = array(
			'startDate'  => $startDate,
			'endDate'    => $endDate,
			'dimensions' => array( 'query', 'page' ),
			'rowLimit'   => 100,
		);

		if ( $page ) {
			$body['dimensionFilterGroups'] = array(
				array(
					'filters' => array(
						array(
							'dimension'  => 'page',
							'operator'   => 'equals',
							'expression' => $page,
						),
					),
				),
			);
		}

		if ( $keyword ) {
			$body['dimensionFilterGroups'] = array(
				array(
					'filters' => array(
						array(
							'dimension'  => 'query',
							'operator'   => 'contains',
							'expression' => $keyword,
						),
					),
				),
			);
		}

		$config   = $this->getConfig();
		$propUrl  = ! empty( $config['selected_property'] ) ? $config['selected_property'] : home_url( '/' );
		$siteUrl  = rawurlencode( $propUrl );
		$response = wp_safe_remote_post(
			"https://www.googleapis.com/webmasters/v3/sites/{$siteUrl}/searchAnalytics/query",
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $data['error'] ) ) {
			return new \WP_Error( 'gsc_error', $data['error']['message'] ?? __( 'GSC error.', 'quinrel' ), array( 'status' => 502 ) );
		}

		$result = array(
			'rows'        => $data['rows'] ?? array(),
			'period_days' => $days,
		);
		set_transient( $cacheKey, $result, self::CACHE_TTL );

		return rest_ensure_response( array_merge( $result, array( 'cached' => false ) ) );
	}

	public function getDecliningKeywords( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$token = $this->getValidToken();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$config  = $this->getConfig();
		$propUrl = ! empty( $config['selected_property'] ) ? $config['selected_property'] : home_url( '/' );
		$siteUrl = rawurlencode( $propUrl );
		$today   = gmdate( 'Y-m-d' );
		$periods = array(
			'recent' => array( gmdate( 'Y-m-d', strtotime( '-28 days' ) ), $today ),
			'prior'  => array( gmdate( 'Y-m-d', strtotime( '-56 days' ) ), gmdate( 'Y-m-d', strtotime( '-29 days' ) ) ),
		);

		$data = array();
		foreach ( $periods as $label => [$start, $end] ) {
			$response = wp_safe_remote_post(
				"https://www.googleapis.com/webmasters/v3/sites/{$siteUrl}/searchAnalytics/query",
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'startDate'  => $start,
							'endDate'    => $end,
							'dimensions' => array( 'query' ),
							'rowLimit'   => 200,
						)
					),
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$body           = json_decode( wp_remote_retrieve_body( $response ), true );
				$data[ $label ] = array_column( $body['rows'] ?? array(), null, 'keys' );
			}
		}

		// Compare: find keywords where position worsened or clicks dropped
		$declining = array();
		foreach ( $data['recent'] ?? array() as $keyArr => $row ) {
			$kw    = is_array( $row['keys'] ) ? $row['keys'][0] : $keyArr;
			$prior = $data['prior'][ $keyArr ] ?? null;
			if ( $prior && ( $row['clicks'] < ( $prior['clicks'] * 0.8 ) || $row['position'] > ( $prior['position'] + 3 ) ) ) {
				$declining[] = array(
					'keyword'          => $kw,
					'recent_clicks'    => $row['clicks'],
					'prior_clicks'     => $prior['clicks'],
					'recent_position'  => round( $row['position'], 1 ),
					'prior_position'   => round( $prior['position'], 1 ),
					'click_change_pct' => round( ( ( $row['clicks'] - $prior['clicks'] ) / max( 1, $prior['clicks'] ) ) * 100, 1 ),
				);
			}
		}

		usort( $declining, fn( $a, $b ) => $a['click_change_pct'] - $b['click_change_pct'] );

		return rest_ensure_response( array( 'declining_keywords' => array_slice( $declining, 0, 20 ) ) );
	}

	/**
	 * Query movement for the rank tracker. Search Console is optional, so a missing connection is an empty result.
	 *
	 * @return array{connected: bool, keywords: array<int, array<string, mixed>>, message: string}
	 */
	public function keywordMovement(): array {
		$token = $this->getValidToken();
		if ( is_wp_error( $token ) ) {
			return array(
				'connected' => false,
				'keywords'  => array(),
				'message'   => __( 'Connect Google Search Console to see how queries moved. A SerpApi key is not required.', 'quinrel' ),
			);
		}

		$config   = $this->getConfig();
		$property = ! empty( $config['selected_property'] ) ? (string) $config['selected_property'] : home_url( '/' );
		$cacheKey = 'quinrel_gsc_query_movement_' . md5( $property );
		$cached   = get_transient( $cacheKey );
		if ( is_array( $cached ) && isset( $cached['keywords'] ) && is_array( $cached['keywords'] ) ) {
			return $cached;
		}

		$today  = gmdate( 'Y-m-d' );
		$recent = $this->searchAnalyticsQueries(
			$token,
			$property,
			gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			$today
		);
		if ( is_wp_error( $recent ) ) {
			return array(
				'connected' => true,
				'keywords'  => array(),
				'message'   => __( 'Search Console did not return query positions.', 'quinrel' ),
			);
		}

		$prior    = $this->searchAnalyticsQueries(
			$token,
			$property,
			gmdate( 'Y-m-d', strtotime( '-56 days' ) ),
			gmdate( 'Y-m-d', strtotime( '-29 days' ) )
		);
		$keywords = self::movement( $recent, is_wp_error( $prior ) ? array() : $prior );
		$result   = array(
			'connected' => true,
			'keywords'  => $keywords,
			'message'   => array() === $keywords
				? __( 'Search Console has no queries for this site yet.', 'quinrel' )
				: '',
		);
		if ( ! is_wp_error( $prior ) ) {
			set_transient( $cacheKey, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/**
	 * Compare two Search Console query windows.
	 *
	 * A row may use `keys` or `query`. Positive change means the query moved up.
	 * A query with no prior row has a null change.
	 *
	 * @param array<int, mixed> $recentRows
	 * @param array<int, mixed> $priorRows
	 * @return array<int, array<string, mixed>>
	 */
	public static function movement( array $recentRows, array $priorRows ): array {
		$priorByQuery = array();
		foreach ( $priorRows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$query = self::movementQuery( $row );
			if ( '' === $query ) {
				continue;
			}
			$priorByQuery[ $query ] = $row;
		}

		$keywords = array();
		foreach ( $recentRows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$query = self::movementQuery( $row );
			if ( '' === $query ) {
				continue;
			}
			$position = round( self::movementNumber( $row, 'position' ), 1 );
			$previous = null;
			$change   = null;
			if ( isset( $priorByQuery[ $query ] ) ) {
				$previous = round( self::movementNumber( $priorByQuery[ $query ], 'position' ), 1 );
				$change   = round( $previous - $position, 1 );
			}
			$keywords[] = array(
				'query'             => $query,
				'position'          => $position,
				'previous_position' => $previous,
				'change'            => $change,
				'clicks'            => (int) round( self::movementNumber( $row, 'clicks' ) ),
				'impressions'       => (int) round( self::movementNumber( $row, 'impressions' ) ),
			);
		}

		usort(
			$keywords,
			static function ( array $left, array $right ): int {
				return $right['impressions'] <=> $left['impressions'];
			}
		);

		return array_slice( $keywords, 0, 200 );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function movementQuery( array $row ): string {
		if ( isset( $row['keys'] ) && is_array( $row['keys'] ) && isset( $row['keys'][0] ) && is_scalar( $row['keys'][0] ) ) {
			return trim( (string) $row['keys'][0] );
		}
		if ( isset( $row['query'] ) && is_scalar( $row['query'] ) ) {
			return trim( (string) $row['query'] );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function movementNumber( array $row, string $field ): float {
		$value = $row[ $field ] ?? 0;
		return is_numeric( $value ) ? (float) $value : 0.0;
	}

	/**
	 * One Search Console query window. The token stays in the request header.
	 *
	 * @return array<int, mixed>|\WP_Error
	 */
	private function searchAnalyticsQueries( string $token, string $property, string $startDate, string $endDate ): array|\WP_Error {
		$response = wp_safe_remote_post(
			'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $property ) . '/searchAnalytics/query',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'startDate'  => $startDate,
						'endDate'    => $endDate,
						'dimensions' => array( 'query' ),
						'rowLimit'   => 200,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || isset( $data['error'] ) ) {
			return new \WP_Error( 'gsc_error', __( 'Search Console did not return query positions.', 'quinrel' ), array( 'status' => 502 ) );
		}

		$rows = $data['rows'] ?? array();
		return is_array( $rows ) ? $rows : array();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	public function getValidToken(): string|\WP_Error {
		$token = $this->readToken();
		if ( empty( $token['access_token'] ) ) {
			return new \WP_Error( 'not_connected', __( 'Google Search Console not connected.', 'quinrel' ), array( 'status' => 401 ) );
		}

		// Refresh if expired
		if ( ! empty( $token['expires_at'] ) && time() > (int) $token['expires_at'] - 60 ) {
			if ( ! empty( $token['refresh_token'] ) ) {
				$refreshed = $this->refreshToken( $token['refresh_token'] );
				if ( is_wp_error( $refreshed ) ) {
					return $refreshed;
				}
				$written = $this->writeToken( $refreshed );
				if ( is_wp_error( $written ) ) {
					return $written;
				}
				$token = $refreshed;
			}
		}

		return $token['access_token'];
	}

	private function exchangeCode( string $code, array $config ): array|\WP_Error {
		$response = wp_safe_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $config['client_id'],
					'client_secret' => $config['client_secret'],
					'redirect_uri'  => rest_url( 'quinrel/v1/gsc/callback' ),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $data['error'] ) ) {
			return new \WP_Error( 'oauth_error', $data['error_description'] ?? __( 'OAuth error.', 'quinrel' ), array( 'status' => 400 ) );
		}

		return array(
			'access_token'  => $data['access_token'],
			'refresh_token' => $data['refresh_token'] ?? '',
			'expires_at'    => time() + (int) ( $data['expires_in'] ?? 3600 ),
		);
	}

	private function refreshToken( string $refreshToken ): array|\WP_Error {
		$config   = $this->getConfig();
		$response = wp_safe_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'refresh_token' => $refreshToken,
					'client_id'     => $config['client_id'],
					'client_secret' => $config['client_secret'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $data['error'] ) ) {
			return new \WP_Error( 'refresh_error', $data['error_description'] ?? __( 'Token refresh error.', 'quinrel' ), array( 'status' => 401 ) );
		}

		$existing = $this->readToken();
		return array_merge(
			$existing,
			array(
				'access_token' => $data['access_token'],
				'expires_at'   => time() + (int) ( $data['expires_in'] ?? 3600 ),
			)
		);
	}

	public function getConfig(): array {
		$config = get_option(
			self::CONFIG_OPTION,
			array(
				'client_id'     => '',
				'client_secret' => '',
			)
		);
		if ( ! is_array( $config ) ) {
			$config = array(
				'client_id'     => '',
				'client_secret' => '',
			);
		}

		$config['client_id']     = isset( $config['client_id'] ) ? (string) $config['client_id'] : '';
		$secret                  = isset( $config['client_secret'] ) ? (string) $config['client_secret'] : '';
		$config['client_secret'] = $secret === '' ? '' : KeyVault::decrypt( $secret );
		return $config;
	}

	/**
	 * Persist Search Console config. The client secret is stored as ciphertext or not at all.
	 *
	 * @param array<string, mixed> $config
	 * @return true|\WP_Error
	 */
	public function storeConfig( array $config ): true|\WP_Error {
		$secret = isset( $config['client_secret'] ) ? (string) $config['client_secret'] : '';
		if ( $secret !== '' ) {
			$encrypted = $this->seal( $secret );
			if ( is_wp_error( $encrypted ) ) {
				return $encrypted;
			}
			$config['client_secret'] = $encrypted;
		}

		update_option( self::CONFIG_OPTION, $config, false );
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readToken(): array {
		$token = get_option( self::TOKEN_OPTION, array() );
		if ( ! is_array( $token ) ) {
			return array();
		}

		foreach ( array( 'access_token', 'refresh_token' ) as $field ) {
			if ( isset( $token[ $field ] ) && is_string( $token[ $field ] ) && $token[ $field ] !== '' ) {
				$token[ $field ] = KeyVault::decrypt( $token[ $field ] );
			}
		}

		return $token;
	}

	/**
	 * @param array<string, mixed> $token
	 * @return true|\WP_Error
	 */
	private function writeToken( array $token ): true|\WP_Error {
		foreach ( array( 'access_token', 'refresh_token' ) as $field ) {
			if ( ! isset( $token[ $field ] ) || ! is_string( $token[ $field ] ) || $token[ $field ] === '' ) {
				continue;
			}
			$encrypted = $this->seal( $token[ $field ] );
			if ( is_wp_error( $encrypted ) ) {
				return $encrypted;
			}
			$token[ $field ] = $encrypted;
		}

		update_option( self::TOKEN_OPTION, $token, false );
		return true;
	}

	/**
	 * @return string|\WP_Error
	 */
	private function seal( string $plaintext ): string|\WP_Error {
		if ( $plaintext === '' ) {
			return '';
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

		return $encrypted;
	}
}
