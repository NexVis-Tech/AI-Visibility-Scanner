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
	}
}
