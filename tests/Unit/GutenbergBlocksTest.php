<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Blocks\BlockRenderer;
use Quinrel\WordPress\Modules\Blocks\BlockSchemaExtractor;
use Quinrel\WordPress\Modules\Schema\SchemaGenerator;

class GutenbergBlocksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $mock_posts, $mock_post_meta, $mock_wp_options;
		$mock_posts      = array();
		$mock_post_meta  = array();
		$mock_wp_options = array(
			'blogname'                => 'Quinrel SEO',
			'quinrel_title_separator' => '|',
		);
	}

	public function testFaqBlockRenderAndSchema(): void {
		$attrs = array(
			'title' => 'Common Questions',
			'items' => array(
				array(
					'question' => 'What is Schema.org markup?',
					'answer'   => 'Structured data that helps search engines understand page entities.',
				),
				array(
					'question' => 'Does Quinrel support FAQ rich snippets?',
					'answer'   => 'Yes, automatically via this Gutenberg block.',
				),
			),
		);

		// 1. Verify HTML Render
		$html = BlockRenderer::renderFaq( $attrs );
		$this->assertStringContainsString( 'Common Questions', $html );
		$this->assertStringContainsString( '<details class="quinrel-faq-item">', $html );
		$this->assertStringContainsString( 'What is Schema.org markup?', $html );
		$this->assertStringContainsString( 'Does Quinrel support FAQ rich snippets?', $html );

		// 2. Verify Schema Node
		$postId = 501;
		$schema = BlockSchemaExtractor::buildFaqSchema( $attrs, $postId );
		$this->assertSame( 'FAQPage', $schema['@type'] );
		$this->assertCount( 2, $schema['mainEntity'] );
		$this->assertSame( 'Question', $schema['mainEntity'][0]['@type'] );
		$this->assertSame( 'What is Schema.org markup?', $schema['mainEntity'][0]['name'] );
		$this->assertSame( 'Answer', $schema['mainEntity'][0]['acceptedAnswer']['@type'] );
	}

	public function testHowToBlockRenderAndSchema(): void {
		$attrs = array(
			'title'       => 'How to Build a Custom Gutenberg Block',
			'description' => 'A step by step tutorial for WordPress developers.',
			'totalTime'   => 'PT45M',
			'supplies'    => array( 'Node.js', 'WordPress 6.4' ),
			'steps'       => array(
				array(
					'title'    => 'Initialize wp-scripts',
					'text'     => 'Run npm install @wordpress/scripts in your plugin.',
					'imageUrl' => 'https://example.com/step1.png',
				),
				array(
					'title'    => 'Register the Block',
					'text'     => 'Call registerBlockType in your JavaScript index.',
					'imageUrl' => '',
				),
			),
		);

		// 1. Verify HTML Render
		$html = BlockRenderer::renderHowTo( $attrs );
		$this->assertStringContainsString( 'How to Build a Custom Gutenberg Block', $html );
		$this->assertStringContainsString( '⏱ PT45M', $html );
		$this->assertStringContainsString( 'Node.js', $html );
		$this->assertStringContainsString( 'Initialize wp-scripts', $html );
		$this->assertStringContainsString( 'https://example.com/step1.png', $html );

		// 2. Verify Schema Node
		$postId = 502;
		$schema = BlockSchemaExtractor::buildHowToSchema( $attrs, $postId );
		$this->assertSame( 'HowTo', $schema['@type'] );
		$this->assertSame( 'How to Build a Custom Gutenberg Block', $schema['name'] );
		$this->assertCount( 2, $schema['step'] );
		$this->assertSame( 'HowToStep', $schema['step'][0]['@type'] );
		$this->assertSame( 'Initialize wp-scripts', $schema['step'][0]['name'] );
		$this->assertSame( 'https://example.com/step1.png', $schema['step'][0]['image'] );
	}

	public function testReviewBlockRenderAndSchema(): void {
		$attrs = array(
			'itemName'      => 'Noise Cancelling Headphones Pro',
			'ratingValue'   => 4.8,
			'itemType'      => 'Product',
			'summary'       => 'Exceptional audio clarity and deep active noise cancellation.',
			'pros'          => array( 'Superior battery life', 'Ultra-comfortable fit' ),
			'cons'          => array( 'Premium price point' ),
			'affiliateUrl'  => 'https://partner.example.com/headphones',
			'affiliateText' => 'View on Retailer',
		);

		// 1. Verify HTML Render
		$html = BlockRenderer::renderReview( $attrs );
		$this->assertStringContainsString( 'Noise Cancelling Headphones Pro', $html );
		$this->assertStringContainsString( '4.8 / 5.0', $html );
		$this->assertStringContainsString( 'Superior battery life', $html );
		$this->assertStringContainsString( 'Premium price point', $html );
		$this->assertStringContainsString( 'href="https://partner.example.com/headphones"', $html );
		$this->assertStringContainsString( 'View on Retailer', $html );

		// 2. Verify Schema Node
		$postId = 503;
		$schema = BlockSchemaExtractor::buildReviewSchema( $attrs, $postId );
		$this->assertSame( 'Review', $schema['@type'] );
		$this->assertSame( 'Product', $schema['itemReviewed']['@type'] );
		$this->assertSame( 'Noise Cancelling Headphones Pro', $schema['itemReviewed']['name'] );
		$this->assertSame( 4.8, $schema['reviewRating']['ratingValue'] );
		$this->assertCount( 2, $schema['positiveNotes']['itemListElement'] );
		$this->assertSame( 'Superior battery life', $schema['positiveNotes']['itemListElement'][0]['name'] );
	}

	public function testTocBlockHeadingExtractionAndAnchorFilter(): void {
		$content = <<<HTML
<p>Introductory paragraph.</p>
<h2>First Major Section</h2>
<p>Some discussion here.</p>
<h3>Sub-point Alpha</h3>
<p>Detail on alpha.</p>
<h2>Second Major Section</h2>
<p>Closing thoughts.</p>
HTML;

		$headings = BlockRenderer::extractHeadings( $content );
		$this->assertCount( 3, $headings );
		$this->assertSame( 'first-major-section', $headings[0]['anchor'] );
		$this->assertSame( 'sub-point-alpha', $headings[1]['anchor'] );
		$this->assertSame( 'second-major-section', $headings[2]['anchor'] );

		// In-page anchor injection
		$filtered = BlockRenderer::filterContentHeadingAnchors( '<div class="quinrel-block-toc"></div>' . $content );
		$this->assertStringContainsString( '<h2 id="first-major-section">First Major Section</h2>', $filtered );
		$this->assertStringContainsString( '<h3 id="sub-point-alpha">Sub-point Alpha</h3>', $filtered );
	}

	public function testBlockSchemasAutomaticallyIntegratedInSchemaGenerator(): void {
		global $mock_posts, $mock_post_meta;
		$postId = 504;

		// Post containing a serialized Gutenberg FAQ block
		$blockContent = '<!-- wp:quinrel/faq {"title":"Gutenberg FAQ","items":[{"question":"Is it automated?","answer":"Yes completely."}]} /-->';

		$mock_posts[ $postId ] = new \WP_Post( array(
			'ID'           => $postId,
			'post_title'   => 'Article With Gutenberg Blocks',
			'post_content' => $blockContent,
			'post_type'    => 'post',
		) );
		$mock_post_meta[ $postId ]['_quinrel_primary_schema'] = 'article';

		$generator = new SchemaGenerator();
		$graph     = $generator->getSchemaForPost( $postId );

		$this->assertNotEmpty( $graph );
		$types = array_column( $graph['@graph'], '@type' );
		$this->assertContains( 'FAQPage', $types );
	}
}

