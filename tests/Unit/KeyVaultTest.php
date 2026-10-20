<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Core\Api\QuinrelApiGateway;
use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Core\Security\KeyVault;
use Quinrel\WordPress\Modules\Admin\AdminMenu;
use Quinrel\WordPress\Modules\Admin\AdminModule;
use Quinrel\WordPress\Modules\Social\SocialModule;
use PHPUnit\Framework\TestCase;

class KeyVaultTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		KeyVault::setTestConstraints( false, false );
		delete_option( 'quinrel_encryption_salt' );
		delete_option( 'quinrel_license_key' );
		delete_option( 'quinrel_openai_key' );
		delete_option( 'quinrel_social_accounts' );
	}

	protected function tearDown(): void {
		KeyVault::setTestConstraints( false, false );
		unset( $GLOBALS['mock_user_caps'], $GLOBALS['mock_http_response'], $GLOBALS['mock_http_log'] );
		parent::tearDown();
	}

	public function testEncryptWritesTheNewPrefixAndRoundTrips(): void {
		$secret    = 'sk-proj-test1234567890abcdefghijklmnopqrstuvwxyz';
		$encrypted = KeyVault::encrypt( $secret );

		$this->assertIsString( $encrypted );
		$this->assertStringStartsWith( 'quinrel_enc::', $encrypted );
		$this->assertStringNotContainsString( 'holvek_enc::', $encrypted );
		$this->assertStringNotContainsString( 'aew_enc::', $encrypted );
		$this->assertNotSame( $secret, $encrypted );
		$this->assertSame( $secret, KeyVault::decrypt( $encrypted ) );
	}

	public function testEncryptCreatesASaltAndDoesNotReplaceAnExistingOne(): void {
		$encrypted = KeyVault::encrypt( 'created-with-new-salt' );

		$this->assertIsString( $encrypted );
		$salt = get_option( 'quinrel_encryption_salt' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', (string) $salt );
		$this->assertSame( 'created-with-new-salt', KeyVault::decrypt( $encrypted ) );

		update_option( 'quinrel_encryption_salt', '' );
		$replaced = KeyVault::encrypt( 'replaced-empty-salt' );
		$this->assertIsString( $replaced );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', (string) get_option( 'quinrel_encryption_salt' ) );
		$this->assertSame( 'replaced-empty-salt', KeyVault::decrypt( $replaced ) );

		update_option( 'quinrel_encryption_salt', 'keep-this-salt' );
		KeyVault::encrypt( 'value' );
		$this->assertSame( 'keep-this-salt', get_option( 'quinrel_encryption_salt' ) );
		$this->assertSame( 'value', KeyVault::decrypt( KeyVault::encrypt( 'value' ) ) );
	}

	public function testMissingSaltRefusesToEncryptAndDoesNotUseTheFallbackKey(): void {
		$this->assertFalse( defined( 'AUTH_KEY' ) );
		KeyVault::setTestConstraints( true, false );

		$ciphertext = $this->ciphertext( 'fallback-secret', 'quinrel-fallback-key-change-me', 'quinrel_enc::' );
		$this->assertSame( '', KeyVault::decrypt( $ciphertext ) );

		$result = KeyVault::encrypt( 'new-secret' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'quinrel_salt_missing', $result->get_error_code() );
		$this->assertFalse( get_option( 'quinrel_encryption_salt', false ) );
		$this->assertStringNotContainsString( 'new-secret', $result->get_error_message() );
		$this->assertStringNotContainsString( 'quinrel-fallback-key-change-me', $result->get_error_message() );
	}

	public function testEmptyStringHandling(): void {
		$this->assertSame( '', KeyVault::encrypt( '' ) );
		$this->assertSame( '', KeyVault::decrypt( '' ) );
	}

	public function testDoesNotDoubleEncrypt(): void {
		$secret         = 'sk-proj-single-encryption';
		$encryptedOnce  = KeyVault::encrypt( $secret );
		$encryptedTwice = KeyVault::encrypt( $encryptedOnce );

		$this->assertSame( $encryptedOnce, $encryptedTwice );
	}

	public function testLegacyUnencryptedKeyPassThrough(): void {
		$legacyKey = 'plain-text-api-key-without-prefix';
		$this->assertSame( $legacyKey, KeyVault::decrypt( $legacyKey ) );
	}

	public function testTamperedCiphertextFailsDecryption(): void {
		$secret    = 'my-super-confidential-service-account-key';
		$encrypted = KeyVault::encrypt( $secret );
		$this->assertIsString( $encrypted );

		$payload = substr( $encrypted, strlen( 'quinrel_enc::' ) );
		$decoded = base64_decode( $payload );
		$decoded[ strlen( $decoded ) - 1 ] = chr( ord( $decoded[ strlen( $decoded ) - 1 ] ) ^ 0xFF );
		$tampered = 'quinrel_enc::' . base64_encode( $decoded );

		$this->assertSame( '', KeyVault::decrypt( $tampered ) );

		delete_option( 'quinrel_encryption_salt' );
		KeyVault::setTestConstraints( true, false );
		$this->assertSame( '', KeyVault::decrypt( $encrypted ) );
	}

	public function testUnavailableCipherDoesNotReturnOrStorePlaintext(): void {
		$encrypted = KeyVault::encrypt( 'stored-secret' );
		$this->assertIsString( $encrypted );

		KeyVault::setTestConstraints( false, true );
		$this->assertSame( '', KeyVault::decrypt( $encrypted ) );

		$result = KeyVault::encrypt( 'plain-secret' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'quinrel_cipher_unavailable', $result->get_error_code() );
		$this->assertStringNotContainsString( 'plain-secret', $result->get_error_message() );

		$kept = KeyVault::encrypt( $encrypted );
		$this->assertSame( $encrypted, $kept );

		update_option( 'quinrel_openai_key', 'already-stored' );
		$admin   = new AdminModule( new EventManager(), new AdminMenu() );
		$request = new \WP_REST_Request();
		$request->set_json_params( array( 'openai_key' => 'sk-new-plaintext' ) );
		$saved = $admin->updateSettings( $request );

		$this->assertInstanceOf( \WP_Error::class, $saved );
		$this->assertSame( 'quinrel_cipher_unavailable', $saved->get_error_code() );
		$this->assertSame( 'already-stored', get_option( 'quinrel_openai_key' ) );

		update_option( 'quinrel_social_accounts', array() );
		$module  = new SocialModule( new EventManager() );
		$account = new \WP_REST_Request();
		$account->set_json_params(
			array(
				'network'      => 'twitter',
				'access_token' => 'plain-token-must-not-store',
			)
		);
		$social = $module->saveAccount( $account );
		$this->assertInstanceOf( \WP_Error::class, $social );
		$this->assertSame( array(), get_option( 'quinrel_social_accounts', array() ) );
	}

	public function testLicenseKeyIsEncryptedAndAStoredStringStillCountsAsPresent(): void {
		$stored = KeyVault::encryptSettings(
			array(
				'quinrel_license_key' => 'lic-plain-value',
				'quinrel_ai_provider' => 'openai',
			)
		);

		$this->assertStringStartsWith( 'quinrel_enc::', $stored['quinrel_license_key'] );
		$this->assertSame( 'lic-plain-value', KeyVault::decryptSettings( $stored )['quinrel_license_key'] );
		$this->assertSame( 'openai', $stored['quinrel_ai_provider'] );

		update_option( 'quinrel_license_key', $stored['quinrel_license_key'] );
		$this->assertFalse( QuinrelApiGateway::hasActiveLicense() );

		$GLOBALS['mock_http_log'] = array();
		$result                   = QuinrelApiGateway::proxyRequest( 'pagespeed', array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'quinrel_not_connected', $result->get_error_code() );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		update_option( 'quinrel_license_key', 'plain-license' );
		$this->assertFalse( QuinrelApiGateway::hasActiveLicense() );

		update_option( 'quinrel_license_key', '' );
		$this->assertFalse( QuinrelApiGateway::hasActiveLicense() );

		KeyVault::setTestConstraints( false, true );
		$dropped = KeyVault::encryptSettings(
			array(
				'quinrel_license_key' => 'super-secret-license',
				'quinrel_ai_provider' => 'openai',
			)
		);
		$this->assertArrayNotHasKey( 'quinrel_license_key', $dropped );
		$this->assertSame( 'openai', $dropped['quinrel_ai_provider'] );
		$this->assertSame( $stored['quinrel_license_key'], KeyVault::encrypt( $stored['quinrel_license_key'] ) );
	}

	public function testSettingsTransferOmitsTheSalt(): void {
		update_option( 'quinrel_encryption_salt', 'salt-must-stay-on-the-site' );
		update_option( 'quinrel_breadcrumb_home_label', 'Start' );

		$admin    = new AdminModule( new EventManager(), new AdminMenu() );
		$exported = $admin->exportSettings( new \WP_REST_Request() );
		$data     = $exported->get_data()['data'];
		$encoded  = wp_json_encode( $data );

		$this->assertArrayNotHasKey( 'quinrel_encryption_salt', $data['settings'] );
		$this->assertStringNotContainsString( 'salt-must-stay-on-the-site', $encoded );
		$this->assertSame( 'Start', $data['settings']['quinrel_breadcrumb_home_label'] );

		$import = new \WP_REST_Request();
		$import->set_json_params(
			array(
				'_plugin'  => 'quinrel',
				'settings' => array(
					'quinrel_encryption_salt'       => 'imported-salt-must-not-replace',
					'quinrel_breadcrumb_home_label' => 'Home',
				),
			)
		);
		$result = $admin->importSettings( $import );

		$this->assertSame( 1, $result->get_data()['imported'] );
		$this->assertSame( 'salt-must-stay-on-the-site', get_option( 'quinrel_encryption_salt' ) );
		$this->assertSame( 'Home', get_option( 'quinrel_breadcrumb_home_label' ) );
	}

	public function testMissingCipherNoticeIsLimitedToAdministrators(): void {
		KeyVault::setTestConstraints( false, true );
		$GLOBALS['mock_hooks'] = array();
		KeyVault::registerAdminNotice();

		$hooks = array_column( $GLOBALS['mock_hooks'], 1 );
		$this->assertContains( 'admin_notices', $hooks );

		$GLOBALS['mock_current_screen'] = (object) array( 'id' => 'dashboard' );
		$this->assertSame( '', $this->captureNotice() );

		$GLOBALS['mock_current_screen'] = (object) array( 'id' => 'toplevel_page_quinrel' );
		$html = $this->captureNotice();
		$this->assertStringContainsString( 'OpenSSL', $html );
		$this->assertStringContainsString( 'is-dismissible', $html );
		$this->assertStringNotContainsString( 'quinrel-fallback-key-change-me', $html );

		$GLOBALS['mock_user_caps'] = array( 'manage_options' => false );
		$this->assertSame( '', $this->captureNotice() );

		unset( $GLOBALS['mock_user_caps'], $GLOBALS['mock_current_screen'] );
		KeyVault::setTestConstraints( false, false );
		$this->assertSame( '', $this->captureNotice() );
	}

	private function captureNotice(): string {
		ob_start();
		try {
			KeyVault::renderMissingCipherNotice();
			return (string) ob_get_clean();
		} catch ( \Throwable $exception ) {
			ob_end_clean();
			throw $exception;
		}
	}

	private function ciphertext( string $secret, string $salt, string $prefix ): string {
		$key    = hash( 'sha256', $salt, true );
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $secret, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		$hmac   = hash_hmac( 'sha256', $cipher, $key, true );

		return $prefix . base64_encode( $iv . $hmac . $cipher );
	}
}
