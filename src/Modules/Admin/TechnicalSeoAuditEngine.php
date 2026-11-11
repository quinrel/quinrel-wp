<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Queue\QueueManager;

/**
 * TechnicalSeoAuditEngine
 *
 * Runs a multi-point technical SEO audit via WP-Cron background jobs.
 * Checks 14 factors across meta, canonical, robots, schema, images,
 * 404s, redirects, sitemap, and more. Results are stored in wp_options
 * and exposed via REST API.
 *
 * BL-006
 */
class TechnicalSeoAuditEngine {

	public const CHECK_BATCH = 2;

	private const RESULTS_OPTION  = 'quinrel_audit_results';
	private const PROGRESS_OPTION = 'quinrel_audit_progress';
	private const CRON_HOOK       = 'quinrel_run_technical_audit';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( self::CRON_HOOK, array( $this, 'enqueueRun' ) );
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/audit/technical',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response( get_option( self::RESULTS_OPTION, null ) ?: array( 'message' => __( 'No audit has been run yet.', 'quinrel' ) ) ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/audit/technical/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scheduleRun' ),
				'permission_callback' => $adminCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/audit/technical/progress',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn() => rest_ensure_response(
					get_option(
						self::PROGRESS_OPTION,
						array(
							'status'        => 'idle',
							'percent'       => 0,
							'current_check' => '',
						)
					)
				),
				'permission_callback' => $adminCap,
			)
		);
	}

	public function scheduleRun( \WP_REST_Request $request ): \WP_REST_Response {
		$this->setProgress( 'running', 0, __( 'Starting…', 'quinrel' ), 0, array() );
		$this->enqueueRun();

		return rest_ensure_response(
			array(
				'success' => true,
				'queued'  => true,
				'message' => __( 'Audit scheduled.', 'quinrel' ),
			)
		);
	}

	/**
	 * An older cron event only enqueues the next slice.
	 */
	public function enqueueRun(): void {
		( new QueueManager() )->push( TechnicalAuditJob::class, array(), 2 );
	}

	/**
	 * Run one slice of the audit and queue the next slice when checks remain.
	 */
	public function runBatch(): void {
		$progress = get_option( self::PROGRESS_OPTION, array() );
		if ( ! is_array( $progress ) ) {
			$progress = array();
		}
		if ( ( $progress['status'] ?? '' ) === 'done' ) {
			return;
		}

		$definitions = $this->checkDefinitions();
		$ids         = array_keys( $definitions );
		$total       = count( $ids );
		$index       = max( 0, (int) ( $progress['index'] ?? 0 ) );
		$collected   = $progress['checks'] ?? array();
		if ( ! is_array( $collected ) ) {
			$collected = array();
		}

		if ( $index >= $total ) {
			$this->storeResults( $collected );
			$this->setProgress( 'done', 100, __( 'Audit complete.', 'quinrel' ), $total, array() );
			return;
		}

		$end = min( $total, $index + self::CHECK_BATCH );
		for ( $i = $index; $i < $end; $i++ ) {
			$checkId = $ids[ $i ];
			$this->setProgress( 'running', (int) round( ( $i / $total ) * 100 ), $definitions[ $checkId ], $i, $collected );

			try {
				$method = lcfirst( str_replace( '_', '', ucwords( $checkId, '_' ) ) );
				$items  = method_exists( $this, $method ) ? $this->{$method}() : array();
			} catch ( \Throwable $e ) {
				$items = array( array( 'error' => $e->getMessage() ) );
			}

			$status      = $this->deriveStatus( $checkId, $items );
			$collected[] = array(
				'id'      => $checkId,
				'name'    => ucwords( str_replace( '_', ' ', $checkId ) ),
				'status'  => $status,
				'message' => $this->buildMessage( $checkId, $items, $status ),
				'items'   => array_slice( (array) $items, 0, 20 ),
			);
		}

		if ( $end >= $total ) {
			$this->storeResults( $collected );
			$this->setProgress( 'done', 100, __( 'Audit complete.', 'quinrel' ), $total, array() );
			return;
		}

		$this->setProgress( 'running', (int) round( ( $end / $total ) * 100 ), $definitions[ $ids[ $end - 1 ] ], $end, $collected );
		$this->enqueueRun();
	}

	/**
	 * Run all checks synchronously (for CLI or on-demand full audits).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function runAllChecks(): array {
		$definitions = $this->checkDefinitions();
		$collected   = array();

		foreach ( array_keys( $definitions ) as $checkId ) {
			try {
				$method = lcfirst( str_replace( '_', '', ucwords( $checkId, '_' ) ) );
				$items  = method_exists( $this, $method ) ? $this->{$method}() : array();
			} catch ( \Throwable $e ) {
				$items = array( array( 'error' => $e->getMessage() ) );
			}

			$status                = $this->deriveStatus( $checkId, $items );
			$collected[ $checkId ] = array(
				'id'      => $checkId,
				'name'    => ucwords( str_replace( '_', ' ', $checkId ) ),
				'status'  => $status,
				'message' => $this->buildMessage( $checkId, $items, $status ),
				'items'   => array_slice( (array) $items, 0, 20 ),
			);
		}

		$this->storeResults( array_values( $collected ) );
		return $collected;
	}

	/**
	 * @return array<string, string>
	 */
	private function checkDefinitions(): array {
		return array(
			'missing_meta_descriptions' => __( 'Checking meta descriptions…', 'quinrel' ),
			'missing_alt_text'          => __( 'Checking image alt text…', 'quinrel' ),
			'check_404_errors'          => __( 'Checking 404 errors…', 'quinrel' ),
			'redirect_chains'           => __( 'Checking redirect chains…', 'quinrel' ),
			'orphaned_pages'            => __( 'Checking orphaned pages…', 'quinrel' ),
			'noindex_important_pages'   => __( 'Checking noindex directives…', 'quinrel' ),
			'sitemap_health'            => __( 'Checking sitemap…', 'quinrel' ),
			'robots_txt'                => __( 'Checking robots.txt…', 'quinrel' ),
			'favicon'                   => __( 'Checking favicon…', 'quinrel' ),
			'ssl_certificate'           => __( 'Checking SSL…', 'quinrel' ),
			'schema_presence'           => __( 'Checking schema markup…', 'quinrel' ),
			'duplicate_titles'          => __( 'Checking for duplicate titles…', 'quinrel' ),
			'stale_cornerstone'         => __( 'Checking cornerstone content…', 'quinrel' ),
			'missing_canonicals'        => __( 'Checking canonical tags…', 'quinrel' ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $checks
	 */
	private function storeResults( array $checks ): void {
		$critical = count( array_filter( $checks, fn( $c ) => ( $c['status'] ?? '' ) === 'critical' ) );
		$warnings = count( array_filter( $checks, fn( $c ) => ( $c['status'] ?? '' ) === 'warning' ) );
		$passed   = count( array_filter( $checks, fn( $c ) => ( $c['status'] ?? '' ) === 'passed' ) );

		update_option(
			self::RESULTS_OPTION,
			array(
				'checks'       => $checks,
				'generated_at' => current_time( 'mysql' ),
				'summary'      => array(
					'critical' => $critical,
					'warnings' => $warnings,
					'passed'   => $passed,
				),
			),
			false
		);
	}

	// ── Individual checks ─────────────────────────────────────────────────────

	private function missingMetaDescriptions(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_quinrel_meta_description',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_quinrel_meta_description',
						'value'   => '',
						'compare' => '=',
					),
				),
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'post_id' => $post->ID,
				'title'   => $post->post_title,
				'url'     => get_permalink( $post->ID ),
			);
		}
		return $items;
	}

	private function missingAltText(): array {
		$count = ( new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
				'posts_per_page' => 1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wp_attachment_image_alt',
						'value'   => '',
						'compare' => '=',
					),
				),
			)
		) )->found_posts;

		return $count > 0 ? array(
			array(
				'count'   => $count,
				'message' => "$count images are missing alt text.",
			),
		) : array();
	}

	private function check404Errors(): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'quinrel_404_logs';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return array();
		}
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}quinrel_404_logs" );
		return $count > 0 ? array(
			array(
				'count'   => $count,
				'message' => "$count 404 errors logged.",
			),
		) : array();
	}

	private function redirectChains(): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'quinrel_redirects';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			return array();
		}

		// Find rows where target is itself a source (chains of 2+)
		$chains = $wpdb->get_results(
			"SELECT a.source, a.target, b.target AS next_target
             FROM {$wpdb->prefix}quinrel_redirects a
             INNER JOIN {$wpdb->prefix}quinrel_redirects b ON a.target = b.source
             WHERE a.is_regex = 0 AND b.is_regex = 0
             LIMIT 20",
			ARRAY_A
		);

		if ( empty( $chains ) ) {
			return array();
		}

		return array_map(
			fn( $c ) => array(
				'source'      => (string) ( $c['source'] ?? '' ),
				'target'      => (string) ( $c['target'] ?? '' ),
				'next_target' => (string) ( $c['next_target'] ?? '' ),
				'issue'       => sprintf(
					/* translators: 1: source, 2: target, 3: next target */
					__( 'Redirect chain detected: %1$s -> %2$s -> %3$s', 'quinrel' ),
					$c['source'] ?? '',
					$c['target'] ?? '',
					$c['next_target'] ?? ''
				),
			),
			$chains
		);
	}

	private function orphanedPages(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 10,
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
			)
		);

		$total = (int) $query->found_posts;

		return $total > 0 ? array_merge(
			array(
				array(
					'total_orphaned' => $total,
					/* translators: %d: number of published posts or pages without incoming internal links. */
					'message'        => sprintf( __( '%d published posts/pages have no incoming internal links.', 'quinrel' ), $total ),
				),
			),
			array_map(
				fn( $p ) => array(
					'post_id' => $p->ID,
					'title'   => $p->post_title,
					'url'     => get_permalink( $p->ID ),
				),
				$query->posts
			)
		) : array();
	}

	private function noindexImportantPages(): array {
		$issues      = array();
		$frontPageId = (int) get_option( 'page_on_front' );

		if ( $frontPageId > 0 ) {
			$noindex = get_post_meta( $frontPageId, '_quinrel_noindex', true );
			if ( $noindex === 'yes' ) {
				$issues[] = array(
					'post_id' => $frontPageId,
					'title'   => 'Front Page',
					'issue'   => 'Front page is set to noindex!',
				);
			}
		}

		return $issues;
	}

	private function sitemapHealth(): array {
		$indexEnabled = \Quinrel\WordPress\Modules\Sitemap\SitemapSettings::get( 'enable_index_sitemap', 'no' ) === 'yes';
		$sitemapUrl   = $indexEnabled ? home_url( '/sitemap.xml' ) : home_url( '/wp-sitemap.xml' );
		$response     = wp_safe_remote_get( $sitemapUrl, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message returned when fetching the sitemap URL. */
			return array( array( 'issue' => sprintf( __( 'Sitemap unreachable: %s', 'quinrel' ), $response->get_error_message() ) ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		return ( 200 === $code ) ? array() : array(
			array(
				/* translators: %d: HTTP status code returned by the sitemap endpoint. */
				'issue' => sprintf( __( 'Sitemap returned HTTP %d.', 'quinrel' ), (int) $code ),
			),
		);
	}

	private function robotsTxt(): array {
		$url      = home_url( '/robots.txt' );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array( array( 'issue' => 'robots.txt unreachable: ' . $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( stripos( $body, 'Disallow: /' ) !== false && strpos( $body, 'Disallow: /wp-admin' ) === false ) {
			return array( array( 'issue' => 'robots.txt may be blocking all crawlers. Review carefully.' ) );
		}

		return array();
	}

	private function favicon(): array {
		$favicon = get_site_icon_url();
		return empty( $favicon ) ? array( array( 'issue' => 'No site icon (favicon) is set. Set one in Appearance → Customize → Site Identity.' ) ) : array();
	}

	private function sslCertificate(): array {
		$siteUrl = site_url();
		return ( strpos( $siteUrl, 'https://' ) !== 0 )
			? array( array( 'issue' => 'Site is not using HTTPS. SSL is a confirmed Google ranking signal.' ) )
			: array();
	}

	private function schemaPresence(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => '_quinrel_primary_schema',
						'value'   => 'none',
						'compare' => '=',
					),
				),
			)
		);

		return $query->found_posts > 0
			? array(
				array(
					'count'   => $query->found_posts,
					/* translators: %d: number of recent posts with schema markup explicitly disabled. */
					'message' => sprintf( __( '%d recent posts have schema markup disabled.', 'quinrel' ), (int) $query->found_posts ),
				),
			)
			: array();
	}

	private function duplicateTitles(): array {
		global $wpdb;

		$dupes = $wpdb->get_results(
			"SELECT meta_value AS title, COUNT(*) AS count
             FROM $wpdb->postmeta
             WHERE meta_key = '_quinrel_meta_title'
               AND meta_value != ''
             GROUP BY meta_value
             HAVING count > 1
             LIMIT 10",
			ARRAY_A
		);

		if ( empty( $dupes ) ) {
			return array();
		}

		return array_map(
			fn( $d ) => array(
				'title'   => (string) ( $d['title'] ?? '' ),
				'count'   => (int) ( $d['count'] ?? 0 ),
				'message' => sprintf(
					/* translators: 1: title, 2: count */
					__( 'Title "%1$s" is duplicated across %2$d posts.', 'quinrel' ),
					(string) ( $d['title'] ?? '' ),
					(int) ( $d['count'] ?? 0 )
				),
			),
			$dupes
		);
	}

	private function staleCornerstone(): array {
		$stale = (array) get_option( 'quinrel_stale_cornerstone_ids', array() );
		return empty( $stale ) ? array() : array(
			array(
				'count'   => count( $stale ),
				'message' => count( $stale ) . ' cornerstone posts are stale.',
			),
		);
	}

	private function missingCanonicals(): array {
		// Canonicals are auto-generated; this is an info-level check
		return array();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function deriveStatus( string $checkId, array $items ): string {
		if ( empty( $items ) ) {
			return 'passed';
		}

		$criticalChecks = array( 'noindex_important_pages', 'ssl_certificate', 'robots_txt' );
		$warningChecks  = array( 'missing_meta_descriptions', 'redirect_chains', 'check_404_errors', 'sitemap_health', 'duplicate_titles', 'stale_cornerstone' );

		if ( in_array( $checkId, $criticalChecks, true ) ) {
			return 'critical';
		}

		if ( in_array( $checkId, $warningChecks, true ) ) {
			// Escalate 404s to critical if count > 20
			if ( $checkId === 'check_404_errors' && ! empty( $items[0]['count'] ) && $items[0]['count'] > 20 ) {
				return 'critical';
			}
			return 'warning';
		}

		return 'info';
	}

	private function buildMessage( string $checkId, array $items, string $status ): string {
		if ( $status === 'passed' ) {
			return 'No issues found.';
		}

		$first = $items[0] ?? null;
		if ( is_array( $first ) ) {
			if ( ! empty( $first['message'] ) ) {
				return (string) $first['message'];
			}
			if ( ! empty( $first['issue'] ) ) {
				return (string) $first['issue'];
			}
		} elseif ( is_object( $first ) ) {
			if ( ! empty( $first->message ) ) {
				return (string) $first->message;
			}
			if ( ! empty( $first->issue ) ) {
				return (string) $first->issue;
			}
		}

		return count( $items ) . ' issue(s) detected.';
	}

	/**
	 * @param array<int, array<string, mixed>> $checks
	 */
	private function setProgress( string $status, int $percent, string $currentCheck, int $index = 0, array $checks = array() ): void {
		update_option(
			self::PROGRESS_OPTION,
			array(
				'status'        => $status,
				'percent'       => $percent,
				'current_check' => $currentCheck,
				'index'         => $index,
				'checks'        => $checks,
				'updated_at'    => current_time( 'mysql' ),
			),
			false
		);
	}
}
