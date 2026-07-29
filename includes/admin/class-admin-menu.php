<?php
namespace AIVisibilityScanner\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles wp-admin menu registration.
 */
class Admin_Menu {

	/**
	 * Register menu pages.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'AI Visibility Scanner', 'ai-visibility-scanner' ),
			__( 'AI Visibility', 'ai-visibility-scanner' ),
			'manage_options',
			'ai-visibility-scanner',
			array( $this, 'render_dashboard_page' ),
			'dashicons-search',
			80
		);

		add_submenu_page(
			'ai-visibility-scanner',
			__( 'Dashboard & Scanner', 'ai-visibility-scanner' ),
			__( 'Dashboard', 'ai-visibility-scanner' ),
			'manage_options',
			'ai-visibility-scanner',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'ai-visibility-scanner',
			__( 'Audit Report', 'ai-visibility-scanner' ),
			__( 'Scan Report', 'ai-visibility-scanner' ),
			'manage_options',
			'avs-report',
			array( $this, 'render_report_page' )
		);

		add_submenu_page(
			'ai-visibility-scanner',
			__( 'Diagnostics & Logs', 'ai-visibility-scanner' ),
			__( 'Diagnostics 🛠️', 'ai-visibility-scanner' ),
			'manage_options',
			'avs-diagnostics',
			array( $this, 'render_diagnostics_page' )
		);

		add_submenu_page(
			'ai-visibility-scanner',
			__( 'Scanner Settings', 'ai-visibility-scanner' ),
			__( 'Settings', 'ai-visibility-scanner' ),
			'manage_options',
			'avs-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'ai-visibility-scanner',
			__( 'Upgrade to Pro', 'ai-visibility-scanner' ),
			__( 'Get Pro ✨', 'ai-visibility-scanner' ),
			'manage_options',
			'avs-pro',
			array( $this, 'render_pro_page' )
		);
		add_filter( 'admin_footer_text', array( $this, 'custom_admin_footer' ) );
	}

	/**
	 * Custom Admin Footer Attribution.
	 */
	public function custom_admin_footer( $footer_text ) {
		$current_screen = get_current_screen();
		if ( $current_screen && strpos( $current_screen->id, 'avs' ) !== false || ( isset( $_GET['page'] ) && strpos( sanitize_text_field( $_GET['page'] ), 'ai-visibility' ) !== false ) ) {
			return sprintf(
				/* translators: 1: NexVis URL, 2: Mudassar Ijaz GitHub URL */
				__( 'Thank you for using <strong>AI Visibility Scanner</strong> (v%1$s) — Developed by <a href="%2$s" target="_blank">NexVis Technologies</a> & <a href="%3$s" target="_blank">Mudassar Ijaz</a>', 'ai-visibility-scanner' ),
				AVS_VERSION,
				'https://nexvistech.com',
				'https://github.com/mudassarijaz'
			);
		}
		return $footer_text;
	}

	public function render_dashboard_page() {
		include AVS_PATH . 'includes/admin/views/dashboard.php';
	}

	public function render_report_page() {
		include AVS_PATH . 'includes/admin/views/report.php';
	}

	public function render_diagnostics_page() {
		include AVS_PATH . 'includes/admin/views/diagnostics.php';
	}

	public function render_settings_page() {
		include AVS_PATH . 'includes/admin/views/settings.php';
	}

	public function render_pro_page() {
		include AVS_PATH . 'includes/admin/views/pro.php';
	}
}
