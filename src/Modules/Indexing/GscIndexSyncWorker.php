<?php

namespace Quinrel\WordPress\Modules\Indexing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Published and sitemap counts, plus stored index-status rows, for the funnel.
 */
class GscIndexSyncWorker {

	public const TABLE_NAME = 'quinrel_index_status';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public static function createTable(): void {
		global $wpdb;
		$table          = $wpdb->prefix . self::TABLE_NAME;
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            post_id BIGINT UNSIGNED NOT NULL,
            url VARCHAR(2048) NOT NULL,
            verdict VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN',
            coverage_state VARCHAR(100) NOT NULL DEFAULT '',
            robots_txt_state VARCHAR(20) NOT NULL DEFAULT '',
            indexing_state VARCHAR(20) NOT NULL DEFAULT '',
            last_crawled DATETIME DEFAULT NULL,
            last_inspected DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id),
            KEY idx_verdict (verdict),
            KEY idx_coverage (coverage_state)
        ) {$charsetCollate};";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
	}

	public function registerRoutes(): void {
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );
		$adminCap  = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/indexing/funnel-data',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getFunnelDataResponse' ),
				'permission_callback' => $reportCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/indexing/sync-batch',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'syncBatchResponse' ),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function getFunnelDataResponse( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );
		global $wpdb;

		$totalPosts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post', 'page')"
		);
		$inSitemap  = ( new \Quinrel\WordPress\Modules\Sitemap\SitemapGenerator() )->countIndexableUrls();

		$table  = esc_sql( $wpdb->prefix . self::TABLE_NAME );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		$indexedCount    = 0;
		$crawledCount    = 0;
		$discoveredCount = 0;
		$coverageGroups  = array();
		$realRows        = 0;

		if ( $exists ) {
			// Table name is the plugin prefix plus a class constant, passed through esc_sql().
			// The old sync batch wrote "Indexed, not submitted in sitemap" without asking Google.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$realRows = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table} WHERE coverage_state <> '' AND coverage_state <> 'Indexed, not submitted in sitemap'"
			);
			if ( $realRows > 0 ) {
				$discoveredCount = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$table} WHERE coverage_state = 'Discovered - currently not indexed'"
				);
				$crawledCount    = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$table} WHERE coverage_state = 'Crawled - currently not indexed'"
				);
				$indexedCount    = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$table} WHERE verdict = 'PASS' AND coverage_state = 'Submitted and indexed'"
				);
				$coverageRows    = $wpdb->get_results(
					"SELECT coverage_state, COUNT(*) as count FROM {$table} WHERE coverage_state <> '' AND coverage_state <> 'Indexed, not submitted in sitemap' GROUP BY coverage_state ORDER BY count DESC LIMIT 10",
					ARRAY_A
				);
				if ( is_array( $coverageRows ) ) {
					$coverageGroups = $coverageRows;
				}
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// A stage is measured only when a stored coverage string answers that stage.
		$coverageMeasured = $coverageGroups !== array();

		return rest_ensure_response(
			array(
				'funnel'            => array(
					$this->funnelStage( __( 'Published Content', 'quinrel' ), $totalPosts, true, '#0060F9', $totalPosts ),
					$this->funnelStage( __( 'Included in XML Sitemap', 'quinrel' ), $inSitemap, true, '#9A37F4', $totalPosts ),
					$this->funnelStage( __( 'Discovered by Google', 'quinrel' ), $discoveredCount, $discoveredCount > 0, '#0140AC', $totalPosts ),
					$this->funnelStage( __( 'Crawled by Googlebot', 'quinrel' ), $crawledCount, $crawledCount > 0, '#0ea5e9', $totalPosts ),
					$this->funnelStage( __( 'Indexed in Google Search', 'quinrel' ), $indexedCount, $indexedCount > 0, '#10b981', $totalPosts ),
					$this->funnelStage( __( 'Generating Impressions (90d)', 'quinrel' ), 0, false, '#059669', $totalPosts ),
				),
				'coverage_groups'   => $coverageMeasured ? $coverageGroups : array(),
				'coverage_measured' => $coverageMeasured,
			)
		);
	}

	/**
	 * The old batch route wrote PASS rows without asking Google. It now writes nothing.
	 */
	public function syncBatchResponse( \WP_REST_Request $request ): \WP_Error {
		unset( $request );

		return new \WP_Error(
			'quinrel_index_unmeasured',
			__( 'Quinrel does not invent Google index status. Nothing was written.', 'quinrel' ),
			array( 'status' => 501 )
		);
	}

	/**
	 * @return array{stage:string,count:int|null,percent:int|null,measured:bool,color:string}
	 */
	private function funnelStage( string $label, int $count, bool $measured, string $color, int $totalPosts ): array {
		if ( ! $measured ) {
			return array(
				'stage'    => $label,
				'count'    => null,
				'percent'  => null,
				'measured' => false,
				'color'    => $color,
			);
		}

		return array(
			'stage'    => $label,
			'count'    => $count,
			'percent'  => $totalPosts > 0 ? (int) round( ( $count / $totalPosts ) * 100 ) : 0,
			'measured' => true,
			'color'    => $color,
		);
	}
}
