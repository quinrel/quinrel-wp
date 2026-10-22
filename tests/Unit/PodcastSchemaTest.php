<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Schema\AudioExtractor;
use Quinrel\WordPress\Modules\Schema\SchemaGenerator;
use Quinrel\WordPress\Modules\Schema\Types\CustomSchema;

class PodcastSchemaTest extends TestCase
{
    private CustomSchema $customSchema;
    private SchemaGenerator $schemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customSchema = new CustomSchema();
        $this->schemaGenerator = new SchemaGenerator();

        global $mock_post_meta, $mock_posts, $mock_current_post_id;
        $mock_post_meta = [];
        $mock_posts = [];
        $mock_current_post_id = 0;
    }

    public function testPodcastSchemaManualMetadata(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 501;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Episode 42: The Future of AI Agents',
            'post_content' => 'In this episode we discuss autonomous agent swarms.',
            'post_type' => 'post',
            'post_date' => '2026-10-22 10:00:00',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'podcast',
            '_quinrel_podcast_title' => 'Episode 42: The Future of AI Agents',
            '_quinrel_podcast_description' => 'A deep dive into multi-agent systems and SEO.',
            '_quinrel_podcast_episode_number' => '42',
            '_quinrel_podcast_season_number' => '3',
            '_quinrel_podcast_episode_type' => 'full',
            '_quinrel_podcast_series_name' => 'The AI Architecture Show',
            '_quinrel_podcast_series_url' => 'https://example.com/podcast',
            '_quinrel_podcast_audio_url' => 'https://example.com/audio/ep42.mp3',
            '_quinrel_podcast_duration' => '45 min',
            '_quinrel_podcast_transcript' => 'https://example.com/transcripts/ep42.txt',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertIsArray($schema);
        $this->assertSame('PodcastEpisode', $schema['@type']);
        $this->assertSame('Episode 42: The Future of AI Agents', $schema['name']);
        $this->assertSame(42, $schema['episodeNumber']);
        $this->assertSame(3, $schema['seasonNumber']);
        $this->assertSame('full', $schema['episodeType']);
        $this->assertSame('PT45M', $schema['timeRequired']);

        // Check series
        $this->assertArrayHasKey('partOfSeries', $schema);
        $this->assertSame('PodcastSeries', $schema['partOfSeries']['@type']);
        $this->assertSame('The AI Architecture Show', $schema['partOfSeries']['name']);
        $this->assertSame('https://example.com/podcast', $schema['partOfSeries']['url']);

        // Check associated media
        $this->assertArrayHasKey('associatedMedia', $schema);
        $audio = $schema['associatedMedia'];
        $this->assertSame('AudioObject', $audio['@type']);
        $this->assertSame('https://example.com/audio/ep42.mp3', $audio['contentUrl']);
        $this->assertSame('audio/mpeg', $audio['encodingFormat']);
        $this->assertSame('PT45M', $audio['duration']);
        $this->assertSame('https://example.com/transcripts/ep42.txt', $audio['transcript']);
    }

    public function testPodcastSchemaAutoExtractsFromGutenbergAudioBlock(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 502;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Interview with Tech Leaders',
            'post_content' => '<!-- wp:paragraph --><p>Welcome to the show.</p><!-- /wp:paragraph -->' .
                              '<!-- wp:audio --><figure class="wp-block-audio"><audio controls src="https://example.com/media/interview.m4a"></audio></figure><!-- /wp:audio -->',
            'post_type' => 'post',
            'post_date' => '2026-10-22 10:00:00',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'podcastepisode',
            '_quinrel_podcast_episode_number' => '1',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertIsArray($schema);
        $this->assertSame('PodcastEpisode', $schema['@type']);
        $this->assertSame('Interview with Tech Leaders', $schema['name']);
        $this->assertSame(1, $schema['episodeNumber']);

        $this->assertArrayHasKey('associatedMedia', $schema);
        $audio = $schema['associatedMedia'];
        $this->assertSame('AudioObject', $audio['@type']);
        $this->assertSame('https://example.com/media/interview.m4a', $audio['contentUrl']);
        $this->assertSame('audio/mp4', $audio['encodingFormat']);
    }

    public function testPodcastSchemaAutoExtractsFromHtml5AudioTag(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 503;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Short Audio Note',
            'post_content' => '<p>Listen below:</p><audio src="https://cdn.example.org/pod/note.ogg" preload="metadata"></audio>',
            'post_type' => 'post',
            'post_date' => '2026-10-22 10:00:00',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'podcast',
        ];

        $schema = $this->customSchema->generate($postId);

        $this->assertIsArray($schema);
        $this->assertSame('PodcastEpisode', $schema['@type']);
        $this->assertArrayHasKey('associatedMedia', $schema);
        $this->assertSame('audio/ogg', $schema['associatedMedia']['encodingFormat']);
    }

    public function testPodcastSchemaIntegrationInSchemaGenerator(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 504;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Live Broadcast Episode',
            'post_content' => '<audio src="https://example.com/live.wav"></audio>',
            'post_type' => 'post',
            'post_date' => '2026-10-22 10:00:00',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_primary_schema' => 'podcast',
            '_quinrel_podcast_series_name' => 'Tech Talks',
        ];

        $fullGraph = $this->schemaGenerator->getSchemaForPost($postId);

        $this->assertNotEmpty($fullGraph['@graph']);
        $types = array_column($fullGraph['@graph'], '@type');
        $this->assertContains('PodcastEpisode', $types);
        $this->assertContains('Article', $types);
        $this->assertContains('BreadcrumbList', $types);
    }
}

