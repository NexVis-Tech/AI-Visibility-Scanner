<?php
namespace AIVisibilityScanner\Scanner\Checks\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Validates LocalBusiness schema properties on designated homepage and contact/about pages.
 */
class Schema_Localbusiness_Validity implements Check_Interface {

	public function get_slug(): string {
		return 'schema_localbusiness_validity';
	}

	public function get_category(): string {
		return 'schema';
	}

	public function is_applicable( array $context ): bool {
		return ! empty( $context['classifier']['is_localbusiness_target'] );
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		if ( empty( $html_body ) ) {
			return new Check_Result( $this->get_slug(), $this->get_category(), 'warn', 'HTML content unavailable for LocalBusiness schema check.' );
		}

		preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html_body, $matches );

		$found_business = null;

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $json_str ) {
				$data = json_decode( trim( $json_str ), true );
				if ( ! is_array( $data ) ) {
					continue;
				}

				$found_business = $this->find_local_business_node( $data );
				if ( $found_business ) {
					break;
				}
			}
		}

		if ( ! $found_business ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				'Local business audit enabled, but no JSON-LD LocalBusiness schema was found on this target page.',
				'Add structured LocalBusiness schema (with name, address, telephone, and opening hours) to ensure AI engines accurately index physical address and contact details.',
				2,
				4
			);
		}

		// Validate required fields: name, address (streetAddress, addressLocality, addressRegion, postalCode), telephone
		$missing = array();

		if ( empty( $found_business['name'] ) ) {
			$missing[] = 'name';
		}
		if ( empty( $found_business['telephone'] ) && empty( $found_business['phone'] ) ) {
			$missing[] = 'telephone';
		}

		if ( empty( $found_business['address'] ) ) {
			$missing[] = 'address';
		} else {
			$addr = $found_business['address'];
			if ( ! is_array( $addr ) ) {
				$missing[] = 'address object';
			} else {
				if ( empty( $addr['streetAddress'] ) ) {
					$missing[] = 'address.streetAddress';
				}
				if ( empty( $addr['addressLocality'] ) ) {
					$missing[] = 'address.addressLocality';
				}
				if ( empty( $addr['addressRegion'] ) ) {
					$missing[] = 'address.addressRegion';
				}
				if ( empty( $addr['postalCode'] ) ) {
					$missing[] = 'address.postalCode';
				}
			}
		}

		if ( ! empty( $missing ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				sprintf( 'LocalBusiness schema present, but missing required NAP fields: %s.', implode( ', ', $missing ) ),
				'Ensure LocalBusiness schema contains complete NAP details: name, telephone, and address with streetAddress, addressLocality, addressRegion, and postalCode.',
				1,
				5
			);
		}

		// Recommended check: openingHours / openingHoursSpecification
		$has_hours = ! empty( $found_business['openingHours'] ) || ! empty( $found_business['openingHoursSpecification'] );
		if ( ! $has_hours ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'LocalBusiness schema is valid for NAP, but missing recommended openingHours or openingHoursSpecification property.',
				'Add openingHours or openingHoursSpecification to your LocalBusiness schema to provide business operating hours to AI assistants.',
				1,
				2
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'Valid LocalBusiness schema detected with complete NAP data (name, address, telephone, and opening hours).',
			'No action required.',
			1,
			3
		);
	}

	/**
	 * Recursively search array graph for a node with @type LocalBusiness or a subclass.
	 *
	 * @param array $node
	 * @return array|null
	 */
	private function find_local_business_node( array $node ): ?array {
		if ( isset( $node['@type'] ) ) {
			$types = (array) $node['@type'];
			foreach ( $types as $t ) {
				if ( 'LocalBusiness' === $t || ( is_string( $t ) && ( false !== strpos( $t, 'LocalBusiness' ) || in_array( $t, array( 'Store', 'Restaurant', 'Dentist', 'ProfessionalService', 'AutomotiveBusiness', 'FinancialService', 'FoodEstablishment', 'HealthAndBeautyBusiness', 'HomeAndConstructionBusiness', 'LegalService', 'MedicalBusiness', 'RealEstateAgent' ), true ) ) ) ) {
					return $node;
				}
			}
		}

		if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
			foreach ( $node['@graph'] as $item ) {
				if ( is_array( $item ) ) {
					$found = $this->find_local_business_node( $item );
					if ( $found ) {
						return $found;
					}
				}
			}
		}

		return null;
	}
}
