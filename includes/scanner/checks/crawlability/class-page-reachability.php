<?php
namespace AIVisibilityScanner\Scanner\Checks\Crawlability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Audits page reachability and accessibility.
 */
class Page_Reachability implements Check_Interface {

	public function get_slug(): string {
		return 'page_reachability';
	}

	public function get_category(): string {
		return 'crawlability';
	}

	public function is_applicable( array $context ): bool {
		return true;
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( trim( $html_body ) ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				'Page could not be loaded. HTML body is empty or server returned a connection timeout/error.',
				'Verify that your server allows loopback HTTP connections or check for WAF/security blocking issues in Diagnostics.',
				2,
				5
			);
		}

		// Check if it is a standard HTML structure
		if ( stripos( $html_body, '<html' ) === false && stripos( $html_body, '<body' ) === false ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'Page loaded but returned a non-HTML or incomplete response structure.',
				'Ensure your page content outputs standard, valid HTML elements.',
				1,
				3
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'Page is accessible and returned valid HTML content structure.',
			'No action required.',
			1,
			5
		);
	}
}
