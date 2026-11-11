<?php

namespace Quinrel\WordPress\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KeywordCannibalizationDetector
 *
 * BL-038: Finds posts that compete for the same focus keyword,
 * which splits PageRank and confuses search engines about which
 * page should rank.
 */
class KeywordCannibalizationDetector {

	private const CACHE_KEY   = 'quinrel_cannibalization_report';
	private const CACHE_TTL   = 6 * HOUR_IN_SECONDS;
	private const REPORT_ROWS = 500;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/analytics/cannibalization',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getReport' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/analytics/cannibalization/refresh',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'refreshReport' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function getReport( \WP_REST_Request $request ): \WP_REST_Response {
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			return rest_ensure_response(
				array(
					'cached' => true,
					'report' => $cached,
				)
			);
		}

		$report = $this->buildReport();
		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );

		return rest_ensure_response(
			array(
				'cached' => false,
				'report' => $report,
			)
		);
	}

	public function refreshReport( \WP_REST_Request $request ): \WP_REST_Response {
		delete_transient( self::CACHE_KEY );
		$report = $this->buildReport();
		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );

		return rest_ensure_response(
			array(
				'success' => true,
				'report'  => $report,
			)
		);
	}

	public function buildReport(): array {
		global $wpdb;

		// Join only against focus keywords shared by two or more published posts,
		// so unique keywords in the first 500 posts cannot hide later collisions.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type, p.post_modified, pm.meta_value AS keyword
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_quinrel_focus_keyword'
             INNER JOIN (
                 SELECT LOWER(TRIM(pm2.meta_value)) AS norm_kw
                 FROM {$wpdb->postmeta} pm2
                 INNER JOIN {$wpdb->posts} p2 ON p2.ID = pm2.post_id
                 WHERE pm2.meta_key = '_quinrel_focus_keyword'
                   AND pm2.meta_value != ''
                   AND p2.post_status = 'publish'
                   AND p2.post_type NOT IN ('attachment', 'revision', 'nav_menu_item')
                 GROUP BY LOWER(TRIM(pm2.meta_value))
                 HAVING COUNT(DISTINCT p2.ID) >= 2
             ) dup ON dup.norm_kw = LOWER(TRIM(pm.meta_value))
             WHERE p.post_status = 'publish'
               AND p.post_type NOT IN ('attachment', 'revision', 'nav_menu_item')
               AND pm.meta_value != ''
             ORDER BY dup.norm_kw, p.ID
             LIMIT %d",
				self::REPORT_ROWS
			),
			ARRAY_A
		);

		// Group raw rows by normalised keyword before resolving permalinks
		$rawGroups = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$kw = mb_strtolower( trim( (string) ( $row['keyword'] ?? '' ) ) );
			if ( '' === $kw ) {
				continue;
			}
			$rawGroups[ $kw ][] = $row;
		}

		// Only keep groups with 2+ posts and prime post caches in one batch
		$collidingGroups = array_filter( $rawGroups, static fn( array $group ): bool => count( $group ) >= 2 );
		$collidingIds    = array();
		foreach ( $collidingGroups as $group ) {
			foreach ( $group as $row ) {
				$collidingIds[] = (int) $row['ID'];
			}
		}
		if ( ! empty( $collidingIds ) && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( array_values( array_unique( $collidingIds ) ), false, false );
		}

		$conflicts = array();
		foreach ( $collidingGroups as $kw => $group ) {
			$posts = array();
			foreach ( $group as $row ) {
				$postId  = (int) $row['ID'];
				$posts[] = array(
					'post_id'   => $postId,
					'title'     => (string) ( $row['post_title'] ?? '' ),
					'post_type' => (string) ( $row['post_type'] ?? 'post' ),
					'url'       => get_permalink( $postId ),
					'keyword'   => (string) ( $row['keyword'] ?? '' ),
					'modified'  => (string) ( $row['post_modified'] ?? '' ),
					'edit_url'  => get_edit_post_link( $postId, 'raw' ),
				);
			}

			$conflicts[] = array(
				'keyword'    => $kw,
				'post_count' => count( $posts ),
				'severity'   => count( $posts ) >= 4 ? 'critical' : ( count( $posts ) >= 3 ? 'high' : 'medium' ),
				'posts'      => $posts,
				'suggestion' => 'Choose the primary page and redirect or noindex the others, or consolidate content.',
			);
		}

		usort( $conflicts, fn( $a, $b ) => $b['post_count'] - $a['post_count'] );

		return array(
			'generated_at'    => current_time( 'mysql' ),
			'total_conflicts' => count( $conflicts ),
			'conflicts'       => $conflicts,
		);
	}
}
