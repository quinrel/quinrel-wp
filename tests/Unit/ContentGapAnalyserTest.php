<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\ContentAssistant\ContentGapAnalyser;
use ReflectionMethod;

class ContentGapAnalyserTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['mock_wpdb_results'] );
		parent::tearDown();
	}

	public function testExistingTopicsReadIsBounded(): void {
		$GLOBALS['wpdb']->queries      = array();
		$GLOBALS['mock_wpdb_results']  = static function (): array {
			return array(
				(object) array(
					'post_title' => 'Alpha',
					'meta_value' => 'alpha kw',
				),
				(object) array(
					'post_title' => 'Alpha',
					'meta_value' => 'alpha kw',
				),
				(object) array(
					'post_title' => '',
					'meta_value' => 'beta',
				),
			);
		};

		$method = new ReflectionMethod( ContentGapAnalyser::class, 'getExistingTopics' );
		$method->setAccessible( true );
		$topics = $method->invoke( new ContentGapAnalyser(), 'widgets' );

		$this->assertSame( array( 'alpha kw', 'Alpha', 'beta' ), $topics );
		$this->assertNotEmpty( $GLOBALS['wpdb']->queries );
		$sql = (string) $GLOBALS['wpdb']->queries[0];
		$this->assertStringContainsString( 'LIMIT 200', $sql );
		$this->assertStringContainsString( '_quinrel_focus_keyword', $sql );
		$this->assertArrayNotHasKey( 'mock_get_posts_args', $GLOBALS );
	}
}
