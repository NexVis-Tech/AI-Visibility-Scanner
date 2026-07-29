<?php
namespace AIVisibilityScanner\Scanner\Checks\Crawlability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Audits XML sitemap presence and published post coverage.
 */
class Sitemap_Coverage implements Check_Interface {

	public function get_slug(): string {
		return 'sitemap_coverage';
	}

	public function get_category(): string {
		return 'crawlability';
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		$sitemap_found = $context['sitemap_found'] ?? false;
		$sitemap_count = $context['sitemap_urls_count'] ?? 0;
		$published     = wp_count_posts( 'post' )->publish + wp_count_posts( 'page' )->publish;

		if ( ! $sitemap_found || 0 === $sitemap_count ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'No accessible XML sitemap found at wp-sitemap.xml or common paths.',
				'Enable core WordPress XML sitemaps or an SEO plugin sitemap feature.',
				2,
				4
			);
		}

		$coverage = $published > 0 ? round( ( $sitemap_count / $published ) * 100 ) : 100;

		if ( $coverage < 80 ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				sprintf( 'Sitemap includes %d URLs vs %d published posts/pages (%d%% coverage).', $sitemap_count, $published, $coverage ),
				'Ensure all public posts and pages are indexed in your XML sitemap.',
				2,
				3
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			sprintf( 'Valid XML sitemap detected with %d URLs (%d%% coverage of published content).', $sitemap_count, $coverage ),
			'No action required.',
			1,
			3
		);
	}
}
