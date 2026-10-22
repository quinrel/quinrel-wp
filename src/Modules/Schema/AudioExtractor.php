<?php

namespace Quinrel\WordPress\Modules\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans post content and post attachments to detect podcast episodes,
 * audio files, and HTML5 audio embeds, generating structured AudioObject schemas.
 */
class AudioExtractor {

	/**
	 * Extracts the primary audio object from post metadata, Gutenberg blocks, or attachments.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function extractAudioObject( int $postId ): ?array {
		$post = get_post( $postId );
		if ( ! $post ) {
			return null;
		}

		// 1. Check explicit manual post metadata
		$manualUrl = get_post_meta( $postId, '_quinrel_podcast_audio_url', true );
		if ( ! empty( $manualUrl ) && is_string( $manualUrl ) ) {
			return self::buildAudioObject( $postId, esc_url_raw( $manualUrl ) );
		}

		$content = (string) $post->post_content;
		if ( '' !== $content ) {
			// 2. Scan Gutenberg wp:audio block
			if ( preg_match( '/<!--\s+wp:audio\s+.*?-->.*?<audio[^>]+src=["\']([^"\']+)["\']/is', $content, $matches ) ) {
				return self::buildAudioObject( $postId, esc_url_raw( $matches[1] ) );
			}

			// 3. Scan HTML5 <audio> tag
			if ( preg_match( '/<audio[^>]+src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
				return self::buildAudioObject( $postId, esc_url_raw( $matches[1] ) );
			}

			// 4. Scan [audio src="..."] shortcode
			if ( preg_match( '/\[audio[^\]]+src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
				return self::buildAudioObject( $postId, esc_url_raw( $matches[1] ) );
			}
		}

		// 5. Fallback: check attached media
		if ( function_exists( 'get_attached_media' ) ) {
			$attachedAudio = get_attached_media( 'audio', $postId );
			if ( ! empty( $attachedAudio ) ) {
				$mediaItem = reset( $attachedAudio );
				$mediaUrl  = wp_get_attachment_url( $mediaItem->ID );
				if ( ! empty( $mediaUrl ) ) {
					return self::buildAudioObject( $postId, esc_url_raw( $mediaUrl ) );
				}
			}
		}

		return null;
	}

	/**
	 * Builds a Schema.org AudioObject node.
	 *
	 * @return array<string, mixed>
	 */
	public static function buildAudioObject( int $postId, string $audioUrl ): array {
		$post      = get_post( $postId );
		$permalink = get_permalink( $postId );
		$title     = get_post_meta( $postId, '_quinrel_podcast_title', true ) ?: get_the_title( $postId );
		$desc      = get_post_meta( $postId, '_quinrel_podcast_description', true ) ?: wp_strip_all_tags( get_the_excerpt( $postId ) );

		$encoding = 'audio/mpeg';
		if ( str_ends_with( strtolower( $audioUrl ), '.ogg' ) ) {
			$encoding = 'audio/ogg';
		} elseif ( str_ends_with( strtolower( $audioUrl ), '.m4a' ) || str_ends_with( strtolower( $audioUrl ), '.aac' ) ) {
			$encoding = 'audio/mp4';
		} elseif ( str_ends_with( strtolower( $audioUrl ), '.wav' ) ) {
			$encoding = 'audio/wav';
		}

		return array(
			'@type'          => 'AudioObject',
			'@id'            => $permalink . '#audio',
			'name'           => $title,
			'description'    => $desc,
			'contentUrl'     => $audioUrl,
			'encodingFormat' => $encoding,
		);
	}
}
