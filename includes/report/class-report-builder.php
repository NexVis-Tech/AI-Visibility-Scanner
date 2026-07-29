<?php
namespace AIVisibilityScanner\Report;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scoring\Score_History;

/**
 * Assembles raw scan & check database rows into view model payloads.
 */
class Report_Builder {

	/**
	 * Build full report array for a scan ID.
	 *
	 * @param int $scan_id
	 * @return array|null
	 */
	public static function get_report( int $scan_id = 0 ): ?array {
		global $wpdb;
		$table_scans   = $wpdb->prefix . 'avs_scans';
		$table_results = $wpdb->prefix . 'avs_check_results';

		if ( $scan_id <= 0 ) {
			$latest  = Score_History::get_latest_scan();
			$scan_id = $latest ? (int) $latest->id : 0;
		}

		if ( $scan_id <= 0 ) {
			return null;
		}

		$scan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_scans} WHERE id = %d", $scan_id ) );
		if ( ! $scan ) {
			return null;
		}

		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table_results} WHERE scan_id = %d ORDER BY id ASC", $scan_id )
		);

		// Prioritized Fixes: Filter fail/warn and sort by impact / effort DESC
		$fixes        = array();
		$total_pass   = 0;
		$total_warn   = 0;
		$total_fail   = 0;
		$pages_map    = array();

		foreach ( $results as $row ) {
			if ( 'pass' === $row->result ) {
				$total_pass++;
			} elseif ( 'warn' === $row->result ) {
				$total_warn++;
			} elseif ( 'fail' === $row->result ) {
				$total_fail++;
			}

			if ( 'pass' !== $row->result ) {
				$row->ratio = $row->effort_score > 0 ? ( $row->impact_score / $row->effort_score ) : $row->impact_score;
				$fixes[]    = $row;
			}

			// Group by Page URL for Page-by-Page Audit tab
			$url = $row->page_url ? $row->page_url : __( 'Global / Site-wide', 'ai-visibility-scanner' );
			if ( ! isset( $pages_map[ $url ] ) ) {
				$pages_map[ $url ] = array(
					'url'        => $url,
					'pass_count' => 0,
					'warn_count' => 0,
					'fail_count' => 0,
					'results'    => array(),
				);
			}
			$pages_map[ $url ]['results'][] = $row;
			if ( 'pass' === $row->result ) {
				$pages_map[ $url ]['pass_count']++;
			} elseif ( 'warn' === $row->result ) {
				$pages_map[ $url ]['warn_count']++;
			} elseif ( 'fail' === $row->result ) {
				$pages_map[ $url ]['fail_count']++;
			}
		}

		usort( $fixes, function( $a, $b ) {
			if ( $a->ratio === $b->ratio ) {
				return 0;
			}
			return ( $a->ratio > $b->ratio ) ? -1 : 1;
		} );

		// Calculate per-page scores
		foreach ( $pages_map as $url => &$pdata ) {
			$p_total = count( $pdata['results'] );
			if ( $p_total > 0 ) {
				$p_score = (int) round( ( ( $pdata['pass_count'] * 100 ) + ( $pdata['warn_count'] * 50 ) ) / $p_total );
				$pdata['score'] = min( 100, max( 0, $p_score ) );
			} else {
				$pdata['score'] = 100;
			}
		}
		unset( $pdata );

		return array(
			'scan_id'          => (int) $scan->id,
			'site_url'         => $scan->site_url,
			'status'           => $scan->status,
			'pages_scanned'    => (int) $scan->pages_scanned,
			'pages_total'      => (int) $scan->pages_total,
			'composite_score'  => (int) $scan->composite_score,
			'subscores'        => array(
				'crawlability' => (int) $scan->subscore_crawlability,
				'schema'       => (int) $scan->subscore_schema,
				'content'      => (int) $scan->subscore_content,
				'experience'   => (int) $scan->subscore_experience,
			),
			'summary_counts'   => array(
				'total_checks' => count( $results ),
				'pass'         => $total_pass,
				'warn'         => $total_warn,
				'fail'         => $total_fail,
			),
			'started_at'       => $scan->started_at,
			'completed_at'     => $scan->completed_at,
			'prioritized_fixes'=> $fixes,
			'pages_map'        => $pages_map,
			'results'          => $results,
		);
	}
}
