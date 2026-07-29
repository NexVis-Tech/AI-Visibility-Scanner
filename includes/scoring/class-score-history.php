<?php
namespace AIVisibilityScanner\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Score History query helper.
 */
class Score_History {

	/**
	 * Retrieve latest scan record.
	 *
	 * @return object|null
	 */
	public static function get_latest_scan() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'avs_scans';
		return $wpdb->get_row( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 1" );
	}

	/**
	 * Retrieve recent scan records.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent_scans( int $limit = 5 ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'avs_scans';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", $limit ) );
	}
}
