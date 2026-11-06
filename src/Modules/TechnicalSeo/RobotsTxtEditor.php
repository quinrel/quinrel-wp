<?php

namespace Quinrel\WordPress\Modules\TechnicalSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Quinrel rules to the robots.txt document WordPress and other plugins built.
 * A saved document replaces that file only after an administrator confirms it.
 *
 * BL-010: Editable custom AI bot list (merged with built-in AI_BOTS at runtime).
 * BL-032: LLM training data opt-out (NoAI directive + TDM-Reservation HTTP header).
 */
class RobotsTxtEditor {

	private const OPTION_KEY           = 'quinrel_robots_txt';
	private const REPLACE_OPTION       = 'quinrel_robots_txt_replaces_generated';
	private const CUSTOM_BOTS_OPTION   = 'quinrel_custom_ai_bots';
	private const TRAINING_OPT_OUT_OPT = 'quinrel_ai_training_optout';
	private const MAX_ROBOTS_BYTES     = 100000;

	// List of major AI scraper/crawler User-Agents (built-in, always applied)
	private const AI_BOTS = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'CCBot',
		'Google-Extended',
		'Anthropic-AI',
		'Claude-Web',
		'ClaudeBot',
		'Claude-SearchBot',
		'cohere-ai',
		'Omgilibot',
		'Omgili',
		'PerplexityBot',
		'YouBot',
	);

	/**
	 * Crawlers that fetch pages for answers. Blocking training crawlers leaves these allowed.
	 */
	private const SEARCH_AGENTS = array(
		'OAI-SearchBot',
		'Claude-SearchBot',
	);

	// Crawlers used exclusively for training data scraping
	private const AI_TRAINING_BOTS = array(
		'GPTBot',
		'CCBot',
		'Google-Extended',
		'Anthropic-AI',
		'ClaudeBot',
		'cohere-ai',
		'Omgilibot',
		'Omgili',
		'YouBot',
		'Bytespider',
		'Diffbot',
		'FacebookBot',
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'filterRobotsTxt' ), 999, 2 );
		add_action( 'init', array( $this, 'proactiveBlockAiBots' ) );
		add_action( 'send_headers', array( $this, 'addTrainingOptOutHeaders' ) );
	}

	/**
	 * Register REST routes for the robots.txt editor.
	 */
	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/robots-txt',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getRobotsTxt' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateRobotsTxt' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		// BL-010: Editable AI bot list
		register_rest_route(
			'quinrel/v1',
			'/ai-bots',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getAiBots' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'updateAiBots' ),
					'permission_callback' => $adminCap,
				),
			)
		);

		// BL-032: Training data opt-out toggle
		register_rest_route(
			'quinrel/v1',
			'/ai-training-optout',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( array( 'enabled' => get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) === 'yes' ) ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => function ( \WP_REST_Request $req ) {
						$p = $req->get_json_params();
						update_option( self::TRAINING_OPT_OUT_OPT, ! empty( $p['enabled'] ) ? 'yes' : 'no', true );
						return rest_ensure_response( array( 'success' => true ) );
					},
					'permission_callback' => $adminCap,
				),
			)
		);
	}

	// ── BL-010: Bot list management ───────────────────────────────────────────

	/**
	 * Merge the built-in AI_BOTS with any custom additions from wp_options.
	 */
	public function getMergedBotList(): array {
		$custom = (array) get_option( self::CUSTOM_BOTS_OPTION, array() );
		$bots   = array();

		foreach ( array_merge( self::AI_BOTS, $custom ) as $bot ) {
			$token = $this->botToken( $bot );
			if ( '' !== $token ) {
				$bots[] = $token;
			}
		}

		return array_values( array_unique( $bots ) );
	}

	public function getAiBots( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'builtin'  => self::AI_BOTS,
				'training' => self::AI_TRAINING_BOTS,
				'custom'   => (array) get_option( self::CUSTOM_BOTS_OPTION, array() ),
				'merged'   => $this->getMergedBotList(),
			)
		);
	}

	/**
	 * Retrieve list of training bots combined with custom additions.
	 */
	public function getTrainingBotsList(): array {
		$custom = (array) get_option( self::CUSTOM_BOTS_OPTION, array() );
		$bots   = array();

		foreach ( array_merge( self::AI_TRAINING_BOTS, $custom ) as $bot ) {
			$token = $this->botToken( $bot );
			if ( '' !== $token ) {
				$bots[] = $token;
			}
		}

		return array_values( array_unique( $bots ) );
	}

	public function userAgentMatchesTrainingBot( string $userAgent ): bool {
		if ( '' === $userAgent ) {
			return false;
		}

		foreach ( $this->getTrainingBotsList() as $bot ) {
			$pattern = '/(^|[^A-Za-z0-9])' . preg_quote( $bot, '/' ) . '([^A-Za-z0-9]|$)/i';
			if ( 1 === preg_match( $pattern, $userAgent ) ) {
				return true;
			}
		}

		return false;
	}

	public function updateAiBots( \WP_REST_Request $request ): \WP_REST_Response {
		$params     = $request->get_json_params();
		$customBots = array_map( 'sanitize_text_field', (array) ( $params['custom_bots'] ?? array() ) );
		// Letters, numbers, dots, underscores, and hyphens. 4 to 100 characters. At most 50 bots.
		$customBots = array_slice(
			array_values(
				array_filter(
					array_map( array( $this, 'botToken' ), $customBots ),
					static fn( $bot ) => '' !== $bot
				)
			),
			0,
			50
		);

		update_option( self::CUSTOM_BOTS_OPTION, array_values( $customBots ), true );

		return rest_ensure_response(
			array(
				'success' => true,
				'merged'  => $this->getMergedBotList(),
			)
		);
	}

	// ── robots.txt endpoints ──────────────────────────────────────────────────

	public function getRobotsTxt( \WP_REST_Request $request ): \WP_REST_Response {
		$blockAi = get_option( 'quinrel_block_ai_bots', 'no' ) === 'yes';
		$policy  = get_option( 'quinrel_ai_bot_policy', $blockAi ? 'block_all' : 'allow_all' );

		return rest_ensure_response(
			array(
				'content'           => (string) get_option( self::OPTION_KEY, '' ),
				'replace_generated' => $this->replacesGeneratedFile(),
				'block_ai_bots'     => $blockAi,
				'ai_bot_policy'     => $policy,
				'training_optout'   => get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) === 'yes',
			)
		);
	}

	/**
	 * Save robots.txt settings.
	 *
	 * A stored document replaces WordPress and other plugins only when the
	 * request also acknowledges that replacement.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function updateRobotsTxt( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$hasContent = array_key_exists( 'content', $params );
		if ( $hasContent && ! is_string( $params['content'] ) ) {
			return new \WP_Error(
				'invalid_robots_txt',
				__( 'robots.txt content must be text.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		$nextContent = $hasContent
			? sanitize_textarea_field( $params['content'] )
			: (string) get_option( self::OPTION_KEY, '' );

		if ( strlen( $nextContent ) > self::MAX_ROBOTS_BYTES ) {
			return new \WP_Error(
				'invalid_robots_txt',
				__( 'robots.txt is too large.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		$hasReplace = array_key_exists( 'replace_generated', $params );
		$replace    = $hasReplace
			? $this->isAffirmative( $params['replace_generated'] )
			: ( get_option( self::REPLACE_OPTION, 'no' ) === 'yes' );

		if ( $replace && '' === trim( $nextContent ) ) {
			return new \WP_Error(
				'robots_replace_empty',
				__( 'Add robots.txt rules before replacing the generated file.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		if ( $hasContent ) {
			update_option( self::OPTION_KEY, $nextContent, false );
		}

		if ( $hasReplace ) {
			update_option( self::REPLACE_OPTION, ( $replace && '' !== trim( $nextContent ) ) ? 'yes' : 'no', true );
		}

		if ( isset( $params['ai_bot_policy'] ) ) {
			$policy = sanitize_key( (string) $params['ai_bot_policy'] );
			if ( in_array( $policy, array( 'allow_all', 'search_only', 'block_all' ), true ) ) {
				update_option( 'quinrel_ai_bot_policy', $policy, true );
				update_option( 'quinrel_block_ai_bots', 'block_all' === $policy ? 'yes' : 'no', true );
			}
		} elseif ( isset( $params['block_ai_bots'] ) ) {
			$block = ! empty( $params['block_ai_bots'] ) ? 'yes' : 'no';
			update_option( 'quinrel_block_ai_bots', $block, true );
			update_option( 'quinrel_ai_bot_policy', 'yes' === $block ? 'block_all' : 'allow_all', true );
		}

		return rest_ensure_response(
			array(
				'success'           => true,
				'replace_generated' => $this->replacesGeneratedFile(),
			)
		);
	}

	// ── robots.txt filter ─────────────────────────────────────────────────────

	public function filterRobotsTxt( string $output, bool $isPublic ): string {
		$replacing = $this->replacesGeneratedFile();
		$document  = $replacing ? (string) get_option( self::OPTION_KEY, '' ) : $output;

		return $this->appendQuinrelRules( $document, $isPublic && ! $replacing );
	}

	// ── BL-032: HTTP headers for training opt-out ─────────────────────────────

	public function addTrainingOptOutHeaders(): void {
		if ( get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) !== 'yes' ) {
			return;
		}

		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		header( 'TDM-Reservation: 1' );
		header( 'X-Robots-Tag: noai, noimageai', false );
	}

	/**
	 * Returns a static method that MetaTagsGenerator can call to add a per-post AI meta tag.
	 * Usage in MetaTagsGenerator::getMetaTags(): echo RobotsTxtEditor::getPerPostAiMeta(get_the_ID());
	 */
	public static function getPerPostAiMeta( int $postId ): string {
		$allowAi = get_post_meta( $postId, '_quinrel_ai_training_allow', true );
		if ( $allowAi === 'yes' ) {
			return '<meta name="robots" content="ai">' . "\n";
		}

		return '';
	}

	// ── Proactive HTTP-level AI bot blocking ──────────────────────────────────

	public function proactiveBlockAiBots(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$blockAi = get_option( 'quinrel_block_ai_bots', 'no' ) === 'yes';
		$policy  = get_option( 'quinrel_ai_bot_policy', $blockAi ? 'block_all' : 'allow_all' );

		if ( 'allow_all' === $policy && ! $blockAi ) {
			return;
		}

		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( '' === $userAgent ) {
			return;
		}

		$shouldBlock = ( 'search_only' === $policy )
			? $this->userAgentMatchesTrainingBot( $userAgent )
			: $this->userAgentMatchesAiBot( $userAgent );

		if ( ! $shouldBlock ) {
			return;
		}

		status_header( 403 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex', false );

		// Site name, URL, and visible strings are escaped in blockedResponseHtml().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->blockedResponseHtml();
		exit;
	}

	/**
	 * Whether the user agent contains an AI crawler token.
	 *
	 * A short name such as Omgili matches Omgili/1.0 and does not match a longer
	 * product token such as NotOmgiliBrowser.
	 */
	public function userAgentMatchesAiBot( string $userAgent ): bool {
		if ( '' === $userAgent ) {
			return false;
		}

		foreach ( $this->getMergedBotList() as $bot ) {
			$pattern = '/(^|[^A-Za-z0-9])' . preg_quote( $bot, '/' ) . '([^A-Za-z0-9]|$)/i';
			if ( 1 === preg_match( $pattern, $userAgent ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * HTML shown when an AI crawler is blocked.
	 *
	 * The lang attribute is included only for an English site locale. A non-English
	 * locale does not claim a language, because this document may still be English
	 * until a translation is loaded.
	 */
	public function blockedResponseHtml(): string {
		$siteName = esc_html( get_bloginfo( 'name' ) );
		$homeUrl  = esc_url( home_url( '/' ) );
		$lang     = $this->englishLanguageTag();

		$title = sprintf(
			/* translators: %s: site name. */
			esc_html__( '403 Forbidden — %s', 'quinrel' ),
			$siteName
		);
		$heading = esc_html__( 'Access Denied', 'quinrel' );
		$message = sprintf(
			/* translators: %s: site name. */
			esc_html__( 'Automated AI crawlers and scrapers are not permitted to access %s.', 'quinrel' ),
			'<strong>' . $siteName . '</strong>'
		);
		$back    = esc_html__( 'Return to homepage', 'quinrel' );
		$htmlTag = '' === $lang ? '<html>' : '<html lang="' . esc_attr( $lang ) . '">';

		return '<!DOCTYPE html>' . "\n"
			. $htmlTag . "\n"
			. "<head>\n"
			. "<meta charset=\"UTF-8\">\n"
			. "<meta name=\"robots\" content=\"noindex\">\n"
			. '<title>' . $title . "</title>\n"
			. "<style>\n"
			. "body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f8fafc;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}\n"
			. ".wrap{max-width:480px;text-align:center;padding:40px 24px}\n"
			. "h1{font-size:4rem;font-weight:800;color:#0060F9;margin:0 0 8px}\n"
			. "h2{font-size:1.25rem;font-weight:600;margin:0 0 16px}\n"
			. "p{color:#64748b;line-height:1.6;margin:0 0 24px}\n"
			. "a{display:inline-block;padding:10px 24px;background:#0060F9;color:#fff;border-radius:6px;text-decoration:none;font-weight:600}\n"
			. "a:hover{background:#0140AC}\n"
			. "a:focus{outline:2px solid #1e293b;outline-offset:2px}\n"
			. "</style>\n"
			. "</head>\n"
			. "<body>\n"
			. "<div class=\"wrap\">\n"
			. "<h1>403</h1>\n"
			. '<h2>' . $heading . "</h2>\n"
			. '<p>' . $message . "</p>\n"
			. '<a href="' . $homeUrl . '">&larr; ' . $back . "</a>\n"
			. "</div>\n"
			. "</body>\n"
			. '</html>';
	}

	/**
	 * Append sitemap, AI crawler, and training opt-out rules.
	 */
	private function appendQuinrelRules( string $document, bool $includeSitemaps ): string {
		$blocks = array();

		if ( $includeSitemaps ) {
			$missing = array();
			foreach ( $this->quinrelSitemapLines() as $line ) {
				if ( ! str_contains( $document, $line ) ) {
					$missing[] = $line;
				}
			}
			if ( array() !== $missing ) {
				$blocks[] = implode( "\n", $missing );
			}
		}

		$blockAi = get_option( 'quinrel_block_ai_bots', 'no' ) === 'yes';
		$policy  = get_option( 'quinrel_ai_bot_policy', $blockAi ? 'block_all' : 'allow_all' );

		if ( $blockAi || 'block_all' === $policy ) {
			$lines   = array( '# Block AI Crawlers and LLM Bots (Quinrel AI Crawler Manager)' );
			$lines[] = '';
			foreach ( $this->getMergedBotList() as $bot ) {
				$lines[] = 'User-agent: ' . $bot;
				$lines[] = 'Disallow: /';
				$lines[] = '';
			}
			$blocks[] = rtrim( implode( "\n", $lines ), "\n" );
		} elseif ( 'search_only' === $policy ) {
			$lines   = array( '# Block AI Training Crawlers (Allow AI Search Agents)' );
			$lines[] = '';
			foreach ( $this->getTrainingBotsList() as $bot ) {
				$lines[] = 'User-agent: ' . $bot;
				$lines[] = 'Disallow: /';
				$lines[] = '';
			}
			$lines[] = '# ChatGPT search and Claude search stay allowed';
			foreach ( self::SEARCH_AGENTS as $agent ) {
				$lines[] = 'User-agent: ' . $agent;
				$lines[] = 'Allow: /';
				$lines[] = '';
			}
			$blocks[] = rtrim( implode( "\n", $lines ), "\n" );
		}

		if ( get_option( self::TRAINING_OPT_OUT_OPT, 'no' ) === 'yes' ) {
			$blocks[] = "# AI Training Data Opt-Out (Quinrel)\nUser-agent: *\nNoAI: 1\nNoImageAI: 1";
		}

		if ( array() === $blocks ) {
			return $document;
		}

		$suffix = implode( "\n\n", $blocks );
		if ( '' === trim( $document ) ) {
			return $suffix . "\n";
		}

		return rtrim( $document, "\r\n" ) . "\n\n" . $suffix . "\n";
	}

	/**
	 * @return array<int, string>
	 */
	private function quinrelSitemapLines(): array {
		$lines = array();

		foreach ( array( '/sitemap.xml', '/news-sitemap.xml' ) as $path ) {
			$url = esc_url_raw( home_url( $path ) );
			if ( ! is_string( $url ) || 1 !== preg_match( '#^https?://[^\s]+$#', $url ) ) {
				continue;
			}
			$lines[] = 'Sitemap: ' . $url;
		}

		return $lines;
	}

	private function replacesGeneratedFile(): bool {
		if ( get_option( self::REPLACE_OPTION, 'no' ) !== 'yes' ) {
			return false;
		}

		return '' !== trim( (string) get_option( self::OPTION_KEY, '' ) );
	}

	/**
	 * @param mixed $value Request flag.
	 */
	private function isAffirmative( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'yes' === $value || 'true' === $value;
	}

	/**
	 * A crawler token: letters, numbers, and . _ - , at least four characters.
	 *
	 * @param mixed $bot Raw bot name.
	 */
	private function botToken( $bot ): string {
		if ( ! is_string( $bot ) ) {
			return '';
		}

		$bot = trim( $bot );
		if ( 1 !== preg_match( '/^[A-Za-z0-9._-]{4,100}$/', $bot ) ) {
			return '';
		}

		return $bot;
	}

	/**
	 * BCP 47 language tag when the site locale is English.
	 */
	private function englishLanguageTag(): string {
		$locale = function_exists( 'get_locale' ) ? get_locale() : '';
		if ( ! is_string( $locale ) || 1 !== preg_match( '/^en(?:[_-][A-Za-z0-9]{2,8})*$/', $locale ) ) {
			return '';
		}

		$parts = preg_split( '/[_-]/', str_replace( '_', '-', $locale ) );
		if ( ! is_array( $parts ) || ! isset( $parts[0] ) ) {
			return '';
		}

		$tag = strtolower( $parts[0] );
		if ( isset( $parts[1] ) && '' !== $parts[1] ) {
			$tag .= '-' . strtoupper( $parts[1] );
		}

		return $tag;
	}
}
