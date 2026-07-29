<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Strategy 1 (In-Process Output Rendering) and Strategy 2 (Loopback HTTP Fetching)
 * with full diagnostic logging integration.
 */
class Page_Fetcher {

	/**
	 * Active scan ID context for diagnostic logging.
	 * @var int|null
	 */
	private $scan_id = null;

	/**
	 * Constructor.
	 *
	 * @param int|null $scan_id
	 */
	public function __construct( $scan_id = null ) {
		$this->scan_id = $scan_id ? (int) $scan_id : null;
	}

	/**
	 * Set current scan context.
	 *
	 * @param int $scan_id
	 */
	public function set_scan_id( int $scan_id ) {
		$this->scan_id = $scan_id;
	}

	/**
	 * Fetch page HTML using Strategy 1 (In-process rendering).
	 * Bypasses firewalls, CDN, WAF, and network limits.
	 *
	 * @param string      $page_url
	 * @param string|null $check_slug
	 * @return string HTML body
	 */
	public function fetch_in_process( string $page_url, string $check_slug = null ): string {
		$start_time = microtime( true );
		$post_id    = url_to_postid( $page_url );
		$errors     = array();

		// Scoped error handler to capture PHP warnings/notices during rendering
		set_error_handler( function( $errno, $errstr, $errfile, $errline ) use ( &$errors ) {
			$errors[] = "PHP {$errno}: {$errstr} in {$errfile} on line {$errline}";
			return false;
		} );

		$html_body = '';
		$error_msg = null;
		$error_type = 'none';

		try {
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( $post ) {
					setup_postdata( $post );
					$content   = apply_filters( 'the_content', $post->post_content );
					wp_reset_postdata();

					$title     = get_the_title( $post );
					$html_body = '<html><head><title>' . esc_html( $title ) . '</title></head><body>' . $content . '</body></html>';
				}
			}
		} catch ( \Throwable $e ) {
			$error_msg  = $e->getMessage();
			$error_type = 'http_error';
		} finally {
			restore_error_handler();
		}

		$elapsed_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( ! empty( $html_body ) ) {
			if ( ! empty( $errors ) && ! $error_msg ) {
				$error_msg = implode( ' | ', array_slice( $errors, 0, 3 ) );
			}

			Diagnostics_Logger::log_fetch( array(
				'scan_id'                  => $this->scan_id,
				'check_slug'               => $check_slug,
				'page_url'                 => $page_url,
				'fetch_strategy'           => 'internal_render',
				'target_url'               => $page_url,
				'request_headers'          => array( 'Strategy' => 'Strategy 1 (In-Process Output Rendering)' ),
				'response_http_code'       => 200,
				'response_headers'         => array( 'Content-Type' => 'text/html; charset=UTF-8' ),
				'response_time_ms'         => $elapsed_ms,
				'response_body_size_bytes' => strlen( $html_body ),
				'response_body_snippet'    => substr( $html_body, 0, 2000 ),
				'error_type'               => $error_type,
				'error_message'            => $error_msg,
			) );

			return $html_body;
		}

		// Fallback to Strategy 2 (Loopback HTTP) if in-process lookup returns empty
		$primary_diag_id = Diagnostics_Logger::log_fetch( array(
			'scan_id'                  => $this->scan_id,
			'check_slug'               => $check_slug,
			'page_url'                 => $page_url,
			'fetch_strategy'           => 'internal_render',
			'target_url'               => $page_url,
			'request_headers'          => array( 'Strategy' => 'Strategy 1 (In-Process Output Rendering)' ),
			'response_http_code'       => null,
			'response_headers'         => array(),
			'response_time_ms'         => $elapsed_ms,
			'response_body_size_bytes' => 0,
			'response_body_snippet'    => null,
			'error_type'               => 'unknown',
			'error_message'            => 'In-process post lookup yielded empty content. Falling back to HTTP Loopback.',
		) );

		$loopback = $this->fetch_loopback_http( $page_url, $check_slug, true, $primary_diag_id );
		return $loopback['body'];
	}

	/**
	 * Fetch content over Strategy 2 (Loopback HTTP Request).
	 *
	 * @param string      $url
	 * @param string|null $check_slug
	 * @param bool        $is_fallback
	 * @param int|null    $fallback_from_id
	 * @return array Array containing 'body', 'headers', 'status', and 'diagnostic_id'
	 */
	public function fetch_loopback_http( string $url, string $check_slug = null, bool $is_fallback = false, $fallback_from_id = null ): array {
		$start_time = microtime( true );

		$user_agent = 'AIVisibilityScanner/' . AVS_VERSION . ' (WordPress; +https://nexvistech.com)';
		$request_headers = array(
			'User-Agent' => $user_agent,
			'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'X-AVS-Mode' => 'Loopback',
		);

		$args = array(
			'timeout'     => 15,
			'redirection' => 5,
			'user-agent'  => $user_agent,
			'headers'     => $request_headers,
			'sslverify'   => false,
		);

		$response   = wp_remote_get( $url, $args );

		// If loopback to public URL fails (common in Docker/Local dev where host port like 8080 is mapped externally, but internal webserver listens on port 80), fallback to 127.0.0.1 with Host header per Spec §11.1
		if ( is_wp_error( $response ) ) {
			$parsed = parse_url( $url );
			if ( ! empty( $parsed['host'] ) ) {
				$host_header   = $parsed['host'] . ( ! empty( $parsed['port'] ) ? ':' . $parsed['port'] : '' );
				$internal_path = ( ! empty( $parsed['path'] ) ? $parsed['path'] : '/' ) . ( ! empty( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
				$internal_url  = 'http://127.0.0.1' . $internal_path;

				$args_internal = $args;
				$args_internal['headers']['Host'] = $host_header;

				$response_alt = wp_remote_get( $internal_url, $args_internal );
				if ( ! is_wp_error( $response_alt ) ) {
					$response = $response_alt;
				}
			}
		}

		$elapsed_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_type    = Error_Classifier::classify( null, $response );

			$diag_id = Diagnostics_Logger::log_fetch( array(
				'scan_id'                     => $this->scan_id,
				'check_slug'                  => $check_slug,
				'page_url'                    => $url,
				'fetch_strategy'              => 'http_loopback',
				'target_url'                  => $url,
				'request_headers'             => $request_headers,
				'response_http_code'          => null,
				'response_headers'            => array(),
				'response_time_ms'            => $elapsed_ms,
				'response_body_size_bytes'    => 0,
				'response_body_snippet'       => null,
				'error_type'                  => $error_type,
				'error_message'               => $error_message,
				'fallback_triggered'          => $is_fallback ? 1 : 0,
				'fallback_from_diagnostic_id' => $fallback_from_id,
			) );

			return array(
				'body'          => '',
				'headers'       => array(),
				'status'        => 500,
				'diagnostic_id' => $diag_id,
			);
		}

		$status_code      = wp_remote_retrieve_response_code( $response );
		$raw_headers_obj  = wp_remote_retrieve_headers( $response );
		$response_headers = $raw_headers_obj ? $raw_headers_obj->getAll() : array();
		$body             = wp_remote_retrieve_body( $response );
		$body_size        = strlen( $body );
		$snippet          = substr( $body, 0, 2000 );

		$error_type = Error_Classifier::classify( $status_code, $response, $snippet, $response_headers );

		$diag_id = Diagnostics_Logger::log_fetch( array(
			'scan_id'                     => $this->scan_id,
			'check_slug'                  => $check_slug,
			'page_url'                    => $url,
			'fetch_strategy'              => 'http_loopback',
			'target_url'                  => $url,
			'request_headers'             => $request_headers,
			'response_http_code'          => $status_code,
			'response_headers'            => $response_headers,
			'response_time_ms'            => $elapsed_ms,
			'response_body_size_bytes'    => $body_size,
			'response_body_snippet'       => $snippet,
			'error_type'                  => $error_type,
			'error_message'               => 'none' !== $error_type ? "HTTP {$status_code} detected ({$error_type})" : null,
			'fallback_triggered'          => $is_fallback ? 1 : 0,
			'fallback_from_diagnostic_id' => $fallback_from_id,
		) );

		return array(
			'body'          => $body,
			'headers'       => $response_headers,
			'status'        => $status_code,
			'diagnostic_id' => $diag_id,
		);
	}
}
