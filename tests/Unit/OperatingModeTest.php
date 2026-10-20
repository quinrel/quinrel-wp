<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Core\Ai\AiGateway;
use Quinrel\WordPress\Core\Api\BackendApiClient;
use Quinrel\WordPress\Core\Api\CloudCredential;
use Quinrel\WordPress\Core\Api\QuinrelApiGateway;
use Quinrel\WordPress\Core\Mode\OperatingMode;
use Quinrel\WordPress\Core\Security\KeyVault;
use Quinrel\WordPress\Modules\Ai\UsageMeteringManager;
use Quinrel\WordPress\Modules\Analytics\KeywordRankTracker;
use Quinrel\WordPress\Modules\Analytics\PageSpeedDashboard;
use Quinrel\WordPress\Modules\ContentAssistant\ContentGapAnalyser;
use Quinrel\WordPress\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OperatingModeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->clearCloudState();
		$GLOBALS['mock_http_log']       = array();
		$GLOBALS['mock_http_responses'] = array();
		unset( $GLOBALS['mock_http_response'], $GLOBALS['mock_get_posts_args'] );
	}

	protected function tearDown(): void {
		$this->clearCloudState();
		$GLOBALS['mock_is_admin']    = false;
		$GLOBALS['mock_cron_events'] = array();
		unset( $GLOBALS['mock_is_user_logged_in'] );
		$_SERVER['REQUEST_URI'] = '/';
		$this->resetPlugin();
		parent::tearDown();
	}

	public function testAStoredCredentialIsConfiguredUntilItIsRevoked(): void {
		$this->assertFalse( OperatingMode::isCloudConfigured() );
		$this->assertFalse( QuinrelApiGateway::hasActiveLicense() );

		update_option( 'quinrel_license_key', 'lic-plain-value' );
		$this->assertFalse( OperatingMode::isCloudConfigured() );
		$this->assertFalse( QuinrelApiGateway::hasActiveLicense() );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		$this->verifyCredential( 'lic-plain-value' );
		$this->assertTrue( OperatingMode::isCloudConfigured() );
		$this->assertTrue( QuinrelApiGateway::hasActiveLicense() );
		$this->assertSame( 'lic-plain-value', OperatingMode::credential() );

		$encrypted = KeyVault::encrypt( 'api-secret' );
		$this->assertIsString( $encrypted );
		update_option( 'quinrel_api_key', $encrypted );
		$this->verifyCredential( 'api-secret' );
		$this->assertSame( 'api-secret', OperatingMode::credential() );
		$this->assertTrue( OperatingMode::isCloudConfigured() );

		update_option( OperatingMode::REVOKED_OPTION, 'yes' );
		$this->assertFalse( OperatingMode::isCloudConfigured() );
		$this->assertFalse( QuinrelApiGateway::hasActiveLicense() );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
	}

	public function testReachabilityIsAnExplicitRequest(): void {
		$GLOBALS['mock_http_response'] = array(
			'body'     => '{}',
			'response' => array( 'code' => 200 ),
		);

		$this->assertTrue( OperatingMode::isCloudReachable() );
		$this->assertSame( 'GET', $GLOBALS['mock_http_log'][0]['method'] );
		$this->assertSame( 'https://api.quinrel.com/v1/health', $GLOBALS['mock_http_log'][0]['url'] );

		$GLOBALS['mock_http_response'] = array(
			'body'     => '',
			'response' => array( 'code' => 503 ),
		);
		$GLOBALS['mock_http_log'] = array();
		$this->assertFalse( OperatingMode::isCloudReachable() );
	}

	public function testProductionCodeDoesNotCallReachability(): void {
		$src  = dirname( __DIR__, 2 ) . '/src';
		$hits = array();
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $files as $file ) {
			if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}
			if ( $file->getBasename() === 'OperatingMode.php' ) {
				continue;
			}
			$contents = file_get_contents( $file->getPathname() );
			if ( is_string( $contents ) && str_contains( $contents, 'isCloudReachable(' ) ) {
				$hits[] = str_replace( dirname( __DIR__, 2 ) . '/', '', $file->getPathname() );
			}
		}

		$this->assertSame( array(), $hits );
	}

	public function testAnonymousFrontEndWithEmptyCloudOptionsMakesNoRemoteCall(): void {
		$this->clearCloudState();
		delete_option( 'quinrel_anthropic_key' );
		delete_option( 'quinrel_google_indexing_key' );
		delete_option( 'quinrel_bing_api_key' );
		delete_option( 'quinrel_gsc_token' );
		delete_option( 'quinrel_indexnow_key' );
		update_option( 'quinrel_db_version', QUINREL_VERSION );

		$this->bootAt( '/hello', false, false );

		$this->assertContains( 'wp_head', $this->hookNames() );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
	}

	public function testANormalPageViewDoesNotCallQuinrel(): void {
		update_option( 'quinrel_db_version', QUINREL_VERSION );
		update_option( 'quinrel_license_key', 'lic-plain-value' );
		$this->verifyCredential( 'lic-plain-value' );
		$GLOBALS['mock_http_log'] = array();

		$this->bootAt( '/hello', false, false );

		$this->assertContains( 'wp_head', $this->hookNames() );
		$this->assertContains( 'robots_txt', $this->hookNames() );
		$this->assertContains( 'template_redirect', $this->hookNames() );
		$this->assertSame( array(), $this->quinrelRequests() );
	}

	public function testCloudCallsFailClosedWhenTheSiteIsNotConnected(): void {
		$client = new BackendApiClient();

		$generated = $client->generateAi( array( 'prompt' => 'test' ) );
		$this->assertSame( 'quinrel_not_connected', $generated->get_error_code() );

		$gap = $client->analyzeContentGap( 'widgets', array( 'existing' ) );
		$this->assertSame( 'quinrel_not_connected', $gap->get_error_code() );

		$ranks = $client->trackRanks( array( 'widgets' ) );
		$this->assertSame( 'quinrel_not_connected', $ranks->get_error_code() );
		$this->assertStringContainsString( 'not connected to Quinrel', $ranks->get_error_message() );

		$posted = $client->post( '/ai/generate', array() );
		$this->assertSame( 'quinrel_not_connected', $posted->get_error_code() );

		$proxy = QuinrelApiGateway::proxyRequest( 'pagespeed', array( 'url' => 'https://example.com/' ) );
		$this->assertSame( 'quinrel_not_connected', $proxy->get_error_code() );

		$usage = UsageMeteringManager::getInstance()->reportToCloud();
		$this->assertSame( 'quinrel_not_connected', $usage->get_error_code() );

		$request = new \WP_REST_Request();
		$request->set_param( 'url', 'https://example.com/' );
		$request->set_param( 'strategy', 'mobile' );
		$pageSpeed = ( new PageSpeedDashboard() )->getPageSpeed( $request );
		$this->assertSame( 'quinrel_not_connected', $pageSpeed->get_error_code() );

		$bulk = new \WP_REST_Request();
		$bulk->set_json_params( array( 'urls' => array( 'https://example.com/' ) ) );
		$bulkResult = ( new PageSpeedDashboard() )->bulkCheck( $bulk );
		$this->assertSame( 'quinrel_not_connected', $bulkResult->get_error_code() );

		update_option( 'quinrel_tracked_keywords', array( 'widgets' ) );
		delete_option( 'quinrel_serpapi_key' );
		$rankRequest = new \WP_REST_Request();
		$rankRequest->set_json_params( array() );
		$rank = ( new KeywordRankTracker() )->checkNow( $rankRequest );
		$this->assertSame( 'quinrel_not_connected', $rank->get_error_code() );

		$gapRequest = new \WP_REST_Request();
		$gapRequest->set_json_params( array( 'keyword' => 'widgets' ) );
		$contentGap = ( new ContentGapAnalyser() )->analyseGap( $gapRequest );
		$this->assertSame( 'quinrel_not_connected', $contentGap->get_error_code() );
		$this->assertArrayNotHasKey( 'mock_get_posts_args', $GLOBALS );

		update_option( 'quinrel_ai_provider', 'not-a-provider' );
		$model = ( new AiGateway() )->queryModel( 'Write a title' );
		$this->assertFalse( $model['success'] );

		$this->assertSame( array(), $this->quinrelRequests() );
	}

	public function testARevokedCredentialDoesNotProxyAndALocalKeyStillCan(): void {
		update_option( 'quinrel_license_key', 'lic-plain-value' );
		$this->verifyCredential( 'lic-plain-value' );
		update_option( OperatingMode::REVOKED_OPTION, 'yes' );

		$proxy = QuinrelApiGateway::proxyRequest( 'pagespeed', array() );
		$this->assertSame( 'quinrel_not_connected', $proxy->get_error_code() );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		delete_option( OperatingMode::REVOKED_OPTION );
		$GLOBALS['mock_http_response'] = array(
			'body'     => '{"ok":true}',
			'response' => array( 'code' => 200 ),
		);
		$proxied = QuinrelApiGateway::proxyRequest( 'pagespeed', array( 'url' => 'https://example.com/' ) );
		$this->assertIsArray( $proxied );
		$this->assertSame( 'Bearer lic-plain-value', $GLOBALS['mock_http_log'][0]['args']['headers']['Authorization'] );

		update_option( 'quinrel_serpapi_key', 'serp-key' );
		update_option( 'quinrel_tracked_keywords', array( 'widgets' ) );
		$GLOBALS['mock_http_log'] = array();
		$queued = ( new KeywordRankTracker() )->checkNow( new \WP_REST_Request() );
		$this->assertInstanceOf( \WP_REST_Response::class, $queued );
		$this->assertTrue( $queued->get_data()['queued'] );
		$this->assertSame( array(), $this->quinrelRequests() );
	}

	private function clearCloudState(): void {
		delete_option( 'quinrel_api_key' );
		delete_option( 'quinrel_license_key' );
		delete_option( CloudCredential::VERIFICATION_OPTION );
		delete_option( 'quinrel_serpapi_key' );
		delete_option( 'quinrel_pagespeed_api_key' );
		delete_option( 'quinrel_openai_key' );
		delete_option( 'quinrel_openai_api_key' );
		delete_option( OperatingMode::REVOKED_OPTION );
	}

	/**
	 * @return array<int, string>
	 */
	private function verifyCredential( string $token ): void {
		update_option(
			CloudCredential::VERIFICATION_OPTION,
			array(
				'token_hash' => hash( 'sha256', $token ),
				'expires_at' => time() + HOUR_IN_SECONDS,
				'tier'       => 'free',
			),
			false
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function quinrelRequests(): array {
		$urls = array();
		foreach ( $GLOBALS['mock_http_log'] as $entry ) {
			$url = (string) ( $entry['url'] ?? '' );
			if ( str_contains( $url, 'api.quinrel.com' ) ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	private function bootAt( string $path, bool $admin, bool $loggedIn ): void {
		$this->resetPlugin();
		$_SERVER['REQUEST_URI']            = $path;
		$GLOBALS['mock_is_admin']          = $admin;
		$GLOBALS['mock_is_user_logged_in'] = $loggedIn;
		$GLOBALS['mock_hooks']             = array();
		$GLOBALS['mock_http_log']          = array();
		Plugin::getInstance()->boot();
	}

	private function resetPlugin(): void {
		$instance = ( new ReflectionClass( Plugin::class ) )->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * @return array<int, string>
	 */
	private function hookNames(): array {
		$names = array();
		foreach ( $GLOBALS['mock_hooks'] as $hook ) {
			$names[] = $hook[1];
		}
		return $names;
	}
}
