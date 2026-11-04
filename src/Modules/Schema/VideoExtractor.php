<?php

namespace Quinrel\WordPress\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans post content to detect YouTube, Vimeo, and HTML5 video embeds
 * and auto-generates structured VideoObject schemas.
 */
class VideoExtractor {

	/**
	 * Extracts video objects from post content.
	 */
	public static function extractVideoObjects( int $postId ): array {
		$post = get_post( $postId );
		if ( ! $post ) {
			return array();
		}

		// Check if there is manual video metadata first
		$manualVideoUrl = get_post_meta( $postId, '_quinrel_video_url', true );
		$videoObjects   = array();

		if ( ! empty( $manualVideoUrl ) ) {
			$thumbnail = get_post_meta( $postId, '_quinrel_video_thumbnail', true ) ?: get_the_post_thumbnail_url( $postId, 'full' );
			$title     = get_post_meta( $postId, '_quinrel_video_title', true ) ?: get_the_title( $postId );
			$desc      = get_post_meta( $postId, '_quinrel_video_description', true ) ?: wp_strip_all_tags( get_the_excerpt( $postId ) );
			$duration  = self::isoDuration( get_post_meta( $postId, '_quinrel_video_duration', true ) );

			$videoObjects[] = self::buildVideoObjectSchema(
				$title,
				$desc,
				$thumbnail ?: get_site_icon_url(),
				get_post_modified_time( 'c', false, $post ),
				$manualVideoUrl,
				$duration
			);
			return $videoObjects;
		}

		// Auto-extract embeds from content
		$content = $post->post_content;
		if ( empty( $content ) ) {
			return array();
		}

		// 1. YouTube Matchers
		// Standard YouTube URL: youtube.com/watch?v=ID or youtu.be/ID
		// Embed: youtube.com/embed/ID
		if ( preg_match_all( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $content, $matches ) ) {
			$youtubeIds = array_unique( $matches[1] );
			foreach ( $youtubeIds as $idx => $id ) {
				$videoObjects[] = self::buildVideoObjectSchema(
					get_the_title( $postId ) . ' - Video ' . ( $idx + 1 ),
					wp_strip_all_tags( get_the_excerpt( $postId ) ) ?: get_the_title( $postId ),
					"https://img.youtube.com/vi/{$id}/maxresdefault.jpg",
					get_post_modified_time( 'c', false, $post ),
					"https://www.youtube.com/embed/{$id}"
				);
			}
		}

		// 2. Vimeo Matchers
		// vimeo.com/ID or player.vimeo.com/video/ID
		if ( preg_match_all( '/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/i', $content, $matches ) ) {
			$vimeoIds = array_unique( $matches[1] );
			foreach ( $vimeoIds as $idx => $id ) {
				$videoObjects[] = self::buildVideoObjectSchema(
					get_the_title( $postId ) . ' - Vimeo ' . ( $idx + 1 ),
					wp_strip_all_tags( get_the_excerpt( $postId ) ) ?: get_the_title( $postId ),
					get_the_post_thumbnail_url( $postId, 'full' ) ?: get_site_icon_url(),
					get_post_modified_time( 'c', false, $post ),
					"https://player.vimeo.com/video/{$id}"
				);
			}
		}

		// 3. HTML5 Video matches
		if ( preg_match_all( '/<video[^>]*src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			$srcs = array_unique( $matches[1] );
			foreach ( $srcs as $idx => $src ) {
				$videoObjects[] = self::buildVideoObjectSchema(
					get_the_title( $postId ) . ' - Video ' . ( $idx + 1 ),
					wp_strip_all_tags( get_the_excerpt( $postId ) ) ?: get_the_title( $postId ),
					get_the_post_thumbnail_url( $postId, 'full' ) ?: get_site_icon_url(),
					get_post_modified_time( 'c', false, $post ),
					$src
				);
			}
		}

		return $videoObjects;
	}

	/**
	 * ISO-8601 duration from a stored value, or an empty string when none was supplied.
	 *
	 * A number is seconds. A PT string is kept only when it adds up to at least one second.
	 *
	 * @param mixed $raw Post meta or an already built duration.
	 */
	public static function isoDuration( mixed $raw ): string {
		if ( is_numeric( $raw ) ) {
			$seconds = (int) $raw;
			return $seconds > 0 ? 'PT' . $seconds . 'S' : '';
		}

		if ( ! is_string( $raw ) || preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', trim( $raw ), $match ) !== 1 ) {
			return '';
		}

		return self::secondsFromIso( $match[0] ) > 0 ? $match[0] : '';
	}

	/**
	 * Seconds in an ISO-8601 PT duration. Zero when the value is missing or not a positive duration.
	 */
	public static function secondsFromIso( string $iso ): int {
		if ( preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $match ) !== 1 ) {
			return 0;
		}

		$hours   = ( $match[1] ?? '' ) !== '' ? (int) $match[1] : 0;
		$minutes = ( $match[2] ?? '' ) !== '' ? (int) $match[2] : 0;
		$seconds = ( $match[3] ?? '' ) !== '' ? (int) $match[3] : 0;

		return ( $hours * 3600 ) + ( $minutes * 60 ) + $seconds;
	}

	/**
	 * Builds standard JSON-LD VideoObject schema.
	 */
	private static function buildVideoObjectSchema( string $name, string $description, string $thumbnailUrl, string $uploadDate, string $embedUrl, string $duration = '' ): array {
		$schema = array(
			'@type'        => 'VideoObject',
			'name'         => $name,
			'description'  => $description,
			'thumbnailUrl' => $thumbnailUrl,
			'uploadDate'   => $uploadDate,
			'embedUrl'     => $embedUrl,
		);

		if ( $duration !== '' ) {
			$schema['duration'] = $duration;
		}

		return $schema;
	}
}
