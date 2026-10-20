<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists features this install already provides.
 *
 * Search Console, redirects, the technical audit, rank checks, the writing
 * assistant, content gap, and competitor schema import run without a Quinrel
 * account. Names that are not implemented are omitted so they are not offered
 * as a locked upgrade.
 */
class UpgradePromptManager {

	private const DISMISSED_META = 'quinrel_upgrade_banner_dismissed';

	public function boot(): void {
		add_action( 'admin_notices', array( $this, 'showUpgradeBannerNotice' ) );
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		add_action( 'wp_ajax_quinrel_dismiss_upgrade_banner', array( $this, 'handleDismissAjax' ) );
	}

	/**
	 * The old notice told every site to upgrade for Search Console, the rank
	 * tracker, and the technical audit. Those features already run here.
	 */
	public function showUpgradeBannerNotice(): void {
	}

	public function handleDismissAjax(): void {
		check_ajax_referer( 'quinrel_dismiss_banner' );
		update_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		wp_die();
	}

	// ── REST routes ───────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		$editorCap = fn() => current_user_can( 'edit_posts' );

		( new StandaloneDashboard() )->registerRoutes();

		register_rest_route(
			'quinrel/v1',
			'/upgrade/features',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'listFeatures' ),
				'permission_callback' => $editorCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/upgrade/dismiss-banner',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dismissBanner' ),
				'permission_callback' => $editorCap,
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/upgrade/check-feature',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkFeature' ),
				'permission_callback' => $editorCap,
			)
		);
	}

	public function listFeatures( \WP_REST_Request $request ): \WP_REST_Response {
		$currentTier = \Quinrel\WordPress\Core\Api\CloudCredential::cachedTier();
		$features    = array();

		foreach ( $this->localFeatures() as $key => $name ) {
			$features[] = array(
				'key'           => $key,
				'name'          => $name,
				'tier_required' => 'free',
				'available'     => true,
				'upgrade_url'   => '',
			);
		}

		return rest_ensure_response(
			array(
				'current_tier' => $currentTier,
				'features'     => $features,
			)
		);
	}

	public function dismissBanner( \WP_REST_Request $request ): \WP_REST_Response {
		update_user_meta( get_current_user_id(), self::DISMISSED_META, true );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function checkFeature( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$key    = sanitize_text_field( $params['feature_key'] ?? '' );

		if ( ! $this->isFeatureAvailable( $key ) ) {
			return new \WP_Error( 'unknown_feature', __( 'Unknown feature key.', 'quinrel' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'feature_key'   => $key,
				'available'     => true,
				'tier_required' => 'free',
				'upgrade_url'   => '',
			)
		);
	}

	/**
	 * Features the plugin provides on this site, with or without an account.
	 *
	 * @return array<string, string>
	 */
	private function localFeatures(): array {
		return array(
			'writing_assistant'  => __( 'AI Writing Assistant', 'quinrel' ),
			'content_gap'        => __( 'Content Gap Analysis', 'quinrel' ),
			'rank_tracker'       => __( 'Keyword Rank Tracker', 'quinrel' ),
			'schema_aggregation' => __( 'Schema Competitor Import', 'quinrel' ),
			'gsc_integration'    => __( 'Google Search Console', 'quinrel' ),
			'technical_audit'    => __( 'Full Technical SEO Audit', 'quinrel' ),
			'bulk_csv_redirects' => __( 'Bulk CSV Redirects', 'quinrel' ),
		);
	}

	/**
	 * A feature is available when this install implements it. The tier option does not hide it.
	 */
	public function isFeatureAvailable( string $featureKey ): bool {
		return array_key_exists( $featureKey, $this->localFeatures() );
	}
}
