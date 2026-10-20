<?php

namespace Quinrel\WordPress\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side SEO score calculator aligned with src/assets/js/lib/seo-score.js.
 */
class SeoScoreCalculator {

	/**
	 * Calculate the 0-100 SEO score for a post ID.
	 *
	 * @param int  $postId      Post ID.
	 * @param bool $preferSaved Whether to return the stored _quinrel_seo_score meta when present.
	 * @return int Score between 0 and 100.
	 */
	public static function calculateForPost( int $postId, bool $preferSaved = true ): int {
		if ( $preferSaved ) {
			$saved = get_post_meta( $postId, '_quinrel_seo_score', true );
			if ( is_numeric( $saved ) && (int) $saved > 0 ) {
				return max( 0, min( 100, (int) $saved ) );
			}
		}

		$post = get_post( $postId );
		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}

		$rawMetaTitle = (string) get_post_meta( $postId, '_quinrel_meta_title', true );
		$rawMetaDesc  = (string) get_post_meta( $postId, '_quinrel_meta_description', true );
		$focusKeyword = (string) get_post_meta( $postId, '_quinrel_focus_keyword', true );
		$cornerstone  = 'yes' === (string) get_post_meta( $postId, '_quinrel_is_cornerstone', true );

		$metaTitle = '' !== $rawMetaTitle ? SmartTagResolver::resolve( $rawMetaTitle, $postId ) : '';
		$metaDesc  = '' !== $rawMetaDesc ? SmartTagResolver::resolve( $rawMetaDesc, $postId ) : '';

		return self::calculate(
			array(
				'title'           => (string) $post->post_title,
				'content'         => (string) $post->post_content,
				'focusKeyword'    => $focusKeyword,
				'metaTitle'       => $metaTitle,
				'metaDescription' => $metaDesc,
				'isCornerstone'   => $cornerstone,
			)
		);
	}

	/**
	 * Calculate the 0-100 SEO score from raw fields, matching calculateSeoScore() in JS.
	 *
	 * @param array<string, mixed> $params Scoring parameters.
	 * @return int Score between 0 and 100.
	 */
	public static function calculate( array $params ): int {
		$title           = isset( $params['title'] ) ? (string) $params['title'] : '';
		$content         = isset( $params['content'] ) ? (string) $params['content'] : '';
		$focusKeyword    = isset( $params['focusKeyword'] ) ? trim( (string) $params['focusKeyword'] ) : '';
		$metaTitle       = isset( $params['metaTitle'] ) ? trim( (string) $params['metaTitle'] ) : '';
		$metaDescription = isset( $params['metaDescription'] ) ? trim( (string) $params['metaDescription'] ) : '';
		$isCornerstone   = ! empty( $params['isCornerstone'] );

		$earned = 0;
		$total  = 0;

		$plainContent = (string) preg_replace( '/<!--.*?-->/s', ' ', $content );
		$plainContent = wp_strip_all_tags( $plainContent );
		$plainContent = str_replace( '&nbsp;', ' ', $plainContent );
		$plainContent = trim( (string) preg_replace( '/\s+/', ' ', $plainContent ) );

		$wordCount      = '' === $plainContent ? 0 : (int) preg_match_all( '/\b\w+\b/u', $plainContent );
		$effectiveTitle = '' !== $metaTitle ? $metaTitle : $title;
		$lowerContent   = mb_strtolower( $plainContent );
		$lowerTitle     = mb_strtolower( $title );
		$lowerKeyword   = '' !== $focusKeyword ? mb_strtolower( $focusKeyword ) : '';

		if ( '' !== $lowerKeyword ) {
			// Keyword in title (10 pts)
			$total += 10;
			if ( str_contains( $lowerTitle, $lowerKeyword ) ) {
				$earned += 10;
			}

			// Keyword in first paragraph / first 300 chars (8 pts)
			$total    += 8;
			$firstPara = mb_substr( $lowerContent, 0, 300 );
			if ( str_contains( $firstPara, $lowerKeyword ) ) {
				$earned += 8;
			}

			// Keyword in content (7 pts)
			$total += 7;
			if ( str_contains( $lowerContent, $lowerKeyword ) ) {
				$earned += 7;
			}

			// Keyword in subheading H2-H6 (6 pts)
			$total         += 6;
			$kwInSubheading = false;
			if ( preg_match_all( '/<h[2-6][^>]*>([\s\S]*?)<\/h[2-6]>/i', $content, $headingMatches ) ) {
				foreach ( $headingMatches[1] as $headingHtml ) {
					if ( str_contains( mb_strtolower( wp_strip_all_tags( $headingHtml ) ), $lowerKeyword ) ) {
						$kwInSubheading = true;
						break;
					}
				}
			}
			if ( $kwInSubheading ) {
				$earned += 6;
			}

			// Keyword in paragraphs (5 pts)
			$total         += 5;
			$kwInParagraphs = false;
			if ( preg_match_all( '/<p[^>]*>([\s\S]*?)<\/p>/i', $content, $paraMatches ) ) {
				foreach ( $paraMatches[1] as $paraHtml ) {
					if ( str_contains( mb_strtolower( wp_strip_all_tags( $paraHtml ) ), $lowerKeyword ) ) {
						$kwInParagraphs = true;
						break;
					}
				}
			} elseif ( str_contains( $lowerContent, $lowerKeyword ) ) {
				$kwInParagraphs = true;
			}
			if ( $kwInParagraphs ) {
				$earned += 5;
			}

			// Keyword in image ALT attributes (5 pts)
			$total  += 5;
			$kwInAlt = false;
			if ( preg_match_all( '/<img[^>]*alt=["\']([^"\']*)["\'][^>]*>/i', $content, $altMatches ) ) {
				foreach ( $altMatches[1] as $altText ) {
					if ( str_contains( mb_strtolower( $altText ), $lowerKeyword ) ) {
						$kwInAlt = true;
						break;
					}
				}
			}
			if ( $kwInAlt ) {
				$earned += 5;
			}

			// Keyword density 0.5-2.5% (8 pts)
			$total        += 8;
			$kwOccurrences = substr_count( $lowerContent, $lowerKeyword );
			$density       = $wordCount > 0 ? ( $kwOccurrences / $wordCount ) * 100 : 0.0;
			if ( $density >= 0.5 && $density <= 2.5 ) {
				$earned += 8;
			}

			// Keyword in meta title (7 pts)
			$total += 7;
			if ( str_contains( mb_strtolower( $effectiveTitle ), $lowerKeyword ) ) {
				$earned += 7;
			}
		} else {
			$total += 56;
		}

		// Meta title length <= 60 chars (8 pts)
		$total   += 8;
		$titleLen = mb_strlen( $effectiveTitle );
		if ( $titleLen > 0 && $titleLen <= 60 ) {
			$earned += 8;
		}

		// Meta description 120-160 chars (9 pts)
		$total  += 9;
		$descLen = mb_strlen( $metaDescription );
		if ( $descLen >= 120 && $descLen <= 160 ) {
			$earned += 9;
		}

		// Content length (8 pts)
		$total   += 8;
		$minWords = $isCornerstone ? 1000 : 300;
		if ( $wordCount >= $minWords ) {
			$earned += 8;
		}

		if ( $isCornerstone ) {
			$total    += 5;
			$linkCount = preg_match_all( '/<a\s+[^>]*href=/i', $content );
			if ( is_int( $linkCount ) && $linkCount >= 3 ) {
				$earned += 5;
			}
		}

		return $total > 0 ? (int) round( ( $earned / $total ) * 100 ) : 0;
	}
}
