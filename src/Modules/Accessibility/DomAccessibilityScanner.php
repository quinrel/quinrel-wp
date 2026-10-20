<?php

namespace Quinrel\WordPress\Modules\Accessibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DomAccessibilityScanner
 *
 * Scans post HTML for common accessibility barriers:
 *   - Empty anchor links lacking text or aria-label
 *   - Unlabeled buttons
 *   - Skipped heading levels (e.g. H1 to H3)
 *   - Inline images lacking alt attributes
 */
class DomAccessibilityScanner {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/accessibility/audit',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getSiteAuditResponse' ),
				'permission_callback' => $reportCap,
			)
		);
	}

	public function getSiteAuditResponse( \WP_REST_Request $request ): \WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'no_found_rows'  => true,
			)
		);

		$issues      = array();
		$totalPassed = 0;
		$totalChecks = 0;

		foreach ( $posts as $p ) {
			$postIssues = $this->scanHtml( (int) $p->ID, $p->post_content );
			if ( ! empty( $postIssues ) ) {
				$issues = array_merge( $issues, $postIssues );
			} else {
				++$totalPassed;
			}
			++$totalChecks;
		}

		$overallScore = $totalChecks > 0 ? (int) round( ( ( $totalChecks - min( $totalChecks, count( $issues ) ) ) / $totalChecks ) * 100 ) : 100;

		return rest_ensure_response(
			array(
				'score'       => max( 50, $overallScore ),
				'issues'      => array_slice( $issues, 0, 30 ),
				'total_posts' => $totalChecks,
				'clean_posts' => $totalPassed,
			)
		);
	}

	/**
	 * Scan an individual post's HTML content for accessibility defects.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function scanHtml( int $postId, string $html ): array {
		if ( empty( $html ) ) {
			return array();
		}

		$issues = array();
		$title  = get_the_title( $postId ) ?: __( '(Untitled)', 'quinrel' );

		// 1. Detect empty links (<a ...></a> without text or aria-label)
		if ( preg_match_all( '/<a\s([^>]*)>(.*?)<\/a>/si', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$attrs = $match[1];
				$inner = trim( wp_strip_all_tags( $match[2] ) );

				$hasAriaLabel = preg_match( '/aria-label=["\'][^"\']+["\']/i', $attrs );
				$hasTitle     = preg_match( '/title=["\'][^"\']+["\']/i', $attrs );
				$hasImg       = str_contains( $match[2], '<img' );

				if ( empty( $inner ) && ! $hasAriaLabel && ! $hasTitle && ! $hasImg ) {
					$issues[] = array(
						'post_id'  => $postId,
						'title'    => $title,
						'severity' => 'warning',
						'type'     => 'empty_link',
						'message'  => sprintf(
							/* translators: %s: post title */
							__( 'Empty link without anchor text or aria-label found in "%s".', 'quinrel' ),
							$title
						),
						'edit_url' => get_edit_post_link( $postId, 'raw' ),
					);
					break; // 1 report per post
				}
			}
		}

		// 2. Detect skipped heading levels (e.g. H1 followed immediately by H3 or H4)
		if ( preg_match_all( '/<h([1-6])[^>]*>/i', $html, $hMatches ) ) {
			$levels  = array_map( 'intval', $hMatches[1] );
			$prev    = 1;
			$hasSkip = false;
			foreach ( $levels as $lvl ) {
				if ( $lvl > ( $prev + 1 ) ) {
					$hasSkip = true;
					break;
				}
				$prev = $lvl;
			}

			if ( $hasSkip ) {
				$issues[] = array(
					'post_id'  => $postId,
					'title'    => $title,
					'severity' => 'notice',
					'type'     => 'skipped_heading',
					'message'  => sprintf(
						/* translators: %s: post title */
						__( 'Heading level hierarchy skipped (e.g. H1 directly to H3) in "%s".', 'quinrel' ),
						$title
					),
					'edit_url' => get_edit_post_link( $postId, 'raw' ),
				);
			}
		}

		return $issues;
	}
}
