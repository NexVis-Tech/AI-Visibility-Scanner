<?php
namespace AIVisibilityScanner\Scanner\Checks\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Validates Product schema properties on identified e-commerce product pages.
 */
class Schema_Product_Validity implements Check_Interface {

	public function get_slug(): string {
		return 'schema_product_validity';
	}

	public function get_category(): string {
		return 'schema';
	}

	public function is_applicable( array $context ): bool {
		return ! empty( $context['classifier']['is_product_page'] );
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( $html_body ) ) {
			return new Check_Result( $this->get_slug(), $this->get_category(), 'warn', 'HTML content unavailable for Product schema check.' );
		}

		preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html_body, $matches );

		$found_product = null;

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $json_str ) {
				$data = json_decode( trim( $json_str ), true );
				if ( ! is_array( $data ) ) {
					continue;
				}

				$found_product = $this->find_product_node( $data );
				if ( $found_product ) {
					break;
				}
			}
		}

		if ( ! $found_product ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				'Product page detected, but no JSON-LD Product schema was found.',
				'Add structured Product schema (including name, image, description, and offers with price, currency, and availability) to enable product features in AI search engine graphs.',
				2,
				4
			);
		}

		// Validate required fields: name, image, description, offers (price, priceCurrency, availability)
		$missing = array();

		if ( empty( $found_product['name'] ) ) {
			$missing[] = 'name';
		}
		if ( empty( $found_product['image'] ) ) {
			$missing[] = 'image';
		}
		if ( empty( $found_product['description'] ) ) {
			$missing[] = 'description';
		}

		if ( empty( $found_product['offers'] ) ) {
			$missing[] = 'offers';
		} else {
			$offers = $found_product['offers'];
			// If offers is numeric array (multiple offers), check first offer
			if ( isset( $offers[0] ) && is_array( $offers[0] ) ) {
				$offers = $offers[0];
			}
			if ( ! is_array( $offers ) ) {
				$missing[] = 'offers structure';
			} else {
				if ( ! isset( $offers['price'] ) && ! isset( $offers['priceSpecification'] ) ) {
					$missing[] = 'offers.price';
				}
				if ( empty( $offers['priceCurrency'] ) ) {
					$missing[] = 'offers.priceCurrency';
				}
				if ( empty( $offers['availability'] ) ) {
					$missing[] = 'offers.availability';
				}
			}
		}

		if ( ! empty( $missing ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				sprintf( 'Product schema present, but missing required/recommended fields: %s.', implode( ', ', $missing ) ),
				'Update Product schema markup to include all required fields: name, image, description, and offers (price, priceCurrency, availability).',
				1,
				4
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'Valid Product schema markup detected with all required properties (name, image, description, offers).',
			'No action required.',
			1,
			3
		);
	}

	/**
	 * Recursively search array graph for a node with @type Product.
	 *
	 * @param array $node
	 * @return array|null
	 */
	private function find_product_node( array $node ): ?array {
		if ( isset( $node['@type'] ) ) {
			$types = (array) $node['@type'];
			if ( in_array( 'Product', $types, true ) ) {
				return $node;
			}
		}

		if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
			foreach ( $node['@graph'] as $item ) {
				if ( is_array( $item ) ) {
					$found = $this->find_product_node( $item );
					if ( $found ) {
						return $found;
					}
				}
			}
		}

		return null;
	}
}
