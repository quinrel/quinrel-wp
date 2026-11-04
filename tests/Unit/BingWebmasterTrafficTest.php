<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Modules\Analytics\BingWebmasterIntegration;
use PHPUnit\Framework\TestCase;

class BingWebmasterTrafficTest extends TestCase {

	private const KEY = 'bing-secret-key';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_http_log']       = array();
		$GLOBALS['mock_http_responses'] = array();
		$GLOBALS['mock_transients']     = array();
		delete_option( 'quinrel_bing_api_key' );
	}

	public function testTrafficWithoutAKeyDoesNotCallBing(): void {
		$bing   = new BingWebmasterIntegration();
		$result = $bing->getTraffic( new \WP_REST_Request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'bing_unconfigured', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
	}

	public function testTrafficSumsTheNewestWindowsAndCachesTheGet(): void {
		$this->storeKey();
		$forty = $this->datedRows( 40 );
		$this->stubBing( $forty );

		$bing   = new BingWebmasterIntegration();
		$result = $bing->getTraffic( new \WP_REST_Request() );
		$data   = $result->get_data();

		$this->assertSame( 'GET', $GLOBALS['mock_http_log'][0]['method'] );
		$parts = wp_parse_url( $GLOBALS['mock_http_log'][0]['url'] );
		$this->assertSame( 'ssl.bing.com', $parts['host'] );
		$this->assertStringContainsString( 'GetRankAndTrafficStats', (string) $parts['path'] );
		$this->assertSame( $this->sumClicks( 12, 39 ), $data['clicks'] );
		$this->assertSame( $this->sumClicks( 12, 39 ) * 10, $data['impressions'] );
		$this->assertSame( $this->sumClicks( 0, 11 ), $data['previous_clicks'] );
		$this->assertSame( 28, $data['measured_days'] );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $data ) );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $GLOBALS['mock_transients'] ) );

		$GLOBALS['mock_http_log'] = array();
		$again                    = $bing->getTraffic( new \WP_REST_Request() )->get_data();
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$this->assertSame( $data['clicks'], $again['clicks'] );

		delete_transient( 'quinrel_bing_traffic_28_' . md5( home_url( '/' ) ) );
		$GLOBALS['mock_http_log'] = array();
		$this->stubBing( $this->datedRows( 56 ) );
		$wide = $bing->getTraffic( new \WP_REST_Request() )->get_data();
		$this->assertSame( $this->sumClicks( 28, 55 ), $wide['clicks'] );
		$this->assertSame( $this->sumClicks( 0, 27 ), $wide['previous_clicks'] );
		$this->assertSame(
			round( ( ( $this->sumClicks( 28, 55 ) - $this->sumClicks( 0, 27 ) ) / $this->sumClicks( 0, 27 ) ) * 100, 1 ),
			$wide['clicks_change_percent']
		);
	}

	public function testTrafficErrorOmitsTheKeyAndANewKeyDropsTheCache(): void {
		$this->storeKey();
		$this->stubBing( $this->datedRows( 30 ) );
		$bing = new BingWebmasterIntegration();
		$bing->getTraffic( new \WP_REST_Request() );
		$this->assertIsArray( get_transient( 'quinrel_bing_traffic_28_' . md5( home_url( '/' ) ) ) );

		BingWebmasterIntegration::storeKey( 'replacement-key-value' );
		$this->assertFalse( get_transient( 'quinrel_bing_traffic_28_' . md5( home_url( '/' ) ) ) );

		$this->storeKey();
		$GLOBALS['mock_http_log']       = array();
		$GLOBALS['mock_http_responses'] = array(
			'ssl.bing.com' => array(
				'response' => array( 'code' => 400 ),
				'body'     => wp_json_encode( array( 'Message' => 'Unknown site ' . self::KEY ) ),
			),
		);
		$error = $bing->getTraffic( new \WP_REST_Request() );
		$this->assertSame( 'bing_api_error', $error->get_error_code() );
		$this->assertSame( 502, $error->get_error_data()['status'] );
		$this->assertStringNotContainsString( self::KEY, $error->get_error_message() );
		$this->assertStringContainsString( '400', $error->get_error_message() );
	}

	public function testEmptyTrafficTellsTheOwnerWhatToCheck(): void {
		$summary = BingWebmasterIntegration::summarizeTraffic( array() );

		$this->assertSame( 0, $summary['measured_days'] );
		$this->assertSame( 0, $summary['clicks'] );
		$this->assertSame(
			'Bing has no traffic rows for this site yet. Add this site in Bing, or check that its address matches.',
			$summary['message']
		);
	}

	public function testDecayRouteIsGone(): void {
		( new BingWebmasterIntegration() )->registerRoutes();
		$this->assertArrayNotHasKey( 'quinrel/v1/analytics/bing/decay', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/analytics/bing/traffic', $GLOBALS['mock_rest_routes'] );
	}

	private function storeKey(): void {
		$this->assertTrue( BingWebmasterIntegration::storeKey( self::KEY ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function datedRows( int $count ): array {
		$rows = array(
			array(
				'Clicks'      => 999,
				'Impressions' => 999,
				'Date'        => 'not-a-date',
			),
		);
		for ( $i = 0; $i < $count; $i++ ) {
			$rows[] = array(
				'Clicks'      => $i + 1,
				'Impressions' => ( $i + 1 ) * 10,
				'Date'        => '/Date(' . ( 1700000000000 + ( $i * 86400000 ) ) . ')/',
			);
		}
		return $rows;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private function stubBing( array $rows ): void {
		$GLOBALS['mock_http_responses']['ssl.bing.com'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array( 'd' => $rows ) ),
		);
	}

	private function sumClicks( int $start, int $end ): int {
		$sum = 0;
		for ( $i = $start; $i <= $end; $i++ ) {
			$sum += $i + 1;
		}
		return $sum;
	}
}
