<?php

namespace Quinrel\WordPress\Modules\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BlockRegistry
 *
 * Registers native Gutenberg blocks: FAQ, HowTo, Review, and Table of Contents.
 */
class BlockRegistry {

	public function register(): void {
		add_action( 'init', array( $this, 'registerBlocks' ) );
		add_filter( 'the_content', array( BlockRenderer::class, 'filterContentHeadingAnchors' ), 20 );
	}

	public function registerBlocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( defined( 'QUINREL_PLUGIN_URL' ) && function_exists( 'wp_register_style' ) ) {
			wp_register_style(
				'quinrel-blocks-css',
				QUINREL_PLUGIN_URL . 'assets/css/blocks.css',
				array(),
				defined( 'QUINREL_VERSION' ) ? QUINREL_VERSION : '1.2.0'
			);
		}

		// 1. FAQ Accordion Block
		register_block_type(
			'quinrel/faq',
			array(
				'api_version'     => 3,
				'title'           => __( 'Quinrel FAQ Accordion', 'quinrel' ),
				'category'        => 'text',
				'style'           => 'quinrel-blocks-css',
				'description'     => __( 'Accessible FAQ accordion with automatic Google FAQPage schema.', 'quinrel' ),
				'render_callback' => array( BlockRenderer::class, 'renderFaq' ),
				'attributes'      => array(
					'title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'items' => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

		// 2. HowTo Block
		register_block_type(
			'quinrel/howto',
			array(
				'api_version'     => 3,
				'title'           => __( 'Quinrel HowTo Guide', 'quinrel' ),
				'category'        => 'text',
				'style'           => 'quinrel-blocks-css',
				'description'     => __( 'Step-by-step tutorial with automatic Schema.org HowTo markup.', 'quinrel' ),
				'render_callback' => array( BlockRenderer::class, 'renderHowTo' ),
				'attributes'      => array(
					'title'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'description'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'totalTime'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'estimatedCost' => array(
						'type'    => 'string',
						'default' => '',
					),
					'currency'      => array(
						'type'    => 'string',
						'default' => 'USD',
					),
					'tools'         => array(
						'type'    => 'array',
						'default' => array(),
					),
					'supplies'      => array(
						'type'    => 'array',
						'default' => array(),
					),
					'steps'         => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

		// 3. Review Card Block
		register_block_type(
			'quinrel/review',
			array(
				'api_version'     => 3,
				'title'           => __( 'Quinrel Review Card', 'quinrel' ),
				'category'        => 'text',
				'style'           => 'quinrel-blocks-css',
				'description'     => __( 'Product/critique review card with star ratings and Review schema.', 'quinrel' ),
				'render_callback' => array( BlockRenderer::class, 'renderReview' ),
				'attributes'      => array(
					'itemName'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'ratingValue'   => array(
						'type'    => 'number',
						'default' => 5.0,
					),
					'itemType'      => array(
						'type'    => 'string',
						'default' => 'Product',
					),
					'summary'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'pros'          => array(
						'type'    => 'array',
						'default' => array(),
					),
					'cons'          => array(
						'type'    => 'array',
						'default' => array(),
					),
					'affiliateUrl'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'affiliateText' => array(
						'type'    => 'string',
						'default' => 'Check Price',
					),
				),
			)
		);

		// 4. Table of Contents Block
		register_block_type(
			'quinrel/toc',
			array(
				'api_version'     => 3,
				'title'           => __( 'Quinrel Table of Contents', 'quinrel' ),
				'category'        => 'text',
				'style'           => 'quinrel-blocks-css',
				'description'     => __( 'Dynamic heading navigation with automatic anchor links.', 'quinrel' ),
				'render_callback' => array( BlockRenderer::class, 'renderToc' ),
				'attributes'      => array(
					'title' => array(
						'type'    => 'string',
						'default' => 'Table of Contents',
					),
				),
			)
		);
	}
}
