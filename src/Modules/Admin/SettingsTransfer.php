<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Ai\OllamaEndpoint;
use Quinrel\WordPress\Core\Security\KeyVault;

/**
 * Settings export and import.
 *
 * Export omits secrets: indexing JSON, the IndexNow key, API keys, OAuth
 * secrets, and the encryption salt. A full database copy can still decrypt
 * stored ciphertext because the salt remains in wp_options.
 *
 * Import accepts only the public allow-list. Each field is checked for its
 * own shape. A key outside that list and the known secret list is rejected,
 * and then nothing is written.
 */
class SettingsTransfer {

	/**
	 * Public options and the shape import accepts.
	 *
	 * @var array<string, string>
	 */
	private const FIELDS = array(
		'quinrel_auto_index'                 => 'bool',
		'quinrel_auto_slug_redirects'        => 'bool',
		'quinrel_enable_google_indexing_api' => 'bool',
		'quinrel_google_verify'              => 'token',
		'quinrel_bing_verify'                => 'token',
		'quinrel_yandex_verify'              => 'token',
		'quinrel_pinterest_verify'           => 'token',
		'quinrel_rss_before_content'         => 'html',
		'quinrel_rss_after_content'          => 'html',
		'quinrel_breadcrumb_separator'       => 'separator',
		'quinrel_breadcrumb_home_label'      => 'text',
		'quinrel_breadcrumb_auto_insert'     => 'breadcrumb_insert',
		'quinrel_sitemap_exclude_post_types' => 'slug_list',
		'quinrel_sitemap_exclude_posts'      => 'id_list',
		'quinrel_ai_provider'                => 'ai_provider',
		'quinrel_ollama_url'                 => 'ollama',
		'quinrel_default_share_image'        => 'url',
		'quinrel_ga4_measurement_id'         => 'ga4_measurement',
		'quinrel_ga4_property_id'            => 'ga4_property',
		'quinrel_html_sitemap_post_types'    => 'slug_list',
		'quinrel_html_sitemap_exclude_ids'   => 'id_list',
		'quinrel_html_sitemap_depth'         => 'depth',
		'quinrel_html_sitemap_order'         => 'sitemap_order',
		'quinrel_html_sitemap_show_count'    => 'bool',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function exportPayload(): array {
		$settings = array();
		foreach ( self::FIELDS as $key => $kind ) {
			if ( self::isSecret( $key ) ) {
				continue;
			}
			$settings[ $key ] = self::exportValue( $key, $kind );
		}

		foreach ( self::secretOptions() as $secret ) {
			unset( $settings[ $secret ] );
		}

		$redacted = self::secretOptions();
		sort( $redacted );

		return array(
			'_version'  => '1.0',
			'_plugin'   => 'quinrel',
			'_exported' => gmdate( 'c' ),
			'_site_url' => (string) get_option( 'siteurl', '' ),
			'_redacted' => $redacted,
			'settings'  => $settings,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function importPayload( array $payload ): array|\WP_Error {
		if ( ( $payload['_plugin'] ?? '' ) !== 'quinrel' ) {
			return new \WP_Error(
				'wrong_plugin',
				__( 'This settings file was not exported from Quinrel.', 'quinrel' ),
				array( 'status' => 422 )
			);
		}

		$settings = $payload['settings'] ?? null;
		if ( ! is_array( $settings ) || $settings === array() ) {
			return new \WP_Error(
				'invalid_payload',
				__( 'Missing or malformed settings object.', 'quinrel' ),
				array( 'status' => 422 )
			);
		}

		$clean  = array();
		$ollama = null;

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) ) {
				return self::unknownSetting();
			}
			if ( self::isSecret( $key ) ) {
				continue;
			}

			if ( ! isset( self::FIELDS[ $key ] ) ) {
				return self::unknownSetting();
			}

			if ( self::FIELDS[ $key ] === 'ollama' ) {
				if ( ! is_string( $value ) ) {
					return self::invalidSetting( $key );
				}
				$ollama = $value;
				continue;
			}

			$sanitized = self::sanitize( self::FIELDS[ $key ], $value );
			if ( is_wp_error( $sanitized ) ) {
				return self::invalidSetting( $key );
			}
			$clean[ $key ] = $sanitized;
		}

		$imported = 0;
		foreach ( $clean as $key => $value ) {
			update_option( $key, $value );
			++$imported;
		}

		if ( is_string( $ollama ) && OllamaEndpoint::import( $ollama ) ) {
			++$imported;
		}

		return array(
			'success'  => true,
			'imported' => $imported,
			'message'  => sprintf(
				/* translators: %d: number of imported settings. */
				__( 'Imported %d settings successfully. API keys and credentials are excluded from import for security.', 'quinrel' ),
				$imported
			),
		);
	}

	/**
	 * Option names whose values must not appear in an export or be written by import.
	 *
	 * @return array<int, string>
	 */
	public static function secretOptions(): array {
		return array_values(
			array_unique(
				array_merge(
					KeyVault::neverExportOptions(),
					array(
						'quinrel_google_indexing_key',
						'quinrel_indexnow_key',
						'quinrel_openai_key',
						'quinrel_anthropic_key',
						'quinrel_api_key',
						'quinrel_license_key',
						'quinrel_user_tier',
						'quinrel_backend_api_url',
						'quinrel_cloud_verification',
						'quinrel_cloud_revoked',
						'quinrel_ga4_credentials',
						'quinrel_social_apps',
						'quinrel_social_accounts',
						'google_indexing_key',
						'ga4_credentials',
						'openai_api_key',
						'anthropic_api_key',
						'gsc_api_key',
						'bing_api_key',
					)
				)
			)
		);
	}

	public static function isSecret( string $option ): bool {
		return in_array( $option, self::secretOptions(), true );
	}

	/**
	 * @return string|int
	 */
	private static function exportValue( string $key, string $kind ): string|int {
		$stored = get_option( $key, '' );
		if ( is_array( $stored ) || is_object( $stored ) ) {
			return '';
		}

		if ( $kind === 'html' ) {
			return wp_kses_post( (string) $stored );
		}

		if ( $kind === 'depth' && is_numeric( $stored ) ) {
			return (int) $stored;
		}

		if ( is_bool( $stored ) ) {
			return $stored ? 'yes' : 'no';
		}

		return is_scalar( $stored ) ? (string) $stored : '';
	}

	/**
	 * @return string|int|\WP_Error
	 */
	private static function sanitize( string $kind, mixed $value ): string|int|\WP_Error {
		if ( $kind === 'bool' ) {
			return self::sanitizeBool( $value );
		}

		if ( $kind === 'depth' ) {
			return self::sanitizeDepth( $value );
		}

		if ( ! is_string( $value ) ) {
			return self::invalidValue();
		}

		return match ( $kind ) {
			'token' => self::sanitizeToken( $value ),
			'html' => self::sanitizeHtml( $value ),
			'separator' => self::sanitizeSeparator( $value ),
			'text' => self::sanitizeText( $value, 100 ),
			'breadcrumb_insert' => self::sanitizeEnum( $value, array( 'none', 'before_content' ), 'none' ),
			'slug_list' => self::sanitizeSlugList( $value ),
			'id_list' => self::sanitizeIdList( $value ),
			'ai_provider' => self::sanitizeEnum( $value, array( 'openai', 'anthropic', 'ollama' ), 'openai' ),
			'url' => self::sanitizeUrl( $value ),
			'ga4_measurement' => self::sanitizeMeasurementId( $value ),
			'ga4_property' => self::sanitizePropertyId( $value ),
			'sitemap_order' => self::sanitizeEnum( $value, array( 'menu_order', 'title', 'date', 'modified' ), 'menu_order' ),
			default => self::invalidValue(),
		};
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeBool( mixed $value ): string|\WP_Error {
		if ( $value === true || $value === 1 ) {
			return 'yes';
		}
		if ( $value === false || $value === 0 || $value === '' ) {
			return 'no';
		}
		if ( ! is_string( $value ) ) {
			return self::invalidValue();
		}

		$normalized = strtolower( trim( $value ) );
		if ( in_array( $normalized, array( 'yes', 'true', '1' ), true ) ) {
			return 'yes';
		}
		if ( in_array( $normalized, array( 'no', 'false', '0' ), true ) ) {
			return 'no';
		}

		return self::invalidValue();
	}

	/**
	 * @return int|\WP_Error
	 */
	private static function sanitizeDepth( mixed $value ): int|\WP_Error {
		if ( $value === '' || $value === null ) {
			return 0;
		}
		if ( is_bool( $value ) || is_array( $value ) ) {
			return self::invalidValue();
		}
		if ( is_int( $value ) ) {
			$depth = $value;
		} elseif ( is_string( $value ) && preg_match( '/^(0|[1-9][0-9]*)$/', $value ) === 1 ) {
			$depth = (int) $value;
		} else {
			return self::invalidValue();
		}

		if ( $depth < 0 || $depth > 10 ) {
			return self::invalidValue();
		}

		return $depth;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeToken( string $value ): string|\WP_Error {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/^[A-Za-z0-9._+\/=-]{1,200}$/', $value ) !== 1 ) {
			return self::invalidValue();
		}
		return $value;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeHtml( string $value ): string|\WP_Error {
		$safe = wp_kses_post( $value );
		if ( strlen( $safe ) > 10000 ) {
			return self::invalidValue();
		}
		return $safe;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeSeparator( string $value ): string|\WP_Error {
		$clean  = sanitize_text_field( $value );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $clean ) : strlen( $clean );
		if ( $length > 16 ) {
			return self::invalidValue();
		}
		return $clean;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeText( string $value, int $maxLength ): string|\WP_Error {
		$clean  = sanitize_text_field( $value );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $clean ) : strlen( $clean );
		if ( $length > $maxLength ) {
			return self::invalidValue();
		}
		return $clean;
	}

	/**
	 * @param array<int, string> $allowed
	 * @return string|\WP_Error
	 */
	private static function sanitizeEnum( string $value, array $allowed, string $emptyDefault ): string|\WP_Error {
		$value = strtolower( trim( $value ) );
		if ( $value === '' ) {
			return $emptyDefault;
		}
		if ( ! in_array( $value, $allowed, true ) ) {
			return self::invalidValue();
		}
		return $value;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeSlugList( string $value ): string|\WP_Error {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		$slugs = array();
		foreach ( explode( ',', $value ) as $part ) {
			$part = strtolower( trim( $part ) );
			if ( $part === '' ) {
				continue;
			}
			if ( preg_match( '/^[a-z0-9_-]{1,20}$/', $part ) !== 1 ) {
				return self::invalidValue();
			}
			$slugs[] = $part;
		}

		$slugs = array_values( array_unique( $slugs ) );
		if ( count( $slugs ) > 30 ) {
			return self::invalidValue();
		}

		return implode( ',', $slugs );
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeIdList( string $value ): string|\WP_Error {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		$ids = array();
		foreach ( explode( ',', $value ) as $part ) {
			$part = trim( $part );
			if ( $part === '' ) {
				continue;
			}
			if ( preg_match( '/^[1-9][0-9]{0,9}$/', $part ) !== 1 ) {
				return self::invalidValue();
			}
			$ids[] = $part;
		}

		$ids = array_values( array_unique( $ids ) );
		if ( count( $ids ) > 500 ) {
			return self::invalidValue();
		}

		return implode( ',', $ids );
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeUrl( string $value ): string|\WP_Error {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/[[:cntrl:][:space:]]/', $value ) === 1 ) {
			return self::invalidValue();
		}

		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return self::invalidValue();
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = (string) ( $parts['host'] ?? '' );
		if ( ( $scheme !== 'http' && $scheme !== 'https' ) || $host === '' ) {
			return self::invalidValue();
		}

		$clean = esc_url_raw( $value );
		if ( $clean === '' || strlen( $clean ) > 2000 ) {
			return self::invalidValue();
		}

		return $clean;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizeMeasurementId( string $value ): string|\WP_Error {
		$value = strtoupper( trim( $value ) );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/^G-[A-Z0-9]{4,20}$/', $value ) !== 1 ) {
			return self::invalidValue();
		}
		return $value;
	}

	/**
	 * @return string|\WP_Error
	 */
	private static function sanitizePropertyId( string $value ): string|\WP_Error {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/^[1-9][0-9]{0,15}$/', $value ) !== 1 ) {
			return self::invalidValue();
		}
		return $value;
	}

	private static function unknownSetting(): \WP_Error {
		return new \WP_Error(
			'unknown_setting',
			__( 'This settings file contains a setting Quinrel does not import. Nothing was changed.', 'quinrel' ),
			array( 'status' => 422 )
		);
	}

	private static function invalidSetting( string $key ): \WP_Error {
		return new \WP_Error(
			'invalid_setting',
			sprintf(
				/* translators: %s: setting name. */
				__( 'The setting %s has a value Quinrel cannot import. Nothing was changed.', 'quinrel' ),
				$key
			),
			array( 'status' => 422 )
		);
	}

	private static function invalidValue(): \WP_Error {
		return new \WP_Error( 'invalid_setting', '', array( 'status' => 422 ) );
	}
}
