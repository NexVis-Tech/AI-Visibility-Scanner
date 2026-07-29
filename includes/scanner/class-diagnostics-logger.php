<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database persistence and retrieval for scan diagnostics and environment records.
 */
class Diagnostics_Logger {

	/**
	 * Log scan environment fingerprint.
	 *
	 * @param int   $scan_id
	 * @param array $env_data
	 * @return int|bool Insert ID or false
	 */
	public static function log_environment( int $scan_id, array $env_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'avs_scan_environment';

		$data = array_merge(
			array( 'scan_id' => $scan_id ),
			$env_data
		);

		// Use replace to overwrite if scan_id exists
		$inserted = $wpdb->replace(
			$table,
			$data,
			array(
				'%d', // scan_id
				'%s', // wp_version
				'%s', // php_version
				'%s', // active_theme
				'%s', // active_page_builders
				'%s', // active_security_plugins
				'%s', // active_cache_plugins
				'%s', // active_seo_plugins
				'%d', // cloudflare_detected
				'%s', // server_software
				'%s', // hosting_signature_guess
				'%s', // loopback_connectivity
				'%s', // site_url_snapshot
				'%s', // created_at
			)
		);

		return $inserted !== false ? $wpdb->insert_id : false;
	}

	/**
	 * Log a fetch attempt diagnostic entry.
	 *
	 * @param array $entry
	 * @return int|bool Diagnostic row ID or false
	 */
	public static function log_fetch( array $entry ) {
		global $wpdb;
		$table = $wpdb->prefix . 'avs_scan_diagnostics';

		$defaults = array(
			'scan_id'                     => null,
			'check_slug'                  => null,
			'page_url'                    => null,
			'fetch_strategy'              => 'http_loopback',
			'target_url'                  => get_site_url(),
			'request_headers'             => null,
			'response_http_code'          => null,
			'response_headers'            => null,
			'response_time_ms'            => 0,
			'response_body_size_bytes'    => null,
			'response_body_snippet'       => null,
			'error_type'                  => 'none',
			'error_message'               => null,
			'retry_count'                 => 0,
			'fallback_triggered'          => 0,
			'fallback_from_diagnostic_id' => null,
			'created_at'                  => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $entry, $defaults );

		// Ensure headers are JSON encoded strings
		if ( is_array( $data['request_headers'] ) ) {
			$data['request_headers'] = wp_json_encode( $data['request_headers'] );
		}
		if ( is_array( $data['response_headers'] ) ) {
			$data['response_headers'] = wp_json_encode( $data['response_headers'] );
		}

		// Truncate response_body_snippet to ~2000 chars if present
		if ( null !== $data['response_body_snippet'] && strlen( $data['response_body_snippet'] ) > 2000 ) {
			$data['response_body_snippet'] = substr( $data['response_body_snippet'], 0, 2000 ) . '... [TRUNCATED]';
		}

		$inserted = $wpdb->insert(
			$table,
			$data,
			array(
				'%d', // scan_id
				'%s', // check_slug
				'%s', // page_url
				'%s', // fetch_strategy
				'%s', // target_url
				'%s', // request_headers
				'%d', // response_http_code
				'%s', // response_headers
				'%d', // response_time_ms
				'%d', // response_body_size_bytes
				'%s', // response_body_snippet
				'%s', // error_type
				'%s', // error_message
				'%d', // retry_count
				'%d', // fallback_triggered
				'%d', // fallback_from_diagnostic_id
				'%s', // created_at
			)
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Prune old diagnostic logs, keeping only the last 10 scans' worth of entries.
	 */
	public static function prune_old_diagnostics() {
		global $wpdb;
		$table_scans       = $wpdb->prefix . 'avs_scans';
		$table_diagnostics = $wpdb->prefix . 'avs_scan_diagnostics';

		// Get the IDs of the last 10 scans
		$recent_scan_ids = $wpdb->get_col( "SELECT id FROM {$table_scans} ORDER BY id DESC LIMIT 10" );

		if ( empty( $recent_scan_ids ) ) {
			return;
		}

		$ids_placeholder = implode( ',', array_map( 'intval', $recent_scan_ids ) );

		// Delete diagnostics for scans older than the last 10 (excluding standalone self-tests with scan_id NULL within last 24h)
		$wpdb->query(
			"DELETE FROM {$table_diagnostics} 
			 WHERE (scan_id IS NOT NULL AND scan_id NOT IN ({$ids_placeholder}))
			    OR (scan_id IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY))"
		);
	}

	/**
	 * Get environment record for a given scan ID or the latest scan.
	 *
	 * @param int|null $scan_id
	 * @return object|null
	 */
	public static function get_environment( $scan_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'avs_scan_environment';

		if ( $scan_id ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE scan_id = %d", $scan_id ) );
		}

		return $wpdb->get_row( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 1" );
	}

	/**
	 * Get diagnostic logs for a given scan ID.
	 *
	 * @param int|null $scan_id
	 * @param array    $filters Optional filters (error_type, fetch_strategy)
	 * @return array
	 */
	public static function get_diagnostics( $scan_id = null, array $filters = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'avs_scan_diagnostics';

		$where = array();
		$params = array();

		if ( null !== $scan_id ) {
			$where[]  = 'scan_id = %d';
			$params[] = $scan_id;
		} else {
			// Default to standalone self-tests or recent scans if no scan_id given
			$where[] = 'scan_id IS NULL';
		}

		if ( ! empty( $filters['error_type'] ) ) {
			if ( 'issues_only' === $filters['error_type'] ) {
				$where[] = "error_type != 'none'";
			} else {
				$where[]  = 'error_type = %s';
				$params[] = sanitize_text_field( $filters['error_type'] );
			}
		}

		if ( ! empty( $filters['fetch_strategy'] ) ) {
			$where[]  = 'fetch_strategy = %s';
			$params[] = sanitize_text_field( $filters['fetch_strategy'] );
		}

		$where_clause = implode( ' AND ', $where );
		$sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY id ASC";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return $wpdb->get_results( $sql );
	}
}
