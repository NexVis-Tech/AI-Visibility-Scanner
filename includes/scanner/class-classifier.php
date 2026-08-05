<?php
namespace AIVisibilityScanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page and Site Classifier Layer for Conditional Schema Expansion.
 * Evaluates structural signals before running Product, LocalBusiness, and Review schema checks.
 */
class Classifier {

	/**
	 * Run site-level classification once per scan.
	 *
	 * @return array
	 */
	public static function classify_site(): array {
		$is_local_business = (bool) get_option( 'avs_is_local_business', false );
		$auto_suggested    = (bool) get_option( 'avs_local_business_auto_suggested', false );

		// If local business option has never been saved/chosen, run auto-detection to set default suggestion
		if ( false === get_option( 'avs_is_local_business', false ) && false === get_option( 'avs_local_business_user_set', false ) ) {
			$detected_signal = self::detect_local_business_signals();
			if ( $detected_signal ) {
				$auto_suggested = true;
				update_option( 'avs_local_business_auto_suggested', true );
			}
		}

		return array(
			'is_local_business'         => $is_local_business,
			'local_business_suggested'  => $auto_suggested,
		);
	}

	/**
	 * Run page-level classification for a specific page URL and HTML body.
	 *
	 * @param string $page_url
	 * @param string $html_body
	 * @param array  $site_context
	 * @return array
	 */
	public static function classify_page( string $page_url, string $html_body, array $site_context ): array {
		$post_id   = url_to_postid( $page_url );
		$post_type = $post_id > 0 ? get_post_type( $post_id ) : '';

		// 1. Product Classification (Per page, high-confidence structural signals only)
		$default_product_cpts = array( 'product', 'download' );
		$recognized_cpts      = apply_filters( 'avs_product_post_types', $default_product_cpts );
		$is_product_page      = ! empty( $post_type ) && in_array( $post_type, $recognized_cpts, true );

		// 2. LocalBusiness Classification (Site-level setting + targeted pages)
		$is_local_business_enabled = ! empty( $site_context['is_local_business'] );
		$is_localbusiness_target   = false;

		if ( $is_local_business_enabled ) {
			$front_page_id = (int) get_option( 'page_on_front' );
			$is_home       = ( $post_id > 0 && $post_id === $front_page_id )
				|| rtrim( $page_url, '/' ) === rtrim( get_site_url(), '/' )
				|| '/' === wp_parse_url( $page_url, PHP_URL_PATH );

			$slug = '';
			if ( $post_id > 0 ) {
				$post_obj = get_post( $post_id );
				$slug     = $post_obj ? $post_obj->post_name : '';
			} else {
				$path = trim( wp_parse_url( $page_url, PHP_URL_PATH ), '/' );
				$slug = strtolower( basename( $path ) );
			}

			$default_local_slugs = array( 'contact', 'contact-us', 'about', 'about-us', 'location', 'locations' );
			$recognized_slugs    = apply_filters( 'avs_local_business_slugs', $default_local_slugs );

			if ( $is_home || in_array( strtolower( $slug ), $recognized_slugs, true ) ) {
				$is_localbusiness_target = true;
			}
		}

		// 3. Review Classification (Per page, plugin and content driven)
		$has_visible_reviews = false;

		// WooCommerce review rating setting check
		if ( 'product' === $post_type && 'yes' === get_option( 'woocommerce_enable_review_rating', 'no' ) ) {
			$has_visible_reviews = true;
		}

		// Content markup patterns (star rating CSS classes or existing schema)
		if ( ! $has_visible_reviews && ! empty( $html_body ) ) {
			$has_rating_markup = (bool) preg_match( '/\b(star-rating|rating-stars|review-rating|user-rating|wc-star-rating)\b/i', $html_body )
				|| ( false !== strpos( $html_body, 'itemprop="ratingValue"' ) )
				|| ( false !== strpos( $html_body, 'itemprop="reviewRating"' ) );

			$has_schema_reviews = ( false !== strpos( $html_body, '"Review"' ) || false !== strpos( $html_body, '"AggregateRating"' ) );

			if ( $has_rating_markup || $has_schema_reviews ) {
				$has_visible_reviews = true;
			}
		}

		return array(
			'is_product_page'         => $is_product_page,
			'is_localbusiness_target' => $is_localbusiness_target,
			'has_visible_reviews'     => $has_visible_reviews,
			'post_id'                 => $post_id,
			'post_type'               => $post_type,
		);
	}

	/**
	 * Detect site-wide LocalBusiness signals for auto-suggesting the settings toggle.
	 *
	 * @return bool
	 */
	public static function detect_local_business_signals(): bool {
		// Signal 1: Active Local SEO plugins with configured data
		if ( get_option( 'wpseo_local' ) || get_option( 'rank_math_local_seo' ) || class_exists( 'Yoast_SEO_Local' ) ) {
			return true;
		}

		// Signal 2: Check Home, Contact, or About page content for Google Maps or NAP patterns
		$front_id   = (int) get_option( 'page_on_front' );
		$check_ids  = array();
		if ( $front_id > 0 ) {
			$check_ids[] = $front_id;
		}

		$contact_page = get_page_by_path( 'contact' ) ?: get_page_by_path( 'contact-us' );
		if ( $contact_page ) {
			$check_ids[] = $contact_page->ID;
		}

		$about_page = get_page_by_path( 'about' ) ?: get_page_by_path( 'about-us' );
		if ( $about_page ) {
			$check_ids[] = $about_page->ID;
		}

		foreach ( array_unique( $check_ids ) as $pid ) {
			$post = get_post( $pid );
			if ( $post && ! empty( $post->post_content ) ) {
				$content = $post->post_content;
				if ( false !== strpos( $content, 'maps.google.com' ) || false !== strpos( $content, 'google.com/maps' ) ) {
					return true;
				}
				// Basic phone & postal pattern near each other
				if ( preg_match( '/\b\+?\d{1,3}?[-.\s]?\(?\d{2,4}?\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}\b/', $content ) && preg_match( '/\b\d{5}(-\d{4})?\b/', $content ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
