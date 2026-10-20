<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Core\Api\BackendApiClient;

class BackendApiClientTest extends TestCase
{
    private BackendApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        unset($GLOBALS['mock_filter_callbacks']['quinrel_backend_api_url']);
        delete_option('quinrel_backend_api_url');
        delete_option('quinrel_api_key');
        delete_option('quinrel_license_key');
        delete_option('quinrel_cloud_revoked');
        $this->client = new BackendApiClient();
    }

    public function testDefaultApiUrl(): void
    {
        $this->assertSame('https://api.quinrel.com/v1', $this->client->getApiUrl());
    }

    public function testIsConfiguredReturnsFalseWhenNoKey(): void
    {
        delete_option('quinrel_api_key');

        $this->assertFalse($this->client->isConfigured());
    }

    public function testTestConnectionReturnsFallbackWhenNotConfigured(): void
    {
        delete_option('quinrel_api_key');

        $result = $this->client->testConnection();
        $this->assertFalse($result['success']);
        $this->assertSame('fallback', $result['mode']);
    }

    public function testGenerateAiReturnsAnErrorWhenNotConfigured(): void
    {
        delete_option('quinrel_api_key');
        delete_option('quinrel_license_key');
        $GLOBALS['mock_http_log'] = array();

        $result = $this->client->generateAi(['prompt' => 'test']);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('quinrel_not_connected', $result->get_error_code());
        $this->assertSame(array(), $GLOBALS['mock_http_log']);
    }

    public function testApiKeyConfiguration(): void
    {
        delete_option('quinrel_api_key');
        delete_option('quinrel_license_key');
        delete_option('quinrel_cloud_verification');
        delete_option('quinrel_cloud_revoked');
        $GLOBALS['mock_http_log'] = array();
        $rawKey = 'sk-quinrel-valid-api-key';
        $encryptedKey = \Quinrel\WordPress\Core\Security\KeyVault::encrypt($rawKey);
        update_option('quinrel_api_key', $encryptedKey, false);

        $this->assertFalse($this->client->isConfigured());
        $this->assertSame($rawKey, $this->client->getApiKey());
        $this->assertSame(array(), $GLOBALS['mock_http_log']);

        update_option(
            'quinrel_cloud_verification',
            array(
                'token_hash' => hash('sha256', $rawKey),
                'expires_at' => time() + HOUR_IN_SECONDS,
                'tier' => 'free',
            ),
            false
        );

        $this->assertTrue($this->client->isConfigured());
        $this->assertSame(array(), $GLOBALS['mock_http_log']);

        delete_option('quinrel_api_key');
        delete_option('quinrel_cloud_verification');
    }
}
