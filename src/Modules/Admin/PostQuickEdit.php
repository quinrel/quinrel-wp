<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Quick Edit SEO capabilities to the Posts and Pages list tables,
 * enabling users to edit SEO titles, meta descriptions, and indexing flags
 * directly from the table without opening the full editor.
 */
class PostQuickEdit {

	/**
	 * Register hooks for quick edit integration.
	 */
	public function register(): void {
		add_action( 'quick_edit_custom_box', array( $this, 'renderQuickEditBox' ), 10, 2 );
		add_action( 'save_post', array( $this, 'saveQuickEdit' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueQuickEditScript' ) );
	}

	/**
	 * Render the Quick Edit SEO fields.
	 *
	 * @param string $columnName Column slug.
	 * @param string $postType Current post type.
	 */
	public function renderQuickEditBox( string $columnName, string $postType ): void {
		if ( 'quinrel_seo' !== $columnName ) {
			return;
		}

		wp_nonce_field( 'quinrel_quick_edit_action', 'quinrel_quick_edit_nonce' );
		?>
		<fieldset class="inline-edit-col-right inline-edit-quinrel-seo" style="margin-top:10px;">
			<div class="inline-edit-col" style="padding:10px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:8px;">
				<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
					<span class="dashicons dashicons-chart-area" style="color:#4f46e5;font-size:16px;width:16px;height:16px;"></span>
					<strong style="color:#0f172a;font-size:12px;"><?php esc_html_e( 'Quinrel SEO Quick Settings', 'quinrel' ); ?></strong>
				</div>

				<label style="display:block;margin-bottom:6px;">
					<span class="title" style="width:110px;display:inline-block;font-size:11px;font-weight:600;"><?php esc_html_e( 'SEO Title', 'quinrel' ); ?></span>
					<span class="input-text-wrap" style="display:inline-block;width:calc(100% - 120px);">
						<input type="text" name="quinrel_meta_title" class="text quinrel-quick-title" value="" placeholder="<?php esc_attr_e( 'Custom search engine title…', 'quinrel' ); ?>" style="width:100%;" />
					</span>
				</label>

				<label style="display:block;margin-bottom:6px;">
					<span class="title" style="width:110px;display:inline-block;font-size:11px;font-weight:600;"><?php esc_html_e( 'Meta Description', 'quinrel' ); ?></span>
					<span class="input-text-wrap" style="display:inline-block;width:calc(100% - 120px);">
						<textarea name="quinrel_meta_description" class="quinrel-quick-desc" rows="2" placeholder="<?php esc_attr_e( 'Search snippet description…', 'quinrel' ); ?>" style="width:100%;"></textarea>
					</span>
				</label>

				<label style="display:inline-flex;align-items:center;gap:6px;margin-top:4px;">
					<input type="checkbox" name="quinrel_noindex" value="yes" class="quinrel-quick-noindex" />
					<span style="font-size:11px;font-weight:600;color:#991b1b;"><?php esc_html_e( 'Noindex (Block search engine indexing)', 'quinrel' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save Quick Edit metadata securely.
	 *
	 * @param int      $postId Post ID.
	 * @param \WP_Post $post Post object.
	 */
	public function saveQuickEdit( int $postId, \WP_Post $post ): void {
		if ( ! isset( $_POST['quinrel_quick_edit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['quinrel_quick_edit_nonce'] ) ), 'quinrel_quick_edit_action' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}

		// SEO Title
		if ( isset( $_POST['quinrel_meta_title'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['quinrel_meta_title'] ) );
			if ( '' !== $title ) {
				update_post_meta( $postId, '_quinrel_meta_title', $title );
			} else {
				delete_post_meta( $postId, '_quinrel_meta_title' );
			}
		}

		// Meta Description
		if ( isset( $_POST['quinrel_meta_description'] ) ) {
			$desc = sanitize_textarea_field( wp_unslash( $_POST['quinrel_meta_description'] ) );
			if ( '' !== $desc ) {
				update_post_meta( $postId, '_quinrel_meta_description', $desc );
			} else {
				delete_post_meta( $postId, '_quinrel_meta_description' );
			}
		}

		// Noindex
		if ( isset( $_POST['quinrel_noindex'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['quinrel_noindex'] ) ) ) {
			update_post_meta( $postId, '_quinrel_noindex', 'yes' );
		} else {
			delete_post_meta( $postId, '_quinrel_noindex' );
		}
	}

	/**
	 * Enqueue minimal inline JavaScript on edit.php to populate Quick Edit fields.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueueQuickEditScript( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$base = defined( 'QUINREL_PLUGIN_URL' ) ? QUINREL_PLUGIN_URL : '';
		wp_enqueue_script(
			'quinrel-quick-edit',
			$base . 'src/assets/js/quick-edit.js',
			array( 'jquery', 'inline-edit-post' ),
			QUINREL_VERSION,
			true
		);
	}
}

