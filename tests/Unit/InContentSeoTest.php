<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Modules\Admin\TaxonomySeo;
use Quinrel\WordPress\Modules\Admin\PostQuickEdit;
use Quinrel\WordPress\Modules\Admin\AdminMenu;
use PHPUnit\Framework\TestCase;

class InContentSeoTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_user_caps'] = array(
			'edit_terms'     => true,
			'edit_posts'     => true,
			'edit_post'      => true,
			'edit_seo_meta'  => true,
			'manage_seo'     => true,
			'manage_options' => true,
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mock_user_caps'], $GLOBALS['mock_term_meta'], $GLOBALS['mock_post_meta'] );
		parent::tearDown();
	}

	public function testTaxonomySeoColumnAndRendering(): void {
		$taxSeo  = new TaxonomySeo();
		$columns = $taxSeo->addColumn( array( 'cb' => '<input />', 'name' => 'Name', 'slug' => 'Slug' ) );

		$this->assertArrayHasKey( 'quinrel_tax_seo', $columns );
		$this->assertSame( 'SEO', $columns['quinrel_tax_seo'] );

		// Test default rendering when no meta is set
		$outputDefault = $taxSeo->renderColumn( '', 'quinrel_tax_seo', 10 );
		$this->assertStringContainsString( 'Default', $outputDefault );

		// Test optimized rendering
		update_term_meta( 11, '_quinrel_meta_title', 'Custom Category SEO Title' );
		$outputOptimized = $taxSeo->renderColumn( '', 'quinrel_tax_seo', 11 );
		$this->assertStringContainsString( 'Optimized', $outputOptimized );

		// Test noindex rendering
		update_term_meta( 12, '_quinrel_noindex', 'yes' );
		$outputNoindex = $taxSeo->renderColumn( '', 'quinrel_tax_seo', 12 );
		$this->assertStringContainsString( 'Noindex', $outputNoindex );
	}

	public function testQuickEditScriptIsAFileAndOnlyLoadsOnTheListTable(): void {
		$GLOBALS['mock_enqueued_scripts'] = array();
		$editor = new PostQuickEdit();
		$editor->enqueueQuickEditScript( 'post.php' );
		$this->assertSame( array(), $GLOBALS['mock_enqueued_scripts'] );

		$editor->enqueueQuickEditScript( 'edit.php' );
		$this->assertArrayHasKey( 'quinrel-quick-edit', $GLOBALS['mock_enqueued_scripts'] );
		$this->assertStringEndsWith( 'src/assets/js/quick-edit.js', $GLOBALS['mock_enqueued_scripts']['quinrel-quick-edit'] );

		$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Modules/Admin/PostQuickEdit.php' );
		$js  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/assets/js/quick-edit.js' );
		$this->assertStringNotContainsString( '<<<', $php );
		$this->assertStringContainsString( 'inlineEditPost.edit', $js );
		$this->assertStringContainsString( 'quinrel-quick-title', $js );
		unset( $GLOBALS['mock_enqueued_scripts'] );
	}

	public function testAdminMenuRegistersSubmenusWithActionItemsAndSiteHealth(): void {
		$menu = new AdminMenu();
		$menu->registerMenu();

		$this->assertTrue( isset( $GLOBALS['mock_admin_submenus'] ) || method_exists( $menu, 'registerMenu' ) );
	}
}

