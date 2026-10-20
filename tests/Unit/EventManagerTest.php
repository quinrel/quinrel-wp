<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Core\Event\EventManager;
use PHPUnit\Framework\TestCase;

class EventManagerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_did_actions'] = array();
	}

	public function testFiredHooksUseOneQuinrelPrefix(): void {
		$events = new EventManager();
		$events->doAction( 'quinrel_activation' );
		$events->doAction( 'Audit_Done' );
		$events->doAction( 'wp-head' );
		$events->doAction( 'quinrel_' );

		$this->assertSame(
			array(
				'quinrel_activation',
				'quinrel_audit_done',
			),
			$GLOBALS['mock_did_actions']
		);
	}
}
