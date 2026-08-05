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

		register_rest_route(
			$this->namespace,
			'/pages/(?P<post_id>[\d]+)/report',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_page_report' ),
					'permission_callback' => array( $this, 'edit_post_permission_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/pages/(?P<post_id>[\d]+)/analyze',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'analyze_page' ),
					'permission_callback' => array( $this, 'edit_post_permission_check' ),
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

		// Auto-fail stale/crashed scans running for more than 5 minutes
		if ( in_array( $scan->status, array( 'queued', 'running' ), true ) ) {
			$started_time = strtotime( $scan->started_at );
			$current_time = current_time( 'timestamp' );
			if ( $started_time && ( $current_time - $started_time ) > 300 ) { // 5 minutes
				$wpdb->update(
					$table_name,
					array( 'status' => 'failed' ),
					array( 'id' => $scan_id ),
					array( '%s' ),
					array( '%d' )
				);
				$scan->status = 'failed';
			}
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

	/**
	 * Permission check for individual post editing capabilities.
	 */
	public function edit_post_permission_check( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		if ( ! $post_id ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Endpoint: GET /avs/v1/pages/{post_id}/report
	 */
	public function get_page_report( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		global $wpdb;

		$score      = get_post_meta( $post_id, '_avs_score', true );
		$scan_id    = get_post_meta( $post_id, '_avs_score_scan_id', true );
		$updated_at = get_post_meta( $post_id, '_avs_score_updated_at', true );
		$summary    = get_post_meta( $post_id, '_avs_issue_summary', true );
		$top_issue  = get_post_meta( $post_id, '_avs_top_issue', true );

		if ( '' === $score || false === $score || ! $updated_at ) {
			return new WP_REST_Response( array( 'scanned' => false ), 200 );
		}

		$table_results = $wpdb->prefix . 'avs_check_results';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT check_slug, category, result, evidence, fix_hint FROM {$table_results} WHERE post_id = %d AND scan_id = %d",
				$post_id,
				$scan_id
			)
		);

		$per_page_check_slugs = array(
			'schema_presence',
			'schema_validity',
			'schema_product_validity',
			'schema_localbusiness_validity',
			'schema_review_validity',
			'heading_hierarchy',
			'meta_description',
			'faq_howto_opportunity',
		);

		$checklist = array();
		foreach ( $results as $row ) {
			if ( in_array( $row->check_slug, $per_page_check_slugs, true ) ) {
				$checklist[] = array(
					'slug'     => $row->check_slug,
					'category' => $row->category,
					'result'   => $row->result,
					'evidence' => $row->evidence,
					'fix_hint' => $row->fix_hint,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'scanned'    => true,
				'score'      => (int) $score,
				'updated_at' => $updated_at,
				'summary'    => json_decode( $summary, true ),
				'top_issue'  => $top_issue,
				'results'    => $checklist,
			),
			200
		);
	}

	/**
	 * Endpoint: POST /avs/v1/pages/{post_id}/analyze
	 */
	public function analyze_page( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'avs_post_not_found', __( 'Post not found.', 'ai-visibility-scanner' ), array( 'status' => 404 ) );
		}

		// Get latest scan ID to associate with the check results
		$table_scans   = $wpdb->prefix . 'avs_scans';
		$table_results = $wpdb->prefix . 'avs_check_results';

		$scan_id = (int) $wpdb->get_var( "SELECT id FROM {$table_scans} ORDER BY id DESC LIMIT 1" );
		if ( ! $scan_id ) {
			$scan_id = 0;
		}

		$page_url = get_permalink( $post_id );

		// Run per-page checks using Strategy 1 (in-process render)
		$fetcher      = new \AIVisibilityScanner\Scanner\Page_Fetcher( $scan_id );
		$html_body    = $fetcher->fetch_in_process( $page_url );

		$registry     = new \AIVisibilityScanner\Scanner\Checks\Check_Registry();
		$checks       = $registry->get_checks();
		$crawler      = new \AIVisibilityScanner\Scanner\Crawler();
		$site_context = $crawler->get_site_context();

		$site_class                   = \AIVisibilityScanner\Scanner\Classifier::classify_site();
		$site_context['is_local_business']        = $site_class['is_local_business'];
		$site_context['local_business_suggested'] = $site_class['local_business_suggested'];
		$page_context               = $site_context;
		$page_context['classifier'] = \AIVisibilityScanner\Scanner\Classifier::classify_page( $page_url, $html_body, $site_context );

		$per_page_check_slugs = array(
			'schema_presence',
			'schema_validity',
			'schema_product_validity',
			'schema_localbusiness_validity',
			'schema_review_validity',
			'heading_hierarchy',
			'meta_description',
			'faq_howto_opportunity',
		);

		// Delete old results for these checks on this post/page under current scan ID
		$placeholders = implode( ',', array_fill( 0, count( $per_page_check_slugs ), '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_results} WHERE post_id = %d AND scan_id = %d AND check_slug IN ({$placeholders})",
				array_merge( array( $post_id, $scan_id ), $per_page_check_slugs )
			)
		);

		$checklist = array();

		foreach ( $checks as $check ) {
			if ( in_array( $check->get_slug(), $per_page_check_slugs, true ) ) {
				if ( method_exists( $check, 'is_applicable' ) && ! $check->is_applicable( $page_context ) ) {
					$result_obj = new \AIVisibilityScanner\Scanner\Checks\Check_Result(
						$check->get_slug(),
						$check->get_category(),
						'skipped',
						'Check skipped: Not applicable to this page or site context.',
						'',
						0,
						0
					);
				} else {
					$result_obj = $check->run( $page_url, $html_body, $page_context );
				}

				$wpdb->insert(
					$table_results,
					array(
						'scan_id'      => $scan_id,
						'post_id'      => $post_id,
						'page_url'     => $page_url,
						'check_slug'   => $result_obj->slug,
						'category'     => $result_obj->category,
						'result'       => $result_obj->result,
						'evidence'     => $result_obj->evidence,
						'fix_hint'     => $result_obj->fix_hint,
						'effort_score' => $result_obj->effort_score,
						'impact_score' => $result_obj->impact_score,
					),
					array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
				);

				$checklist[] = array(
					'slug'     => $result_obj->slug,
					'category' => $result_obj->category,
					'result'   => $result_obj->result,
					'evidence' => $result_obj->evidence,
					'fix_hint' => $result_obj->fix_hint,
				);
			}
		}

		// Calculate page score and save postmeta
		$scoring_engine  = new \AIVisibilityScanner\Scoring\Scoring_Engine();
		$page_score_data = $scoring_engine->calculate_page_score( $scan_id, $post_id );

		$page_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT result, evidence, effort_score, impact_score FROM {$table_results} WHERE scan_id = %d AND post_id = %d",
				$scan_id,
				$post_id
			)
		);

		$counts = array( 'fail' => 0, 'warn' => 0, 'pass' => 0 );
		$unresolved = array();

		foreach ( $page_results as $row ) {
			$res = $row->result;
			if ( isset( $counts[ $res ] ) ) {
				$counts[ $res ]++;
			}

			if ( 'pass' !== $res ) {
				$ratio = $row->effort_score > 0 ? ( $row->impact_score / $row->effort_score ) : $row->impact_score;
				$unresolved[] = array(
					'evidence' => $row->evidence,
					'ratio'    => $ratio,
				);
			}
		}

		usort( $unresolved, function( $a, $b ) {
			if ( $a['ratio'] === $b['ratio'] ) {
				return 0;
			}
			return ( $a['ratio'] > $b['ratio'] ) ? -1 : 1;
		} );

		$top_issue = '';
		if ( ! empty( $unresolved ) ) {
			$top_issue = $unresolved[0]['evidence'];
		}

		update_post_meta( $post_id, '_avs_score', (int) $page_score_data['composite'] );
		update_post_meta( $post_id, '_avs_score_scan_id', (int) $scan_id );
		update_post_meta( $post_id, '_avs_score_updated_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_avs_issue_summary', wp_json_encode( $counts ) );
		update_post_meta( $post_id, '_avs_top_issue', $top_issue );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'score'      => (int) $page_score_data['composite'],
				'updated_at' => current_time( 'mysql' ),
				'summary'    => $counts,
				'top_issue'  => $top_issue,
				'results'    => $checklist,
			),
			200
		);
	}
}
