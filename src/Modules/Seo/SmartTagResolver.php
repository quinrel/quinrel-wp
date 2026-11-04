<?php

namespace Quinrel\WordPress\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves Quinrel ({{title}}) and migrated competitor (Yoast %%title%%,
 * Rank Math %title%, AIOSEO #post_title) smart tags into clean strings,
 * and strips blocks/shortcodes from auto-generated content snippets.
 */
class SmartTagResolver {

	/**
	 * Fast-path resolution of smart tag templates.
	 */
	public static function resolve( string $template, int $postId = 0 ): string {
		if ( '' === $template || false === strpbrk( $template, '%{#' ) ) {
			return $template;
		}

		$resolvedId = $postId > 0 ? $postId : ( function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0 );

		// Resolve dynamic ACF and custom post meta tags first
		if ( false !== strpos( $template, 'acf:' ) || false !== strpos( $template, 'cf:' ) || false !== strpos( $template, 'custom_field:' ) ) {
			$template = (string) preg_replace_callback(
				'/(?:%%(?:acf|cf|custom_field):([a-zA-Z0-9_\-]+)%%|\{\{\s*(?:acf|cf|custom_field):([a-zA-Z0-9_\-]+)\s*\}\}|%(?:acf|cf):([a-zA-Z0-9_\-]+)%|#(?:acf|cf):([a-zA-Z0-9_\-]+))/i',
				static function ( array $matches ) use ( $resolvedId ): string {
					$fieldName = ( '' !== ( $matches[1] ?? '' ) ) ? $matches[1] : (
						( '' !== ( $matches[2] ?? '' ) ) ? $matches[2] : (
							( '' !== ( $matches[3] ?? '' ) ) ? $matches[3] : ( $matches[4] ?? '' )
						)
					);
					return self::resolveCustomField( $fieldName, $resolvedId );
				},
				$template
			);
		}

		$sep = (string) get_option( 'quinrel_title_separator', '-' );
		if ( '' === trim( $sep ) ) {
			$sep = '-';
		}

		$paged     = function_exists( 'get_query_var' ) ? (int) get_query_var( 'paged', 0 ) : 0;
		$pageLabel = $paged > 1 ? sprintf(
			/* translators: %d: current page number. */
			__( 'Page %d', 'quinrel' ),
			$paged
		) : '';

		$replacements = array(
			'title'            => static fn(): string => $resolvedId > 0 ? (string) get_the_title( $resolvedId ) : (string) get_the_title(),
			'post_title'       => static fn(): string => $resolvedId > 0 ? (string) get_the_title( $resolvedId ) : (string) get_the_title(),
			'sitename'         => static fn(): string => (string) get_bloginfo( 'name' ),
			'site_title'       => static fn(): string => (string) get_bloginfo( 'name' ),
			'sitetitle'        => static fn(): string => (string) get_bloginfo( 'name' ),
			'sitedesc'         => static fn(): string => (string) get_bloginfo( 'description' ),
			'tagline'          => static fn(): string => (string) get_bloginfo( 'description' ),
			'sep'              => static fn(): string => $sep,
			'separator_sa'     => static fn(): string => $sep,
			'page'             => static fn(): string => $pageLabel,
			'pagenumber'       => static fn(): string => $paged > 1 ? (string) $paged : '1',
			'pagetotal'        => static fn(): string => '1',
			'currentyear'      => static fn(): string => gmdate( 'Y' ),
			'current_year'     => static fn(): string => gmdate( 'Y' ),
			'date'             => static fn(): string => (string) get_the_date( '', $resolvedId ),
			'post_date'        => static fn(): string => (string) get_the_date( '', $resolvedId ),
			'modified'         => static fn(): string => (string) get_the_modified_date( '', $resolvedId ),
			'excerpt'          => static fn(): string => self::resolveExcerpt( $resolvedId ),
			'excerpt_only'     => static fn(): string => self::cleanContentSnippet( (string) get_post_field( 'post_excerpt', $resolvedId ), 25 ),
			'post_excerpt'     => static fn(): string => self::resolveExcerpt( $resolvedId ),
			'name'             => static fn(): string => self::resolveAuthorName( $resolvedId ),
			'author'           => static fn(): string => self::resolveAuthorName( $resolvedId ),
			'author_name'      => static fn(): string => self::resolveAuthorName( $resolvedId ),
			'category'         => static fn(): string => self::resolvePrimaryCategory( $resolvedId ),
			'primary_category' => static fn(): string => self::resolvePrimaryCategory( $resolvedId ),
			'categories'       => static fn(): string => self::resolvePrimaryCategory( $resolvedId ),
		);

		$output = preg_replace_callback(
			'/(?:%%([a-z0-9_]+)%%|\{\{\s*([a-z0-9_]+)\s*\}\}|%([a-z0-9_]+)%|#([a-z0-9_]+))/i',
			static function ( array $matches ) use ( $replacements ): string {
				$token = strtolower(
					( '' !== ( $matches[1] ?? '' ) ) ? $matches[1] : (
						( '' !== ( $matches[2] ?? '' ) ) ? $matches[2] : (
							( '' !== ( $matches[3] ?? '' ) ) ? $matches[3] : ( $matches[4] ?? '' )
						)
					)
				);
				if ( isset( $replacements[ $token ] ) ) {
					return (string) ( $replacements[ $token ] )();
				}
				return $matches[0];
			},
			$template
		);

		if ( ! is_string( $output ) ) {
			return $template;
		}

		// Collapse duplicate whitespace and duplicate adjacent separators caused by empty tokens (e.g., %%page%%)
		$quotedSep = preg_quote( $sep, '/' );
		$output    = (string) preg_replace( '/\s+/', ' ', $output );
		$output    = (string) preg_replace( '/(?:\s+' . $quotedSep . '\s+){2,}/', ' ' . $sep . ' ', $output );
		$output    = (string) preg_replace( '/^\s*' . $quotedSep . '\s+|\s+' . $quotedSep . '\s*$/', '', $output );

		return trim( $output );
	}

	/**
	 * Strip Gutenberg block comments, shortcodes, and HTML tags before trimming to word limit.
	 */
	public static function cleanContentSnippet( string $rawContent, int $wordLimit = 25 ): string {
		if ( '' === trim( $rawContent ) ) {
			return '';
		}

		$cleaned = $rawContent;
		if ( function_exists( 'excerpt_remove_blocks' ) ) {
			$cleaned = excerpt_remove_blocks( $cleaned );
		} else {
			$cleaned = (string) preg_replace( '/<!--\s+\/?wp:.*?-->/s', '', $cleaned );
		}

		if ( function_exists( 'strip_shortcodes' ) ) {
			$cleaned = strip_shortcodes( $cleaned );
		}

		// Also strip any unregistered bracketed shortcodes (e.g. [vc_row], [contact-form-7 id="1"])
		$cleaned = (string) preg_replace( '/\[\/?[a-zA-Z0-9_\-]+(?:\s[^\]]*)?\]/', ' ', $cleaned );
		$cleaned = wp_strip_all_tags( $cleaned );
		$cleaned = (string) preg_replace( '/\s+/', ' ', trim( $cleaned ) );

		if ( '' === $cleaned ) {
			return '';
		}

		return wp_trim_words( $cleaned, $wordLimit );
	}

	private static function resolveExcerpt( int $postId ): string {
		if ( $postId <= 0 ) {
			return '';
		}
		$excerpt = (string) get_post_field( 'post_excerpt', $postId );
		if ( '' !== trim( $excerpt ) ) {
			return self::cleanContentSnippet( $excerpt, 25 );
		}
		return self::cleanContentSnippet( (string) get_post_field( 'post_content', $postId ), 25 );
	}

	private static function resolveAuthorName( int $postId ): string {
		$authorId = $postId > 0 ? (int) get_post_field( 'post_author', $postId ) : 0;
		return (string) get_the_author_meta( 'display_name', $authorId > 0 ? $authorId : false );
	}

	private static function resolvePrimaryCategory( int $postId ): string {
		if ( $postId <= 0 || ! function_exists( 'get_the_category' ) ) {
			return '';
		}
		$categories = get_the_category( $postId );
		if ( is_array( $categories ) && ! empty( $categories[0]->name ) ) {
			return (string) $categories[0]->name;
		}
		return '';
	}

	/**
	 * Resolves an ACF field or custom post meta key into a clean sanitized string.
	 */
	public static function resolveCustomField( string $fieldName, int $postId ): string {
		if ( $postId <= 0 || '' === $fieldName ) {
			return '';
		}

		$val = null;
		if ( function_exists( 'get_field' ) ) {
			$val = get_field( $fieldName, $postId );
		}

		if ( null === $val || false === $val || '' === $val ) {
			if ( function_exists( 'get_post_meta' ) ) {
				$val = get_post_meta( $postId, $fieldName, true );
			}
		}

		if ( is_string( $val ) || is_numeric( $val ) ) {
			return sanitize_text_field( (string) $val );
		}

		if ( is_array( $val ) ) {
			if ( isset( $val['label'] ) && is_string( $val['label'] ) ) {
				return sanitize_text_field( $val['label'] );
			}
			if ( isset( $val['value'] ) && ( is_string( $val['value'] ) || is_numeric( $val['value'] ) ) ) {
				return sanitize_text_field( (string) $val['value'] );
			}
			$scalars = array_filter( $val, static fn( $item ) => is_string( $item ) || is_numeric( $item ) );
			return sanitize_text_field( implode( ', ', $scalars ) );
		}

		return '';
	}
}
