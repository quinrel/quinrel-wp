<?php

namespace Quinrel\WordPress\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MetaTagsGenerator {

	public function outputStandardMetaTags(): void {
		if ( is_admin() ) {
			return;
		}

		echo "\n<!-- Quinrel SEO Meta Tags -->\n";

		$description = $this->getDescription();
		if ( $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		$canonical = $this->getCanonicalUrl();
		if ( $canonical ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
		}

		$robots = $this->getRobotsMeta();
		if ( $robots ) {
			printf( '<meta name="robots" content="%s" />' . "\n", esc_attr( $robots ) );
		}

		// Machine-readable AI training opt-out and EU DSM TDM-Reservation directives
		$aiOptOut    = get_option( 'quinrel_ai_training_optout', 'no' ) === 'yes';
		$postAiAllow = is_singular() ? get_post_meta( get_the_ID(), '_quinrel_ai_training_allow', true ) : '';

		if ( ( $aiOptOut && 'yes' !== $postAiAllow ) || 'no' === $postAiAllow ) {
			echo '<meta name="robots" content="noai, noimageai" />' . "\n";
			echo '<meta name="tdm-reservation" content="1" />' . "\n";
		}

		// Webmaster Tools Verification Meta Tags
		$googleVerify = get_option( 'quinrel_google_verify', '' );
		if ( ! empty( $googleVerify ) ) {
			printf( '<meta name="google-site-verification" content="%s" />' . "\n", esc_attr( $googleVerify ) );
		}

		$bingVerify = get_option( 'quinrel_bing_verify', '' );
		if ( ! empty( $bingVerify ) ) {
			printf( '<meta name="msvalidate.01" content="%s" />' . "\n", esc_attr( $bingVerify ) );
		}

		$yandexVerify = get_option( 'quinrel_yandex_verify', '' );
		if ( ! empty( $yandexVerify ) ) {
			printf( '<meta name="yandex-verification" content="%s" />' . "\n", esc_attr( $yandexVerify ) );
		}

		$pinterestVerify = get_option( 'quinrel_pinterest_verify', '' );
		if ( ! empty( $pinterestVerify ) ) {
			printf( '<meta name="pdomain" content="%s" />' . "\n", esc_attr( $pinterestVerify ) );
		}

		$this->enqueueGa4Tag();

		echo "<!-- /Quinrel SEO Meta Tags -->\n\n";
	}

	/**
	 * Replace the document title only when a custom SEO title is stored.
	 *
	 * An empty return lets WordPress, or an earlier filter, keep its title.
	 */
	public function getDocumentTitle( string $title = '' ): string {
		if ( is_singular() ) {
			$postId      = (int) get_the_ID();
			$customTitle = (string) get_post_meta( $postId, '_quinrel_meta_title', true );
			if ( '' !== trim( $customTitle ) ) {
				$resolved = SmartTagResolver::resolve( $customTitle, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}
			return $title;
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$customTitle = (string) get_term_meta( $term->term_id, '_quinrel_meta_title', true );
				if ( '' !== trim( $customTitle ) ) {
					return $customTitle;
				}
			}
		}

		return $title;
	}

	private function getDescription(): string {
		if ( is_singular() ) {
			$postId     = (int) get_the_ID();
			$customDesc = (string) get_post_meta( $postId, '_quinrel_meta_description', true );
			if ( '' !== trim( $customDesc ) ) {
				$resolved = SmartTagResolver::resolve( $customDesc, $postId );
				if ( '' !== $resolved ) {
					return $resolved;
				}
			}
			// Fallback to excerpt, then post content snippet (stripped of blocks and shortcodes)
			$excerpt = (string) get_post_field( 'post_excerpt', $postId );
			if ( '' !== trim( $excerpt ) ) {
				return SmartTagResolver::cleanContentSnippet( $excerpt, 25 );
			}
			return SmartTagResolver::cleanContentSnippet( (string) get_post_field( 'post_content', $postId ), 25 );
		}

		if ( is_front_page() || is_home() ) {
			return get_bloginfo( 'description' );
		}

		// Taxonomy archive (category, tag, custom taxonomy)
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$customDesc = (string) get_term_meta( $term->term_id, '_quinrel_meta_description', true );
				if ( '' !== trim( $customDesc ) ) {
					return $customDesc;
				}
				$termDesc = term_description( $term );
				$termDesc = wp_strip_all_tags( $termDesc );
				if ( ! empty( $termDesc ) ) {
					return wp_trim_words( $termDesc, 25 );
				}
				// Fallback: generate from term name
				return sprintf(
					/* translators: 1: Term name, 2: Taxonomy name, 3: Site name */
					__( 'Browse all posts in the "%1$s" %2$s on %3$s.', 'quinrel' ),
					$term->name,
					$term->taxonomy,
					get_bloginfo( 'name' )
				);
			}
		}

		// Author archive
		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				$bio = get_the_author_meta( 'description', $author->ID );
				if ( ! empty( $bio ) ) {
					return wp_trim_words( $bio, 25 );
				}
				return sprintf(
					/* translators: 1: Author name, 2: Site name */
					__( 'Posts by %1$s on %2$s.', 'quinrel' ),
					$author->display_name,
					get_bloginfo( 'name' )
				);
			}
		}

		// Post type archive
		if ( is_post_type_archive() ) {
			$postType = get_post_type_object( (string) get_query_var( 'post_type' ) );
			if ( $postType instanceof \WP_Post_Type ) {
				if ( ! empty( $postType->description ) ) {
					return wp_trim_words( $postType->description, 25 );
				}
				return sprintf(
					/* translators: 1: Post type plural name, 2: Site name */
					__( 'Browse all %1$s on %2$s.', 'quinrel' ),
					strtolower( $postType->labels->name ?? $postType->name ),
					get_bloginfo( 'name' )
				);
			}
		}

		// Search results
		if ( is_search() ) {
			$query = get_search_query();
			if ( ! empty( $query ) ) {
				return sprintf(
					/* translators: 1: Search query, 2: Site name */
					__( 'Search results for "%1$s" on %2$s.', 'quinrel' ),
					esc_html( $query ),
					get_bloginfo( 'name' )
				);
			}
		}

		// Date archives
		if ( is_date() ) {
			if ( is_day() ) {
				$label = get_the_date();
			} elseif ( is_month() ) {
				$label = get_the_date( 'F Y' );
			} else {
				$label = get_the_date( 'Y' );
			}
			return sprintf(
				/* translators: 1: Date label, 2: Site name */
				__( 'Archive for %1$s on %2$s.', 'quinrel' ),
				$label,
				get_bloginfo( 'name' )
			);
		}

		return '';
	}

	public function filterWpRobots( array $robots ): array {
		$directives = array_map( 'trim', explode( ',', $this->getRobotsMeta() ) );
		if ( in_array( 'noindex', $directives, true ) ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
		} else {
			unset( $robots['noindex'] );
			$robots['index'] = true;
		}

		if ( in_array( 'nofollow', $directives, true ) ) {
			unset( $robots['follow'] );
			$robots['nofollow'] = true;
		} else {
			unset( $robots['nofollow'] );
			$robots['follow'] = true;
		}

		return $robots;
	}

	private function getCanonicalUrl(): string {
		// Suppress canonical when site-wide search engine indexing is discouraged
		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			return '';
		}

		// ── Singular: honour per-post override, then use the clean permalink ──
		if ( is_singular() ) {
			// Suppress canonical on noindexed posts — contradictory signals confuse crawlers
			if ( get_post_meta( get_the_ID(), '_quinrel_noindex', true ) === 'yes' ) {
				return '';
			}
			$customCanonical = get_post_meta( get_the_ID(), '_quinrel_canonical_url', true );
			if ( ! empty( $customCanonical ) ) {
				return $customCanonical;
			}
			return (string) get_permalink();
		}

		// ── Pages that will be noindexed: suppress canonical entirely ──
		if ( is_search() || is_404() || is_date() ) {
			return '';
		}

		// ── Paginated archives: self-referencing canonical per page number ──
		// get_pagenum_link() returns the correct URL for the current page in
		// an archive sequence (/page/2/, ?paged=2, etc.) without extra query strings.
		if ( is_paged() ) {
			return get_pagenum_link( get_query_var( 'paged' ) );
		}

		// ── First page of a taxonomy archive ──
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				if ( get_term_meta( $term->term_id, '_quinrel_noindex', true ) === 'yes' ) {
					return '';
				}
				$customCanonical = (string) get_term_meta( $term->term_id, '_quinrel_canonical_url', true );
				if ( '' !== trim( $customCanonical ) ) {
					return $customCanonical;
				}
				$link = get_term_link( $term );
				return ! is_wp_error( $link ) ? $link : '';
			}
		}

		// ── Post-type archive ──
		if ( is_post_type_archive() ) {
			$link = get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
			return $link ?: '';
		}

		// ── Author archive ──
		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				return get_author_posts_url( $author->ID );
			}
		}

		// ── Front page / blog index ──
		if ( is_front_page() || is_home() ) {
			return home_url( '/' );
		}

		// ── Fallback: clean path without query strings ──
		global $wp;
		return home_url( $wp->request );
	}

	private function getRobotsMeta(): string {
		// ── Site-wide search engine visibility ("Discourage search engines from indexing this site") ──
		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			return 'noindex, nofollow';
		}

		// ── Singular posts/pages: respect per-post noindex/nofollow flags ──
		if ( is_singular() ) {
			$noindex  = get_post_meta( get_the_ID(), '_quinrel_noindex', true );
			$nofollow = get_post_meta( get_the_ID(), '_quinrel_nofollow', true );

			$index  = ( $noindex === 'yes' ) ? 'noindex' : 'index';
			$follow = ( $nofollow === 'yes' ) ? 'nofollow' : 'follow';

			return "{$index}, {$follow}";
		}

		// ── 404 pages: always noindex ──
		if ( is_404() ) {
			return 'noindex, follow';
		}

		// ── Search results: thin/duplicate content — always noindex ──
		if ( is_search() ) {
			return 'noindex, follow';
		}

		// Date archives stay noindex, including page 2 and later.
		if ( is_date() ) {
			return 'noindex, follow';
		}

		// Page 2 and later stay indexable unless an administrator opts in.
		// The canonical for that page is already self-referencing.
		if ( is_paged() && get_option( 'quinrel_noindex_paged_archives', 'no' ) === 'yes' ) {
			return 'noindex, follow';
		}

		// ── Taxonomy archives (category, tag, custom taxonomy) ──
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$noindex = get_term_meta( $term->term_id, '_quinrel_noindex', true );
				if ( 'yes' === $noindex ) {
					return 'noindex, follow';
				}
			}
		}

		// ── All other contexts (category, tag, author, CPT archives, home) ──
		return 'index, follow';
	}

	/**
	 * Return a validated GA4 Measurement ID (G-XXXXXXXXXX) when configured, or empty string.
	 */
	public function getValidGa4MeasurementId(): string {
		$raw = strtoupper( trim( (string) get_option( 'quinrel_ga4_measurement_id', '' ) ) );
		if ( $raw === '' || preg_match( '/^G-[A-Z0-9]{4,20}$/', $raw ) !== 1 ) {
			return '';
		}
		return $raw;
	}

	/**
	 * Check whether a valid GA4 Measurement ID is configured.
	 */
	public function hasValidGa4MeasurementId(): bool {
		return $this->getValidGa4MeasurementId() !== '';
	}

	/**
	 * Enqueue the Google Analytics 4 gtag.js snippet on public front-end pages
	 * when a valid Measurement ID is configured and the visitor is not an administrator.
	 */
	public function enqueueGa4Tag(): void {
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$measurementId = $this->getValidGa4MeasurementId();
		if ( $measurementId === '' ) {
			return;
		}

		$src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $measurementId );
		wp_enqueue_script(
			'quinrel-ga4-gtag',
			$src,
			array(),
			defined( 'QUINREL_VERSION' ) ? QUINREL_VERSION : '1.0.0',
			false
		);

		$inline = sprintf(
			'window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag("js", new Date()); gtag("config", %s);',
			wp_json_encode( $measurementId )
		);
		wp_add_inline_script( 'quinrel-ga4-gtag', $inline, 'after' );
	}
}
