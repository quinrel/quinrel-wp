<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\ContentAssistant\BrandMentionFinder;
use Quinrel\WordPress\Modules\ContentAssistant\AiPitchDraftsman;

class OutreachSuiteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $mock_posts, $mock_post_meta, $mock_wp_options, $mock_blogname;
		$mock_posts         = array();
		$mock_post_meta     = array();
		$mock_blogname      = 'Quinrel SEO';
		$mock_wp_options    = array(
			'blogname'            => 'Quinrel SEO',
			'quinrel_brand_terms' => 'quinrel, skoorite',
		);
	}

	protected function tearDown(): void {
		$GLOBALS['mock_blogname'] = 'Quinrel Test Site';
		parent::tearDown();
	}

	public function testBrandMentionFinderExtractsConfiguredTerms(): void {
		$finder = new BrandMentionFinder();
		$terms  = $finder->getBrandTerms();

		$this->assertContains( 'quinrel', $terms );
		$this->assertContains( 'skoorite', $terms );
		$this->assertContains( 'quinrel seo', $terms );
	}

	public function testBrandMentionFinderIdentifiesOpportunities(): void {
		$finder  = new BrandMentionFinder();
		$queries = array(
			array(
				'query'       => 'how to configure quinrel schema',
				'impressions' => 1200,
				'clicks'      => 14,
				'ctr'         => 0.011,
				'position'    => 4.2,
				'page'        => 'https://example.com/blog/general-setup/',
			),
			array(
				'query'       => 'best seo plugins for wordpress',
				'impressions' => 8500,
				'clicks'      => 450,
				'ctr'         => 0.052,
				'position'    => 2.1,
				'page'        => 'https://example.com/blog/top-plugins/',
			),
			array(
				'query'       => 'skoorite team benchmarks',
				'impressions' => 350,
				'clicks'      => 45,
				'ctr'         => 0.128,
				'position'    => 1.5,
				'page'        => 'https://example.com/about/',
			),
		);

		$result = $finder->findMentions( array( 'queries' => $queries ) );

		$this->assertCount( 2, $result['opportunities'] );
		$first = $result['opportunities'][0];
		$this->assertSame( 'how to configure quinrel schema', $first['query'] );
		$this->assertSame( 'quinrel', $first['matched_brand'] );
		$this->assertSame( 'unlinked_citation_risk', $first['type'] );
		$this->assertGreaterThan( 50, $first['opportunity_score'] );
	}

	public function testAiPitchDraftsmanProducesEditorialTemplate(): void {
		global $mock_posts;
		$postId = 840;
		$mock_posts[ $postId ] = new \WP_Post( array(
			'ID'         => $postId,
			'post_title' => 'The Complete Architecture of Modern Structured Data',
			'post_type'  => 'post',
		) );

		$draftsman = new AiPitchDraftsman();
		$pitch     = $draftsman->draftPitch( array(
			'target_domain'  => 'smashingmagazine.com',
			'target_topic'   => 'WordPress Rich Snippets',
			'contact_name'   => 'Rachel',
			'contact_email'  => 'rachel@smashingmagazine.com',
			'our_article_id' => $postId,
			'pitch_type'     => 'resource_addition',
		) );

		$this->assertArrayHasKey( 'subject', $pitch );
		$this->assertArrayHasKey( 'body', $pitch );
		$this->assertArrayHasKey( 'mailto_url', $pitch );
		$this->assertStringContainsString( 'WordPress Rich Snippets', $pitch['subject'] );
		$this->assertStringContainsString( 'Hi Rachel,', $pitch['body'] );
		$this->assertStringContainsString( 'The Complete Architecture of Modern Structured Data', $pitch['body'] );
		$this->assertStringStartsWith( 'mailto:rachel%40smashingmagazine.com', $pitch['mailto_url'] );
	}

	public function testAiPitchDraftsmanBrokenLinkMode(): void {
		$draftsman = new AiPitchDraftsman();
		$pitch     = $draftsman->draftPitch( array(
			'target_domain'     => 'searchengineland.com',
			'target_topic'      => 'Core Web Vitals Optimization',
			'our_article_title' => 'Definitive CWV Playbook',
			'our_article_url'   => 'https://example.com/cwv-playbook/',
			'pitch_type'        => 'broken_link',
		) );

		$this->assertStringContainsString( 'Broken link replacement', $pitch['subject'] );
		$this->assertStringContainsString( '404 error', $pitch['body'] );
		$this->assertStringContainsString( 'Definitive CWV Playbook', $pitch['body'] );
	}
}
