<?php
namespace AIVisibilityScanner\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads admin scripts and styles contextually on plugin screens.
 */
class Admin_Assets {

	/**
	 * Enqueue assets only on AI Visibility Scanner pages.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_assets( $hook_suffix ) {
		$page        = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		$valid_pages = array(
			'ai-visibility-scanner',
			'avs-report',
			'avs-diagnostics',
			'avs-settings',
			'avs-pro',
		);

		$is_avs_page = in_array( $page, $valid_pages, true )
			|| strpos( $hook_suffix, 'ai-visibility' ) !== false
			|| strpos( $hook_suffix, 'avs-' ) !== false;

		if ( ! $is_avs_page ) {
			return;
		}

		wp_enqueue_style(
			'avs-admin-css',
			AVS_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			AVS_VERSION
		);

		wp_enqueue_script(
			'avs-admin-js',
			AVS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AVS_VERSION,
			true
		);

		wp_localize_script(
			'avs-admin-js',
			'avsData',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'avs/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'siteUrl'  => get_site_url(),
				'reportUrl'=> admin_url( 'admin.php?page=avs-report' ),
			)
		);
	}
}
