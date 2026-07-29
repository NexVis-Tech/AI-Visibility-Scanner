<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Page_Fetcher;

/**
 * Handles site URL discovery and shared context gathering.
 */
class Crawler {

	private $fetcher;

	public function __construct() {
		$this->fetcher = new Page_Fetcher();
	}

	/**
	 * Gather site-level context shared across all check modules.
	 *
	 * @return array
	 */
	public function get_site_context(): array {
		$site_url = get_site_url();

		// Fetch virtual or physical robots.txt via HTTP loopback
		$robots_res = $this->fetcher->fetch_loopback_http( trailingslashit( $site_url ) . 'robots.txt' );

		// Fetch homepage headers for Cloudflare / WAF detection
		$home_res = $this->fetcher->fetch_loopback_http( $site_url );

		// Sitemap discovery
		$sitemap_url   = trailingslashit( $site_url ) . 'wp-sitemap.xml';
		$sitemap_res   = $this->fetcher->fetch_loopback_http( $sitemap_url );
		$sitemap_found = ( 200 === $sitemap_res['status'] && ! empty( $sitemap_res['body'] ) );

		$sitemap_urls_count = 0;
		if ( $sitemap_found ) {
			preg_match_all( '/<loc>(.*?)<\/loc>/i', $sitemap_res['body'], $matches );
			$sitemap_urls_count = ! empty( $matches[1] ) ? count( $matches[1] ) : 0;
		}

		return array(
			'robots_txt'         => $robots_res['body'],
			'http_headers'       => $home_res['headers'],
			'sitemap_found'      => $sitemap_found,
			'sitemap_urls_count' => $sitemap_urls_count,
		);
	}

	/**
	 * Retrieve priority list of site URLs capped by max_pages setting.
	 *
	 * @return string[] Array of URLs
	 */
	public function get_urls_to_scan(): array {
		$settings     = get_option( 'avs_settings', array( 'post_types' => array( 'post', 'page' ), 'max_pages' => 30 ) );
		$post_types   = $settings['post_types'] ?? array( 'post', 'page' );
		$requested_cap= $settings['max_pages'] ?? 30;

		// Enforce free cap ceiling
		$free_cap = apply_filters( 'avs_max_pages_free', 30 );
		$limit    = min( $requested_cap, $free_cap );

		$urls = array( get_site_url() );

		$posts = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit - 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		foreach ( $posts as $p ) {
			$urls[] = get_permalink( $p->ID );
		}

		return array_unique( array_slice( $urls, 0, $limit ) );
	}
}
