<?php

namespace Quinrel\WordPress\Modules\Compliance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CookieBannerModule: Lightweight cookie notice.
 *
 * Injects a consent bar into wp_footer with zero JS dependencies.
 * This module records a visitor's notice choice. It does not discover, block,
 * categorize, or manage third-party scripts and is therefore not presented as
 * a GDPR/CCPA consent-management solution.
 * Stores consent in a first-party cookie (quinrel_consent).
 */
class CookieBannerModule {

	private const OPTION_KEY     = 'quinrel_cookie_banner';
	private const CONSENT_COOKIE = 'quinrel_consent';

	public function boot(): void {
		add_action( 'wp_footer', array( $this, 'renderBanner' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueAssets' ) );
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function enqueueAssets(): void {
		$config = $this->getConfig();
		if ( ! $config['enabled'] ) {
			return;
		}

		$pluginUrl = QUINREL_PLUGIN_URL;

		wp_enqueue_style(
			'quinrel-cookie-banner',
			$pluginUrl . 'build/cookie-banner.css',
			array(),
			QUINREL_VERSION ?? '1.0.1'
		);

		wp_enqueue_script(
			'quinrel-cookie-banner',
			$pluginUrl . 'build/cookie-banner.js',
			array(),
			QUINREL_VERSION ?? '1.0.1',
			true
		);

		wp_localize_script(
			'quinrel-cookie-banner',
			'quinrelCookieConfig',
			array(
				'cookieName' => self::CONSENT_COOKIE,
				'cookieDays' => 365,
			)
		);
	}

	public function renderBanner(): void {
		$config = $this->getConfig();
		if ( ! $config['enabled'] ) {
			return;
		}
		?>
		<div id="quinrel-cookie-banner" class="quinrel-cookie-banner" role="dialog" aria-live="polite" aria-label="<?php echo esc_attr__( 'Cookie consent', 'quinrel' ); ?>" style="display:none;">
			<div class="quinrel-cookie-banner__inner">
				<p class="quinrel-cookie-banner__text"><?php echo wp_kses_post( $config['message'] ); ?>
					<?php if ( ! empty( $config['policy_url'] ) ) : ?>
						<a href="<?php echo esc_url( $config['policy_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Privacy Policy', 'quinrel' ); ?></a>.
					<?php endif; ?>
				</p>
				<div class="quinrel-cookie-banner__actions">
					<button id="quinrel-cookie-accept" class="quinrel-cookie-btn quinrel-cookie-btn--accept"><?php echo esc_html( $config['accept_label'] ); ?></button>
					<button id="quinrel-cookie-decline" class="quinrel-cookie-btn quinrel-cookie-btn--decline"><?php echo esc_html( $config['decline_label'] ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	// ── REST API ─────────────────────────────────────────────────────────────

	public function registerRoutes(): void {
		register_rest_route(
			'quinrel/v1',
			'/settings/cookie-banner',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getSettings' ),
					'permission_callback' => fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveSettings' ),
					'permission_callback' => fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' ),
				),
			)
		);
	}

	public function getSettings(): \WP_REST_Response {
		return rest_ensure_response( $this->getConfig() );
	}

	public function saveSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$config = array(
			'enabled'       => (bool) ( $params['enabled'] ?? false ),
			'message'       => wp_kses_post( $params['message'] ?? $this->defaultMessage() ),
			'accept_label'  => sanitize_text_field( $params['accept_label'] ?? 'Accept' ),
			'decline_label' => sanitize_text_field( $params['decline_label'] ?? 'Decline' ),
			'policy_url'    => esc_url_raw( $params['policy_url'] ?? '' ),
		);
		update_option( self::OPTION_KEY, $config, true );
		return rest_ensure_response(
			array(
				'success' => true,
				'config'  => $config,
			)
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function getConfig(): array {
		$defaults = array(
			'enabled'       => false,
			'message'       => $this->defaultMessage(),
			'accept_label'  => 'Accept',
			'decline_label' => 'Decline',
			'policy_url'    => '',
		);
		$saved    = get_option( self::OPTION_KEY, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	private function defaultMessage(): string {
		return 'This site uses cookies. Review the privacy policy for details.';
	}
}
