<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Breadcrumbs\BreadcrumbRenderer;
use Quinrel\WordPress\Modules\Schema\WooCommerceProductSchema;
use Quinrel\WordPress\Modules\Seo\OpenGraphGenerator;
use Quinrel\WordPress\Modules\Seo\WooCommercePermalinkManager;

class WooCommerceSeoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $mock_post_meta, $mock_posts, $mock_wp_options, $mock_terms, $mock_woocommerce_active;
        $mock_post_meta = [];
        $mock_posts = [];
        $mock_wp_options = [];
        $mock_terms = [];
        $mock_woocommerce_active = true;
    }

    protected function tearDown(): void
    {
        global $mock_woocommerce_active;
        $mock_woocommerce_active = false;
        parent::tearDown();
    }

    public function testProductSchemaIncludesGtinMpnIsbnAndReturnPolicy(): void
    {
        global $mock_posts, $mock_post_meta;

        $postId = 901;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Ultra High Performance Running Shoe',
            'post_type' => 'product',
        ]);

        $mock_post_meta[$postId] = [
            '_quinrel_gtin' => '0123456789012',
            '_quinrel_mpn' => 'PRO-RUN-900',
            '_quinrel_isbn' => '978-3-16-148410-0',
            '_quinrel_brand' => 'Apex Athletics',
        ];

        update_option('quinrel_woo_return_window_days', 45);
        update_option('quinrel_woo_return_country', 'US');

        $wooSchema = new WooCommerceProductSchema();
        $schema = $wooSchema->buildProductSchema($postId);

        $this->assertIsArray($schema);
        $this->assertSame('Product', $schema['@type']);
        $this->assertSame('0123456789012', $schema['gtin']);
        $this->assertSame('PRO-RUN-900', $schema['mpn']);
        $this->assertSame('978-3-16-148410-0', $schema['isbn']);
        $this->assertSame('Brand', $schema['brand']['@type']);
        $this->assertSame('Apex Athletics', $schema['brand']['name']);

        $this->assertArrayHasKey('offers', $schema);
        $this->assertArrayHasKey('hasMerchantReturnPolicy', $schema['offers']);
        $policy = $schema['offers']['hasMerchantReturnPolicy'];
        $this->assertSame('MerchantReturnPolicy', $policy['@type']);
        $this->assertSame(45, $policy['merchantReturnDays']);
        $this->assertSame('US', $policy['applicableCountry']);
    }

    public function testPrimaryCategoryBreadcrumbsForProduct(): void
    {
        global $mock_posts, $mock_post_meta, $mock_terms;

        $postId = 902;
        $mock_posts[$postId] = new \WP_Post([
            'ID' => $postId,
            'post_title' => 'Wireless Gaming Mouse',
            'post_type' => 'product',
        ]);

        // Define categories: Electronics (parent) -> Gaming Gear (child)
        $parentTerm = (object) [
            'term_id' => 10,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'taxonomy' => 'product_cat',
            'parent' => 0,
        ];
        $childTerm = (object) [
            'term_id' => 11,
            'name' => 'Gaming Gear',
            'slug' => 'gaming-gear',
            'taxonomy' => 'product_cat',
            'parent' => 10,
        ];

        $mock_terms['product_cat'][10] = $parentTerm;
        $mock_terms['product_cat'][11] = $childTerm;

        // Set Gaming Gear as primary category
        $mock_post_meta[$postId] = [
            '_quinrel_primary_product_cat' => 11,
        ];

        $renderer = new BreadcrumbRenderer();
        $items = $renderer->itemsForPost($postId);

        $this->assertIsArray($items);
        $names = array_column($items, 'name');
        $this->assertContains('Home', $names);
        $this->assertContains('Electronics', $names);
        $this->assertContains('Gaming Gear', $names);
        $this->assertContains('Wireless Gaming Mouse', $names);
    }

    public function testStripCategoryBaseFilter(): void
    {
        $manager = new WooCommercePermalinkManager();
        $originalUrl = 'https://example.com/product-category/apparel/shoes/';
        $filtered = $manager->filterTermLink($originalUrl, (object) ['term_id' => 5], 'product_cat');

        $this->assertSame('https://example.com/apparel/shoes/', $filtered);
    }
}
