<?php
/**
 * Unit tests covering WP_REST_Global_Styles_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @covers WP_REST_Global_Styles_Controller
 * @group restapi-global-styles
 * @group restapi
 */
class WP_REST_Global_Styles_Controller_Test extends WP_Test_REST_Controller_Testcase {
	/**
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * @var int
	 */
	protected static $global_styles_id;

	/**
	 * @var int
	 */
	protected static $post_id;

	private function find_and_normalize_global_styles_by_id( $global_styles, $id ) {
		foreach ( $global_styles as $style ) {
			if ( $style['id'] === $id ) {
				unset( $style['_links'] );
				return $style;
			}
		}

		return null;
	}

	public function set_up() {
		parent::set_up();
		switch_theme( 'tt1-blocks' );
	}

	/**
	 * Create fake data before our tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetupBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		// This creates the global styles for the current theme.
		self::$global_styles_id = $factory->post->create(
			array(
				'post_content' => '{"version": ' . WP_Theme_JSON::LATEST_SCHEMA . ', "isGlobalStylesUserThemeJSON": true }',
				'post_status'  => 'publish',
				'post_title'   => 'Custom Styles',
				'post_type'    => 'wp_global_styles',
				'post_name'    => 'wp-global-styles-tt1-blocks',
				'tax_input'    => array(
					'wp_theme' => 'tt1-blocks',
				),
			)
		);

		self::$post_id = $factory->post->create();
	}

	/**
	 *
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::register_routes
	 * @ticket 54596
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey(
			'/wp/v2/global-styles/(?P<id>[\/\s%\w\.\(\)\[\]\@_\-]+)',
			$routes,
			'Single global style based on the given ID route does not exist'
		);
		$this->assertCount(
			2,
			$routes['/wp/v2/global-styles/(?P<id>[\/\s%\w\.\(\)\[\]\@_\-]+)'],
			'Single global style based on the given ID route does not have exactly two elements'
		);
		$this->assertArrayHasKey(
			'/wp/v2/global-styles/themes/(?P<stylesheet>[\/\s%\w\.\(\)\[\]\@_\-]+)',
			$routes,
			'Theme global styles route does not exist'
		);
		$this->assertCount(
			1,
			$routes['/wp/v2/global-styles/themes/(?P<stylesheet>[\/\s%\w\.\(\)\[\]\@_\-]+)'],
			'Theme global styles route does not have exactly one element'
		);
	}

	public function test_context_param() {
		$this->markTestSkipped( 'Controller does not implement context_param().' );
	}

	public function test_get_items() {
		$this->markTestSkipped( 'Controller does not implement get_items().' );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_theme_item
	 * @ticket 54516
	 */
	public function test_get_theme_item_no_user() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/tt1-blocks' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_global_styles', $response, 401 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_theme_item
	 * @ticket 54516
	 */
	public function test_get_theme_item_permission_check() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/tt1-blocks' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_manage_global_styles', $response, 403 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_theme_item
	 * @ticket 54516
	 */
	public function test_get_theme_item_invalid() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_theme_not_found', $response, 404 );
	}

	/**
	 * @dataProvider data_get_theme_item_invalid_theme_dirname
	 * @covers WP_REST_Global_Styles_Controller::get_theme_item
	 * @ticket 54596
	 */
	public function test_get_theme_item_invalid_theme_dirname( $theme_dirname ) {
		wp_set_current_user( self::$admin_id );
		switch_theme( $theme_dirname );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/' . $theme_dirname );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_theme_item_invalid_theme_dirname() {
		return array(
			'with |'                 => array( 'my|theme' ),
			'with +'                 => array( 'my+theme' ),
			'with {}'                => array( 'my{theme}' ),
			'with #'                 => array( 'my#theme' ),
			'with !'                 => array( 'my!theme' ),
			'multiple invalid chars' => array( 'mytheme-[_(+@)]#! v4.0' ),
		);
	}

	/**
	 * @dataProvider data_get_theme_item
	 * @covers WP_REST_Global_Styles_Controller::get_theme_item
	 * @ticket 54596
	 */
	public function test_get_theme_item( $theme ) {
		wp_set_current_user( self::$admin_id );
		switch_theme( $theme );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/' . $theme );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$links    = $response->get_links();
		$this->assertArrayHasKey( 'settings', $data, 'Data does not have "settings" key' );
		$this->assertArrayHasKey( 'styles', $data, 'Data does not have "styles" key' );
		$this->assertArrayHasKey( 'self', $links, 'Links do not have a "self" key' );
		$this->assertStringContainsString( '/wp/v2/global-styles/themes/' . $theme, $links['self'][0]['href'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_theme_item() {
		return array(
			'alphabetic chars'   => array( 'mytheme' ),
			'alphanumeric chars' => array( 'mythemev1' ),
			'space'              => array( 'my theme' ),
			'-'                  => array( 'my-theme' ),
			'_'                  => array( 'my_theme' ),
			'.'                  => array( 'mytheme0.1' ),
			'- and .'            => array( 'my-theme-0.1' ),
			'space and .'        => array( 'mytheme v0.1' ),
			'space, -, _, .'     => array( 'my-theme-v0.1' ),
			'[]'                 => array( 'my[theme]' ),
			'()'                 => array( 'my(theme)' ),
			'@'                  => array( 'my@theme' ),
		);
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_theme_item
	 * @ticket 54595
	 */
	public function test_get_theme_item_fields() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/themes/tt1-blocks' );
		$request->set_param( '_fields', 'settings' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'settings', $data );
		$this->assertArrayNotHasKey( 'styles', $data );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 * @ticket 54516
	 */
	public function test_get_item_no_user() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 * @ticket 54516
	 */
	public function test_get_item_invalid_post() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_global_styles_not_found', $response, 404 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 * @ticket 54516
	 */
	public function test_get_item_permission_check() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 403 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 * @ticket 54516
	 */
	public function test_get_item_no_user_edit() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 * @ticket 54516
	 */
	public function test_get_item_permission_check_edit() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_item
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/global-styles/' . self::$global_styles_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$links    = $response->get_links();

		$this->assertEquals(
			array(
				'id'       => self::$global_styles_id,
				'title'    => array(
					'raw'      => 'Custom Styles',
					'rendered' => 'Custom Styles',
				),
				'settings' => new stdClass(),
				'styles'   => new stdClass(),
			),
			$data
		);

		$this->assertArrayHasKey( 'self', $links );
		$this->assertStringContainsString( '/wp/v2/global-styles/' . self::$global_styles_id, $links['self'][0]['href'] );
	}

	public function test_create_item() {
		$this->markTestSkipped( 'Controller does not implement create_item().' );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::update_item
	 * @ticket 54516
	 */
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/global-styles/' . self::$global_styles_id );
		$request->set_body_params(
			array(
				'title' => 'My new global styles title',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'My new global styles title', $data['title']['raw'] );
	}


	/**
	 * @covers WP_REST_Global_Styles_Controller::update_item
	 * @ticket 54516
	 */
	public function test_update_item_no_user() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'PUT', '/wp/v2/global-styles/' . self::$global_styles_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::update_item
	 * @ticket 54516
	 */
	public function test_update_item_invalid_post() {
		wp_set_current_user( self::$admin_id );
		$request  = new WP_REST_Request( 'PUT', '/wp/v2/global-styles/' . self::$post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_global_styles_not_found', $response, 404 );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::update_item
	 * @ticket 54516
	 */
	public function test_update_item_permission_check() {
		wp_set_current_user( self::$subscriber_id );
		$request  = new WP_REST_Request( 'PUT', '/wp/v2/global-styles/' . self::$global_styles_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_delete_item() {
		$this->markTestSkipped( 'Controller does not implement delete_item().' );
	}

	public function test_prepare_item() {
		$this->markTestSkipped( 'Controller does not implement prepare_item().' );
	}

	/**
	 * @covers WP_REST_Global_Styles_Controller::get_item_schema
	 * @ticket 54516
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/global-styles/' . self::$global_styles_id );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 4, $properties, 'Schema properties array does not have exactly 4 elements' );
		$this->assertArrayHasKey( 'id', $properties, 'Schema properties array does not have "id" key' );
		$this->assertArrayHasKey( 'styles', $properties, 'Schema properties array does not have "styles" key' );
		$this->assertArrayHasKey( 'settings', $properties, 'Schema properties array does not have "settings" key' );
		$this->assertArrayHasKey( 'title', $properties, 'Schema properties array does not have "title" key' );
	}
}
