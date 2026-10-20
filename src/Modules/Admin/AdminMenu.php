<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {

	public function registerMenu(): void {
		// Use the lowest-tier SEO capability as the menu gate.
		// The React SPA handles per-tab permission gating based on the
		// capabilities map passed via wp_localize_script in enqueueAssets().
		add_menu_page(
			__( 'Quinrel', 'quinrel' ),
			__( 'Quinrel', 'quinrel' ),
			'edit_seo_meta',
			'quinrel',
			array( $this, 'renderAdminPage' ),
			'dashicons-chart-area',
			85
		);
	}

	public function enqueueAssets( string $hook ): void {
		// Only load assets on our plugin page
		if ( $hook !== 'toplevel_page_quinrel' ) {
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

			$adminConfig = array(
				'apiUrl'          => esc_url_raw( rest_url( 'quinrel/v1' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'setupComplete'   => get_option( 'quinrel_setup_complete' ) ? '1' : '0',
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
