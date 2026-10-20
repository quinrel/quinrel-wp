<?php

namespace Quinrel\WordPress\Tests\Unit;

use Quinrel\WordPress\Core\Event\EventManager;
use Quinrel\WordPress\Modules\Admin\AdminMenu;
use Quinrel\WordPress\Modules\Admin\AdminModule;
use Quinrel\WordPress\Modules\Api\HeadlessSeoEndpoints;
use Quinrel\WordPress\Modules\Social\SocialModule;
use Quinrel\WordPress\Modules\TechnicalSeo\HtaccessEditor;
use Quinrel\WordPress\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Stub coverage for the release gate. The WordPress test library is not installed.
 */
class ReleaseGateTest extends TestCase {

	private array $saved = array();

	private bool $createdUpgrade = false;

	protected function setUp(): void {
		parent::setUp();
		$this->saved = array(
			'options'    => $GLOBALS['mock_wp_options'] ?? array(),
			'autoload'   => $GLOBALS['mock_option_autoload'] ?? array(),
			'cron'       => $GLOBALS['mock_recurring_events'] ?? array(),
			'events'     => $GLOBALS['mock_cron_events'] ?? array(),
			'cleared'    => $GLOBALS['mock_cleared_hooks'] ?? array(),
			'delta'      => $GLOBALS['mock_db_delta'] ?? array(),
			'flush'      => $GLOBALS['mock_flush_count'] ?? 0,
			'rewrite'    => $GLOBALS['mock_rewrite_rules'] ?? array(),
			'roles'      => $GLOBALS['mock_roles'] ?? array(),
			'roleEvents' => $GLOBALS['mock_role_events'] ?? array(),
			'caps'       => array_key_exists( 'mock_user_caps', $GLOBALS ) ? $GLOBALS['mock_user_caps'] : null,
			'queries'    => $GLOBALS['wpdb']->queries,
			'prefix'     => $GLOBALS['wpdb']->prefix,
			'rewriteObj' => $GLOBALS['wp_rewrite'] ?? null,
			'posts'      => $GLOBALS['mock_posts'] ?? array(),
			'meta'       => $GLOBALS['mock_post_meta'] ?? array(),
			'routes'     => $GLOBALS['mock_rest_routes'] ?? array(),
			'http'       => $GLOBALS['mock_http_log'] ?? array(),
			'admin'      => $GLOBALS['mock_is_admin'] ?? false,
			'loggedIn'   => $GLOBALS['mock_is_user_logged_in'] ?? null,
			'multisite'  => $GLOBALS['mock_is_multisite'] ?? null,
			'var'        => $GLOBALS['mock_wpdb_var'] ?? null,
			'results'    => $GLOBALS['mock_wpdb_results'] ?? null,
		);

		$GLOBALS['mock_wp_options']         = array();
		$GLOBALS['mock_option_autoload']    = array();
		$GLOBALS['mock_recurring_events']   = array();
		$GLOBALS['mock_cron_events']        = array();
		$GLOBALS['mock_cleared_hooks']      = array();
		$GLOBALS['mock_db_delta']           = array();
		$GLOBALS['mock_flush_count']        = 0;
		$GLOBALS['mock_rewrite_rules']      = array();
		$GLOBALS['mock_roles']              = array();
		$GLOBALS['mock_role_events']        = array();
		$GLOBALS['mock_posts']              = array();
		$GLOBALS['mock_post_meta']          = array();
		$GLOBALS['mock_rest_routes']        = array();
		$GLOBALS['mock_http_log']           = array();
		$GLOBALS['mock_is_admin']           = false;
		$GLOBALS['mock_is_multisite']       = false;
		$GLOBALS['mock_is_user_logged_in']  = true;
		$GLOBALS['wpdb']->queries           = array();
		$GLOBALS['wpdb']->prefix            = 'wp_';
		$GLOBALS['wp_rewrite']              = new \stdClass();
		$GLOBALS['wp_rewrite']->extra_rules_top = array();
		unset( $GLOBALS['mock_user_caps'], $GLOBALS['mock_wpdb_var'], $GLOBALS['mock_wpdb_results'] );
	}

	protected function tearDown(): void {
		$GLOBALS['mock_wp_options']       = $this->saved['options'];
		$GLOBALS['mock_option_autoload']  = $this->saved['autoload'];
		$GLOBALS['mock_recurring_events'] = $this->saved['cron'];
		$GLOBALS['mock_cron_events']      = $this->saved['events'];
		$GLOBALS['mock_cleared_hooks']    = $this->saved['cleared'];
		$GLOBALS['mock_db_delta']         = $this->saved['delta'];
		$GLOBALS['mock_flush_count']      = $this->saved['flush'];
		$GLOBALS['mock_rewrite_rules']    = $this->saved['rewrite'];
		$GLOBALS['mock_roles']            = $this->saved['roles'];
		$GLOBALS['mock_role_events']      = $this->saved['roleEvents'];
		$GLOBALS['mock_posts']            = $this->saved['posts'];
		$GLOBALS['mock_post_meta']        = $this->saved['meta'];
		$GLOBALS['mock_rest_routes']      = $this->saved['routes'];
		$GLOBALS['mock_http_log']         = $this->saved['http'];
		$GLOBALS['mock_is_admin']         = $this->saved['admin'];
		$GLOBALS['wpdb']->queries         = $this->saved['queries'];
		$GLOBALS['wpdb']->prefix          = $this->saved['prefix'];
		if ( null === $this->saved['rewriteObj'] ) {
			unset( $GLOBALS['wp_rewrite'] );
		} else {
			$GLOBALS['wp_rewrite'] = $this->saved['rewriteObj'];
		}
		if ( null === $this->saved['caps'] ) {
			unset( $GLOBALS['mock_user_caps'] );
		} else {
			$GLOBALS['mock_user_caps'] = $this->saved['caps'];
		}
		if ( null === $this->saved['loggedIn'] ) {
			unset( $GLOBALS['mock_is_user_logged_in'] );
		} else {
			$GLOBALS['mock_is_user_logged_in'] = $this->saved['loggedIn'];
		}
		if ( null === $this->saved['multisite'] ) {
			unset( $GLOBALS['mock_is_multisite'] );
		} else {
			$GLOBALS['mock_is_multisite'] = $this->saved['multisite'];
		}
		if ( null === $this->saved['var'] ) {
			unset( $GLOBALS['mock_wpdb_var'] );
		} else {
			$GLOBALS['mock_wpdb_var'] = $this->saved['var'];
		}
		if ( null === $this->saved['results'] ) {
			unset( $GLOBALS['mock_wpdb_results'] );
		} else {
			$GLOBALS['mock_wpdb_results'] = $this->saved['results'];
		}
		$this->removeUpgradeStub();
		parent::tearDown();
	}

	public function testActivationCreatesTablesCapabilitiesAndEnabledRewriteRules(): void {
		$this->stubWordPressUpgrade();

		Plugin::activate();

		$created = implode( "\n", array_map( 'strval', $GLOBALS['mock_db_delta'] ) );
		foreach (
			array(
				'wp_quinrel_redirects',
				'wp_quinrel_404_logs',
				'wp_quinrel_sitemap_settings',
				'wp_quinrel_links',
				'wp_quinrel_ai_usage',
				'wp_quinrel_audit_log',
				'wp_quinrel_rank_history',
				'wp_quinrel_broken_links',
			) as $table
		) {
			$this->assertStringContainsString( $table, $created );
		}
		$this->assertFalse( $this->queriesContain( 'DROP TABLE' ) );
		$this->assertSame( QUINREL_VERSION, get_option( 'quinrel_db_version' ) );
		$this->assertNotSame( '', (string) get_option( 'quinrel_encryption_salt' ) );
		$this->assertTrue(
			$this->queriesContain( "SET autoload = 'no' WHERE option_name = 'quinrel_encryption_salt'" )
		);
		$this->assertTrue( $this->roleHas( 'administrator', 'manage_seo' ) );
		$this->assertTrue( $this->roleHas( 'administrator', 'view_seo_reports' ) );
		$this->assertTrue( $this->roleHas( 'administrator', 'manage_redirects' ) );
		$this->assertTrue( $this->roleHas( 'administrator', 'edit_seo_meta' ) );
		$this->assertTrue( $this->roleHas( 'editor', 'edit_seo_meta' ) );
		$this->assertFalse( $this->roleHas( 'editor', 'manage_seo' ) );
		$this->assertTrue( $this->roleHas( 'author', 'edit_seo_meta' ) );
		$this->assertFalse( $this->roleHas( 'author', 'manage_redirects' ) );
		$this->assertArrayNotHasKey( 'contributor', $GLOBALS['mock_roles'] );
		$this->assertSame( 1, $GLOBALS['mock_flush_count'] );
		$this->assertArrayNotHasKey( '^sitemap\\.xml$', $GLOBALS['wp_rewrite']->extra_rules_top );

		$scheduled = array();
		foreach ( $GLOBALS['mock_recurring_events'] as $event ) {
			$scheduled[] = $event['hook'];
		}
		foreach (
			array(
				'quinrel_flush_404_buffer',
				'quinrel_404_cleanup',
				'quinrel_rank_check',
				'quinrel_apply_retention',
				'quinrel_weekly_usage_email',
				'quinrel_cornerstone_staleness_check',
				'quinrel_prune_audit_log',
				'quinrel_scheduled_audit',
			) as $hook
		) {
			$this->assertContains( $hook, $scheduled );
		}

		update_option( 'quinrel_enable_index_sitemap', 'yes' );
		Plugin::activate();

		$this->assertSame( 2, $GLOBALS['mock_flush_count'] );
		$this->assertSame(
			'index.php?quinrel_sitemap=index',
			$GLOBALS['wp_rewrite']->extra_rules_top['^sitemap\\.xml$']
		);
		$this->assertArrayNotHasKey( '^sitemap-news\\.xml$', $GLOBALS['wp_rewrite']->extra_rules_top );
		$GLOBALS['wp_rewrite']->extra_rules_top['^keep-me$'] = 'index.php?keep=1';

		Plugin::deactivate();

		$this->assertArrayNotHasKey( '^sitemap\\.xml$', $GLOBALS['wp_rewrite']->extra_rules_top );
		$this->assertSame( 'index.php?keep=1', $GLOBALS['wp_rewrite']->extra_rules_top['^keep-me$'] );
		$this->assertSame( 3, $GLOBALS['mock_flush_count'] );
		$this->assertSame( QUINREL_VERSION, get_option( 'quinrel_db_version' ) );
		$this->assertFalse( $this->queriesContain( 'DROP TABLE' ) );
		$this->assertSame( array(), $GLOBALS['mock_http_log'] );

		$stillScheduled = array();
		foreach ( $GLOBALS['mock_recurring_events'] as $event ) {
			$stillScheduled[] = $event['hook'];
		}
		foreach (
			array(
				'quinrel_process_job',
				'quinrel_flush_404_buffer',
				'quinrel_scan_orphaned',
				'quinrel_stale_cornerstone_check',
				'quinrel_cornerstone_staleness_check',
				'quinrel_weekly_audit',
				'quinrel_monthly_audit',
				'quinrel_scheduled_audit',
				'quinrel_rank_check',
				'quinrel_prune_audit_log',
				'quinrel_404_cleanup',
				'quinrel_apply_retention',
				'quinrel_run_technical_audit',
				'quinrel_blc_scan_batch',
				'quinrel_weekly_usage_email',
			) as $hook
		) {
			$this->assertContains( $hook, $GLOBALS['mock_cleared_hooks'] );
			$this->assertNotContains( $hook, $stillScheduled );
		}
	}

	public function testContributorIsDeniedServerConfigSocialTokensSettingsExportAndAnotherDraft(): void {
		$GLOBALS['mock_user_caps'] = array(
			'manage_options' => false,
			'manage_seo'     => false,
			'edit_posts'     => true,
			'edit_post'      => array( 41 ),
		);

		$read = ( new HtaccessEditor() )->readHtaccessEndpoint( new \WP_REST_Request() );
		$this->assertInstanceOf( \WP_Error::class, $read );
		$this->assertSame( 'forbidden', $read->get_error_code() );
		$this->assertSame( 403, $read->get_error_data()['status'] );

		update_option(
			'quinrel_social_accounts',
			array(
				array(
					'network'      => 'twitter',
					'access_token' => 'contributor-must-not-see',
				),
			)
		);
		$social = new SocialModule( new EventManager() );
		$this->assertFalse( $social->checkPermission() );
		$accounts = $social->getAccounts( new \WP_REST_Request() );
		$this->assertInstanceOf( \WP_Error::class, $accounts );
		$this->assertSame( 403, $accounts->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'contributor-must-not-see', $accounts->get_error_message() );

		$admin = new AdminModule( new EventManager(), new AdminMenu() );
		$admin->registerRestRoutes();
		$export = $GLOBALS['mock_rest_routes']['quinrel/v1/settings/export']['permission_callback'];
		$import = $GLOBALS['mock_rest_routes']['quinrel/v1/settings/import']['permission_callback'];
		$this->assertSame( array( $admin, 'checkSettingsTransferPermission' ), $export );
		$this->assertFalse( $export() );
		$this->assertFalse( $import() );
		$this->assertTrue( is_user_logged_in() );

		$GLOBALS['mock_posts'][88] = new \WP_Post(
			array(
				'ID'          => 88,
				'post_status' => 'draft',
				'post_title'  => 'Other draft',
				'post_author' => 2,
				'post_type'   => 'post',
			)
		);
		$GLOBALS['mock_posts'][41] = new \WP_Post(
			array(
				'ID'          => 41,
				'post_status' => 'draft',
				'post_title'  => 'Own draft',
				'post_author' => 1,
				'post_type'   => 'post',
			)
		);
		$GLOBALS['mock_post_meta'][88] = array(
			'_quinrel_meta_title'    => 'Hidden title',
			'_quinrel_focus_keyword' => 'secret keyword',
		);

		$endpoints = new HeadlessSeoEndpoints();
		$endpoints->registerRoutes();
		$route = $GLOBALS['mock_rest_routes']['quinrel/v1/seo/(?P<post_id>\\d+)'];
		$deny  = new \WP_REST_Request();
		$deny->set_param( 'post_id', 88 );
		$deny->set_json_params(
			array(
				'meta_title'    => 'Taken',
				'focus_keyword' => 'stolen',
			)
		);
		$this->assertFalse( $route[1]['permission_callback']( $deny ) );

		$updated = $endpoints->updatePostSeo( $deny );
		$this->assertInstanceOf( \WP_Error::class, $updated );
		$this->assertSame( 'forbidden', $updated->get_error_code() );
		$this->assertSame( 403, $updated->get_error_data()['status'] );
		$this->assertSame( 'Hidden title', get_post_meta( 88, '_quinrel_meta_title', true ) );
		$this->assertStringNotContainsString( 'secret keyword', (string) wp_json_encode( $updated->get_error_data() ) );

		$hidden = $endpoints->getPostSeo( $deny );
		$this->assertInstanceOf( \WP_Error::class, $hidden );
		$this->assertSame( 404, $hidden->get_error_data()['status'] );

		$own = new \WP_REST_Request();
		$own->set_param( 'post_id', 41 );
		$own->set_json_params( array( 'meta_title' => 'Mine' ) );
		$this->assertTrue( $route[1]['permission_callback']( $own ) );
		$saved = $endpoints->updatePostSeo( $own );
		$this->assertInstanceOf( \WP_REST_Response::class, $saved );
		$this->assertSame( 'Mine', get_post_meta( 41, '_quinrel_meta_title', true ) );
	}

	private function roleHas( string $role, string $cap ): bool {
		$stored = $GLOBALS['mock_roles'][ $role ] ?? null;
		return $stored instanceof \MockWpRole && ! empty( $stored->caps[ $cap ] );
	}

	private function queriesContain( string $needle ): bool {
		foreach ( $GLOBALS['wpdb']->queries as $query ) {
			if ( is_string( $query ) && str_contains( $query, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private function stubWordPressUpgrade(): void {
		$dir = dirname( __DIR__, 2 ) . '/wp-admin/includes';
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
			$this->fail( 'Could not create the upgrade.php stub directory.' );
		}
		$file = $dir . '/upgrade.php';
		if ( ! is_file( $file ) ) {
			file_put_contents( $file, "<?php\n" );
			$this->createdUpgrade = true;
		}
	}

	private function removeUpgradeStub(): void {
		if ( ! $this->createdUpgrade ) {
			return;
		}
		$root = dirname( __DIR__, 2 );
		$file = $root . '/wp-admin/includes/upgrade.php';
		if ( is_file( $file ) ) {
			unlink( $file );
		}
		foreach ( array( $root . '/wp-admin/includes', $root . '/wp-admin' ) as $dir ) {
			if ( is_dir( $dir ) ) {
				$left = scandir( $dir );
				if ( is_array( $left ) && array( '.', '..' ) === $left ) {
					rmdir( $dir );
				}
			}
		}
		$this->createdUpgrade = false;
	}
}
