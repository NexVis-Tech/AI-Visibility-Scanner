<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Crawler;
use AIVisibilityScanner\Scanner\Page_Fetcher;
use AIVisibilityScanner\Scanner\Scan_Job;
use AIVisibilityScanner\Scanner\Environment_Collector;
use AIVisibilityScanner\Scanner\Diagnostics_Logger;
use AIVisibilityScanner\Scanner\Checks\Check_Registry;
use AIVisibilityScanner\Scoring\Scoring_Engine;

/**
 * Manages scan lifecycle and check pipeline execution.
 */
class Orchestrator {

	/**
	 * Initialize a new scan run.
	 *
	 * @return int|\WP_Error Scan ID on success
	 */
	public function start_scan() {
		global $wpdb;
		$table_scans = $wpdb->prefix . 'avs_scans';

		// Auto-heal check: If table missing, attempt creation on-the-fly
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_scans ) ) !== $table_scans ) {
			if ( class_exists( '\\AIVisibilityScanner\\DB\\Schema' ) ) {
				\AIVisibilityScanner\DB\Schema::create_tables();
			}
		}

		$crawler = new Crawler();
		$urls    = $crawler->get_urls_to_scan();

		$data = array(
			'site_url'      => get_site_url(),
			'status'        => 'queued',
			'pages_scanned' => 0,
			'pages_total'   => count( $urls ),
			'started_at'    => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert( $table_scans, $data, array( '%s', '%s', '%d', '%d', '%s' ) );

		if ( ! $inserted ) {
			$db_err = ! empty( $wpdb->last_error ) ? $wpdb->last_error : __( 'Database table missing or MySQL user lacks INSERT privileges.', 'ai-visibility-scanner' );
			return new \WP_Error(
				'avs_scan_failed',
				sprintf(
					/* translators: %s: Database error string */
					__( 'Could not initialize scan in database (%s). Developer Tip: Please check your MySQL table permissions or navigate to AI Visibility > Diagnostics to run system health checks.', 'ai-visibility-scanner' ),
					$db_err
				)
			);
		}

		$scan_id = $wpdb->insert_id;

		Scan_Job::enqueue( $scan_id );

		return $scan_id;
	}

	/**
	 * Execute scan logic across discovered URLs.
	 *
	 * @param int $scan_id
	 */
	public function process_scan( int $scan_id ) {
		global $wpdb;
		$table_scans   = $wpdb->prefix . 'avs_scans';
		$table_results = $wpdb->prefix . 'avs_check_results';

		try {
			// Update status to running
			$wpdb->update( $table_scans, array( 'status' => 'running' ), array( 'id' => $scan_id ), array( '%s' ), array( '%d' ) );

			// 1. Capture environment fingerprint at scan start
			$env_data = Environment_Collector::collect();
			Diagnostics_Logger::log_environment( $scan_id, $env_data );

			$crawler      = new Crawler();
			$fetcher      = new Page_Fetcher( $scan_id );
			$registry     = new Check_Registry();
			$checks       = $registry->get_checks();
			$site_context = $crawler->get_site_context();
			$urls         = $crawler->get_urls_to_scan();

			$settings     = get_option( 'avs_settings', array() );
			$delay_ms     = isset( $settings['request_delay'] ) ? (int) $settings['request_delay'] : 500;

			$pages_scanned = 0;

			foreach ( $urls as $url ) {
				// Optimize: Fetch page HTML once per URL instead of once per check!
				$html_body = $fetcher->fetch_in_process( $url, 'page_fetch' );

				foreach ( $checks as $check ) {
					$result_obj = $check->run( $url, $html_body, $site_context );

					$wpdb->insert(
						$table_results,
						array(
							'scan_id'      => $scan_id,
							'page_url'     => $url,
							'check_slug'   => $result_obj->slug,
							'category'     => $result_obj->category,
							'result'       => $result_obj->result,
							'evidence'     => $result_obj->evidence,
							'fix_hint'     => $result_obj->fix_hint,
							'effort_score' => $result_obj->effort_score,
							'impact_score' => $result_obj->impact_score,
						),
						array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
					);
				}

				$pages_scanned++;
				$wpdb->update( $table_scans, array( 'pages_scanned' => $pages_scanned ), array( 'id' => $scan_id ), array( '%d' ), array( '%d' ) );

				// Add delay to prevent firewall rate limiting (robotic attack false-positive mitigation)
				if ( $delay_ms > 0 && $pages_scanned < count( $urls ) ) {
					usleep( $delay_ms * 1000 );
				}
			}

			// Run scoring calculation
			$scoring = new Scoring_Engine();
			$scores  = $scoring->calculate_scores( $scan_id );

			// Mark scan as completed
			$wpdb->update(
				$table_scans,
				array(
					'status'               => 'completed',
					'composite_score'      => $scores['composite'],
					'subscore_crawlability'=> $scores['subscores']['crawlability'],
					'subscore_schema'      => $scores['subscores']['schema'],
					'subscore_content'     => $scores['subscores']['content'],
					'subscore_experience'  => $scores['subscores']['experience'],
					'completed_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $scan_id ),
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);

		} catch ( \Throwable $e ) {
			// Fail-safe: Mark scan as failed in DB if exception is thrown
			$wpdb->update(
				$table_scans,
				array( 'status' => 'failed' ),
				array( 'id' => $scan_id ),
				array( '%s' ),
				array( '%d' )
			);
			error_log( 'AI Visibility Scanner execution error: ' . $e->getMessage() );
		}

		// Prune old diagnostic records (keep last 10 scans)
		Diagnostics_Logger::prune_old_diagnostics();
	}
}
