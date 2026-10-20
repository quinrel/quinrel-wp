<?php

namespace Quinrel\WordPress\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates Quinrel database tables.
 */
class Installer {

	/**
	 * Run the schema installation.
	 */
	public function install(): void {
		global $wpdb;

		$charsetCollate = $wpdb->get_charset_collate();

		$tableRedirects       = $wpdb->prefix . 'quinrel_redirects';
		$table404Logs         = $wpdb->prefix . 'quinrel_404_logs';
		$tableSitemapSettings = $wpdb->prefix . 'quinrel_sitemap_settings';
		$tableLinks           = $wpdb->prefix . 'quinrel_links';

		// 1. Redirects table schema
		$sqlRedirects = "CREATE TABLE $tableRedirects (
            id varchar(50) NOT NULL,
            source text NOT NULL,
            target text NOT NULL,
            code int NOT NULL DEFAULT 301,
            is_regex tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY source (source(191)),
            KEY is_regex (is_regex)
        ) $charsetCollate;";

		// 2. 404 logs table schema
		$sql404Logs = "CREATE TABLE $table404Logs (
            id varchar(50) NOT NULL,
            uri varchar(255) NOT NULL,
            hits int NOT NULL DEFAULT 1,
            referer text DEFAULT NULL,
            user_agent text DEFAULT NULL,
            last_hit datetime NOT NULL,
            resolved tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY uri (uri(191))
        ) $charsetCollate;";

		// 3. Sitemap settings table schema
		$sqlSitemapSettings = "CREATE TABLE $tableSitemapSettings (
            setting_key varchar(100) NOT NULL,
            setting_value text NOT NULL,
            PRIMARY KEY  (setting_key)
        ) $charsetCollate;";

		// 4. Internal Link Index table
		$sqlLinks = "CREATE TABLE $tableLinks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            target_url varchar(255) NOT NULL,
            anchor_text varchar(255) DEFAULT '',
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY target_url (target_url(191))
        ) $charsetCollate;";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sqlRedirects );
		dbDelta( $sql404Logs );
		dbDelta( $sqlSitemapSettings );
		dbDelta( $sqlLinks );

		\Quinrel\WordPress\Modules\TechnicalSeo\ErrorMonitor::markTableExists();
		\Quinrel\WordPress\Modules\Ai\UsageMeteringManager::createTable();
		\Quinrel\WordPress\Modules\Admin\SeoAuditHistoryLog::createTable();
		\Quinrel\WordPress\Modules\Analytics\KeywordRankTracker::createTable();
		\Quinrel\WordPress\Modules\TechnicalSeo\BrokenLinkChecker::createTable();
		\Quinrel\WordPress\Modules\Analytics\PageSpeedScheduler::createTable();
		\Quinrel\WordPress\Modules\Indexing\GscIndexSyncWorker::createTable();

		update_option( 'quinrel_db_version', QUINREL_VERSION, true );
	}
}
