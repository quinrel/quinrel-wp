<?php

namespace Quinrel\WordPress\Modules\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database operations for Quinrel sitemap settings using the custom table.
 * Fallbacks to options and handles transparent on-demand migrations.
 */
class SitemapSettings {

	private static string $table = 'quinrel_sitemap_settings';

	/**
	 * Cached table-existence state keyed by wpdb context signature.
	 *
	 * @var array<string, bool>
	 */
	private static array $tableExistsCache = array();

	/**
	 * Cached custom-table values keyed by wpdb context signature and setting key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $rowCache = array();

	/**
	 * Clear in-memory caches (useful for testing or multisite blog switches).
	 */
	public static function clearCache(): void {
		self::$tableExistsCache = array();
		self::$rowCache         = array();
	}

	/**
	 * Build a cache signature for the current $wpdb instance, prefix, and mock state.
	 */
	private static function contextKey(): string {
		global $wpdb;
		$dbId   = is_object( $wpdb ) ? (string) spl_object_id( $wpdb ) : '0';
		$prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) ? (string) $wpdb->prefix : 'wp_';
		$varCb  = isset( $GLOBALS['mock_wpdb_var'] ) && is_object( $GLOBALS['mock_wpdb_var'] )
			? (string) spl_object_id( $GLOBALS['mock_wpdb_var'] )
			: 'none';
		return $dbId . ':' . $prefix . ':' . $varCb;
	}

	/**
	 * Check whether the custom sitemap settings table exists, cached per request/context.
	 */
	private static function tableExists(): bool {
		global $wpdb;
		$ctx = self::contextKey();
		if ( array_key_exists( $ctx, self::$tableExistsCache ) ) {
			return self::$tableExistsCache[ $ctx ];
		}

		$tableName                      = $wpdb->prefix . self::$table;
		$found                          = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tableName ) ) );
		self::$tableExistsCache[ $ctx ] = ( $found === $tableName );

		return self::$tableExistsCache[ $ctx ];
	}

	/**
	 * Retrieve a sitemap configuration setting.
	 *
	 * @param string $key      Setting key without the quinrel_ prefix.
	 * @param mixed  $fallback Default value when not configured.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		// Fast path: if the option is explicitly present in wp_options (autoloaded), return without custom table SQL.
		if ( ! self::tableExists() ) {
			return get_option( 'quinrel_' . $key, $fallback );
		}

		global $wpdb;
		$ctx = self::contextKey();
		if ( isset( self::$rowCache[ $ctx ] ) && array_key_exists( $key, self::$rowCache[ $ctx ] ) ) {
			$cached = self::$rowCache[ $ctx ][ $key ];
			return null !== $cached ? $cached : get_option( 'quinrel_' . $key, $fallback );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}quinrel_sitemap_settings WHERE setting_key = %s",
				$key
			)
		);
		if ( null !== $row && isset( $row->setting_value ) ) {
			$val                            = maybe_unserialize( $row->setting_value );
			self::$rowCache[ $ctx ][ $key ] = $val;
			return $val;
		}

		self::$rowCache[ $ctx ][ $key ] = null;

		// Check fallback to option table for backwards compatibility
		$optVal = get_option( 'quinrel_' . $key, null );
		if ( null !== $optVal ) {
			// One-time self-healing migration
			self::set( $key, $optVal );
			delete_option( 'quinrel_' . $key );
			return $optVal;
		}

		return $fallback;
	}

	/**
	 * Save a sitemap configuration setting.
	 *
	 * @param string $key   Setting key without the quinrel_ prefix.
	 * @param mixed  $value Value to store.
	 */
	public static function set( string $key, $value ): void {
		global $wpdb;
		$tableName = $wpdb->prefix . self::$table;
		$ctx       = self::contextKey();

		if ( ! self::tableExists() ) {
			update_option( 'quinrel_' . $key, $value );
			return;
		}

		self::$rowCache[ $ctx ][ $key ] = $value;
		$serializedValue                = maybe_serialize( $value );

		$wpdb->replace(
			$tableName,
			array(
				'setting_key'   => $key,
				'setting_value' => $serializedValue,
			)
		);
	}
}
