<?php
namespace AIVisibilityScanner\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use AIVisibilityScanner\Scanner\Orchestrator;
use AIVisibilityScanner\Scanner\Diagnostics_Logger;
use AIVisibilityScanner\Scanner\Self_Test_Runner;
use AIVisibilityScanner\Scanner\Adhoc_Tester;
use AIVisibilityScanner\Report\Report_Builder;

/**
 * Custom REST API endpoints under avs/v1 namespace.
 */
class Rest_API extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'avs/v1';
		$this->rest_base = 'scans';
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Scans CRUD
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_scan' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_scan' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/report',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_report' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// Diagnostics Endpoints
		register_rest_route(
			$this->namespace,
			'/diagnostics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diagnostics' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/diagnostics/scans',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diagnostic_scans' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/diagnostics/selftest',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_selftest' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/diagnostics/adhoc',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_adhoc' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/diagnostics/compare',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'compare_scans' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/diagnostics/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_diagnostics' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Permission check wrapper.
	 */
	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Endpoint: POST /avs/v1/scans
	 */
	public function create_scan( WP_REST_Request $request ) {
		$orchestrator = new Orchestrator();
		$scan_id      = $orchestrator->start_scan();

		if ( is_wp_error( $scan_id ) ) {
			return $scan_id;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'scan_id' => $scan_id,
				'message' => __( 'Scan enqueued successfully.', 'ai-visibility-scanner' ),
			),
			201
		);
	}

	/**
	 * Endpoint: GET /avs/v1/scans/{id}
	 */
	public function get_scan( WP_REST_Request $request ) {
		$scan_id = (int) $request['id'];
		global $wpdb;

		$table_name = $wpdb->prefix . 'avs_scans';
		$scan       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $scan_id ) );

		if ( ! $scan ) {
			return new WP_Error( 'avs_scan_not_found', __( 'Scan not found', 'ai-visibility-scanner' ), array( 'status' => 404 ) );
		}

		$progress = $scan->pages_total > 0 ? round( ( $scan->pages_scanned / $scan->pages_total ) * 100 ) : 0;

		return new WP_REST_Response(
			array(
				'id'             => (int) $scan->id,
				'status'         => $scan->status,
				'pages_scanned'  => (int) $scan->pages_scanned,
				'pages_total'    => (int) $scan->pages_total,
				'progress'       => $progress,
				'composite_score'=> null !== $scan->composite_score ? (int) $scan->composite_score : null,
			),
			200
		);
	}

	/**
	 * Endpoint: GET /avs/v1/scans/{id}/report
	 */
	public function get_report( WP_REST_Request $request ) {
		$scan_id = (int) $request['id'];
		$report  = Report_Builder::get_report( $scan_id );

		if ( ! $report ) {
			return new WP_Error( 'avs_report_not_found', __( 'Report not found', 'ai-visibility-scanner' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $report, 200 );
	}

	/**
	 * Endpoint: GET /avs/v1/diagnostics/scans
	 */
	public function get_diagnostic_scans( WP_REST_Request $request ) {
		global $wpdb;
		$table_scans = $wpdb->prefix . 'avs_scans';

		$scans = $wpdb->get_results( "SELECT id, site_url, status, composite_score, started_at, completed_at FROM {$table_scans} ORDER BY id DESC LIMIT 30" );
		return new WP_REST_Response( array( 'success' => true, 'scans' => $scans ), 200 );
	}

	/**
	 * Endpoint: GET /avs/v1/diagnostics
	 */
	public function get_diagnostics( WP_REST_Request $request ) {
		$scan_id        = $request->get_param( 'scan_id' );
		$scan_id        = null !== $scan_id ? (int) $scan_id : null;
		$error_type     = $request->get_param( 'error_type' );
		$fetch_strategy = $request->get_param( 'fetch_strategy' );

		// If no scan_id requested, attempt to get the latest scan ID
		if ( null === $scan_id ) {
			global $wpdb;
			$table_scans = $wpdb->prefix . 'avs_scans';
			$latest_id   = $wpdb->get_var( "SELECT id FROM {$table_scans} ORDER BY id DESC LIMIT 1" );
			if ( $latest_id ) {
				$scan_id = (int) $latest_id;
			}
		}

		$environment = Diagnostics_Logger::get_environment( $scan_id );
		$diagnostics = Diagnostics_Logger::get_diagnostics(
			$scan_id,
			array(
				'error_type'     => $error_type,
				'fetch_strategy' => $fetch_strategy,
			)
		);

		// Verbosity hook for future public/private split (§7)
		$verbosity   = apply_filters( 'avs_diagnostics_verbosity', 'internal' );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'scan_id'     => $scan_id,
				'verbosity'   => $verbosity,
				'environment' => $environment,
				'diagnostics' => $diagnostics,
			),
			200
		);
	}

	/**
	 * Endpoint: POST /avs/v1/diagnostics/selftest
	 */
	public function run_selftest( WP_REST_Request $request ) {
		$runner = new Self_Test_Runner();
		$result = $runner->run();
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Endpoint: POST /avs/v1/diagnostics/adhoc
	 */
	public function run_adhoc( WP_REST_Request $request ) {
		$url    = $request->get_param( 'url' );
		$checks = $request->get_param( 'checks' );

		if ( empty( $url ) ) {
			return new WP_Error( 'avs_invalid_url', __( 'Please provide a valid URL.', 'ai-visibility-scanner' ), array( 'status' => 400 ) );
		}

		$tester = new Adhoc_Tester();
		$result = $tester->run( $url, $checks ? $checks : 'all' );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Endpoint: GET /avs/v1/diagnostics/compare
	 */
	public function compare_scans( WP_REST_Request $request ) {
		$scan_id_1 = (int) $request->get_param( 'scan_id_1' );
		$scan_id_2 = (int) $request->get_param( 'scan_id_2' );

		if ( ! $scan_id_1 || ! $scan_id_2 ) {
			return new WP_Error( 'avs_invalid_scans', __( 'Please provide scan_id_1 and scan_id_2 parameters.', 'ai-visibility-scanner' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$table_results = $wpdb->prefix . 'avs_check_results';

		$results_1 = $wpdb->get_results( $wpdb->prepare( "SELECT page_url, check_slug, result, evidence, fix_hint FROM {$table_results} WHERE scan_id = %d", $scan_id_1 ) );
		$results_2 = $wpdb->get_results( $wpdb->prepare( "SELECT page_url, check_slug, result, evidence, fix_hint FROM {$table_results} WHERE scan_id = %d", $scan_id_2 ) );

		$map_1 = array();
		foreach ( $results_1 as $r ) {
			$key = $r->page_url . '|' . $r->check_slug;
			$map_1[ $key ] = $r;
		}

		$map_2 = array();
		foreach ( $results_2 as $r ) {
			$key = $r->page_url . '|' . $r->check_slug;
			$map_2[ $key ] = $r;
		}

		$all_keys = array_unique( array_merge( array_keys( $map_1 ), array_keys( $map_2 ) ) );
		$diffs    = array();

		foreach ( $all_keys as $key ) {
			$item_1 = isset( $map_1[ $key ] ) ? $map_1[ $key ] : null;
			$item_2 = isset( $map_2[ $key ] ) ? $map_2[ $key ] : null;

			$status_1 = $item_1 ? $item_1->result : 'missing';
			$status_2 = $item_2 ? $item_2->result : 'missing';

			if ( $status_1 !== $status_2 ) {
				list( $page_url, $check_slug ) = explode( '|', $key );
				$change_type = 'changed';

				if ( 'pass' !== $status_1 && 'pass' === $status_2 ) {
					$change_type = 'resolved'; // Fixed!
				} elseif ( 'pass' === $status_1 && 'pass' !== $status_2 ) {
					$change_type = 'regressed'; // New issue!
				} elseif ( 'missing' === $status_1 ) {
					$change_type = 'new_check';
				} elseif ( 'missing' === $status_2 ) {
					$change_type = 'removed_check';
				}

				$diffs[] = array(
					'key'         => $key,
					'page_url'    => $page_url,
					'check_slug'  => $check_slug,
					'change_type' => $change_type,
					'scan_1'      => $item_1 ? array( 'result' => $item_1->result, 'evidence' => $item_1->evidence ) : null,
					'scan_2'      => $item_2 ? array( 'result' => $item_2->result, 'evidence' => $item_2->evidence ) : null,
				);
			}
		}

		$env_1 = Diagnostics_Logger::get_environment( $scan_id_1 );
		$env_2 = Diagnostics_Logger::get_environment( $scan_id_2 );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'scan_id_1'   => $scan_id_1,
				'scan_id_2'   => $scan_id_2,
				'env_1'       => $env_1,
				'env_2'       => $env_2,
				'total_diffs' => count( $diffs ),
				'diffs'       => $diffs,
			),
			200
		);
	}

	/**
	 * Endpoint: GET /avs/v1/diagnostics/export
	 */
	public function export_diagnostics( WP_REST_Request $request ) {
		$scan_id = (int) $request->get_param( 'scan_id' );
		if ( ! $scan_id ) {
			return new WP_Error( 'avs_missing_scan_id', __( 'Missing scan_id parameter.', 'ai-visibility-scanner' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$table_scans = $wpdb->prefix . 'avs_scans';
		$scan        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_scans} WHERE id = %d", $scan_id ) );

		$environment = Diagnostics_Logger::get_environment( $scan_id );
		$diagnostics = Diagnostics_Logger::get_diagnostics( $scan_id );

		$export_data = array(
			'plugin_version' => AVS_VERSION,
			'exported_at'    => current_time( 'mysql' ),
			'scan'           => $scan,
			'environment'    => $environment,
			'diagnostics'    => $diagnostics,
		);

		return new WP_REST_Response( $export_data, 200 );
	}
}
