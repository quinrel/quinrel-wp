<?php

namespace Quinrel\WordPress\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenGraphGenerator {

	public function outputSocialMetaTags(): void {
		if ( is_admin() ) {
			return;
		}

		echo "<!-- Quinrel Social Meta Tags -->\n";

		$title       = $this->getContextTitle();
		$description = $this->getContextDescription();
		$url         = $this->getContextUrl();
		$ogType      = $this->getOgType();
		$image       = $this->getContextImage();

		// Open Graph
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( $locale ) );
		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $ogType ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );

		if ( ! empty( $image['url'] ) ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image['url'] ) );
			if ( ! empty( $image['width'] ) ) {
				printf( '<meta property="og:image:width" content="%d" />' . "\n", (int) $image['width'] );
			}
			if ( ! empty( $image['height'] ) ) {
				printf( '<meta property="og:image:height" content="%d" />' . "\n", (int) $image['height'] );
			}
			$mimeType = ! empty( $image['mime'] ) ? $image['mime'] : $this->detectImageMimeType( (string) $image['url'] );
			if ( '' !== $mimeType ) {
				printf( '<meta property="og:image:type" content="%s" />' . "\n", esc_attr( $mimeType ) );
			}
		}

		// Twitter Cards (check explicit Twitter overrides before falling back to Open Graph values)
		$twitterTitle       = $this->getTwitterTitle( $title );
		$twitterDescription = $this->getTwitterDescription( $description );
		$twitterImage       = $this->getTwitterImage( (string) ( $image['url'] ?? '' ) );

		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $twitterTitle ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $twitterDescription ) );

		if ( '' !== $twitterImage ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $twitterImage ) );
		}

		echo "<!-- /Quinrel Social Meta Tags -->\n\n";
	}

	// ── Context helpers ──────────────────────────────────────────────────────

	private function getOgType(): string {
		if ( is_singular( 'post' ) ) {
			return 'article';
		}
		return 'website';
	}

	private function getContextUrl(): string {
		if ( is_singular() ) {
			return (string) get_permalink();
		}
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}

	private function getContextTitle(): string {
		if ( is_singular() ) {
			$postId = (int) get_the_ID();
			$og     = trim( (string) get_post_meta( $postId, '_quinrel_og_title', true ) );
			if ( '' !== $og ) {
				$resolved = SmartTagResolver::resolve( $og, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}

			$metaTitle = trim( (string) get_post_meta( $postId, '_quinrel_meta_title', true ) );
			if ( '' !== $metaTitle ) {
				$resolved = SmartTagResolver::resolve( $metaTitle, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}

			return get_the_title();
		}

		if ( is_front_page() || is_home() ) {
			return get_bloginfo( 'name' ) . ' — ' . get_bloginfo( 'description' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			return ( $term instanceof \WP_Term ) ? $term->name : get_bloginfo( 'name' );
		}

		if ( is_author() ) {
			$author = get_queried_object();
			return ( $author instanceof \WP_User )
				? 'Posts by ' . $author->display_name
				: get_bloginfo( 'name' );
		}

		if ( is_search() ) {
			return 'Search results for: ' . get_search_query();
		}

		if ( is_post_type_archive() ) {
			$postType = get_post_type_object( (string) get_query_var( 'post_type' ) );
			return ( $postType instanceof \WP_Post_Type )
				? ( $postType->labels->archive_title ?? $postType->labels->name ?? $postType->name )
				: get_bloginfo( 'name' );
		}

		return get_bloginfo( 'name' );
	}

	private function getContextDescription(): string {
		if ( is_singular() ) {
			$postId = (int) get_the_ID();
			$ogDesc = trim( (string) get_post_meta( $postId, '_quinrel_og_description', true ) );
			if ( '' !== $ogDesc ) {
				$resolved = SmartTagResolver::resolve( $ogDesc, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}

			$metaDesc = trim( (string) get_post_meta( $postId, '_quinrel_meta_description', true ) );
			if ( '' !== $metaDesc ) {
				$resolved = SmartTagResolver::resolve( $metaDesc, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}

			$excerpt = (string) get_post_field( 'post_excerpt', $postId );
			if ( '' !== trim( $excerpt ) ) {
				return SmartTagResolver::cleanContentSnippet( $excerpt, 25 );
			}

			return SmartTagResolver::cleanContentSnippet( (string) get_post_field( 'post_content', $postId ), 25 );
		}

		if ( is_front_page() || is_home() ) {
			return get_bloginfo( 'description' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term     = get_queried_object();
			$termDesc = ( $term instanceof \WP_Term ) ? wp_strip_all_tags( term_description( $term ) ) : '';
			return ! empty( $termDesc ) ? wp_trim_words( $termDesc, 25 ) : get_bloginfo( 'description' );
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				$bio = get_the_author_meta( 'description', $author->ID );
				return ! empty( $bio ) ? wp_trim_words( $bio, 25 ) : get_bloginfo( 'description' );
			}
		}

		return get_bloginfo( 'description' );
	}

	private function getTwitterTitle( string $fallbackTitle ): string {
		if ( is_singular() ) {
			$postId  = (int) get_the_ID();
			$twitter = trim( (string) get_post_meta( $postId, '_quinrel_twitter_title', true ) );
			if ( '' !== $twitter ) {
				$resolved = SmartTagResolver::resolve( $twitter, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}
		}
		return $fallbackTitle;
	}

	private function getTwitterDescription( string $fallbackDescription ): string {
		if ( is_singular() ) {
			$postId  = (int) get_the_ID();
			$twitter = trim( (string) get_post_meta( $postId, '_quinrel_twitter_description', true ) );
			if ( '' !== $twitter ) {
				$resolved = SmartTagResolver::resolve( $twitter, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}
		}
		return $fallbackDescription;
	}

	private function getTwitterImage( string $fallbackImageUrl ): string {
		if ( is_singular() ) {
			$postId  = (int) get_the_ID();
			$twitter = trim( (string) get_post_meta( $postId, '_quinrel_twitter_image', true ) );
			if ( '' !== $twitter ) {
				return $twitter;
			}
		}
		return $fallbackImageUrl;
	}

	/**
	 * Returns ['url' => string, 'width' => int, 'height' => int, 'mime' => string].
	 * Tries in order: post custom meta, featured image, site default option.
	 *
	 * @return array{url:string,width:int,height:int,mime?:string}
	 */
	private function getContextImage(): array {
		// Singular: check custom OG image meta, then featured image
		if ( is_singular() ) {
			$postId    = get_the_ID();
			$customUrl = get_post_meta( $postId, '_quinrel_og_image', true );
			if ( ! empty( $customUrl ) ) {
				return $this->resolveDimensions( (string) $customUrl );
			}
			if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $postId ) ) {
				$thumbId = get_post_thumbnail_id( $postId );
				return $this->resolveAttachmentImage( (int) $thumbId );
			}
		}

		// Site default share image
		$defaultId = (int) get_option( 'quinrel_default_share_image_id', 0 );
		if ( $defaultId > 0 ) {
			return $this->resolveAttachmentImage( $defaultId );
		}

		$defaultUrl = (string) get_option( 'quinrel_default_share_image', '' );
		if ( ! empty( $defaultUrl ) ) {
			return $this->resolveDimensions( $defaultUrl );
		}

		// Site icon as last resort
		$siteIconUrl = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
		if ( ! empty( $siteIconUrl ) ) {
			return array(
				'url'    => $siteIconUrl,
				'width'  => 512,
				'height' => 512,
				'mime'   => $this->detectImageMimeType( (string) $siteIconUrl ),
			);
		}

		return array(
			'url'    => '',
			'width'  => 0,
			'height' => 0,
			'mime'   => '',
		);
	}

	/**
	 * Resolve dimensions from an attachment ID.
	 *
	 * @return array{url:string,width:int,height:int,mime:string}
	 */
	private function resolveAttachmentImage( int $attachmentId ): array {
		$src = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $attachmentId, 'full' ) : false;
		if ( $src === false || empty( $src[0] ) ) {
			return array(
				'url'    => '',
				'width'  => 0,
				'height' => 0,
				'mime'   => '',
			);
		}
		$mime = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $attachmentId ) : '';
		return array(
			'url'    => $src[0],
			'width'  => (int) ( $src[1] ?? 0 ),
			'height' => (int) ( $src[2] ?? 0 ),
			'mime'   => '' !== $mime ? $mime : $this->detectImageMimeType( (string) $src[0] ),
		);
	}

	/**
	 * Resolve dimensions from a URL by looking up the attachment.
	 *
	 * @return array{url:string,width:int,height:int,mime:string}
	 */
	private function resolveDimensions( string $url ): array {
		$attachmentId = function_exists( 'attachment_url_to_postid' ) ? (int) attachment_url_to_postid( $url ) : 0;
		if ( $attachmentId > 0 ) {
			return $this->resolveAttachmentImage( $attachmentId );
		}
		// URL not in media library — return it without dimensions
		return array(
			'url'    => $url,
			'width'  => 0,
			'height' => 0,
			'mime'   => $this->detectImageMimeType( $url ),
		);
	}

	/**
	 * Detect MIME type from an image URL path extension.
	 */
	private function detectImageMimeType( string $url ): string {
		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( str_ends_with( $path, '.png' ) ) {
			return 'image/png';
		}
		if ( str_ends_with( $path, '.webp' ) ) {
			return 'image/webp';
		}
		if ( str_ends_with( $path, '.gif' ) ) {
			return 'image/gif';
		}
		if ( str_ends_with( $path, '.avif' ) ) {
			return 'image/avif';
		}
		if ( str_ends_with( $path, '.svg' ) ) {
			return 'image/svg+xml';
		}
		return 'image/jpeg';
	}
}
