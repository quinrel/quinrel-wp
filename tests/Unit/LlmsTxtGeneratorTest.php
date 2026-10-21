<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Ai\LlmsTxtGenerator;

class LlmsTxtGeneratorTest extends TestCase {

	private LlmsTxtGenerator $generator;

	protected function setUp(): void {
		parent::setUp();
		$this->generator = new LlmsTxtGenerator();

		global $mock_wp_options, $mock_transients;
		$mock_wp_options  = array();
		$mock_transients  = array();
	}

	public function testBuildContentGeneratesSpecCompliantMarkdown(): void {
		$config = array(
			'description'    => 'Official test site for AI discovery',
			'allow_index'    => true,
			'allow_training' => false,
			'contact_url'    => 'https://example.com/contact',
			'extra_notes'    => 'All content copyright 2026.',
		);

		$output = $this->generator->buildContent( $config );

		$this->assertStringContainsString( '# Quinrel Test Site', $output );
		$this->assertStringContainsString( '> Official test site for AI discovery', $output );
		$this->assertStringContainsString( '## Site', $output );
		$this->assertStringContainsString( '## Permissions', $output );
		$this->assertStringContainsString( 'Indexing: Allowed', $output );
		$this->assertStringContainsString( 'Training data use: Not Allowed', $output );
		$this->assertStringContainsString( '## Structured Data', $output );
		$this->assertStringContainsString( 'Schema Map: https://example.com/wp-json/quinrel/v1/schemamap', $output );
		$this->assertStringContainsString( 'Full Digest: https://example.com/llms-full.txt', $output );
		$this->assertStringContainsString( '## Notes', $output );
		$this->assertStringContainsString( 'All content copyright 2026.', $output );
	}

	public function testBuildFullContentGeneratesMarkdownDigest(): void {
		$config = array(
			'description' => 'Comprehensive knowledge base',
		);

		$output = $this->generator->buildFullContent( $config );

		$this->assertStringContainsString( 'Full Content Digest', $output );
		$this->assertStringContainsString( 'Canonical Site URL: https://example.com', $output );
	}

	public function testGetConfigReturnsPreviewAndOptions(): void {
		update_option(
			'quinrel_llms_txt_config',
			array(
				'description'    => 'Custom Description',
				'allow_index'    => false,
				'allow_training' => true,
				'contact_url'    => 'mailto:team@example.com',
				'extra_notes'    => 'Test notes',
			)
		);

		$response = $this->generator->getConfig();
		$data     = $response->get_data();

		$this->assertSame( 'Custom Description', $data['description'] );
		$this->assertFalse( $data['allow_index'] );
		$this->assertTrue( $data['allow_training'] );
		$this->assertNotEmpty( $data['preview'] );
		$this->assertStringContainsString( 'Training data use: Allowed', $data['preview'] );
	}

	public function testSaveConfigUpdatesOptionAndInvalidatesCache(): void {
		set_transient( 'quinrel_llms_txt_content', 'stale cached content', 3600 );
		set_transient( 'quinrel_llms_full_txt_content', 'stale full content', 3600 );

		$request = new \WP_REST_Request( 'POST', '/quinrel/v1/llms-txt/config' );
		$request->set_json_params(
			array(
				'description'    => 'Newly Saved Description',
				'allow_index'    => true,
				'allow_training' => false,
				'contact_url'    => 'https://example.com/contact-us',
				'extra_notes'    => 'Updated notes',
			)
		);

		$response = $this->generator->saveConfig( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertSame( 'Newly Saved Description', $data['config']['description'] );
		$this->assertFalse( get_transient( 'quinrel_llms_txt_content' ) );
		$this->assertFalse( get_transient( 'quinrel_llms_full_txt_content' ) );
	}

	public function testPreviewContentEndpointReturnsJsonWithContent(): void {
		$request = new \WP_REST_Request( 'GET', '/quinrel/v1/ai/llms-preview' );
		$response = $this->generator->previewContent( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'content', $data );
		$this->assertStringContainsString( '## Site', $data['content'] );

		$fullRequest = new \WP_REST_Request( 'GET', '/quinrel/v1/ai/llms-preview' );
		$fullRequest->set_param( 'type', 'full' );
		$fullResponse = $this->generator->previewContent( $fullRequest );
		$fullData     = $fullResponse->get_data();

		$this->assertArrayHasKey( 'content', $fullData );
		$this->assertStringContainsString( 'Full Content Digest', $fullData['content'] );
	}
}
