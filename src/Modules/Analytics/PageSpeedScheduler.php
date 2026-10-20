<?php

namespace Quinrel\WordPress\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PageSpeedScheduler
 *
 * Tracks Core Web Vitals and PageSpeed scores over time, storing historical
 * snapshots to power interactive sparklines and performance regression detection.
 */
class PageSpeedScheduler {

	private const TABLE_NAME = 'quinrel_pagespeed_history';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public static function createTable(): void {
		global $wpdb;
		$table          = $wpdb->prefix . self::TABLE_NAME;
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            device VARCHAR(10) NOT NULL DEFAULT 'mobile',
            perf_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            lcp_ms INT UNSIGNED NOT NULL DEFAULT 0,
            cls_score DECIMAL(5,3) NOT NULL DEFAULT 0.000,
            inp_ms INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_device (post_id, device),
            KEY idx_created (created_at)
        ) {$charsetCollate};";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );
	}

	public function registerRoutes(): void {
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/analytics/pagespeed/history',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getHistoryResponse' ),
				'permission_callback' => $reportCap,
				'args'                => array(
					'post_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'device'  => array(
						'default'           => 'mobile',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	public function getHistoryResponse( \WP_REST_Request $request ): \WP_REST_Response {
		$postId = absint( $request->get_param( 'post_id' ) );
		$device = sanitize_key( $request->get_param( 'device' ) ?: 'mobile' );

		return rest_ensure_response(
			array(
				'history' => $this->getHistory( $postId, $device ),
			)
		);
	}

	/**
	 * Record a snapshot into the history table.
	 */
	public static function recordSnapshot( int $postId, string $device, int $perfScore, int $lcpMs, float $clsScore, int $inpMs ): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$wpdb->insert(
			$table,
			array(
				'post_id'    => $postId,
				'device'     => $device,
				'perf_score' => $perfScore,
				'lcp_ms'     => $lcpMs,
				'cls_score'  => $clsScore,
				'inp_ms'     => $inpMs,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%f', '%d', '%s' )
		);
	}

	/**
	 * Get historical snapshots for sparkline rendering.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getHistory( int $postId, string $device = 'mobile', int $limit = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT perf_score, lcp_ms, cls_score, inp_ms, created_at 
                 FROM {$table} 
                 WHERE post_id = %d AND device = %s 
                 ORDER BY created_at ASC 
                 LIMIT %d",
				$postId,
				$device,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}
}
