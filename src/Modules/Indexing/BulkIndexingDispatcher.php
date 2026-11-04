<?php

namespace Quinrel\WordPress\Modules\Indexing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues up to ten published URLs for IndexNow when that feature is on.
 */
class BulkIndexingDispatcher {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/indexing/bulk-push',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dispatchBulkPushResponse' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function dispatchBulkPushResponse( \WP_REST_Request $request ): \WP_REST_Response {
		$params  = $request->get_json_params() ?: array();
		$postIds = array_slice( array_map( 'absint', (array) ( $params['post_ids'] ?? array() ) ), 0, 20 );

		if ( empty( $postIds ) ) {
			// Get 10 most recently updated published posts
			$query   = new \WP_Query(
				array(
					'post_type'      => array( 'post', 'page' ),
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'fields'         => 'ids',
				)
			);
			$postIds = $query->posts;
		}

		$urls = array();
		foreach ( $postIds as $id ) {
			$url = get_permalink( $id );
			if ( $url && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}

		if ( empty( $urls ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No eligible published URLs found to submit.', 'quinrel' ),
				),
				400
			);
		}

		$indexNow = new BingIndexNowApi();
		if ( ! $indexNow->isEnabled() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'IndexNow is off. Turn it on and save a key before submitting URLs.', 'quinrel' ),
				),
				400
			);
		}

		$queued = 0;
		foreach ( $urls as $url ) {
			if ( $indexNow->enqueueUrl( $url ) ) {
				++$queued;
			}
		}

		if ( 0 === $queued ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No URLs were queued for IndexNow.', 'quinrel' ),
				),
				400
			);
		}

		return rest_ensure_response(
			array(
				'success'         => true,
				'submitted_count' => $queued,
				'message'         => sprintf(
					/* translators: %d: number of URLs queued for IndexNow. */
					__( 'Queued %d URL(s) for IndexNow.', 'quinrel' ),
					$queued
				),
			)
		);
	}
}
