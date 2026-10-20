<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Performance\ScriptOptimizer;
use Quinrel\WordPress\Modules\Performance\FontOptimizer;
use Quinrel\WordPress\Modules\Accessibility\ContrastAuditor;
use Quinrel\WordPress\Modules\Accessibility\DomAccessibilityScanner;
use Quinrel\WordPress\Modules\TechnicalSeo\RedirectChainCollapser;

class AutofixAndOptimizationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_wp_options'] = array();
	}

	public function testScriptOptimizerDefersEligibleScripts(): void {
		$optimizer = new ScriptOptimizer();

		// Regular script should receive defer
		$tag      = '<script src="https://example.com/app.js" id="app-js"></script>';
		$result   = $optimizer->filterScriptTag( $tag, 'app-js', 'https://example.com/app.js' );
		$this->assertStringContainsString( 'defer="defer"', $result );

		// Core exclusions (jquery) should NOT be deferred
		$jqTag    = '<script src="https://example.com/jquery.js" id="jquery-core-js"></script>';
		$jqResult = $optimizer->filterScriptTag( $jqTag, 'jquery-core', 'https://example.com/jquery.js' );
		$this->assertStringNotContainsString( 'defer="defer"', $jqResult );

		// Scripts already deferred should not have duplicate defer attributes
		$alreadyDeferred = '<script src="https://example.com/deferred.js" defer="defer"></script>';
		$defResult       = $optimizer->filterScriptTag( $alreadyDeferred, 'def-js', 'https://example.com/deferred.js' );
		$this->assertSame( $alreadyDeferred, $defResult );

		// When option is disabled, no deferral happens
		$GLOBALS['mock_wp_options']['quinrel_defer_non_critical_scripts'] = 'no';
		$noResult = $optimizer->filterScriptTag( $tag, 'app-js', 'https://example.com/app.js' );
		$this->assertSame( $tag, $noResult );
	}

	public function testFontOptimizerAppendsDisplaySwap(): void {
		$optimizer = new FontOptimizer();

		$googleFontUrl = 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700';
		$result        = $optimizer->filterGoogleFontSrc( $googleFontUrl, 'roboto-font' );
		$this->assertStringContainsString( 'display=swap', $result );

		// Non-Google fonts are unmodified
		$localFont = 'https://example.com/fonts.css';
		$localRes  = $optimizer->filterGoogleFontSrc( $localFont, 'local-font' );
		$this->assertSame( $localFont, $localRes );

		// When disabled, URL remains untouched
		$GLOBALS['mock_wp_options']['quinrel_font_display_swap'] = 'no';
		$disabledRes = $optimizer->filterGoogleFontSrc( $googleFontUrl, 'roboto-font' );
		$this->assertSame( $googleFontUrl, $disabledRes );
	}

	public function testContrastAuditorCalculatesAccurateRatio(): void {
		// Pure black vs pure white is 21:1
		$ratioMax = ContrastAuditor::calculateRatio( '#000000', '#ffffff' );
		$this->assertEqualsWithDelta( 21.0, $ratioMax, 0.1 );

		// Identical colors are 1:1
		$ratioMin = ContrastAuditor::calculateRatio( '#ffffff', '#ffffff' );
		$this->assertEqualsWithDelta( 1.0, $ratioMin, 0.1 );

		// Low contrast check (light gray on white)
		$lowRatio = ContrastAuditor::calculateRatio( '#cccccc', '#ffffff' );
		$this->assertLessThan( 4.5, $lowRatio );

		// Dark recommendation for light background
		$suggestion = ContrastAuditor::suggestCompliantColor( '#cccccc', '#ffffff' );
		$this->assertSame( '#0f172a', $suggestion );
	}

	public function testDomAccessibilityScannerDetectsEmptyLinks(): void {
		$scanner = new DomAccessibilityScanner();

		$htmlBad = '<p>Check this out: <a href="https://example.com/page"></a></p>';
		$issues  = $scanner->scanHtml( 10, $htmlBad );
		$this->assertNotEmpty( $issues );
		$this->assertSame( 'empty_link', $issues[0]['type'] );

		// Good link with text should pass
		$htmlGood = '<p>Check this out: <a href="https://example.com/page">Read our guide</a></p>';
		$clean    = $scanner->scanHtml( 10, $htmlGood );
		$this->assertEmpty( $clean );

		// Good link with aria-label should pass
		$htmlAria = '<p>Check this out: <a href="https://example.com/page" aria-label="Read our guide"></a></p>';
		$ariaPass = $scanner->scanHtml( 10, $htmlAria );
		$this->assertEmpty( $ariaPass );
	}

	public function testDomAccessibilityScannerDetectsSkippedHeadings(): void {
		$scanner = new DomAccessibilityScanner();

		// H1 straight to H3 is a skipped heading level
		$htmlSkipped = '<h1>Main Title</h1><p>Intro</p><h3>Skipped Subhead</h3>';
		$issues      = $scanner->scanHtml( 12, $htmlSkipped );
		$this->assertNotEmpty( $issues );
		$this->assertSame( 'skipped_heading', $issues[0]['type'] );

		// Proper hierarchy H1 -> H2 -> H3 should not flag skipped headings
		$htmlProper = '<h1>Main Title</h1><h2>Section</h2><h3>Subhead</h3>';
		$clean      = $scanner->scanHtml( 12, $htmlProper );
		$this->assertEmpty( $clean );
	}
}
