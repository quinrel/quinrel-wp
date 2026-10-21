<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BootstrapSplitTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		update_option( 'quinrel_db_version', QUINREL_VERSION );
		unset(
			$GLOBALS['mock_wp_options']['quinrel_filename_enforce'],
			$GLOBALS['mock_wp_options']['quinrel_cookie_banner'],
			$GLOBALS['mock_wp_options']['quinrel_htaccess_web_backups_retired'],
			$GLOBALS['mock_wp_options']['quinrel_enable_index_sitemap']
		);
		unset( $GLOBALS['mock_is_user_logged_in'], $_GET['rest_route'] );
		$GLOBALS['mock_is_admin'] = false;
		$GLOBALS['mock_http_log'] = array();
		$_SERVER['REQUEST_URI']   = '/hello';
		$this->resetPlugin();
	}

	protected function tearDown(): void {
		$GLOBALS['mock_is_admin'] = false;
		unset( $GLOBALS['mock_is_user_logged_in'], $_GET['rest_route'] );
		$_SERVER['REQUEST_URI'] = '/';
		$this->resetPlugin();
		parent::tearDown();
	}

	public function testAnonymousHtmlBootsPublicSeoOnly(): void {
		$plugin = $this->bootAt( '/hello', false, false );

		$this->assertContains( 'wp_head', $this->hookNames() );
		$this->assertContains( 'robots_txt', $this->hookNames() );
		$this->assertContains( 'template_redirect', $this->hookNames() );
		$this->assertContains( 'wp_handle_upload_prefilter', $this->hookNames() );
		$this->assertNotContains( 'admin_menu', $this->hookNames() );
		$this->assertNotContains( 'admin_enqueue_scripts', $this->hookNames() );
		$this->assertNotContains( 'admin_notices', $this->hookNames() );
		$this->assertNotContains( 'enqueue_block_editor_assets', $this->hookNames() );
		$this->assertNotContains( 'quinrel_process_job', $this->hookNames() );
		$this->assertNotContains( 'transition_post_status', $this->hookNames() );
		$this->assertNotContains( 'quinrel_flush_404_buffer', $this->hookNames() );
		$this->assertNotContains( 'quinrel_404_cleanup', $this->hookNames() );
		$this->assertNotContains( 'quinrel_apply_retention', $this->hookNames() );
		$this->assertNotContains( 'quinrel_weekly_usage_email', $this->hookNames() );
		$this->assertNotContains( 'quinrel_indexnow_flush', $this->hookNames() );
		$this->assertNotContains( 'quinrel_rank_check', $this->hookNames() );
		$this->assertNotContains( 'quinrel_blc_scan_batch', $this->hookNames() );
		$this->assertNotContains( 'quinrel_scan_orphaned', $this->hookNames() );
		$this->assertNotContains( 'quinrel_run_technical_audit', $this->hookNames() );
		$this->assertNotContains( 'wp_enqueue_scripts', $this->hookNames() );
		$this->assertSame( array(), $GLOBALS['mock_rest_routes'] );

		$this->assertTrue( $this->hasService( $plugin, 'seo_module' ) );
		$this->assertTrue( $this->hasService( $plugin, 'schema_module' ) );
		$this->assertTrue( $this->hasService( $plugin, 'redirect_manager' ) );
		$this->assertTrue( $this->hasService( $plugin, 'error_monitor' ) );
		$this->assertTrue( $this->hasService( $plugin, 'robots_txt_editor' ) );
		$this->assertTrue( $this->hasService( $plugin, 'breadcrumb_renderer' ) );
		$this->assertTrue( $this->hasService( $plugin, 'image_filename_enforcer' ) );
		$this->assertFalse( $this->hasService( $plugin, 'frontend_seo_inspector' ) );
		$this->assertFalse( $this->hasService( $plugin, 'admin_module' ) );
		$this->assertFalse( $this->hasService( $plugin, 'queue_manager' ) );
		$this->assertFalse( $this->hasService( $plugin, 'sitemap_generator' ) );
		$this->assertFalse( $this->hasService( $plugin, 'sitemap_module' ) );
		$this->assertFalse( $this->hasService( $plugin, 'llms_txt_generator' ) );
		$this->assertFalse( $this->hasService( $plugin, 'bing_indexnow_api' ) );
		$this->assertFalse( $this->hasService( $plugin, 'pagespeed_dashboard' ) );
		$this->assertFalse( $this->hasService( $plugin, 'google_search_console' ) );
		$this->assertFalse( $this->hasService( $plugin, 'bing_webmaster' ) );
		$this->assertFalse( $this->hasService( $plugin, 'keyword_rank_tracker' ) );
		$this->assertFalse( $this->hasService( $plugin, 'llm_writing_assistant' ) );
		$this->assertFalse( $this->hasService( $plugin, 'content_assistant_module' ) );
		$this->assertFalse( $this->hasService( $plugin, 'social_module' ) );
		$this->assertFalse( $this->hasService( $plugin, 'backend_api_client' ) );
		$this->assertFalse( $this->hasService( $plugin, 'cookie_banner_module' ) );
		$this->assertFalse( $this->hasService( $plugin, 'htaccess_editor' ) );
	}

	public function testLoggedInHtmlConstructsInspector(): void {
		$plugin = $this->bootAt( '/hello', false, true );

		$this->assertTrue( $this->hasService( $plugin, 'frontend_seo_inspector' ) );
		$this->assertContains( 'wp_footer', $this->hookNames() );
		$this->assertNotContains( 'admin_menu', $this->hookNames() );
	}

	public function testFilenameFilterStaysOffWhenDisabled(): void {
		update_option( 'quinrel_filename_enforce', 'no' );
		$this->bootAt( '/hello', false, false );

		$this->assertNotContains( 'wp_handle_upload_prefilter', $this->hookNames() );
	}

	public function testCookieBannerBootsOnlyWhenEnabled(): void {
		update_option( 'quinrel_cookie_banner', array( 'enabled' => true ) );
		$plugin = $this->bootAt( '/hello', false, false );

		$this->assertTrue( $this->hasService( $plugin, 'cookie_banner_module' ) );
		$this->assertContains( 'wp_enqueue_scripts', $this->hookNames() );
	}

	public function testAdminBootKeepsMenusQueueAndPublishHooks(): void {
		update_option( 'quinrel_caps_version', QUINREL_VERSION );
		$this->bootAt( '/wp-admin/index.php', true, true );

		$this->assertContains( 'admin_menu', $this->hookNames() );
		$this->assertContains( 'admin_enqueue_scripts', $this->hookNames() );
		$this->assertContains( 'admin_notices', $this->hookNames() );
		$this->assertContains( 'transition_post_status', $this->hookNames() );
		$this->assertContains( 'quinrel_process_job', $this->hookNames() );
		$this->assertContains( 'enqueue_block_editor_assets', $this->hookNames() );
		$this->assertContains( 'quinrel_indexnow_flush', $this->hookNames() );
		$this->assertContains( 'quinrel_rank_check', $this->hookNames() );
		$this->assertContains( 'quinrel_blc_scan_batch', $this->hookNames() );
		$this->assertContains( 'quinrel_scan_orphaned', $this->hookNames() );
		$this->assertContains( 'quinrel_run_technical_audit', $this->hookNames() );
		$this->assertContains( 'quinrel_404_cleanup', $this->hookNames() );
	}

	public function testRestRequestUsesOperationalBoot(): void {
		$this->bootAt( '/wp-json/quinrel/v1/settings', false, false );

		$this->assertContains( 'rest_api_init', $this->hookNames() );
		$this->assertContains( 'transition_post_status', $this->hookNames() );
		$this->assertContains( 'quinrel_process_job', $this->hookNames() );
		$this->assertNotContains( 'admin_menu', $this->hookNames() );
		$this->assertNotContains( 'admin_enqueue_scripts', $this->hookNames() );
		$this->assertSame( array(), $GLOBALS['mock_rest_routes'] );
	}

	public function testRestRouteQueryIsNotHtml(): void {
		$_GET['rest_route'] = '/quinrel/v1/ai/usage';
		$this->bootAt( '/index.php', false, false );

		$this->assertContains( 'transition_post_status', $this->hookNames() );
		$this->assertNotContains( 'admin_menu', $this->hookNames() );
	}

	public function testDeferredRestRegistrationDoesNotCallHttp(): void {
		$plugin = $this->bootAt( '/hello', false, false );
		$this->assertSame( array(), $GLOBALS['mock_rest_routes'] );

		$plugin->registerRestControllers();

		$this->assertArrayHasKey( 'quinrel/v1/settings', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/pagespeed', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/analytics/pagespeed', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/ai/usage', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/robots-txt', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/gsc/status', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/inspector/audit', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/seo/global', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/schemamap', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/writing/suggest-meta', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/rank-tracker/keywords', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/analytics/bing/settings', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/migration/detect', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/bulk-meta', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayHasKey( 'quinrel/v1/roles/capabilities', $GLOBALS['mock_rest_routes'] );
		$this->assertArrayNotHasKey( 'quinrel/v1/schema/product', $GLOBALS['mock_rest_routes'] );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );
		$this->assertFalse( $this->hasService( $plugin, 'backend_api_client' ) );
	}

	public function testSitemapAndLlmsPathsDoNotConstructGenerators(): void {
		$plain = $this->bootAt( '/hello', false, false );
		$plainRedirects = $this->hookCount( 'template_redirect' );
		$plainInits     = $this->hookCount( 'init' );
		$this->assertFalse( $this->hasService( $plain, 'sitemap_generator' ) );
		$this->assertFalse( $this->hasService( $plain, 'llms_txt_generator' ) );
		$this->assertFalse( $this->hasService( $plain, 'bing_indexnow_api' ) );

		$sitemap = $this->bootAt( '/sitemap.xml', false, false );
		$this->assertGreaterThan( $plainRedirects, $this->hookCount( 'template_redirect' ) );
		$this->assertFalse( $this->hasService( $sitemap, 'sitemap_generator' ) );
		$this->assertFalse( $this->hasService( $sitemap, 'llms_txt_generator' ) );

		$posts = $this->bootAt( '/sitemap-posts-2024-05.xml', false, false );
		$this->assertGreaterThan( $plainRedirects, $this->hookCount( 'template_redirect' ) );
		$this->assertFalse( $this->hasService( $posts, 'sitemap_generator' ) );

		$paged = $this->bootAt( '/sitemap-posts-2024-05-2.xml', false, false );
		$this->assertGreaterThan( $plainRedirects, $this->hookCount( 'template_redirect' ) );
		$this->assertFalse( $this->hasService( $paged, 'sitemap_generator' ) );

		$notAPage = $this->bootAt( '/sitemap-posts-2024-05-1.xml', false, false );
		$this->assertSame( $plainRedirects, $this->hookCount( 'template_redirect' ) );

		$llms = $this->bootAt( '/llms.txt', false, false );
		$this->assertGreaterThan( $plainRedirects, $this->hookCount( 'template_redirect' ) );
		$this->assertSame( $plainInits, $this->hookCount( 'init' ) );
		$this->assertFalse( $this->hasService( $llms, 'llms_txt_generator' ) );
		$this->assertFalse( $this->hasService( $llms, 'bing_indexnow_api' ) );

		$llmsFull = $this->bootAt( '/llms-full.txt', false, false );
		$this->assertGreaterThan( $plainRedirects, $this->hookCount( 'template_redirect' ) );
		$this->assertSame( $plainInits, $this->hookCount( 'init' ) );
		$this->assertFalse( $this->hasService( $llmsFull, 'llms_txt_generator' ) );
		$this->assertFalse( $this->hasService( $llmsFull, 'bing_indexnow_api' ) );

		$robots = $this->bootAt( '/robots.txt', false, false );
		$this->assertSame( $plainInits, $this->hookCount( 'init' ) );
		$this->assertFalse( $this->hasService( $robots, 'bing_indexnow_api' ) );

		$key = $this->bootAt( '/site-key.txt', false, false );
		$this->assertGreaterThan( $plainInits, $this->hookCount( 'init' ) );
		$this->assertFalse( $this->hasService( $key, 'bing_indexnow_api' ) );
	}

	public function testCoreSitemapFilterDoesNotConstructSitemapModule(): void {
		$plugin = $this->bootAt( '/hello', false, false );
		$this->assertTrue( apply_filters( 'wp_sitemaps_enabled', true ) );
		$this->assertFalse( $this->hasService( $plugin, 'sitemap_module' ) );

		update_option( 'quinrel_enable_index_sitemap', 'yes' );
		$this->assertFalse( apply_filters( 'wp_sitemaps_enabled', true ) );
		$this->assertFalse( $this->hasService( $plugin, 'sitemap_module' ) );
	}

	public function testRetiredHtaccessFlagSkipsEditor(): void {
		update_option( 'quinrel_htaccess_web_backups_retired', 'yes' );
		$plugin = $this->bootAt( '/hello', false, false );

		$this->assertContains( 'init', $this->hookNames() );
		$plugin->retireHtaccessBackupsOnce();
		$this->assertFalse( $this->hasService( $plugin, 'htaccess_editor' ) );
	}

	public function testSecondBootAddsNoHooks(): void {
		$this->bootAt( '/hello', false, false );
		$once = $GLOBALS['mock_hooks'];

		Plugin::getInstance()->boot();

		$this->assertSame( $once, $GLOBALS['mock_hooks'] );
		$this->assertGreaterThan( 10, count( $once ) );
	}

	private function bootAt( string $path, bool $admin, bool $loggedIn ): Plugin {
		$this->resetPlugin();
		$_SERVER['REQUEST_URI']           = $path;
		$GLOBALS['mock_is_admin']         = $admin;
		$GLOBALS['mock_is_user_logged_in'] = $loggedIn;
		$GLOBALS['mock_hooks']            = array();
		$GLOBALS['mock_rest_routes']      = array();
		$GLOBALS['mock_http_log']         = array();
		$GLOBALS['mock_filter_callbacks'] = array();

		$plugin = Plugin::getInstance();
		$plugin->boot();
		return $plugin;
	}

	private function resetPlugin(): void {
		$instance = ( new ReflectionClass( Plugin::class ) )->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$GLOBALS['mock_hooks'] = array();
	}

	private function hookNames(): array {
		$names = array();
		foreach ( $GLOBALS['mock_hooks'] as $hook ) {
			$names[] = $hook[1];
		}
		return $names;
	}

	private function hookCount( string $name ): int {
		return count(
			array_filter(
				$this->hookNames(),
				static function ( $hook ) use ( $name ) {
					return $hook === $name;
				}
			)
		);
	}

	private function hasService( Plugin $plugin, string $key ): bool {
		$property = ( new ReflectionClass( $plugin->getContainer() ) )->getProperty( 'instances' );
		$property->setAccessible( true );
		$instances = $property->getValue( $plugin->getContainer() );
		return isset( $instances[ $key ] );
	}
}
