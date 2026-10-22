<?php

namespace Quinrel\WordPress\Modules\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BlockSchemaExtractor
 *
 * TASK-BLK-01..04: Parses Gutenberg post blocks and automatically generates
 * Schema.org structured data for FAQPage, HowTo, Review, and ItemList (TOC).
 */
class BlockSchemaExtractor {

	/**
	 * Extracts all schema nodes from blocks within a post.
	 *
	 * @param int $postId
	 * @return array<int, array<string, mixed>>
	 */
	public static function extractBlockSchemas( int $postId ): array {
		if ( $postId <= 0 || ! function_exists( 'parse_blocks' ) ) {
			return array();
		}

		$rawContent = (string) get_post_field( 'post_content', $postId );
		if ( '' === trim( $rawContent ) ) {
			return array();
		}

		$blocks = parse_blocks( $rawContent );
		if ( empty( $blocks ) ) {
			return array();
		}

		$nodes = array();
		self::traverseBlocks( $blocks, $postId, $nodes );

		return $nodes;
	}

	/**
	 * Recursively traverses parsed blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks
	 * @param int                              $postId
	 * @param array<int, array<string, mixed>> $nodes Reference to collected nodes.
	 */
	private static function traverseBlocks( array $blocks, int $postId, array &$nodes ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$blockName = (string) ( $block['blockName'] ?? '' );
			$attrs     = $block['attrs'] ?? array();

			switch ( $blockName ) {
				case 'quinrel/faq':
					$faqNode = self::buildFaqSchema( $attrs, $postId );
					if ( ! empty( $faqNode ) ) {
						$nodes[] = $faqNode;
					}
					break;

				case 'quinrel/howto':
					$howtoNode = self::buildHowToSchema( $attrs, $postId );
					if ( ! empty( $howtoNode ) ) {
						$nodes[] = $howtoNode;
					}
					break;

				case 'quinrel/review':
					$reviewNode = self::buildReviewSchema( $attrs, $postId );
					if ( ! empty( $reviewNode ) ) {
						$nodes[] = $reviewNode;
					}
					break;

				case 'quinrel/toc':
					$tocNode = self::buildTocSchema( $attrs, $postId );
					if ( ! empty( $tocNode ) ) {
						$nodes[] = $tocNode;
					}
					break;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::traverseBlocks( $block['innerBlocks'], $postId, $nodes );
			}
		}
	}

	/**
	 * Builds FAQPage schema node from quinrel/faq block attributes.
	 *
	 * @param array<string, mixed> $attrs
	 * @param int                  $postId
	 * @return array<string, mixed>
	 */
	public static function buildFaqSchema( array $attrs, int $postId ): array {
		$items = $attrs['items'] ?? array();
		if ( ! is_array( $items ) || empty( $items ) ) {
			return array();
		}

		$questions = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$q = trim( (string) ( $item['question'] ?? '' ) );
			$a = trim( (string) ( $item['answer'] ?? '' ) );
			if ( '' !== $q && '' !== $a ) {
				$questions[] = array(
					'@type'          => 'Question',
					'name'           => $q,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_kses_post( $a ),
					),
				);
			}
		}

		if ( empty( $questions ) ) {
			return array();
		}

		$permalink = get_permalink( $postId );

		return array(
			'@type'      => 'FAQPage',
			'@id'        => $permalink . '#faq',
			'mainEntity' => $questions,
		);
	}

	/**
	 * Builds HowTo schema node from quinrel/howto block attributes.
	 *
	 * @param array<string, mixed> $attrs
	 * @param int                  $postId
	 * @return array<string, mixed>
	 */
	public static function buildHowToSchema( array $attrs, int $postId ): array {
		$name  = trim( (string) ( $attrs['title'] ?? get_the_title( $postId ) ) );
		$steps = $attrs['steps'] ?? array();
		if ( ! is_array( $steps ) || empty( $steps ) ) {
			return array();
		}

		$howToSteps = array();
		$pos        = 1;
		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}
			$sTitle = trim( (string) ( $step['title'] ?? ( 'Step ' . $pos ) ) );
			$sText  = trim( (string) ( $step['text'] ?? '' ) );
			if ( '' === $sText && '' === $sTitle ) {
				continue;
			}

			$stepObj = array(
				'@type'    => 'HowToStep',
				'position' => $pos,
				'name'     => $sTitle,
				'text'     => wp_kses_post( $sText ),
			);

			if ( ! empty( $step['imageUrl'] ) ) {
				$stepObj['image'] = esc_url_raw( (string) $step['imageUrl'] );
			}

			$howToSteps[] = $stepObj;
			++$pos;
		}

		if ( empty( $howToSteps ) ) {
			return array();
		}

		$permalink = get_permalink( $postId );
		$node      = array(
			'@type' => 'HowTo',
			'@id'   => $permalink . '#howto',
			'name'  => $name,
			'step'  => $howToSteps,
		);

		if ( ! empty( $attrs['description'] ) ) {
			$node['description'] = sanitize_text_field( (string) $attrs['description'] );
		}
		if ( ! empty( $attrs['totalTime'] ) ) {
			$node['totalTime'] = sanitize_text_field( (string) $attrs['totalTime'] );
		}
		if ( ! empty( $attrs['estimatedCost'] ) ) {
			$node['estimatedCost'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => sanitize_text_field( (string) ( $attrs['currency'] ?? 'USD' ) ),
				'value'    => sanitize_text_field( (string) $attrs['estimatedCost'] ),
			);
		}
		if ( ! empty( $attrs['tools'] ) && is_array( $attrs['tools'] ) ) {
			$node['tool'] = array_map(
				static fn( $t ) => array(
					'@type' => 'HowToTool',
					'name'  => sanitize_text_field( is_string( $t ) ? $t : ( $t['name'] ?? '' ) ),
				),
				array_filter( $attrs['tools'] )
			);
		}
		if ( ! empty( $attrs['supplies'] ) && is_array( $attrs['supplies'] ) ) {
			$node['supply'] = array_map(
				static fn( $s ) => array(
					'@type' => 'HowToSupply',
					'name'  => sanitize_text_field( is_string( $s ) ? $s : ( $s['name'] ?? '' ) ),
				),
				array_filter( $attrs['supplies'] )
			);
		}

		return $node;
	}

	/**
	 * Builds Review schema node from quinrel/review block attributes.
	 *
	 * @param array<string, mixed> $attrs
	 * @param int                  $postId
	 * @return array<string, mixed>
	 */
	public static function buildReviewSchema( array $attrs, int $postId ): array {
		$itemName = trim( (string) ( $attrs['itemName'] ?? '' ) );
		$rating   = floatval( $attrs['ratingValue'] ?? 0.0 );
		if ( '' === $itemName || $rating <= 0.0 ) {
			return array();
		}

		$permalink = get_permalink( $postId );
		$summary   = trim( (string) ( $attrs['summary'] ?? '' ) );
		$itemType  = sanitize_text_field( (string) ( $attrs['itemType'] ?? 'Product' ) );

		$authorName = (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $postId ) );
		if ( '' === $authorName ) {
			$authorName = (string) get_bloginfo( 'name' );
		}

		$node = array(
			'@type'         => 'Review',
			'@id'           => $permalink . '#review',
			'itemReviewed'  => array(
				'@type' => $itemType,
				'name'  => $itemName,
			),
			'reviewRating'  => array(
				'@type'       => 'Rating',
				'ratingValue' => min( 5.0, max( 1.0, $rating ) ),
				'bestRating'  => 5,
				'worstRating' => 1,
			),
			'author'        => array(
				'@type' => 'Person',
				'name'  => $authorName,
			),
			'reviewBody'    => '' !== $summary ? $summary : get_the_title( $postId ),
			'datePublished' => get_the_date( 'c', $postId ),
		);

		if ( ! empty( $attrs['pros'] ) && is_array( $attrs['pros'] ) ) {
			$node['positiveNotes'] = array(
				'@type'           => 'ItemList',
				'itemListElement' => array_map(
					static fn( $pro, $idx ) => array(
						'@type'    => 'ListItem',
						'position' => $idx + 1,
						'name'     => sanitize_text_field( (string) $pro ),
					),
					array_values( array_filter( $attrs['pros'] ) ),
					array_keys( array_values( array_filter( $attrs['pros'] ) ) )
				),
			);
		}

		if ( ! empty( $attrs['cons'] ) && is_array( $attrs['cons'] ) ) {
			$node['negativeNotes'] = array(
				'@type'           => 'ItemList',
				'itemListElement' => array_map(
					static fn( $con, $idx ) => array(
						'@type'    => 'ListItem',
						'position' => $idx + 1,
						'name'     => sanitize_text_field( (string) $con ),
					),
					array_values( array_filter( $attrs['cons'] ) ),
					array_keys( array_values( array_filter( $attrs['cons'] ) ) )
				),
			);
		}

		return $node;
	}

	/**
	 * Builds ItemList schema node for Table of Contents.
	 *
	 * @param array<string, mixed> $attrs
	 * @param int                  $postId
	 * @return array<string, mixed>
	 */
	public static function buildTocSchema( array $attrs, int $postId ): array {
		$content = (string) get_post_field( 'post_content', $postId );
		if ( '' === trim( $content ) ) {
			return array();
		}

		$headings = BlockRenderer::extractHeadings( $content );
		if ( empty( $headings ) ) {
			return array();
		}

		$permalink = get_permalink( $postId );
		$listItems = array();
		$pos       = 1;

		foreach ( $headings as $h ) {
			$listItems[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => $h['text'],
				'url'      => $permalink . '#' . $h['anchor'],
			);
			++$pos;
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => $permalink . '#toc',
			'name'            => sanitize_text_field( (string) ( $attrs['title'] ?? 'Table of Contents' ) ),
			'itemListElement' => $listItems,
		);
	}
}
