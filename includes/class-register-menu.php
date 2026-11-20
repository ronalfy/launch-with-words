<?php
/**
 * Register the sub-menus needed for this plugin.
 *
 * @package Launch With Words
 */

namespace LWW\Includes;

use LWW\Includes\Settings_Page;
use LWW\Includes\Functions;

/**
 * Plugin sub-menus and helper methods.
 */
class Register_Menu {

	/**
	 * Class runner.
	 */
	public function run() {
		add_action( 'admin_menu', array( $this, 'register_import_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_posts_page' ) );
	}

	/**
	 * Redirect the legacy Settings screen to the Posts import screen.
	 *
	 * The page query argument is only used to recognize the old menu slug.
	 * No data is saved, so this redirect does not use a nonce.
	 */
	public function maybe_redirect_posts_page() {
		if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fixed-slug redirect. No data is saved.
		$page = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';
		if ( ! is_string( $page ) || 'launch-with-words-options' !== sanitize_key( $page ) ) {
			return;
		}

		wp_safe_redirect( Functions::get_settings_url() );
		exit;
	}

	/**
	 * Register the import menu.
	 */
	public function register_import_menu() {

		add_posts_page(
			__( 'Launch With Words', 'launch-with-words' ),
			__( 'Launch With Words', 'launch-with-words' ),
			'manage_options',
			'launch-with-words',
			array( '\LWW\Includes\Settings_Page', 'settings_page' ),
			100
		);

		add_options_page(
			__( 'Launch With Words', 'launch-with-words' ),
			__( 'Launch With Words', 'launch-with-words' ),
			'manage_options',
			'launch-with-words-options',
			array( $this, 'output' ),
			100
		);
	}

	/**
	 * Create empty output page for Launch With Words Settings page.
	 */
	public function output() {
		// Empty.
	}
}
