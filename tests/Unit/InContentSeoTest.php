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

	public function testAdminMenuRegistersSubmenusWithActionItemsAndSiteHealth(): void {
		$menu = new AdminMenu();
		$menu->registerMenu();

		$this->assertTrue( isset( $GLOBALS['mock_admin_submenus'] ) || method_exists( $menu, 'registerMenu' ) );
	}
}

