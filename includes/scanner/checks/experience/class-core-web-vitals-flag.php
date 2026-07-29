<?php
namespace AIVisibilityScanner\Scanner\Checks\Experience;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Flag-only Core Web Vitals and INP page experience advisory.
 */
class Core_Web_Vitals_Flag implements Check_Interface {

	public function get_slug(): string {
		return 'core_web_vitals_flag';
	}

	public function get_category(): string {
		return 'experience';
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'warn',
			'Page experience signals (Core Web Vitals & INP) directly influence AI Overview eligibility.',
			'Optimize LCP, INP, and CLS performance using speed optimization tools.',
			2,
			3
		);
	}
}
