<?php

namespace Quinrel\WordPress\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Modules\Admin\SeoAuditHistoryLog;

/**
 * CannibalizationResolver
 *
 * Discovers keyword cannibalization where multiple internal URLs compete
 * for identical search terms, and provides 1-click canonical consolidation.
 */
class CannibalizationResolver {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );
		$adminCap  = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/analytics/cannibalization',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getCannibalizationResponse' ),
				'permission_callback' => $reportCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/analytics/cannibalization/resolve',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resolveCannibalizationResponse' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function getCannibalizationResponse( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		// Find posts sharing identical focus keywords
		$query = "SELECT m.meta_value as keyword, COUNT(m.post_id) as count, GROUP_CONCAT(m.post_id) as post_ids
                  FROM {$wpdb->postmeta} m
                  INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID
                  WHERE m.meta_key = '_quinrel_focus_keyword' 
                    AND m.meta_value != ''
                    AND p.post_status = 'publish'
                  GROUP BY m.meta_value
                  HAVING count > 1
                  LIMIT 20";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $query, ARRAY_A );
		$items = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$postIds = array_map( 'intval', explode( ',', $row['post_ids'] ) );
				$urls    = array();

				foreach ( $postIds as $pid ) {
					$urls[] = array(
						'post_id'  => $pid,
						'title'    => get_the_title( $pid ) ?: __( '(Untitled)', 'quinrel' ),
						'url'      => get_permalink( $pid ),
						'edit_url' => get_edit_post_link( $pid, 'raw' ),
					);
				}

				$items[] = array(
					'keyword'   => $row['keyword'],
					'url_count' => (int) $row['count'],
					'posts'     => $urls,
				);
			}
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}

	public function resolveCannibalizationResponse( \WP_REST_Request $request ): \WP_REST_Response {
		$params          = $request->get_json_params() ?: array();
		$primaryPostId   = absint( $params['primary_post_id'] ?? 0 );
		$secondaryPostId = absint( $params['secondary_post_id'] ?? 0 );

		if ( $primaryPostId < 1 || $secondaryPostId < 1 || $primaryPostId === $secondaryPostId ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Valid primary_post_id and secondary_post_id are required.', 'quinrel' ),
				),
				400
			);
		}

		$primaryUrl = get_permalink( $primaryPostId );
		if ( ! $primaryUrl ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Primary post URL not found.', 'quinrel' ),
				),
				404
			);
		}

		// Set canonical on secondary post pointing to primary post
		update_post_meta( $secondaryPostId, '_quinrel_canonical_url', $primaryUrl );

		( new SeoAuditHistoryLog() )->log(
			$secondaryPostId,
			'resolve_cannibalization',
			'_quinrel_canonical_url',
			'',
			$primaryUrl,
			sprintf( 'Set canonical to primary post #%d', $primaryPostId )
		);

		return rest_ensure_response(
			array(
				'success'       => true,
				'message'       => sprintf(
					/* translators: 1: secondary ID, 2: primary ID */
					__( 'Canonical tag on post #%1$d updated to point to primary post #%2$d.', 'quinrel' ),
					$secondaryPostId,
					$primaryPostId
				),
				'canonical_url' => $primaryUrl,
			)
		);
	}
}
