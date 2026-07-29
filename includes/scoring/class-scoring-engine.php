<?php
namespace AIVisibilityScanner\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes category subscores and composite AI Visibility Index.
 */
class Scoring_Engine {

	/**
	 * Compute scores for a completed scan ID.
	 *
	 * @param int $scan_id
	 * @return array
	 */
	public function calculate_scores( int $scan_id ): array {
		global $wpdb;
		$table_results = $wpdb->prefix . 'avs_check_results';

		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT category, result FROM {$table_results} WHERE scan_id = %d", $scan_id )
		);

		$categories = array(
			'crawlability' => array( 'points' => 0, 'total' => 0 ),
			'schema'       => array( 'points' => 0, 'total' => 0 ),
			'content'      => array( 'points' => 0, 'total' => 0 ),
			'experience'   => array( 'points' => 0, 'total' => 0 ),
		);

		foreach ( $results as $row ) {
			$cat = strtolower( $row->category );
			if ( ! isset( $categories[ $cat ] ) ) {
				$categories[ $cat ] = array( 'points' => 0, 'total' => 0 );
			}

			$pts = ('pass' === $row->result) ? 1.0 : ( ('warn' === $row->result) ? 0.5 : 0.0 );
			$categories[ $cat ]['points'] += $pts;
			$categories[ $cat ]['total']  += 1.0;
		}

		$subscores = array();
		foreach ( $categories as $cat_key => $data ) {
			$subscores[ $cat_key ] = $data['total'] > 0 ? (int) round( ( $data['points'] / $data['total'] ) * 100 ) : 100;
		}

		// Category weights
		$default_weights = array(
			'crawlability' => 0.30,
			'schema'       => 0.30,
			'content'      => 0.30,
			'experience'   => 0.10,
		);

		$weights   = apply_filters( 'avs_category_weights', $default_weights );
		$composite = 0;

		foreach ( $subscores as $cat_key => $score ) {
			$weight    = $weights[ $cat_key ] ?? 0.25;
			$composite += $score * $weight;
		}

		return array(
			'subscores' => $subscores,
			'composite' => (int) round( $composite ),
		);
	}
}
