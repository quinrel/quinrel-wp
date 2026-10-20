<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Proves the rename left a single autoloadable namespace.
 *
 * Composer maps Quinrel\WordPress\ to src/. Every class whose file name matches
 * its class name must load. quinrel.php must reference that same class.
 */
class NamespaceBootTest extends TestCase {

	public function testEveryMatchingClassAutoloadsUnderQuinrelWordPress(): void {
		$src = dirname( __DIR__, 2 ) . '/src';
		$loaded = array();
		$failures = array();

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}

			$contents = file_get_contents( $file->getPathname() );
			if ( ! preg_match( '/^namespace\s+([^;]+);/m', $contents, $namespaceMatch ) ) {
				$failures[] = $file->getPathname() . ' has no namespace';
				continue;
			}

			$namespace = trim( $namespaceMatch[1] );
			if ( ! str_starts_with( $namespace, 'Quinrel\\WordPress' ) ) {
				$failures[] = $file->getPathname() . " uses {$namespace}";
				continue;
			}

			if ( ! preg_match( '/^(?:abstract\s+|final\s+)?class\s+(\w+)/m', $contents, $classMatch ) ) {
				continue;
			}

			$class = $classMatch[1];
			$fqcn  = $namespace . '\\' . $class;

			if ( $file->getBasename( '.php' ) !== $class ) {
				continue;
			}

			if ( ! class_exists( $fqcn ) ) {
				$failures[] = $fqcn . ' did not autoload from ' . $file->getFilename();
				continue;
			}

			$loaded[] = $fqcn;
		}

		$this->assertGreaterThan( 60, count( $loaded ), 'Expected the plugin class map to load.' );
		$this->assertSame( array(), $failures, implode( "\n", $failures ) );
	}

	public function testBootstrapReferencesTheAutoloadablePluginClass(): void {
		$bootstrap = file_get_contents( dirname( __DIR__, 2 ) . '/quinrel.php' );

		$this->assertStringContainsString( 'Quinrel\\WordPress\\Plugin::getInstance()', $bootstrap );
		$this->assertStringContainsString( 'Quinrel\\WordPress\\Plugin::class', $bootstrap );
		$this->assertDoesNotMatchRegularExpression( '/\\\\(?:Holvek|Quinrel)\\\\Plugin(?!\\\\)/', $bootstrap );
		$this->assertDoesNotMatchRegularExpression( '/\\\\(?:Holvek|Quinrel)\\\\(Core|Modules)\\\\/', $bootstrap );

		require_once dirname( __DIR__, 2 ) . '/quinrel.php';

		$this->assertTrue( function_exists( 'quinrel_init' ) );
		$this->assertTrue( class_exists( Plugin::class ) );
		$this->assertInstanceOf( Plugin::class, Plugin::getInstance() );
		$this->assertSame( Plugin::getInstance(), Plugin::getInstance() );
	}

	public function testQuinrelApiGatewayAutoloadsFromItsClassFilename(): void {
		$root = dirname( __DIR__, 2 );
		$this->assertFileDoesNotExist( $root . '/src/Core/Api/AmEveryWhereApiGateway.php' );
		$this->assertFileDoesNotExist( $root . '/src/Core/Api/HolvekApiGateway.php' );
		$this->assertFileExists( $root . '/src/Core/Api/QuinrelApiGateway.php' );
		$this->assertTrue( class_exists( \Quinrel\WordPress\Core\Api\QuinrelApiGateway::class ) );
	}

	public function testSourceAndTestsHaveNoLegacyNamespacePrefix(): void {
		$roots = array(
			dirname( __DIR__, 2 ) . '/src',
			dirname( __DIR__, 2 ) . '/tests',
			dirname( __DIR__, 2 ) . '/quinrel.php',
		);
		$stale = array();

		foreach ( $roots as $root ) {
			$files = is_file( $root ) ? array( new \SplFileInfo( $root ) ) : $this->phpFiles( $root );
			foreach ( $files as $file ) {
				$contents = file_get_contents( $file->getPathname() );
				if ( preg_match( '/(?:Holvek|AmEveryWhere)\\\\/', $contents ) ) {
					$stale[] = $file->getPathname();
				}
			}
		}

		$this->assertSame( array(), $stale, "Legacy namespace prefix remains in:\n" . implode( "\n", $stale ) );
	}

	/**
	 * @return list<\SplFileInfo>
	 */
	private function phpFiles( string $directory ): array {
		$files = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$files[] = $file;
			}
		}
		return $files;
	}
}
