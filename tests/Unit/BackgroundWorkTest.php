<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Core\Security\KeyVault;
use Quinrel\WordPress\Modules\Admin\TechnicalSeoAuditEngine;
use Quinrel\WordPress\Modules\Analytics\KeywordRankTracker;
use Quinrel\WordPress\Modules\Indexing\BingIndexNowApi;
use Quinrel\WordPress\Modules\Indexing\IndexNowFlushJob;
use Quinrel\WordPress\Modules\Social\AutoShareJob;
use Quinrel\WordPress\Modules\Social\SocialModule;
use Quinrel\WordPress\Modules\TechnicalSeo\BrokenLinkChecker;
use Quinrel\WordPress\Modules\TechnicalSeo\BrokenLinkScanJob;
use Quinrel\WordPress\Modules\TechnicalSeo\OrphanedContentFinder;
use Quinrel\WordPress\Modules\TechnicalSeo\OrphanScanJob;
use PHPUnit\Framework\TestCase;

class BackgroundWorkTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_http_log']       = array();
		$GLOBALS['mock_http_responses'] = array();
		$GLOBALS['mock_cron_events']    = array();
		$GLOBALS['mock_user_caps']      = array(
			'manage_options' => true,
			'manage_seo'     => true,
		);
		unset(
			$GLOBALS['mock_wpdb_col'],
			$GLOBALS['mock_wpdb_var'],
			$GLOBALS['mock_wpdb_update'],
			$GLOBALS['mock_wp_query'],
			$GLOBALS['mock_http_response']
		);
		delete_option( BingIndexNowApi::QUEUE_OPTION );
		delete_option( BingIndexNowApi::FLUSH_PENDING );
		delete_option( 'quinrel_indexnow_key' );
		delete_option( 'quinrel_auto_index' );
		delete_option( 'quinrel_blc_scan_lock' );
		delete_option( 'quinrel_blc_progress' );
		BrokenLinkChecker::clearCache();
		delete_option( 'quinrel_orphan_scan_pending' );
		delete_option( 'quinrel_orphan_scan_last_run' );
		delete_option( 'quinrel_audit_progress' );
		delete_option( 'quinrel_audit_results' );
		delete_option( 'quinrel_tracked_keywords' );
		delete_option( 'quinrel_serpapi_key' );
		delete_option( 'quinrel_social_accounts' );
		delete_option( 'quinrel_global_auto_share' );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['mock_user_caps'],
			$GLOBALS['mock_wpdb_col'],
			$GLOBALS['mock_wpdb_var'],
			$GLOBALS['mock_wpdb_update'],
			$GLOBALS['mock_wp_query'],
			$GLOBALS['mock_posts'][4],
			$GLOBALS['mock_posts'][11],
			$GLOBALS['mock_posts'][12],
			$GLOBALS['mock_post_meta'][4]
		);
		parent::tearDown();
	}

	public function testIndexNowPublishSchedulesOneBatchJob(): void {
		update_option( 'quinrel_indexnow_key', 'abcd1234' );
		update_option( 'quinrel_auto_index', 'yes' );
		$GLOBALS['mock_http_responses']['api.indexnow.org'] = array(
			'response' => array( 'code' => 202 ),
		);

		$api = new BingIndexNowApi();
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->assertTrue( $api->enqueueUrl( 'https://example.com/queued-' . $i . '/' ) );
		}

		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$jobs = $this->jobsFor( IndexNowFlushJob::class );
		$this->assertCount( 1, $jobs );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertCount( 1, $GLOBALS['mock_http_log'] );
		$body = json_decode( $GLOBALS['mock_http_log'][0]['args']['body'], true );
		$this->assertCount( BingIndexNowApi::BATCH_SIZE, $body['urlList'] );
		$this->assertCount( 2, $api->getQueue() );
		$this->assertCount( 1, $this->jobsFor( IndexNowFlushJob::class ) );
	}

	public function testManualShareAndPublishOnlyEnqueueABoundedAccountBatch(): void {
		update_option( 'quinrel_encryption_salt', 'social-test-salt-0123456789abcdef' );
		update_option( 'quinrel_global_auto_share', 'yes' );
		$accounts = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$token = KeyVault::encrypt( 'token-' . $i );
			$this->assertIsString( $token );
			$accounts[] = array(
				'id'           => 'facebook_' . $i,
				'network'      => 'facebook',
				'access_token' => $token,
				'auto_share'   => true,
			);
		}
		update_option( 'quinrel_social_accounts', $accounts );
		$GLOBALS['mock_posts'][4] = new \WP_Post(
			array(
				'ID'           => 4,
				'post_status'  => 'publish',
				'post_title'   => 'Hello',
				'post_excerpt' => 'Excerpt',
			)
		);
		$GLOBALS['mock_http_responses']['graph.facebook.com'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"id":"1"}',
		);

		$module  = new SocialModule( new EventManager() );
		$request = new \WP_REST_Request();
		$request->set_param( 'post_id', 4 );
		$manual = $module->manualShare( $request );

		$this->assertTrue( $manual->get_data()['queued'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$this->assertCount( 1, $this->jobsFor( AutoShareJob::class ) );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertCount( AutoShareJob::ACCOUNT_BATCH, $GLOBALS['mock_http_log'] );
		$continuation = $this->jobsFor( AutoShareJob::class );
		$this->assertCount( 1, $continuation );
		$this->assertSame( AutoShareJob::ACCOUNT_BATCH, $continuation[0]['args'][1]['offset'] );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertCount( 4, $GLOBALS['mock_http_log'] );
		$this->assertSame( array(), $this->jobsFor( AutoShareJob::class ) );

		$GLOBALS['mock_http_log']    = array();
		$GLOBALS['mock_cron_events'] = array();
		unset( $GLOBALS['mock_post_meta'][4] );
		$module->handlePostTransition( 'publish', 'draft', $GLOBALS['mock_posts'][4] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$this->assertCount( 1, $this->jobsFor( AutoShareJob::class ) );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertNotSame( '1', get_post_meta( 4, '_quinrel_auto_shared', true ) );
		$this->assertSame( 1, $this->drainJobs() );
		$this->assertSame( '1', get_post_meta( 4, '_quinrel_auto_shared', true ) );
	}

	public function testBrokenLinkScanChecksAFixedNumberOfUrlsAndResumes(): void {
		$GLOBALS['mock_http_responses']['8.8.8.8'] = array(
			'response' => array( 'code' => 404 ),
		);
		$GLOBALS['mock_wpdb_col'] = static function ( $query ) {
			if ( ! is_string( $query ) || ! preg_match( '/ID > (\d+)/', $query, $matches ) ) {
				return array();
			}
			$after = (int) $matches[1];
			foreach ( array( 11, 12 ) as $id ) {
				if ( $id > $after ) {
					return array( $id );
				}
			}
			return array();
		};
		$GLOBALS['mock_wpdb_var'] = static function ( $query ) {
			return is_string( $query ) && str_contains( $query, 'COUNT' ) ? 2 : null;
		};
		$GLOBALS['mock_posts'][11] = new \WP_Post(
			array(
				'ID'           => 11,
				'post_content' => $this->links( 'p11', 8 ),
			)
		);
		$GLOBALS['mock_posts'][12] = new \WP_Post(
			array(
				'ID'           => 12,
				'post_content' => $this->links( 'p12', 8 ),
			)
		);

		$checker = new BrokenLinkChecker();
		update_option( 'quinrel_blc_scan_lock', (string) ( time() - 300 ), false );
		$started = $checker->startScan( new \WP_REST_Request() );
		$this->assertTrue( $started->get_data()['success'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$this->assertCount( 1, $this->jobsFor( BrokenLinkScanJob::class ) );

		$GLOBALS['mock_cron_events'] = array();
		$checker->processBatch();
		$this->assertCount( 10, $GLOBALS['mock_http_log'] );
		$progress = get_option( 'quinrel_blc_progress' );
		$this->assertSame( 'running', $progress['status'] );
		$this->assertSame( 11, $progress['cursor'] );
		$this->assertSame( 2, $progress['link_offset'] );
		$this->assertSame( 1, $progress['scanned'] );
		$this->assertCount( 1, $this->jobsFor( BrokenLinkScanJob::class ) );

		$GLOBALS['mock_http_log']    = array();
		$GLOBALS['mock_cron_events'] = array();
		$checker->processBatch();
		$this->assertCount( 6, $GLOBALS['mock_http_log'] );
		$this->assertSame( 'done', get_option( 'quinrel_blc_progress' )['status'] );
		$this->assertFalse( get_option( 'quinrel_blc_scan_lock', false ) );
		$this->assertSame( array(), $this->jobsFor( BrokenLinkScanJob::class ) );

		$GLOBALS['mock_http_log']   = array();
		$GLOBALS['mock_http_queue'] = array(
			array( 'response' => array( 'code' => 405 ) ),
			array( 'response' => array( 'code' => 200 ) ),
		);
		$updatedCode                = null;
		$GLOBALS['mock_wpdb_update'] = static function ( $table, $data ) use ( &$updatedCode ) {
			$updatedCode = $data['http_code'] ?? null;
			return 1;
		};
		$recheck                    = new \WP_REST_Request();
		$recheck->set_json_params(
			array(
				'id'  => 5,
				'url' => 'https://8.8.8.8/recheck',
			)
		);
		$queued = $checker->recheckLink( $recheck );
		$this->assertTrue( $queued->get_data()['queued'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$this->assertSame( 1, $this->drainJobs() );
		$this->assertCount( 2, $GLOBALS['mock_http_log'] );
		$this->assertSame( 'bytes=0-2048', $GLOBALS['mock_http_log'][1]['args']['headers']['Range'] ?? '' );
		$this->assertSame( 200, $updatedCode );

		$GLOBALS['mock_http_log'] = array();
		$checker->recheckQueued(
			array(
				'id'  => 6,
				'url' => 'https://8.8.8.8:6379/redis-probe',
			)
		);
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		unset( $GLOBALS['mock_http_queue'] );
	}

	public function testOrphanScanPagesThroughTheQueue(): void {
		$seen                     = array();
		$GLOBALS['mock_wp_query'] = static function ( array $args ) use ( &$seen ) {
			$seen[]  = $args;
			$offset  = (int) ( $args['offset'] ?? 0 );
			$perPage = (int) ( $args['posts_per_page'] ?? 0 );
			return array(
				'posts' => array_slice( range( 1, 21 ), $offset, $perPage ),
			);
		};
		$GLOBALS['mock_wpdb_var'] = static fn() => 3;

		$finder = new OrphanedContentFinder();
		$first  = $finder->refreshScan( new \WP_REST_Request() );
		$this->assertTrue( $first->get_data()['queued'] );
		$this->assertFalse( get_option( 'quinrel_orphan_scan_last_run', false ) );
		$this->assertCount( 1, $this->jobsFor( OrphanScanJob::class ) );

		$second = $finder->refreshScan( new \WP_REST_Request() );
		$this->assertTrue( $second->get_data()['queued'] );
		$this->assertCount( 1, $this->jobsFor( OrphanScanJob::class ) );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertSame( 20, $seen[0]['posts_per_page'] );
		$this->assertSame( 0, $seen[0]['offset'] );
		$this->assertArrayNotHasKey( 'suppress_filters', $seen[0] );
		$this->assertTrue( $seen[0]['no_found_rows'] );
		$this->assertTrue( $seen[0]['ignore_sticky_posts'] );
		$this->assertSame( 'ID', $seen[0]['orderby'] );
		$this->assertSame( 'ASC', $seen[0]['order'] );
		$this->assertSame( 3, (int) get_post_meta( 1, '_quinrel_inbound_links', true ) );
		$this->assertSame( '', (string) get_post_meta( 21, '_quinrel_inbound_links', true ) );
		$this->assertFalse( get_option( 'quinrel_orphan_scan_last_run', false ) );
		$next = $this->jobsFor( OrphanScanJob::class );
		$this->assertCount( 1, $next );
		$this->assertSame( 20, $next[0]['args'][1]['offset'] );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertSame( 3, (int) get_post_meta( 21, '_quinrel_inbound_links', true ) );
		$this->assertSame( '2026-10-02 12:00:00', get_option( 'quinrel_orphan_scan_last_run' ) );
		$this->assertSame( array(), $this->jobsFor( OrphanScanJob::class ) );
		$sawLinksTable = false;
		foreach ( $GLOBALS['wpdb']->queries as $query ) {
			if ( ! is_string( $query ) ) {
				continue;
			}
			$this->assertStringNotContainsString( 'post_content LIKE', $query );
			if ( str_contains( $query, 'quinrel_links' ) && str_contains( $query, 'target_url IN' ) ) {
				$sawLinksTable = true;
			}
		}
		$this->assertTrue( $sawLinksTable );
	}

	public function testRankCheckNowEnqueuesSlicesOfFive(): void {
		update_option( 'quinrel_serpapi_key', 'test-key' );
		update_option(
			'quinrel_tracked_keywords',
			array( 'one', 'two', 'three', 'four', 'five', 'six', 'seven' )
		);
		$tracker = new KeywordRankTracker();
		$tracker->register();
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		$request = new \WP_REST_Request();
		$request->set_json_params( array() );
		$queued = $tracker->checkNow( $request );
		$this->assertTrue( $queued->get_data()['queued'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertCount( KeywordRankTracker::KEYWORD_BATCH, $GLOBALS['mock_http_log'] );
		$this->assertCount( 1, $this->jobsFor( \Quinrel\WordPress\Modules\Analytics\RankCheckJob::class ) );

		$this->assertSame( 1, $this->drainJobs() );
		$this->assertCount( 7, $GLOBALS['mock_http_log'] );
		$this->assertSame( array(), $this->jobsFor( \Quinrel\WordPress\Modules\Analytics\RankCheckJob::class ) );

		$GLOBALS['mock_http_log']    = array();
		$GLOBALS['mock_cron_events'] = array();
		$tracker->checkAllKeywords();
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$this->assertCount( 1, $this->jobsFor( \Quinrel\WordPress\Modules\Analytics\RankCheckJob::class ) );
	}

	public function testTechnicalAuditRunsTwoChecksPerJob(): void {
		$engine   = new TechnicalSeoAuditEngine();
		$started  = $engine->scheduleRun( new \WP_REST_Request() );
		$this->assertTrue( $started->get_data()['queued'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		$deltas = array();
		$guard  = 0;
		do {
			$before   = count( $GLOBALS['mock_http_log'] );
			$ran      = $this->drainJobs();
			$deltas[] = count( $GLOBALS['mock_http_log'] ) - $before;
			++$guard;
		} while ( $ran > 0 && $guard < 20 );

		$this->assertLessThan( 20, $guard );
		$this->assertSame( 'done', get_option( 'quinrel_audit_progress' )['status'] );
		$this->assertLessThanOrEqual( TechnicalSeoAuditEngine::CHECK_BATCH, max( $deltas ) );
		$this->assertSame( 2, array_sum( $deltas ) );
		$this->assertCount( 14, get_option( 'quinrel_audit_results' )['checks'] );
		$this->assertSame( array(), $this->jobsFor( \Quinrel\WordPress\Modules\Admin\TechnicalAuditJob::class ) );
	}

	public function testTechnicalAuditRunAllChecksHandlesDuplicateTitlesAndObjects(): void {
		global $wpdb;
		$engine  = new TechnicalSeoAuditEngine();
		$results = $engine->runAllChecks();

		$this->assertCount( 14, $results );
		$this->assertArrayHasKey( 'duplicate_titles', $results );
		$this->assertArrayHasKey( 'redirect_chains', $results );
		$this->assertNotEmpty( $results['duplicate_titles']['message'] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function jobsFor( string $class ): array {
		$events = $GLOBALS['mock_cron_events'] ?? array();
		if ( ! is_array( $events ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$events,
				static fn( $event ) => is_array( $event )
					&& 'quinrel_process_job' === ( $event['hook'] ?? '' )
					&& $class === ( $event['args'][0] ?? '' )
			)
		);
	}

	private function drainJobs(): int {
		$events                      = $GLOBALS['mock_cron_events'] ?? array();
		$GLOBALS['mock_cron_events'] = array();
		if ( ! is_array( $events ) ) {
			return 0;
		}

		$ran = 0;
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) || 'quinrel_process_job' !== ( $event['hook'] ?? '' ) ) {
				continue;
			}
			$class = $event['args'][0] ?? '';
			$data  = $event['args'][1] ?? array();
			if ( ! is_string( $class ) || ! class_exists( $class ) || ! is_array( $data ) ) {
				continue;
			}
			$job = new $class( $data );
			$job->handle();
			++$ran;
		}

		return $ran;
	}

	private function links( string $prefix, int $count ): string {
		$html = '';
		for ( $i = 1; $i <= $count; $i++ ) {
			$html .= '<a href="https://8.8.8.8/' . $prefix . '-' . $i . '">Link</a>';
		}
		return $html;
	}
}
