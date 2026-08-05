<?php
namespace AIVisibilityScanner\Scanner\Checks\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Audits H1-H6 heading hierarchy and checks for missing or multiple H1 tags.
 */
class Heading_Hierarchy implements Check_Interface {

	public function get_slug(): string {
		return 'heading_hierarchy';
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

		preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html_body, $matches );

		if ( empty( $matches[1] ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'No HTML heading tags (H1-H6) found on this page.',
				'Add structured H1 and H2 headings to organize content for AI reading parsers.',
				1,
				4
			);
		}

		$levels = array_map( 'intval', $matches[1] );
		$h1_count = count( array_keys( $levels, 1, true ) );

		if ( 0 === $h1_count ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				'Missing <h1> heading tag. AI engines rely on H1 tags as primary topic identifiers.',
				'Add exactly one primary <h1> title heading to the page.',
				1,
				5
			);
		}

		if ( $h1_count > 1 ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				sprintf( 'Multiple <h1> headings found (%d present).', $h1_count ),
				'Ensure the page uses only a single <h1> heading, using <h2> for section titles.',
				1,
				3
			);
		}

		// Check for skipped heading levels (e.g. H2 -> H4)
		$skipped = false;
		for ( $i = 0; $i < count( $levels ) - 1; $i++ ) {
			if ( $levels[ $i + 1 ] > $levels[ $i ] + 1 ) {
				$skipped = true;
				break;
			}
		}

		if ( $skipped ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'Heading hierarchy skips levels (e.g. <h2> followed directly by <h4>).',
				'Maintain sequential heading hierarchy (H1 -> H2 -> H3) for clear document outline parsing.',
				1,
				2
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			sprintf( 'Valid heading hierarchy detected with 1 H1 and %d total subheadings.', count( $levels ) ),
			'No action required.',
			1,
			4
		);
	}
}
