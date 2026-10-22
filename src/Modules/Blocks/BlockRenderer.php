<?php

namespace Quinrel\WordPress\Modules\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BlockRenderer
 *
 * Front-end HTML rendering for Quinrel Gutenberg blocks:
 * FAQ, HowTo, Review, and Table of Contents.
 */
class BlockRenderer {

	/**
	 * Renders quinrel/faq block into accessible HTML5 details/summary accordions.
	 *
	 * @param array<string, mixed> $attrs
	 * @param string               $content
	 * @return string
	 */
	public static function renderFaq( array $attrs, string $content = '' ): string {
		$items = $attrs['items'] ?? array();
		if ( ! is_array( $items ) || empty( $items ) ) {
			return '';
		}

		$title = trim( (string) ( $attrs['title'] ?? '' ) );
		$html  = '<section class="quinrel-block-faq" aria-label="Frequently Asked Questions">';
		if ( '' !== $title ) {
			$html .= '<h3 class="quinrel-faq-heading">' . esc_html( $title ) . '</h3>';
		}

		$html .= '<div class="quinrel-faq-accordion-list">';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$q = trim( (string) ( $item['question'] ?? '' ) );
			$a = trim( (string) ( $item['answer'] ?? '' ) );
			if ( '' === $q || '' === $a ) {
				continue;
			}

			$html .= '<details class="quinrel-faq-item">';
			$html .= '<summary class="quinrel-faq-question">' . esc_html( $q ) . '</summary>';
			$html .= '<div class="quinrel-faq-answer">' . wp_kses_post( self::formatParagraphs( $a ) ) . '</div>';
			$html .= '</details>';
		}
		$html .= '</div></section>';

		return $html;
	}

	/**
	 * Renders quinrel/howto tutorial block.
	 *
	 * @param array<string, mixed> $attrs
	 * @param string               $content
	 * @return string
	 */
	public static function renderHowTo( array $attrs, string $content = '' ): string {
		$steps = $attrs['steps'] ?? array();
		if ( ! is_array( $steps ) || empty( $steps ) ) {
			return '';
		}

		$title = trim( (string) ( $attrs['title'] ?? 'Instructions' ) );
		$desc  = trim( (string) ( $attrs['description'] ?? '' ) );
		$time  = trim( (string) ( $attrs['totalTime'] ?? '' ) );

		$html  = '<article class="quinrel-block-howto">';
		$html .= '<header class="quinrel-howto-header">';
		$html .= '<h3 class="quinrel-howto-title">' . esc_html( $title ) . '</h3>';
		if ( '' !== $desc ) {
			$html .= '<p class="quinrel-howto-description">' . esc_html( $desc ) . '</p>';
		}
		if ( '' !== $time ) {
			$html .= '<div class="quinrel-howto-meta"><span class="quinrel-howto-time">⏱ ' . esc_html( $time ) . '</span></div>';
		}
		$html .= '</header>';

		if ( ! empty( $attrs['supplies'] ) && is_array( $attrs['supplies'] ) ) {
			$html .= '<div class="quinrel-howto-supplies"><strong>' . esc_html__( 'Supplies Needed:', 'quinrel' ) . '</strong><ul>';
			foreach ( $attrs['supplies'] as $s ) {
				$name  = is_string( $s ) ? $s : ( $s['name'] ?? '' );
				$html .= '<li>' . esc_html( $name ) . '</li>';
			}
			$html .= '</ul></div>';
		}

		$html .= '<ol class="quinrel-howto-step-list">';
		$pos   = 1;
		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}
			$sTitle = trim( (string) ( $step['title'] ?? ( 'Step ' . $pos ) ) );
			$sText  = trim( (string) ( $step['text'] ?? '' ) );

			$html .= '<li class="quinrel-howto-step-item">';
			$html .= '<h4 class="quinrel-step-title">' . esc_html( $sTitle ) . '</h4>';
			if ( ! empty( $step['imageUrl'] ) ) {
				$html .= '<img class="quinrel-step-image" src="' . esc_url( $step['imageUrl'] ) . '" alt="' . esc_attr( $sTitle ) . '" loading="lazy" />';
			}
			if ( '' !== $sText ) {
				$html .= '<div class="quinrel-step-text">' . wp_kses_post( self::formatParagraphs( $sText ) ) . '</div>';
			}
			$html .= '</li>';
			++$pos;
		}
		$html .= '</ol></article>';

		return $html;
	}

	/**
	 * Renders quinrel/review card with stars and pros/cons.
	 *
	 * @param array<string, mixed> $attrs
	 * @param string               $content
	 * @return string
	 */
	public static function renderReview( array $attrs, string $content = '' ): string {
		$itemName = trim( (string) ( $attrs['itemName'] ?? '' ) );
		$rating   = floatval( $attrs['ratingValue'] ?? 0.0 );
		if ( '' === $itemName || $rating <= 0.0 ) {
			return '';
		}

		$summary = trim( (string) ( $attrs['summary'] ?? '' ) );
		$pros    = $attrs['pros'] ?? array();
		$cons    = $attrs['cons'] ?? array();
		$btnUrl  = trim( (string) ( $attrs['affiliateUrl'] ?? '' ) );
		$btnText = trim( (string) ( $attrs['affiliateText'] ?? 'Check Availability' ) );

		$starsHtml = self::renderStars( $rating );

		$html  = '<section class="quinrel-block-review" aria-label="Review Card">';
		$html .= '<div class="quinrel-review-header">';
		$html .= '<h3 class="quinrel-review-title">' . esc_html( $itemName ) . '</h3>';
		$html .= '<div class="quinrel-review-rating" aria-label="' . esc_attr( sprintf( 'Rating: %.1f out of 5', $rating ) ) . '">';
		$html .= $starsHtml;
		$html .= '<span class="quinrel-rating-number">' . esc_html( number_format( $rating, 1 ) ) . ' / 5.0</span>';
		$html .= '</div></div>';

		if ( '' !== $summary ) {
			$html .= '<p class="quinrel-review-summary">' . esc_html( $summary ) . '</p>';
		}

		if ( ( is_array( $pros ) && ! empty( $pros ) ) || ( is_array( $cons ) && ! empty( $cons ) ) ) {
			$html .= '<div class="quinrel-review-pros-cons-grid">';
			if ( ! empty( $pros ) ) {
				$html .= '<div class="quinrel-pros-column"><strong>✓ ' . esc_html__( 'Pros', 'quinrel' ) . '</strong><ul>';
				foreach ( $pros as $p ) {
					$html .= '<li>' . esc_html( (string) $p ) . '</li>';
				}
				$html .= '</ul></div>';
			}
			if ( ! empty( $cons ) ) {
				$html .= '<div class="quinrel-cons-column"><strong>✗ ' . esc_html__( 'Cons', 'quinrel' ) . '</strong><ul>';
				foreach ( $cons as $c ) {
					$html .= '<li>' . esc_html( (string) $c ) . '</li>';
				}
				$html .= '</ul></div>';
			}
			$html .= '</div>';
		}

		if ( '' !== $btnUrl ) {
			$html .= '<div class="quinrel-review-cta"><a href="' . esc_url( $btnUrl ) . '" class="quinrel-review-button" target="_blank" rel="noopener nofollow sponsored">' . esc_html( $btnText ) . '</a></div>';
		}

		$html .= '</section>';
		return $html;
	}

	/**
	 * Renders quinrel/toc table of contents block.
	 *
	 * @param array<string, mixed> $attrs
	 * @param string               $content
	 * @return string
	 */
	public static function renderToc( array $attrs, string $content = '' ): string {
		$postId = get_the_ID();
		if ( ! $postId ) {
			return '';
		}

		$rawContent = (string) get_post_field( 'post_content', $postId );
		$headings   = self::extractHeadings( $rawContent );
		if ( empty( $headings ) ) {
			return '';
		}

		$title = trim( (string) ( $attrs['title'] ?? 'Table of Contents' ) );

		$html  = '<nav class="quinrel-block-toc" aria-label="Table of Contents">';
		$html .= '<div class="quinrel-toc-title">' . esc_html( $title ) . '</div>';
		$html .= '<ul class="quinrel-toc-list">';

		foreach ( $headings as $h ) {
			$indentClass = 'quinrel-toc-level-' . esc_attr( $h['level'] );
			$html       .= '<li class="' . $indentClass . '">';
			$html       .= '<a href="#' . esc_attr( $h['anchor'] ) . '">' . esc_html( $h['text'] ) . '</a>';
			$html       .= '</li>';
		}

		$html .= '</ul></nav>';
		return $html;
	}

	/**
	 * Extracts heading elements (h2, h3, h4) and assigns deterministic anchor slugs.
	 *
	 * @param string $content
	 * @return array<int, array{level: string, text: string, anchor: string}>
	 */
	public static function extractHeadings( string $content ): array {
		if ( '' === trim( $content ) ) {
			return array();
		}

		$pattern = '/<h([2-4])(?:\s+[^>]*)?>(.*?)<\/h\1>/is';
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$headings = array();
		$usedIds  = array();

		foreach ( $matches as $match ) {
			$level = $match[1];
			$text  = trim( wp_strip_all_tags( $match[2] ) );
			if ( '' === $text ) {
				continue;
			}

			$slug = self::slugify( $text );
			if ( '' === $slug ) {
				$slug = 'heading';
			}

			// Ensure anchor slug uniqueness
			$base = $slug;
			$idx  = 1;
			while ( isset( $usedIds[ $slug ] ) ) {
				$slug = $base . '-' . $idx;
				++$idx;
			}
			$usedIds[ $slug ] = true;

			$headings[] = array(
				'level'  => $level,
				'text'   => $text,
				'anchor' => $slug,
			);
		}

		return $headings;
	}

	/**
	 * Injects matching id attributes into headings in post content for in-page TOC jumping.
	 */
	public static function filterContentHeadingAnchors( string $content ): string {
		$isSingular = function_exists( 'is_singular' ) ? ( is_singular() || defined( 'QUINREL_TESTS' ) ) : true;
		if ( ! $isSingular || '' === trim( $content ) || false === strpos( $content, 'quinrel-block-toc' ) ) {
			return $content;
		}

		$usedIds = array();

		return (string) preg_replace_callback(
			'/<h([2-4])(?:\s+([^>]*))?>(.*?)<\/h\1>/is',
			static function ( array $headingMatch ) use ( &$usedIds ): string {
				$level = $headingMatch[1];
				$attrs = $headingMatch[2] ?? '';
				$inner = $headingMatch[3] ?? '';
				$text  = trim( wp_strip_all_tags( $inner ) );

				// If heading already has an id attribute, preserve it
				if ( 1 === preg_match( '/\bid=[\'"]([^\'"]+)[\'"]/i', $attrs, $idMatch ) ) {
					return $headingMatch[0];
				}

				$slug = self::slugify( $text );
				if ( '' === $slug ) {
					$slug = 'heading';
				}

				$base = $slug;
				$idx  = 1;
				while ( isset( $usedIds[ $slug ] ) ) {
					$slug = $base . '-' . $idx;
					++$idx;
				}
				$usedIds[ $slug ] = true;

				return sprintf( '<h%s id="%s"%s>%s</h%s>', $level, esc_attr( $slug ), $attrs ? ' ' . $attrs : '', $inner, $level );
			},
			$content
		);
	}

	/**
	 * Generates accessible visual star rating symbols.
	 */
	private static function renderStars( float $rating ): string {
		$fullStars  = (int) floor( $rating );
		$hasHalf    = ( $rating - $fullStars ) >= 0.4;
		$emptyStars = 5 - $fullStars - ( $hasHalf ? 1 : 0 );

		$stars  = '<span class="quinrel-stars" aria-hidden="true">';
		$stars .= str_repeat( '★', max( 0, $fullStars ) );
		if ( $hasHalf ) {
			$stars .= '½';
		}
		$stars .= str_repeat( '☆', max( 0, $emptyStars ) );
		$stars .= '</span> ';

		return $stars;
	}

	private static function formatParagraphs( string $text ): string {
		if ( function_exists( 'wpautop' ) ) {
			return wpautop( $text );
		}
		return '<p>' . nl2br( trim( $text ) ) . '</p>';
	}

	private static function slugify( string $text ): string {
		if ( function_exists( 'sanitize_title' ) ) {
			return sanitize_title( $text );
		}
		return strtolower( trim( (string) preg_replace( '/[^a-zA-Z0-9]+/', '-', $text ), '-' ) );
	}
}
