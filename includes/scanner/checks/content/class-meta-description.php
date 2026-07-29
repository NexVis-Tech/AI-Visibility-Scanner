<?php
namespace AIVisibilityScanner\Scanner\Checks\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Audits meta description presence and optimal length.
 */
class Meta_Description implements Check_Interface {

	public function get_slug(): string {
		return 'meta_description';
	}

	public function get_category(): string {
		return 'content';
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( $html_body ) ) {
			return new Check_Result( $this->get_slug(), $this->get_category(), 'warn', 'HTML body unavailable.' );
		}

		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html_body, $matches ) ) {
			$desc   = trim( $matches[1] );
			$length = mb_strlen( $desc );

			if ( 0 === $length ) {
				return new Check_Result(
					$this->get_slug(),
					$this->get_category(),
					'warn',
					'Meta description tag exists but is empty.',
					'Add a concise 50-160 character summary in the meta description field.',
					1,
					3
				);
			}

			if ( $length < 50 || $length > 160 ) {
				return new Check_Result(
					$this->get_slug(),
					$this->get_category(),
					'warn',
					sprintf( 'Meta description length (%d chars) is outside optimal 50-160 range.', $length ),
					'Optimize meta description length between 50 and 160 characters for optimal snippet extraction.',
					1,
					2
				);
			}

			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'pass',
				sprintf( 'Meta description present with optimal length (%d characters).', $length ),
				'No action required.',
				1,
				3
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'warn',
			'Missing <meta name="description"> tag.',
			'Add a meta description to summarize page intent for search & AI crawlers.',
			1,
			3
		);
	}
}
