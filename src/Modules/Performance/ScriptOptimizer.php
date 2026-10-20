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
		if ( is_admin() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return;
		}

		add_filter( 'script_loader_tag', array( $this, 'filterScriptTag' ), 10, 3 );
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
		// Only run when deferral is enabled in Quinrel settings
		if ( 'no' === get_option( 'quinrel_defer_non_critical_scripts', 'yes' ) ) {
			return $tag;
		}

		// Don't modify if already has defer or async
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
