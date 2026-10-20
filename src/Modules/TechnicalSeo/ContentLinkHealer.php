<?php

namespace Quinrel\WordPress\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Modules\Admin\SeoAuditHistoryLog;
use Quinrel\WordPress\Modules\ContentAssistant\InternalLinkEngine;

/**
 * ContentLinkHealer
 *
 * Scans internal links across post_content that point to known 301 redirects,
 * and safely rewrites them in-place to point directly to the destination URL.
 * Creates WordPress revisions, refreshes internal link indexes, and logs changes.
 */
class ContentLinkHealer {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/broken-links/healable',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getHealableLinks' ),
				'permission_callback' => $adminCap,
				'args'                => array(
					'limit' => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/broken-links/heal',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'healSingleLink' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/broken-links/heal-all',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'healAllBatch' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	/**
	 * Find internal links in published posts that point to active redirects.
	 *
	 * @param \WP_REST_Request|int $requestOrLimit
	 * @return \WP_REST_Response|array
	 */
	public function getHealableLinks( $requestOrLimit = 50 ) {
		$limit = 50;
		if ( $requestOrLimit instanceof \WP_REST_Request ) {
			$limit = min( 100, max( 1, (int) $requestOrLimit->get_param( 'limit' ) ) );
		} elseif ( is_numeric( $requestOrLimit ) ) {
			$limit = min( 100, max( 1, (int) $requestOrLimit ) );
		}

		global $wpdb;
		$tableLinks     = $wpdb->prefix . 'quinrel_links';
		$tableRedirects = $wpdb->prefix . 'quinrel_redirects';

		// Verify tables exist
		$linksExists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tableLinks ) ) );
		$redExists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tableRedirects ) ) );

		if ( ! $linksExists || ! $redExists ) {
			$empty = array(
				'items' => array(),
				'total' => 0,
			);
			return $requestOrLimit instanceof \WP_REST_Request ? rest_ensure_response( $empty ) : $empty;
		}

		// Find links pointing to redirect sources (exact matches on relative or absolute path)
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT l.id AS link_id, l.post_id, l.target_url, l.anchor_text, r.target AS new_url, p.post_title
             FROM {$tableLinks} l
             INNER JOIN {$tableRedirects} r ON (
                 l.target_url = r.source 
                 OR l.target_url = CONCAT('/', TRIM(LEADING '/' FROM r.source))
                 OR TRIM(LEADING '/' FROM l.target_url) = TRIM(LEADING '/' FROM r.source)
             )
             INNER JOIN {$wpdb->posts} p ON l.post_id = p.ID
             WHERE r.is_regex = 0 AND p.post_status = 'publish'
             ORDER BY l.id ASC
             LIMIT %d",
			$limit
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$siteUrl = home_url();
		$items   = array();
		foreach ( $rows as $row ) {
			$sourceUrl = $row['target_url'];
			$targetUrl = $row['new_url'];

			// Normalize targetUrl if root-relative
			if ( str_starts_with( $targetUrl, '/' ) ) {
				$targetUrl = untrailingslashit( $siteUrl ) . $targetUrl;
			}

			$items[] = array(
				'link_id'     => (int) $row['link_id'],
				'post_id'     => (int) $row['post_id'],
				'post_title'  => $row['post_title'] ?: __( '(Untitled)', 'quinrel' ),
				'edit_url'    => get_edit_post_link( (int) $row['post_id'], 'raw' ),
				'old_url'     => $sourceUrl,
				'new_url'     => $targetUrl,
				'anchor_text' => $row['anchor_text'],
			);
		}

		$response = array(
			'items' => $items,
			'total' => count( $items ),
		);

		return $requestOrLimit instanceof \WP_REST_Request ? rest_ensure_response( $response ) : $response;
	}

	/**
	 * Heal a single link in a post.
	 */
	public function healSingleLink( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		$postId = absint( $params['post_id'] ?? 0 );
		$oldUrl = trim( (string) ( $params['old_url'] ?? '' ) );
		$newUrl = trim( (string) ( $params['new_url'] ?? '' ) );

		if ( $postId < 1 || empty( $oldUrl ) || empty( $newUrl ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Missing post_id, old_url, or new_url parameter.', 'quinrel' ),
				),
				400
			);
		}

		$result = $this->replaceLinkInPost( $postId, $oldUrl, $newUrl );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Link successfully healed and updated.', 'quinrel' ),
				'details' => $result,
			)
		);
	}

	/**
	 * Bulk-heal a batch of links (up to 20 per request to guarantee fast execution).
	 */
	public function healAllBatch( \WP_REST_Request $request ): \WP_REST_Response {
		$data  = $this->getHealableLinks( 20 );
		$items = $data['items'] ?? array();

		$healed = 0;
		$failed = 0;

		foreach ( $items as $item ) {
			$res = $this->replaceLinkInPost( $item['post_id'], $item['old_url'], $item['new_url'] );
			if ( ! is_wp_error( $res ) && ( $res['replaced'] ?? 0 ) > 0 ) {
				++$healed;
			} else {
				++$failed;
			}
		}

		// Re-fetch remaining count
		$remainingData = $this->getHealableLinks( 50 );
		$remaining     = $remainingData['total'] ?? 0;

		return rest_ensure_response(
			array(
				'success'   => true,
				'healed'    => $healed,
				'failed'    => $failed,
				'remaining' => $remaining,
				'message'   => sprintf(
					/* translators: 1: number of healed links, 2: remaining links */
					__( 'Healed %1$d link(s). %2$d remaining.', 'quinrel' ),
					$healed,
					$remaining
				),
			)
		);
	}

	/**
	 * Perform safe in-place replacement of an anchor href in post content.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function replaceLinkInPost( int $postId, string $oldUrl, string $newUrl ) {
		$post = get_post( $postId );
		if ( ! $post || ! current_user_can( 'edit_post', $postId ) ) {
			return new \WP_Error( 'unauthorized', __( 'Cannot edit this post.', 'quinrel' ) );
		}

		$content = $post->post_content;
		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', __( 'Post content is empty.', 'quinrel' ) );
		}

		// Prepare patterns for absolute and root-relative representations
		$siteUrl  = untrailingslashit( home_url() );
		$relative = wp_make_link_relative( $oldUrl );
		$absolute = str_starts_with( $oldUrl, 'http' ) ? $oldUrl : $siteUrl . '/' . ltrim( $oldUrl, '/' );

		$targets       = array_unique( array_filter( array( $oldUrl, $relative, $absolute ) ) );
		$replacedCount = 0;

		$updatedContent = $content;
		foreach ( $targets as $target ) {
			// Exact href match in <a ... href="..." ...>
			$pattern = '/(<a\s[^>]*href=["\'])' . preg_quote( $target, '/' ) . '(["\'][^>]*>)/i';
			$updated = preg_replace( $pattern, '${1}' . esc_url( $newUrl ) . '${2}', $updatedContent, -1, $count );
			if ( $count > 0 && is_string( $updated ) ) {
				$updatedContent = $updated;
				$replacedCount += $count;
			}
		}

		if ( $replacedCount === 0 ) {
			// Also attempt matching without trailing slash variations
			foreach ( $targets as $target ) {
				$trimmed = rtrim( $target, '/' );
				$pattern = '/(<a\s[^>]*href=["\'])' . preg_quote( $trimmed, '/' ) . '(\/?["\'][^>]*>)/i';
				$updated = preg_replace( $pattern, '${1}' . esc_url( $newUrl ) . '${2}', $updatedContent, -1, $count );
				if ( $count > 0 && is_string( $updated ) ) {
					$updatedContent = $updated;
					$replacedCount += $count;
				}
			}
		}

		if ( $replacedCount === 0 ) {
			return array(
				'replaced' => 0,
				'message'  => __( 'No matching link occurrences found in content.', 'quinrel' ),
			);
		}

		// Update post with revision creation
		$updateResult = wp_update_post(
			array(
				'ID'           => $postId,
				'post_content' => $updatedContent,
			),
			true
		);

		if ( is_wp_error( $updateResult ) ) {
			return $updateResult;
		}

		// Re-index internal links for this post
		( new InternalLinkEngine() )->indexPostLinks( $postId, get_post( $postId ) );

		// Log tamper-evident audit entry
		( new SeoAuditHistoryLog() )->log(
			$postId,
			'heal_internal_link',
			'post_content',
			$oldUrl,
			$newUrl,
			sprintf( 'Healed %d link occurrences', $replacedCount )
		);

		return array(
			'post_id'  => $postId,
			'replaced' => $replacedCount,
			'old_url'  => $oldUrl,
			'new_url'  => $newUrl,
		);
	}
}
