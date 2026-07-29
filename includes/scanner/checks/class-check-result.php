<?php
namespace AIVisibilityScanner\Scanner\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object representing a single check result.
 */
class Check_Result {

	public $slug;
	public $category;
	public $result; // 'pass', 'warn', 'fail'
	public $evidence;
	public $fix_hint;
	public $effort_score; // 1 to 5
	public $impact_score; // 1 to 5

	public function __construct(
		string $slug,
		string $category,
		string $result,
		string $evidence = '',
		string $fix_hint = '',
		int $effort_score = 1,
		int $impact_score = 1
	) {
		$this->slug         = $slug;
		$this->category     = $category;
		$this->result       = $result;
		$this->evidence     = $evidence;
		$this->fix_hint     = $fix_hint;
		$this->effort_score = max( 1, min( 5, $effort_score ) );
		$this->impact_score = max( 1, min( 5, $impact_score ) );
	}
}
