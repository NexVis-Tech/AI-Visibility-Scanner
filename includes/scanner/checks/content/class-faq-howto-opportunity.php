<?php
namespace AIVisibilityScanner\Scanner\Checks\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Scans for un-schematized FAQ or HowTo content patterns.
 */
class Faq_Howto_Opportunity implements Check_Interface {

	public function get_slug(): string {
		return 'faq_howto_opportunity';
	}

	public function get_category(): string {
		return 'content';
	}

	public function is_applicable( array $context ): bool {
		return true;
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( $html_body ) ) {
			return new Check_Result( $this->get_slug(), $this->get_category(), 'warn', 'HTML body unavailable.' );
		}

		// Find question-like headings
		preg_match_all( '/<h[2-6]\b[^>]*>(.*?\?|How to\b.*?)<\/h[2-6]>/is', $html_body, $matches );

		$opportunity_count = ! empty( $matches[1] ) ? count( $matches[1] ) : 0;

		// Cross reference with JSON-LD FAQPage/HowTo schema presence on this page
		$has_faq_schema = ( false !== strpos( $html_body, 'FAQPage' ) || false !== strpos( $html_body, 'HowTo' ) );

		if ( $opportunity_count > 0 && ! $has_faq_schema ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				sprintf( 'Detected %d question/how-to heading pattern(s) without corresponding FAQPage/HowTo schema.', $opportunity_count ),
				'Wrap Q&A and how-to sections with FAQPage/HowTo schema markup to support AI search engines (like Google AI Overviews and SearchGPT) in accurately parsing, comprehending, and citing your content.',
				2,
				4
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'No un-schematized FAQ or HowTo content opportunities detected.',
			'No action required.',
			1,
			2
		);
	}
}
