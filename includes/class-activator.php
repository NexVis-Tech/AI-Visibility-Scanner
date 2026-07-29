<?php
namespace AIVisibilityScanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\DB\Schema;

/**
 * Handles activation logic.
 */
class Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		try {
			if ( defined( 'AVS_PATH' ) && file_exists( AVS_PATH . 'includes/db/class-schema.php' ) ) {
				require_once AVS_PATH . 'includes/db/class-schema.php';
			}

			Schema::create_tables();

			// Set default settings if not exists
			if ( false === get_option( 'avs_settings' ) ) {
				$defaults = array(
					'post_types'           => array( 'post', 'page' ),
					'max_pages'            => 30,
					'exclude_urls'         => '',
					'enable_credit_footer' => 1,
				);
				update_option( 'avs_settings', $defaults );
			}
		} catch ( \Throwable $e ) {
			error_log( 'AI Visibility Scanner activation error: ' . $e->getMessage() );
		}
	}
}
