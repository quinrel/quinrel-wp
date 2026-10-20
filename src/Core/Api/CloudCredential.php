<?php

namespace Quinrel\WordPress\Core\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Mode\OperatingMode;
use Quinrel\WordPress\Core\Security\KeyVault;

/**
 * Site credential for api.quinrel.com.
 *
 * Exchange, verify, rotate, and revoke run only when an administrator uses
 * the connection screen. A page view reads the stored result and does not
 * call the API. The settings screen cannot change the host.
 */
class CloudCredential {

	public const API_KEY_OPTION      = 'quinrel_api_key';
	public const LICENSE_OPTION      = 'quinrel_license_key';
	public const VERIFICATION_OPTION = 'quinrel_cloud_verification';
	public const TIER_OPTION         = 'quinrel_user_tier';
	public const TIMEOUT             = 10;

	private const HOST = 'https://api.quinrel.com';

	/**
	 * @var array<int, string>
	 */
	private const TIERS = array( 'free', 'pro', 'enterprise' );

	public function registerRoutes(): void {
		$permission = array( $this, 'canManage' );

		register_rest_route(
			'quinrel/v1',
			'/connection',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/connection/site-token',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'connectSiteToken' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/connection/authorization-code',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'connectAuthorizationCode' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/connection/verify',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'recheck' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/connection/rotate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rotate' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/connection/revoke',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'revoke' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/connection/auto-connect',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'autoConnect' ),
				'permission_callback' => $permission,
			)
		);
	}

	public function canManage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * A verified credential is stored, it has not been revoked, and the
	 * verification cache has not expired. This reads options only.
	 */
	public static function isActive(): bool {
		if ( get_option( OperatingMode::REVOKED_OPTION, '' ) === 'yes' ) {
			return false;
		}

		$token = OperatingMode::credential();
		if ( $token === '' ) {
			return false;
		}

		$record = get_option( self::VERIFICATION_OPTION, array() );
		if ( ! is_array( $record ) ) {
			return false;
		}

		$hash    = $record['token_hash'] ?? '';
		$expires = $record['expires_at'] ?? 0;
		if ( ! is_string( $hash ) || $hash === '' || ! is_numeric( $expires ) ) {
			return false;
		}

		if ( time() >= (int) $expires ) {
			return false;
		}

		return hash_equals( $hash, hash( 'sha256', $token ) );
	}

	/**
	 * Tier from the last successful verification. A settings value is ignored.
	 */
	public static function cachedTier(): string {
		if ( ! self::isActive() ) {
			return 'free';
		}

		$record = get_option( self::VERIFICATION_OPTION, array() );
		$tier   = is_array( $record ) ? (string) ( $record['tier'] ?? '' ) : '';
		if ( ! in_array( $tier, self::TIERS, true ) ) {
			return 'free';
		}

		return $tier;
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( \WP_REST_Request $request ) {
		unset( $request );
		if ( ! $this->canManage() ) {
			return $this->forbidden();
		}

		return rest_ensure_response( $this->statusPayload() );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function connectSiteToken( \WP_REST_Request $request ) {
		if ( ! $this->canManage() ) {
			return $this->forbidden();
		}

		$token = $this->presentedSecret( $request, 'site_token' );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$exchanged = $this->request(
			'POST',
			'/auth/site-token',
			array(
				'grant_type' => 'site_token',
				'site_token' => $token,
				'site_url'   => home_url( '/' ),
			),
			''
		);
		if ( is_wp_error( $exchanged ) ) {
			return $exchanged;
		}

		return $this->storeExchanged( $exchanged );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function connectAuthorizationCode( \WP_REST_Request $request ) {
		if ( ! $this->canManage() ) {
			return $this->forbidden();
		}

		$code = $this->presentedSecret( $request, 'code' );
		if ( is_wp_error( $code ) ) {
			return $code;
		}

		$exchanged = $this->request(
			'POST',
			'/auth/token',
			array(
				'grant_type' => 'authorization_code',
				'code'       => $code,
				'site_url'   => home_url( '/' ),
			),
			''
		);
		if ( is_wp_error( $exchanged ) ) {
			return $exchanged;
		}

		return $this->storeExchanged( $exchanged );
	}

	/**
	 * Automatically pair this site with Quinrel Cloud without manual token copy/paste.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function autoConnect( \WP_REST_Request $request ) {
		if ( ! $this->canManage() ) {
			return $this->forbidden();
		}

		$siteUrl  = home_url( '/' );
		$siteName = get_bloginfo( 'name' );

		$exchanged = $this->request(
			'POST',
			'/auth/auto-connect',
			array(
				'site_url'  => $siteUrl,
				'site_name' => $siteName,
				'admin_url' => admin_url(),
			),
			''
		);

		if ( is_wp_error( $exchanged ) ) {
			return rest_ensure_response(
				array(
					'requires_redirect' => true,
					'auth_url'          => 'https://api.quinrel.com/oauth/authorize?site_url=' . rawurlencode( $siteUrl ) . '&return_url=' . rawurlencode( admin_url( 'admin.php?page=quinrel&tab=settings_cloud' ) ),
					'message'           => __( 'Redirecting to Quinrel Cloud for automated 1-click authorization…', 'quinrel' ),
				)
			);
		}

		return $this->storeExchanged( $exchanged );
	}

	/**
	 * Ask the API about the credential already stored on this site.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function recheck( \WP_REST_Request $request ) {
		unset( $request );
		if ( ! $this->canManage() ) {
			return $this->forbidden();
		}

		$token = OperatingMode::credential();
		if ( $token === '' ) {
			return $this->notConnected();
		}

		$verified = $this->request( 'GET', '/auth/verify', array(), $token );
		if ( is_wp_error( $verified ) ) {
			$data   = $verified->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
			// A transport failure leaves the saved check in place. A rejected
			// credential stops counting as connected, and the ciphertext stays
			// so disconnect can still run.
			if ( 502 === $status ) {
				return $verified;
			}

			delete_option( self::VERIFICATION_OPTION );
			delete_option( self::TIER_OPTION );
			return new \WP_Error(
				'quinrel_not_verified',
				__( 'Quinrel did not confirm the saved credential. It is no longer treated as connected. SEO data was kept.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		if ( ( $verified['active'] ?? null ) !== true ) {
			delete_option( self::VERIFICATION_OPTION );
			delete_option( self::TIER_OPTION );
			return new \WP_Error(
				'quinrel_not_verified',
				__( 'Quinrel did not confirm the saved credential. It is no longer treated as connected. SEO data was kept.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		$saved = $this->remember( $token, $this->tierFrom( $verified ) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return rest_ensure_response( $this->statusPayload() );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rotate( \WP_REST_Request $request ) {
		unset( $request );
		if ( ! $this->canManage() ) {
			return $this->forbidden();
		}

		$token = OperatingMode::credential();
		if ( $token === '' ) {
			return $this->notConnected();
		}

		$rotated = $this->request(
			'POST',
			'/auth/rotate',
			array(
				'site_url' => home_url( '/' ),
			),
			$token
		);
		if ( is_wp_error( $rotated ) ) {
			return $rotated;
		}

		return $this->storeExchanged( $rotated );
	}

	/**
	 * Remove the local credential. A remote revoke is attempted once, with one retry.
	 *
	 * SEO data is not deleted. If the API does not answer, the local credential
	 * is still removed.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function revoke( \WP_REST_Request $request ) {
		unset( $request );
		if ( ! $this->canManage() ) {
			return $this->forbidden();
		}

		$token        = OperatingMode::credential();
		$remoteFailed = false;
		if ( $token !== '' ) {
			$remote       = $this->request(
				'POST',
				'/auth/revoke',
				array(
					'site_url' => home_url( '/' ),
				),
				$token
			);
			$remoteFailed = is_wp_error( $remote );
		}

		$this->forget();

		if ( $remoteFailed ) {
			return rest_ensure_response(
				array(
					'disconnected'   => true,
					'remote_revoked' => false,
					'message'        => __( 'The Quinrel credential was removed from this site. SEO data was kept. Quinrel could not be reached to revoke the remote token.', 'quinrel' ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'disconnected'   => true,
				'remote_revoked' => $token !== '',
				'message'        => __( 'The Quinrel credential was removed from this site. SEO data was kept.', 'quinrel' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $exchanged
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function storeExchanged( array $exchanged ) {
		$access = $exchanged['access_token'] ?? '';
		if ( ! is_string( $access ) || ! $this->isCredentialShape( $access ) ) {
			return new \WP_Error(
				'quinrel_not_verified',
				__( 'Quinrel did not return a site credential. Nothing was saved.', 'quinrel' ),
				array( 'status' => 502 )
			);
		}

		$verified = $this->request( 'GET', '/auth/verify', array(), $access );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		if ( ( $verified['active'] ?? null ) !== true ) {
			return new \WP_Error(
				'quinrel_not_verified',
				__( 'Quinrel did not confirm this credential. Nothing was saved.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		$saved = $this->remember( $access, $this->tierFrom( $verified ) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return rest_ensure_response( $this->statusPayload() );
	}

	/**
	 * @return true|\WP_Error
	 */
	private function remember( string $token, string $tier ) {
		$encrypted = KeyVault::encrypt( $token );
		if ( is_wp_error( $encrypted ) || $encrypted === '' ) {
			if ( is_wp_error( $encrypted ) ) {
				return $encrypted;
			}

			return new \WP_Error(
				'quinrel_encryption_failed',
				__( 'Quinrel could not store this credential. Nothing was saved.', 'quinrel' ),
				array( 'status' => 500 )
			);
		}

		update_option( self::API_KEY_OPTION, $encrypted, false );
		delete_option( self::LICENSE_OPTION );
		delete_option( OperatingMode::REVOKED_OPTION );
		delete_option( self::TIER_OPTION );
		update_option(
			self::VERIFICATION_OPTION,
			array(
				'token_hash' => hash( 'sha256', $token ),
				'expires_at' => time() + DAY_IN_SECONDS,
				'tier'       => in_array( $tier, self::TIERS, true ) ? $tier : '',
			),
			false
		);

		return true;
	}

	private function forget(): void {
		delete_option( self::API_KEY_OPTION );
		delete_option( self::LICENSE_OPTION );
		delete_option( self::VERIFICATION_OPTION );
		delete_option( self::TIER_OPTION );
		delete_option( OperatingMode::REVOKED_OPTION );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|\WP_Error
	 */
	private function request( string $method, string $path, array $payload, string $bearer ) {
		$url     = ( new BackendApiClient() )->getApiUrl() . $path;
		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'Quinrel-WP/' . ( defined( 'QUINREL_VERSION' ) ? QUINREL_VERSION : '1.0.1' ),
		);
		if ( $bearer !== '' ) {
			$headers['Authorization'] = 'Bearer ' . $bearer;
		}
		if ( $method === 'POST' ) {
			$headers['Content-Type'] = 'application/json';
		}

		for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
			$args = array(
				'headers' => $headers,
				'timeout' => self::TIMEOUT,
			);
			if ( $method === 'POST' ) {
				$args['body'] = wp_json_encode( $payload );
				$response     = wp_safe_remote_post( $url, $args );
			} else {
				$response = wp_safe_remote_get( $url, $args );
			}

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) ) {
				$data = array();
			}

			if ( $this->shouldRetry( $code ) ) {
				continue;
			}

			if ( $code < 200 || $code >= 300 ) {
				return new \WP_Error(
					'quinrel_connection_failed',
					$this->failureMessage( $data ),
					array( 'status' => 400 )
				);
			}

			return $data;
		}

		return new \WP_Error(
			'quinrel_connection_failed',
			__( 'Quinrel could not be reached. This site keeps working without an account.', 'quinrel' ),
			array( 'status' => 502 )
		);
	}

	private function shouldRetry( int $code ): bool {
		return $code === 408 || $code === 429 || $code >= 500;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function failureMessage( array $data ): string {
		$message = $data['message'] ?? '';
		if ( ! is_string( $message ) || $message === '' ) {
			return __( 'Quinrel did not accept this credential. Nothing was saved.', 'quinrel' );
		}

		$clean = sanitize_text_field( $message );
		if ( strlen( $clean ) > 180 ) {
			$clean = substr( $clean, 0, 180 );
		}

		return $clean;
	}

	/**
	 * @return string|\WP_Error
	 */
	private function presentedSecret( \WP_REST_Request $request, string $field ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$value = $params[ $field ] ?? '';
		if ( ! is_string( $value ) || ! $this->isCredentialShape( trim( $value ) ) ) {
			return new \WP_Error(
				'quinrel_invalid_credential',
				__( 'Enter the site token or authorization code from Quinrel. The settings screen cannot change the Quinrel API host.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		return trim( $value );
	}

	private function isCredentialShape( string $value ): bool {
		return preg_match( '/^[A-Za-z0-9._~+\/=-]{20,512}$/', $value ) === 1;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function tierFrom( array $payload ): string {
		$tier = $payload['tier'] ?? '';
		return is_string( $tier ) ? $tier : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function statusPayload(): array {
		$active = self::isActive();
		return array(
			'connected'        => $active,
			'credential_saved' => OperatingMode::credential() !== '',
			'tier'             => $active ? self::cachedTier() : '',
			'host'             => self::HOST,
			'api_base'         => ( new BackendApiClient() )->getApiUrl(),
		);
	}

	private function forbidden(): \WP_Error {
		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to manage the Quinrel connection.', 'quinrel' ),
			array( 'status' => 403 )
		);
	}

	private function notConnected(): \WP_Error {
		return new \WP_Error(
			'quinrel_not_connected',
			__( 'This site is not connected to Quinrel. You can keep using Quinrel on this site.', 'quinrel' ),
			array( 'status' => 400 )
		);
	}
}
