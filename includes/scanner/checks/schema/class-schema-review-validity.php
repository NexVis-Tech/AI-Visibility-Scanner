<?php
namespace AIVisibilityScanner\Scanner\Checks\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Validates Review and AggregateRating schema properties on pages with visible ratings or reviews.
 */
class Schema_Review_Validity implements Check_Interface {

	public function get_slug(): string {
		return 'schema_review_validity';
	}

	public function get_category(): string {
		return 'schema';
	}

	public function is_applicable( array $context ): bool {
		return ! empty( $context['classifier']['has_visible_reviews'] );
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( $html_body ) ) {
			return new Check_Result( $this->get_slug(), $this->get_category(), 'warn', 'HTML content unavailable for Review schema check.' );
		}

		preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html_body, $matches );

		$found_review_node = null;

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $json_str ) {
				$data = json_decode( trim( $json_str ), true );
				if ( ! is_array( $data ) ) {
					continue;
				}

				$found_review_node = $this->find_review_node( $data );
				if ( $found_review_node ) {
					break;
				}
			}
		}

		if ( ! $found_review_node ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'Visible rating/review markup detected on page, but no corresponding JSON-LD Review or AggregateRating schema was found.',
				'Add structured Review or AggregateRating schema (including itemReviewed, reviewRating with ratingValue/bestRating, and author) to help AI engines verify visitor feedback. Note: Ensure all reviews comply with FTC and search engine policy guidelines regarding disclosure of incentivized feedback.',
				2,
				4
			);
		}

		$node_type = (array) ( $found_review_node['@type'] ?? array() );
		$missing   = array();

		// Check itemReviewed
		if ( empty( $found_review_node['itemReviewed'] ) ) {
			$missing[] = 'itemReviewed';
		}

		// Check rating
		if ( in_array( 'AggregateRating', $node_type, true ) ) {
			if ( ! isset( $found_review_node['ratingValue'] ) ) {
				$missing[] = 'ratingValue';
			}
		} else {
			// Review node
			if ( empty( $found_review_node['reviewRating'] ) ) {
				$missing[] = 'reviewRating';
			} else {
				$rating = $found_review_node['reviewRating'];
				if ( is_array( $rating ) && ! isset( $rating['ratingValue'] ) ) {
					$missing[] = 'reviewRating.ratingValue';
				}
			}

			if ( empty( $found_review_node['author'] ) ) {
				$missing[] = 'author';
			}
		}

		if ( ! empty( $missing ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				sprintf( 'Review/AggregateRating schema present, but missing required properties: %s.', implode( ', ', $missing ) ),
				'Ensure Review schema includes itemReviewed, reviewRating (with ratingValue and bestRating), and author. Note: Incentivized or compensated reviews must include visible disclosures per FTC and Google policies.',
				1,
				3
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'Valid Review/AggregateRating schema detected with required rating properties (itemReviewed, ratingValue, author).',
			'No action required. Remind authors to ensure clear disclosures for incentivized reviews.',
			1,
			2
		);
	}

	/**
	 * Recursively search array graph for a node with @type Review or AggregateRating.
	 *
	 * @param array $node
	 * @return array|null
	 */
	private function find_review_node( array $node ): ?array {
		if ( isset( $node['@type'] ) ) {
			$types = (array) $node['@type'];
			if ( in_array( 'Review', $types, true ) || in_array( 'AggregateRating', $types, true ) ) {
				return $node;
			}
		}

		if ( isset( $node['aggregateRating'] ) && is_array( $node['aggregateRating'] ) ) {
			return $node['aggregateRating'];
		}

		if ( isset( $node['review'] ) && is_array( $node['review'] ) ) {
			$rev = $node['review'];
			return is_array( $rev ) ? ( $rev[0] ?? $rev ) : null;
		}

		if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
			foreach ( $node['@graph'] as $item ) {
				if ( is_array( $item ) ) {
					$found = $this->find_review_node( $item );
					if ( $found ) {
						return $found;
					}
				}
			}
		}

		return null;
	}
}
