<?php

declare(strict_types=1);

namespace Quinrel\WordPress\Modules\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HtmlSitemapShortcode
 *
 * Registers and renders the [quinrel_sitemap] shortcode.
 * Configuration is read from WordPress options set via the admin UI.
 */
class HtmlSitemapShortcode {

	private bool $stylesPrinted = false;

	public function register(): void {
		add_shortcode( 'quinrel_sitemap', array( $this, 'render' ) );
	}

	public function render( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'post_types'  => get_option( 'quinrel_html_sitemap_post_types', 'post,page' ),
				'exclude_ids' => get_option( 'quinrel_html_sitemap_exclude_ids', '' ),
				'order'       => get_option( 'quinrel_html_sitemap_order', 'menu_order' ),
				'show_count'  => get_option( 'quinrel_html_sitemap_show_count', 'no' ),
			),
			$atts,
			'quinrel_sitemap'
		);

		$postTypes  = array_filter( array_map( 'trim', explode( ',', $atts['post_types'] ) ) );
		$excludeIds = array_slice(
			array_values( array_filter( array_map( 'absint', explode( ',', (string) $atts['exclude_ids'] ) ) ) ),
			0,
			500
		);
		$showCount  = $atts['show_count'] === 'yes';

		$orderby = match ( $atts['order'] ) {
			'title'    => 'title',
			'date'     => 'date',
			'modified' => 'modified',
			default    => 'menu_order',
		};

		if ( empty( $postTypes ) ) {
			$postTypes = array( 'post', 'page' );
		}

		ob_start(); ?>
		<nav class="quinrel-sitemap" aria-label="<?php echo esc_attr__( 'Site Map', 'quinrel' ); ?>">
		<?php
		foreach ( $postTypes as $postType ) :
			$ptObj = get_post_type_object( $postType );
			if ( ! $ptObj || ! $ptObj->public ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'      => $postType,
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'post__not_in'   => $excludeIds,
					'orderby'        => $orderby,
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);

			if ( empty( $posts ) ) {
				continue;
			}
			?>
			<section class="quinrel-sitemap__section">
				<h3 class="quinrel-sitemap__heading">
					<?php echo esc_html( $ptObj->labels->name ); ?>
					<?php
					if ( $showCount ) :
						?>
						<span class="quinrel-sitemap__count">(<?php echo esc_html( (string) count( $posts ) ); ?>)</span><?php endif; ?>
				</h3>
				<ul class="quinrel-sitemap__list">
					<?php foreach ( $posts as $post ) : ?>
					<li class="quinrel-sitemap__item">
						<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ?: '(no title)' ); ?></a>
					</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
		</nav>
		<?php
		return $this->styles() . ob_get_clean();
	}

	/**
	 * Print the sitemap rules with the shortcode, not on every front-end URL.
	 */
	private function styles(): string {
		if ( $this->stylesPrinted ) {
			return '';
		}

		$this->stylesPrinted = true;

		return '<style id="quinrel-sitemap-css">
.quinrel-sitemap{font-family:inherit;margin:0;padding:0}
.quinrel-sitemap__section{margin-bottom:2rem}
.quinrel-sitemap__heading{font-size:1.1em;font-weight:600;margin:0 0 .5rem;border-bottom:2px solid currentColor;padding-bottom:.25rem}
.quinrel-sitemap__count{font-weight:400;font-size:.85em;opacity:.65;margin-left:.4em}
.quinrel-sitemap__list{list-style:none;margin:.5rem 0 0;padding:0;display:flex;flex-wrap:wrap;gap:.35rem .75rem}
.quinrel-sitemap__item a{color:inherit;text-decoration:none;font-size:.95em}
.quinrel-sitemap__item a:hover{text-decoration:underline}
</style>' . "\n";
	}
}
