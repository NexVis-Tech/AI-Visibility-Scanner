<?php
namespace AIVisibilityScanner\Scanner\Checks\Crawlability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Audits meta robots noindex tags and canonical URL consistency on each page.
 */
class Noindex_Canonical implements Check_Interface {

	public function get_slug(): string {
		return 'noindex_canonical';
	}

	public function get_category(): string {
		return 'crawlability';
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( $html_body ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'Empty HTML body retrieved.',
				'Verify page permalink accessibility.',
				1,
				2
			);
		}

		// Check meta robots tag
		if ( preg_match( '/<meta[^>]+name=["\']robots["\'][^>]+content=["\'][^"\']*noindex[^"\']*["\']/i', $html_body ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				'Page carries a <meta name="robots" content="noindex"> tag.',
				'Remove noindex directive if this page is intended for public search indexing.',
				1,
				5
			);
		}

		// Check canonical URL
		if ( preg_match( '/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html_body, $matches ) ) {
			$canonical = $matches[1];
			$page_host = wp_parse_url( $page_url, PHP_URL_HOST );
			$can_host  = wp_parse_url( $canonical, PHP_URL_HOST );

			if ( $page_host && $can_host && strtolower( $page_host ) !== strtolower( $can_host ) ) {
				return new Check_Result(
					$this->get_slug(),
					$this->get_category(),
					'warn',
					sprintf( 'Canonical link points to a different domain: %s', $canonical ),
					'Verify if cross-domain canonical tag is intended.',
					2,
					4
				);
			}
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'Page is indexable (no noindex found) with valid canonical tag.',
			'No action required.',
			1,
			5
		);
	}
}
