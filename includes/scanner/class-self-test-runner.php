<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs standalone connectivity self-tests (Loopback, Robots.txt, Sitemap, Cloudflare).
 */
class Self_Test_Runner {

	/**
	 * Run all four fast self-tests.
	 *
	 * @return array Self-test results with expandable diagnostic details
	 */
	public function run(): array {
		$site_url = get_site_url();
		$fetcher  = new Page_Fetcher( null );

		$results = array(
			'loopback'   => $this->test_loopback( $fetcher, $site_url ),
			'robots'     => $this->test_robots_txt( $fetcher, $site_url ),
			'sitemap'    => $this->test_sitemap( $fetcher, $site_url ),
			'cloudflare' => $this->test_cloudflare( $fetcher, $site_url ),
		);

		// Update or record environment with the loopback connectivity status
		$loopback_status = 'pass' === $results['loopback']['status'] ? 'ok' : 'failed';
		$env_data = Environment_Collector::collect( $loopback_status );
		
		// If there is an existing latest scan environment, update it, otherwise create standalone
		global $wpdb;
		$table_env = $wpdb->prefix . 'avs_scan_environment';
		$latest_env = $wpdb->get_row( "SELECT scan_id FROM {$table_env} ORDER BY id DESC LIMIT 1" );
		if ( $latest_env ) {
			Diagnostics_Logger::log_environment( (int) $latest_env->scan_id, $env_data );
		}

		return array(
			'success'     => true,
			'timestamp'   => current_time( 'mysql' ),
			'environment' => $env_data,
			'tests'       => $results,
		);
	}

	/**
	 * Test 1: Loopback reachability.
	 */
	private function test_loopback( Page_Fetcher $fetcher, string $site_url ): array {
		$res = $fetcher->fetch_loopback_http( $site_url, 'selftest_loopback' );
		$is_pass = ( 200 === (int) $res['status'] );

		return array(
			'name'          => __( 'Loopback Reachability', 'ai-visibility-scanner' ),
			'status'        => $is_pass ? 'pass' : 'fail',
			'summary'       => $is_pass ? __( 'Loopback HTTP request succeeded (200 OK)', 'ai-visibility-scanner' ) : sprintf( __( 'Loopback HTTP failed with status %s', 'ai-visibility-scanner' ), $res['status'] ),
			'http_code'     => $res['status'],
			'diagnostic_id' => $res['diagnostic_id'],
		);
	}

	/**
	 * Test 2: Robots.txt fetch.
	 */
	private function test_robots_txt( Page_Fetcher $fetcher, string $site_url ): array {
		$robots_url = trailingslashit( $site_url ) . 'robots.txt';
		$res        = $fetcher->fetch_loopback_http( $robots_url, 'selftest_robotstxt' );
		$is_pass    = ( 200 === (int) $res['status'] && ! empty( $res['body'] ) );

		return array(
			'name'          => __( 'Robots.txt Reachability', 'ai-visibility-scanner' ),
			'status'        => $is_pass ? 'pass' : ( 404 === (int) $res['status'] ? 'warn' : 'fail' ),
			'summary'       => $is_pass ? sprintf( __( 'robots.txt found (%d bytes)', 'ai-visibility-scanner' ), strlen( $res['body'] ) ) : ( 404 === (int) $res['status'] ? __( 'robots.txt returned 404 Not Found', 'ai-visibility-scanner' ) : sprintf( __( 'robots.txt fetch failed with status %s', 'ai-visibility-scanner' ), $res['status'] ) ),
			'http_code'     => $res['status'],
			'diagnostic_id' => $res['diagnostic_id'],
			'snippet'       => substr( $res['body'], 0, 500 ),
		);
	}

	/**
	 * Test 3: Sitemap fetch.
	 */
	private function test_sitemap( Page_Fetcher $fetcher, string $site_url ): array {
		$sitemap_candidates = array(
			trailingslashit( $site_url ) . 'sitemap.xml',
			trailingslashit( $site_url ) . 'wp-sitemap.xml',
			trailingslashit( $site_url ) . 'sitemap_index.xml',
		);

		$found_url  = null;
		$url_count  = 0;
		$last_res   = null;

		foreach ( $sitemap_candidates as $url ) {
			$res = $fetcher->fetch_loopback_http( $url, 'selftest_sitemap' );
			$last_res = $res;
			if ( 200 === (int) $res['status'] && strpos( $res['body'], '<xml' ) !== false || strpos( $res['body'], '<urlset' ) !== false || strpos( $res['body'], '<sitemapindex' ) !== false ) {
				$found_url = $url;
				// Count <loc> instances
				$url_count = substr_count( strtolower( $res['body'] ), '<loc>' );
				break;
			}
		}

		$is_pass = null !== $found_url;

		return array(
			'name'          => __( 'Sitemap Reachability', 'ai-visibility-scanner' ),
			'status'        => $is_pass ? 'pass' : 'warn',
			'summary'       => $is_pass ? sprintf( __( 'Sitemap accessible at %s (%d URLs parsed)', 'ai-visibility-scanner' ), esc_html( basename( $found_url ) ), $url_count ) : __( 'No standard sitemap.xml or wp-sitemap.xml found', 'ai-visibility-scanner' ),
			'http_code'     => $last_res ? $last_res['status'] : null,
			'diagnostic_id' => $last_res ? $last_res['diagnostic_id'] : null,
			'url'           => $found_url,
		);
	}

	/**
	 * Test 4: Cloudflare header detection.
	 */
	private function test_cloudflare( Page_Fetcher $fetcher, string $site_url ): array {
		$res = $fetcher->fetch_loopback_http( $site_url, 'selftest_cloudflare' );
		
		$headers = is_array( $res['headers'] ) ? array_change_key_case( $res['headers'], CASE_LOWER ) : array();
		$cf_ray  = isset( $headers['cf-ray'] ) ? $headers['cf-ray'] : null;
		$cf_cache = isset( $headers['cf-cache-status'] ) ? $headers['cf-cache-status'] : null;
		$server  = isset( $headers['server'] ) ? $headers['server'] : null;

		$detected = ( null !== $cf_ray || null !== $cf_cache || ( $server && strpos( strtolower( $server ), 'cloudflare' ) !== false ) );

		return array(
			'name'          => __( 'Cloudflare Intermediary Check', 'ai-visibility-scanner' ),
			'status'        => 'info',
			'summary'       => $detected ? sprintf( __( 'Cloudflare Detected (Ray ID: %s, Cache: %s)', 'ai-visibility-scanner' ), esc_html( $cf_ray ? $cf_ray : 'Yes' ), esc_html( $cf_cache ? $cf_cache : 'N/A' ) ) : __( 'Cloudflare proxy headers not detected on loopback', 'ai-visibility-scanner' ),
			'detected'      => $detected,
			'diagnostic_id' => $res['diagnostic_id'],
		);
	}
}
