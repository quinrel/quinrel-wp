<?php

namespace Quinrel\WordPress\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optimizes RSS feeds by prepending/appending custom HTML/text,
 * adding attribution, branding, and anti-scraping elements.
 */
class RssOptimizations {

	/**
	 * Boot the RSS optimization hooks.
	 */
	public function register(): void {
		add_filter( 'the_content_feed', array( $this, 'optimizeFeedContent' ) );
		add_filter( 'the_excerpt_rss', array( $this, 'optimizeFeedContent' ) );
	}

	/**
	 * Modifies the RSS feed item content/excerpt with custom prepend/append attributions.
	 */
	public function optimizeFeedContent( string $content ): string {
		if ( ! is_feed() ) {
			return $content;
		}

		$before = (string) get_option( 'quinrel_rss_before_content', '' );
		$after  = (string) get_option( 'quinrel_rss_after_content', '' );

		if ( $before === '' && $after === '' ) {
			if ( get_option( 'quinrel_rss_attribution', 'no' ) !== 'yes' ) {
				return $content;
			}

			$postLink = sprintf( '<a href="%s">%s</a>', esc_url( get_permalink() ), esc_html( get_the_title() ) );
			$siteLink = sprintf( '<a href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html( get_bloginfo( 'name' ) ) );
			$after    = '<p>' . sprintf(
				/* translators: 1: linked post title, 2: linked site name. */
				esc_html__( 'The post %1$s first appeared on %2$s.', 'quinrel' ),
				$postLink,
				$siteLink
			) . '</p>';
		} else {
			// Replace placeholder tokens
			$tokens = array(
				'%%POST_LINK%%' => sprintf( '<a href="%s">%s</a>', esc_url( get_permalink() ), esc_html( get_the_title() ) ),
				'%%SITE_LINK%%' => sprintf( '<a href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html( get_bloginfo( 'name' ) ) ),
				'%%AUTHOR%%'    => esc_html( get_the_author() ),
			);

			$before = str_replace( array_keys( $tokens ), array_values( $tokens ), $before );
			$after  = str_replace( array_keys( $tokens ), array_values( $tokens ), $after );
		}

		return $before . $content . $after;
	}
}
