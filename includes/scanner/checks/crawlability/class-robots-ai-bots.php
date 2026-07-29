<?php
namespace AIVisibilityScanner\Scanner\Checks\Crawlability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scanner\Checks\Check_Interface;
use AIVisibilityScanner\Scanner\Checks\Check_Result;

/**
 * Check: Audits robots.txt rules for priority AI web crawlers.
 */
class Robots_Ai_Bots implements Check_Interface {

	public function get_slug(): string {
		return 'robots_ai_bots';
	}

	public function get_category(): string {
		return 'crawlability';
	}

	public function run( string $page_url, string $html_body, array $context ): Check_Result {
		$robots_txt = $context['robots_txt'] ?? '';

		if ( empty( $robots_txt ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'warn',
				'robots.txt content could not be retrieved over loopback or is empty.',
				'Ensure your site serves a valid virtual or physical robots.txt file.',
				1,
				3
			);
		}

		$priority_bots = array( 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'CCBot', 'Bytespider' );
		$disallowed    = array();

		foreach ( $priority_bots as $bot ) {
			// Check if User-agent: [bot] is followed by Disallow: /
			$pattern = '/User-agent:\s*' . preg_quote( $bot, '/' ) . '\s*\n(?:\s*Disallow:\s*\/|\s*User-agent:)/i';
			if ( preg_match( $pattern, $robots_txt ) ) {
				$disallowed[] = $bot;
			}
		}

		if ( ! empty( $disallowed ) ) {
			return new Check_Result(
				$this->get_slug(),
				$this->get_category(),
				'fail',
				'Disallowed AI Bots found in robots.txt: ' . implode( ', ', $disallowed ),
				'Remove Disallow: / directives for key AI crawlers in your robots.txt file to allow search indexing.',
				2,
				5
			);
		}

		return new Check_Result(
			$this->get_slug(),
			$this->get_category(),
			'pass',
			'Priority AI bots (GPTBot, ClaudeBot, PerplexityBot, Google-Extended) are allowed in robots.txt.',
			'No action required.',
			1,
			5
		);
	}
}
