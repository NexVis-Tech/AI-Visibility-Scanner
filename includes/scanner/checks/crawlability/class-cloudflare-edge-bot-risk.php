<?php
namespace AIVisibilityScanner\Scanner\Checks\Crawlability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Detects Cloudflare presence and flags edge WAF AI bot control risks.
 */
class Cloudflare_Edge_Bot_Risk implements Check_Interface {

	public function get_slug(): string {
		return 'cloudflare_edge_bot_risk';
	}

	public function get_category(): string {
		return 'crawlability';
	}

	public function is_applicable( array $context ): bool {
		return true;
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		$headers = $context['http_headers'] ?? array();

		$cf_detected = false;
		$evidence    = array();

		foreach ( $headers as $name => $value ) {
			if ( strpos( strtolower( $name ), 'cf-ray' ) !== false || strpos( strtolower( $name ), 'cf-cache-status' ) !== false || strpos( strtolower( $value ), 'cloudflare' ) !== false ) {
				$cf_detected = true;
				$evidence[]  = $name . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : $value );
			}
		}

		if ( $cf_detected ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'Cloudflare Edge Proxy detected (' . implode( '; ', array_slice( $evidence, 0, 2 ) ) . '). Cloudflare edge WAF rules can independently block AI bots regardless of robots.txt.',
				'Verify Security → AI Crawl Control setting in your Cloudflare dashboard to ensure edge blocking is disabled for required crawlers.',
				1,
				4
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'No Cloudflare edge proxy headers detected in loopback HTTP response.',
			'No action required.',
			1,
			1
		);
	}
}
