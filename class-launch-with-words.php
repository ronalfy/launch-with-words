<?php
/**
 * Launch with Words Importer
 *
 * @package Launch with Words
 */

namespace LWW;

/**
 * Plugin Name:       Launch With Words
 * Plugin URI:        https://launchwithwords.com/
 * Description:       Launch With Words installs a year's worth of blog post prompts to encourage and guide your client in blogging best practices -- not lorem ipsum or bacon ipsum or other placeholder text.
 * Version:           1.2.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Ronald Huereca
 * Author URI:        https://ronaldh.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       launch-with-words
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Setup the plugin auto loader.
require_once 'autoloader.php';

define( 'LWW_FILE', __FILE__ );
define( 'LWW_VERSION', '1.2.0' );

/**
 * The plugin base class.
 */
class Launch_With_Words {

	/**
	 * Import menu.
	 *
	 * @var \LWW\Includes\Register_Menu
	 */
	private $import_menu;

	/**
	 * Plugin settings links.
	 *
	 * @var \LWW\Includes\Plugin_Settings_Links
	 */
	private $plugin_settings;

	/**
	 * Admin tabs.
	 *
	 * @var \LWW\Includes\Templates\Init
	 */
	private $tabs;

	/**
	 * Launch With Words instance.
	 *
	 * @var Launch_With_Words $instance
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class Constructor
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 20 );
	}

	/**
	 * Fired when the plugins for WordPress have finished loading.
	 */
	public function plugins_loaded() {

		// Register import menu.
		$this->import_menu = new \LWW\Includes\Register_Menu();
		$this->import_menu->run();

		// Register plugin settings.
		$this->plugin_settings = new \LWW\Includes\Plugin_Settings_Links();
		$this->plugin_settings->run();

		// Tabs initializer.
		$this->tabs = new \LWW\Includes\Templates\Init();
		$this->tabs->run();
	}
}

Launch_With_Words::get_instance();

register_activation_hook( __FILE__, array( '\LWW\Includes\Plugin_Settings_Links', 'plugin_activate' ) );
add_action( 'admin_init', array( '\LWW\Includes\Plugin_Settings_Links', 'redirect_to_import_screen' ) );
