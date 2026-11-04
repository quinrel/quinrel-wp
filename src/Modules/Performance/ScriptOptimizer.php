<?php

namespace Quinrel\WordPress\Modules\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ScriptOptimizer
 *
 * Automatically appends defer attributes to non-critical front-end scripts,
 * eliminating Lighthouse "Eliminate render-blocking resources" penalties
 * and improving First Contentful Paint (FCP) and LCP.
 */
class ScriptOptimizer {

	private const CORE_EXCLUSIONS = array(
		'jquery',
		'jquery-core',
		'jquery-migrate',
		'wp-polyfill',
		'wp-i18n',
		'wp-hooks',
	);

	public function register(): void {
		if ( get_option( 'quinrel_defer_non_critical_scripts', 'no' ) !== 'yes' ) {
			return;
		}
		if ( is_admin() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return;
		}

		add_filter( 'script_loader_tag', array( $this, 'filterScriptTag' ), 10, 3 );
	}

	/**
	 * Store at most 40 script handles from a comma- or whitespace-separated list.
	 */
	public static function sanitizeHandleList( string $raw ): string {
		$parts = preg_split( '/[\s,]+/', $raw );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$handles = array();
		foreach ( $parts as $part ) {
			$handle = sanitize_key( $part );
			if ( $handle === '' || in_array( $handle, $handles, true ) ) {
				continue;
			}
			$handles[] = $handle;
			if ( count( $handles ) >= 40 ) {
				break;
			}
		}

		return implode( ',', $handles );
	}

	/**
	 * @return array<int, string>
	 */
	public static function listedHandles(): array {
		$stored = (string) get_option( 'quinrel_defer_script_handles', '' );
		if ( $stored === '' ) {
			return array();
		}

		$handles = explode( ',', self::sanitizeHandleList( $stored ) );
		return array_values( array_filter( $handles, static fn( string $handle ): bool => $handle !== '' ) );
	}

	/**
	 * Append defer="defer" to eligible front-end script tags.
	 *
	 * @param string $tag    The HTML <script> tag.
	 * @param string $handle The registered script handle.
	 * @param string $src    The script source URL.
	 * @return string
	 */
	public function filterScriptTag( string $tag, string $handle, string $src ): string {
		if ( get_option( 'quinrel_defer_non_critical_scripts', 'no' ) !== 'yes' ) {
			return $tag;
		}

		// An empty list defers nothing. Core handles stay blocking even if listed.
		if ( ! in_array( $handle, self::listedHandles(), true ) ) {
			return $tag;
		}

		if ( str_contains( $tag, ' defer' ) || str_contains( $tag, ' async' ) ) {
			return $tag;
		}

		// Don't modify inline scripts
		if ( empty( $src ) || ! str_contains( $tag, ' src=' ) ) {
			return $tag;
		}

		// Check exclusions
		$exclusions = apply_filters( 'quinrel_script_defer_exclusions', self::CORE_EXCLUSIONS );
		if ( in_array( $handle, $exclusions, true ) ) {
			return $tag;
		}

		// Exclude admin-bar or logged-in editor scripts on the frontend
		if ( str_starts_with( $handle, 'admin-bar' ) || str_starts_with( $handle, 'customize-' ) ) {
			return $tag;
		}

		// Inject defer attribute safely before src
		return str_replace( ' src=', ' defer="defer" src=', $tag );
	}
}
