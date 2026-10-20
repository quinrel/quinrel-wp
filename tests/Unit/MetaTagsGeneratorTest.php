<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Seo\MetaTagsGenerator;

class MetaTagsGeneratorTest extends TestCase
{
    private MetaTagsGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new MetaTagsGenerator();

        global $mock_post_meta, $mock_posts, $mock_current_post_id;
        global $mock_is_singular, $mock_is_admin, $mock_is_front_page, $mock_is_home, $mock_is_404, $mock_is_search, $mock_is_paged, $mock_is_date;
        global $mock_wp_options;

        $mock_post_meta = [];
        $mock_posts = [];
        $mock_current_post_id = 0;
        $mock_wp_options = [];

        $mock_is_singular = false;
        $mock_is_admin = false;
        $mock_is_front_page = false;
        $mock_is_home = false;
        $mock_is_404 = false;
        $mock_is_search = false;
        $mock_is_paged = false;
        $mock_is_date = false;
    }

    public function testGetDocumentTitleNonSingular(): void
    {
        $title = $this->generator->getDocumentTitle();
        $this->assertSame('Quinrel Test Site - AI Powered SEO Plugin', $title);
    }

    public function testGetDocumentTitleSingularDefault(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_posts;
        $mock_is_singular = true;
        $mock_current_post_id = 42;
        $mock_posts[42] = new \WP_Post([
            'ID' => 42,
            'post_title' => 'Sample Post Title',
        ]);

        $title = $this->generator->getDocumentTitle();
        $this->assertSame('Sample Post Title - Quinrel Test Site', $title);
    }

    public function testGetDocumentTitleSingularCustomMeta(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_posts, $mock_post_meta;
        $mock_is_singular = true;
        $mock_current_post_id = 42;
        $mock_posts[42] = new \WP_Post([
            'ID' => 42,
            'post_title' => 'Original Post Title',
        ]);
        $mock_post_meta[42]['_quinrel_meta_title'] = 'Custom AI SEO Optimized Title';

        $title = $this->generator->getDocumentTitle();
        $this->assertSame('Custom AI SEO Optimized Title', $title);
    }

    public function testRobotsMetaDirectives(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_post_meta;
        global $mock_is_404, $mock_is_search, $mock_is_paged, $mock_is_date;

        $ref = new \ReflectionClass(MetaTagsGenerator::class);
        $method = $ref->getMethod('getRobotsMeta');
        $method->setAccessible(true);

        // Default non-singular (archive/home)
        $this->assertSame('index, follow', $method->invoke($this->generator));

        // 404 page
        $mock_is_404 = true;
        $this->assertSame('noindex, follow', $method->invoke($this->generator));
        $mock_is_404 = false;

        // Search page
        $mock_is_search = true;
        $this->assertSame('noindex, follow', $method->invoke($this->generator));
        $mock_is_search = false;

        // Paged archive (page 2+)
        $mock_is_paged = true;
        $this->assertSame('noindex, follow', $method->invoke($this->generator));
        $mock_is_paged = false;

        // Date archive
        $mock_is_date = true;
        $this->assertSame('noindex, follow', $method->invoke($this->generator));
        $mock_is_date = false;

        // Singular default (index, follow)
        $mock_is_singular = true;
        $mock_current_post_id = 10;
        $this->assertSame('index, follow', $method->invoke($this->generator));

        // Singular noindex
        $mock_post_meta[10]['_quinrel_noindex'] = 'yes';
        $this->assertSame('noindex, follow', $method->invoke($this->generator));

        // Singular noindex and nofollow
        $mock_post_meta[10]['_quinrel_nofollow'] = 'yes';
        $this->assertSame('noindex, nofollow', $method->invoke($this->generator));
    }

    public function testCanonicalUrlResolution(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_post_meta, $mock_posts;
        global $mock_is_front_page, $mock_is_404, $mock_is_search;

        $ref = new \ReflectionClass(MetaTagsGenerator::class);
        $method = $ref->getMethod('getCanonicalUrl');
        $method->setAccessible(true);

        // Front page canonical
        $mock_is_front_page = true;
        $this->assertSame('https://example.com/', $method->invoke($this->generator));
        $mock_is_front_page = false;

        // 404 page suppresses canonical
        $mock_is_404 = true;
        $this->assertSame('', $method->invoke($this->generator));
        $mock_is_404 = false;

        // Search page suppresses canonical
        $mock_is_search = true;
        $this->assertSame('', $method->invoke($this->generator));
        $mock_is_search = false;

        // Singular standard permalink
        $mock_is_singular = true;
        $mock_current_post_id = 55;
        $mock_posts[55] = new \WP_Post(['ID' => 55, 'post_title' => 'Test Post']);
        $this->assertSame('https://example.com/post-55/', $method->invoke($this->generator));

        // Singular custom canonical override
        $mock_post_meta[55]['_quinrel_canonical_url'] = 'https://example.com/preferred-url/';
        $this->assertSame('https://example.com/preferred-url/', $method->invoke($this->generator));

        // Singular noindex suppresses canonical
        $mock_post_meta[55]['_quinrel_noindex'] = 'yes';
        $this->assertSame('', $method->invoke($this->generator));
    }

    public function testOutputStandardMetaTagsIncludesVerificationAndDescriptions(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_posts, $mock_post_meta;

        $mock_is_singular = true;
        $mock_current_post_id = 99;
        $mock_posts[99] = new \WP_Post([
            'ID' => 99,
            'post_title' => 'SEO Title',
            'post_excerpt' => 'This is a sample excerpt for description.',
        ]);

        update_option('quinrel_google_verify', 'google-test-token-123');
        update_option('quinrel_bing_verify', 'bing-test-token-456');

        ob_start();
        $this->generator->outputStandardMetaTags();
        $output = ob_get_clean();

        $this->assertStringContainsString('name="description" content="This is a sample excerpt for description."', $output);
        $this->assertStringContainsString('rel="canonical" href="https://example.com/post-99/"', $output);
        $this->assertStringContainsString('name="robots" content="index, follow"', $output);
        $this->assertStringContainsString('name="google-site-verification" content="google-test-token-123"', $output);
        $this->assertStringContainsString('name="msvalidate.01" content="bing-test-token-456"', $output);
    }

    public function testBlogPublicDiscouragedForcesNoindexNofollowAndSuppressesCanonical(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_posts;

        $mock_is_singular = true;
        $mock_current_post_id = 77;
        $mock_posts[77] = new \WP_Post([
            'ID' => 77,
            'post_title' => 'Staging Post',
            'post_excerpt' => 'Staging excerpt.',
        ]);

        update_option('blog_public', '0');

        ob_start();
        $this->generator->outputStandardMetaTags();
        $output = ob_get_clean();

        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $output);
        $this->assertStringNotContainsString('rel="canonical"', $output);

        $filtered = $this->generator->filterWpRobots(['index' => true, 'follow' => true, 'max-image-preview' => 'large']);
        $this->assertTrue($filtered['noindex'] ?? false);
        $this->assertTrue($filtered['nofollow'] ?? false);
        $this->assertArrayNotHasKey('index', $filtered);
        $this->assertArrayNotHasKey('follow', $filtered);
        $this->assertSame('large', $filtered['max-image-preview']);
    }

    public function testSeoModuleBootRemovesDuplicateCoreCanonicalAndRobotsHooks(): void
    {
        $GLOBALS['mock_removed_actions'] = [];
        $eventManager = new \Quinrel\WordPress\Core\Event\EventManager();
        $openGraph = new \Quinrel\WordPress\Modules\Seo\OpenGraphGenerator();
        $module = new \Quinrel\WordPress\Modules\Seo\SeoModule($eventManager, $this->generator, $openGraph);

        $module->boot();

        $this->assertContains(['wp_head', 'rel_canonical', 10], $GLOBALS['mock_removed_actions']);
        $this->assertContains(['wp_head', 'wp_robots', 1], $GLOBALS['mock_removed_actions']);
    }

    public function testSmartTagsAndShortcodeStrippingInTitleAndDescription(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_posts, $mock_post_meta;

        $mock_is_singular = true;
        $mock_current_post_id = 88;
        $mock_posts[88] = new \WP_Post([
            'ID' => 88,
            'post_title' => 'Ultimate WordPress Guide',
            'post_content' => '<!-- wp:paragraph -->[vc_row][contact-form-7 id="12"]Clean body content without shortcodes.[/vc_row]<!-- /wp:paragraph -->',
        ]);
        $mock_post_meta[88]['_quinrel_meta_title'] = '%%title%% %%page%% %%sep%% %%sitename%%';

        $this->assertSame('Ultimate WordPress Guide - Quinrel Test Site', $this->generator->getDocumentTitle());

        ob_start();
        $this->generator->outputStandardMetaTags();
        $output = ob_get_clean();

        $this->assertStringContainsString('name="description" content="Clean body content without shortcodes."', $output);
        $this->assertStringNotContainsString('[vc_row]', $output);
        $this->assertStringNotContainsString('[contact-form-7', $output);
    }

    public function testGa4GtagEnqueuesForVisitorsAndSkipsAdminsOrInvalidIds(): void
    {
        $GLOBALS['mock_enqueued_scripts'] = [];
        $GLOBALS['mock_inline_scripts'] = [];
        $GLOBALS['mock_user_caps'] = ['manage_options' => false];

        // Invalid Measurement ID is ignored
        update_option('quinrel_ga4_measurement_id', 'UA-12345');
        $this->assertFalse($this->generator->hasValidGa4MeasurementId());
        $this->generator->enqueueGa4Tag();
        $this->assertArrayNotHasKey('quinrel-ga4-gtag', $GLOBALS['mock_enqueued_scripts']);

        // Valid Measurement ID enqueues gtag.js for non-admin visitor
        update_option('quinrel_ga4_measurement_id', 'g-abc1234567');
        $this->assertTrue($this->generator->hasValidGa4MeasurementId());
        $this->assertSame('G-ABC1234567', $this->generator->getValidGa4MeasurementId());

        $this->generator->enqueueGa4Tag();
        $this->assertSame(
            'https://www.googletagmanager.com/gtag/js?id=G-ABC1234567',
            $GLOBALS['mock_enqueued_scripts']['quinrel-ga4-gtag'] ?? ''
        );
        $this->assertStringContainsString(
            'gtag("config", "G-ABC1234567");',
            $GLOBALS['mock_inline_scripts']['quinrel-ga4-gtag'] ?? ''
        );

        // Logged-in administrator is skipped
        $GLOBALS['mock_enqueued_scripts'] = [];
        $GLOBALS['mock_user_caps'] = ['manage_options' => true];
        $this->generator->enqueueGa4Tag();
        $this->assertArrayNotHasKey('quinrel-ga4-gtag', $GLOBALS['mock_enqueued_scripts']);

        delete_option('quinrel_ga4_measurement_id');
        unset($GLOBALS['mock_user_caps']);
    }

    public function testOpenGraphAndTwitterCardFallbacksAndDynamicImageMimeType(): void
    {
        global $mock_is_singular, $mock_current_post_id, $mock_posts, $mock_post_meta;

        $mock_is_singular = true;
        $mock_current_post_id = 120;
        $mock_posts[120] = new \WP_Post([
            'ID' => 120,
            'post_title' => 'Social Fallback Post',
            'post_content' => '[vc_row]Clean social snippet.[/vc_row]',
        ]);

        // Only SEO meta title/description set (no explicit _quinrel_og_* set)
        $mock_post_meta[120] = [
            '_quinrel_meta_title' => '{{title}} | {{sitename}}',
            '_quinrel_meta_description' => 'Custom SEO meta description used as OG fallback.',
            '_quinrel_og_image' => 'https://example.com/uploads/banner.webp',
            '_quinrel_twitter_title' => 'Twitter Specific Title - {{title}}',
            '_quinrel_twitter_description' => 'Twitter Specific Description.',
            '_quinrel_twitter_image' => 'https://example.com/uploads/twitter-card.png',
        ];

        $ogGenerator = new \Quinrel\WordPress\Modules\Seo\OpenGraphGenerator();
        ob_start();
        $ogGenerator->outputSocialMetaTags();
        $output = ob_get_clean();

        $this->assertStringContainsString('property="og:title" content="Social Fallback Post | Quinrel Test Site"', $output);
        $this->assertStringContainsString('property="og:description" content="Custom SEO meta description used as OG fallback."', $output);
        $this->assertStringContainsString('property="og:image" content="https://example.com/uploads/banner.webp"', $output);
        $this->assertStringContainsString('property="og:image:type" content="image/webp"', $output);
        $this->assertStringContainsString('name="twitter:title" content="Twitter Specific Title - Social Fallback Post"', $output);
        $this->assertStringContainsString('name="twitter:description" content="Twitter Specific Description."', $output);
        $this->assertStringContainsString('name="twitter:image" content="https://example.com/uploads/twitter-card.png"', $output);
    }
}
