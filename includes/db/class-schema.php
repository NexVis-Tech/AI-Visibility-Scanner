<?php
namespace AIVisibilityScanner\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom database table creation using dbDelta().
 */
class Schema {

	const DB_VERSION = '1.1.0';

	/**
	 * Create or update database schema.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_scans       = $wpdb->prefix . 'avs_scans';
		$table_results     = $wpdb->prefix . 'avs_check_results';
		$table_diagnostics = $wpdb->prefix . 'avs_scan_diagnostics';
		$table_environment = $wpdb->prefix . 'avs_scan_environment';

		$sql_scans = "CREATE TABLE {$table_scans} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_url VARCHAR(255) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			pages_scanned INT UNSIGNED NOT NULL DEFAULT 0,
			pages_total INT UNSIGNED NOT NULL DEFAULT 0,
			composite_score TINYINT UNSIGNED NULL,
			subscore_crawlability TINYINT UNSIGNED NULL,
			subscore_schema TINYINT UNSIGNED NULL,
			subscore_content TINYINT UNSIGNED NULL,
			subscore_experience TINYINT UNSIGNED NULL,
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		$sql_results = "CREATE TABLE {$table_results} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT UNSIGNED NOT NULL,
			page_url VARCHAR(255) NOT NULL,
			check_slug VARCHAR(100) NOT NULL,
			category VARCHAR(50) NOT NULL,
			result VARCHAR(10) NOT NULL,
			evidence TEXT NULL,
			fix_hint TEXT NULL,
			effort_score TINYINT UNSIGNED NOT NULL DEFAULT 1,
			impact_score TINYINT UNSIGNED NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY category (category),
			KEY result (result)
		) {$charset_collate};";

		$sql_diagnostics = "CREATE TABLE {$table_diagnostics} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT UNSIGNED NULL,
			check_slug VARCHAR(100) NULL,
			page_url VARCHAR(255) NULL,
			fetch_strategy ENUM('internal_render','http_loopback','http_external') NOT NULL DEFAULT 'http_loopback',
			target_url VARCHAR(255) NOT NULL,
			request_headers TEXT NULL,
			response_http_code SMALLINT NULL,
			response_headers TEXT NULL,
			response_time_ms INT UNSIGNED NOT NULL DEFAULT 0,
			response_body_size_bytes INT UNSIGNED NULL,
			response_body_snippet TEXT NULL,
			error_type ENUM('none','timeout','dns_failure','connection_refused','ssl_error','http_error','cloudflare_challenge','waf_block_suspected','unknown') NOT NULL DEFAULT 'none',
			error_message TEXT NULL,
			retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
			fallback_triggered BOOLEAN NOT NULL DEFAULT FALSE,
			fallback_from_diagnostic_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY check_slug (check_slug),
			KEY error_type (error_type)
		) {$charset_collate};";

		$sql_environment = "CREATE TABLE {$table_environment} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_id BIGINT UNSIGNED NOT NULL,
			wp_version VARCHAR(20) NOT NULL,
			php_version VARCHAR(20) NOT NULL,
			active_theme VARCHAR(100) NOT NULL,
			active_page_builders TEXT NULL,
			active_security_plugins TEXT NULL,
			active_cache_plugins TEXT NULL,
			active_seo_plugins TEXT NULL,
			cloudflare_detected BOOLEAN NOT NULL DEFAULT FALSE,
			server_software VARCHAR(255) NULL,
			hosting_signature_guess VARCHAR(100) NULL,
			loopback_connectivity ENUM('ok','failed','not_tested') NOT NULL DEFAULT 'not_tested',
			site_url_snapshot VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY scan_id (scan_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_scans );
		dbDelta( $sql_results );
		dbDelta( $sql_diagnostics );
		dbDelta( $sql_environment );

		update_option( 'avs_db_version', self::DB_VERSION );
	}
}
