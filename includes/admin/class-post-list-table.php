<?php
namespace AIVisibilityScanner\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scoring\Scoring_Engine;

/**
 * Custom columns and metadata cache display in WP Posts/Pages list tables.
 */
class Post_List_Table {

	/**
	 * Target post types.
	 * @var array
	 */
	protected $post_types = array( 'post', 'page' );

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		foreach ( $this->post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'register_sortable_column' ) );
		}
		add_action( 'pre_get_posts', array( $this, 'handle_column_sorting' ) );
	}

	/**
	 * Add "AI Visibility" column header.
	 */
	public function add_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['avs_score'] = __( 'AI Visibility', 'ai-visibility-scanner' );
			}
			$new_columns[ $key ] = $title;
		}
		if ( ! isset( $new_columns['avs_score'] ) ) {
			$new_columns['avs_score'] = __( 'AI Visibility', 'ai-visibility-scanner' );
		}
		return $new_columns;
	}

	/**
	 * Register sortable column.
	 */
	public function register_sortable_column( $columns ) {
		$columns['avs_score'] = 'avs_score';
		return $columns;
	}

	/**
	 * Display column cell content.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'avs_score' !== $column ) {
			return;
		}

		$score      = get_post_meta( $post_id, '_avs_score', true );
		$updated_at = get_post_meta( $post_id, '_avs_score_updated_at', true );

		if ( '' === $score || false === $score || ! $updated_at ) {
			echo '<span class="avs-badge-column not-scanned" title="' . esc_attr__( 'Not yet scanned', 'ai-visibility-scanner' ) . '">—</span>';
			return;
		}

		$score = (int) $score;
		$thresholds = apply_filters( 'avs_score_thresholds', array( 80, 50 ) );
		$good = $thresholds[0];
		$warn = $thresholds[1];

		if ( $score >= $good ) {
			$class = 'good';
		} elseif ( $score >= $warn ) {
			$class = 'warn';
		} else {
			$class = 'bad';
		}

		$top_issue = get_post_meta( $post_id, '_avs_top_issue', true );
		if ( empty( $top_issue ) ) {
			$tooltip = sprintf( _x( '%1$d/100 — All checks passing', 'list table column tooltip when no issues', 'ai-visibility-scanner' ), $score );
		} else {
			$tooltip = sprintf( '%d/100 — %s', $score, $top_issue );
		}

		$post_url   = get_permalink( $post_id );
		$report_url = admin_url( 'admin.php?page=avs-report&search=' . rawurlencode( $post_url ) );

		printf(
			'<a href="%1$s" class="avs-badge-column avs-badge-column-%2$s" title="%3$s">%4$d</a>',
			esc_url( $report_url ),
			esc_attr( $class ),
			esc_attr( $tooltip ),
			$score
		);
	}

	/**
	 * Custom query logic for sortable column.
	 */
	public function handle_column_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'avs_score' === $orderby ) {
			$query->set( 'meta_key', '_avs_score' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
}
