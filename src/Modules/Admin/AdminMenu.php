<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {

	public function registerMenu(): void {
		// Main top-level entry
		add_menu_page(
			__( 'Quinrel', 'quinrel' ),
			__( 'Quinrel', 'quinrel' ),
			'edit_seo_meta',
			'quinrel',
			array( $this, 'renderAdminPage' ),
			'dashicons-chart-area',
			85
		);

		// 1. Dashboard (first submenu re-labels parent entry in WordPress flyout menu)
		add_submenu_page(
			'quinrel',
			__( 'Dashboard & Overview', 'quinrel' ),
			__( 'Dashboard', 'quinrel' ),
			'edit_seo_meta',
			'quinrel',
			array( $this, 'renderAdminPage' )
		);

		// 2. Action Items / To-Do
		add_submenu_page(
			'quinrel',
			__( 'Action Items / To-Do', 'quinrel' ),
			__( 'Action Items', 'quinrel' ),
			'edit_seo_meta',
			'quinrel&tab=todo',
			array( $this, 'renderAdminPage' )
		);

		// 3. Site Health
		add_submenu_page(
			'quinrel',
			__( 'Site Health Audit', 'quinrel' ),
			__( 'Site Health', 'quinrel' ),
			'view_seo_reports',
			'quinrel&tab=site_audit',
			array( $this, 'renderAdminPage' )
		);

		// 4. Titles & Meta
		add_submenu_page(
			'quinrel',
			__( 'Titles & Meta', 'quinrel' ),
			__( 'Titles & Meta', 'quinrel' ),
			'edit_seo_meta',
			'quinrel&tab=bulk_meta',
			array( $this, 'renderAdminPage' )
		);

		// 5. XML Sitemaps
		add_submenu_page(
			'quinrel',
			__( 'XML Sitemaps', 'quinrel' ),
			__( 'XML Sitemaps', 'quinrel' ),
			'manage_seo',
			'quinrel&tab=sitemaps',
			array( $this, 'renderAdminPage' )
		);

		// 6. Schema Markup
		add_submenu_page(
			'quinrel',
			__( 'Schema Markup', 'quinrel' ),
			__( 'Schema Markup', 'quinrel' ),
			'manage_seo',
			'quinrel&tab=schema',
			array( $this, 'renderAdminPage' )
		);

		// 7. Redirects & 404
		add_submenu_page(
			'quinrel',
			__( 'Redirects & 404', 'quinrel' ),
			__( 'Redirects & 404', 'quinrel' ),
			'manage_redirects',
			'quinrel&tab=technical',
			array( $this, 'renderAdminPage' )
		);

		// 8. AI & LLMs (llms.txt)
		add_submenu_page(
			'quinrel',
			__( 'AI Discovery (llms.txt)', 'quinrel' ),
			__( 'AI Discovery', 'quinrel' ),
			'manage_seo',
			'quinrel&tab=llms',
			array( $this, 'renderAdminPage' )
		);

		// 9. Settings
		add_submenu_page(
			'quinrel',
			__( 'Settings & Integrations', 'quinrel' ),
			__( 'Settings', 'quinrel' ),
			'manage_seo',
			'quinrel&tab=settings',
			array( $this, 'renderAdminPage' )
		);
	}

	public function enqueueAssets( string $hook ): void {
		// Only load assets on our plugin pages
		if ( $hook !== 'toplevel_page_quinrel' && false === strpos( $hook, 'page_quinrel' ) ) {
			return;
		}

		$assetFile = QUINREL_PLUGIN_DIR . 'build/index.asset.php';

		if ( file_exists( $assetFile ) ) {
			$assets = require $assetFile;
			wp_enqueue_script(
				'quinrel-admin-js',
				QUINREL_PLUGIN_URL . 'build/index.js',
				$assets['dependencies'],
				$assets['version'],
				true
			);

			// Determine initial tab from query parameter if present
			$initialTab = '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['tab'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$initialTab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			}

			$adminConfig = array(
				'apiUrl'          => esc_url_raw( rest_url( 'quinrel/v1' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'setupComplete'   => get_option( 'quinrel_setup_complete' ) ? '1' : '0',
				'initialTab'      => $initialTab,
				'isMultisite'     => is_multisite() ? '1' : '0',
				'cloudConfigured' => \Quinrel\WordPress\Core\Mode\OperatingMode::isCloudConfigured() ? '1' : '0',
				'capabilities'    => array(
					'manage_seo'       => current_user_can( 'manage_seo' ),
					'view_seo_reports' => current_user_can( 'view_seo_reports' ),
					'manage_redirects' => current_user_can( 'manage_redirects' ),
					'edit_seo_meta'    => current_user_can( 'edit_seo_meta' ),
					'manage_options'   => current_user_can( 'manage_options' ),
				),
			);

			wp_localize_script( 'quinrel-admin-js', 'quinrelAdminConfig', $adminConfig );
		}

		wp_enqueue_style(
			'quinrel-admin-css',
			QUINREL_PLUGIN_URL . 'build/index.css',
			array(),
			QUINREL_VERSION
		);
		wp_style_add_data( 'quinrel-admin-css', 'rtl', 'replace' );
	}

	public function renderAdminPage(): void {
		echo '<div class="wrap"><div id="quinrel-admin-app"><div class="quinrel-skeleton-wrap" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-top:20px;min-height:480px;display:flex;align-items:center;justify-content:center;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;"><p style="font-size:14px;font-weight:500;">' . esc_html__( 'Loading Quinrel...', 'quinrel' ) . '</p></div></div></div>';
	}
}
