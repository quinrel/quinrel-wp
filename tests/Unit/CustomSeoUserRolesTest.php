<?php

namespace Quinrel\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Quinrel\WordPress\Modules\Admin\CustomSeoUserRoles;

class CustomSeoUserRolesTest extends TestCase {

	public function testCapabilitiesRouteReturnsOnlySeoCapsOnARestRequest(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Modules/Admin/CustomSeoUserRoles.php' );
		$this->assertStringContainsString( "require_once ABSPATH . 'wp-admin/includes/user.php'", $source );

		$GLOBALS['mock_editable_roles'] = array(
			'administrator' => array(
				'name'         => 'Administrator',
				'capabilities' => array(
					'manage_options' => true,
					'manage_seo'     => true,
					'edit_seo_meta'  => true,
				),
			),
			'editor'        => array(
				'name'         => 'Editor',
				'capabilities' => 'not-an-array',
			),
			'broken'        => 'not-a-role',
		);

		$roles   = new CustomSeoUserRoles();
		$roles->registerRoutes();
		$route = $GLOBALS['mock_rest_routes']['quinrel/v1/roles/capabilities'];
		$result = $route[0]['callback']( new \WP_REST_Request() );
		$data   = $result->get_data();

		$this->assertSame( array( 'manage_seo', 'view_seo_reports', 'manage_redirects', 'edit_seo_meta' ), $data['seo_capabilities'] );
		$this->assertSame( 'Administrator', $data['roles']['administrator']['label'] );
		$this->assertSame(
			array(
				'manage_seo'    => true,
				'edit_seo_meta' => true,
			),
			$data['roles']['administrator']['capabilities']
		);
		$this->assertSame( array(), $data['roles']['editor']['capabilities'] );
		$this->assertArrayNotHasKey( 'broken', $data['roles'] );
	}
}
