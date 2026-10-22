<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\ContentAssistant\CustomFieldScanner;
use Quinrel\WordPress\Modules\ContentAssistant\WordComplexityScorer;
use Quinrel\WordPress\Modules\ContentAssistant\MorphologicalKeywordMatcher;

class CustomFieldScannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $mock_post_meta, $mock_posts, $mock_wp_options;
        $mock_post_meta = [];
        $mock_posts = [];
        $mock_wp_options = [];
    }

    public function testDiscoverFieldsIgnoresPrivateMeta(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 801;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Case Study: Quantum Computing',
            'post_content' => 'Base intro.',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_edit_lock' => '12345:1',
            '_quinrel_meta_title' => 'Private Quinrel Title',
            'case_study_summary' => 'This is an extensive analysis of quantum supremacy.',
            'client_industry' => 'Aerospace & Defense',
        ];

        $discovered = CustomFieldScanner::discoverFields($postId);

        $this->assertIsArray($discovered);
        $names = array_column($discovered, 'name');
        $this->assertContains('case_study_summary', $names);
        $this->assertContains('client_industry', $names);
        $this->assertNotContains('_edit_lock', $names);
        $this->assertNotContains('_quinrel_meta_title', $names);
    }

    public function testGetAndSetConfiguredSeoFields(): void
    {
        $fields = ['case_study_summary', 'client_industry', 'invalid key!'];
        CustomFieldScanner::setConfiguredSeoFields($fields);

        $saved = CustomFieldScanner::getConfiguredSeoFields();
        $this->assertContains('case_study_summary', $saved);
        $this->assertContains('client_industry', $saved);
        $this->assertContains('invalidkey', $saved); // sanitized
    }

    public function testGetAggregatedContentCombinesBaseAndCustomFields(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 802;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Product Deep Dive',
            'post_content' => 'Overview paragraph.',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            'extended_features' => 'Feature 1: AI Auto-indexing. Feature 2: High throughput.',
            'custom_specs' => '10Gbps bandwidth with redundant power supplies.',
        ];

        CustomFieldScanner::setConfiguredSeoFields(['extended_features', 'custom_specs']);

        $aggregated = CustomFieldScanner::getAggregatedContent($postId);

        $this->assertStringContainsString('Overview paragraph.', $aggregated);
        $this->assertStringContainsString('Feature 1: AI Auto-indexing.', $aggregated);
        $this->assertStringContainsString('10Gbps bandwidth with redundant power supplies.', $aggregated);
    }

    public function testFlattenValueRecursion(): void
    {
        $nestedAcfValue = [
            'section_title' => 'Architecture Overview',
            'blocks' => [
                ['paragraph' => 'First block describing the multi-tenant database.'],
                ['paragraph' => 'Second block describing the vector indexing service.'],
            ],
            'tags' => ['Kubernetes', 'PostgreSQL', 'Docker'],
        ];

        $flattened = CustomFieldScanner::flattenValue($nestedAcfValue);

        $this->assertStringContainsString('Architecture Overview', $flattened);
        $this->assertStringContainsString('First block describing the multi-tenant database.', $flattened);
        $this->assertStringContainsString('Second block describing the vector indexing service.', $flattened);
        $this->assertStringContainsString('Kubernetes PostgreSQL Docker', $flattened);
    }

    public function testContentAnalysisConsidersCustomFields(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 803;
        // Post content itself has 0 occurrences of "vector database"
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Modern Database Architecture',
            'post_content' => 'Introduction to modern search systems.',
            'post_type' => 'post',
        ]);

        // Custom field holds the actual content with the focus keyword
        $mock_post_meta[$postId] = [
            'acf_body_content' => 'High-dimensional semantic search enables intelligent discovery. Advanced caching is critical for AI infrastructure.',
            '_quinrel_focus_keyword' => 'discovery',
        ];

        CustomFieldScanner::setConfiguredSeoFields(['acf_body_content']);

        $matcher = new MorphologicalKeywordMatcher();
        $request = new \WP_REST_Request('POST', '/quinrel/v1/content/keyword-density');
        $request->set_json_params(['post_id' => $postId]);

        $response = $matcher->analyseKeywordDensity($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertGreaterThan(0, $data['total_matches']);
        $this->assertSame('discovery', $data['keyword']);
    }
}
