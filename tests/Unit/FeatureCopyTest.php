<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FeatureCopyTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();
		$this->root = dirname( __DIR__, 2 );
	}

	public function testTheReadmeFeaturesDocAndAdminUseTheSameFeatureNames(): void {
		$readme   = $this->read( 'readme.txt' );
		$features = $this->read( 'docs/features.md' );
		$app      = $this->read( 'src/assets/js/App.js' );
		$phrases  = array(
			'Google Analytics 4',
			'Link Health',
			'Technical SEO',
			'XML Sitemaps',
			'Schema Markup',
			'llms.txt',
			'Bulk Meta Editor',
			'HTML Sitemap',
			'Image SEO',
			'cookie notice',
			'Roles & Capabilities',
			'Technical SEO Audit',
			'Broken Link Checker',
			'Keyword Rank Tracker',
			'Google Search Console',
			'IndexNow',
			'Redirects Manager',
			'The writing assistant uses an OpenAI, Anthropic, or Ollama key saved on this site.',
			'Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it.',
			'It does not score AI mentions, Google presence, or competitors.',
			'A Quinrel account is optional.',
			'The settings screen cannot change the Quinrel API host.',
		);

		foreach ( $phrases as $phrase ) {
			$this->assertStringContainsString( $phrase, $readme, $phrase );
			$this->assertStringContainsString( $phrase, $features, $phrase );
			$this->assertStringContainsString( $phrase, $app, $phrase );
		}
	}

	public function testPageSpeedStaysInTheEditorCopy(): void {
		$this->assertStringContainsString( 'PageSpeed', $this->read( 'readme.txt' ) );
		$this->assertStringContainsString( 'PageSpeed', $this->read( 'docs/features.md' ) );
		$this->assertStringContainsString( 'PageSpeed Performance', $this->read( 'src/assets/js/editor.js' ) );
	}

	public function testTheShortDescriptionMatchesThePluginHeader(): void {
		$sentence = 'Visibility & Discovery Intelligence for WordPress, with technical SEO included.';

		$this->assertStringContainsString( '=== Quinrel ===', $this->read( 'readme.txt' ) );
		$this->assertStringContainsString( $sentence, $this->read( 'readme.txt' ) );
		$this->assertStringContainsString( $sentence, $this->read( 'docs/features.md' ) );
		$this->assertStringContainsString( $sentence, $this->read( 'src/assets/js/App.js' ) );
		$this->assertStringContainsString( 'Description: ' . $sentence, $this->read( 'quinrel.php' ) );
		$this->assertStringContainsString( '"description": "' . $sentence . '"', $this->read( 'composer.json' ) );
	}

	public function testTheReadmeStatesThePhpRequirementAndTheExternalServices(): void {
		$readme = $this->read( 'readme.txt' );

		$this->assertStringContainsString( 'Requires PHP: 8.2', $readme );
		$this->assertStringContainsString( 'Requires PHP: 8.2', $this->read( 'quinrel.php' ) );
		$this->assertStringContainsString( '"php": ">=8.2"', $this->read( 'composer.json' ) );
		$this->assertStringContainsString( 'Tested up to: 7.1', $readme );
		$this->assertStringContainsString( 'nothing is sent until an administrator turns the feature on', $readme );
		$this->assertStringContainsString( 'https://api.quinrel.com', $readme );
		$this->assertStringNotContainsString( 'guaranteed indexing', $readme );
		$this->assertStringNotContainsString( 'ranking improvement', $readme );
		$this->assertStringContainsString( '== Screenshots ==', $readme );
		$this->assertStringContainsString( '1. The Quinrel dashboard', $readme );
		$this->assertStringContainsString( '2. The Quinrel panel in the block editor', $readme );
		$this->assertStringContainsString( '3. Redirects Manager.', $readme );
		$this->assertStringContainsString( '4. The technical SEO audit', $readme );
		$this->assertStringContainsString( '5. Image SEO.', $readme );
		$this->assertStringContainsString( '6. Schema markup templates.', $readme );
		$this->assertStringContainsString( '7. The keyword rank tracker.', $readme );
		$this->assertStringContainsString( "8. XML sitemap settings. Quinrel's sitemap stays off", $readme );
	}

	public function testUserFacingCopyDoesNotClaimADiscoveryProduct(): void {
		$files     = array(
			'readme.txt',
			'docs/features.md',
			'src/assets/js/App.js',
			'src/assets/js/editor.js',
			'quinrel.php',
			'composer.json',
		);
		$forbidden = array(
			'Discovery Scan',
			'technical discovery score',
			'AI mention check',
			'competitor comparison',
			'SEO & Discovery',
			'llms-full.txt',
			'AI-First',
			'AEO',
		);

		foreach ( $files as $file ) {
			$contents = $this->read( $file );
			foreach ( $forbidden as $phrase ) {
				$this->assertStringNotContainsString( $phrase, $contents, $file . ' contains ' . $phrase );
			}
		}
	}

	private function read( string $path ): string {
		$contents = file_get_contents( $this->root . '/' . $path );
		$this->assertIsString( $contents, $path );

		return $contents;
	}
}
