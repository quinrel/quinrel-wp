<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Modules\Api\HeadlessSeoEndpoints;
use Quinrel\WordPress\Modules\Schema\SchemaGenerator;
use Quinrel\WordPress\Modules\Schema\SchemaModule;
use PHPUnit\Framework\TestCase;

class PublicSeoPayloadTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		global $mock_posts, $mock_post_meta, $mock_user_caps, $mock_transients;
		$mock_posts       = array();
		$mock_post_meta   = array();
		$mock_user_caps   = null;
		$mock_transients  = array();
	}

	public function testAnonymousPostSeoOmitsEditorialFields(): void {
		global $mock_posts, $mock_post_meta;

		$mock_posts[42] = new \WP_Post(
			array(
				'ID'            => 42,
				'post_title'    => 'Public title',
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_content'  => '<p>Visible copy.</p>',
				'post_modified' => '2026-02-01 00:00:00',
			)
		);
		$mock_post_meta[42] = array(
			'_quinrel_meta_title'       => 'Search title',
			'_quinrel_meta_description' => 'Search description',
			'_quinrel_focus_keyword'    => 'secret keyword',
			'_quinrel_search_intent'    => 'transactional',
			'_quinrel_is_cornerstone'   => 'yes',
			'_quinrel_ai_generated'     => 'yes',
			'_quinrel_schema_type'      => 'faq',
			'_quinrel_canonical_url'    => 'https://example.com/public/',
		);

		$request = new \WP_REST_Request();
		$request->set_param( 'post_id', 42 );

		$response = ( new HeadlessSeoEndpoints() )->getPostSeo( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertSame( 'Search title', $data['meta_title'] );
		$this->assertSame( 'Search description', $data['meta_description'] );
		$this->assertSame( 'https://example.com/public/', $data['canonical_url'] );
		$this->assertArrayHasKey( 'robots', $data );
		$this->assertArrayHasKey( 'open_graph', $data );
		$this->assertArrayHasKey( 'structured_data', $data );

		foreach ( array( 'focus_keyword', 'search_intent', 'is_cornerstone', 'ai_generated', 'schema_type' ) as $hidden ) {
			$this->assertArrayNotHasKey( $hidden, $data );
		}
		$this->assertStringNotContainsString( 'secret keyword', wp_json_encode( $data ) );
		$this->assertStringNotContainsString( 'transactional', wp_json_encode( $data ) );
	}

	public function testAnonymousPostSeoHidesUnpublishedPosts(): void {
		global $mock_posts;

		$mock_posts[7] = new \WP_Post(
			array(
				'ID'          => 7,
				'post_status' => 'draft',
				'post_title'  => 'Draft',
			)
		);

		$request = new \WP_REST_Request();
		$request->set_param( 'post_id', 7 );
		$result = ( new HeadlessSeoEndpoints() )->getPostSeo( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 404, $result->data['status'] );
	}

	public function testPutStillRequiresEditPost(): void {
		global $mock_posts, $mock_user_caps;

		$mock_posts[9] = new \WP_Post(
			array(
				'ID'          => 9,
				'post_status' => 'draft',
				'post_title'  => 'Draft',
			)
		);
		$mock_user_caps = array( 'edit_post' => array() );

		$request = new \WP_REST_Request();
		$request->set_param( 'post_id', 9 );
		$request->set_json_params( array( 'meta_title' => 'Changed' ) );

		$result = ( new HeadlessSeoEndpoints() )->updatePostSeo( $request );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'forbidden', $result->get_error_code() );
	}

	public function testSchemaMapRejectsInheritedAndDraftPosts(): void {
		global $mock_posts;

		$module = new SchemaModule( new EventManager(), new SchemaGenerator() );

		foreach ( array( 'inherit', 'draft', 'private' ) as $index => $status ) {
			$postId                 = 100 + $index;
			$mock_posts[ $postId ]  = new \WP_Post(
				array(
					'ID'          => $postId,
					'post_status' => $status,
					'post_title'  => 'Hidden',
				)
			);
			$request = new \WP_REST_Request();
			$request->set_param( 'post_id', $postId );
			$result = $module->handleSchemaMap( $request );
			$this->assertInstanceOf( \WP_Error::class, $result, $status );
			$this->assertSame( 404, $result->data['status'] );
		}
	}

	public function testSchemaMapCacheVersionBumpsAfterARealSave(): void {
		$module = new SchemaModule( new EventManager(), new SchemaGenerator() );
		update_option( 'quinrel_schemamap_version', 3 );

		$module->bumpSchemaMapCache( 15 );

		$this->assertSame( 4, get_option( 'quinrel_schemamap_version' ) );
	}

	public function testBulkMetaEditorAndHeadlessEndpointsUseCanonicalMetaKeys(): void {
		global $mock_posts;

		$mock_posts[51] = new \WP_Post(
			array(
				'ID'          => 51,
				'post_status' => 'publish',
				'post_title'  => 'Canonical Meta Test',
			)
		);

		$bulkEditor  = new \Quinrel\WordPress\Modules\Admin\BulkMetaEditor();
		$saveRequest = new \WP_REST_Request();
		$saveRequest->set_json_params(
			array(
				'updates' => array(
					array(
						'id'        => 51,
						'seo_title' => 'Bulk Edited Title',
						'seo_desc'  => 'Bulk Edited Description',
						'noindex'   => true,
					),
				),
			)
		);

		$saveResp = $bulkEditor->saveMeta( $saveRequest );
		$this->assertInstanceOf( \WP_REST_Response::class, $saveResp );
		$this->assertSame( 'Bulk Edited Title', get_post_meta( 51, '_quinrel_meta_title', true ) );
		$this->assertSame( 'Bulk Edited Description', get_post_meta( 51, '_quinrel_meta_description', true ) );
		$this->assertSame( 'yes', get_post_meta( 51, '_quinrel_noindex', true ) );

		$headlessRequest = new \WP_REST_Request();
		$headlessRequest->set_param( 'post_id', 51 );
		$headlessRequest->set_json_params( array( 'schema_type' => 'faq' ) );

		$headlessResp = ( new HeadlessSeoEndpoints() )->updatePostSeo( $headlessRequest );
		$this->assertInstanceOf( \WP_REST_Response::class, $headlessResp );
		$this->assertSame( 'faq', get_post_meta( 51, '_quinrel_primary_schema', true ) );
	}

	public function testUnifiedSeoScoreCalculatorMatchesAcrossColumnInspectorAndBulkEditor(): void {
		global $mock_posts;

		$mock_posts[52] = new \WP_Post(
			array(
				'ID'           => 52,
				'post_status'  => 'publish',
				'post_title'   => 'Quinrel Performance Guide',
				'post_content' => '<h2>Quinrel Performance</h2><p>Quinrel performance optimization keeps queries fast. <img src="https://example.com/a.jpg" alt="Quinrel performance chart" /></p>',
			)
		);
		update_post_meta( 52, '_quinrel_focus_keyword', 'quinrel performance' );
		update_post_meta( 52, '_quinrel_meta_title', '%%title%% - %%sitename%%' );
		update_post_meta( 52, '_quinrel_meta_description', str_repeat( 'Quinrel performance guide for fast WordPress SEO. ', 3 ) );

		$calculated = \Quinrel\WordPress\Modules\Seo\SeoScoreCalculator::calculateForPost( 52, false );
		$this->assertGreaterThan( 70, $calculated );

		$audit = ( new \Quinrel\WordPress\Modules\Admin\FrontendSeoInspector() )->auditPost( 52 );
		$this->assertSame( $calculated, $audit['summary']['score'] );

		ob_start();
		( new \Quinrel\WordPress\Modules\Admin\SeoScoreColumn() )->renderColumn( 'quinrel_seo', 52 );
		$columnHtml = (string) ob_get_clean();
		$this->assertStringContainsString( (string) $calculated . '/100', $columnHtml );
	}
}
