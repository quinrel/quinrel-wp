<?php

namespace Quinrel\WordPress\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages clean URLs and permalinks for WooCommerce product categories and archives.
 * Supports stripping the /product-category/ base URL prefix cleanly and safely.
 */
class WooCommercePermalinkManager {

	public const OPTION_STRIP_CATEGORY_BASE = 'quinrel_woo_strip_category_base';

	public function register(): void {
		if ( ! $this->isWooCommerceActive() ) {
			return;
		}

		if ( $this->isStripCategoryBaseEnabled() ) {
			add_filter( 'term_link', array( $this, 'filterTermLink' ), 10, 3 );
			add_action( 'init', array( $this, 'addCategoryRewriteRules' ) );
			add_filter( 'request', array( $this, 'resolveCategoryRequest' ) );
		}
	}

	public function isWooCommerceActive(): bool {
		return class_exists( '\WC_Product' ) || function_exists( 'wc_get_product' );
	}

	public function isStripCategoryBaseEnabled(): bool {
		return (bool) get_option( self::OPTION_STRIP_CATEGORY_BASE, false );
	}

	/**
	 * Filters category term link to remove product-category base.
	 *
	 * @param string   $url      Term link.
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy slug.
	 */
	public function filterTermLink( string $url, $term, string $taxonomy ): string {
		if ( 'product_cat' !== $taxonomy ) {
			return $url;
		}

		$categoryBase = $this->getCategoryBase();
		if ( '' !== $categoryBase && str_contains( $url, '/' . $categoryBase . '/' ) ) {
			return str_replace( '/' . $categoryBase . '/', '/', $url );
		}

		return $url;
	}

	/**
	 * Adds rewrite rules so clean URLs map to product_cat query variables.
	 */
	public function addCategoryRewriteRules(): void {
		add_rewrite_rule( '^([^/]+)/page/([0-9]+)/?$', 'index.php?product_cat=$matches[1]&paged=$matches[2]', 'top' );
	}

	/**
	 * Resolves request query for stripped category bases.
	 *
	 * @param array<string, mixed> $queryVars
	 * @return array<string, mixed>
	 */
	public function resolveCategoryRequest( array $queryVars ): array {
		if ( ! empty( $queryVars['name'] ) && empty( $queryVars['post_type'] ) ) {
			$slug = sanitize_title( (string) $queryVars['name'] );
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				unset( $queryVars['name'] );
				$queryVars['product_cat'] = $slug;
			}
		}

		return $queryVars;
	}

	/**
	 * Retrieves the active WooCommerce product category base slug.
	 */
	private function getCategoryBase(): string {
		$permalinkOptions = get_option( 'woocommerce_permalinks', array() );
		$categoryBase     = $permalinkOptions['category_base'] ?? 'product-category';
		return trim( (string) $categoryBase, '/' );
	}
}
