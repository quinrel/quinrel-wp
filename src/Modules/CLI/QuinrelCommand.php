<?php

namespace Quinrel\WordPress\Modules\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Quinrel SEO via the WP-CLI command line.
 *
 * ## EXAMPLES
 *
 *     # Run technical SEO audit
 *     $ wp quinrel audit
 *
 *     # Preview competitor migration
 *     $ wp quinrel migrate yoast
 *
 *     # Confirm competitor migration
 *     $ wp quinrel migrate yoast --confirm
 *
 *     # Check IndexNow configuration
 *     $ wp quinrel indexnow
 */
class QuinrelCommand {

	/**
	 * Run the 14-point Technical SEO Audit.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output format (table, json, yaml).
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @subcommand audit
	 */
	public function audit( array $args, array $assocArgs ): void {
		$engine  = new \Quinrel\WordPress\Modules\Admin\TechnicalSeoAuditEngine();
		$results = $engine->runAllChecks();

		$rows = array();
		foreach ( $results as $key => $check ) {
			$rows[] = array(
				'check'    => $key,
				'name'     => $check['name'] ?? $key,
				'status'   => strtoupper( (string) ( $check['status'] ?? 'unknown' ) ),
				'score'    => (int) ( $check['score'] ?? 0 ),
				'detail'   => $check['detail'] ?? '',
				'fix_hint' => $check['fix_hint'] ?? '',
			);
		}

		$format = $assocArgs['format'] ?? 'table';
		\WP_CLI\Utils\format_items( $format, $rows, array( 'check', 'status', 'score', 'detail' ) );
		\WP_CLI::success( 'Technical SEO Audit complete.' );
	}

	/**
	 * Import SEO meta from a competitor plugin (yoast, rankmath, aioseo).
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : Competitor plugin ID (yoast, rankmath, aioseo).
	 *
	 * [--confirm]
	 * : Actually write changes. Without this flag, a preview is displayed.
	 *
	 * @subcommand migrate
	 */
	public function migrate( array $args, array $assocArgs ): void {
		$plugin  = strtolower( (string) ( $args[0] ?? '' ) );
		$confirm = ! empty( $assocArgs['confirm'] );
		$manager = new \Quinrel\WordPress\Modules\Migration\MigrationManager();

		if ( ! $confirm ) {
			$preview = $manager->preview( $plugin );
			if ( empty( $preview['success'] ) ) {
				\WP_CLI::error( $preview['message'] ?? 'Migration preview failed.' );
				return;
			}
			\WP_CLI::line( $preview['message'] );
			if ( ! empty( $preview['samples'] ) ) {
				\WP_CLI::line( 'Sample previews:' );
				foreach ( $preview['samples'] as $sample ) {
					\WP_CLI::line( sprintf( ' - [%d] %s -> %s', $sample['post_id'], $sample['source_title'], $sample['resolved_title'] ) );
				}
			}
			\WP_CLI::line( 'Pass --confirm to execute the migration.' );
			return;
		}

		$result = $manager->migrate( $plugin );
		if ( empty( $result['success'] ) ) {
			\WP_CLI::error( $result['message'] ?? 'Migration failed.' );
			return;
		}

		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Check IndexNow API key and status.
	 *
	 * @subcommand indexnow
	 */
	public function indexnow( array $args, array $assocArgs ): void {
		$key       = (string) get_option( 'quinrel_indexnow_key', '' );
		$autoIndex = (string) get_option( 'quinrel_auto_index', 'no' );
		$queue     = (array) get_option( \Quinrel\WordPress\Modules\Indexing\BingIndexNowApi::QUEUE_OPTION, array() );

		\WP_CLI::line( sprintf( 'IndexNow API Key: %s', '' !== $key ? $key : '(not set)' ) );
		\WP_CLI::line( sprintf( 'Auto-Index On Publish: %s', $autoIndex ) );
		\WP_CLI::line( sprintf( 'Queued URLs: %d', count( $queue ) ) );
		\WP_CLI::success( 'IndexNow status retrieved.' );
	}
}
