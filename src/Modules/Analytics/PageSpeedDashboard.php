<?php

namespace Quinrel\WordPress\Modules\Analytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Quinrel\WordPress\Core\Mode\OperatingMode;
use Quinrel\WordPress\Core\Security\KeyVault;

/**
 * Fetches a Lighthouse audit from the Google PageSpeed Insights API.
 *
 * A check runs only when an editor asks for one. The last report is stored
 * without autoload and shown again until the next check.
 */
class PageSpeedDashboard {

	private const API_BASE      = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
	private const KEY_OPTION    = 'quinrel_pagespeed_api_key';
	private const REPORT_OPTION = 'quinrel_pagespeed_reports';
	private const MASK          = '••••••••••••';
	private const SERIES_LIMIT  = 12;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function registerRoutes(): void {
		$reportCap = fn() => current_user_can( 'view_seo_reports' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/analytics/pagespeed',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPageSpeed' ),
				'permission_callback' => $reportCap,
				'args'                => array(
					'url'      => array(
						'required'          => false,
						'sanitize_callback' => 'esc_url_raw',
					),
					'strategy' => array(
						'default'           => 'mobile',
						'sanitize_callback' => 'sanitize_key',
					),
					'force'    => array( 'default' => false ),
				),
			)
		);

		register_rest_route(
			'quinrel/v1',
			'/analytics/pagespeed/bulk',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulkCheck' ),
				'permission_callback' => $reportCap,
			)
		);
	}

	/**
	 * Decrypt the saved PageSpeed Insights key. A wp-config constant wins.
	 * A legacy plaintext value still works.
	 */
	public static function storedKey(): string {
		if ( defined( 'QUINREL_PSI_KEY' ) && is_string( QUINREL_PSI_KEY ) && QUINREL_PSI_KEY !== '' ) {
			return QUINREL_PSI_KEY;
		}

		foreach ( array( self::KEY_OPTION, 'quinrel_psi_key' ) as $option ) {
			$stored = (string) get_option( $option, '' );
			if ( $stored === '' ) {
				continue;
			}
			$plain = KeyVault::decrypt( $stored );
			if ( $plain !== '' ) {
				return $plain;
			}
		}

		return '';
	}

	/**
	 * Store the key as ciphertext. A failed encrypt does not write the plaintext.
	 *
	 * @return true|\WP_Error
	 */
	public static function storeKey( string $plaintext ): true|\WP_Error {
		if ( $plaintext === self::MASK ) {
			return true;
		}
		if ( $plaintext === '' ) {
			self::clearKey();
			return true;
		}

		$encrypted = KeyVault::encrypt( $plaintext );
		if ( is_wp_error( $encrypted ) || ! is_string( $encrypted ) || ! KeyVault::isCiphertext( $encrypted ) ) {
			return is_wp_error( $encrypted )
				? $encrypted
				: new \WP_Error(
					'quinrel_encryption_failed',
					__( 'Quinrel could not encrypt this secret. The value was not saved.', 'quinrel' ),
					array( 'status' => 500 )
				);
		}

		update_option( self::KEY_OPTION, $encrypted, false );
		update_option( 'quinrel_psi_key', '', false );
		return true;
	}

	public static function hasStoredKey(): bool {
		return (string) get_option( self::KEY_OPTION, '' ) !== '' || (string) get_option( 'quinrel_psi_key', '' ) !== '';
	}

	public static function clearKey(): void {
		update_option( self::KEY_OPTION, '', false );
		update_option( 'quinrel_psi_key', '', false );
	}

	public function getPageSpeed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$strategy = $this->strategy( (string) $request->get_param( 'strategy' ) );
		$url      = esc_url_raw( (string) $request->get_param( 'url' ) );
		$force    = filter_var( $request->get_param( 'force' ), FILTER_VALIDATE_BOOLEAN );
		$stored   = $this->storedReport( $strategy );

		if ( ! $force ) {
			if ( is_array( $stored ) && ( $url === '' || $stored['url'] === $url ) ) {
				return rest_ensure_response( $stored );
			}

			return rest_ensure_response(
				array(
					'status'   => 'empty',
					'strategy' => $strategy,
					'url'      => $url !== '' ? $url : home_url( '/' ),
					'has_key'  => self::storedKey() !== '',
				)
			);
		}

		if ( $url === '' ) {
			$url = home_url( '/' );
		}

		$audit = $this->runAudit( $url, $strategy );
		if ( is_wp_error( $audit ) ) {
			return $audit;
		}

		$audit['series'] = $this->appendSeries( $stored, $audit['url'], $audit['metrics'] );
		$this->saveReport( $strategy, $audit );
		return rest_ensure_response( $audit );
	}

	public function bulkCheck( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$rawUrls = $params['urls'] ?? array();
		if ( ! is_array( $rawUrls ) ) {
			$rawUrls = array();
		}

		$urls = array();
		foreach ( array_slice( $rawUrls, 0, 10 ) as $rawUrl ) {
			if ( ! is_string( $rawUrl ) || $rawUrl === '' ) {
				continue;
			}
			$urls[] = esc_url_raw( $rawUrl );
		}

		if ( $urls === array() ) {
			return new \WP_Error( 'missing_urls', __( 'Add at least one URL to check.', 'quinrel' ), array( 'status' => 400 ) );
		}

		$strategy = $this->strategy( isset( $params['strategy'] ) && is_string( $params['strategy'] ) ? $params['strategy'] : 'mobile' );
		$results  = array();
		foreach ( $urls as $url ) {
			$audit = $this->runAudit( $url, $strategy );
			if ( is_wp_error( $audit ) ) {
				$results[ $url ] = array( 'error' => $audit->get_error_message() );
				continue;
			}
			$results[ $url ] = $audit;
		}

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Run one audit. Does not write the stored report.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function runAudit( string $url, string $strategy ): array|\WP_Error {
		$strategy = $this->strategy( $strategy );
		$valid    = $this->auditableUrl( $url );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$apiKey = self::storedKey();
		if ( $apiKey === '' && OperatingMode::isCloudConfigured() ) {
			$proxied = $this->fetchViaProxy( $valid, $strategy );
			if ( is_wp_error( $proxied ) ) {
				return $proxied;
			}
			if ( ! is_array( $proxied ) || ! isset( $proxied['lighthouseResult'] ) || ! is_array( $proxied['lighthouseResult'] ) ) {
				return new \WP_Error(
					'psi_error',
					__( 'Quinrel Cloud did not return a PageSpeed audit.', 'quinrel' ),
					array( 'status' => 502 )
				);
			}
			return $this->normalize( $valid, $strategy, $proxied );
		}

		return $this->fetchPageSpeed( $valid, $strategy, $apiKey );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function fetchPageSpeed( string $url, string $strategy, string $apiKey ): array|\WP_Error {
		$response = wp_safe_remote_get(
			$this->endpoint( $url, $strategy, $apiKey ),
			array(
				'timeout'    => 60,
				'user-agent' => 'Quinrel/' . ( defined( 'QUINREL_VERSION' ) ? QUINREL_VERSION : '1' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code !== 200 || ! is_array( $body ) ) {
			$message = '';
			if ( is_array( $body ) && isset( $body['error']['message'] ) && is_string( $body['error']['message'] ) ) {
				$message = wp_strip_all_tags( $body['error']['message'] );
			}
			if ( $message === '' ) {
				$message = __( 'Google PageSpeed did not return an audit.', 'quinrel' );
			}
			return new \WP_Error( 'psi_error', $message, array( 'status' => 502 ) );
		}

		return $this->normalize( $url, $strategy, $body );
	}

	private function endpoint( string $url, string $strategy, string $apiKey ): string {
		$query = array(
			'url'      => $url,
			'strategy' => $strategy,
		);
		if ( $apiKey !== '' ) {
			$query['key'] = $apiKey;
		}

		$endpoint = self::API_BASE . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		foreach ( array( 'performance', 'accessibility', 'best-practices', 'seo' ) as $category ) {
			$endpoint .= '&category=' . rawurlencode( $category );
		}
		return $endpoint;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	private function normalize( string $url, string $strategy, array $body ): array {
		$lighthouse = isset( $body['lighthouseResult'] ) && is_array( $body['lighthouseResult'] ) ? $body['lighthouseResult'] : array();
		$categories = isset( $lighthouse['categories'] ) && is_array( $lighthouse['categories'] ) ? $lighthouse['categories'] : array();
		$audits     = isset( $lighthouse['audits'] ) && is_array( $lighthouse['audits'] ) ? $lighthouse['audits'] : array();
		$field      = array();
		if ( isset( $body['loadingExperience']['metrics'] ) && is_array( $body['loadingExperience']['metrics'] ) ) {
			$field = $body['loadingExperience']['metrics'];
		}

		return array(
			'status'        => 'ready',
			'url'           => $url,
			'strategy'      => $strategy,
			'fetched_at'    => current_time( 'mysql' ),
			'has_key'       => self::storedKey() !== '',
			'scores'        => array(
				'performance'    => $this->categoryScore( $categories, 'performance' ),
				'accessibility'  => $this->categoryScore( $categories, 'accessibility' ),
				'best_practices' => $this->categoryScore( $categories, 'best-practices' ),
				'seo'            => $this->categoryScore( $categories, 'seo' ),
			),
			'metrics'       => array(
				$this->metric( 'lcp', $this->fieldMs( $field, 'LARGEST_CONTENTFUL_PAINT_MS' ), $this->labAudit( $audits, array( 'largest-contentful-paint' ) ), 'ms' ),
				$this->metric( 'inp', $this->fieldMs( $field, 'INTERACTION_TO_NEXT_PAINT' ), $this->labAudit( $audits, array( 'interaction-to-next-paint', 'experimental-interaction-to-next-paint' ) ), 'ms' ),
				$this->metric( 'cls', $this->fieldCls( $field ), $this->labAudit( $audits, array( 'cumulative-layout-shift' ) ), 'cls' ),
				$this->metric( 'ttfb', $this->fieldMs( $field, 'EXPERIMENTAL_TIME_TO_FIRST_BYTE' ), $this->labAudit( $audits, array( 'server-response-time' ) ), 'ms' ),
			),
			'opportunities' => $this->opportunities( $audits ),
			'series'        => array(),
		);
	}

	/**
	 * @param array<string, mixed> $categories
	 */
	private function categoryScore( array $categories, string $key ): ?int {
		if ( ! isset( $categories[ $key ]['score'] ) || ! is_numeric( $categories[ $key ]['score'] ) ) {
			return null;
		}
		return (int) round( (float) $categories[ $key ]['score'] * 100 );
	}

	/**
	 * @param array<string, mixed>      $field
	 * @return array{numeric: float, rating: string}|null
	 */
	private function fieldMs( array $field, string $name ): ?array {
		if ( ! isset( $field[ $name ]['percentile'] ) || ! is_numeric( $field[ $name ]['percentile'] ) ) {
			return null;
		}
		return array(
			'numeric' => (float) $field[ $name ]['percentile'],
			'rating'  => $this->fieldRating( $field[ $name ] ),
		);
	}

	/**
	 * CrUX reports CLS multiplied by 100.
	 *
	 * @param array<string, mixed> $field
	 * @return array{numeric: float, rating: string}|null
	 */
	private function fieldCls( array $field ): ?array {
		$point = $this->fieldMs( $field, 'CUMULATIVE_LAYOUT_SHIFT_SCORE' );
		if ( $point === null ) {
			return null;
		}
		$point['numeric'] = $point['numeric'] / 100;
		return $point;
	}

	/**
	 * @param array<string, mixed> $metric
	 */
	private function fieldRating( array $metric ): string {
		$category = isset( $metric['category'] ) ? strtoupper( (string) $metric['category'] ) : '';
		if ( $category === 'FAST' ) {
			return 'good';
		}
		if ( $category === 'AVERAGE' ) {
			return 'needs-improvement';
		}
		if ( $category === 'SLOW' ) {
			return 'poor';
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $audits
	 * @param array<int, string>   $ids
	 * @return array{numeric: float, display: string}|null
	 */
	private function labAudit( array $audits, array $ids ): ?array {
		foreach ( $ids as $id ) {
			if ( ! isset( $audits[ $id ] ) || ! is_array( $audits[ $id ] ) ) {
				continue;
			}
			if ( ! isset( $audits[ $id ]['numericValue'] ) || ! is_numeric( $audits[ $id ]['numericValue'] ) ) {
				continue;
			}
			$display = isset( $audits[ $id ]['displayValue'] ) ? wp_strip_all_tags( (string) $audits[ $id ]['displayValue'] ) : '';
			return array(
				'numeric' => (float) $audits[ $id ]['numericValue'],
				'display' => $display,
			);
		}
		return null;
	}

	/**
	 * @param array{numeric: float, rating: string}|null  $field
	 * @param array{numeric: float, display: string}|null $lab
	 * @return array<string, mixed>
	 */
	private function metric( string $id, ?array $field, ?array $lab, string $unit ): array {
		$origin  = null;
		$numeric = null;
		$display = '';
		$rating  = '';

		if ( $field !== null ) {
			$origin  = 'field';
			$numeric = $field['numeric'];
			$rating  = $field['rating'];
		} elseif ( $lab !== null ) {
			$origin  = 'lab';
			$numeric = $lab['numeric'];
			$display = $lab['display'];
		}

		if ( $numeric !== null && $rating === '' ) {
			$rating = $this->rate( $id, $numeric );
		}
		if ( $numeric !== null && $display === '' ) {
			$display = $unit === 'cls' ? $this->formatCls( $numeric ) : $this->formatMs( $numeric );
		}

		return array(
			'id'      => $id,
			'value'   => $display,
			'numeric' => $numeric,
			'rating'  => $rating,
			'origin'  => $origin,
		);
	}

	private function rate( string $id, float $measured ): string {
		if ( $id === 'cls' ) {
			$good = 0.1;
			$poor = 0.25;
		} elseif ( $id === 'inp' ) {
			$good = 200;
			$poor = 500;
		} elseif ( $id === 'ttfb' ) {
			$good = 800;
			$poor = 1800;
		} else {
			$good = 2500;
			$poor = 4000;
		}

		if ( $measured <= $good ) {
			return 'good';
		}
		if ( $measured <= $poor ) {
			return 'needs-improvement';
		}
		return 'poor';
	}

	private function formatMs( float $ms ): string {
		if ( $ms >= 1000 ) {
			$seconds = $ms / 1000;
			$text    = abs( $seconds - round( $seconds ) ) < 0.05
				? (string) (int) round( $seconds )
				: number_format( $seconds, 1, '.', '' );
			return $text . ' s';
		}
		return (string) (int) round( $ms ) . ' ms';
	}

	private function formatCls( float $cls ): string {
		return number_format( $cls, 2, '.', '' );
	}

	/**
	 * @param array<string, mixed> $audits
	 * @return array<int, array<string, mixed>>
	 */
	private function opportunities( array $audits ): array {
		$opps = array();
		foreach ( $audits as $id => $audit ) {
			if ( ! is_array( $audit ) || ! isset( $audit['details'] ) || ! is_array( $audit['details'] ) ) {
				continue;
			}
			if ( ( $audit['details']['type'] ?? '' ) !== 'opportunity' ) {
				continue;
			}
			if ( isset( $audit['score'] ) && is_numeric( $audit['score'] ) && (float) $audit['score'] >= 0.9 ) {
				continue;
			}
			$ms    = isset( $audit['details']['overallSavingsMs'] ) && is_numeric( $audit['details']['overallSavingsMs'] )
				? (int) round( (float) $audit['details']['overallSavingsMs'] )
				: 0;
			$bytes = isset( $audit['details']['overallSavingsBytes'] ) && is_numeric( $audit['details']['overallSavingsBytes'] )
				? (int) round( (float) $audit['details']['overallSavingsBytes'] )
				: 0;
			if ( $ms <= 0 && $bytes <= 0 ) {
				continue;
			}
			$opps[] = array(
				'id'            => sanitize_key( (string) $id ),
				'title'         => wp_strip_all_tags( (string) ( $audit['title'] ?? $id ) ),
				'savings_ms'    => $ms > 0 ? $ms : null,
				'savings_bytes' => $bytes > 0 ? $bytes : null,
			);
		}

		usort(
			$opps,
			static function ( array $left, array $right ): int {
				return ( (int) ( $right['savings_ms'] ?? 0 ) ) <=> ( (int) ( $left['savings_ms'] ?? 0 ) );
			}
		);
		return array_slice( $opps, 0, 6 );
	}

	/**
	 * @return string|\WP_Error
	 */
	private function auditableUrl( string $url ): string|\WP_Error {
		$url = esc_url_raw( $url );
		if ( $url === '' || wp_http_validate_url( $url ) === false ) {
			return new \WP_Error(
				'psi_invalid_url',
				__( 'Enter a public http or https URL.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $host === '' || $this->hostIsLocal( $host ) ) {
			return new \WP_Error(
				'psi_local_url',
				__( 'Google PageSpeed checks pages from its own servers. This address is only on your network, so Quinrel did not send it. Enter a public URL.', 'quinrel' ),
				array( 'status' => 400 )
			);
		}

		return $url;
	}

	private function hostIsLocal( string $host ): bool {
		if ( $host === 'localhost' || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.test' ) || str_ends_with( $host, '.invalid' ) ) {
			return true;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) === false ) {
			return false;
		}
		return filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false;
	}

	private function strategy( string $strategy ): string {
		return $strategy === 'desktop' ? 'desktop' : 'mobile';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function storedReport( string $strategy ): ?array {
		$all    = get_option( self::REPORT_OPTION, array() );
		$report = is_array( $all ) ? ( $all[ $strategy ] ?? null ) : null;
		if ( ! is_array( $report ) || ( $report['status'] ?? '' ) !== 'ready' || ! isset( $report['url'] ) ) {
			return null;
		}
		return $report;
	}

	/**
	 * @param array<string, mixed> $report
	 */
	private function saveReport( string $strategy, array $report ): void {
		$all = get_option( self::REPORT_OPTION, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$all[ $strategy ] = $report;
		update_option( self::REPORT_OPTION, $all, false );
	}

	/**
	 * @param array<string, mixed>|null        $previous
	 * @param array<int, array<string, mixed>> $metrics
	 * @return array<string, array<int, float>>
	 */
	private function appendSeries( ?array $previous, string $url, array $metrics ): array {
		$same   = is_array( $previous ) && ( $previous['url'] ?? '' ) === $url && isset( $previous['series'] ) && is_array( $previous['series'] );
		$series = $same ? $previous['series'] : array();
		$next   = array();

		foreach ( $metrics as $metric ) {
			$id     = (string) $metric['id'];
			$points = $same && isset( $series[ $id ] ) && is_array( $series[ $id ] ) ? $series[ $id ] : array();
			if ( isset( $metric['numeric'] ) && is_numeric( $metric['numeric'] ) ) {
				$points[] = (float) $metric['numeric'];
			}
			$next[ $id ] = array_slice( array_values( array_map( 'floatval', $points ) ), -1 * self::SERIES_LIMIT );
		}

		return $next;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private function fetchViaProxy( string $url, string $strategy ): array|\WP_Error {
		$result = \Quinrel\WordPress\Core\Api\QuinrelApiGateway::proxyRequest(
			'pagespeed',
			array(
				'url'      => $url,
				'strategy' => $strategy,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return is_array( $result ) ? $result : array();
	}
}
