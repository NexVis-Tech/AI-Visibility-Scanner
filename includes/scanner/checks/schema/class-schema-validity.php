<?php
namespace AIVisibilityScanner\Scanner\Checks\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Validates JSON syntactic correctness and structural properties for key Schema types.
 */
class Schema_Validity implements Check_Interface {

	public function get_slug(): string {
		return 'schema_validity';
	}

	public function get_category(): string {
		return 'schema';
	}

	public function is_applicable( array $context ): bool {
		return true;
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html_body, $matches );

		if ( empty( $matches[1] ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'No schema blocks present to validate.',
				'Add schema markup to enable validation.',
				1,
				2
			);
		}

		$errors = array();
		foreach ( $matches[1] as $idx => $json_str ) {
			$data = json_decode( trim( $json_str ), true );

			if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
				$errors[] = sprintf( 'Block #%d has invalid JSON syntax: %s', $idx + 1, json_last_error_msg() );
				continue;
			}

			// Validate common schema requirements
			if ( is_array( $data ) ) {
				$this->validate_node( $data, $errors, $idx + 1 );
			}
		}

		if ( ! empty( $errors ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				implode( '; ', $errors ),
				'Fix JSON syntax errors or missing required properties in schema script tags.',
				2,
				5
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'All JSON-LD blocks are syntactically valid JSON with required structural fields.',
			'No action required.',
			1,
			5
		);
	}

	private function validate_node( array $node, array &$errors, int $block_num ) {
		if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
			foreach ( $node['@graph'] as $sub_node ) {
				if ( is_array( $sub_node ) ) {
					$this->validate_node( $sub_node, $errors, $block_num );
				}
			}
			return;
		}

		$type = $node['@type'] ?? '';
		if ( is_array( $type ) ) {
			$type = reset( $type );
		}

		if ( 'Article' === $type || 'BlogPosting' === $type ) {
			if ( empty( $node['headline'] ) ) {
				$errors[] = sprintf( 'Block #%d (Article) missing required property "headline"', $block_num );
			}
		} elseif ( 'FAQPage' === $type ) {
			if ( empty( $node['mainEntity'] ) || ! is_array( $node['mainEntity'] ) ) {
				$errors[] = sprintf( 'Block #%d (FAQPage) missing "mainEntity" Question array', $block_num );
			}
		}
	}
}
