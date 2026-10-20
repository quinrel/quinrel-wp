<?php

namespace WP_CLI\Utils {
	if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
		function format_items( string $format, array $items, array $fields ): void {
			\WP_CLI::$formatted = $items;
		}
	}
}

namespace {
	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			public static array $lines     = array();
			public static array $successes = array();
			public static array $errors    = array();
			public static array $formatted = array();

			public static function line( string $msg ): void {
				self::$lines[] = $msg;
			}

			public static function success( string $msg ): void {
				self::$successes[] = $msg;
			}

			public static function error( string $msg ): void {
				self::$errors[] = $msg;
			}

			public static function add_command( string $name, string $class ): void {
			}

			public static function reset(): void {
				self::$lines     = array();
				self::$successes = array();
				self::$errors    = array();
				self::$formatted = array();
			}
		}
	}
}

namespace Quinrel\WordPress\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use Quinrel\WordPress\Modules\CLI\QuinrelCommand;

	class CliCommandTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();
			\WP_CLI::reset();
		}

		public function testAuditCommandRunsChecksAndFormats(): void {
			$cmd = new QuinrelCommand();
			$cmd->audit( array(), array( 'format' => 'table' ) );

			$this->assertNotEmpty( \WP_CLI::$formatted );
			$this->assertContains( 'Technical SEO Audit complete.', \WP_CLI::$successes );
		}

		public function testMigrateCommandPreviewsAndConfirms(): void {
			$cmd = new QuinrelCommand();

			$GLOBALS['mock_wpdb_var'] = static fn() => 0;
			$GLOBALS['mock_wpdb_col'] = static fn() => array();

			$cmd->migrate( array( 'yoast' ), array() );
			$this->assertNotEmpty( \WP_CLI::$lines );

			$cmd->migrate( array( 'yoast' ), array( 'confirm' => true ) );
			$this->assertNotEmpty( \WP_CLI::$successes );

			unset( $GLOBALS['mock_wpdb_var'], $GLOBALS['mock_wpdb_col'] );
		}

		public function testIndexNowCommandOutputsStatus(): void {
			update_option( 'quinrel_indexnow_key', 'test-key-1234567890123456789012' );
			update_option( 'quinrel_auto_index', 'yes' );

			$cmd = new QuinrelCommand();
			$cmd->indexnow( array(), array() );

			$this->assertContains( 'IndexNow status retrieved.', \WP_CLI::$successes );
			$foundKey = false;
			foreach ( \WP_CLI::$lines as $line ) {
				if ( str_contains( $line, 'test-key-1234567890123456789012' ) ) {
					$foundKey = true;
				}
			}
			$this->assertTrue( $foundKey );
		}
	}
}

