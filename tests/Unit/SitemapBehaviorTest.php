<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Modules\Schema\VideoExtractor;
use Quinrel\WordPress\Modules\Sitemap\NewsSitemapGenerator;
use Quinrel\WordPress\Modules\Sitemap\SitemapGenerator;
use Quinrel\WordPress\Modules\Sitemap\SitemapModule;
use Quinrel\WordPress\Modules\Sitemap\SitemapRouteManager;
use Quinrel\WordPress\Modules\Sitemap\SitemapSettings;
use Quinrel\WordPress\Modules\Sitemap\VideoSitemapGenerator;
use Quinrel\WordPress\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SitemapBehaviorTest extends TestCase {

	private array $options;

	private $wpdbResults;

	private $wpdbVar;

	private $getPosts;

	protected function setUp(): void {
		parent::setUp();
		$this->options     = $GLOBALS['mock_wp_options'];
		$this->wpdbResults = $GLOBALS['mock_wpdb_results'] ?? null;
		$this->wpdbVar     = $GLOBALS['mock_wpdb_var'] ?? null;
		$this->getPosts    = $GLOBALS['mock_get_posts'] ?? null;
		$GLOBALS['mock_wpdb_var'] = static function () {
			return null;
		};
		$GLOBALS['mock_post_types']  = array( 'post', 'page' );
		$GLOBALS['mock_flush_count'] = 0;
		$GLOBALS['mock_rewrite_rules'] = array();
		$GLOBALS['wp_rewrite']         = new \stdClass();
		$GLOBALS['wp_rewrite']->extra_rules_top = array();
		$GLOBALS['mock_transients']    = array();
		$GLOBALS['mock_posts']         = array();
		$GLOBALS['mock_post_meta']     = array();
		SitemapSettings::clearCache();
		unset(
			$GLOBALS['mock_wpdb_results'],
			$GLOBALS['mock_get_posts'],
			$GLOBALS['mock_current_screen'],
			$GLOBALS['mock_user_caps'],
			$GLOBALS['mock_filter_callbacks']['quinrel_sitemap_urls_per_file'],
			$GLOBALS['mock_filter_callbacks']['quinrel_sitemap_conflicting_plugins']
		);
	}

	protected function tearDown(): void {
		$GLOBALS['mock_wp_options'] = $this->options;
		if ( $this->wpdbResults ) {
			$GLOBALS['mock_wpdb_results'] = $this->wpdbResults;
		} else {
			unset( $GLOBALS['mock_wpdb_results'] );
		}
		if ( $this->getPosts ) {
			$GLOBALS['mock_get_posts'] = $this->getPosts;
		} else {
			unset( $GLOBALS['mock_get_posts'] );
		}
		if ( $this->wpdbVar ) {
			$GLOBALS['mock_wpdb_var'] = $this->wpdbVar;
		} else {
			unset( $GLOBALS['mock_wpdb_var'] );
		}
		$instance = ( new ReflectionClass( Plugin::class ) )->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$GLOBALS['mock_is_admin'] = false;
		$_SERVER['REQUEST_URI']   = '/';
		unset(
			$GLOBALS['mock_post_types'],
			$GLOBALS['mock_current_screen'],
			$GLOBALS['mock_user_caps'],
			$GLOBALS['mock_filter_callbacks']['quinrel_sitemap_urls_per_file'],
			$GLOBALS['mock_filter_callbacks']['quinrel_sitemap_conflicting_plugins'],
			$GLOBALS['wp_rewrite'],
			$GLOBALS['mock_published_posts']
		);
		parent::tearDown();
	}

	public function testIndexSplitsWhenAPeriodExceedsTheUrlLimit(): void {
		add_filter(
			'quinrel_sitemap_urls_per_file',
			static function () {
				return 2;
			}
		);
		$GLOBALS['mock_wpdb_results'] = static function ( string $query ): array {
			if ( ! str_contains( $query, 'COUNT' ) ) {
				return array();
			}
			return array(
				(object) array(
					'period'    => '2026-10',
					'url_count' => 5,
				),
			);
		};

		$xml = ( new SitemapGenerator() )->indexXml();

		$this->assertStringContainsString( '<sitemapindex ', $xml );
		$this->assertStringNotContainsString( '<url>', $xml );
		$this->assertStringContainsString( 'https://example.com/sitemap-posts-2026-10.xml', $xml );
		$this->assertStringContainsString( 'https://example.com/sitemap-posts-2026-10-2.xml', $xml );
		$this->assertStringContainsString( 'https://example.com/sitemap-posts-2026-10-3.xml', $xml );
		$this->assertStringNotContainsString( 'sitemap-posts-2026-10-1.xml', $xml );
		$this->assertStringNotContainsString( 'sitemap-posts-2026-10-4.xml', $xml );
		$this->assertStringNotContainsString( 'video-sitemap', $xml );
		$this->assertSame( 2, SitemapGenerator::urlLimit() );
	}

	public function testVideoSitemapListsEveryPageOfFiveHundredPosts(): void {
		update_option( 'quinrel_enable_video_sitemap', 'yes' );
		$GLOBALS['mock_post_types']       = array( 'post' );
		$GLOBALS['mock_published_posts']  = 1000;
		$GLOBALS['mock_wpdb_results']     = static function (): array {
			return array();
		};
		$seen                         = array();
		$GLOBALS['mock_get_posts']    = static function ( array $args ) use ( &$seen ): array {
			$seen = $args;
			return array();
		};

		$xml = ( new SitemapGenerator() )->indexXml();

		$this->assertStringContainsString( 'https://example.com/video-sitemap.xml', $xml );
		$this->assertStringContainsString( 'https://example.com/video-sitemap-2.xml', $xml );
		$this->assertStringNotContainsString( 'video-sitemap-3.xml', $xml );

		$page = ( new VideoSitemapGenerator() )->generateXml( 2 );

		$this->assertSame( 500, $seen['posts_per_page'] );
		$this->assertSame( 2, $seen['paged'] );
		$this->assertTrue( $seen['no_found_rows'] );
		$this->assertStringContainsString( '<urlset ', $page );
		$this->assertStringNotContainsString( '<url>', $page );

		update_option( 'quinrel_video_sitemap_cache_ver', 1, false );
		VideoSitemapGenerator::clearCache();
		$this->assertSame( 2, (int) get_option( 'quinrel_video_sitemap_cache_ver' ) );
		$this->assertFalse( $GLOBALS['mock_option_autoload']['quinrel_video_sitemap_cache_ver'] );
	}

	public function testNoindexPostsAndExcludedTypesStayOut(): void {
		update_option( 'quinrel_sitemap_exclude_types', array( 'product' ) );
		update_option( 'quinrel_sitemap_exclude_posts', '4' );
		$GLOBALS['mock_post_types'] = array( 'post', 'page', 'product' );
		$GLOBALS['mock_post_meta']  = array(
			3 => array( '_quinrel_noindex' => 'yes' ),
		);
		$GLOBALS['mock_wpdb_results'] = static function ( string $query ): array {
			if ( str_contains( $query, 'COUNT' ) ) {
				if ( str_contains( $query, "'product'" ) ) {
					return array(
						(object) array(
							'period'    => '2026-10',
							'url_count' => 9,
						),
					);
				}
				return array();
			}

			return array(
				(object) array(
					'ID'                => 2,
					'post_title'        => 'Public',
					'post_type'         => 'post',
					'post_modified_gmt' => '2026-10-01 12:00:00',
					'thumbnail_id'      => '',
				),
				(object) array(
					'ID'                => 3,
					'post_title'        => 'Hidden',
					'post_type'         => 'post',
					'post_modified_gmt' => '2026-10-01 12:00:00',
					'thumbnail_id'      => '',
				),
				(object) array(
					'ID'                => 4,
					'post_title'        => 'Skipped',
					'post_type'         => 'post',
					'post_modified_gmt' => '2026-10-01 12:00:00',
					'thumbnail_id'      => '',
				),
				(object) array(
					'ID'                => 5,
					'post_title'        => 'Product',
					'post_type'         => 'product',
					'post_modified_gmt' => '2026-10-01 12:00:00',
					'thumbnail_id'      => '',
				),
			);
		};

		$generator = new SitemapGenerator();
		$index     = $generator->indexXml();
		$chunk     = $generator->chunkXml( '2026-10', 1 );

		$this->assertStringNotContainsString( 'sitemap-posts-', $index );
		$this->assertStringContainsString( 'https://example.com/post-2/', $chunk );
		$this->assertStringNotContainsString( 'https://example.com/post-3/', $chunk );
		$this->assertStringNotContainsString( 'https://example.com/post-4/', $chunk );
		$this->assertStringNotContainsString( 'https://example.com/post-5/', $chunk );
		$sawNoindex = false;
		$sawProduct = false;
		foreach ( $GLOBALS['wpdb']->queries as $query ) {
			if ( ! is_string( $query ) || ! str_contains( $query, 'COUNT' ) ) {
				continue;
			}
			$sawNoindex = str_contains( $query, '_quinrel_noindex' );
			$sawProduct = str_contains( $query, "'product'" );
		}
		$this->assertTrue( $sawNoindex );
		$this->assertFalse( $sawProduct );
	}

	public function testChunkQueryUsesTheConfiguredLimitAndPage(): void {
		add_filter(
			'quinrel_sitemap_urls_per_file',
			static function () {
				return 2;
			}
		);
		$GLOBALS['mock_wpdb_results'] = static function (): array {
			return array();
		};

		( new SitemapGenerator() )->chunkXml( '2026-10', 2 );

		$chunkQuery = '';
		foreach ( $GLOBALS['wpdb']->queries as $query ) {
			if ( is_string( $query ) && str_contains( $query, 'LIMIT' ) ) {
				$chunkQuery = $query;
			}
		}
		$this->assertStringContainsString( 'LIMIT 2 OFFSET 2', $chunkQuery );
		$this->assertStringContainsString( "p.post_date >= '2026-10-01 00:00:00'", $chunkQuery );
		$this->assertStringContainsString( "p.post_date < '2026-11-01 00:00:00'", $chunkQuery );
		$this->assertStringNotContainsString( 'YEAR(p.post_date)', $chunkQuery );
	}

	public function testSitemapSettingsCachesTableExistenceAndRowWithinRequest(): void {
		$GLOBALS['wpdb']->queries = array();
		SitemapSettings::clearCache();

		SitemapSettings::get( 'enable_index_sitemap', 'yes' );
		SitemapSettings::get( 'enable_news_sitemap', 'no' );
		SitemapSettings::get( 'enable_video_sitemap', 'no' );

		$showTablesCount = 0;
		foreach ( $GLOBALS['wpdb']->queries as $query ) {
			if ( is_string( $query ) && str_contains( $query, 'SHOW TABLES LIKE' ) ) {
				++$showTablesCount;
			}
		}
		$this->assertSame( 1, $showTablesCount );
	}

	public function testStaticFrontPageIsIncludedInSitemapChunkAtRootUrlWithPriorityOne(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 10 );
		$GLOBALS['mock_wpdb_results'] = static function ( string $query ): array {
			if ( str_contains( $query, 'COUNT' ) ) {
				return array();
			}
			return array(
				(object) array(
					'ID'                => 10,
					'post_title'        => 'Home Page',
					'post_type'         => 'page',
					'post_modified_gmt' => '2026-10-01 12:00:00',
					'thumbnail_id'      => '',
				),
				(object) array(
					'ID'                => 11,
					'post_title'        => 'About Us',
					'post_type'         => 'page',
					'post_modified_gmt' => '2026-10-01 12:00:00',
					'thumbnail_id'      => '',
				),
			);
		};

		$chunk = ( new SitemapGenerator() )->chunkXml( '2026-10', 1 );

		$this->assertStringContainsString( '<loc>https://example.com/</loc>', $chunk );
		$this->assertStringContainsString( '<priority>1.0</priority>', $chunk );
		$this->assertStringContainsString( '<changefreq>daily</changefreq>', $chunk );
		$this->assertStringContainsString( '<loc>https://example.com/post-11/</loc>', $chunk );
		$this->assertStringNotContainsString( '<loc>https://example.com/post-10/</loc>', $chunk );
	}

	public function testNewsSitemapKeepsTheLast48Hours(): void {
		$GLOBALS['mock_posts'] = array(
			1 => new \WP_Post(
				array(
					'ID'         => 1,
					'post_title' => 'Fresh',
					'post_date'  => '2026-10-02 11:00:00',
				)
			),
			2 => new \WP_Post(
				array(
					'ID'         => 2,
					'post_title' => 'Old',
					'post_date'  => '2026-09-01 12:00:00',
				)
			),
			3 => new \WP_Post(
				array(
					'ID'         => 3,
					'post_title' => 'Edge',
					'post_date'  => '2026-09-30 12:00:00',
				)
			),
			4 => new \WP_Post(
				array(
					'ID'         => 4,
					'post_title' => 'Just out',
					'post_date'  => '2026-09-30 11:59:59',
				)
			),
			5 => new \WP_Post(
				array(
					'ID'         => 5,
					'post_title' => 'Hidden fresh',
					'post_date'  => '2026-10-02 10:00:00',
				)
			),
		);
		$GLOBALS['mock_post_meta'][5] = array( '_quinrel_noindex' => 'yes' );
		$GLOBALS['mock_get_posts']    = static function (): array {
			return array_values( $GLOBALS['mock_posts'] );
		};

		$xml = ( new NewsSitemapGenerator() )->generateXml();

		$this->assertStringContainsString( 'https://example.com/post-1/', $xml );
		$this->assertStringContainsString( 'Fresh', $xml );
		$this->assertStringContainsString( 'https://example.com/post-3/', $xml );
		$this->assertStringContainsString( 'Edge', $xml );
		$this->assertStringNotContainsString( 'https://example.com/post-2/', $xml );
		$this->assertStringNotContainsString( 'Old', $xml );
		$this->assertStringNotContainsString( 'https://example.com/post-4/', $xml );
		$this->assertStringNotContainsString( 'Just out', $xml );
		$this->assertStringNotContainsString( 'https://example.com/post-5/', $xml );
		$this->assertStringNotContainsString( 'Hidden fresh', $xml );
		$this->assertSame( '48 hours ago', $GLOBALS['mock_get_posts_args']['date_query'][0]['after'] );
	}

	public function testVideoSitemapListsOnlyPagesWithAVideo(): void {
		$GLOBALS['mock_posts'] = array(
			8 => new \WP_Post(
				array(
					'ID'           => 8,
					'post_title'   => 'Watch',
					'post_content' => 'See https://www.youtube.com/watch?v=abcdefghijk today.',
					'post_excerpt' => 'A clip',
				)
			),
			9 => new \WP_Post(
				array(
					'ID'           => 9,
					'post_title'   => 'Plain',
					'post_content' => 'No video on this page.',
					'post_excerpt' => 'Text',
				)
			),
		);
		$GLOBALS['mock_get_posts'] = static function (): array {
			return array_values( $GLOBALS['mock_posts'] );
		};

		$xml = ( new VideoSitemapGenerator() )->generateXml();

		$this->assertStringContainsString( 'https://example.com/post-8/', $xml );
		$this->assertStringContainsString( '<video:video>', $xml );
		$this->assertStringNotContainsString( 'https://example.com/post-9/', $xml );
		$this->assertStringNotContainsString( 'https://example.com/</loc>', $xml );
		$this->assertStringNotContainsString( '<video:duration>', $xml );
		$this->assertSame( 1, substr_count( $xml, '<loc>' ) );
		$this->assertArrayNotHasKey( 'duration', VideoExtractor::extractVideoObjects( 8 )[0] );
	}

	public function testVideoSitemapUsesOnlyADurationTheEditorSaved(): void {
		$GLOBALS['mock_posts'] = array(
			10 => new \WP_Post(
				array(
					'ID'           => 10,
					'post_title'   => 'Manual',
					'post_content' => '',
					'post_excerpt' => 'Saved length',
				)
			),
			11 => new \WP_Post(
				array(
					'ID'           => 11,
					'post_title'   => 'Unknown length',
					'post_content' => '',
					'post_excerpt' => 'No length',
				)
			),
			12 => new \WP_Post(
				array(
					'ID'           => 12,
					'post_title'   => 'Hour',
					'post_content' => '<video src="https://example.com/clip.mp4"></video>',
					'post_excerpt' => 'File',
				)
			),
		);
		$GLOBALS['mock_post_meta'][10] = array(
			'_quinrel_video_url'      => 'https://example.com/talk.mp4',
			'_quinrel_video_duration' => '90',
		);
		$GLOBALS['mock_post_meta'][11] = array(
			'_quinrel_video_url'      => 'https://example.com/untitled.mp4',
			'_quinrel_video_duration' => '0',
		);
		$GLOBALS['mock_get_posts'] = static function (): array {
			return array_values( $GLOBALS['mock_posts'] );
		};

		$saved = VideoExtractor::extractVideoObjects( 10 );
		$this->assertSame( 'PT90S', $saved[0]['duration'] );
		$this->assertArrayNotHasKey( 'duration', VideoExtractor::extractVideoObjects( 11 )[0] );
		$this->assertArrayNotHasKey( 'duration', VideoExtractor::extractVideoObjects( 12 )[0] );
		$this->assertSame( '', VideoExtractor::isoDuration( 'PT0S' ) );
		$this->assertSame( '', VideoExtractor::isoDuration( 'about a minute' ) );
		$this->assertSame( 'PT1H2M', VideoExtractor::isoDuration( 'PT1H2M' ) );
		$this->assertSame( 3720, VideoExtractor::secondsFromIso( 'PT1H2M' ) );

		$xml = ( new VideoSitemapGenerator() )->generateXml();

		$this->assertStringContainsString( '<video:duration>90</video:duration>', $xml );
		$this->assertSame( 1, substr_count( $xml, '<video:duration>' ) );
		$this->assertStringContainsString( 'https://example.com/post-11/', $xml );
		$this->assertStringContainsString( 'https://example.com/post-12/', $xml );
	}

	public function testActivationRegistersRulesAndFlushesOnce(): void {
		update_option( 'quinrel_enable_index_sitemap', 'yes' );
		update_option( 'quinrel_enable_news_sitemap', 'yes' );
		update_option( 'quinrel_enable_video_sitemap', 'yes' );
		$GLOBALS['wp_rewrite']->extra_rules_top['^shop$'] = 'index.php?post_type=product';

		$routes = new SitemapRouteManager(
			new SitemapGenerator(),
			new NewsSitemapGenerator(),
			new VideoSitemapGenerator()
		);
		$routes->flushRules();

		$this->assertSame( 1, $GLOBALS['mock_flush_count'] );
		$rules = $GLOBALS['wp_rewrite']->extra_rules_top;
		$this->assertArrayHasKey( '^sitemap\.xml$', $rules );
		$this->assertArrayHasKey( '^sitemap-posts-(\d{4}-\d{2})(?:-([2-9]|[1-9]\d+))?\.xml$', $rules );
		$this->assertStringContainsString( 'quinrel_sitemap_page=$matches[2]', $rules['^sitemap-posts-(\d{4}-\d{2})(?:-([2-9]|[1-9]\d+))?\.xml$'] );
		$this->assertArrayHasKey( '^sitemap-news\.xml$', $rules );
		$videoRule = '^video-sitemap(?:-([2-9]|[1-9]\d+))?\.xml$';
		$this->assertArrayHasKey( $videoRule, $rules );
		$this->assertStringContainsString( 'quinrel_sitemap_page=$matches[1]', $rules[ $videoRule ] );
		$this->assertArrayHasKey( '^shop$', $rules );

		$routes->removeRulesAndFlush();

		$this->assertSame( 2, $GLOBALS['mock_flush_count'] );
		$this->assertArrayNotHasKey( '^sitemap\.xml$', $GLOBALS['wp_rewrite']->extra_rules_top );
		$this->assertArrayNotHasKey( '^sitemap-posts-(\d{4}-\d{2})(?:-([2-9]|[1-9]\d+))?\.xml$', $GLOBALS['wp_rewrite']->extra_rules_top );
		$this->assertArrayNotHasKey( '^sitemap-news\.xml$', $GLOBALS['wp_rewrite']->extra_rules_top );
		$this->assertArrayNotHasKey( '^video-sitemap(?:-([2-9]|[1-9]\d+))?\.xml$', $GLOBALS['wp_rewrite']->extra_rules_top );
		$this->assertSame( 'index.php?post_type=product', $GLOBALS['wp_rewrite']->extra_rules_top['^shop$'] );

		$plugin     = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );
		$activate   = $this->methodBody( $plugin, 'activateSite' );
		$deactivate = $this->methodBody( $plugin, 'deactivateSite' );
		$this->assertSame( 1, substr_count( $activate, 'flushRules()' ) );
		$this->assertSame( 0, substr_count( $activate, 'removeRulesAndFlush()' ) );
		$this->assertSame( 1, substr_count( $deactivate, 'removeRulesAndFlush()' ) );
		$this->assertSame( 0, substr_count( $deactivate, 'flushRules()' ) );
		$this->assertStringContainsString( 'video-sitemap(?:-(?:[2-9]|[1-9]\d+))?\.xml', $plugin );
	}

	public function testConflictingSeoPluginAsksTheUserToDisableOne(): void {
		update_option( 'active_plugins', array( 'wordpress-seo/wp-seo.php' ) );
		update_option( 'quinrel_enable_index_sitemap', 'yes' );

		$module = new SitemapModule(
			new \Quinrel\WordPress\Core\Event\EventManager(),
			new SitemapRouteManager(
				new SitemapGenerator(),
				new NewsSitemapGenerator(),
				new VideoSitemapGenerator()
			)
		);
		$text = $module->conflictNoticeText();
		$this->assertStringContainsString( 'Yoast SEO', $text );
		$this->assertStringContainsString( 'Disable the Quinrel XML sitemap or disable the sitemap in the other plugin', $text );

		update_option( 'quinrel_enable_index_sitemap', 'no' );
		$this->assertSame( '', $module->conflictNoticeText() );

		update_option( 'quinrel_enable_index_sitemap', 'yes' );
		$GLOBALS['mock_current_screen'] = (object) array( 'id' => 'dashboard' );
		ob_start();
		$module->renderConflictNotice();
		$dashboard = ob_get_clean();
		$this->assertSame( '', $dashboard );

		$GLOBALS['mock_current_screen'] = (object) array( 'id' => 'toplevel_page_quinrel' );
		ob_start();
		$module->renderConflictNotice();
		$quinrel = ob_get_clean();
		$this->assertStringContainsString( 'Yoast SEO', $quinrel );
		$this->assertStringContainsString( 'notice-warning', $quinrel );

		$GLOBALS['mock_user_caps'] = array(
			'manage_seo'     => false,
			'manage_options' => false,
		);
		ob_start();
		$module->renderConflictNotice();
		$denied = ob_get_clean();
		$this->assertSame( '', $denied );

		add_filter(
			'quinrel_sitemap_conflicting_plugins',
			static function (): array {
				return array( 'Custom SEO' );
			}
		);
		$GLOBALS['mock_user_caps'] = array(
			'manage_seo'     => true,
			'manage_options' => true,
		);
		$this->assertStringContainsString( 'Custom SEO', $module->conflictNoticeText() );
		$this->assertStringNotContainsString( 'Yoast SEO', $module->conflictNoticeText() );
	}

	public function testPagedSitemapPathDoesNotConstructTheGenerator(): void {
		update_option( 'quinrel_db_version', QUINREL_VERSION );
		$instance = ( new ReflectionClass( Plugin::class ) )->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$_SERVER['REQUEST_URI']   = '/sitemap-posts-2026-10-2.xml';
		$GLOBALS['mock_is_admin'] = false;
		$GLOBALS['mock_hooks']    = array();

		$plugin = Plugin::getInstance();
		$plugin->boot();

		$names = array();
		foreach ( $GLOBALS['mock_hooks'] as $hook ) {
			$names[] = $hook[1];
		}
		$this->assertContains( 'template_redirect', $names );
		$property = ( new ReflectionClass( $plugin->getContainer() ) )->getProperty( 'instances' );
		$property->setAccessible( true );
		$instances = $property->getValue( $plugin->getContainer() );
		$this->assertArrayNotHasKey( 'sitemap_generator', $instances );

		$instance->setValue( null, null );
		$_SERVER['REQUEST_URI'] = '/';
	}

	public function testWooCommerceProductGalleryImagesIncludedInSitemap(): void {
		$productId = 901;
		$GLOBALS['mock_post_meta'][ $productId ]['_product_image_gallery'] = '902,903';
		$GLOBALS['mock_posts'][902] = (object) array(
			'ID'         => 902,
			'post_title' => 'Gallery Angle 1',
			'guid'       => 'https://example.com/wp-content/uploads/angle1.jpg',
		);
		$GLOBALS['mock_posts'][903] = (object) array(
			'ID'         => 903,
			'post_title' => 'Gallery Angle 2',
			'guid'       => 'https://example.com/wp-content/uploads/angle2.jpg',
		);

		$GLOBALS['mock_wpdb_results'] = static function ( string $query ) use ( $productId ): array {
			if ( str_contains( $query, 'COUNT' ) ) {
				return array();
			}
			return array(
				(object) array(
					'ID'                => $productId,
					'post_title'        => 'Custom Boots',
					'post_type'         => 'product',
					'post_modified_gmt' => '2026-10-01 12:00:00',
					'thumbnail_id'      => 0,
				),
			);
		};

		$xml = ( new SitemapGenerator() )->chunkXml( '2026-10', 1 );

		$this->assertStringContainsString( '<image:image>', $xml );
		$this->assertStringContainsString( 'angle1.jpg', $xml );
		$this->assertStringContainsString( 'Gallery Angle 1', $xml );
		$this->assertStringContainsString( 'angle2.jpg', $xml );
		$this->assertStringContainsString( 'Gallery Angle 2', $xml );
	}

	private function methodBody( string $source, string $name ): string {
		$start = strpos( $source, 'function ' . $name . '(' );
		$this->assertNotFalse( $start );
		$end = strpos( $source, "\n\tprivate static function", $start + 10 );
		$this->assertNotFalse( $end );
		return substr( $source, (int) $start, (int) $end - (int) $start );
	}
}
