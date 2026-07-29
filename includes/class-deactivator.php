<?php
namespace AIVisibilityScanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles deactivation logic.
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate() {
		// Clean up Action Scheduler background tasks or cron hooks if needed
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'avs_run_scan_batch' );
		}
	}
}
