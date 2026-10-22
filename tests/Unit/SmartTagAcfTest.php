<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Seo\SmartTagResolver;
use Quinrel\WordPress\Modules\Schema\SchemaGenerator;

class SmartTagAcfTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $mock_post_meta, $mock_posts, $mock_options;
		$mock_post_meta = array();
		$mock_posts     = array();
		$mock_options   = array(
			'blogname'                => 'Acme Testing',
			'quinrel_title_separator' => '|',
		);
	}

	public function testResolvesAcfSmartTagInTitle(): void {
		global $mock_posts, $mock_post_meta;
		$postId = 789;
		$mock_posts[ $postId ] = new \WP_Post( array(
			'ID'         => $postId,
			'post_title' => 'Sample Guide',
			'post_type'  => 'post',
		) );
		$mock_post_meta[ $postId ]['subtitle'] = 'The Definitive Architecture Manual';

		$template = '%%title%% - %%acf:subtitle%% | %%sitename%%';
		$resolved = SmartTagResolver::resolve( $template, $postId );

		$this->assertStringContainsString( 'The Definitive Architecture Manual', $resolved );
		$this->assertStringNotContainsString( '%%acf:subtitle%%', $resolved );
	}

	public function testResolvesDoubleCurlyBraceAcfSmartTag(): void {
		global $mock_posts, $mock_post_meta;
		$postId = 790;
		$mock_posts[ $postId ] = new \WP_Post( array(
			'ID'         => $postId,
			'post_title' => 'Framework Overview',
			'post_type'  => 'post',
		) );
		$mock_post_meta[ $postId ]['custom_intro'] = 'Specialized Framework Intro';

		$template = '{{title}} - {{acf:custom_intro}} | {{sitename}}';
		$resolved = SmartTagResolver::resolve( $template, $postId );

		$this->assertStringContainsString( 'Specialized Framework Intro', $resolved );
		$this->assertStringNotContainsString( '{{acf:custom_intro}}', $resolved );
	}

	public function testResolvesCustomFieldFallbackWhenEmpty(): void {
		global $mock_posts;
		$postId = 791;
		$mock_posts[ $postId ] = new \WP_Post( array(
			'ID'         => $postId,
			'post_title' => 'Article Title',
			'post_type'  => 'post',
		) );
		$template = '%%title%% - %%acf:nonexistent_field%% | %%sitename%%';
		$resolved = SmartTagResolver::resolve( $template, $postId );

		$this->assertStringNotContainsString( '%%acf:nonexistent_field%%', $resolved );
		// Ensure extra separators are cleanly stripped
		$this->assertStringNotContainsString( '| |', $resolved );
	}

	public function testResolvesDynamicAcfBindingsInSchemaGenerator(): void {
		global $mock_posts, $mock_post_meta;
		$postId = 792;
		$mock_posts[ $postId ] = new \WP_Post( array(
			'ID'         => $postId,
			'post_title' => 'Tech Summit 2026',
			'post_type'  => 'post',
		) );

		$mock_post_meta[ $postId ] = array(
			'_quinrel_primary_schema'           => 'custom',
			'_quinrel_custom_schema_properties' => wp_json_encode( array(
				'@type'                   => 'Event',
				'name'                    => 'Tech Summit 2026',
				'location'                => array(
					'@type' => 'Place',
					'name'  => 'acf:event_venue',
				),
				'offers'                  => array(
					'@type' => 'Offer',
					'price' => 'acf:ticket_price',
				),
				'maximumAttendeeCapacity' => 'acf:capacity_count',
			) ),
			'event_venue'                       => 'Metropolitan Convention Center',
			'ticket_price'                      => '89.50',
			'capacity_count'                    => '1200',
		);

		$generator = new SchemaGenerator();
		$graph     = $generator->getSchemaForPost( $postId );

		$this->assertNotEmpty( $graph );
		$this->assertArrayHasKey( '@graph', $graph );

		$customNode = null;
		foreach ( $graph['@graph'] as $node ) {
			if ( ( $node['@type'] ?? '' ) === 'Event' ) {
				$customNode = $node;
				break;
			}
		}

		$this->assertNotNull( $customNode );
		$this->assertSame( 'Metropolitan Convention Center', $customNode['location']['name'] );
		$this->assertSame( 89.5, $customNode['offers']['price'] );
		$this->assertSame( 1200, $customNode['maximumAttendeeCapacity'] );
	}
}
