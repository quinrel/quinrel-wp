<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Admin\StandaloneDashboard;
use Quinrel\WordPress\Modules\Indexing\BulkIndexingDispatcher;
use Quinrel\WordPress\Modules\Indexing\GscIndexSyncWorker;

class GscIndexSyncWorkerTest extends TestCase {

	/** @var mixed */
	private $savedVar;

	/** @var mixed */
	private $savedResults;

	/** @var mixed */
	private $savedCaps;

	protected function setUp(): void {
		parent::setUp();
		$this->savedVar     = $GLOBALS['mock_wpdb_var'] ?? null;
		$this->savedResults = $GLOBALS['mock_wpdb_results'] ?? null;
		$this->savedCaps    = $GLOBALS['mock_user_caps'] ?? null;
		$GLOBALS['wpdb']->queries = array();
		unset( $GLOBALS['mock_wpdb_var'], $GLOBALS['mock_wpdb_results'], $GLOBALS['mock_user_caps'] );
		delete_option( 'quinrel_auto_index' );
		delete_option( 'quinrel_indexnow_key' );
		delete_option( 'quinrel_indexnow_queue' );
		delete_option( 'quinrel_indexnow_flush_pending' );
		delete_option( 'quinrel_sitemap_exclude_types' );
		delete_option( 'quinrel_sitemap_exclude_posts' );
		delete_transient( 'quinrel_standalone_counts' );
		unset( $GLOBALS['mock_post_types'] );
	}

	protected function tearDown(): void {
		delete_option( 'quinrel_auto_index' );
		delete_option( 'quinrel_indexnow_key' );
		delete_option( 'quinrel_indexnow_queue' );
		delete_option( 'quinrel_indexnow_flush_pending' );
		delete_option( 'quinrel_sitemap_exclude_types' );
		delete_option( 'quinrel_sitemap_exclude_posts' );
		delete_transient( 'quinrel_standalone_counts' );
		unset( $GLOBALS['mock_post_types'] );
		$GLOBALS['wpdb']->queries = array();
		if ( null === $this->savedVar ) {
			unset( $GLOBALS['mock_wpdb_var'] );
		} else {
			$GLOBALS['mock_wpdb_var'] = $this->savedVar;
		}
		if ( null === $this->savedResults ) {
			unset( $GLOBALS['mock_wpdb_results'] );
		} else {
			$GLOBALS['mock_wpdb_results'] = $this->savedResults;
		}
		if ( null === $this->savedCaps ) {
			unset( $GLOBALS['mock_user_caps'] );
		} else {
			$GLOBALS['mock_user_caps'] = $this->savedCaps;
		}
		parent::tearDown();
	}

	public function testFunnelLeavesGoogleStagesUnmeasuredWithoutIndexRows(): void {
		$published = 4;
		$sitemap   = 4;
		$this->mockCounts( $published, $sitemap, null );

		$data   = ( new GscIndexSyncWorker() )->getFunnelDataResponse( new \WP_REST_Request() )->get_data();
		$stages = $this->stagesByName( $data['funnel'] );

		$this->assertSame( $published, $stages['Published Content']['count'] );
		$this->assertTrue( $stages['Published Content']['measured'] );
		$this->assertSame( $sitemap, $stages['Included in XML Sitemap']['count'] );
		$this->assertTrue( $stages['Included in XML Sitemap']['measured'] );
		$this->assertGoogleStagesUnmeasured( $stages, $published, $sitemap );
		$this->assertFalse( $data['coverage_measured'] );
		$this->assertSame( array(), $data['coverage_groups'] );
		$this->assertStringNotContainsString( 'verdict', implode( "\n", $GLOBALS['wpdb']->queries ) );
		$this->assertSitemapQueryIgnoresLooseNoindexRows();

		$worker = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Modules/Indexing/GscIndexSyncWorker.php' );
		$this->assertStringNotContainsString( '0.85', $worker );
		$this->assertStringNotContainsString( '0.92', $worker );
		$this->assertStringNotContainsString( '0.96', $worker );
		$this->assertStringNotContainsString( '0.75', $worker );
	}

	public function testNoindexedDraftDoesNotReduceSitemapEligibleCount(): void {
		$GLOBALS['mock_post_types'] = array( 'post', 'page', 'product' );
		update_option( 'quinrel_sitemap_exclude_types', array( 'product' ) );
		update_option( 'quinrel_sitemap_exclude_posts', '9,0,-3' );
		$this->mockCounts( 4, 4, null );

		$data   = ( new GscIndexSyncWorker() )->getFunnelDataResponse( new \WP_REST_Request() )->get_data();
		$stages = $this->stagesByName( $data['funnel'] );
		$sql    = implode( "\n", $GLOBALS['wpdb']->queries );

		$this->assertSame( 4, $stages['Published Content']['count'] );
		$this->assertSame( 4, $stages['Included in XML Sitemap']['count'] );
		$this->assertStringContainsString( "'post'", $sql );
		$this->assertStringContainsString( "'page'", $sql );
		$this->assertStringNotContainsString( "'product'", $sql );
		$this->assertStringContainsString( 'NOT IN (9)', $sql );
		$this->assertSitemapQueryIgnoresLooseNoindexRows();
	}

	public function testAnEmptyIndexTableIsNotAGoogleResult(): void {
		$this->mockCounts( 4, 4, 'wp_quinrel_index_status' );

		$data   = ( new GscIndexSyncWorker() )->getFunnelDataResponse( new \WP_REST_Request() )->get_data();
		$stages = $this->stagesByName( $data['funnel'] );
		$sql    = implode( "\n", $GLOBALS['wpdb']->queries );

		$this->assertGoogleStagesUnmeasured( $stages, 4, 4 );
		$this->assertFalse( $data['coverage_measured'] );
		$this->assertStringContainsString( 'Indexed, not submitted in sitemap', $sql );
		$this->assertStringNotContainsString( 'verdict', $sql );
	}

	public function testSubmittedAndIndexedDoesNotCountAsDiscoveredOrCrawled(): void {
		$this->mockCounts(
			4,
			4,
			'wp_quinrel_index_status',
			array(
				array(
					'verdict'        => 'PASS',
					'coverage_state' => 'Submitted and indexed',
				),
				array(
					'verdict'        => 'PASS',
					'coverage_state' => 'Indexed, not submitted in sitemap',
				),
			)
		);

		$data   = ( new GscIndexSyncWorker() )->getFunnelDataResponse( new \WP_REST_Request() )->get_data();
		$stages = $this->stagesByName( $data['funnel'] );
		$sql    = implode( "\n", $GLOBALS['wpdb']->queries );

		$this->assertStringContainsString( "coverage_state = 'Discovered - currently not indexed'", $sql );
		$this->assertStringContainsString( "coverage_state = 'Crawled - currently not indexed'", $sql );
		$this->assertStringContainsString( "verdict = 'PASS' AND coverage_state = 'Submitted and indexed'", $sql );
		$this->assertStringContainsString( "coverage_state <> 'Indexed, not submitted in sitemap'", $sql );
		$this->assertFalse( $stages['Discovered by Google']['measured'] );
		$this->assertNull( $stages['Discovered by Google']['count'] );
		$this->assertFalse( $stages['Crawled by Googlebot']['measured'] );
		$this->assertNull( $stages['Crawled by Googlebot']['count'] );
		$this->assertTrue( $stages['Indexed in Google Search']['measured'] );
		$this->assertSame( 1, $stages['Indexed in Google Search']['count'] );
		$this->assertSame( 25, $stages['Indexed in Google Search']['percent'] );
		$this->assertFalse( $stages['Generating Impressions (90d)']['measured'] );
		$this->assertTrue( $data['coverage_measured'] );
		$this->assertSame( 'Submitted and indexed', $data['coverage_groups'][0]['coverage_state'] );
		$this->assertSame( '1', (string) $data['coverage_groups'][0]['count'] );
		$this->assertCount( 1, $data['coverage_groups'] );
	}

	public function testDiscoveredCoverageMeasuresOnlyThatStage(): void {
		$this->mockCounts(
			4,
			4,
			'wp_quinrel_index_status',
			array(
				array(
					'verdict'        => 'NEUTRAL',
					'coverage_state' => 'Discovered - currently not indexed',
				),
			)
		);

		$data   = ( new GscIndexSyncWorker() )->getFunnelDataResponse( new \WP_REST_Request() )->get_data();
		$stages = $this->stagesByName( $data['funnel'] );

		$this->assertTrue( $stages['Discovered by Google']['measured'] );
		$this->assertSame( 1, $stages['Discovered by Google']['count'] );
		$this->assertFalse( $stages['Crawled by Googlebot']['measured'] );
		$this->assertFalse( $stages['Indexed in Google Search']['measured'] );
		$this->assertSame( 'Discovered - currently not indexed', $data['coverage_groups'][0]['coverage_state'] );
	}

	public function testSyncBatchWritesNothing(): void {
		$GLOBALS['wpdb']->queries = array();
		$result                   = ( new GscIndexSyncWorker() )->syncBatchResponse( new \WP_REST_Request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'quinrel_index_unmeasured', $result->get_error_code() );
		$this->assertSame( 501, $result->get_error_data()['status'] );
		$this->assertSame( array(), $GLOBALS['wpdb']->queries );
	}

	public function testBulkPushQueuesOnlyWhenIndexNowIsOn(): void {
		$dispatcher = new BulkIndexingDispatcher();
		$off        = new \WP_REST_Request();
		$off->set_json_params( array( 'post_ids' => array( 5 ) ) );
		$blocked = $dispatcher->dispatchBulkPushResponse( $off );

		$this->assertFalse( $blocked->get_data()['success'] );
		$this->assertSame( 400, $blocked->get_status() );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] ?? array() );

		update_option( 'quinrel_auto_index', 'yes' );
		update_option( 'quinrel_indexnow_key', 'abcd1234' );
		$on = new \WP_REST_Request();
		$on->set_json_params( array( 'post_ids' => array( 5 ) ) );
		$queued = $dispatcher->dispatchBulkPushResponse( $on )->get_data();

		$this->assertTrue( $queued['success'] );
		$this->assertSame( 1, $queued['submitted_count'] );
		$this->assertSame( array( 'https://example.com/post-5/' ), get_option( 'quinrel_indexnow_queue' ) );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] ?? array() );
	}

	public function testSnapshotReportsIndexNowFromTheRealSwitch(): void {
		$dashboard = new StandaloneDashboard();
		$off       = $dashboard->snapshot( new \WP_REST_Request() )->get_data();
		$this->assertFalse( $off['indexnow_enabled'] );

		update_option( 'quinrel_auto_index', 'yes' );
		update_option( 'quinrel_indexnow_key', 'abcd1234' );
		$on = $dashboard->snapshot( new \WP_REST_Request() )->get_data();
		$this->assertTrue( $on['indexnow_enabled'] );
	}

	public function testAdminSourcesDoNotShipSampleAuditFigures(): void {
		$root  = dirname( __DIR__, 2 );
		$crawl = (string) file_get_contents( $root . '/src/assets/js/components/charts/CrawlDepthChart.js' );
		$a11y  = (string) file_get_contents( $root . '/src/assets/js/components/charts/AccessibilityBreakdownChart.js' );
		$funnel = (string) file_get_contents( $root . '/src/assets/js/components/charts/IndexationFunnelChart.js' );
		$actions = (string) file_get_contents( $root . '/src/assets/js/components/ActionItemsTodo.js' );

		$this->assertStringContainsString( 'Click depth has not been measured on this site.', $crawl );
		$this->assertStringNotContainsString( 'Excellent Crawl Efficiency', $crawl );
		$this->assertStringNotContainsString( 'Live Link Index Active', $crawl );
		$this->assertStringNotContainsString( '94', $crawl );
		$this->assertStringNotContainsString( '86', $crawl );
		$this->assertStringNotContainsString( '105', $crawl );
		$this->assertStringNotContainsString( '184', $crawl );

		$this->assertStringContainsString( 'A WCAG score has not been measured on this site.', $a11y );
		$this->assertStringNotContainsString( 'WCAG 2.1 Level AA Passing', $a11y );
		$this->assertStringNotContainsString( '88', $a11y );
		$this->assertStringNotContainsString( '252', $a11y );
		$this->assertStringNotContainsString( '142', $a11y );
		$this->assertStringNotContainsString( '86', $a11y );
		$this->assertStringNotContainsString( '24', $a11y );

		$this->assertStringContainsString( 'Not measured', $funnel );
		$this->assertStringContainsString( 'Search Console coverage has not been measured on this site.', $funnel );
		$this->assertStringNotContainsString( '168 URLs', $funnel );
		$this->assertStringNotContainsString( '12 URLs', $funnel );
		$this->assertStringNotContainsString( '8 URLs', $funnel );
		$this->assertStringNotContainsString( 'count: 200', $funnel );
		$this->assertStringNotContainsString( 'count: 168', $funnel );
		$this->assertStringNotContainsString( 'count: 132', $funnel );

		$this->assertSame( 'info', $this->severityFor( $actions, 'self_healing_links' ) );
		$this->assertSame( 'info', $this->severityFor( $actions, 'indexation_funnel' ) );
		$this->assertStringNotContainsString( 'automatic update pings', $actions );
		$this->assertStringNotContainsString( 'missing_titles_count', $actions );
		$this->assertStringNotContainsString( 'missing_alt_count', $actions );
		$this->assertStringNotContainsString( 'not_found_count', $actions );
		$this->assertStringContainsString( 'indexnow_enabled', $actions );
	}

	/**
	 * @param array<int, array<string, mixed>> $funnel
	 * @return array<string, array<string, mixed>>
	 */
	private function stagesByName( array $funnel ): array {
		$stages = array();
		foreach ( $funnel as $stage ) {
			$stages[ $stage['stage'] ] = $stage;
		}
		return $stages;
	}

	/**
	 * @param array<string, array<string, mixed>> $stages
	 */
	private function assertGoogleStagesUnmeasured( array $stages, int $published, int $sitemap ): void {
		$fractions = array(
			(int) round( $sitemap * 0.96 ),
			(int) round( $sitemap * 0.92 ),
			(int) round( $sitemap * 0.85 ),
			(int) round( $sitemap * 0.75 ),
			(int) round( $published * 0.96 ),
			(int) round( $published * 0.92 ),
			(int) round( $published * 0.85 ),
			(int) round( $published * 0.75 ),
		);
		foreach ( array( 'Discovered by Google', 'Crawled by Googlebot', 'Indexed in Google Search', 'Generating Impressions (90d)' ) as $name ) {
			$this->assertFalse( $stages[ $name ]['measured'], $name );
			$this->assertNull( $stages[ $name ]['count'], $name );
			$this->assertNull( $stages[ $name ]['percent'], $name );
			$this->assertNotContains( $stages[ $name ]['count'], $fractions, $name );
		}
	}

	private function severityFor( string $source, string $id ): string {
		$matched = preg_match( "/id: '" . $id . "'[\\s\\S]{0,500}?severity: '([^']+)'/", $source, $matches );
		$this->assertSame( 1, $matched, $id );
		return $matches[1];
	}

	private function assertSitemapQueryIgnoresLooseNoindexRows(): void {
		$sawJoin = false;
		foreach ( $GLOBALS['wpdb']->queries as $query ) {
			$query = (string) $query;
			if ( ! str_contains( $query, '_quinrel_noindex' ) ) {
				continue;
			}
			$this->assertStringContainsString( 'LEFT JOIN', $query );
			$this->assertStringContainsString( "post_status = 'publish'", $query );
			$this->assertStringContainsString( 'p.post_type IN', $query );
			$sawJoin = true;
		}
		$this->assertTrue( $sawJoin );
	}

	/**
	 * @param array<int, array<string, string>> $rows
	 */
	private function mockCounts( int $published, int $sitemap, ?string $table, array $rows = array() ): void {
		$GLOBALS['mock_wpdb_var']     = static function ( $query ) use ( $published, $sitemap, $table, $rows ) {
			$query = (string) $query;
			if ( str_contains( $query, 'SHOW TABLES' ) ) {
				$plain = str_replace( '\\', '', $query );
				return str_contains( $plain, 'quinrel_index_status' ) ? $table : null;
			}
			if ( str_contains( $query, '_quinrel_noindex' ) && str_contains( $query, 'post_status' ) ) {
				return (string) $sitemap;
			}
			if ( str_contains( $query, 'post_status' ) ) {
				return (string) $published;
			}
			if ( str_contains( $query, 'quinrel_index_status' ) ) {
				return (string) self::rowsMatchingCoverageQuery( $rows, $query );
			}
			return '99';
		};
		$GLOBALS['mock_wpdb_results'] = static function ( $query ) use ( $rows ) {
			$query = (string) $query;
			if ( ! str_contains( $query, 'GROUP BY' ) ) {
				return array();
			}
			$counts = array();
			foreach ( $rows as $row ) {
				$coverage = (string) ( $row['coverage_state'] ?? '' );
				if ( '' === $coverage ) {
					continue;
				}
				if ( str_contains( $query, 'Indexed, not submitted in sitemap' ) && 'Indexed, not submitted in sitemap' === $coverage ) {
					continue;
				}
				$counts[ $coverage ] = ( $counts[ $coverage ] ?? 0 ) + 1;
			}
			$groups = array();
			foreach ( $counts as $coverage => $count ) {
				$groups[] = array(
					'coverage_state' => $coverage,
					'count'          => (string) $count,
				);
			}
			return $groups;
		};
	}

	/**
	 * @param array<int, array<string, string>> $rows
	 */
	private static function rowsMatchingCoverageQuery( array $rows, string $query ): int {
		$answers = array(
			'Discovered - currently not indexed',
			'Crawled - currently not indexed',
			'Submitted and indexed',
		);
		foreach ( $answers as $coverage ) {
			if ( ! str_contains( $query, "coverage_state = '" . $coverage . "'" ) ) {
				continue;
			}
			$requirePass = str_contains( $query, "verdict = 'PASS'" );
			$count       = 0;
			foreach ( $rows as $row ) {
				if ( (string) ( $row['coverage_state'] ?? '' ) !== $coverage ) {
					continue;
				}
				if ( $requirePass && 'PASS' !== (string) ( $row['verdict'] ?? '' ) ) {
					continue;
				}
				++$count;
			}
			return $count;
		}

		if ( str_contains( $query, 'Indexed, not submitted in sitemap' ) ) {
			$count = 0;
			foreach ( $rows as $row ) {
				$coverage = (string) ( $row['coverage_state'] ?? '' );
				if ( '' !== $coverage && 'Indexed, not submitted in sitemap' !== $coverage ) {
					++$count;
				}
			}
			return $count;
		}

		return 0;
	}
}
