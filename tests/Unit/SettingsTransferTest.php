<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Modules\Admin\AdminMenu;
use Quinrel\WordPress\Modules\Admin\AdminModule;
use Quinrel\WordPress\Modules\Admin\SettingsTransfer;
use PHPUnit\Framework\TestCase;

class SettingsTransferTest extends TestCase {

	private AdminModule $admin;

	protected function setUp(): void {
		parent::setUp();
		$this->admin = new AdminModule( new EventManager(), new AdminMenu() );
		foreach ( $this->watchedOptions() as $option ) {
			delete_option( $option );
		}
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mock_user_caps'] );
		$GLOBALS['mock_rest_routes'] = array();
		parent::tearDown();
	}

	public function testExportOmitsSecretsAndKeepsPublicSettings(): void {
		update_option( 'quinrel_google_indexing_key', '{"private_key":"INDEXING-SECRET-VALUE"}' );
		update_option( 'quinrel_indexnow_key', 'indexnow-secret-value' );
		update_option( 'quinrel_openai_key', 'openai-secret-value' );
		update_option( 'quinrel_anthropic_key', 'anthropic-secret-value' );
		update_option( 'quinrel_api_key', 'api-secret-value' );
		update_option( 'quinrel_license_key', 'license-secret-value' );
		update_option( 'quinrel_ga4_credentials', '{"private_key":"GA4-SECRET-VALUE"}' );
		update_option( 'quinrel_encryption_salt', 'salt-secret-value' );
		update_option(
			'quinrel_social_apps',
			array(
				'facebook' => array( 'app_secret' => 'oauth-app-secret-value' ),
			)
		);
		update_option(
			'quinrel_social_accounts',
			array(
				array( 'access_token' => 'oauth-token-value' ),
			)
		);
		update_option( 'quinrel_google_verify', 'google-verify-token' );
		update_option( 'quinrel_rss_before_content', '<p>Hello</p><script>alert(1)</script>' );
		update_option( 'quinrel_breadcrumb_home_label', 'Start' );

		$payload = $this->admin->exportSettings( new \WP_REST_Request() )->get_data()['data'];
		$encoded = (string) wp_json_encode( $payload );

		$this->assertSame( 'google-verify-token', $payload['settings']['quinrel_google_verify'] );
		$this->assertSame( 'Start', $payload['settings']['quinrel_breadcrumb_home_label'] );
		$this->assertStringContainsString( '<p>Hello</p>', $payload['settings']['quinrel_rss_before_content'] );
		$this->assertStringNotContainsString( '<script', $payload['settings']['quinrel_rss_before_content'] );
		$this->assertArrayNotHasKey( 'quinrel_google_indexing_key', $payload['settings'] );
		$this->assertArrayNotHasKey( 'quinrel_indexnow_key', $payload['settings'] );
		$this->assertArrayNotHasKey( 'quinrel_encryption_salt', $payload['settings'] );
		$this->assertContains( 'quinrel_indexnow_key', $payload['_redacted'] );
		$this->assertContains( 'quinrel_encryption_salt', $payload['_redacted'] );
		$this->assertContains( 'quinrel_social_apps', $payload['_redacted'] );

		foreach (
			array(
				'INDEXING-SECRET-VALUE',
				'indexnow-secret-value',
				'openai-secret-value',
				'anthropic-secret-value',
				'api-secret-value',
				'license-secret-value',
				'GA4-SECRET-VALUE',
				'salt-secret-value',
				'oauth-app-secret-value',
				'oauth-token-value',
				'<script',
			) as $secret
		) {
			$this->assertStringNotContainsString( $secret, $encoded );
		}
	}

	public function testImportSanitizesEachAllowedField(): void {
		$result = $this->import(
			array(
				'quinrel_auto_index'                 => true,
				'quinrel_enable_google_indexing_api' => 'no',
				'quinrel_google_verify'              => 'abcDEF_123-token',
				'quinrel_bing_verify'                => '',
				'quinrel_rss_before_content'         => '<p>Read <a href="https://example.com">this</a></p><script>alert(1)</script>',
				'quinrel_rss_after_content'          => '<strong>End</strong>',
				'quinrel_breadcrumb_separator'       => '›',
				'quinrel_breadcrumb_home_label'      => 'Home',
				'quinrel_breadcrumb_auto_insert'     => 'before_content',
				'quinrel_sitemap_exclude_post_types' => 'Post, Page',
				'quinrel_sitemap_exclude_posts'      => '12, 34',
				'quinrel_ai_provider'                => 'Anthropic',
				'quinrel_ollama_url'                 => 'http://localhost:11434/',
				'quinrel_default_share_image'        => 'https://cdn.example.com/share.png',
				'quinrel_ga4_measurement_id'         => 'g-abc12345',
				'quinrel_ga4_property_id'            => '123456',
				'quinrel_html_sitemap_post_types'    => 'post,page',
				'quinrel_html_sitemap_exclude_ids'   => '7',
				'quinrel_html_sitemap_depth'         => '3',
				'quinrel_html_sitemap_order'         => 'title',
				'quinrel_html_sitemap_show_count'    => 'yes',
			)
		);

		$this->assertSame( 21, $result->get_data()['imported'] );
		$this->assertSame( 'yes', get_option( 'quinrel_auto_index' ) );
		$this->assertSame( 'no', get_option( 'quinrel_enable_google_indexing_api' ) );
		$this->assertSame( 'abcDEF_123-token', get_option( 'quinrel_google_verify' ) );
		$this->assertSame( '', get_option( 'quinrel_bing_verify' ) );
		$rss = (string) get_option( 'quinrel_rss_before_content' );
		$this->assertStringContainsString( '<p>Read', $rss );
		$this->assertStringContainsString( 'https://example.com', $rss );
		$this->assertStringNotContainsString( '<script', $rss );
		$this->assertStringNotContainsString( 'alert(1)', $rss );
		$this->assertSame( '<strong>End</strong>', get_option( 'quinrel_rss_after_content' ) );
		$this->assertSame( '›', get_option( 'quinrel_breadcrumb_separator' ) );
		$this->assertSame( 'before_content', get_option( 'quinrel_breadcrumb_auto_insert' ) );
		$this->assertSame( 'post,page', get_option( 'quinrel_sitemap_exclude_post_types' ) );
		$this->assertSame( '12,34', get_option( 'quinrel_sitemap_exclude_posts' ) );
		$this->assertSame( 'anthropic', get_option( 'quinrel_ai_provider' ) );
		$this->assertSame( 'http://localhost:11434', get_option( 'quinrel_ollama_url' ) );
		$this->assertSame( 'https://cdn.example.com/share.png', get_option( 'quinrel_default_share_image' ) );
		$this->assertSame( 'G-ABC12345', get_option( 'quinrel_ga4_measurement_id' ) );
		$this->assertSame( '123456', get_option( 'quinrel_ga4_property_id' ) );
		$this->assertSame( 3, get_option( 'quinrel_html_sitemap_depth' ) );
		$this->assertSame( 'title', get_option( 'quinrel_html_sitemap_order' ) );
	}

	public function testInvalidValueWritesNothing(): void {
		update_option( 'quinrel_breadcrumb_home_label', 'Start' );
		update_option( 'quinrel_default_share_image', 'https://example.com/old.png' );
		update_option( 'quinrel_indexnow_key', 'original-indexnow' );

		$cases = array(
			array( 'quinrel_default_share_image', 'javascript:alert(1)' ),
			array( 'quinrel_google_verify', 'token with spaces' ),
			array( 'quinrel_ga4_measurement_id', 'UA-123' ),
			array( 'quinrel_sitemap_exclude_posts', '12,foo' ),
			array( 'quinrel_sitemap_exclude_post_types', 'post,<script>' ),
			array( 'quinrel_ai_provider', 'gpt' ),
			array( 'quinrel_breadcrumb_auto_insert', 'footer' ),
			array( 'quinrel_html_sitemap_depth', '11' ),
			array( 'quinrel_auto_index', 'maybe' ),
		);

		foreach ( $cases as $case ) {
			$result = $this->import(
				array(
					$case[0]                       => $case[1],
					'quinrel_breadcrumb_home_label' => 'Changed',
				)
			);

			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'invalid_setting', $result->get_error_code() );
			$this->assertSame( 'Start', get_option( 'quinrel_breadcrumb_home_label' ) );
			$this->assertSame( 'https://example.com/old.png', get_option( 'quinrel_default_share_image' ) );
			$this->assertSame( 'original-indexnow', get_option( 'quinrel_indexnow_key' ) );
		}
	}

	public function testUnknownKeyWritesNothingAndKnownSecretsAreIgnored(): void {
		update_option( 'quinrel_breadcrumb_home_label', 'Start' );
		update_option( 'quinrel_encryption_salt', 'salt-must-stay-on-the-site' );
		update_option( 'quinrel_indexnow_key', 'original-indexnow' );
		update_option( 'quinrel_google_indexing_key', 'original-indexing-json' );

		$unknown = $this->import(
			array(
				'quinrel_not_a_setting'         => 'x',
				'quinrel_breadcrumb_home_label' => 'Changed',
				'quinrel_indexnow_key'          => 'replacement-key',
			)
		);
		$this->assertInstanceOf( \WP_Error::class, $unknown );
		$this->assertSame( 'unknown_setting', $unknown->get_error_code() );
		$this->assertSame( 'Start', get_option( 'quinrel_breadcrumb_home_label' ) );
		$this->assertSame( 'original-indexnow', get_option( 'quinrel_indexnow_key' ) );
		$this->assertSame( 'salt-must-stay-on-the-site', get_option( 'quinrel_encryption_salt' ) );

		$secrets = $this->import(
			array(
				'quinrel_indexnow_key'          => 'replacement-key',
				'quinrel_google_indexing_key'   => 'replacement-json',
				'quinrel_encryption_salt'       => 'replacement-salt',
				'quinrel_openai_key'            => 'replacement-openai',
				'quinrel_social_apps'           => array( 'facebook' => array( 'app_secret' => 'new-secret' ) ),
				'quinrel_breadcrumb_home_label' => 'Home',
			)
		);
		$this->assertSame( 1, $secrets->get_data()['imported'] );
		$this->assertSame( 'Home', get_option( 'quinrel_breadcrumb_home_label' ) );
		$this->assertSame( 'original-indexnow', get_option( 'quinrel_indexnow_key' ) );
		$this->assertSame( 'original-indexing-json', get_option( 'quinrel_google_indexing_key' ) );
		$this->assertSame( 'salt-must-stay-on-the-site', get_option( 'quinrel_encryption_salt' ) );
		$this->assertFalse( get_option( 'quinrel_openai_key', false ) );
		$this->assertContains( 'quinrel_indexnow_key', SettingsTransfer::secretOptions() );
	}

	public function testTransferRoutesRequireManageOptions(): void {
		$GLOBALS['mock_rest_routes'] = array();
		$this->admin->registerRestRoutes();
		$routes = $GLOBALS['mock_rest_routes'];

		$export = $routes['quinrel/v1/settings/export']['permission_callback'];
		$import = $routes['quinrel/v1/settings/import']['permission_callback'];
		$settings = $routes['quinrel/v1/settings'][0]['permission_callback'];

		$GLOBALS['mock_user_caps'] = array(
			'manage_options' => true,
			'manage_seo'     => false,
		);
		$this->assertTrue( $export() );
		$this->assertTrue( $import() );
		$this->assertTrue( $settings() );

		$GLOBALS['mock_user_caps'] = array(
			'manage_options' => false,
			'manage_seo'     => true,
		);
		$this->assertFalse( $export() );
		$this->assertFalse( $import() );
		$this->assertTrue( $settings() );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function import( array $settings ): \WP_REST_Response|\WP_Error {
		$request = new \WP_REST_Request();
		$request->set_json_params(
			array(
				'_plugin'  => 'quinrel',
				'settings' => $settings,
			)
		);

		return $this->admin->importSettings( $request );
	}

	/**
	 * @return array<int, string>
	 */
	private function watchedOptions(): array {
		return array(
			'quinrel_auto_index',
			'quinrel_enable_google_indexing_api',
			'quinrel_google_verify',
			'quinrel_bing_verify',
			'quinrel_yandex_verify',
			'quinrel_pinterest_verify',
			'quinrel_rss_before_content',
			'quinrel_rss_after_content',
			'quinrel_breadcrumb_separator',
			'quinrel_breadcrumb_home_label',
			'quinrel_breadcrumb_auto_insert',
			'quinrel_sitemap_exclude_post_types',
			'quinrel_sitemap_exclude_posts',
			'quinrel_ai_provider',
			'quinrel_ollama_url',
			'quinrel_ollama_remote_ack',
			'quinrel_default_share_image',
			'quinrel_ga4_measurement_id',
			'quinrel_ga4_property_id',
			'quinrel_html_sitemap_post_types',
			'quinrel_html_sitemap_exclude_ids',
			'quinrel_html_sitemap_depth',
			'quinrel_html_sitemap_order',
			'quinrel_html_sitemap_show_count',
			'quinrel_google_indexing_key',
			'quinrel_indexnow_key',
			'quinrel_openai_key',
			'quinrel_anthropic_key',
			'quinrel_api_key',
			'quinrel_license_key',
			'quinrel_ga4_credentials',
			'quinrel_encryption_salt',
			'quinrel_social_apps',
			'quinrel_social_accounts',
		);
	}
}
