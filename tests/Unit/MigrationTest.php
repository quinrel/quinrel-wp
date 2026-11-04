<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Migration\MigrationManager;

class MigrationTest extends TestCase
{
    public function testCompetitorPluginDetection(): void
    {
        $manager = new MigrationManager();
        $detected = $manager->detectPlugins();

        $this->assertIsArray($detected);
    }

    public function testUnknownPluginMigrationReturnsError(): void
    {
        $manager = new MigrationManager();
        $result = $manager->migrate('nonexistent_plugin');

        $this->assertFalse($result['success']);
        $this->assertSame('Unknown plugin.', $result['message']);
    }

    public function testPreviewCountsAndDoesNotWrite(): void
    {
        $GLOBALS['mock_post_meta'] = array();
        $GLOBALS['mock_wpdb_var'] = function (string $query) {
            if (str_contains($query, 'INNER JOIN')) {
                return 1;
            }
            return 2;
        };

        $result = (new MigrationManager())->handleMigrate($this->migrationRequest('yoast', false))->get_data();

        $this->assertFalse($result['wrote']);
        $this->assertSame(18, $result['fields']);
        $this->assertSame(9, $result['copy']);
        $this->assertSame(9, $result['skipped']);
        $this->assertStringContainsString('Deactivate Yoast SEO', $result['message']);
        $this->assertSame(array(), $GLOBALS['mock_post_meta']);
        unset($GLOBALS['mock_wpdb_var']);
    }

    public function testConfirmedImportSkipsExistingQuinrelMeta(): void
    {
        $GLOBALS['mock_post_meta'] = array(
            7 => array('_quinrel_meta_title' => 'Keep me'),
        );
        $GLOBALS['mock_wpdb_results'] = function (string $query) {
            if (!str_contains($query, '_yoast_wpseo_title')) {
                return array();
            }
            return array(
                (object) array('post_id' => 7, 'meta_value' => 'Replacement title'),
            );
        };

        $result = (new MigrationManager())->handleMigrate($this->migrationRequest('yoast', true))->get_data();

        $this->assertTrue($result['wrote']);
        $this->assertSame(50, $result['batch_size']);
        $this->assertSame('Keep me', get_post_meta(7, '_quinrel_meta_title', true));
        $this->assertGreaterThan(0, $result['skipped']);
        $this->assertStringContainsString('Deactivate Yoast SEO', $result['message']);
        unset($GLOBALS['mock_wpdb_results']);
    }

    public function testRankMathRobotsAndFocusKeywordShapes(): void
    {
        $GLOBALS['mock_post_meta'] = array();
        $robots = serialize(array('noindex', 'nofollow'));
        $GLOBALS['mock_wpdb_results'] = function (string $query) use ($robots) {
            if (str_contains($query, 'rank_math_robots')) {
                return array(
                    (object) array('post_id' => 8, 'meta_value' => $robots),
                );
            }
            if (str_contains($query, 'rank_math_focus_keyword')) {
                return array(
                    (object) array('post_id' => 8, 'meta_value' => 'alpha, beta'),
                );
            }
            return array();
        };

        (new MigrationManager())->migrate('rankmath');

        $this->assertSame('yes', get_post_meta(8, '_quinrel_noindex', true));
        $this->assertSame('yes', get_post_meta(8, '_quinrel_nofollow', true));
        $this->assertSame('alpha', get_post_meta(8, '_quinrel_focus_keyword', true));
        unset($GLOBALS['mock_wpdb_results']);
    }

    public function testReadmeDescribesTheImportPreview(): void
    {
        $sentence = 'The import shows how many fields it will copy and does not write until you confirm.';
        $root = dirname(__DIR__, 2);
        $this->assertStringContainsString($sentence, (string) file_get_contents($root . '/readme.txt'));
        $this->assertStringContainsString($sentence, (string) file_get_contents($root . '/docs/features.md'));
        $this->assertStringNotContainsString('The import does not show a preview.', (string) file_get_contents($root . '/readme.txt'));
    }

    public function testMigratesAndResolvesCompetitorSmartTagsCleanly(): void
    {
        global $mock_posts;
        $mock_posts[21] = new \WP_Post([
            'ID' => 21,
            'post_title' => 'Migrated SEO Post',
            'post_excerpt' => 'Migrated post summary.',
        ]);
        $GLOBALS['mock_post_meta'] = array();
        $GLOBALS['mock_wpdb_results'] = function (string $query) {
            if (str_contains($query, '_yoast_wpseo_title')) {
                return array(
                    (object) array('post_id' => 21, 'meta_value' => '%%title%% %%page%% %%sep%% %%sitename%%'),
                );
            }
            if (str_contains($query, '_yoast_wpseo_metadesc')) {
                return array(
                    (object) array('post_id' => 21, 'meta_value' => '%%excerpt%% - %%sitename%%'),
                );
            }
            return array();
        };

        (new MigrationManager())->migrate('yoast');

        $this->assertSame('Migrated SEO Post - Quinrel Test Site', get_post_meta(21, '_quinrel_meta_title', true));
        $this->assertSame('Migrated post summary. - Quinrel Test Site', get_post_meta(21, '_quinrel_meta_description', true));
        unset($GLOBALS['mock_wpdb_results']);
    }

    public function testPreviewReturnsResolvedSamplesAndDeactivateEndpointWorks(): void
    {
        $GLOBALS['mock_posts'][30] = new \WP_Post(
            array(
                'ID' => 30,
                'post_status' => 'publish',
                'post_title' => 'Sample Article',
            )
        );
        $GLOBALS['mock_post_meta'][30] = array(
            '_yoast_wpseo_title' => '%%title%% | %%sitename%%',
            '_yoast_wpseo_metadesc' => 'Sample meta description for article.',
        );
        $GLOBALS['mock_wpdb_col'] = function (string $query) {
            return array(30);
        };

        $manager = new MigrationManager();
        $preview = $manager->preview('yoast');

        $this->assertTrue($preview['success']);
        $this->assertArrayHasKey('samples', $preview);
        $this->assertNotEmpty($preview['samples']);
        $this->assertSame('Sample Article | Quinrel Test Site', $preview['samples'][0]['resolved_title']);

        $req = new \WP_REST_Request();
        $req->set_param('plugin_id', 'yoast');
        $deactivateResp = $manager->handleDeactivateCompetitor($req);
        $this->assertTrue($deactivateResp->get_data()['success']);
        $this->assertStringContainsString('Yoast SEO has been safely deactivated.', $deactivateResp->get_data()['message']);

        unset($GLOBALS['mock_wpdb_col']);
    }

    public function testSeoPressImportCopiesTheFirstKeywordAndRestrictiveFlagsOnly(): void
    {
        $GLOBALS['mock_posts'][41] = new \WP_Post(array(
            'ID' => 41,
            'post_title' => 'Imported Post',
        ));
        $GLOBALS['mock_post_meta'] = array(
            41 => array('_quinrel_meta_title' => 'Keep me'),
        );
        $GLOBALS['mock_wpdb_results'] = function (string $query) {
            if (str_contains($query, '_seopress_titles_title')) {
                return array((object) array('post_id' => 41, 'meta_value' => 'Replacement title'));
            }
            if (str_contains($query, '_seopress_titles_desc')) {
                return array((object) array('post_id' => 41, 'meta_value' => '%%post_title%% %%sep%% %%sitetitle%%'));
            }
            if (str_contains($query, '_seopress_analysis_target_kw')) {
                return array((object) array('post_id' => 41, 'meta_value' => 'alpha, beta'));
            }
            if (str_contains($query, '_seopress_robots_index')) {
                return array((object) array('post_id' => 41, 'meta_value' => 'yes'));
            }
            if (str_contains($query, '_seopress_robots_follow')) {
                return array((object) array('post_id' => 41, 'meta_value' => 'no'));
            }
            if (str_contains($query, '_seopress_robots_canonical')) {
                return array((object) array('post_id' => 41, 'meta_value' => 'https://example.com/from-seopress'));
            }
            return array();
        };

        $result = (new MigrationManager())->migrate('seopress');

        $this->assertTrue($result['success']);
        $this->assertSame('Keep me', get_post_meta(41, '_quinrel_meta_title', true));
        $this->assertSame('Imported Post - Quinrel Test Site', get_post_meta(41, '_quinrel_meta_description', true));
        $this->assertSame('alpha', get_post_meta(41, '_quinrel_focus_keyword', true));
        $this->assertSame('yes', get_post_meta(41, '_quinrel_noindex', true));
        $this->assertSame('', get_post_meta(41, '_quinrel_nofollow', true));
        $this->assertSame('https://example.com/from-seopress', get_post_meta(41, '_quinrel_canonical_url', true));
        $this->assertStringContainsString('Deactivate SEOPress', $result['message']);
        unset($GLOBALS['mock_wpdb_results']);
    }

    public function testTheSeoFrameworkImportWritesNoindexOnlyWhenSet(): void
    {
        $GLOBALS['mock_post_meta'] = array();
        $GLOBALS['mock_wpdb_results'] = function (string $query) {
            if (str_contains($query, '_genesis_title')) {
                return array((object) array('post_id' => 42, 'meta_value' => 'Framework title'));
            }
            if (str_contains($query, '_genesis_noindex')) {
                return array((object) array('post_id' => 42, 'meta_value' => '1'));
            }
            if (str_contains($query, '_genesis_nofollow')) {
                return array((object) array('post_id' => 42, 'meta_value' => '0'));
            }
            return array();
        };

        $result = (new MigrationManager())->migrate('tsf');

        $this->assertSame('Framework title', get_post_meta(42, '_quinrel_meta_title', true));
        $this->assertSame('yes', get_post_meta(42, '_quinrel_noindex', true));
        $this->assertSame('', get_post_meta(42, '_quinrel_nofollow', true));
        $this->assertSame('', get_post_meta(42, '_quinrel_focus_keyword', true));
        $this->assertStringContainsString('Deactivate The SEO Framework', $result['message']);
        unset($GLOBALS['mock_wpdb_results']);
    }

    public function testSeoPressAndTheSeoFrameworkAreDetectedAndCanBeDeactivated(): void
    {
        $GLOBALS['mock_wpdb_var'] = function (string $query) {
            if (str_contains($query, '_seopress_titles_title')) {
                return 3;
            }
            if (str_contains($query, '_genesis_title')) {
                return 4;
            }
            return 0;
        };

        $detected = (new MigrationManager())->detectPlugins();
        $byId = array();
        foreach ($detected as $row) {
            $byId[$row['id']] = $row;
        }
        $this->assertSame('SEOPress', $byId['seopress']['name']);
        $this->assertSame(3, $byId['seopress']['posts']);
        $this->assertSame('The SEO Framework', $byId['tsf']['name']);
        $this->assertSame(4, $byId['tsf']['posts']);
        $this->assertArrayNotHasKey('yoast', $byId);

        $request = new \WP_REST_Request();
        $request->set_param('plugin_id', 'tsf');
        $response = (new MigrationManager())->handleDeactivateCompetitor($request);
        $this->assertStringContainsString('The SEO Framework has been safely deactivated.', $response->get_data()['message']);
        unset($GLOBALS['mock_wpdb_var']);
    }

    private function migrationRequest(string $pluginId, bool $confirm): \WP_REST_Request
    {
        $request = new \WP_REST_Request();
        $request->set_json_params(array(
            'plugin_id' => $pluginId,
            'confirm' => $confirm,
        ));
        return $request;
    }
}
