<?php

namespace Quinrel\WordPress\Modules\ContentAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * BrandMentionFinder
 *
 * TASK-LNK-01: Discovers unlinked brand mentions and outreach citation opportunities
 * by cross-referencing search console queries and site brand terms.
 */
class BrandMentionFinder {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$cap = static fn() => current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/outreach/brand-mentions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'restGetBrandMentions' ),
				'permission_callback' => $cap,
			)
		);
	}

	/**
	 * REST handler for brand mention discovery.
	 */
	public function restGetBrandMentions( WP_REST_Request $request ): WP_REST_Response {
		$limit = absint( $request->get_param( 'limit' ) ?: 50 );
		$data  = $this->findMentions( array( 'limit' => $limit ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Extracts brand mention opportunities from query datasets or fallback simulations.
	 *
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public function findMentions( array $options = array() ): array {
		$brandTerms = $this->getBrandTerms();
		if ( empty( $brandTerms ) ) {
			return array(
				'brand_terms'   => array(),
				'opportunities' => array(),
				'summary'       => array(
					'total_found'       => 0,
					'total_impressions' => 0,
					'total_clicks'      => 0,
				),
			);
		}

		$queries = $options['queries'] ?? $this->loadCachedQueries();
		$limit   = (int) ( $options['limit'] ?? 50 );

		$opportunities    = array();
		$totalImpressions = 0;
		$totalClicks      = 0;

		foreach ( $queries as $row ) {
			$query = strtolower( trim( (string) ( $row['query'] ?? '' ) ) );
			if ( '' === $query ) {
				continue;
			}

			$matchedBrand = null;
			foreach ( $brandTerms as $term ) {
				if ( str_contains( $query, $term ) ) {
					$matchedBrand = $term;
					break;
				}
			}

			if ( ! $matchedBrand ) {
				continue;
			}

			$impressions = (int) ( $row['impressions'] ?? 0 );
			$clicks      = (int) ( $row['clicks'] ?? 0 );
			$ctr         = (float) ( $row['ctr'] ?? ( $impressions > 0 ? ( $clicks / $impressions ) : 0.0 ) );
			$position    = (float) ( $row['position'] ?? 1.0 );
			$page        = (string) ( $row['page'] ?? home_url( '/' ) );

			// Classify opportunity type
			$type = 'brand_query';
			if ( $ctr < 0.05 && $impressions > 50 ) {
				$type = 'unlinked_citation_risk';
			} elseif ( str_contains( $query, 'vs' ) || str_contains( $query, 'alternative' ) || str_contains( $query, 'review' ) ) {
				$type = 'comparison_mention';
			}

			// Opportunity Score (0-100) based on impression volume and CTR gap
			$score = min( 100, (int) round( ( log10( max( 1, $impressions ) ) * 25 ) + ( ( 1.0 - $ctr ) * 20 ) ) );

			$opportunities[] = array(
				'query'             => $query,
				'matched_brand'     => $matchedBrand,
				'type'              => $type,
				'impressions'       => $impressions,
				'clicks'            => $clicks,
				'ctr'               => round( $ctr * 100, 2 ),
				'position'          => round( $position, 1 ),
				'landing_page'      => $page,
				'opportunity_score' => $score,
			);

			$totalImpressions += $impressions;
			$totalClicks      += $clicks;

			if ( count( $opportunities ) >= $limit ) {
				break;
			}
		}

		// Sort by opportunity score descending
		usort( $opportunities, static fn( $a, $b ) => $b['opportunity_score'] <=> $a['opportunity_score'] );

		return array(
			'brand_terms'   => $brandTerms,
			'opportunities' => $opportunities,
			'summary'       => array(
				'total_found'       => count( $opportunities ),
				'total_impressions' => $totalImpressions,
				'total_clicks'      => $totalClicks,
			),
		);
	}

	/**
	 * Retrieves configured brand search terms.
	 *
	 * @return array<int, string>
	 */
	public function getBrandTerms(): array {
		$configured = (string) get_option( 'quinrel_brand_terms', '' );
		$terms      = array();

		if ( '' !== trim( $configured ) ) {
			$raw = explode( ',', $configured );
			foreach ( $raw as $item ) {
				$clean = strtolower( trim( $item ) );
				if ( '' !== $clean ) {
					$terms[] = $clean;
				}
			}
		}

		// Always include blog name if available
		$siteName = strtolower( trim( (string) get_bloginfo( 'name' ) ) );
		if ( '' !== $siteName && ! in_array( $siteName, $terms, true ) ) {
			$terms[] = $siteName;
		}

		return array_values( array_unique( $terms ) );
	}

	/**
	 * Loads cached search console queries or returns default empty list.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function loadCachedQueries(): array {
		$cached = get_transient( 'quinrel_gsc_performance_28' );
		if ( is_array( $cached ) && isset( $cached['rows'] ) && is_array( $cached['rows'] ) ) {
			$results = array();
			foreach ( $cached['rows'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$keys      = $row['keys'] ?? array();
				$results[] = array(
					'query'       => $keys[0] ?? '',
					'page'        => $keys[1] ?? '',
					'clicks'      => $row['clicks'] ?? 0,
					'impressions' => $row['impressions'] ?? 0,
					'ctr'         => $row['ctr'] ?? 0,
					'position'    => $row['position'] ?? 0,
				);
			}
			return $results;
		}

		return array();
	}
}
