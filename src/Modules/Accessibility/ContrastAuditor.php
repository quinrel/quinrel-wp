<?php

namespace Quinrel\WordPress\Modules\Accessibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ContrastAuditor
 *
 * Audits text-to-background color combinations against WCAG 2.1 AA standards
 * (4.5:1 for body text, 3:1 for large text). Computes exact relative luminance
 * and suggests accessible alternative colors.
 */
class ContrastAuditor {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/accessibility/contrast-check',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkContrastResponse' ),
				'permission_callback' => $reportCap,
			)
		);
	}

	public function checkContrastResponse( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		$fg     = sanitize_hex_color( $params['foreground'] ?? '#000000' ) ?: '#000000';
		$bg     = sanitize_hex_color( $params['background'] ?? '#ffffff' ) ?: '#ffffff';

		$ratio = self::calculateRatio( $fg, $bg );

		$passAaNormal = $ratio >= 4.5;
		$passAaLarge  = $ratio >= 3.0;
		$passAaa      = $ratio >= 7.0;

		return rest_ensure_response(
			array(
				'foreground'      => $fg,
				'background'      => $bg,
				'ratio'           => round( $ratio, 2 ),
				'pass_aa_normal'  => $passAaNormal,
				'pass_aa_large'   => $passAaLarge,
				'pass_aaa'        => $passAaa,
				'recommended_hex' => $passAaNormal ? $fg : self::suggestCompliantColor( $fg, $bg ),
			)
		);
	}

	/**
	 * Compute WCAG 2.1 relative luminance for a given hex color.
	 */
	public static function getLuminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$r = ( $r <= 0.03928 ) ? $r / 12.92 : pow( ( $r + 0.055 ) / 1.055, 2.4 );
		$g = ( $g <= 0.03928 ) ? $g / 12.92 : pow( ( $g + 0.055 ) / 1.055, 2.4 );
		$b = ( $b <= 0.03928 ) ? $b / 12.92 : pow( ( $b + 0.055 ) / 1.055, 2.4 );

		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	/**
	 * Compute the contrast ratio between two hex colors.
	 */
	public static function calculateRatio( string $hex1, string $hex2 ): float {
		$lum1 = self::getLuminance( $hex1 );
		$lum2 = self::getLuminance( $hex2 );

		$lighter = max( $lum1, $lum2 );
		$darker  = min( $lum1, $lum2 );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Suggest a compliant foreground color by adjusting lightness toward black or white.
	 */
	public static function suggestCompliantColor( string $fg, string $bg ): string {
		$bgLum = self::getLuminance( $bg );
		// If background is light, return darker shade; if background is dark, return light shade
		return $bgLum > 0.5 ? '#0f172a' : '#f8fafc';
	}
}

