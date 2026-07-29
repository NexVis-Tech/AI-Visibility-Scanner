<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures site environment fingerprint (WP version, PHP version, plugins, host, Cloudflare).
 */
class Environment_Collector {

	/**
	 * Collect full environment snapshot.
	 *
	 * @param string $loopback_status 'ok', 'failed', or 'not_tested'
	 * @return array Environment data
	 */
	public static function collect( string $loopback_status = 'not_tested' ): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		global $wp_version;

		$theme = wp_get_theme();
		$theme_name = $theme ? $theme->get( 'Name' ) : 'Unknown';

		$builders = self::detect_page_builders();
		$security = self::detect_security_plugins();
		$cache    = self::detect_cache_plugins();
		$seo      = self::detect_seo_plugins();

		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ) : 'Unknown';
		$hosting_guess   = self::guess_hosting_provider();
		$cf_detected     = self::detect_cloudflare();

		return array(
			'wp_version'              => $wp_version ? $wp_version : get_bloginfo( 'version' ),
			'php_version'             => PHP_VERSION,
			'active_theme'            => $theme_name,
			'active_page_builders'    => wp_json_encode( $builders ),
			'active_security_plugins' => wp_json_encode( $security ),
			'active_cache_plugins'    => wp_json_encode( $cache ),
			'active_seo_plugins'      => wp_json_encode( $seo ),
			'cloudflare_detected'     => $cf_detected ? 1 : 0,
			'server_software'         => $server_software,
			'hosting_signature_guess' => $hosting_guess,
			'loopback_connectivity'   => $loopback_status,
			'site_url_snapshot'       => get_site_url(),
			'created_at'              => current_time( 'mysql' ),
		);
	}

	/**
	 * Detect active page builders.
	 */
	private static function detect_page_builders(): array {
		$known = array(
			'elementor/elementor.php'               => 'Elementor',
			'elementor-pro/elementor-pro.php'       => 'Elementor Pro',
			'divi-builder/divi-builder.php'         => 'Divi Builder',
			'beaver-builder-lite-version/fl-builder.php' => 'Beaver Builder',
			'fl-builder/fl-builder.php'             => 'Beaver Builder Agency',
			'js_composer/js_composer.php'           => 'WPBakery',
			'oxygen/functions.php'                  => 'Oxygen',
			'bricks/bricks.php'                     => 'Bricks Builder',
			'siteorigin-panels/siteorigin-panels.php' => 'SiteOrigin Page Builder',
		);

		$active = array();
		foreach ( $known as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}

		// Also check active theme for Divi or Bricks
		$theme = wp_get_theme();
		$template = strtolower( $theme->get_template() );
		if ( 'divi' === $template && ! in_array( 'Divi Builder', $active, true ) ) {
			$active[] = 'Divi Theme Builder';
		}
		if ( 'bricks' === $template && ! in_array( 'Bricks Builder', $active, true ) ) {
			$active[] = 'Bricks Theme';
		}

		return $active;
	}

	/**
	 * Detect active security plugins.
	 */
	private static function detect_security_plugins(): array {
		$known = array(
			'wordfence/wordfence.php'                          => 'Wordfence',
			'sucuri-scanner/sucuri.php'                        => 'Sucuri Security',
			'better-wp-security/better-wp-security.php'        => 'Solid Security (iThemes)',
			'ithemes-security-pro/ithemes-security-pro.php'    => 'Solid Security Pro',
			'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security',
			'wp-defender/wp-defender.php'                      => 'Defender Security',
			'ninja-firewall/ninjafirewall.php'                 => 'NinjaFirewall',
			'secupress/secupress.php'                          => 'SecuPress',
		);

		$active = array();
		foreach ( $known as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}

		return $active;
	}

	/**
	 * Detect active caching plugins.
	 */
	private static function detect_cache_plugins(): array {
		$known = array(
			'wp-rocket/wp-rocket.php'             => 'WP Rocket',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
			'w3-total-cache/w3-total-cache.php'   => 'W3 Total Cache',
			'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
			'cache-enabler/cache-enabler.php'     => 'Cache Enabler',
			'sg-cachepress/sg-cachepress.php'     => 'Speed Booster (SiteGround)',
			'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
			'autoptimize/autoptimize.php'         => 'Autoptimize',
		);

		$active = array();
		foreach ( $known as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}

		return $active;
	}

	/**
	 * Detect active SEO plugins.
	 */
	private static function detect_seo_plugins(): array {
		$known = array(
			'wordpress-seo/wp-seo.php'                 => 'Yoast SEO',
			'wordpress-seo-premium/wp-seo-premium.php' => 'Yoast SEO Premium',
			'seo-by-rank-math/rank-math.php'           => 'Rank Math',
			'seo-by-rank-math-pro/rank-math-pro.php'   => 'Rank Math Pro',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
			'wp-seopress/seopress.php'                 => 'SEOPress',
			'slim-seo/slim-seo.php'                    => 'Slim SEO',
			'autodescription/autodescription.php'      => 'The SEO Framework',
		);

		$active = array();
		foreach ( $known as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}

		return $active;
	}

	/**
	 * Cloudflare detection based on environment headers or request.
	 */
	private static function detect_cloudflare(): bool {
		if ( isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return true;
		}

		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( strtolower( $_SERVER['SERVER_SOFTWARE'] ), 'cloudflare' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Best-effort guess of hosting provider signature based on environment constants and server vars.
	 */
	private static function guess_hosting_provider(): string {
		if ( defined( 'WPE_APIKEY' ) || isset( $_SERVER['IS_WPE'] ) || isset( $_SERVER['WPE_APIKEY'] ) ) {
			return 'WP Engine (High Confidence)';
		}

		if ( defined( 'KINSTA_DEV_MODE' ) || isset( $_SERVER['KINSTA_CACHE_ZONE'] ) ) {
			return 'Kinsta (High Confidence)';
		}

		if ( isset( $_SERVER['CLOUDWAYS_BUILD'] ) || ( isset( $_SERVER['HTTP_X_CLOUDWAYS_AGENT'] ) ) ) {
			return 'Cloudways (High Confidence)';
		}

		if ( defined( 'PRESSABLE_SITE_ID' ) || isset( $_SERVER['PRESSABLE_SITE_ID'] ) ) {
			return 'Pressable (High Confidence)';
		}

		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$srv = strtolower( $_SERVER['SERVER_SOFTWARE'] );
			if ( strpos( $srv, 'litespeed' ) !== false ) {
				return 'LiteSpeed Server (Hostinger/Namecheap/Generic)';
			}
			if ( strpos( $srv, 'nginx' ) !== false ) {
				return 'Nginx Web Server';
			}
			if ( strpos( $srv, 'apache' ) !== false ) {
				return 'Apache Web Server';
			}
		}

		return 'Generic / Self-Hosted';
	}
}
