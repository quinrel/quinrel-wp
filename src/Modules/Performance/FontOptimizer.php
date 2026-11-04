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
		if ( get_option( 'quinrel_font_display_swap', 'no' ) !== 'yes' ) {
			return;
		}
		if ( is_admin() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return;
		}

		add_filter( 'style_loader_src', array( $this, 'filterGoogleFontSrc' ), 10, 2 );
		add_filter( 'wp_resource_hints', array( $this, 'addFontHints' ), 10, 2 );
	}

	/**
	 * Append display=swap to Google Font stylesheet URLs if missing.
	 *
	 * @param string $src    Stylesheet source URL.
	 * @param string $handle Stylesheet handle.
	 * @return string
	 */
	public function filterGoogleFontSrc( string $src, string $handle ): string {
		if ( get_option( 'quinrel_font_display_swap', 'no' ) !== 'yes' ) {
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
	 * Preconnect only when this request already queues a Google Fonts stylesheet.
	 *
	 * @param array<int, string|array<string, string>> $urls
	 * @return array<int, string|array<string, string>>
	 */
	public function addFontHints( array $urls, string $relationType ): array {
		if ( $relationType !== 'preconnect' || get_option( 'quinrel_font_display_swap', 'no' ) !== 'yes' ) {
			return $urls;
		}
		if ( ! $this->queueUsesGoogleFonts() ) {
			return $urls;
		}

		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
		return $urls;
	}

	private function queueUsesGoogleFonts(): bool {
		global $wp_styles;
		if ( ! is_object( $wp_styles ) || ! isset( $wp_styles->queue, $wp_styles->registered ) || ! is_array( $wp_styles->queue ) ) {
			return false;
		}

		foreach ( $wp_styles->queue as $handle ) {
			$style = $wp_styles->registered[ $handle ] ?? null;
			$src   = is_object( $style ) && isset( $style->src ) ? (string) $style->src : '';
			if ( $src !== '' && str_contains( $src, 'fonts.googleapis.com' ) ) {
				return true;
			}
		}

		return false;
	}
}
