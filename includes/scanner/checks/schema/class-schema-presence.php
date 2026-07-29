<?php
namespace AIVisibilityScanner\Scanner\Checks\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Detects JSON-LD script blocks and records present @type declarations.
 */
class Schema_Presence implements Check_Interface {

	public function get_slug(): string {
		return 'schema_presence';
	}

	public function get_category(): string {
		return 'schema';
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( $html_body ) ) {
			return new Check_Result( $this->get_slug(), $this->get_category(), 'warn', 'HTML content unavailable for schema audit.' );
		}

		preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html_body, $matches );

		if ( empty( $matches[1] ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'No JSON-LD structured data script blocks found on this page.',
				'Add JSON-LD schema markup (Article, FAQPage, Organization, WebPage) to structure content for LLM extractors.',
				2,
				4
			);
		}

		$types = array();
		foreach ( $matches[1] as $json_str ) {
			$data = json_decode( trim( $json_str ), true );
			if ( is_array( $data ) ) {
				if ( isset( $data['@type'] ) ) {
					$types[] = is_array( $data['@type'] ) ? implode( ',', $data['@type'] ) : $data['@type'];
				} elseif ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
					foreach ( $data['@graph'] as $graph_node ) {
						if ( isset( $graph_node['@type'] ) ) {
							$types[] = is_array( $graph_node['@type'] ) ? implode( ',', $graph_node['@type'] ) : $graph_node['@type'];
						}
					}
				}
			}
		}

		$types_str = ! empty( $types ) ? implode( ', ', array_unique( $types ) ) : 'Generic JSON-LD';

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			sprintf( 'Found %d JSON-LD block(s) declaring types: %s', count( $matches[1] ), $types_str ),
			'No action required.',
			1,
			4
		);
	}
}
