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
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_url varchar(255) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			pages_scanned int(11) unsigned NOT NULL DEFAULT 0,
			pages_total int(11) unsigned NOT NULL DEFAULT 0,
			composite_score tinyint(3) unsigned NULL,
			subscore_crawlability tinyint(3) unsigned NULL,
			subscore_schema tinyint(3) unsigned NULL,
			subscore_content tinyint(3) unsigned NULL,
			subscore_experience tinyint(3) unsigned NULL,
			started_at datetime NOT NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status  (status)
		) {$charset_collate};";

		$sql_results = "CREATE TABLE {$table_results} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			page_url varchar(255) NOT NULL,
			check_slug varchar(100) NOT NULL,
			category varchar(50) NOT NULL,
			result varchar(10) NOT NULL,
			evidence text NULL,
			fix_hint text NULL,
			effort_score tinyint(3) unsigned NOT NULL DEFAULT 1,
			impact_score tinyint(3) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY scan_id  (scan_id),
			KEY category  (category),
			KEY result  (result)
		) {$charset_collate};";

		$sql_diagnostics = "CREATE TABLE {$table_diagnostics} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NULL,
			check_slug varchar(100) NULL,
			page_url varchar(255) NULL,
			fetch_strategy varchar(20) NOT NULL DEFAULT 'http_loopback',
			target_url varchar(255) NOT NULL,
			request_headers text NULL,
			response_http_code smallint(5) NULL,
			response_headers text NULL,
			response_time_ms int(11) unsigned NOT NULL DEFAULT 0,
			response_body_size_bytes int(11) unsigned NULL,
			response_body_snippet text NULL,
			error_type varchar(50) NOT NULL DEFAULT 'none',
			error_message text NULL,
			retry_count tinyint(3) unsigned NOT NULL DEFAULT 0,
			fallback_triggered tinyint(1) NOT NULL DEFAULT 0,
			fallback_from_diagnostic_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_id  (scan_id),
			KEY check_slug  (check_slug),
			KEY error_type  (error_type)
		) {$charset_collate};";

		$sql_environment = "CREATE TABLE {$table_environment} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			wp_version varchar(20) NOT NULL,
			php_version varchar(20) NOT NULL,
			active_theme varchar(100) NOT NULL,
			active_page_builders text NULL,
			active_security_plugins text NULL,
			active_cache_plugins text NULL,
			active_seo_plugins text NULL,
			cloudflare_detected tinyint(1) NOT NULL DEFAULT 0,
			server_software varchar(255) NULL,
			hosting_signature_guess varchar(100) NULL,
			loopback_connectivity varchar(20) NOT NULL DEFAULT 'not_tested',
			site_url_snapshot varchar(255) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY scan_id  (scan_id)
		) {$charset_collate};";

		try {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql_scans );
			dbDelta( $sql_results );
			dbDelta( $sql_diagnostics );
			dbDelta( $sql_environment );

			update_option( 'avs_db_version', self::DB_VERSION );
		} catch ( \Throwable $e ) {
			// Catch any DB exceptions to prevent fatal error on activation
			error_log( 'AI Visibility Scanner activation DB notice: ' . $e->getMessage() );
		}
	}
}
