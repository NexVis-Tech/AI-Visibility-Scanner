<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Crawler;
use AIVisibilityScanner\Scanner\Page_Fetcher;
use AIVisibilityScanner\Scanner\Checks\Check_Registry;

/**
 * Runs targeted check(s) against a single page URL in real-time for fast developer iteration.
 */
class Adhoc_Tester {

	/**
	 * Run ad-hoc check(s) against a target URL.
	 *
	 * @param string      $url Target page URL
	 * @param string|array $checks Check slug or array of check slugs, or 'all'
	 * @return array
	 */
	public function run( string $url, $check_slugs = 'all' ): array {
		$url = esc_url_raw( trim( $url ) );
		if ( empty( $url ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid URL provided.', 'ai-visibility-scanner' ),
			);
		}

		$crawler      = new Crawler();
		$fetcher      = new Page_Fetcher( null ); // Ad-hoc runs don't require a full scan_id
		$registry     = new Check_Registry();
		$all_checks   = $registry->get_checks();
		$site_context = $crawler->get_site_context();

		// Filter checks to run
		$checks_to_run = array();
		if ( 'all' === $check_slugs || empty( $check_slugs ) ) {
			$checks_to_run = $all_checks;
		} else {
			$target_slugs = is_array( $check_slugs ) ? $check_slugs : array( $check_slugs );
			foreach ( $all_checks as $check ) {
				if ( in_array( $check->slug, $target_slugs, true ) ) {
					$checks_to_run[] = $check;
				}
			}
		}

		if ( empty( $checks_to_run ) ) {
			return array(
				'success' => false,
				'message' => __( 'No valid checks selected.', 'ai-visibility-scanner' ),
			);
		}

		$results = array();

		foreach ( $checks_to_run as $check ) {
			$html_body  = $fetcher->fetch_in_process( $url, 'adhoc_' . $check->slug );
			$result_obj = $check->run( $url, $html_body, $site_context );

			$results[] = array(
				'slug'         => $result_obj->slug,
				'title'        => $result_obj->title,
				'category'     => $result_obj->category,
				'result'       => $result_obj->result,
				'evidence'     => $result_obj->evidence,
				'fix_hint'     => $result_obj->fix_hint,
				'effort_score' => $result_obj->effort_score,
				'impact_score' => $result_obj->impact_score,
			);
		}

		// Fetch recent diagnostic entries for this adhoc run
		global $wpdb;
		$table_diag = $wpdb->prefix . 'avs_scan_diagnostics';
		$recent_diagnostics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_diag} WHERE page_url = %s ORDER BY id DESC LIMIT %d",
				$url,
				count( $checks_to_run ) * 2
			)
		);

		return array(
			'success'     => true,
			'url'         => $url,
			'timestamp'   => current_time( 'mysql' ),
			'results'     => $results,
			'diagnostics' => $recent_diagnostics,
		);
	}
}
