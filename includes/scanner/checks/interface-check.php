<?php
namespace AIVisibilityScanner\Scanner\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface contract that every Check Module must implement.
 */
interface Check_Interface {

	/**
	 * Unique check identifier slug (e.g., 'robots_ai_bots').
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Category group ('crawlability', 'schema', 'content', 'experience').
	 *
	 * @return string
	 */
	public function get_category(): string;

	/**
	 * Determine if this check is applicable to the given page or site context.
	 *
	 * @param array $context
	 * @return bool
	 */
	public function is_applicable( array $context ): bool;

	/**
	 * Execute check logic on a single page or site context.
	 *
	 * @param string $page_url
	 * @param string $html_body
	 * @param array  $context Shared site-level data (e.g. parsed robots.txt, sitemap URLs)
	 * @return Check_Result
	 */
	public function run( string $page_url, string $html_body, array $context ): Check_Result;
}
