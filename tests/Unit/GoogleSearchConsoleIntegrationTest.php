<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Analytics\BingWebmasterIntegration;
use Quinrel\WordPress\Modules\Analytics\GoogleSearchConsoleIntegration;
use Quinrel\WordPress\Modules\Analytics\KeywordRankTracker;
use Quinrel\WordPress\Modules\Admin\AdminModule;
use Quinrel\WordPress\Modules\Admin\AdminMenu;
use Quinrel\WordPress\Core\Event\EventManager;

class GoogleSearchConsoleIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( 'quinrel_gsc_config' );
		delete_option( 'quinrel_gsc_token' );
		delete_transient( 'gsc_perf_summary' );
		delete_transient( 'quinrel_gsc_dashboard_v1' );
	}

	protected function tearDown(): void {
		delete_option( 'quinrel_gsc_config' );
		delete_option( 'quinrel_gsc_token' );
		delete_transient( 'gsc_perf_summary' );
		delete_transient( 'quinrel_gsc_dashboard_v1' );
		parent::tearDown();
	}

	public function testMovementComparesSearchConsolePositionsWithoutCallingGoogle(): void {
		$GLOBALS['mock_http_log'] = array();
		$recent                   = array(
			array(
				'keys'        => array( 'alpha' ),
				'position'    => 4.24,
				'clicks'      => 10,
				'impressions' => 100,
			),
			array(
				'keys'        => array( 'new query' ),
				'position'    => 8,
				'clicks'      => 1,
				'impressions' => 20,
			),
			array(
				'query'       => 'beta',
				'position'    => 12.2,
				'clicks'      => 3,
				'impressions' => 50,
			),
			array(
				'query'       => 'gamma',
				'position'    => 3,
				'clicks'      => 1,
				'impressions' => 10,
			),
			array(
				'query'       => '   ',
				'position'    => 1,
				'impressions' => 999,
			),
		);
		$prior                    = array(
			array(
				'keys'        => array( 'alpha' ),
				'position'    => 7.2,
				'clicks'      => 4,
				'impressions' => 40,
			),
			array(
				'query'       => 'beta',
				'position'    => 9,
				'clicks'      => 2,
				'impressions' => 30,
			),
			array(
				'query'       => 'gamma',
				'position'    => 3,
				'clicks'      => 1,
				'impressions' => 8,
			),
		);

		$rows = GoogleSearchConsoleIntegration::movement( $recent, $prior );

		$this->assertCount( 4, $rows );
		$this->assertSame( 'alpha', $rows[0]['query'] );
		$this->assertEqualsWithDelta( 4.2, $rows[0]['position'], 0.001 );
		$this->assertEqualsWithDelta( 7.2, $rows[0]['previous_position'], 0.001 );
		$this->assertEqualsWithDelta( 3.0, $rows[0]['change'], 0.001 );
		$this->assertSame( 'beta', $rows[1]['query'] );
		$this->assertEqualsWithDelta( -3.2, $rows[1]['change'], 0.001 );
		$this->assertSame( 'new query', $rows[2]['query'] );
		$this->assertNull( $rows[2]['previous_position'] );
		$this->assertNull( $rows[2]['change'] );
		$this->assertSame( 'gamma', $rows[3]['query'] );
		$this->assertEqualsWithDelta( 0.0, $rows[3]['change'], 0.001 );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
	}

	public function testMovementKeepsTheQueriesWithTheMostImpressions(): void {
		$recent = array();
		for ( $i = 0; $i < 201; $i++ ) {
			$recent[] = array(
				'query'       => 'q' . $i,
				'position'    => 5,
				'clicks'      => 1,
				'impressions' => $i,
			);
		}

		$rows = GoogleSearchConsoleIntegration::movement( $recent, array() );

		$this->assertCount( 200, $rows );
		$this->assertSame( 'q200', $rows[0]['query'] );
		$this->assertSame( 'q1', $rows[199]['query'] );
	}

	public function testRankTrackerSearchConsoleStaysAvailableWhenDisconnected(): void {
		$GLOBALS['mock_http_log'] = array();

		$response = ( new KeywordRankTracker() )->searchConsoleMovement( new \WP_REST_Request() );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertFalse( $data['connected'] );
		$this->assertSame( array(), $data['keywords'] );
		$this->assertStringContainsString( 'SerpApi key is not required', $data['message'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
	}

	public function testCannibalizationWithoutSearchConsoleIsAnError(): void {
		delete_transient( 'quinrel_cannibalization_v1' );
		$GLOBALS['mock_http_log'] = array();

		$admin  = new AdminModule( new EventManager(), new AdminMenu() );
		$result = $admin->getKeywordCannibalization( new \WP_REST_Request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_connected', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
	}

	public function testUnconfiguredGa4ReportDoesNotCallGoogle(): void {
		delete_option( 'quinrel_ga4_property_id' );
		delete_option( 'quinrel_ga4_credentials' );
		delete_transient( 'quinrel_ga4_report_v1' );
		$GLOBALS['mock_http_log'] = array();

		$admin  = new AdminModule( new EventManager(), new AdminMenu() );
		$result = $admin->getGa4Report( new \WP_REST_Request() );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertSame( 200, $result->get_status() );
		$this->assertFalse( $result->get_data()['success'] );
		$this->assertSame( 'ga4_not_configured', $result->get_data()['code'] );
		$this->assertNotSame( '', $result->get_data()['message'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
	}

	public function testGetAuthUrlWithoutConfigReturnsWpError(): void {
		$gsc = new GoogleSearchConsoleIntegration();
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/gsc/auth-url' );

		$result = $gsc->getAuthUrl( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'no_config', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function testGetAuthUrlWithCredentialsSavesConfigAndReturnsUrl(): void {
		$gsc = new GoogleSearchConsoleIntegration();
		$request = new \WP_REST_Request( 'POST', '/quinrel/v1/gsc/auth-url' );
		$request->set_param( 'client_id', '12345-abc.apps.googleusercontent.com' );
		$request->set_param( 'client_secret', 'secret_xyz_123' );

		$response = $gsc->getAuthUrl( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'auth_url', $data );
		$this->assertStringContainsString( 'accounts.google.com/o/oauth2/v2/auth', $data['auth_url'] );
		$this->assertStringContainsString( '12345-abc.apps.googleusercontent.com', $data['auth_url'] );
		$this->assertStringContainsString( 'https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fwebmasters.readonly', $data['auth_url'] );

		$savedConfig = get_option( 'quinrel_gsc_config' );
		$this->assertSame( '12345-abc.apps.googleusercontent.com', $savedConfig['client_id'] );
		$this->assertStringStartsWith( 'quinrel_enc::', $savedConfig['client_secret'] );
		$this->assertSame( 'secret_xyz_123', \Quinrel\WordPress\Core\Security\KeyVault::decrypt( $savedConfig['client_secret'] ) );
		$this->assertStringNotContainsString( 'secret_xyz_123', $data['auth_url'] );
	}

	public function testSearchConsoleTokensAreEncryptedAndStillValidate(): void {
		$gsc   = new GoogleSearchConsoleIntegration();
		$write = new \ReflectionMethod( GoogleSearchConsoleIntegration::class, 'writeToken' );
		$write->setAccessible( true );
		$this->assertTrue(
			$write->invoke(
				$gsc,
				array(
					'access_token'  => 'mock_ya29_token',
					'refresh_token' => 'mock_refresh',
					'expires_at'    => time() + 3600,
				)
			)
		);

		$stored = get_option( 'quinrel_gsc_token' );
		$this->assertStringStartsWith( 'quinrel_enc::', $stored['access_token'] );
		$this->assertStringStartsWith( 'quinrel_enc::', $stored['refresh_token'] );

		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/gsc/status' );
		$this->assertTrue( $gsc->getStatus( $request )->get_data()['connected'] );
		$this->assertSame( 'mock_ya29_token', $gsc->getValidToken() );
	}

	public function testHandleCallbackWithStateMismatchReturnsError(): void {
		$gsc = new GoogleSearchConsoleIntegration();
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/gsc/callback' );
		$request->set_header( 'X-WP-Nonce', 'rest_test_nonce' );
		$request->set_param( 'state', 'invalid_nonce_state' );
		$request->set_param( 'code', 'sample_code' );

		$result = $gsc->handleCallback( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'oauth_error', $result->get_error_code() );
	}

	public function testGetStatusReportsAccurateConnectionState(): void {
		$gsc = new GoogleSearchConsoleIntegration();
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/gsc/status' );

		$resNotConnected = $gsc->getStatus( $request );
		$data = $resNotConnected->get_data();
		$this->assertFalse( $data['connected'] );

		// Simulate connected token
		update_option( 'quinrel_gsc_token', array(
			'access_token'  => 'mock_ya29_token',
			'refresh_token' => 'mock_refresh',
			'expires_at'    => time() + 3600,
		) );

		$resConnected = $gsc->getStatus( $request );
		$dataConnected = $resConnected->get_data();
		$this->assertTrue( $dataConnected['connected'] );
	}

	public function testDisconnectClearsToken(): void {
		$gsc = new GoogleSearchConsoleIntegration();
		update_option( 'quinrel_gsc_token', array(
			'access_token' => 'mock_ya29_token',
		) );

		$request = new \WP_REST_Request( 'POST', '/quinrel/v1/gsc/disconnect' );
		$res = $gsc->disconnect( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $res );
		$this->assertEmpty( get_option( 'quinrel_gsc_token' ) );
	}

	public function testGetPerformanceWhenNotConnectedReturnsWpError(): void {
		$gsc = new GoogleSearchConsoleIntegration();
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/gsc/performance' );

		$result = $gsc->getPerformance( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_connected', $result->get_error_code() );
	}

	public function testGetDecliningKeywordsWhenNotConnectedReturnsWpError(): void {
		$gsc = new GoogleSearchConsoleIntegration();
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/gsc/declining-keywords' );

		$result = $gsc->getDecliningKeywords( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_connected', $result->get_error_code() );
	}

	public function testAdminModuleSettingsReturnsGscFields(): void {
		update_option( 'quinrel_gsc_config', array(
			'client_id'         => '123-test.apps.googleusercontent.com',
			'client_secret'     => 'supersecret',
			'selected_property' => 'https://example.com/',
		) );
		update_option( 'quinrel_gsc_token', array(
			'access_token' => 'mock_token',
		) );

		$admin = new AdminModule( new EventManager(), new AdminMenu() );
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/settings' );
		$response = $admin->getSettings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$data = $response->get_data();

		$this->assertSame( '123-test.apps.googleusercontent.com', $data['gsc_client_id'] );
		$this->assertSame( '••••••••••••', $data['gsc_client_secret'] );
		$this->assertTrue( $data['gsc_connected'] );
		$this->assertSame( 'https://example.com/', $data['gsc_property'] );
	}

	public function testBingKeyIsMaskedAndEncrypted(): void {
		$admin   = new AdminModule( new EventManager(), new AdminMenu() );
		$request = new \WP_REST_Request( 'POST', '/quinrel/v1/settings' );
		$request->set_json_params( array( 'bing_api_key' => 'bing-secret-key' ) );

		$saved = $admin->updateSettings( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $saved );

		$stored = (string) get_option( 'quinrel_bing_api_key' );
		$this->assertStringStartsWith( 'quinrel_enc::', $stored );

		$settings = $admin->getSettings( new \WP_REST_Request( 'GET', '/quinrel/v1/settings' ) )->get_data();
		$this->assertSame( '••••••••••••', $settings['bing_api_key'] );

		$request->set_json_params( array( 'bing_api_key' => '••••••••••••' ) );
		$admin->updateSettings( $request );
		$this->assertSame( $stored, (string) get_option( 'quinrel_bing_api_key' ) );
		$this->assertSame( 'bing-secret-key', BingWebmasterIntegration::storedKey() );

		delete_option( 'quinrel_bing_api_key' );
	}

	public function testAdminModuleGscDashboardWithoutTokenReturnsWpError(): void {
		$admin = new AdminModule( new EventManager(), new AdminMenu() );
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/gsc/dashboard' );

		$result = $admin->getGscDashboard( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gsc_not_connected', $result->get_error_code() );
	}
}
