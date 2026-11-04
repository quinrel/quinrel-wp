<?php

namespace Quinrel\WordPress\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Modules\Admin\SeoAuditHistoryLog;

/**
 * RedirectChainCollapser
 *
 * Scans the redirects table for multi-hop chains (A -> B -> C) and circular loops.
 * Automatically collapses chains to direct 1-hop 301 redirects (A -> C) to conserve
 * crawl budget, eliminate latency, and pass full PageRank.
 */
class RedirectChainCollapser {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/redirects/chains',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getChainsResponse' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/redirects/collapse-chains',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'collapseChainsResponse' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function getChainsResponse( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( $this->detectChainsAndLoops() );
	}

	public function collapseChainsResponse( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( $this->collapseAll() );
	}

	/**
	 * Detect all multi-hop redirect chains and circular loops in the database.
	 *
	 * @return array{chains: array<int, array<string, mixed>>, loops: array<int, array<string, mixed>>, total_chains: int, total_loops: int}
	 */
	public function detectChainsAndLoops(): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'quinrel_redirects' );

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return array(
				'chains'       => array(),
				'loops'        => array(),
				'total_chains' => 0,
				'total_loops'  => 0,
			);
		}

		// Retrieve all non-regex redirects for in-memory graph traversal (bounded to 1,000 rules)
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, source, target, code FROM {$table} WHERE is_regex = 0 ORDER BY id ASC LIMIT 1000",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return array(
				'chains'       => array(),
				'loops'        => array(),
				'total_chains' => 0,
				'total_loops'  => 0,
			);
		}

		$map = array();
		foreach ( $rows as $row ) {
			$normSource         = '/' . trim( (string) $row['source'], '/' );
			$normTarget         = '/' . trim( (string) $row['target'], '/' );
			$map[ $normSource ] = array(
				'id'     => $row['id'],
				'source' => $row['source'],
				'target' => $row['target'],
				'code'   => (int) $row['code'],
			);
		}

		$chains = array();
		$loops  = array();

		foreach ( $map as $sourceKey => $data ) {
			$visited     = array( $sourceKey => true );
			$path        = array( $data['source'] );
			$currentRule = $data;
			$hopCount    = 0;
			$isLoop      = false;

			while ( $hopCount < 10 ) {
				$targetKey = '/' . trim( $currentRule['target'], '/' );

				// Self-redirect loop
				if ( $targetKey === $sourceKey && $hopCount === 0 ) {
					$loops[] = array(
						'id'     => $data['id'],
						'source' => $data['source'],
						'target' => $data['target'],
						'path'   => array( $data['source'], $data['target'] ),
					);
					$isLoop  = true;
					break;
				}

				if ( ! isset( $map[ $targetKey ] ) ) {
					// Terminal destination reached
					break;
				}

				++$hopCount;
				$nextRule = $map[ $targetKey ];
				$path[]   = $nextRule['source'];

				if ( isset( $visited[ $targetKey ] ) ) {
					// Circular loop detected
					$loops[] = array(
						'id'     => $data['id'],
						'source' => $data['source'],
						'target' => $data['target'],
						'path'   => array_merge( $path, array( $nextRule['target'] ) ),
					);
					$isLoop  = true;
					break;
				}

				$visited[ $targetKey ] = true;
				$currentRule           = $nextRule;
			}

			if ( ! $isLoop && $hopCount >= 1 ) {
				$chains[] = array(
					'id'           => $data['id'],
					'initial_url'  => $data['source'],
					'next_hop'     => $data['target'],
					'final_target' => $currentRule['target'],
					'hops'         => $hopCount + 1,
					'path'         => array_merge( $path, array( $currentRule['target'] ) ),
				);
			}
		}

		return array(
			'chains'       => $chains,
			'loops'        => $loops,
			'total_chains' => count( $chains ),
			'total_loops'  => count( $loops ),
		);
	}

	/**
	 * Collapse all detected multi-hop chains into direct 1-hop 301 redirects.
	 *
	 * @return array<string, mixed>
	 */
	public function collapseAll(): array {
		$analysis = $this->detectChainsAndLoops();
		$chains   = $analysis['chains'] ?? array();
		$loops    = $analysis['loops'] ?? array();

		global $wpdb;
		$table = $wpdb->prefix . 'quinrel_redirects';

		$collapsedCount = 0;
		$loopsHandled   = 0;
		$log            = new SeoAuditHistoryLog();

		// 1. Collapse chains
		foreach ( $chains as $chain ) {
			$id          = $chain['id'];
			$finalTarget = $chain['final_target'];
			$initialUrl  = $chain['initial_url'];
			$oldTarget   = $chain['next_hop'];

			if ( empty( $finalTarget ) || $finalTarget === $initialUrl ) {
				continue;
			}

			$updated = $wpdb->update(
				$table,
				array(
					'target' => $finalTarget,
					'code'   => 301,
				),
				array( 'id' => $id ),
				array( '%s', '%d' ),
				array( '%s' )
			);

			if ( $updated !== false && $updated > 0 ) {
				++$collapsedCount;
				$log->log(
					0,
					'collapse_redirect_chain',
					'redirect_target',
					$oldTarget,
					$finalTarget,
					sprintf( 'Collapsed %d-hop chain for %s', $chain['hops'], $initialUrl )
				);
			}
		}

		// 2. Break circular self-redirects by removing identical source/target
		foreach ( $loops as $loop ) {
			$sourceKey = '/' . trim( $loop['source'], '/' );
			$targetKey = '/' . trim( $loop['target'], '/' );
			if ( $sourceKey === $targetKey ) {
				$wpdb->delete( $table, array( 'id' => $loop['id'] ), array( '%s' ) );
				++$loopsHandled;
				$log->log(
					0,
					'break_redirect_loop',
					'redirect_rule',
					$loop['source'],
					'[DELETED]',
					'Deleted circular self-redirect'
				);
			}
		}

		// Refresh cache counter
		update_option( 'quinrel_redirect_counts', null );

		return array(
			'success'          => true,
			'collapsed_chains' => $collapsedCount,
			'loops_resolved'   => $loopsHandled,
			'message'          => sprintf(
				/* translators: 1: number of collapsed chains, 2: resolved loops */
				__( 'Successfully collapsed %1$d redirect chain(s) and resolved %2$d loop(s).', 'quinrel' ),
				$collapsedCount,
				$loopsHandled
			),
		);
	}
}
