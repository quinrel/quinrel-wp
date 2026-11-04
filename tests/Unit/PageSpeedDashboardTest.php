<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Core\Security\KeyVault;
use Quinrel\WordPress\Modules\Admin\AdminMenu;
use Quinrel\WordPress\Modules\Admin\AdminModule;
use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Modules\Analytics\PageSpeedDashboard;

class PageSpeedDashboardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( 'quinrel_pagespeed_api_key' );
		delete_option( 'quinrel_psi_key' );
		delete_option( 'quinrel_pagespeed_reports' );
		$GLOBALS['mock_http_log'] = array();
		unset( $GLOBALS['mock_http_response'] );
	}

	protected function tearDown(): void {
		delete_option( 'quinrel_pagespeed_api_key' );
		delete_option( 'quinrel_psi_key' );
		delete_option( 'quinrel_pagespeed_reports' );
		delete_post_meta( 77, '_quinrel_pagespeed_cache' );
		unset( $GLOBALS['mock_http_response'] );
		parent::tearDown();
	}

	public function testACheckUsesTheLighthousePayloadAndSkipsALocalAddress(): void {
		$dashboard = new PageSpeedDashboard();
		$local     = new \WP_REST_Request();
		$local->set_param( 'url', 'http://localhost/holvek-uat/' );
		$local->set_param( 'strategy', 'mobile' );
		$local->set_param( 'force', '1' );

		$blocked = $dashboard->getPageSpeed( $local );

		$this->assertSame( 'psi_local_url', $blocked->get_error_code() );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		$quiet = new \WP_REST_Request();
		$quiet->set_param( 'url', 'https://wordpress.org/' );
		$quiet->set_param( 'strategy', 'mobile' );
		$this->assertSame( 'empty', $dashboard->getPageSpeed( $quiet )->get_data()['status'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		KeyVault::ensureSaltExists();
		$this->assertTrue( PageSpeedDashboard::storeKey( 'psi-test-key' ) );
		$this->assertStringStartsWith( 'quinrel_enc::', (string) get_option( 'quinrel_pagespeed_api_key' ) );
		$this->assertTrue( PageSpeedDashboard::storeKey( '••••••••••••' ) );
		$this->assertSame( 'psi-test-key', PageSpeedDashboard::storedKey() );

		$GLOBALS['mock_http_response'] = array(
			'body'     => $this->lighthouseBody(),
			'response' => array( 'code' => 200 ),
		);
		$check = new \WP_REST_Request();
		$check->set_param( 'url', 'https://wordpress.org/' );
		$check->set_param( 'strategy', 'mobile' );
		$check->set_param( 'force', '1' );
		$first = $dashboard->getPageSpeed( $check );
		$this->assertInstanceOf( \WP_REST_Response::class, $first );
		$data = $first->get_data();

		$this->assertSame( 'ready', $data['status'] );
		$this->assertSame( 42, $data['scores']['performance'] );
		$this->assertSame( 91, $data['scores']['accessibility'] );
		$this->assertSame( 80, $data['scores']['best_practices'] );
		$this->assertSame( 100, $data['scores']['seo'] );
		$this->assertSame( 'field', $data['metrics'][0]['origin'] );
		$this->assertSame( 2100.0, $data['metrics'][0]['numeric'] );
		$this->assertSame( 'good', $data['metrics'][0]['rating'] );
		$this->assertSame( 'field', $data['metrics'][2]['origin'] );
		$this->assertSame( 0.04, $data['metrics'][2]['numeric'] );
		$this->assertSame( 'lab', $data['metrics'][3]['origin'] );
		$this->assertSame( 'needs-improvement', $data['metrics'][3]['rating'] );
		$this->assertSame( 'Reduce unused JavaScript', $data['opportunities'][0]['title'] );
		$this->assertSame( 1734, $data['opportunities'][0]['savings_ms'] );
		$this->assertCount( 2, $data['opportunities'] );
		$this->assertSame( array( 2100.0 ), $data['series']['lcp'] );

		$sent = $GLOBALS['mock_http_log'][0]['url'];
		$this->assertStringContainsString( 'url=https%3A%2F%2Fwordpress.org%2F', $sent );
		$this->assertStringNotContainsString( '%253A', $sent );
		$this->assertStringContainsString( 'category=performance', $sent );
		$this->assertStringContainsString( 'category=best-practices', $sent );
		$this->assertStringContainsString( 'key=psi-test-key', $sent );
		$this->assertStringNotContainsString( 'category%5B0%5D', $sent );

		$second = $dashboard->getPageSpeed( $check );
		$this->assertSame( array( 2100.0, 2100.0 ), $second->get_data()['series']['lcp'] );

		$again = $dashboard->getPageSpeed( $quiet );
		$this->assertSame( 42, $again->get_data()['scores']['performance'] );
		$this->assertCount( 2, $GLOBALS['mock_http_log'] );
	}

	public function testTheEditorUsesTheSameAuditAndDoesNotInventAScore(): void {
		$GLOBALS['mock_http_response'] = array(
			'body'     => $this->lighthouseBody(),
			'response' => array( 'code' => 200 ),
		);
		$admin   = new AdminModule( new EventManager(), new AdminMenu() );
		$request = new \WP_REST_Request();
		$request->set_param( 'post_id', 77 );

		$result = $admin->getPageSpeed( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertSame( 42, $result->get_data()['scores']['mobile'] );
		$this->assertSame( 42, $result->get_data()['scores']['desktop'] );
		$this->assertStringContainsString( 'googleapis.com', $GLOBALS['mock_http_log'][0]['url'] );

		$cached = $admin->getPageSpeed( $request );
		$this->assertTrue( $cached->get_data()['cached'] );
		$this->assertCount( 2, $GLOBALS['mock_http_log'] );
	}

	public function testSettingsMaskThePageSpeedKey(): void {
		KeyVault::ensureSaltExists();
		$admin = new AdminModule( new EventManager(), new AdminMenu() );
		$save  = new \WP_REST_Request();
		$save->set_json_params( array( 'pagespeed_api_key' => 'psi-test-key' ) );
		$this->assertInstanceOf( \WP_REST_Response::class, $admin->updateSettings( $save ) );

		$settings = $admin->getSettings( new \WP_REST_Request() )->get_data();
		$this->assertSame( '••••••••••••', $settings['pagespeed_api_key'] );
		$this->assertStringStartsWith( 'quinrel_enc::', (string) get_option( 'quinrel_pagespeed_api_key' ) );

		$mask = new \WP_REST_Request();
		$mask->set_json_params( array( 'pagespeed_api_key' => '••••••••••••' ) );
		$admin->updateSettings( $mask );
		$this->assertSame( 'psi-test-key', PageSpeedDashboard::storedKey() );

		$clear = new \WP_REST_Request();
		$clear->set_json_params( array( 'pagespeed_api_key' => '' ) );
		$admin->updateSettings( $clear );
		$this->assertSame( '', PageSpeedDashboard::storedKey() );
	}

	private function lighthouseBody(): string {
		return (string) wp_json_encode(
			array(
				'loadingExperience' => array(
					'metrics' => array(
						'LARGEST_CONTENTFUL_PAINT_MS'   => array(
							'percentile' => 2100,
							'category'   => 'FAST',
						),
						'INTERACTION_TO_NEXT_PAINT'      => array(
							'percentile' => 180,
							'category'   => 'FAST',
						),
						'CUMULATIVE_LAYOUT_SHIFT_SCORE'  => array(
							'percentile' => 4,
							'category'   => 'FAST',
						),
					),
				),
				'lighthouseResult'  => array(
					'categories' => array(
						'performance'     => array( 'score' => 0.42 ),
						'accessibility'   => array( 'score' => 0.91 ),
						'best-practices'  => array( 'score' => 0.8 ),
						'seo'             => array( 'score' => 1 ),
					),
					'audits'     => array(
						'largest-contentful-paint'  => array(
							'numericValue' => 3200,
							'displayValue' => '3.2 s',
						),
						'cumulative-layout-shift'   => array(
							'numericValue' => 0.18,
							'displayValue' => '0.18',
						),
						'server-response-time'      => array(
							'numericValue' => 900,
							'displayValue' => '0.9 s',
						),
						'unused-javascript'         => array(
							'score'   => 0,
							'title'   => 'Reduce unused JavaScript',
							'details' => array(
								'type'             => 'opportunity',
								'overallSavingsMs' => 1734,
							),
						),
						'modern-image-formats'      => array(
							'score'   => 0.5,
							'title'   => 'Serve images in next-gen formats',
							'details' => array(
								'type'                => 'opportunity',
								'overallSavingsBytes' => 12000,
							),
						),
						'offscreen-images'          => array(
							'title'   => 'No saving',
							'details' => array( 'type' => 'opportunity' ),
						),
					),
				),
			)
		);
	}
}
