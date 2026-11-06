<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides in-content SEO optimization directly on Taxonomy edit screens
 * (Categories, Tags, Custom Taxonomies) and adds an SEO status column to listing tables.
 */
class TaxonomySeo {

	/**
	 * Register hooks for all public taxonomies.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'hookTaxonomies' ), 99 );
	}

	/**
	 * Hook into all public taxonomies dynamically.
	 */
	public function hookTaxonomies(): void {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			// Add & Edit form fields
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'renderAddFormFields' ), 10, 1 );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'renderEditFormFields' ), 10, 2 );

			// Save term meta
			add_action( "created_{$taxonomy}", array( $this, 'saveTermMeta' ), 10, 1 );
			add_action( "edited_{$taxonomy}", array( $this, 'saveTermMeta' ), 10, 1 );

			// List table columns
			add_filter( "manage_edit-{$taxonomy}_columns", array( $this, 'addColumn' ) );
			add_filter( "manage_{$taxonomy}_custom_column", array( $this, 'renderColumn' ), 10, 3 );
		}
	}

	/**
	 * Render fields on the Add New Term form (vertical column layout).
	 *
	 * @param string $taxonomy Current taxonomy slug.
	 */
	public function renderAddFormFields( string $taxonomy ): void {
		wp_nonce_field( 'quinrel_taxonomy_seo_action', 'quinrel_taxonomy_seo_nonce' );
		?>
		<div class="form-field quinrel-taxonomy-seo-wrap" style="margin-top:20px;padding:16px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;">
			<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
				<span class="dashicons dashicons-chart-area" style="color:#0060F9;"></span>
				<h3 style="margin:0;font-size:14px;font-weight:700;color:#0f172a;"><?php esc_html_e( 'Quinrel SEO Optimization', 'quinrel' ); ?></h3>
			</div>

			<div class="form-field" style="margin-bottom:12px;">
				<label for="quinrel_meta_title" style="font-weight:600;font-size:12px;color:#334155;"><?php esc_html_e( 'SEO Title', 'quinrel' ); ?></label>
				<input type="text" name="quinrel_meta_title" id="quinrel_meta_title" value="" placeholder="<?php esc_attr_e( 'Custom title for Google search results…', 'quinrel' ); ?>" style="width:100%;margin-top:4px;" />
				<p class="description" style="font-size:11px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Recommended length: 50–60 characters. Leave empty to use default taxonomy title.', 'quinrel' ); ?></p>
			</div>

			<div class="form-field" style="margin-bottom:12px;">
				<label for="quinrel_meta_description" style="font-weight:600;font-size:12px;color:#334155;"><?php esc_html_e( 'Meta Description', 'quinrel' ); ?></label>
				<textarea name="quinrel_meta_description" id="quinrel_meta_description" rows="3" placeholder="<?php esc_attr_e( 'Summary snippet shown under title in search engines…', 'quinrel' ); ?>" style="width:100%;margin-top:4px;"></textarea>
				<p class="description" style="font-size:11px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Recommended length: 120–160 characters.', 'quinrel' ); ?></p>
			</div>

			<div class="form-field" style="margin-bottom:12px;">
				<label for="quinrel_canonical_url" style="font-weight:600;font-size:12px;color:#334155;"><?php esc_html_e( 'Canonical URL', 'quinrel' ); ?></label>
				<input type="url" name="quinrel_canonical_url" id="quinrel_canonical_url" value="" placeholder="<?php esc_attr_e( 'https://…', 'quinrel' ); ?>" style="width:100%;margin-top:4px;" />
				<p class="description" style="font-size:11px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Leave empty for automatic self-referencing canonical URL.', 'quinrel' ); ?></p>
			</div>

			<div class="form-field" style="margin-bottom:6px;">
				<label for="quinrel_noindex" style="font-weight:600;font-size:12px;color:#334155;"><?php esc_html_e( 'Search Engine Indexing', 'quinrel' ); ?></label>
				<select name="quinrel_noindex" id="quinrel_noindex" style="width:100%;margin-top:4px;">
					<option value="no"><?php esc_html_e( 'Default (Allow Indexing)', 'quinrel' ); ?></option>
					<option value="yes"><?php esc_html_e( 'Noindex (Block Search Engines)', 'quinrel' ); ?></option>
				</select>
			</div>
		</div>
		<?php
	}

	/**
	 * Render fields on the Edit Term screen (table row layout).
	 *
	 * @param \WP_Term $term Current term object.
	 * @param string   $taxonomy Current taxonomy slug.
	 */
	public function renderEditFormFields( \WP_Term $term, string $taxonomy ): void {
		$title       = (string) get_term_meta( $term->term_id, '_quinrel_meta_title', true );
		$description = (string) get_term_meta( $term->term_id, '_quinrel_meta_description', true );
		$canonical   = (string) get_term_meta( $term->term_id, '_quinrel_canonical_url', true );
		$noindex     = (string) get_term_meta( $term->term_id, '_quinrel_noindex', true );

		wp_nonce_field( 'quinrel_taxonomy_seo_action', 'quinrel_taxonomy_seo_nonce' );
		?>
		<tr class="form-field">
			<th colspan="2" style="padding-top:20px;padding-bottom:10px;">
				<div style="display:flex;align-items:center;gap:8px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;">
					<span class="dashicons dashicons-chart-area" style="color:#0060F9;font-size:20px;width:20px;height:20px;"></span>
					<strong style="font-size:16px;color:#0f172a;"><?php esc_html_e( 'Quinrel SEO Settings', 'quinrel' ); ?></strong>
					<span style="font-size:11px;font-weight:600;background:#E0ECFE;color:#0140AC;padding:2px 8px;border-radius:12px;"><?php esc_html_e( 'Live Preview & Indexing', 'quinrel' ); ?></span>
				</div>
			</th>
		</tr>

		<tr class="form-field term-quinrel-title-wrap">
			<th scope="row"><label for="quinrel_meta_title"><?php esc_html_e( 'SEO Title', 'quinrel' ); ?></label></th>
			<td>
				<input type="text" name="quinrel_meta_title" id="quinrel_meta_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Custom title for Google search results…', 'quinrel' ); ?>" class="large-text" />
				<p class="description"><?php esc_html_e( 'Optimal length: 50–60 characters. Leave empty to use the taxonomy default title format.', 'quinrel' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-quinrel-desc-wrap">
			<th scope="row"><label for="quinrel_meta_description"><?php esc_html_e( 'Meta Description', 'quinrel' ); ?></label></th>
			<td>
				<textarea name="quinrel_meta_description" id="quinrel_meta_description" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Snippet shown under title in search engines…', 'quinrel' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optimal length: 120–160 characters. Search engines use this summary snippet on SERP cards.', 'quinrel' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-quinrel-canonical-wrap">
			<th scope="row"><label for="quinrel_canonical_url"><?php esc_html_e( 'Canonical URL', 'quinrel' ); ?></label></th>
			<td>
				<input type="url" name="quinrel_canonical_url" id="quinrel_canonical_url" value="<?php echo esc_url( $canonical ); ?>" placeholder="<?php esc_attr_e( 'https://…', 'quinrel' ); ?>" class="large-text" />
				<p class="description"><?php esc_html_e( 'Leave empty for automatic self-referencing canonical URL.', 'quinrel' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-quinrel-noindex-wrap">
			<th scope="row"><label for="quinrel_noindex"><?php esc_html_e( 'Search Engine Indexing', 'quinrel' ); ?></label></th>
			<td>
				<select name="quinrel_noindex" id="quinrel_noindex" style="max-width:280px;">
					<option value="no" <?php selected( $noindex, 'no' ); ?>><?php esc_html_e( 'Default (Allow Search Indexing)', 'quinrel' ); ?></option>
					<option value="yes" <?php selected( $noindex, 'yes' ); ?>><?php esc_html_e( 'Noindex (Block Search Engines)', 'quinrel' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Set to Noindex if you do not want this taxonomy archive appearing in search engine results.', 'quinrel' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term metadata securely.
	 *
	 * @param int $termId ID of the created or edited term.
	 */
	public function saveTermMeta( int $termId ): void {
		// Nonce verification
		if ( ! isset( $_POST['quinrel_taxonomy_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quinrel_taxonomy_seo_nonce'] ) ), 'quinrel_taxonomy_seo_action' ) ) {
			return;
		}

		// Authorization capability check
		if ( ! current_user_can( 'manage_categories' ) && ! current_user_can( 'edit_seo_meta' ) ) {
			return;
		}

		// SEO Title
		if ( isset( $_POST['quinrel_meta_title'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['quinrel_meta_title'] ) );
			if ( '' !== $title ) {
				update_term_meta( $termId, '_quinrel_meta_title', $title );
			} else {
				delete_term_meta( $termId, '_quinrel_meta_title' );
			}
		}

		// Meta Description
		if ( isset( $_POST['quinrel_meta_description'] ) ) {
			$desc = sanitize_textarea_field( wp_unslash( $_POST['quinrel_meta_description'] ) );
			if ( '' !== $desc ) {
				update_term_meta( $termId, '_quinrel_meta_description', $desc );
			} else {
				delete_term_meta( $termId, '_quinrel_meta_description' );
			}
		}

		// Canonical URL
		if ( isset( $_POST['quinrel_canonical_url'] ) ) {
			$canonical = esc_url_raw( wp_unslash( $_POST['quinrel_canonical_url'] ) );
			if ( '' !== $canonical ) {
				update_term_meta( $termId, '_quinrel_canonical_url', $canonical );
			} else {
				delete_term_meta( $termId, '_quinrel_canonical_url' );
			}
		}

		// Robots Noindex
		if ( isset( $_POST['quinrel_noindex'] ) ) {
			$noindex = sanitize_key( wp_unslash( $_POST['quinrel_noindex'] ) );
			if ( 'yes' === $noindex ) {
				update_term_meta( $termId, '_quinrel_noindex', 'yes' );
			} else {
				delete_term_meta( $termId, '_quinrel_noindex' );
			}
		}
	}

	/**
	 * Add an SEO column to the taxonomy list table.
	 *
	 * @param array<string, string> $columns Existing list table columns.
	 * @return array<string, string> Updated columns.
	 */
	public function addColumn( array $columns ): array {
		$newColumns = array();
		foreach ( $columns as $key => $title ) {
			$newColumns[ $key ] = $title;
			if ( 'name' === $key ) {
				$newColumns['quinrel_tax_seo'] = __( 'SEO', 'quinrel' );
			}
		}
		return $newColumns;
	}

	/**
	 * Render the SEO column indicator for each taxonomy row.
	 *
	 * @param string $content Default content.
	 * @param string $columnName Current column slug.
	 * @param int    $termId Term ID.
	 * @return string Rendered HTML.
	 */
	public function renderColumn( string $content, string $columnName, int $termId ): string {
		if ( 'quinrel_tax_seo' !== $columnName ) {
			return $content;
		}

		$title   = (string) get_term_meta( $termId, '_quinrel_meta_title', true );
		$desc    = (string) get_term_meta( $termId, '_quinrel_meta_description', true );
		$noindex = (string) get_term_meta( $termId, '_quinrel_noindex', true );

		if ( 'yes' === $noindex ) {
			return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 6px;border-radius:4px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:600;">' . esc_html__( 'Noindex', 'quinrel' ) . '</span>';
		}

		$hasCustom = ( '' !== trim( $title ) ) || ( '' !== trim( $desc ) );

		if ( $hasCustom ) {
			return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 6px;border-radius:4px;background:#dcfce7;color:#166534;font-size:11px;font-weight:600;"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#22c55e;"></span>' . esc_html__( 'Optimized', 'quinrel' ) . '</span>';
		}

		return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 6px;border-radius:4px;background:#f1f5f9;color:#64748b;font-size:11px;font-weight:500;">' . esc_html__( 'Default', 'quinrel' ) . '</span>';
	}
}
