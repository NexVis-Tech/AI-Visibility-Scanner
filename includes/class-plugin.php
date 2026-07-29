<?php
namespace AIVisibilityScanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Admin\Admin_Menu;
use AIVisibilityScanner\Admin\Admin_Assets;
use AIVisibilityScanner\API\Rest_API;
use AIVisibilityScanner\Scanner\Scan_Job;

/**
 * Core Plugin Singleton Class.
 */
class Plugin {

	/**
	 * Instance of this class.
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get Singleton Instance.
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Execute plugin initialization.
	 */
	public function run() {
		$this->load_textdomain();

		// Auto-update DB schema if version changes
		if ( get_option( 'avs_db_version' ) !== \AIVisibilityScanner\DB\Schema::DB_VERSION ) {
			\AIVisibilityScanner\DB\Schema::create_tables();
		}

		if ( is_admin() ) {
			$admin_menu   = new Admin_Menu();
			$admin_assets = new Admin_Assets();

			add_action( 'admin_menu', array( $admin_menu, 'register_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $admin_assets, 'enqueue_assets' ) );
		}

		$rest_api = new Rest_API();
		add_action( 'rest_api_init', array( $rest_api, 'register_routes' ) );

		Scan_Job::init_hooks();
	}

	/**
	 * Load plugin textdomain for internationalization.
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'ai-visibility-scanner',
			false,
			dirname( plugin_basename( AVS_FILE ) ) . '/languages'
		);
	}
}
