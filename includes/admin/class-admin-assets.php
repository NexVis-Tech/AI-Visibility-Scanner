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

		$current_screen = get_current_screen();
		$is_edit_screen = $current_screen && 'edit' === $current_screen->base && in_array( $current_screen->post_type, array( 'post', 'page' ), true );

		if ( ! $is_avs_page && ! $is_edit_screen ) {
			return;
		}

		wp_enqueue_style(
			'avs-admin-css',
			AVS_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			AVS_VERSION
		);

		if ( $is_avs_page ) {
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

	/**
	 * Enqueue Gutenberg block editor assets contextually.
	 */
	public function enqueue_editor_assets() {
		$current_screen = get_current_screen();
		if ( ! $current_screen || ! in_array( $current_screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'avs-admin-css',
			AVS_URL . 'assets/css/admin.css',
			array(),
			AVS_VERSION
		);

		wp_enqueue_script(
			'avs-editor-js',
			AVS_URL . 'assets/js/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-element' ),
			AVS_VERSION,
			true
		);

		wp_localize_script(
			'avs-editor-js',
			'avsEditorData',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'avs/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'postId'    => get_the_ID(),
				'reportUrl' => admin_url( 'admin.php?page=avs-report' ),
			)
		);
	}
}
