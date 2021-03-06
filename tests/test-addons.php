<?php
/**
 * Tests for the plugin settings.
 *
 * @package WP101
 */

namespace WP101\Tests;

use WP_Screen;
use WP101\Addons as Addons;

/**
 * Tests for the plugin settings UI, defined in includes/settings.php.
 */
class AddonTest extends TestCase {

	public function test_check_plugins() {
		$api = $this->mock_api();
		$api->shouldReceive( 'has_api_key' )
			->once()
			->andReturn( true );
		$api->shouldReceive( 'get_addons' )
			->once()
			->andReturn( [
				'addons' => [
					[
						'title'        => 'Learning Some Plugin',
						'slug'         => 'learning-some-plugin',
						'excerpt'      => 'An excerpt',
						'description'  => 'The full description',
						'url'          => 'https://wp101plugin.com/series/some-plugin',
						'restrictions' => [
							'plugins' => [
								'some-plugin/some-plugin.php',
							],
						],
					],
				],
			] );
		$api->shouldReceive( 'get_playlist' )
			->once()
			->andReturn( [
				'series' => [],
			] );

		Addons\check_plugins( null, array(
			'some-plugin/some-plugin.php',
			'another-plugin/another-plugin.php',
		) );

		$this->assertEquals( [
			'learning-some-plugin' => [
				'title'  => 'Learning Some Plugin',
				'url'    => 'https://wp101plugin.com/series/some-plugin',
				'plugin' => 'some-plugin/some-plugin.php',
			],
		], get_option( 'wp101-available-series', [] ) );
	}

	public function test_check_plugins_does_not_fire_if_no_api_key_is_set() {
		$api = $this->mock_api();
		$api->shouldReceive( 'has_api_key' )
			->once()
			->andReturn( false );
		$api->shouldReceive( 'get_addons' )
			->never();

		Addons\check_plugins( null, [
			'some-plugin/some-plugin.php',
			'another-plugin/another-plugin.php',
		] );

		$this->assertEmpty( get_option( 'wp101-available-series', [] ) );
	}

	public function test_check_plugins_filters_out_purchased_addons() {
		$api = $this->mock_api();
		$api->shouldReceive( 'has_api_key' )
			->andReturn( true );
		$api->shouldReceive( 'get_addons' )
			->andReturn( [
				'addons' => [
					[
						'title'        => 'Learning Some Plugin',
						'slug'         => 'learning-some-plugin',
						'excerpt'      => 'An excerpt',
						'description'  => 'The full description',
						'url'          => 'https://wp101plugin.com/series/some-plugin',
						'restrictions' => [
							'plugins' => [
								'some-plugin/some-plugin.php',
							],
						],
					],
				],
			] );
		$api->shouldReceive( 'get_playlist' )
			->once()
			->andReturn( [
				'series' => [
					[
						'slug' => 'learning-some-plugin',
					],
				],
			] );

		Addons\check_plugins( null, [
			'some-plugin/some-plugin.php',
		] );

		$this->assertEmpty( get_option( 'wp101-available-series', [] ) );
	}

	public function test_show_notifications() {
		Addons\register_scripts();

		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'administrator',
		] ) );

		update_option( 'wp101-available-series', [
			'learning-some-plugin' => [
				'title'  => 'Learning Some Plugin',
				'url'    => '#',
				'plugin' => 'some-plugin/some-plugin.php',
			],
		] );

		ob_start();
		Addons\show_notifications( WP_Screen::get( 'plugins' ) );
		do_action( 'admin_notices' );
		$output = ob_get_clean();

		$this->assertContains( 'Learning Some Plugin', $output );
		$this->assertContains( 'data-wp101-addon-slug="learning-some-plugin"', $output );
		$this->assertTrue( wp_script_is( 'wp101-addons', 'enqueued' ) );
	}

	/**
	 * @dataProvider notification_page_provider()
	 *
	 * @param string $page     The admin page screen's base.
	 * @param bool   $expected Should the notification appear on this page?
	 */
	public function test_show_notifications_only_shows_on_specific_pages( $page, $expected ) {
		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'administrator',
		] ) );

		update_option( 'wp101-available-series', [
			'learning-some-plugin' => [
				'title'  => 'Learning Some Plugin',
				'url'    => '#',
				'plugin' => 'some-plugin/some-plugin.php',
			],
		] );

		ob_start();
		Addons\show_notifications( WP_Screen::get( $page ) );
		do_action( 'admin_notices' );
		$output = ob_get_clean();

		if ( $expected ) {
			$this->assertNotEmpty( $output );
		} else {
			$this->assertEmpty( $output );
		}
	}

	public function notification_page_provider() {
		return [
			'Plugins page'        => [ 'plugins', true ],
			'WP101 player page'   => [ 'toplevel_page_wp101', true ],
			'WP101 settings page' => [ 'video-tutorials_page_wp101-settings', true ],
			'Posts page'          => [ 'edit', false ],
			'Users page'          => [ 'users', false ],
		];
	}

	public function test_show_notifications_checks_capabilities() {
		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'subscriber',
		] ) );

		update_option( 'wp101-available-series', [
			'learning-some-plugin' => [
				'title'  => 'Learning Some Plugin',
				'url'    => '#',
				'plugin' => 'some-plugin/some-plugin.php',
			],
		] );

		ob_start();
		Addons\show_notifications( WP_Screen::get( 'plugins' ) );
		do_action( 'admin_notices' );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_show_notifications_wont_show_dismissed_notifications() {
		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'administrator',
		] ) );

		update_option( 'wp101-available-series', [
			'learning-some-plugin' => [
				'title'  => 'Learning Some Plugin',
				'url'    => '#',
				'plugin' => 'some-plugin/some-plugin.php',
			],
		] );

		add_user_meta( get_current_user_id(), 'wp101-dismissed-notifications', [
			'learning-some-plugin',
		] );

		ob_start();
		Addons\show_notifications( WP_Screen::get( 'plugins' ) );
		do_action( 'admin_notices' );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_show_notifications_can_flatten_multiple_plugins() {
		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'administrator',
		] ) );

		update_option( 'wp101-available-series', [
			'first-plugin'  => [
				'title'  => 'First plugin',
				'url'    => '#',
				'plugin' => 'first-plugin/first-plugin.php',
			],
			'second-plugin' => [
				'title'  => 'Second plugin',
				'url'    => '#',
				'plugin' => 'second-plugin/second-plugin.php',
			],
			'third-plugin'  => [
				'title'  => 'Third plugin',
				'url'    => '#',
				'plugin' => 'third-plugin/third-plugin.php',
			],
		] );

		ob_start();
		Addons\show_notifications( WP_Screen::get( 'plugins' ) );
		do_action( 'admin_notices' );
		$output = ob_get_clean();

		$this->assertContains( 'First plugin, Second plugin, and Third plugin', strip_tags( $output ) );
		$this->assertContains( 'data-wp101-addon-slug="first-plugin,second-plugin,third-plugin"', $output);
	}

	public function test_dismiss_notification() {
		add_filter( 'wp_die_ajax_handler', function () {
			return '__return_empty_string';
		} );

		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'administrator',
		] ) );

		$_POST = [
			'addons' => [ 'foo', 'bar' ],
			'nonce'  => wp_create_nonce( 'dismiss-notice' ),
		];

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		Addons\dismiss_notice();
		ob_end_clean();

		$this->assertEquals(
			[ 'foo', 'bar' ],
			get_user_meta( get_current_user_id(), 'wp101-dismissed-notifications', true )
		);
	}

	public function test_dismiss_notification_merges_with_existing_settings() {
		add_filter( 'wp_die_ajax_handler', function () {
			return '__return_empty_string';
		} );

		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'administrator',
		] ) );

		add_user_meta( get_current_user_id(), 'wp101-dismissed-notifications', [ 'foo' ] );

		$_POST = [
			'addons' => [ 'bar', 'baz' ],
			'nonce'  => wp_create_nonce( 'dismiss-notice' ),
		];

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		Addons\dismiss_notice();
		ob_end_clean();

		$this->assertEquals(
			[ 'foo', 'bar', 'baz' ],
			get_user_meta( get_current_user_id(), 'wp101-dismissed-notifications', true )
		);
	}
}
