<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\ContentAssistant\OutreachManager;

class OutreachManagerTest extends TestCase
{
    private OutreachManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new OutreachManager();

        global $mock_post_meta, $mock_posts, $wpdb, $mock_table_rows;
        $mock_post_meta = [];
        $mock_posts = [];
        $mock_table_rows = [];
        $wpdb->insert_id = 0;

        $GLOBALS['mock_wpdb_insert'] = function ($table, $data) use (&$mock_table_rows, $wpdb) {
            $data['id'] = $wpdb->insert_id;
            $mock_table_rows[$table][] = $data;
            return 1;
        };

        $GLOBALS['mock_wpdb_results'] = function ($query) use (&$mock_table_rows, $wpdb) {
            if (str_contains($query, 'quinrel_outreach_prospects')) {
                return $mock_table_rows[$wpdb->prefix . 'quinrel_outreach_prospects'] ?? [];
            }
            if (str_contains($query, 'quinrel_links')) {
                return [
                    ['anchor_text' => 'best crm software', 'link_count' => 3],
                    ['anchor_text' => 'CRM software options', 'link_count' => 1],
                ];
            }
            return [];
        };
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mock_wpdb_insert'], $GLOBALS['mock_wpdb_results']);
        parent::tearDown();
    }

    public function testSaveProspectValidationAndInsert(): void
    {
        $invalid = $this->manager->saveProspect([]);
        $this->assertTrue(is_wp_error($invalid));
        $this->assertSame('missing_domain', $invalid->get_error_code());

        $id = $this->manager->saveProspect([
            'domain' => 'techcrunch.com',
            'target_url' => 'https://example.com/ai-seo-guide',
            'contact_name' => 'Alex Editor',
            'contact_email' => 'alex@techcrunch.com',
            'pitch_type' => 'guest_post',
            'stage' => 'prospect',
            'notes' => 'Pitching an AI search architecture piece.',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $prospects = $this->manager->getProspects(['stage' => 'prospect']);
        $this->assertNotEmpty($prospects);
        $this->assertSame('techcrunch.com', $prospects[0]['domain']);
        $this->assertSame('alex@techcrunch.com', $prospects[0]['contact_email']);
    }

    public function testExportCsv(): void
    {
        $this->manager->saveProspect([
            'domain' => 'searchengineland.com',
            'target_url' => 'https://example.com/schema-handbook',
            'contact_name' => 'SEO Editor',
            'pitch_type' => 'unlinked_mention',
            'stage' => 'contacted',
        ]);

        $csv = $this->manager->exportCsv();
        $this->assertStringContainsString('Domain', $csv);
        $this->assertStringContainsString('searchengineland.com', $csv);
    }

    public function testAnalyzeAnchorDiversityFlagsOverOptimization(): void
    {
        global $mock_posts, $mock_post_meta, $wpdb;

        $postId = 950;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Best CRM Software 2026',
            'post_type' => 'post',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_focus_keyword' => 'best crm software',
        ];

        // Simulate 4 internal links, all using exact match anchor text
        $table = $wpdb->prefix . 'quinrel_links';
        $permalink = get_permalink($postId);

        // Prepopulate wpdb with rows for quinrel_links
        $wpdb->insert($table, ['post_id' => 10, 'target_url' => $permalink, 'anchor_text' => 'best crm software']);
        $wpdb->insert($table, ['post_id' => 11, 'target_url' => $permalink, 'anchor_text' => 'best crm software']);
        $wpdb->insert($table, ['post_id' => 12, 'target_url' => $permalink, 'anchor_text' => 'best crm software']);
        $wpdb->insert($table, ['post_id' => 13, 'target_url' => $permalink, 'anchor_text' => 'CRM software options']);

        $analysis = $this->manager->analyzeAnchorDiversity($postId);

        $this->assertSame($postId, $analysis['post_id']);
        $this->assertSame('over_optimized', $analysis['status']);
        $this->assertNotEmpty($analysis['warnings']);
        $this->assertStringContainsString('over-optimization', $analysis['warnings'][0]);
    }
}
