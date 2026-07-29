<?php
/**
 * Fired when the plugin is uninstalled.
 * Drops custom tables and removes options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables
$table_scans   = $wpdb->prefix . 'avs_scans';
$table_results = $wpdb->prefix . 'avs_check_results';

$wpdb->query( "DROP TABLE IF EXISTS {$table_results}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table_scans}" );   // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete plugin options
delete_option( 'avs_settings' );
delete_option( 'avs_db_version' );
delete_site_option( 'avs_settings' );
