<?php
/**
 * Quinrel uninstall routine.
 *
 * Plugin data is retained by default. An administrator must explicitly enable
 * `quinrel_delete_data_on_uninstall` before deletion to be permitted.
 *
 * @package Quinrel
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Remove the legacy public backup directory. It lives under wp-content, so one
 * successful delete covers every site. Paths outside that directory are ignored.
 */
$quinrelRemoveBackupDirectory = static function (): void {
	if ( ! defined( 'WP_CONTENT_DIR' ) || '' === WP_CONTENT_DIR ) {
		return;
	}

	$content = rtrim( (string) WP_CONTENT_DIR, '/\\' );
	$root    = $content . '/quinrel-backups';
	if ( ! is_dir( $root ) ) {
		return;
	}

	$realContent = realpath( $content );
	$realRoot    = realpath( $root );
	if ( false === $realContent || false === $realRoot ) {
		return;
	}

	$prefix = rtrim( $realContent, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	if ( ! str_starts_with( $realRoot, $prefix ) || 'quinrel-backups' !== basename( $realRoot ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $realRoot, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			continue;
		}
		wp_delete_file( $item->getPathname() );
	}

	rmdir( $realRoot ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
};

/**
 * Clear runtime work for one site and, when explicitly requested, remove only
 * data owned by this plugin. Table names are calculated after switch_to_blog().
 * Capabilities are removed even when the site owner keeps the data.
 */
$quinrelUninstallSite = static function ( bool $deleteData ) use ( $quinrelRemoveBackupDirectory ): void {
	global $wpdb;

	$cronHooks = array(
		'quinrel_process_job',
		'quinrel_scan_orphaned',
		'quinrel_stale_cornerstone_check',
		'quinrel_weekly_audit',
		'quinrel_monthly_audit',
		'quinrel_scheduled_audit',
		'quinrel_rank_check',
		'quinrel_404_cleanup',
		'quinrel_apply_retention',
		'quinrel_run_technical_audit',
		'quinrel_blc_scan_batch',
		'quinrel_usage_metering_weekly',
		'quinrel_flush_404_buffer',
		'quinrel_cornerstone_staleness_check',
		'quinrel_weekly_usage_email',
		'quinrel_prune_audit_log',
		'quinrel_indexnow_flush',
	);

	foreach ( $cronHooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook );
			as_unschedule_all_actions( $hook, array(), 'quinrel' );
		}
	}

	foreach ( array( 'administrator', 'editor', 'author', 'contributor' ) as $roleName ) {
		$role = get_role( $roleName );
		if ( $role ) {
			foreach ( array( 'manage_seo', 'view_seo_reports', 'manage_redirects', 'edit_seo_meta' ) as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}

	if ( ! $deleteData ) {
		return;
	}

	$uploads     = wp_upload_dir();
	$uploadsBase = trailingslashit( (string) ( $uploads['basedir'] ?? '' ) );
	$backups     = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			'_quinrel_compression_original'
		)
	);
	foreach ( $backups as $backup ) {
		$backup = (string) $backup;
		if ( $uploadsBase !== '' && str_starts_with( $backup, $uploadsBase ) && is_file( $backup ) ) {
			wp_delete_file( $backup );
		}
	}

	$likeOptions          = $wpdb->esc_like( 'quinrel_' ) . '%';
	$likeMeta             = $wpdb->esc_like( '_quinrel_' ) . '%';
	$likeUserMeta         = $wpdb->esc_like( 'quinrel_' ) . '%';
	$likeTransient        = $wpdb->esc_like( '_transient_quinrel_' ) . '%';
	$likeTransientTimeout = $wpdb->esc_like( '_transient_timeout_quinrel_' ) . '%';

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $likeOptions ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $likeTransient ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $likeTransientTimeout ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $likeMeta ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $likeUserMeta ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$tables = array(
		$wpdb->prefix . 'quinrel_redirects',
		$wpdb->prefix . 'quinrel_404_logs',
		$wpdb->prefix . 'quinrel_sitemap_settings',
		$wpdb->prefix . 'quinrel_links',
		$wpdb->prefix . 'quinrel_ai_usage',
		$wpdb->prefix . 'quinrel_usage_metering',
		$wpdb->prefix . 'quinrel_audit_log',
		$wpdb->prefix . 'quinrel_rank_history',
		$wpdb->prefix . 'quinrel_broken_links',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	$quinrelRemoveBackupDirectory();
};

$quinrel_delete_data = get_option( 'quinrel_delete_data_on_uninstall', 'no' ) === 'yes';
$quinrelUninstallSite( $quinrel_delete_data );

if ( is_multisite() ) {
	$quinrel_site_ids        = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	$quinrel_current_blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1;
	foreach ( $quinrel_site_ids as $quinrel_site_id ) {
		if ( (int) $quinrel_site_id === (int) $quinrel_current_blog_id ) {
			continue;
		}
		switch_to_blog( (int) $quinrel_site_id );
		$quinrelUninstallSite( $quinrel_delete_data );
		restore_current_blog();
	}

	if ( $quinrel_delete_data ) {
		$quinrel_like_network_options = $wpdb->esc_like( 'quinrel_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $quinrel_like_network_options ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
