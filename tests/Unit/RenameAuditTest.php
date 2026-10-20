<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RenameAuditTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();
		$this->root = dirname( __DIR__, 2 );
	}

	public function testLocalizedGlobalsMatchOnBothSides(): void {
		$pairs = array(
			array( 'src/Modules/Admin/AdminMenu.php', 'src/assets/js/App.js', 'quinrelAdminConfig', 'holvekAdminConfig' ),
			array( 'src/Modules/Admin/AdminMenu.php', 'src/assets/js/components/SetupWizard.js', 'quinrelAdminConfig', 'holvekAdminConfig' ),
			array( 'src/Modules/ContentAssistant/ContentAssistantModule.php', 'src/assets/js/editor.js', 'quinrelEditorConfig', 'holvekEditorConfig' ),
			array( 'src/Modules/Compliance/CookieBannerModule.php', 'src/assets/js/cookie-banner.js', 'quinrelCookieConfig', 'holvekCookieConfig' ),
		);

		foreach ( $pairs as $pair ) {
			$php = file_get_contents( $this->root . '/' . $pair[0] );
			$js  = file_get_contents( $this->root . '/' . $pair[1] );
			$this->assertStringContainsString( $pair[2], $php, $pair[0] );
			$this->assertStringContainsString( $pair[2], $js, $pair[1] );
			$this->assertStringNotContainsString( $pair[3], $php, $pair[0] );
			$this->assertStringNotContainsString( $pair[3], $js, $pair[1] );
			$this->assertStringNotContainsString( 'amEveryWhere', $php, $pair[0] );
			$this->assertStringNotContainsString( 'amEveryWhere', $js, $pair[1] );
		}

		$assistant = file_get_contents( $this->root . '/src/Modules/ContentAssistant/LlmWritingAssistant.php' );
		$this->assertStringContainsString( 'quinrelWritingAssistant', $assistant );
		$this->assertStringNotContainsString( 'holvekWritingAssistant', $assistant );
		$this->assertStringNotContainsString( 'amEveryWhereWritingAssistant', $assistant );

		$banner = file_get_contents( $this->root . '/src/assets/js/cookie-banner.js' );
		$this->assertStringContainsString( 'quinrelConsent', $banner );
		$this->assertStringNotContainsString( 'holvekConsent', $banner );
		$this->assertStringNotContainsString( 'amEveryWhereConsent', $banner );
	}

	public function testBuiltBundlesUseTheNewGlobals(): void {
		$index  = file_get_contents( $this->root . '/build/index.js' );
		$editor = file_get_contents( $this->root . '/build/editor.js' );
		$cookie = file_get_contents( $this->root . '/build/cookie-banner.js' );

		$this->assertStringContainsString( 'quinrelAdminConfig', $index );
		$this->assertStringNotContainsString( 'holvekAdminConfig', $index );
		$this->assertStringNotContainsString( 'amEveryWhereAdminConfig', $index );
		$this->assertStringContainsString( 'quinrelEditorConfig', $editor );
		$this->assertStringNotContainsString( 'holvekEditorConfig', $editor );
		$this->assertStringNotContainsString( 'amEveryWhereEditorConfig', $editor );
		$this->assertStringContainsString( 'quinrelCookieConfig', $cookie );
		$this->assertStringContainsString( 'quinrelConsent', $cookie );
		$this->assertStringNotContainsString( 'holvek', $cookie );
		$this->assertStringNotContainsString( 'amEveryWhere', $cookie );
	}

	public function testPublicLegacyNamesAreGone(): void {
		$sources = array(
			'quinrel.php',
			'src/Plugin.php',
			'src/Modules/Sitemap/HtmlSitemapShortcode.php',
			'src/Modules/Compliance/CcpaPrivacyTools.php',
			'src/Modules/Compliance/CookieBannerModule.php',
			'src/assets/js/App.js',
			'src/assets/js/cookie-banner.js',
			'src/assets/css/admin.css',
			'src/assets/css/cookie-banner.css',
			'readme.txt',
			'docs/features.md',
			'.github/workflows/release-package.yml',
			'.github/workflows/wordpress-e2e.yml',
		);

		foreach ( $sources as $path ) {
			$contents = strtolower( file_get_contents( $this->root . '/' . $path ) );
			$this->assertStringNotContainsString( 'holvek', $contents, $path );
			$this->assertStringNotContainsString( 'ameverywhere', $contents, $path );
			$this->assertStringNotContainsString( 'aew-', $contents, $path );
			$this->assertStringNotContainsString( 'aew_', $contents, $path );
		}

		$sitemap = file_get_contents( $this->root . '/src/Modules/Sitemap/HtmlSitemapShortcode.php' );
		$this->assertStringContainsString( "add_shortcode( 'quinrel_sitemap'", $sitemap );

		$gpc = file_get_contents( $this->root . '/src/Modules/Compliance/CcpaPrivacyTools.php' );
		$this->assertStringContainsString( "do_action( 'quinrel_gpc_opt_out'", $gpc );

		$ruleset = file_get_contents( $this->root . '/phpcs.xml.dist' );
		$this->assertStringContainsString( 'name="Quinrel"', $ruleset );
		$this->assertStringNotContainsString( 'Holvek', $ruleset );
		$this->assertStringNotContainsString( 'AmEveryWhere', $ruleset );
	}

	public function testLegacyDebtIsRemoved(): void {
		$installer = file_get_contents( $this->root . '/src/Core/Database/Installer.php' );
		$this->assertStringNotContainsString( 'ameverywhere', strtolower( $installer ) );
		$this->assertStringNotContainsString( 'holvek', strtolower( $installer ) );

		$vault = file_get_contents( $this->root . '/src/Core/Security/KeyVault.php' );
		$this->assertStringContainsString( "'quinrel_enc::'", $vault );
		$this->assertStringNotContainsString( 'holvek_enc::', $vault );
		$this->assertStringNotContainsString( 'aew_enc::', $vault );
	}

	public function testPotUsesTheQuinrelDomain(): void {
		$pot = file_get_contents( $this->root . '/languages/quinrel.pot' );
		$this->assertStringContainsString( 'Project-Id-Version: Quinrel 1.0.1', $pot );
		$this->assertStringContainsString( 'X-Domain: quinrel', $pot );
		$this->assertStringContainsString( 'https://wordpress.org/support/plugin/quinrel', $pot );
		$this->assertStringNotContainsString( 'holvek', strtolower( $pot ) );
		$this->assertStringNotContainsString( 'ameverywhere', strtolower( $pot ) );
		$this->assertStringNotContainsString( 'aew_', $pot );
	}

	public function testOldReleaseArtifactIsGone(): void {
		$this->assertFileDoesNotExist( $this->root . '/dist/ameverywhere-1.0.0.zip' );
		$this->assertFileDoesNotExist( $this->root . '/dist/ameverywhere-1.0.0.manifest.sha256' );
		$this->assertFileDoesNotExist( $this->root . '/dist/holvek-1.0.0.zip' );
		$this->assertFileDoesNotExist( $this->root . '/dist/holvek-1.0.1.zip' );
	}
}
