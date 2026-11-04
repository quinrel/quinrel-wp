<?php

namespace Quinrel\WordPress\Modules\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Modules\Schema\VideoExtractor;

/**
 * Generates and caches the Google-compliant Video XML sitemap.
 * Implements high-performance Transient caching.
 */
class VideoSitemapGenerator {

	private const PAGE_SIZE        = 500;
	private const CACHE_VERSION    = 'quinrel_video_sitemap_cache_ver';
	private const CACHE_EXPIRATION = 4 * HOUR_IN_SECONDS;

	/**
	 * Serves one page of the video sitemap. Page 1 is /video-sitemap.xml.
	 */
	public function serveSitemap( int $page = 1 ): void {
		status_header( 200 );
		header( 'Content-Type: text/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		$page = max( 1, $page );
		$ver  = max( 1, (int) get_option( self::CACHE_VERSION, 1 ) );
		$key  = 'quinrel_video_sitemap_p' . $page . '_v' . $ver;
		$xml  = get_transient( $key );
		if ( ! is_string( $xml ) ) {
			$xml = $this->generateXml( $page );
			set_transient( $key, $xml, self::CACHE_EXPIRATION );
		}

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Drop every cached page by moving the version forward. Old transients expire on their own.
	 */
	public static function clearCache(): void {
		$ver = max( 1, (int) get_option( self::CACHE_VERSION, 1 ) );
		update_option( self::CACHE_VERSION, $ver + 1, false );
	}

	/**
	 * Sitemap files needed for the published posts this generator reads.
	 *
	 * A page that contains no video is still listed. Skipping it would drop later posts.
	 */
	public static function pageCount(): int {
		$total = 0;
		foreach ( self::activePostTypes() as $type ) {
			$counts = wp_count_posts( $type );
			if ( is_object( $counts ) && isset( $counts->publish ) ) {
				$total += (int) $counts->publish;
			}
		}
		if ( $total < 1 ) {
			return 1;
		}
		return (int) ceil( $total / self::PAGE_SIZE );
	}

	/**
	 * Compiles one page of the video urlset. Pages without a video are omitted from that file.
	 */
	public function generateXml( int $page = 1 ): string {
		$excludePostsString = SitemapSettings::get( 'sitemap_exclude_posts', '' );

		$excludePostIds = array();
		if ( is_string( $excludePostsString ) && $excludePostsString !== '' ) {
			$excludePostIds = array_slice(
				array_values( array_filter( array_map( 'intval', array_map( 'trim', explode( ',', $excludePostsString ) ) ) ) ),
				0,
				500
			);
		}

		$postTypes = self::activePostTypes();
		$page      = max( 1, $page );

		$posts = array();
		if ( $postTypes !== array() ) {
			$queryArgs = array(
				'post_type'      => $postTypes,
				'post_status'    => 'publish',
				'posts_per_page' => self::PAGE_SIZE, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- One bounded page. Pages without a video are skipped inside this page.
				'paged'          => $page,
				'no_found_rows'  => true,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			);

			if ( $excludePostIds !== array() ) {
				$queryArgs['post__not_in'] = $excludePostIds;
			}

			$posts = get_posts( $queryArgs );
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		$xml .= '        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		foreach ( $posts as $post ) {
			// Check if there is a noindex tag
			$noindex = get_post_meta( $post->ID, '_quinrel_noindex', true );
			if ( $noindex === 'yes' ) {
				continue;
			}

			// Extract videos
			$videos = VideoExtractor::extractVideoObjects( $post->ID );
			if ( empty( $videos ) ) {
				continue;
			}

			$url = get_permalink( $post->ID );

			foreach ( $videos as $video ) {
				$xml .= "  <url>\n";
				$xml .= '    <loc>' . esc_url( $url ) . "</loc>\n";
				$xml .= "    <video:video>\n";
				$xml .= '      <video:thumbnail_loc>' . esc_url( $video['thumbnailUrl'] ) . "</video:thumbnail_loc>\n";
				$xml .= '      <video:title>' . esc_html( $video['name'] ) . "</video:title>\n";
				$xml .= '      <video:description>' . esc_html( $video['description'] ) . "</video:description>\n";

				// If it is a direct file url (like .mp4) use content_loc, otherwise use player_loc (embed url)
				$embedUrl = $video['embedUrl'];
				if ( preg_match( '/\.(mp4|m4v|webm|ogv)$/i', $embedUrl ) ) {
					$xml .= '      <video:content_loc>' . esc_url( $embedUrl ) . "</video:content_loc>\n";
				} else {
					$xml .= '      <video:player_loc>' . esc_url( $embedUrl ) . "</video:player_loc>\n";
				}

				$durationSec = VideoExtractor::secondsFromIso( isset( $video['duration'] ) && is_string( $video['duration'] ) ? $video['duration'] : '' );
				if ( $durationSec > 0 ) {
					$xml .= '      <video:duration>' . $durationSec . "</video:duration>\n";
				}
				$xml .= '      <video:publication_date>' . esc_html( $video['uploadDate'] ) . "</video:publication_date>\n";
				$xml .= "    </video:video>\n";
				$xml .= "  </url>\n";
			}
		}

		$xml .= '</urlset>';
		return $xml;
	}

	/**
	 * Public post types this sitemap reads, minus types an administrator excluded.
	 *
	 * @return array<int, string>
	 */
	private static function activePostTypes(): array {
		$excludePostTypes = SitemapSettings::get( 'sitemap_exclude_types', array() );
		$postTypes        = get_post_types( array( 'public' => true ) );
		if ( ! is_array( $postTypes ) || $postTypes === array() ) {
			$postTypes = array( 'post', 'page' );
		}
		if ( is_array( $excludePostTypes ) ) {
			$postTypes = array_diff( $postTypes, $excludePostTypes );
		}
		return array_values( $postTypes );
	}
}
