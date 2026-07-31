<?php
namespace AIVisibilityScanner\Scanner\Checks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Crawlability\Robots_Ai_Bots;
use AIVisibilityScanner\Scanner\Checks\Crawlability\Cloudflare_Edge_Bot_Risk;
use AIVisibilityScanner\Scanner\Checks\Crawlability\Sitemap_Coverage;
use AIVisibilityScanner\Scanner\Checks\Crawlability\Noindex_Canonical;
use AIVisibilityScanner\Scanner\Checks\Crawlability\Page_Reachability;
use AIVisibilityScanner\Scanner\Checks\Schema\Schema_Presence;
use AIVisibilityScanner\Scanner\Checks\Schema\Schema_Validity;
use AIVisibilityScanner\Scanner\Checks\Content\Heading_Hierarchy;
use AIVisibilityScanner\Scanner\Checks\Content\Meta_Description;
use AIVisibilityScanner\Scanner\Checks\Content\Faq_Howto_Opportunity;
use AIVisibilityScanner\Scanner\Checks\Experience\Core_Web_Vitals_Flag;

/**
 * Central registry managing check instances.
 */
class Check_Registry {

	/**
	 * Registered check instances.
	 * @var Check_Interface[]
	 */
	private $checks = array();

	public function __construct() {
		$this->register_default_checks();
	}

	/**
	 * Register default check modules.
	 */
	private function register_default_checks() {
		$this->register_check( new Page_Reachability() );
		$this->register_check( new Robots_Ai_Bots() );
		$this->register_check( new Cloudflare_Edge_Bot_Risk() );
		$this->register_check( new Sitemap_Coverage() );
		$this->register_check( new Noindex_Canonical() );
		$this->register_check( new Schema_Presence() );
		$this->register_check( new Schema_Validity() );
		$this->register_check( new Heading_Hierarchy() );
		$this->register_check( new Meta_Description() );
		$this->register_check( new Faq_Howto_Opportunity() );
		$this->register_check( new Core_Web_Vitals_Flag() );
	}

	/**
	 * Register a check instance.
	 *
	 * @param Check_Interface $check
	 */
	public function register_check( Check_Interface $check ) {
		$this->checks[ $check->get_slug() ] = $check;
	}

	/**
	 * Get all registered checks.
	 *
	 * @return Check_Interface[]
	 */
	public function get_checks() {
		return apply_filters( 'avs_registered_checks', $this->checks );
	}
}
