<?php

namespace Quinrel\WordPress\Modules\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FontOptimizer
 *
 * Ensures all Google Fonts specify `font-display: swap` to prevent
 * Flash of Invisible Text (FOIT), and injects early preconnect hints
 * to accelerate web font discovery and First Contentful Paint.
 */
class FontOptimizer {

	public function register(): void {
		if ( is_admin() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return;
		}

		add_filter( 'style_loader_src', array( $this, 'filterGoogleFontSrc' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'injectPreconnects' ), 1 );
	}

	/**
	 * Append display=swap to Google Font stylesheet URLs if missing.
	 *
	 * @param string $src    Stylesheet source URL.
	 * @param string $handle Stylesheet handle.
	 * @return string
	 */
	public function filterGoogleFontSrc( string $src, string $handle ): string {
		if ( 'no' === get_option( 'quinrel_font_display_swap', 'yes' ) ) {
			return $src;
		}

		if ( ! str_contains( $src, 'fonts.googleapis.com' ) ) {
			return $src;
		}

		if ( str_contains( $src, 'display=swap' ) || str_contains( $src, 'display=optional' ) || str_contains( $src, 'display=fallback' ) ) {
			return $src;
		}

		return add_query_arg( 'display', 'swap', $src );
	}

	/**
	 * Inject preconnect resource hints for Google Fonts CDN.
	 */
	public function injectPreconnects(): void {
		if ( 'no' === get_option( 'quinrel_font_display_swap', 'yes' ) ) {
			return;
		}

		// Avoid duplicates if another plugin or theme already injected preconnects
		static $injected = false;
		if ( $injected ) {
			return;
		}
		$injected = true;

		echo "\n<!-- Quinrel Font Optimization -->\n";
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	}
}
