<?php

namespace Quinrel\WordPress\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerceProductSchema
 *
 * Auto-generates Product JSON-LD for WooCommerce product posts,
 * including variable products and AggregateRating from WC reviews.
 *
 * BL-026 — Only boots if WooCommerce is active.
 */
class WooCommerceProductSchema {

	public function register(): void {
		if ( ! $this->isWooCommerceActive() ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'outputProductSchema' ), 5 );
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function isWooCommerceActive(): bool {
		$active = ( class_exists( '\WC_Product' ) || function_exists( 'wc_get_product' ) ) && ( ! defined( 'QUINREL_TESTS' ) || ! empty( $GLOBALS['mock_woocommerce_active'] ) );
		return (bool) apply_filters( 'quinrel_is_woocommerce_active', $active );
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		register_rest_route(
			'quinrel/v1',
			'/schema/product',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProductSchema' ),
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function getProductSchema( \WP_REST_Request $request ): \WP_REST_Response {
		$postId = (int) $request->get_param( 'post_id' );
		$schema = $this->buildProductSchema( $postId );

		if ( $schema === null ) {
			return new \WP_Error( 'not_found', __( 'Product not found or WooCommerce not active.', 'quinrel' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'schema' => $schema ) );
	}

	// ── Schema output ─────────────────────────────────────────────────────────

	public function outputProductSchema(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		$postId = get_the_ID();
		if ( ! $postId ) {
			return;
		}

		$schema = $this->buildProductSchema( $postId );
		if ( $schema === null ) {
			return;
		}

		JsonLd::printScript( $schema );
	}

	public function buildProductSchema( int $postId ): ?array {
		if ( ! $this->isWooCommerceActive() ) {
			return null;
		}

		$product = wc_get_product( $postId );
		if ( ! $product ) {
			return null;
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product->get_name(),
			'url'         => get_permalink( $postId ),
			'description' => $product->get_short_description() ?: $product->get_description(),
			'sku'         => $product->get_sku() ?: null,
		);

		// Image
		$thumbId = $product->get_image_id();
		if ( $thumbId ) {
			$imgData = wp_get_attachment_image_src( $thumbId, 'full' );
			if ( $imgData ) {
				$schema['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $imgData[0],
					'width'  => $imgData[1],
					'height' => $imgData[2],
				);
			}
		}

		// Identifiers (GTIN, MPN, ISBN)
		$gtin = get_post_meta( $postId, '_quinrel_gtin', true ) ?: ( get_post_meta( $postId, '_gtin', true ) ?: get_post_meta( $postId, '_barcode', true ) );
		if ( ! empty( $gtin ) ) {
			$schema['gtin'] = (string) $gtin;
		}

		$mpn = get_post_meta( $postId, '_quinrel_mpn', true ) ?: ( get_post_meta( $postId, '_mpn', true ) ?: $product->get_sku() );
		if ( ! empty( $mpn ) ) {
			$schema['mpn'] = (string) $mpn;
		}

		$isbn = get_post_meta( $postId, '_quinrel_isbn', true ) ?: get_post_meta( $postId, '_isbn', true );
		if ( ! empty( $isbn ) ) {
			$schema['isbn'] = (string) $isbn;
		}

		// Brand from post meta, custom taxonomy, or attribute
		$brandName = get_post_meta( $postId, '_quinrel_brand', true );
		if ( empty( $brandName ) ) {
			$brandName = $product->get_attribute( 'pa_brand' ) ?: $product->get_attribute( 'brand' );
		}
		if ( empty( $brandName ) && taxonomy_exists( 'product_brand' ) ) {
			$brands = wp_get_post_terms( $postId, 'product_brand' );
			if ( ! empty( $brands ) && ! is_wp_error( $brands ) ) {
				$brandName = $brands[0]->name;
			}
		}
		if ( ! empty( $brandName ) ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => (string) $brandName,
			);
		}

		// Merchant Return Policy
		$returnWindow = (int) get_option( 'quinrel_woo_return_window_days', 30 );
		$returnPolicy = null;
		if ( $returnWindow > 0 ) {
			$returnPolicy = array(
				'@type'                => 'MerchantReturnPolicy',
				'applicableCountry'    => get_option( 'quinrel_woo_return_country', 'US' ),
				'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
				'merchantReturnDays'   => $returnWindow,
				'returnMethod'         => 'https://schema.org/ReturnByMail',
				'returnFees'           => 'https://schema.org/FreeReturn',
			);
		}

		// Offers — variable vs. simple
		if ( $product->is_type( 'variable' ) ) {
			$minPrice         = $product->get_variation_price( 'min' );
			$maxPrice         = $product->get_variation_price( 'max' );
			$schema['offers'] = array(
				'@type'           => 'AggregateOffer',
				'priceCurrency'   => get_woocommerce_currency(),
				'lowPrice'        => (string) $minPrice,
				'highPrice'       => (string) $maxPrice,
				'offerCount'      => count( $product->get_available_variations() ),
				'availability'    => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'             => get_permalink( $postId ),
				'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			);
			if ( $returnPolicy ) {
				$schema['offers']['hasMerchantReturnPolicy'] = $returnPolicy;
			}
		} else {
			$price            = $product->get_price();
			$schema['offers'] = array(
				'@type'           => 'Offer',
				'price'           => (string) $price,
				'priceCurrency'   => get_woocommerce_currency(),
				'availability'    => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'             => get_permalink( $postId ),
				'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			);
			if ( $returnPolicy ) {
				$schema['offers']['hasMerchantReturnPolicy'] = $returnPolicy;
			}
		}

		// AggregateRating from WooCommerce reviews
		$reviewCount = $product->get_review_count();
		$avgRating   = $product->get_average_rating();
		if ( $reviewCount > 0 && $avgRating > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $avgRating,
				'reviewCount' => $reviewCount,
				'bestRating'  => '5',
				'worstRating' => '1',
			);
		}

		// Remove null values
		return array_filter( $schema, fn( $v ) => $v !== null );
	}
}
