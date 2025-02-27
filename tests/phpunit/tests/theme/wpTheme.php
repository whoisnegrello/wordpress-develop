<?php
/**
/**
 * Test WP_Theme class.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @group themes
 */
class Tests_Theme_wpTheme extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->theme_root = realpath( DIR_TESTDATA . '/themedir1' );

		$this->orig_theme_dir            = $GLOBALS['wp_theme_directories'];
		$GLOBALS['wp_theme_directories'] = array( $this->theme_root );

		add_filter( 'theme_root', array( $this, '_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, '_theme_root' ) );
		add_filter( 'template_root', array( $this, '_theme_root' ) );
		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tear_down();
	}

	// Replace the normal theme root directory with our premade test directory.
	public function _theme_root( $dir ) {
		return $this->theme_root;
	}

	public function test_new_WP_Theme_top_level() {
		$theme = new WP_Theme( 'theme1', $this->theme_root );

		// Meta.
		$this->assertSame( 'My Theme', $theme->get( 'Name' ) );
		$this->assertSame( 'http://example.org/', $theme->get( 'ThemeURI' ) );
		$this->assertSame( 'An example theme', $theme->get( 'Description' ) );
		$this->assertSame( 'Minnie Bannister', $theme->get( 'Author' ) );
		$this->assertSame( 'http://example.com/', $theme->get( 'AuthorURI' ) );
		$this->assertSame( '1.3', $theme->get( 'Version' ) );
		$this->assertSame( '', $theme->get( 'Template' ) );
		$this->assertSame( 'publish', $theme->get( 'Status' ) );
		$this->assertSame( array(), $theme->get( 'Tags' ) );

		// Important.
		$this->assertSame( 'theme1', $theme->get_stylesheet() );
		$this->assertSame( 'theme1', $theme->get_template() );
	}

	public function test_new_WP_Theme_subdir() {
		$theme = new WP_Theme( 'subdir/theme2', $this->theme_root );

		// Meta.
		$this->assertSame( 'My Subdir Theme', $theme->get( 'Name' ) );
		$this->assertSame( 'http://example.org/', $theme->get( 'ThemeURI' ) );
		$this->assertSame( 'An example theme in a sub directory', $theme->get( 'Description' ) );
		$this->assertSame( 'Mr. WordPress', $theme->get( 'Author' ) );
		$this->assertSame( 'http://wordpress.org/', $theme->get( 'AuthorURI' ) );
		$this->assertSame( '0.1', $theme->get( 'Version' ) );
		$this->assertSame( '', $theme->get( 'Template' ) );
		$this->assertSame( 'publish', $theme->get( 'Status' ) );
		$this->assertSame( array(), $theme->get( 'Tags' ) );

		// Important.
		$this->assertSame( 'subdir/theme2', $theme->get_stylesheet() );
		$this->assertSame( 'subdir/theme2', $theme->get_template() );
	}

	/**
	 * @ticket 20313
	 */
	public function test_new_WP_Theme_subdir_bad_root() {
		// This is what get_theme_data() does when you pass it a style.css file for a theme in a subdirectory.
		$theme = new WP_Theme( 'theme2', $this->theme_root . '/subdir' );

		// Meta.
		$this->assertSame( 'My Subdir Theme', $theme->get( 'Name' ) );
		$this->assertSame( 'http://example.org/', $theme->get( 'ThemeURI' ) );
		$this->assertSame( 'An example theme in a sub directory', $theme->get( 'Description' ) );
		$this->assertSame( 'Mr. WordPress', $theme->get( 'Author' ) );
		$this->assertSame( 'http://wordpress.org/', $theme->get( 'AuthorURI' ) );
		$this->assertSame( '0.1', $theme->get( 'Version' ) );
		$this->assertSame( '', $theme->get( 'Template' ) );
		$this->assertSame( 'publish', $theme->get( 'Status' ) );
		$this->assertSame( array(), $theme->get( 'Tags' ) );

		// Important.
		$this->assertSame( 'subdir/theme2', $theme->get_stylesheet() );
		$this->assertSame( 'subdir/theme2', $theme->get_template() );
	}

	/**
	 * @ticket 21749
	 */
	public function test_wp_theme_uris_with_spaces() {
		$theme = new WP_Theme( 'theme with spaces', $this->theme_root . '/subdir' );
		// Make sure subdir/ is considered part of the stylesheet, as we must avoid encoding /'s.
		$this->assertSame( 'subdir/theme with spaces', $theme->get_stylesheet() );

		// Check that in a URI path, we have raw URL encoding (spaces become %20).
		// Don't try to verify the complete URI path. get_theme_root_uri() breaks down quickly.
		$this->assertSame( 'theme%20with%20spaces', basename( $theme->get_stylesheet_directory_uri() ) );
		$this->assertSame( 'theme%20with%20spaces', basename( $theme->get_template_directory_uri() ) );

		// Check that wp_customize_url() uses URL encoding, as it is a query arg (spaces become +).
		$this->assertSame( admin_url( 'customize.php?theme=theme+with+spaces' ), wp_customize_url( 'theme with spaces' ) );
	}

	/**
	 * @ticket 21969
	 */
	public function test_theme_uris_with_spaces() {
		$callback = array( $this, 'filter_theme_with_spaces' );
		add_filter( 'stylesheet', $callback );
		add_filter( 'template', $callback );

		$this->assertSame( get_theme_root_uri() . '/subdir/theme%20with%20spaces', get_stylesheet_directory_uri() );
		$this->assertSame( get_theme_root_uri() . '/subdir/theme%20with%20spaces', get_template_directory_uri() );

		remove_filter( 'stylesheet', $callback );
		remove_filter( 'template', $callback );
	}

	public function filter_theme_with_spaces() {
		return 'subdir/theme with spaces';
	}

	/**
	 * @ticket 26873
	 */
	public function test_display_method_on_get_method_failure() {
		$theme = new WP_Theme( 'nonexistent', $this->theme_root );
		$this->assertSame( 'nonexistent', $theme->get( 'Name' ) );
		$this->assertFalse( $theme->get( 'AuthorURI' ) );
		$this->assertFalse( $theme->get( 'Tags' ) );
		$this->assertFalse( $theme->display( 'Tags' ) );
	}

	/**
	 * @ticket 40820
	 */
	public function test_child_theme_with_itself_as_parent_should_appear_as_broken() {
		$theme  = new WP_Theme( 'child-parent-itself', $this->theme_root );
		$errors = $theme->errors();
		$this->assertWPError( $errors );
		$this->assertSame( 'theme_child_invalid', $errors->get_error_code() );
	}


	/**
	 * Enable a single theme on a network.
	 *
	 * @ticket 30594
	 * @group ms-required
	 */
	public function test_wp_theme_network_enable_single_theme() {
		$theme                  = 'testtheme-1';
		$current_allowed_themes = get_site_option( 'allowedthemes' );
		WP_Theme::network_enable_theme( $theme );
		$new_allowed_themes = get_site_option( 'allowedthemes' );
		update_site_option( 'allowedthemes', $current_allowed_themes ); // Reset previous value.
		$current_allowed_themes['testtheme-1'] = true; // Add the new theme to the previous set.

		$this->assertSameSetsWithIndex( $current_allowed_themes, $new_allowed_themes );
	}

	/**
	 * Enable multiple themes on a network.
	 *
	 * @ticket 30594
	 * @group ms-required
	 */
	public function test_wp_theme_network_enable_multiple_themes() {
		$themes                 = array( 'testtheme-2', 'testtheme-3' );
		$current_allowed_themes = get_site_option( 'allowedthemes' );
		WP_Theme::network_enable_theme( $themes );
		$new_allowed_themes = get_site_option( 'allowedthemes' );
		update_site_option( 'allowedthemes', $current_allowed_themes ); // Reset previous value.
		$current_allowed_themes = array_merge(
			$current_allowed_themes,
			array(
				'testtheme-2' => true,
				'testtheme-3' => true,
			)
		);

		$this->assertSameSetsWithIndex( $current_allowed_themes, $new_allowed_themes );
	}

	/**
	 * Disable a single theme on a network.
	 *
	 * @ticket 30594
	 * @group ms-required
	 */
	public function test_network_disable_single_theme() {
		$current_allowed_themes = get_site_option( 'allowedthemes' );

		$allowed_themes = array(
			'existing-1' => true,
			'existing-2' => true,
			'existing-3' => true,
		);
		update_site_option( 'allowedthemes', $allowed_themes );

		$disable_theme = 'existing-2';
		WP_Theme::network_disable_theme( $disable_theme );
		$new_allowed_themes = get_site_option( 'allowedthemes' );
		update_site_option( 'allowedthemes', $current_allowed_themes ); // Reset previous value.
		unset( $allowed_themes[ $disable_theme ] ); // Remove deleted theme from initial set.

		$this->assertSameSetsWithIndex( $allowed_themes, $new_allowed_themes );
	}

	/**
	 * Disable multiple themes on a network.
	 *
	 * @ticket 30594
	 * @group ms-required
	 */
	public function test_network_disable_multiple_themes() {
		$current_allowed_themes = get_site_option( 'allowedthemes' );

		$allowed_themes = array(
			'existing-4' => true,
			'existing-5' => true,
			'existing-6' => true,
		);
		update_site_option( 'allowedthemes', $allowed_themes );

		$disable_themes = array( 'existing-4', 'existing-5' );
		WP_Theme::network_disable_theme( $disable_themes );
		$new_allowed_themes = get_site_option( 'allowedthemes' );
		update_site_option( 'allowedthemes', $current_allowed_themes ); // Reset previous value.
		unset( $allowed_themes['existing-4'] );
		unset( $allowed_themes['existing-5'] );

		$this->assertSameSetsWithIndex( $allowed_themes, $new_allowed_themes );
	}

	/**
	 * @dataProvider data_is_block_theme
	 * @ticket 54460
	 *
	 * @covers WP_Theme::is_block_theme
	 *
	 * @param string $theme_dir Directory of the theme to test.
	 * @param bool   $expected  Expected result.
	 */
	public function test_is_block_theme( $theme_dir, $expected ) {
		$theme = new WP_Theme( $theme_dir, $this->theme_root );
		$this->assertSame( $expected, $theme->is_block_theme() );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_is_block_theme() {
		return array(
			'default - non-block theme' => array(
				'theme_dir' => 'default',
				'expected'  => false,
			),
			'parent block theme'        => array(
				'theme_dir' => 'test-block-theme',
				'expected'  => true,
			),
			'child block theme'         => array(
				'theme_dir' => 'test-block-child-theme',
				'expected'  => true,
			),
		);
	}

	/**
	 * @dataProvider data_get_file_path
	 * @ticket 54460
	 *
	 * @covers WP_Theme::get_file_path
	 *
	 * @param string $theme_dir Directory of the theme to test.
	 * @param string $file      Given file name to test.
	 * @param string $expected  Expected file path.
	 */
	public function test_get_file_path( $theme_dir, $file, $expected ) {
		$theme = new WP_Theme( $theme_dir, $this->theme_root );

		$this->assertStringEndsWith( $expected, $theme->get_file_path( $file ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_file_path() {
		return array(
			'no theme: no file given'           => array(
				'theme_dir' => 'nonexistent',
				'file'      => '',
				'expected'  => '/nonexistent',
			),
			'parent theme: no file given'       => array(
				'theme_dir' => 'test-block-theme',
				'file'      => '',
				'expected'  => '/test-block-theme',
			),
			'child theme: no file given'        => array(
				'theme_dir' => 'test-block-child-theme',
				'file'      => '',
				'expected'  => '/test-block-child-theme',
			),
			'nonexistent theme: file given'     => array(
				'theme_dir' => 'nonexistent',
				'file'      => '/templates/page.html',
				'expected'  => '/nonexistent/templates/page.html',
			),
			'parent theme: file exists'         => array(
				'theme_dir' => 'test-block-theme',
				'file'      => '/templates/page-home.html',
				'expected'  => '/test-block-theme/templates/page-home.html',
			),
			'parent theme: file does not exist' => array(
				'theme_dir' => 'test-block-theme',
				'file'      => '/templates/nonexistent.html',
				'expected'  => '/test-block-theme/templates/nonexistent.html',
			),
			'child theme: file exists'          => array(
				'theme_dir' => 'test-block-child-theme',
				'file'      => '/templates/page-1.html',
				'expected'  => '/test-block-child-theme/templates/page-1.html',
			),
			'child theme: file does not exist'  => array(
				'theme_dir' => 'test-block-child-theme',
				'file'      => '/templates/nonexistent.html',
				'expected'  => '/test-block-theme/templates/nonexistent.html',
			),
			'child theme: file exists in parent, not in child' => array(
				'theme_dir' => 'test-block-child-theme',
				'file'      => '/templates/page.html',
				'expected'  => '/test-block-theme/templates/page.html',
			),
		);
	}

	/**
	 * Tests that block themes support a feature by default.
	 *
	 * @ticket 54597
	 * @dataProvider data_block_theme_has_default_support
	 *
	 * @covers ::_add_default_theme_supports
	 *
	 * @param array $support {
	 *     The feature to check.
	 *
	 *     @type string $feature     The feature to check.
	 *     @type string $sub_feature Optional. The sub-feature to check.
	 * }
	 */
	public function test_block_theme_has_default_support( $support ) {
		$this->helper_requires_block_theme();

		$support_data     = array_values( $support );
		$support_data_str = implode( ': ', $support_data );

		// Remove existing support.
		if ( current_theme_supports( ...$support_data ) ) {
			remove_theme_support( ...$support_data );
		}

		$this->assertFalse(
			current_theme_supports( ...$support_data ),
			"Could not remove support for $support_data_str."
		);

		do_action( 'setup_theme' );

		$this->assertTrue(
			current_theme_supports( ...$support_data ),
			"Does not have default support for $support_data_str."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_block_theme_has_default_support() {
		return array(
			'post-thumbnails'      => array(
				'support' => array(
					'feature' => 'post-thumbnails',
				),
			),
			'responsive-embeds'    => array(
				'support' => array(
					'feature' => 'responsive-embeds',
				),
			),
			'editor-styles'        => array(
				'support' => array(
					'feature' => 'editor-styles',
				),
			),
			'html5: style'         => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'style',
				),
			),
			'html5: script'        => array(
				'support' => array(
					'feature'     => 'html5',
					'sub_feature' => 'script',
				),
			),
			'automatic-feed-links' => array(
				'support' => array(
					'feature' => 'automatic-feed-links',
				),
			),
		);
	}

	/**
	 * Tests that block themes load separate core block assets by default.
	 *
	 * @ticket 54597
	 *
	 * @covers ::wp_should_load_separate_core_block_assets
	 */
	public function test_block_theme_should_load_separate_core_block_assets_by_default() {
		$this->helper_requires_block_theme();

		add_filter( 'should_load_separate_core_block_assets', '__return_false' );

		$this->assertFalse(
			wp_should_load_separate_core_block_assets(),
			'Could not disable loading separate core block assets.'
		);

		do_action( 'setup_theme' );

		$this->assertTrue(
			wp_should_load_separate_core_block_assets(),
			'Block themes do not load separate core block assets by default.'
		);
	}

	/**
	 * Helper function to ensure that a block theme is available and active.
	 */
	private function helper_requires_block_theme() {
		// No need to switch if we're already on a block theme.
		if ( wp_is_block_theme() ) {
			return;
		}

		$block_theme        = 'twentytwentytwo';
		$block_theme_object = new WP_Theme( $block_theme, WP_CONTENT_DIR . '/themes' );

		// Skip if the block theme is not available.
		if ( ! $block_theme_object->exists() ) {
			$this->markTestSkipped( "$block_theme must be available." );
		}

		switch_theme( $block_theme );

		// Skip if we could not switch to the block theme.
		if ( wp_get_theme()->stylesheet !== $block_theme ) {
			$this->markTestSkipped( "Could not switch to $block_theme." );
		}
	}
}
