<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Report\Report_Builder;

$scan_id = isset( $_GET['scan_id'] ) ? absint( $_GET['scan_id'] ) : 0;
$report  = Report_Builder::get_report( $scan_id );
?>
<div class="wrap avs-wrap">
	<div class="avs-top-bar">
		<div>
			<h1 class="avs-heading"><?php esc_html_e( 'AI Visibility Audit Report', 'ai-visibility-scanner' ); ?></h1>
			<p class="avs-subtitle"><?php esc_html_e( 'Deep analysis of website crawlability, schema entities, content extractability, and LLM answer engine readiness.', 'ai-visibility-scanner' ); ?></p>
		</div>
		<div class="avs-top-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-visibility-scanner' ) ); ?>" class="button button-secondary">
				← <?php esc_html_e( 'Back to Dashboard', 'ai-visibility-scanner' ); ?>
			</a>
		</div>
	</div>

	<?php if ( ! $report ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'No completed audit report found. Please run a scan from the Dashboard.', 'ai-visibility-scanner' ); ?></p></div>
	<?php else : 
		$score = $report['composite_score'];
		$score_class = $score >= 80 ? 'good' : ( $score >= 50 ? 'warn' : 'bad' );
		$score_label = $score >= 80 ? __( 'Excellent AI Visibility', 'ai-visibility-scanner' ) : ( $score >= 50 ? __( 'Needs Improvement', 'ai-visibility-scanner' ) : __( 'Critical AI Invisibility', 'ai-visibility-scanner' ) );
		$counts = $report['summary_counts'];
	?>

		<!-- Executive Overview Card -->
		<div class="avs-report-header avs-card">
			<div class="avs-report-score-block">
				<div class="avs-main-score avs-score-<?php echo esc_attr( $score_class ); ?>">
					<div class="avs-score-val-wrap">
						<span class="avs-score-num"><?php echo esc_html( $score ); ?></span>
						<span class="avs-score-sub">/100</span>
					</div>
				</div>
				<div class="avs-main-score-label">
					<h3><?php echo esc_html( $score_label ); ?></h3>
					<p class="avs-scan-meta">
						<strong><?php echo esc_html( $report['pages_scanned'] ); ?></strong> <?php esc_html_e( 'pages audited on', 'ai-visibility-scanner' ); ?> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report['completed_at'] ) ) ); ?>
					</p>
					
					<!-- Metric Pills -->
					<div class="avs-summary-pills">
						<span class="avs-pill avs-pill-total"><?php printf( esc_html__( '%d Total Checks', 'ai-visibility-scanner' ), $counts['total_checks'] ); ?></span>
						<span class="avs-pill avs-pill-fail"><?php printf( esc_html__( '%d Critical', 'ai-visibility-scanner' ), $counts['fail'] ); ?></span>
						<span class="avs-pill avs-pill-warn"><?php printf( esc_html__( '%d Warnings', 'ai-visibility-scanner' ), $counts['warn'] ); ?></span>
						<span class="avs-pill avs-pill-pass"><?php printf( esc_html__( '%d Passed', 'ai-visibility-scanner' ), $counts['pass'] ); ?></span>
					</div>
				</div>
			</div>

			<!-- Subscores Breakdown Grid -->
			<div class="avs-subscores-grid">
				<?php 
				$subscore_config = array(
					'crawlability' => array( 'label' => __( 'Crawlability & Bots', 'ai-visibility-scanner' ), 'icon' => '🤖' ),
					'schema'       => array( 'label' => __( 'Schema & Data', 'ai-visibility-scanner' ), 'icon' => '🏷️' ),
					'content'      => array( 'label' => __( 'Content Extractability', 'ai-visibility-scanner' ), 'icon' => '📝' ),
					'experience'   => array( 'label' => __( 'User & Bot Experience', 'ai-visibility-scanner' ), 'icon' => '⚡' ),
				);
				foreach ( $report['subscores'] as $key => $val ) :
					$sub_cls = $val >= 80 ? 'good' : ( $val >= 50 ? 'warn' : 'bad' );
					$cfg = $subscore_config[ $key ] ?? array( 'label' => ucfirst( $key ), 'icon' => '📊' );
				?>
					<div class="avs-subscore-box">
						<div class="avs-subscore-header">
							<span class="avs-subscore-icon"><?php echo esc_html( $cfg['icon'] ); ?></span>
							<span class="avs-subscore-title"><?php echo esc_html( $cfg['label'] ); ?></span>
						</div>
						<div class="avs-subscore-val avs-color-<?php echo esc_attr( $sub_cls ); ?>">
							<?php echo esc_html( $val ); ?><span>/100</span>
						</div>
						<div class="avs-mini-bar">
							<div class="avs-mini-bar-fill avs-bg-<?php echo esc_attr( $sub_cls ); ?>" style="width: <?php echo esc_attr( $val ); ?>%;"></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Interactive Filter & Search Controls Bar -->
		<div class="avs-card avs-controls-card" style="margin-top: 20px;">
			<div class="avs-filter-bar">
				<div class="avs-search-wrapper">
					<span class="dashicons dashicons-search"></span>
					<input type="text" id="avs-filter-search" class="avs-input-search" placeholder="<?php esc_attr_e( 'Search checks, URLs, or evidence...', 'ai-visibility-scanner' ); ?>" />
				</div>
				<div class="avs-filter-group">
					<label for="avs-filter-status"><?php esc_html_e( 'Status:', 'ai-visibility-scanner' ); ?></label>
					<select id="avs-filter-status" class="avs-select-filter">
						<option value="all"><?php esc_html_e( 'All Statuses', 'ai-visibility-scanner' ); ?></option>
						<option value="fail"><?php esc_html_e( 'Critical (FAIL)', 'ai-visibility-scanner' ); ?></option>
						<option value="warn"><?php esc_html_e( 'Warnings (WARN)', 'ai-visibility-scanner' ); ?></option>
						<option value="pass"><?php esc_html_e( 'Passed (PASS)', 'ai-visibility-scanner' ); ?></option>
					</select>
				</div>
				<div class="avs-filter-group">
					<label for="avs-filter-category"><?php esc_html_e( 'Category:', 'ai-visibility-scanner' ); ?></label>
					<select id="avs-filter-category" class="avs-select-filter">
						<option value="all"><?php esc_html_e( 'All Categories', 'ai-visibility-scanner' ); ?></option>
						<option value="crawlability"><?php esc_html_e( 'Crawlability', 'ai-visibility-scanner' ); ?></option>
						<option value="schema"><?php esc_html_e( 'Schema & Data', 'ai-visibility-scanner' ); ?></option>
						<option value="content"><?php esc_html_e( 'Content Extractability', 'ai-visibility-scanner' ); ?></option>
						<option value="experience"><?php esc_html_e( 'Experience', 'ai-visibility-scanner' ); ?></option>
					</select>
				</div>
				<button id="avs-filter-reset" class="button button-small"><?php esc_html_e( 'Reset Filters', 'ai-visibility-scanner' ); ?></button>
			</div>
		</div>

		<!-- View Tab Navigation -->
		<div class="avs-tabs-container">
			<ul class="avs-tabs-menu">
				<li class="avs-tab-item active" data-tab="tab-fixes">
					🎯 <?php esc_html_e( 'Action Plan', 'ai-visibility-scanner' ); ?>
					<span class="avs-tab-badge avs-badge-fixes"><?php echo esc_html( count( $report['prioritized_fixes'] ) ); ?></span>
				</li>
				<li class="avs-tab-item" data-tab="tab-pages">
					📄 <?php esc_html_e( 'Page-by-Page Audit', 'ai-visibility-scanner' ); ?>
					<span class="avs-tab-badge"><?php echo esc_html( count( $report['pages_map'] ) ); ?></span>
				</li>
				<li class="avs-tab-item" data-tab="tab-all">
					📋 <?php esc_html_e( 'All Check Results', 'ai-visibility-scanner' ); ?>
					<span class="avs-tab-badge"><?php echo esc_html( count( $report['results'] ) ); ?></span>
				</li>
			</ul>
		</div>

		<!-- TAB 1: Action Plan (Prioritized Fixes) -->
		<div id="tab-fixes" class="avs-tab-content active">
			<div class="avs-card">
				<div class="avs-section-header">
					<h2>🎯 <?php esc_html_e( 'Prioritized Action Plan (High Impact First)', 'ai-visibility-scanner' ); ?></h2>
					<p class="avs-section-desc"><?php esc_html_e( 'Recommended fixes sorted by high impact and low effort to maximize your site’s visibility to AI answer engines.', 'ai-visibility-scanner' ); ?></p>
				</div>

				<?php if ( ! empty( $report['prioritized_fixes'] ) ) : ?>
					<div class="avs-accordion-group" id="avs-fixes-list">
						<?php foreach ( $report['prioritized_fixes'] as $index => $fix ) : ?>
							<div class="avs-accordion-item avs-fix-card avs-item-row" 
								 data-status="<?php echo esc_attr( $fix->result ); ?>" 
								 data-category="<?php echo esc_attr( $fix->category ); ?>"
								 data-search="<?php echo esc_attr( strtolower( $fix->check_slug . ' ' . $fix->page_url . ' ' . $fix->evidence ) ); ?>">
								
								<div class="avs-accordion-header">
									<div class="avs-accordion-title-block">
										<span class="avs-badge avs-badge-<?php echo esc_attr( $fix->result ); ?>"><?php echo esc_html( strtoupper( $fix->result ) ); ?></span>
										<code class="avs-cat-tag"><?php echo esc_html( $fix->category ); ?></code>
										<strong class="avs-check-title"><?php echo esc_html( str_replace( '_', ' ', ucfirst( $fix->check_slug ) ) ); ?></strong>
										<span class="avs-fix-url" title="<?php echo esc_attr( $fix->page_url ); ?>"><?php echo esc_html( $fix->page_url ); ?></span>
									</div>
									<div class="avs-accordion-meta">
										<span class="avs-impact-pill"><?php printf( esc_html__( 'Impact: %d/5', 'ai-visibility-scanner' ), $fix->impact_score ); ?></span>
										<span class="avs-accordion-toggle-icon">▼</span>
									</div>
								</div>

								<div class="avs-accordion-body">
									<div class="avs-fix-details-grid">
										<div class="avs-evidence-box">
											<strong>🔍 <?php esc_html_e( 'Observed Evidence & Findings:', 'ai-visibility-scanner' ); ?></strong>
											<p><?php echo esc_html( $fix->evidence ); ?></p>
										</div>
										<div class="avs-recommendation-box">
											<strong>💡 <?php esc_html_e( 'Actionable Solution:', 'ai-visibility-scanner' ); ?></strong>
											<p><?php echo esc_html( $fix->fix_hint ); ?></p>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div id="avs-no-fixes-matched" class="avs-no-results-msg" style="display: none;">
						<p>🔍 <?php esc_html_e( 'No action plan items match your current search/filter settings.', 'ai-visibility-scanner' ); ?></p>
					</div>
				<?php else : ?>
					<div class="avs-pass-all">
						<h3>🎉 <?php esc_html_e( 'All Check Items Passed Cleanly!', 'ai-visibility-scanner' ); ?></h3>
						<p><?php esc_html_e( 'No critical or warning issues were detected during this audit run.', 'ai-visibility-scanner' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- TAB 2: Page-by-Page Audit -->
		<div id="tab-pages" class="avs-tab-content">
			<div class="avs-card">
				<div class="avs-section-header">
					<h2>📄 <?php esc_html_e( 'Page-by-Page Audit Breakdown', 'ai-visibility-scanner' ); ?></h2>
					<p class="avs-section-desc"><?php esc_html_e( 'View audit results grouped by URL to evaluate individual page readiness.', 'ai-visibility-scanner' ); ?></p>
				</div>

				<div class="avs-accordion-group" id="avs-pages-list">
					<?php foreach ( $report['pages_map'] as $url => $pdata ) : 
						$pscore = $pdata['score'];
						$pclass = $pscore >= 80 ? 'good' : ( $pscore >= 50 ? 'warn' : 'bad' );
					?>
						<div class="avs-accordion-item avs-page-card avs-item-row"
							 data-search="<?php echo esc_attr( strtolower( $url ) ); ?>">
							
							<div class="avs-accordion-header">
								<div class="avs-page-header-left">
									<div class="avs-page-score-badge avs-score-<?php echo esc_attr( $pclass ); ?>">
										<?php echo esc_html( $pscore ); ?>
									</div>
									<div>
										<h3 class="avs-page-title"><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></h3>
										<div class="avs-page-counts-row">
											<span class="avs-count-tag avs-tag-fail">❌ <?php echo esc_html( $pdata['fail_count'] ); ?> <?php esc_html_e( 'Fail', 'ai-visibility-scanner' ); ?></span>
											<span class="avs-count-tag avs-tag-warn">⚠️ <?php echo esc_html( $pdata['warn_count'] ); ?> <?php esc_html_e( 'Warn', 'ai-visibility-scanner' ); ?></span>
											<span class="avs-count-tag avs-tag-pass">✅ <?php echo esc_html( $pdata['pass_count'] ); ?> <?php esc_html_e( 'Pass', 'ai-visibility-scanner' ); ?></span>
										</div>
									</div>
								</div>
								<div class="avs-accordion-meta">
									<span class="avs-accordion-toggle-icon">▼</span>
								</div>
							</div>

							<div class="avs-accordion-body">
								<table class="wp-list-table widefat fixed striped avs-mini-table">
									<thead>
										<tr>
											<th style="width: 15%;"><?php esc_html_e( 'Category', 'ai-visibility-scanner' ); ?></th>
											<th style="width: 25%;"><?php esc_html_e( 'Check Name', 'ai-visibility-scanner' ); ?></th>
											<th style="width: 12%;"><?php esc_html_e( 'Result', 'ai-visibility-scanner' ); ?></th>
											<th style="width: 48%;"><?php esc_html_e( 'Evidence & Fix Recommendation', 'ai-visibility-scanner' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $pdata['results'] as $row ) : ?>
											<tr class="avs-item-row" data-status="<?php echo esc_attr( $row->result ); ?>" data-category="<?php echo esc_attr( $row->category ); ?>" data-search="<?php echo esc_attr( strtolower( $row->check_slug . ' ' . $row->evidence ) ); ?>">
												<td><code class="avs-cat-tag"><?php echo esc_html( $row->category ); ?></code></td>
												<td><strong><?php echo esc_html( str_replace( '_', ' ', ucfirst( $row->check_slug ) ) ); ?></strong></td>
												<td><span class="avs-badge avs-badge-<?php echo esc_attr( $row->result ); ?>"><?php echo esc_html( strtoupper( $row->result ) ); ?></span></td>
												<td>
													<div><?php echo esc_html( $row->evidence ); ?></div>
													<?php if ( 'pass' !== $row->result && ! empty( $row->fix_hint ) ) : ?>
														<div class="avs-mini-hint">💡 <strong><?php esc_html_e( 'Fix:', 'ai-visibility-scanner' ); ?></strong> <?php echo esc_html( $row->fix_hint ); ?></div>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<!-- TAB 3: All Check Results Table (Filterable & Paginated) -->
		<div id="tab-all" class="avs-tab-content">
			<div class="avs-card">
				<div class="avs-section-header">
					<h2>📋 <?php esc_html_e( 'Complete Check Results Log', 'ai-visibility-scanner' ); ?></h2>
					<p class="avs-section-desc"><?php esc_html_e( 'Examine raw output logs for all automated checks executed across the site.', 'ai-visibility-scanner' ); ?></p>
				</div>

				<div class="avs-table-responsive">
					<table class="wp-list-table widefat fixed striped" id="avs-all-results-table">
						<thead>
							<tr>
								<th style="width: 14%;"><?php esc_html_e( 'Category', 'ai-visibility-scanner' ); ?></th>
								<th style="width: 22%;"><?php esc_html_e( 'Check Name', 'ai-visibility-scanner' ); ?></th>
								<th style="width: 28%;"><?php esc_html_e( 'Page URL', 'ai-visibility-scanner' ); ?></th>
								<th style="width: 10%;"><?php esc_html_e( 'Result', 'ai-visibility-scanner' ); ?></th>
								<th style="width: 26%;"><?php esc_html_e( 'Evidence', 'ai-visibility-scanner' ); ?></th>
							</tr>
						</thead>
						<tbody id="avs-all-results-tbody">
							<?php foreach ( $report['results'] as $row ) : ?>
								<tr class="avs-table-row avs-item-row" 
									data-status="<?php echo esc_attr( $row->result ); ?>" 
									data-category="<?php echo esc_attr( $row->category ); ?>"
									data-search="<?php echo esc_attr( strtolower( $row->check_slug . ' ' . $row->page_url . ' ' . $row->evidence ) ); ?>">
									<td><code class="avs-cat-tag"><?php echo esc_html( $row->category ); ?></code></td>
									<td><strong><?php echo esc_html( str_replace( '_', ' ', ucfirst( $row->check_slug ) ) ); ?></strong></td>
									<td><a href="<?php echo esc_url( $row->page_url ); ?>" target="_blank" class="avs-table-url" title="<?php echo esc_attr( $row->page_url ); ?>"><?php echo esc_html( $row->page_url ); ?></a></td>
									<td><span class="avs-badge avs-badge-<?php echo esc_attr( $row->result ); ?>"><?php echo esc_html( strtoupper( $row->result ) ); ?></span></td>
									<td class="avs-evidence-col"><?php echo esc_html( $row->evidence ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Pagination Bar -->
				<div class="avs-pagination-bar">
					<div class="avs-pagination-info">
						<span id="avs-page-info"><?php esc_html_e( 'Showing 1-15 of results', 'ai-visibility-scanner' ); ?></span>
					</div>
					<div class="avs-pagination-controls">
						<label for="avs-per-page-select"><?php esc_html_e( 'Per page:', 'ai-visibility-scanner' ); ?></label>
						<select id="avs-per-page-select" class="avs-select-filter" style="width: 70px; margin-right: 15px;">
							<option value="15">15</option>
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>

						<button id="avs-prev-page" class="button button-small" disabled>« <?php esc_html_e( 'Prev', 'ai-visibility-scanner' ); ?></button>
						<span id="avs-current-page-num" style="margin: 0 8px; font-weight: 600;">1</span>
						<button id="avs-next-page" class="button button-small"><?php esc_html_e( 'Next', 'ai-visibility-scanner' ); ?> »</button>
					</div>
				</div>
			</div>
		</div>

		<div class="avs-report-footer-credit" style="margin-top: 25px; text-align: center; color: #64748b; font-size: 13px;">
			<p>
				<?php esc_html_e( 'AI Visibility Scanner Audit Report — Developed by ', 'ai-visibility-scanner' ); ?>
				<a href="https://nexvistech.com" target="_blank" style="text-decoration: none; font-weight: 600; color: #2563eb;">NexVis Technologies</a>
				<?php esc_html_e( ' & ', 'ai-visibility-scanner' ); ?>
				<a href="https://github.com/mudassarijaz" target="_blank" style="text-decoration: none; font-weight: 600; color: #2563eb;">Mudassar Ijaz</a>
			</p>
		</div>

	<?php endif; ?>
</div>
