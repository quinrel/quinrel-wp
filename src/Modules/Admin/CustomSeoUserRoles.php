<?php

namespace Quinrel\WordPress\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CustomSeoUserRoles
 *
 * BL-034: Registers granular SEO capabilities and assigns them to user roles.
 * - manage_seo         → full access to all Quinrel settings
 * - view_seo_reports   → read-only access to analytics/audit reports
 * - manage_redirects   → create/edit/delete redirect rules
 * - edit_seo_meta      → edit SEO meta fields per-post
 */
class CustomSeoUserRoles {

	private const CAPABILITIES = array(
		'manage_seo',
		'view_seo_reports',
		'manage_redirects',
		'edit_seo_meta',
	);

	public static function activate(): void {
		self::addCapabilities();
	}

	public static function deactivate(): void {
		self::removeCapabilities();
	}

	public static function addCapabilities(): void {
		// Administrator: all capabilities
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::CAPABILITIES as $cap ) {
				$admin->add_cap( $cap, true );
			}
		}

		// Editor: everything except manage_seo (settings)
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( 'view_seo_reports', true );
			$editor->add_cap( 'manage_redirects', true );
			$editor->add_cap( 'edit_seo_meta', true );
		}

		// Author: can only edit SEO meta on their own posts
		$author = get_role( 'author' );
		if ( $author ) {
			$author->add_cap( 'edit_seo_meta', true );
		}
	}

	public static function removeCapabilities(): void {
		$roles = array( 'administrator', 'editor', 'author', 'contributor' );
		foreach ( $roles as $roleName ) {
			$role = get_role( $roleName );
			if ( $role ) {
				foreach ( self::CAPABILITIES as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );

		// One-time recovery: re-apply capabilities only when the plugin version changes.
		// This avoids the previous pattern of writing to wp_options on every request.
		$applied = get_option( 'quinrel_caps_version', '' );
		if ( $applied !== QUINREL_VERSION ) {
			self::addCapabilities();
			update_option( 'quinrel_caps_version', QUINREL_VERSION, true );
		}
	}

	public function registerRoutes(): void {
		$adminCap = fn() => current_user_can( 'manage_seo' ) || current_user_can( 'manage_options' );

		register_rest_route(
			'quinrel/v1',
			'/roles/capabilities',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getCapabilities' ),
					'permission_callback' => $adminCap,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveCapabilities' ),
					'permission_callback' => $adminCap,
				),
			)
		);
	}

	public function getCapabilities( \WP_REST_Request $request ): \WP_REST_Response {
		// get_editable_roles() lives in wp-admin and is not loaded on a REST request.
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
		if ( ! is_array( $roles ) ) {
			$roles = array();
		}

		$result = array();
		foreach ( $roles as $slug => $roleData ) {
			if ( ! is_array( $roleData ) ) {
				continue;
			}
			$caps            = isset( $roleData['capabilities'] ) && is_array( $roleData['capabilities'] ) ? $roleData['capabilities'] : array();
			$result[ $slug ] = array(
				'label'        => isset( $roleData['name'] ) ? (string) $roleData['name'] : (string) $slug,
				'capabilities' => array_filter( $caps, static fn( $key ) => in_array( $key, self::CAPABILITIES, true ), ARRAY_FILTER_USE_KEY ),
			);
		}

		return rest_ensure_response(
			array(
				'seo_capabilities' => self::CAPABILITIES,
				'roles'            => $result,
			)
		);
	}

	public function saveCapabilities( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		foreach ( $params as $roleSlug => $caps ) {
			$roleSlug = sanitize_key( $roleSlug );
			if ( $roleSlug === 'administrator' ) {
				continue; // Never downgrade administrators
			}
			$role = get_role( $roleSlug );
			if ( ! $role || ! is_array( $caps ) ) {
				continue;
			}
			foreach ( $caps as $cap => $granted ) {
				if ( ! in_array( $cap, self::CAPABILITIES, true ) ) {
					continue; // Only allow SEO-specific capabilities
				}
				if ( $granted ) {
					$role->add_cap( $cap, true );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
