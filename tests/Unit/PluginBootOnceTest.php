<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PluginBootOnceTest extends TestCase {

	private bool $createdUpgrade = false;

	protected function setUp(): void {
		parent::setUp();

		update_option( 'quinrel_db_version', QUINREL_VERSION );
		$GLOBALS['mock_hooks'] = array();

		$instance = ( new ReflectionClass( Plugin::class ) )->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	protected function tearDown(): void {
		$this->removeUpgradeStub();
		parent::tearDown();
	}

	public function testBootRegistersHooksOnce(): void {
		$plugin = Plugin::getInstance();
		$plugin->boot();
		$once = $GLOBALS['mock_hooks'];

		$this->assertGreaterThan( 10, count( $once ) );

		$plugin->boot();

		$this->assertSame( $once, $GLOBALS['mock_hooks'] );
	}

	public function testQuinrelInitDoesNotRegisterHooksAgain(): void {
		require_once dirname( __DIR__, 2 ) . '/quinrel.php';

		quinrel_init();
		$once = $GLOBALS['mock_hooks'];
		$this->assertGreaterThan( 10, count( $once ) );

		quinrel_init();

		$this->assertSame( $once, $GLOBALS['mock_hooks'] );
	}

	public function testBootInstallsSchemaWhenStoredVersionIsBehind(): void {
		$this->stubWordPressUpgrade();
		update_option( 'quinrel_db_version', '0' );
		$GLOBALS['mock_db_delta'] = array();

		Plugin::getInstance()->boot();

		$this->assertSame( QUINREL_VERSION, get_option( 'quinrel_db_version' ) );
		$this->assertTrue( $GLOBALS['mock_option_autoload']['quinrel_db_version'] ?? false );
		$created = implode( "\n", array_map( 'strval', $GLOBALS['mock_db_delta'] ) );
		foreach (
			array(
				'wp_quinrel_redirects',
				'wp_quinrel_404_logs',
				'wp_quinrel_sitemap_settings',
				'wp_quinrel_links',
				'wp_quinrel_ai_usage',
				'wp_quinrel_audit_log',
				'wp_quinrel_rank_history',
				'wp_quinrel_broken_links',
			) as $table
		) {
			$this->assertStringContainsString( $table, $created );
		}
	}

	private function stubWordPressUpgrade(): void {
		$dir = dirname( __DIR__, 2 ) . '/wp-admin/includes';
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
			$this->fail( 'Could not create the upgrade.php stub directory.' );
		}
		$file = $dir . '/upgrade.php';
		if ( ! is_file( $file ) ) {
			file_put_contents( $file, "<?php\n" );
			$this->createdUpgrade = true;
		}
	}

	private function removeUpgradeStub(): void {
		if ( ! $this->createdUpgrade ) {
			return;
		}
		$root = dirname( __DIR__, 2 );
		$file = $root . '/wp-admin/includes/upgrade.php';
		if ( is_file( $file ) ) {
			unlink( $file );
		}
		foreach ( array( $root . '/wp-admin/includes', $root . '/wp-admin' ) as $dir ) {
			if ( is_dir( $dir ) ) {
				$left = scandir( $dir );
				if ( is_array( $left ) && array( '.', '..' ) === $left ) {
					rmdir( $dir );
				}
			}
		}
		$this->createdUpgrade = false;
	}
}
