<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Seo\RssOptimizations;

class RssOptimizationsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_is_feed'] = true;
		delete_option( 'quinrel_rss_attribution' );
		delete_option( 'quinrel_rss_before_content' );
		delete_option( 'quinrel_rss_after_content' );
	}

	protected function tearDown(): void {
		$GLOBALS['mock_is_feed'] = false;
		parent::tearDown();
	}

	public function testDefaultAttributionStaysOffUntilEnabled(): void {
		$rss = new RssOptimizations();

		$this->assertSame( 'Body', $rss->optimizeFeedContent( 'Body' ) );

		update_option( 'quinrel_rss_attribution', 'yes' );
		$result = $rss->optimizeFeedContent( 'Body' );
		$this->assertStringContainsString( 'The post', $result );
		$this->assertStringContainsString( 'first appeared on', $result );
		$this->assertStringContainsString( 'href="https://example.com/"', $result );
		$this->assertStringStartsWith( 'Body', $result );
	}

	public function testSavedFeedTextIsUsedWhenTheDefaultLineIsOff(): void {
		update_option( 'quinrel_rss_before_content', '<p>%%POST_LINK%% by %%AUTHOR%%</p>' );
		$rss    = new RssOptimizations();
		$result = $rss->optimizeFeedContent( 'Body' );

		$this->assertStringContainsString( 'Test Author', $result );
		$this->assertStringContainsString( 'Body', $result );
		$this->assertStringNotContainsString( 'first appeared on', $result );
	}

	public function testFeedContentOutsideAFeedIsUnchanged(): void {
		$GLOBALS['mock_is_feed'] = false;
		update_option( 'quinrel_rss_attribution', 'yes' );
		$rss = new RssOptimizations();
		$this->assertSame( 'Body', $rss->optimizeFeedContent( 'Body' ) );
	}
}
