<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Orchestrator;

/**
 * Handles async background processing via Action Scheduler or WP-Cron.
 */
class Scan_Job {

	/**
	 * Hook initialization.
	 */
	public static function init_hooks() {
		add_action( 'avs_run_scan_batch', array( __CLASS__, 'process_batch' ) );
	}

	/**
	 * Enqueue a scan job batch.
	 *
	 * @param int $scan_id
	 */
	public static function enqueue( int $scan_id ) {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'avs_run_scan_batch', array( 'scan_id' => $scan_id ), 'ai-visibility-scanner' );
		} else {
			// Shared hosting fallback: schedule single event via WP-Cron
			if ( ! wp_next_scheduled( 'avs_run_scan_batch', array( $scan_id ) ) ) {
				wp_schedule_single_event( time(), 'avs_run_scan_batch', array( $scan_id ) );
			}
			// Synchronous immediate execution fallback for small sites
			self::process_batch( $scan_id );
		}
	}

	/**
	 * Process background scan batch.
	 *
	 * @param int $scan_id
	 */
	public static function process_batch( int $scan_id ) {
		$orchestrator = new Orchestrator();
		$orchestrator->process_scan( $scan_id );
	}
}
