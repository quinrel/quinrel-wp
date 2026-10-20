<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds an SEO Score column to the WordPress Posts and Pages list tables.
 * Displays a color-coded indicator based on the completeness of SEO meta fields.
 */
class SeoScoreColumn {

	/**
	 * Register hooks for both Posts and Pages list tables.
	 */
	public function register(): void {
		foreach ( array( 'post', 'page' ) as $postType ) {
			add_filter( "manage_{$postType}_posts_columns", array( $this, 'addColumn' ) );
			add_action( "manage_{$postType}_posts_custom_column", array( $this, 'renderColumn' ), 10, 2 );
			add_filter( "manage_edit-{$postType}_sortable_columns", array( $this, 'makeSortable' ) );
		}

		add_action( 'admin_head', array( $this, 'injectColumnStyles' ) );
	}

	/**
	 * Add the SEO Score column header.
	 */
	public function addColumn( array $columns ): array {
		$newColumns = array();
		foreach ( $columns as $key => $label ) {
			$newColumns[ $key ] = $label;
			// Insert our column right after the title column
			if ( $key === 'title' ) {
				$newColumns['quinrel_seo'] = 'SEO';
			}
		}
		return $newColumns;
	}

	/**
	 * Render the SEO Score indicator for each row.
	 */
	public function renderColumn( string $column, int $postId ): void {
		if ( $column !== 'quinrel_seo' ) {
			return;
		}

		$score   = $this->calculateScore( $postId );
		$color   = $score >= 80 ? '#22c55e' : ( $score >= 50 ? '#f59e0b' : '#ef4444' );
		$label   = $score >= 80 ? 'Good' : ( $score >= 50 ? 'OK' : 'Poor' );
		$title   = (string) get_post_meta( $postId, '_quinrel_meta_title', true );
		$desc    = (string) get_post_meta( $postId, '_quinrel_meta_description', true );
		$noindex = get_post_meta( $postId, '_quinrel_noindex', true ) === 'yes' ? '1' : '0';

		printf(
			'<span title="%s — %d/100" style="display:inline-flex;align-items:center;gap:4px;">
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%%;background:%s;" aria-hidden="true"></span>
                <span style="font-size:12px;font-weight:500;color:%s;">%d</span>
                <span class="screen-reader-text">%s</span>
            </span>
            <span class="quinrel-post-meta-title" style="display:none;">%s</span>
            <span class="quinrel-post-meta-desc" style="display:none;">%s</span>
            <span class="quinrel-post-noindex" style="display:none;">%s</span>',
			esc_attr( $label ),
			esc_html( $score ),
			esc_attr( $color ),
			esc_attr( $color ),
			esc_html( $score ),
			esc_html( $label ),
			esc_html( $title ),
			esc_html( $desc ),
			esc_html( $noindex )
		);
	}

	/**
	 * Make the column sortable (optional enhancement).
	 */
	public function makeSortable( array $columns ): array {
		$columns['quinrel_seo'] = 'quinrel_seo';
		return $columns;
	}

	/**
	 * Calculate a server-side SEO score aligned with the Block Editor calculator.
	 */
	private function calculateScore( int $postId ): int {
		return \Quinrel\WordPress\Modules\Seo\SeoScoreCalculator::calculateForPost( $postId );
	}

	/**
	 * Inject minimal CSS for the column width.
	 */
	public function injectColumnStyles(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'list-tables' );
		wp_add_inline_style( 'list-tables', '.column-quinrel_seo { width: 60px; text-align: center; }' );
	}
}
