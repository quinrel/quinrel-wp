<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Ai\AiBotTracker;

class AiBotTrackerTest extends TestCase
{
    private AiBotTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new AiBotTracker();
        $GLOBALS['mock_transients'] = array();
        $GLOBALS['mock_is_admin'] = false;
        $_SERVER['HTTP_USER_AGENT'] = '';
    }

    protected function tearDown(): void
    {
        $GLOBALS['mock_transients'] = array();
        $_SERVER['HTTP_USER_AGENT'] = '';
        parent::tearDown();
    }

    public function testDetectsKnownAiBots(): void
    {
        $this->assertSame('gptbot', $this->tracker->detectBot('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)'));
        $this->assertSame('gptbot', $this->tracker->detectBot('Mozilla/5.0 ChatGPT-User/1.0'));
        $this->assertSame('claudebot', $this->tracker->detectBot('Mozilla/5.0 ClaudeBot/1.0; +claudebot@anthropic.com'));
        $this->assertSame('claudebot', $this->tracker->detectBot('Anthropic-AI/1.0'));
        $this->assertSame('perplexitybot', $this->tracker->detectBot('Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)'));
        $this->assertSame('google_extended', $this->tracker->detectBot('Google-Extended'));
        $this->assertSame('applebot', $this->tracker->detectBot('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Applebot-Extended/0.1'));
        $this->assertSame('other_ai', $this->tracker->detectBot('CCBot/2.0 (https://commoncrawl.org/faq/)'));
    }

    public function testIgnoresStandardUserAgents(): void
    {
        $this->assertNull($this->tracker->detectBot('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'));
        $this->assertNull($this->tracker->detectBot('Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/119.0'));
        $this->assertNull($this->tracker->detectBot('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'));
    }

    public function testIncrementsCountsInTransient(): void
    {
        $this->tracker->incrementCount('gptbot');
        $this->tracker->incrementCount('gptbot');
        $this->tracker->incrementCount('claudebot');

        $data = $this->tracker->getActivity();
        $this->assertSame(2, $data['gptbot']);
        $this->assertSame(1, $data['claudebot']);
        $this->assertSame(3, $data['total']);
        $this->assertSame('claudebot', $data['last_bot']);
        $this->assertNotEmpty($data['last_seen']);
    }

    public function testTrackRequestRunsOnFrontendOnly(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; PerplexityBot/1.0)';
        $this->tracker->trackRequest();

        $data = $this->tracker->getActivity();
        $this->assertSame(1, $data['perplexitybot']);
        $this->assertSame(1, $data['total']);

        // In admin context, request should NOT be tracked
        $GLOBALS['mock_is_admin'] = true;
        $this->tracker->trackRequest();
        $dataAfter = $this->tracker->getActivity();
        $this->assertSame(1, $dataAfter['perplexitybot']);
    }

    public function testGetActivityEndpointReturnsJsonResponse(): void
    {
        $this->tracker->incrementCount('gptbot');
        $response = $this->tracker->getActivityEndpoint();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['data']['gptbot']);
    }
}
