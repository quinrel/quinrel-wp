<?php

namespace Quinrel\WordPress\Modules\ContentAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Modules\Admin\SeoAuditHistoryLog;

/**
 * OrphanLinker
 *
 * Locates published orphan posts (zero incoming internal links) and identifies
 * topically aligned candidate parent posts sharing categories/tags.
 * Provides 1-click in-content link injection to weave isolated posts into the site's link graph.
 */
class OrphanLinker {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$editorCap = fn() => current_user_can( 'edit_posts' );

		register_rest_route(
			'quinrel/v1',
			'/content/orphan-candidates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getOrphanCandidatesResponse' ),
				'permission_callback' => $editorCap,
				'args'                => array(
					'limit' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/content/connect-orphan',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'connectOrphanResponse' ),
				'permission_callback' => $editorCap,
			)
		);
	}

	/**
	 * Retrieve orphan posts with their top candidate parent posts for linking.
	 *
	 * @return \WP_REST_Response
	 */
	public function getOrphanCandidatesResponse( \WP_REST_Request $request ): \WP_REST_Response {
		$limit = min( 20, max( 1, (int) $request->get_param( 'limit' ) ) );

		// Query published posts with 0 inbound links
		$orphanQuery = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_quinrel_inbound_links',
						'value'   => '0',
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_quinrel_inbound_links',
						'compare' => 'NOT EXISTS',
					),
				),
				'no_found_rows'  => true,
			)
		);

		$items  = array();
		$engine = new InternalLinkEngine();

		foreach ( $orphanQuery->posts as $orphan ) {
			$orphanId = (int) $orphan->ID;
			$status   = $engine->checkOrphanStatus( $orphanId );

			if ( ! $status['is_orphan'] ) {
				update_post_meta( $orphanId, '_quinrel_inbound_links', $status['incoming_count'] );
				continue;
			}

			// Find candidate parents sharing categories or tags
			$parents = $this->findCandidateParents( $orphanId );

			$items[] = array(
				'orphan_id'         => $orphanId,
				'orphan_title'      => $orphan->post_title ?: __( '(Untitled)', 'quinrel' ),
				'orphan_url'        => get_permalink( $orphanId ),
				'orphan_date'       => get_the_date( 'Y-m-d', $orphanId ),
				'edit_url'          => get_edit_post_link( $orphanId, 'raw' ),
				'candidate_parents' => $parents,
				'suggested_anchor'  => $orphan->post_title,
			);
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * Find candidate parent posts sharing category or tag taxonomy terms.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function findCandidateParents( int $orphanId ): array {
		$categories = wp_get_post_categories( $orphanId );
		$tags       = wp_get_post_tags( $orphanId );
		$tagIds     = wp_list_pluck( $tags, 'term_id' );
		$allTerms   = array_merge( $categories, $tagIds );

		$args = array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => 3,
			'post__not_in'           => array( $orphanId ),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		);

		if ( ! empty( $allTerms ) ) {
			$args['tax_query'] = array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => $categories,
				),
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $tagIds,
				),
			);
		}

		$candidates = get_posts( $args );
		$results    = array();

		foreach ( $candidates as $candidate ) {
			$results[] = array(
				'id'       => (int) $candidate->ID,
				'title'    => $candidate->post_title ?: __( '(Untitled)', 'quinrel' ),
				'url'      => get_permalink( $candidate->ID ),
				'edit_url' => get_edit_post_link( $candidate->ID, 'raw' ),
			);
		}

		return $results;
	}

	/**
	 * Connect an orphan post by injecting an in-content link into a parent post.
	 */
	public function connectOrphanResponse( \WP_REST_Request $request ): \WP_REST_Response {
		$params       = $request->get_json_params() ?: array();
		$orphanId     = absint( $params['orphan_id'] ?? 0 );
		$parentPostId = absint( $params['parent_post_id'] ?? 0 );
		$customAnchor = sanitize_text_field( $params['anchor_text'] ?? '' );

		if ( $orphanId < 1 || $parentPostId < 1 ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Missing orphan_id or parent_post_id.', 'quinrel' ),
				),
				400
			);
		}

		if ( ! current_user_can( 'edit_post', $parentPostId ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'You do not have permission to edit the parent post.', 'quinrel' ),
				),
				403
			);
		}

		$orphan = get_post( $orphanId );
		$parent = get_post( $parentPostId );

		if ( ! $orphan || ! $parent ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Orphan or parent post could not be found.', 'quinrel' ),
				),
				404
			);
		}

		$orphanUrl = get_permalink( $orphanId );
		$anchor    = ! empty( $customAnchor ) ? $customAnchor : $orphan->post_title;
		$content   = $parent->post_content;

		// Check if link already exists in parent
		if ( str_contains( $content, $orphanUrl ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Link already exists in parent post.', 'quinrel' ),
				)
			);
		}

		// Inject contextual related reading paragraph at the end of the post content
		$linkSnippet = sprintf(
			"\n\n<!-- quinrel-internal-link -->\n<p class=\"quinrel-related-reading\">%s <a href=\"%s\">%s</a>.</p>",
			esc_html__( 'Related reading:', 'quinrel' ),
			esc_url( $orphanUrl ),
			esc_html( $anchor )
		);

		$newContent = $content . $linkSnippet;

		$update = wp_update_post(
			array(
				'ID'           => $parentPostId,
				'post_content' => $newContent,
			),
			true
		);

		if ( is_wp_error( $update ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $update->get_error_message(),
				),
				500
			);
		}

		// Refresh internal link index
		$engine = new InternalLinkEngine();
		$engine->indexPostLinks( $parentPostId, get_post( $parentPostId ) );

		// Update orphan inbound count
		$newStatus = $engine->checkOrphanStatus( $orphanId );
		update_post_meta( $orphanId, '_quinrel_inbound_links', $newStatus['incoming_count'] );

		// Log audit event
		( new SeoAuditHistoryLog() )->log(
			$parentPostId,
			'connect_orphan_post',
			'post_content',
			'',
			$orphanUrl,
			sprintf( 'Connected orphan post "%s"', $orphan->post_title )
		);

		return rest_ensure_response(
			array(
				'success'       => true,
				'message'       => sprintf(
					/* translators: 1: orphan title, 2: parent title */
					__( 'Successfully linked "%1$s" inside "%2$s".', 'quinrel' ),
					$orphan->post_title,
					$parent->post_title
				),
				'inbound_count' => $newStatus['incoming_count'],
			)
		);
	}
}
