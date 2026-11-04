<?php

namespace Quinrel\WordPress\Modules\ContentAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages link building, outreach pipelines, broken backlink reclamation,
 * and anchor text diversity analysis.
 */
class OutreachManager {

	public const TABLE_PROSPECTS = 'quinrel_outreach_prospects';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public static function createTable(): void {
		global $wpdb;
		$table          = $wpdb->prefix . self::TABLE_PROSPECTS;
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            target_url text NOT NULL,
            contact_name varchar(150) DEFAULT '',
            contact_email varchar(191) DEFAULT '',
            pitch_type varchar(50) NOT NULL DEFAULT 'guest_post',
            stage varchar(30) NOT NULL DEFAULT 'prospect',
            da_tier varchar(20) DEFAULT 'medium',
            notes text,
            last_contacted_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY domain (domain(191)),
            KEY stage (stage)
        ) {$charsetCollate};";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sql );
	}

	public function registerRoutes(): void {
		register_rest_route(
			'quinrel/v1',
			'/outreach/prospects',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handleGetProspects' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handleSaveProspect' ),
					'permission_callback' => array( $this, 'checkPermission' ),
				),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/outreach/prospects/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handleDeleteProspect' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/outreach/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleExportCsv' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/outreach/broken-backlinks',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleGetBrokenBacklinks' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/outreach/anchor-diversity',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handleAnchorDiversity' ),
				'permission_callback' => array( $this, 'checkPermission' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );
	}

	public function handleGetProspects( \WP_REST_Request $request ): \WP_REST_Response {
		$stage = sanitize_key( (string) $request->get_param( 'stage' ) );
		$items = $this->getProspects( array( 'stage' => $stage ) );

		return rest_ensure_response(
			array(
				'prospects' => $items,
				'total'     => count( $items ),
			)
		);
	}

	public function handleSaveProspect( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();
		$result = $this->saveProspect( $params ?: array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $result,
			)
		);
	}

	public function handleDeleteProspect( \WP_REST_Request $request ): \WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$success = $this->deleteProspect( $id );

		return rest_ensure_response(
			array(
				'success' => $success,
			)
		);
	}

	public function handleExportCsv( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'csv'      => $this->exportCsv(),
				'filename' => 'quinrel-outreach-prospects-' . gmdate( 'Y-m-d' ) . '.csv',
			)
		);
	}

	public function handleGetBrokenBacklinks( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'opportunities' => $this->getBrokenBacklinks(),
			)
		);
	}

	public function handleAnchorDiversity( \WP_REST_Request $request ): \WP_REST_Response {
		$postId = (int) $request->get_param( 'post_id' );
		return rest_ensure_response( $this->analyzeAnchorDiversity( $postId ) );
	}

	// ── Core Operations ───────────────────────────────────────────────────────

	/**
	 * Retrieve prospects.
	 *
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function getProspects( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_PROSPECTS;

		$stage = isset( $args['stage'] ) ? sanitize_key( (string) $args['stage'] ) : '';
		if ( '' !== $stage ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table} WHERE stage = %s ORDER BY id DESC LIMIT 100",
					$stage
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT 100",
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Save or update an outreach prospect.
	 *
	 * @param array<string, mixed> $data
	 * @return int|\WP_Error
	 */
	public function saveProspect( array $data ): int|\WP_Error {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_PROSPECTS;

		$domain = sanitize_text_field( $data['domain'] ?? '' );
		if ( '' === $domain ) {
			return new \WP_Error( 'missing_domain', __( 'Domain is required.', 'quinrel' ), array( 'status' => 400 ) );
		}

		$validStages = array( 'prospect', 'contacted', 'replied', 'earned', 'rejected' );
		$stage       = sanitize_key( $data['stage'] ?? 'prospect' );
		if ( ! in_array( $stage, $validStages, true ) ) {
			$stage = 'prospect';
		}

		$validPitches = array( 'guest_post', 'unlinked_mention', 'broken_link', 'resource_page' );
		$pitchType    = sanitize_key( $data['pitch_type'] ?? 'guest_post' );
		if ( ! in_array( $pitchType, $validPitches, true ) ) {
			$pitchType = 'guest_post';
		}

		$contactEmail = sanitize_email( $data['contact_email'] ?? '' );
		$targetUrl    = esc_url_raw( $data['target_url'] ?? '' );
		$contactName  = sanitize_text_field( $data['contact_name'] ?? '' );
		$notes        = sanitize_textarea_field( $data['notes'] ?? '' );
		$daTier       = sanitize_key( $data['da_tier'] ?? 'medium' );

		$id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;

		$record = array(
			'domain'        => $domain,
			'target_url'    => $targetUrl,
			'contact_name'  => $contactName,
			'contact_email' => $contactEmail,
			'pitch_type'    => $pitchType,
			'stage'         => $stage,
			'da_tier'       => $daTier,
			'notes'         => $notes,
		);

		if ( ! empty( $data['last_contacted_at'] ) ) {
			$record['last_contacted_at'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $data['last_contacted_at'] ) );
		}

		if ( $id > 0 ) {
			$wpdb->update( $table, $record, array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			return $id;
		}

		$wpdb->insert( $table, $record, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	public function deleteProspect( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_PROSPECTS;
		return (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Exports all outreach records as CSV.
	 */
	public function exportCsv(): string {
		$prospects = $this->getProspects();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory temp stream for CSV generation
		$output = fopen( 'php://temp', 'r+' );
		if ( ! $output ) {
			return '';
		}

		fputcsv( $output, array( 'ID', 'Domain', 'Target URL', 'Contact Name', 'Contact Email', 'Pitch Type', 'Stage', 'DA Tier', 'Notes', 'Created At' ) );

		foreach ( $prospects as $p ) {
			fputcsv(
				$output,
				array(
					$p['id'] ?? '',
					$p['domain'] ?? '',
					$p['target_url'] ?? '',
					$p['contact_name'] ?? '',
					$p['contact_email'] ?? '',
					$p['pitch_type'] ?? '',
					$p['stage'] ?? '',
					$p['da_tier'] ?? '',
					$p['notes'] ?? '',
					$p['created_at'] ?? '',
				)
			);
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- close in-memory temp stream
		fclose( $output );

		return is_string( $csv ) ? $csv : '';
	}

	/**
	 * Identifies broken backlinks with external referrers from 404 logs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBrokenBacklinks(): array {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'quinrel_404_logs' );

		$siteHost = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( empty( $siteHost ) ) {
			$siteHost = 'localhost';
		}

		$likePattern = '%' . $wpdb->esc_like( (string) $siteHost ) . '%';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, uri, referer, hits, last_hit FROM {$table}
                 WHERE referer IS NOT NULL AND referer != '' AND referer NOT LIKE %s
                 ORDER BY hits DESC LIMIT 50",
				$likePattern
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$opportunities = array();
		foreach ( $rows as $row ) {
			$referrerHost    = wp_parse_url( (string) ( $row['referer'] ?? '' ), PHP_URL_HOST ) ?: 'external';
			$opportunities[] = array(
				'id'            => $row['id'],
				'broken_url'    => home_url( (string) ( $row['uri'] ?? '' ) ),
				'uri'           => $row['uri'],
				'referrer'      => $row['referer'],
				'referrer_host' => $referrerHost,
				'hits'          => (int) $row['hits'],
				'last_hit'      => $row['last_hit'],
			);
		}

		return $opportunities;
	}

	/**
	 * Analyzes anchor text distribution for a given post.
	 *
	 * @return array<string, mixed>
	 */
	public function analyzeAnchorDiversity( int $postId ): array {
		global $wpdb;
		$post = get_post( $postId );
		if ( ! $post ) {
			return array(
				'post_id'     => $postId,
				'total_links' => 0,
				'score'       => 100,
				'status'      => 'healthy',
				'anchors'     => array(),
				'warnings'    => array(),
			);
		}

		$permalink    = get_permalink( $postId );
		$tableLinks   = esc_sql( $wpdb->prefix . 'quinrel_links' );
		$focusKeyword = mb_strtolower( (string) get_post_meta( $postId, '_quinrel_focus_keyword', true ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT anchor_text, COUNT(*) as link_count FROM {$tableLinks}
                 WHERE target_url = %s GROUP BY anchor_text ORDER BY link_count DESC",
				$permalink
			),
			ARRAY_A
		);

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return array(
				'post_id'     => $postId,
				'total_links' => 0,
				'score'       => 100,
				'status'      => 'natural',
				'anchors'     => array(),
				'warnings'    => array(),
			);
		}

		$totalLinks   = 0;
		$anchors      = array();
		$exactMatches = 0;

		foreach ( $rows as $r ) {
			$text        = trim( (string) $r['anchor_text'] );
			$count       = (int) $r['link_count'];
			$totalLinks += $count;
			$isExact     = ( '' !== $focusKeyword && mb_strtolower( $text ) === $focusKeyword );
			if ( $isExact ) {
				$exactMatches += $count;
			}
			$anchors[] = array(
				'anchor'   => $text ?: '(no anchor text)',
				'count'    => $count,
				'is_exact' => $isExact,
			);
		}

		$exactRatio = $totalLinks > 0 ? ( $exactMatches / $totalLinks ) : 0.0;
		$warnings   = array();
		$score      = 100;

		if ( $exactRatio > 0.50 && $totalLinks >= 4 ) {
			$score      = 60;
			$warnings[] = sprintf(
				/* translators: 1: Percentage of links, 2: Focus keyword */
				__( '%1$d%% of internal inbound links use the exact focus keyword "%2$s". Diversify with branded or partial-match anchor text to avoid over-optimization.', 'quinrel' ),
				(int) round( $exactRatio * 100 ),
				$focusKeyword
			);
		}

		return array(
			'post_id'     => $postId,
			'total_links' => $totalLinks,
			'exact_ratio' => round( $exactRatio * 100, 1 ),
			'score'       => $score,
			'status'      => $score >= 80 ? 'healthy' : 'over_optimized',
			'anchors'     => $anchors,
			'warnings'    => $warnings,
		);
	}
}
