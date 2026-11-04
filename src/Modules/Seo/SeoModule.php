<?php

namespace Quinrel\WordPress\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Modules\Sitemap\SitemapModule;

class SeoModule {

	private EventManager $eventManager;
	private MetaTagsGenerator $metaGenerator;
	private OpenGraphGenerator $openGraphGenerator;

	public function __construct(
		EventManager $eventManager,
		MetaTagsGenerator $metaGenerator,
		OpenGraphGenerator $openGraphGenerator
	) {
		$this->eventManager       = $eventManager;
		$this->metaGenerator      = $metaGenerator;
		$this->openGraphGenerator = $openGraphGenerator;
	}

	public function boot(): void {
		$rssOptimizations = new RssOptimizations();
		$rssOptimizations->register();

		if ( is_admin() ) {
			$this->eventManager->addAction( 'admin_notices', array( $this, 'renderHeadNotice' ) );
			add_action( 'admin_post_quinrel_own_head', array( $this, 'confirmOwnsHead' ) );
		}

		if ( ! self::printsHead() ) {
			return;
		}

		// Quinrel replaces the core canonical and robots tags only when it owns the head.
		$this->eventManager->removeAction( 'wp_head', 'rel_canonical', 10 );
		$this->eventManager->removeAction( 'wp_head', 'wp_robots', 1 );
		$this->eventManager->addFilter( 'wp_robots', array( $this->metaGenerator, 'filterWpRobots' ), 20, 1 );

		$this->eventManager->addAction( 'wp_head', array( $this->metaGenerator, 'outputStandardMetaTags' ), 1 );
		$this->eventManager->addAction( 'wp_head', array( $this->openGraphGenerator, 'outputSocialMetaTags' ), 2 );
		$this->eventManager->addFilter( 'pre_get_document_title', array( $this->metaGenerator, 'getDocumentTitle' ), 10, 1 );
	}

	/**
	 * Quinrel prints titles and meta when no other SEO plugin is active,
	 * or after an administrator confirms that Quinrel owns the head.
	 */
	public static function printsHead(): bool {
		if ( get_option( 'quinrel_owns_head', 'no' ) === 'yes' ) {
			return true;
		}

		return SitemapModule::conflictingSitemapPlugins() === array();
	}

	/**
	 * On Quinrel screens, offer to take over the head from another SEO plugin.
	 */
	public function renderHeadNotice(): void {
		if ( self::printsHead() ) {
			return;
		}
		if ( ! current_user_can( 'manage_seo' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || ! str_contains( (string) $screen->id, 'quinrel' ) ) {
			return;
		}

		$names = SitemapModule::conflictingSitemapPlugins();
		if ( $names === array() ) {
			return;
		}

		$text = sprintf(
			/* translators: %s: names of the other SEO plugins. */
			__( '%s is writing titles and meta. Quinrel leaves the head alone until you confirm it.', 'quinrel' ),
			implode( ', ', $names )
		);

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><form method="post" action="%2$s"><input type="hidden" name="action" value="quinrel_own_head" /><input type="hidden" name="_wpnonce" value="%3$s" /><p><button type="submit" class="button button-primary">%4$s</button></p></form></div>',
			esc_html( $text ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( wp_create_nonce( 'quinrel_own_head' ) ),
			esc_html__( 'Let Quinrel write titles and meta.', 'quinrel' )
		);
	}

	/**
	 * Save the confirmation that Quinrel should write titles and meta.
	 */
	public function confirmOwnsHead(): void {
		$allowed = current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';
		if ( ! $allowed || ! wp_verify_nonce( $nonce, 'quinrel_own_head' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'quinrel' ), '', array( 'response' => 403 ) );
		}

		update_option( 'quinrel_owns_head', 'yes', true );
		wp_safe_redirect( admin_url( 'admin.php?page=quinrel' ) );
		exit;
	}
}
