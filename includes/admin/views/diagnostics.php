<?php
/**
 * Scan Diagnostics & Developer Log Panel View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_scans = $wpdb->prefix . 'avs_scans';
$recent_scans = $wpdb->get_results( "SELECT id, site_url, status, composite_score, started_at FROM {$table_scans} ORDER BY id DESC LIMIT 20" );

$current_scan_id = isset( $_GET['scan_id'] ) ? (int) $_GET['scan_id'] : ( ! empty( $recent_scans ) ? (int) $recent_scans[0]->id : null );

$environment = \AIVisibilityScanner\Scanner\Diagnostics_Logger::get_environment( $current_scan_id );
$diagnostics = \AIVisibilityScanner\Scanner\Diagnostics_Logger::get_diagnostics( $current_scan_id );

// Count issues for priority badge
$issue_count = 0;
foreach ( $diagnostics as $d ) {
	if ( 'none' !== $d->error_type ) {
		$issue_count++;
	}
}

?>
<div class="wrap avs-wrap avs-diagnostics-wrap">
	<div class="avs-top-bar">
		<div>
			<h1 class="avs-heading"><?php esc_html_e( 'Scan Diagnostics & Infrastructure Logs', 'ai-visibility-scanner' ); ?></h1>
			<p class="avs-subtitle"><?php esc_html_e( 'Inspect fetch strategies, response headers, WAF/Cloudflare interstitials, and environmental footprints across test runs.', 'ai-visibility-scanner' ); ?></p>
		</div>
		<div class="avs-top-actions">
			<?php if ( $current_scan_id ) : ?>
				<button type="button" class="button" id="avs-export-json-btn" data-scan-id="<?php echo esc_attr( $current_scan_id ); ?>">
					<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Export Log JSON', 'ai-visibility-scanner' ); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="button button-primary" id="avs-run-selftest-btn">
				<span class="dashicons dashicons-dashboard" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Run Connectivity Self-Test', 'ai-visibility-scanner' ); ?>
			</button>
		</div>
	</div>

	<!-- Scan Selector Header Bar -->
	<div class="avs-card avs-scan-selector-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="avs-scan-selector-form">
			<input type="hidden" name="page" value="avs-diagnostics" />
			<label for="avs-scan-select" class="avs-label-bold"><?php esc_html_e( 'Select Scan Run:', 'ai-visibility-scanner' ); ?></label>
			<select name="scan_id" id="avs-scan-select" onchange="this.form.submit()">
				<?php if ( empty( $recent_scans ) ) : ?>
					<option value=""><?php esc_html_e( 'No scans recorded yet', 'ai-visibility-scanner' ); ?></option>
				<?php else : ?>
					<?php foreach ( $recent_scans as $scan ) : ?>
						<option value="<?php echo esc_attr( $scan->id ); ?>" <?php selected( $current_scan_id, $scan->id ); ?>>
							Scan #<?php echo esc_html( $scan->id ); ?> &mdash; <?php echo esc_html( $scan->started_at ); ?> (Score: <?php echo null !== $scan->composite_score ? esc_html( $scan->composite_score ) . '/100' : 'N/A'; ?>)
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<?php if ( $issue_count > 0 ) : ?>
				<span class="avs-badge avs-badge-danger-glow" style="margin-left: 15px;">
					<span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
					<?php printf( esc_html__( '%d Issues / Errors Prioritized', 'ai-visibility-scanner' ), $issue_count ); ?>
				</span>
			<?php else : ?>
				<span class="avs-badge avs-badge-success" style="margin-left: 15px;">
					<span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
					<?php esc_html_e( 'No Network/WAF Errors Detected', 'ai-visibility-scanner' ); ?>
				</span>
			<?php endif; ?>
		</form>
	</div>

	<!-- Standalone Connectivity Self-Test Output Area -->
	<div id="avs-selftest-container" style="display: none;" class="avs-card avs-card-primary">
		<div class="avs-card-header">
			<h3 class="avs-card-title"><?php esc_html_e( 'Connectivity Self-Test Results', 'ai-visibility-scanner' ); ?></h3>
			<span class="avs-timestamp" id="avs-selftest-time"></span>
		</div>
		<div id="avs-selftest-cards-grid" class="avs-selftest-grid"></div>
	</div>

	<!-- Section 1: Environment Fingerprint Card -->
	<div class="avs-card avs-env-card">
		<div class="avs-card-header">
			<h3 class="avs-card-title">
				<span class="dashicons dashicons-admin-network" style="margin-right: 6px;"></span>
				<?php esc_html_e( 'Environment Fingerprint', 'ai-visibility-scanner' ); ?>
			</h3>
			<span class="avs-badge avs-badge-secondary">
				<?php echo $environment ? esc_html( $environment->site_url_snapshot ) : esc_html( get_site_url() ); ?>
			</span>
		</div>

		<?php if ( ! $environment ) : ?>
			<p class="avs-empty-state"><?php esc_html_e( 'No environment fingerprint recorded for this scan. Run a new scan or connectivity test to capture environment signatures.', 'ai-visibility-scanner' ); ?></p>
		<?php else : ?>
			<?php
				$builders = json_decode( $environment->active_page_builders, true );
				$security = json_decode( $environment->active_security_plugins, true );
				$cache    = json_decode( $environment->active_cache_plugins, true );
				$seo      = json_decode( $environment->active_seo_plugins, true );
			?>
			<div class="avs-env-grid">
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'WordPress / PHP', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val">WP <?php echo esc_html( $environment->wp_version ); ?> / PHP <?php echo esc_html( $environment->php_version ); ?></span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'Active Theme', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val"><?php echo esc_html( $environment->active_theme ); ?></span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'Page Builders', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val"><?php echo ! empty( $builders ) ? esc_html( implode( ', ', $builders ) ) : 'None detected'; ?></span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'Security Plugins', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val"><?php echo ! empty( $security ) ? esc_html( implode( ', ', $security ) ) : 'None detected'; ?></span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'Caching Plugins', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val"><?php echo ! empty( $cache ) ? esc_html( implode( ', ', $cache ) ) : 'None detected'; ?></span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'SEO Plugins', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val"><?php echo ! empty( $seo ) ? esc_html( implode( ', ', $seo ) ) : 'None detected'; ?></span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'Cloudflare Status', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val">
						<?php if ( $environment->cloudflare_detected ) : ?>
							<span class="avs-pill avs-pill-warn"><?php esc_html_e( 'Cloudflare Active', 'ai-visibility-scanner' ); ?></span>
						<?php else : ?>
							<span class="avs-pill avs-pill-neutral"><?php esc_html_e( 'Not Detected', 'ai-visibility-scanner' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'Hosting Signature', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val"><?php echo esc_html( $environment->hosting_signature_guess ); ?></span>
				</div>
				<div class="avs-env-metric">
					<span class="avs-env-label"><?php esc_html_e( 'Loopback Status', 'ai-visibility-scanner' ); ?></span>
					<span class="avs-env-val">
						<?php if ( 'ok' === $environment->loopback_connectivity ) : ?>
							<span class="avs-pill avs-pill-success"><?php esc_html_e( 'OK (Reachable)', 'ai-visibility-scanner' ); ?></span>
						<?php elseif ( 'failed' === $environment->loopback_connectivity ) : ?>
							<span class="avs-pill avs-pill-danger"><?php esc_html_e( 'FAILED (Blocked)', 'ai-visibility-scanner' ); ?></span>
						<?php else : ?>
							<span class="avs-pill avs-pill-neutral"><?php esc_html_e( 'Not Tested', 'ai-visibility-scanner' ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Navigation Tabs inside Diagnostics Panel -->
	<div class="avs-tabs-nav">
		<button class="avs-diag-tab-item active" data-tab="tab-diag-logs">
			<span class="dashicons dashicons-list-view"></span>
			<?php esc_html_e( 'Fetch Diagnostics Log', 'ai-visibility-scanner' ); ?>
			<?php if ( $issue_count > 0 ) : ?>
				<span class="avs-tab-count avs-count-danger"><?php echo esc_html( $issue_count ); ?></span>
			<?php endif; ?>
		</button>
		<button class="avs-diag-tab-item" data-tab="tab-diag-diff">
			<span class="dashicons dashicons-randomize"></span>
			<?php esc_html_e( 'Scan Comparison / Diff View', 'ai-visibility-scanner' ); ?>
		</button>
		<button class="avs-diag-tab-item" data-tab="tab-diag-adhoc">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Ad-Hoc Single-URL Tester', 'ai-visibility-scanner' ); ?>
		</button>
	</div>

	<!-- TAB 1: Diagnostic Log Table -->
	<div id="tab-diag-logs" class="avs-diag-tab-content active">
		<div class="avs-card">
			<div class="avs-filter-bar">
				<div class="avs-filter-group">
					<label class="avs-label-bold"><?php esc_html_e( 'Priority Filter:', 'ai-visibility-scanner' ); ?></label>
					<button type="button" class="button avs-log-filter-btn active" data-filter-type="error" data-filter-val="all">
						<?php esc_html_e( 'All Entries', 'ai-visibility-scanner' ); ?>
					</button>
					<button type="button" class="button avs-log-filter-btn avs-btn-filter-issues" data-filter-type="error" data-filter-val="issues_only">
						<span class="dashicons dashicons-warning" style="font-size: 13px; width: 13px; height: 13px;"></span>
						<?php esc_html_e( '⚠️ Errors & Issues Only', 'ai-visibility-scanner' ); ?>
					</button>
				</div>
				<div class="avs-filter-group">
					<label class="avs-label-bold"><?php esc_html_e( 'Strategy:', 'ai-visibility-scanner' ); ?></label>
					<select id="avs-strategy-filter" class="avs-select-sm">
						<option value="all"><?php esc_html_e( 'All Strategies', 'ai-visibility-scanner' ); ?></option>
						<option value="internal_render"><?php esc_html_e( 'Strategy 1: Internal Render', 'ai-visibility-scanner' ); ?></option>
						<option value="http_loopback"><?php esc_html_e( 'Strategy 2: HTTP Loopback', 'ai-visibility-scanner' ); ?></option>
						<option value="http_external"><?php esc_html_e( 'Strategy 3: External Fallback', 'ai-visibility-scanner' ); ?></option>
					</select>
				</div>
			</div>

			<div class="avs-table-responsive">
				<table class="wp-list-table widefat fixed striped table-view-list avs-diag-table">
					<thead>
						<tr>
							<th style="width: 140px;"><?php esc_html_e( 'Check Slug', 'ai-visibility-scanner' ); ?></th>
							<th><?php esc_html_e( 'Target Page URL', 'ai-visibility-scanner' ); ?></th>
							<th style="width: 130px;"><?php esc_html_e( 'Strategy', 'ai-visibility-scanner' ); ?></th>
							<th style="width: 90px;"><?php esc_html_e( 'HTTP Code', 'ai-visibility-scanner' ); ?></th>
							<th style="width: 90px;"><?php esc_html_e( 'Time (ms)', 'ai-visibility-scanner' ); ?></th>
							<th style="width: 160px;"><?php esc_html_e( 'Error Classification', 'ai-visibility-scanner' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Fallback', 'ai-visibility-scanner' ); ?></th>
							<th style="width: 70px;"><?php esc_html_e( 'Action', 'ai-visibility-scanner' ); ?></th>
						</tr>
					</thead>
					<tbody id="avs-diag-table-body">
						<?php if ( empty( $diagnostics ) ) : ?>
							<tr>
								<td colspan="8" class="avs-text-center avs-py-4">
									<?php esc_html_e( 'No diagnostic log entries recorded for this scan.', 'ai-visibility-scanner' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $diagnostics as $row ) : ?>
								<?php
									$is_issue = 'none' !== $row->error_type;
									$row_class = $is_issue ? 'avs-row-issue' : '';
								?>
								<tr class="avs-diag-row <?php echo esc_attr( $row_class ); ?>"
									data-error-type="<?php echo esc_attr( $row->error_type ); ?>"
									data-strategy="<?php echo esc_attr( $row->fetch_strategy ); ?>"
									data-is-issue="<?php echo $is_issue ? '1' : '0'; ?>">

									<td><code><?php echo esc_html( $row->check_slug ? $row->check_slug : 'System' ); ?></code></td>
									<td>
										<span class="avs-url-truncate" title="<?php echo esc_attr( $row->target_url ); ?>">
											<?php echo esc_html( $row->target_url ); ?>
										</span>
									</td>
									<td>
										<span class="avs-strategy-tag avs-strat-<?php echo esc_attr( $row->fetch_strategy ); ?>">
											<?php echo esc_html( str_replace( '_', ' ', $row->fetch_strategy ) ); ?>
										</span>
									</td>
									<td>
										<?php if ( $row->response_http_code ) : ?>
											<span class="avs-http-badge avs-http-<?php echo substr( (string) $row->response_http_code, 0, 1 ); ?>xx">
												<?php echo esc_html( $row->response_http_code ); ?>
											</span>
										<?php else : ?>
											<span class="avs-http-badge avs-http-null">ERR</span>
										<?php endif; ?>
									</td>
									<td><code><?php echo esc_html( $row->response_time_ms ); ?> ms</code></td>
									<td>
										<?php if ( 'cloudflare_challenge' === $row->error_type ) : ?>
											<span class="avs-badge avs-badge-cf">Cloudflare Interstitial</span>
										<?php elseif ( 'waf_block_suspected' === $row->error_type ) : ?>
											<span class="avs-badge avs-badge-waf">WAF Block Suspected</span>
										<?php elseif ( 'none' !== $row->error_type ) : ?>
											<span class="avs-badge avs-badge-danger"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row->error_type ) ) ); ?></span>
										<?php else : ?>
											<span class="avs-badge avs-badge-success-subtle">Clean (None)</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $row->fallback_triggered ) : ?>
											<span class="avs-pill avs-pill-warn" title="Fallback from #<?php echo esc_attr( $row->fallback_from_diagnostic_id ); ?>">Yes ↳</span>
										<?php else : ?>
											<span class="avs-text-muted">No</span>
										<?php endif; ?>
									</td>
									<td>
										<button type="button" class="button button-small avs-toggle-detail-btn" data-target="diag-detail-<?php echo esc_attr( $row->id ); ?>">
											<?php esc_html_e( 'Inspect', 'ai-visibility-scanner' ); ?>
										</button>
									</td>
								</tr>

								<!-- Expandable Row Detail -->
								<tr id="diag-detail-<?php echo esc_attr( $row->id ); ?>" class="avs-detail-row" style="display: none;">
									<td colspan="8">
										<div class="avs-detail-box">
											<?php if ( $row->error_message ) : ?>
												<div class="avs-alert avs-alert-danger">
													<strong>Error Message:</strong> <?php echo esc_html( $row->error_message ); ?>
												</div>
											<?php endif; ?>

											<div class="avs-detail-grid">
												<div class="avs-detail-col">
													<h4>Request Headers</h4>
													<pre class="avs-code-box"><code><?php echo esc_html( $row->request_headers ? json_encode( json_decode( $row->request_headers ), JSON_PRETTY_PRINT ) : 'None' ); ?></code></pre>
												</div>

												<div class="avs-detail-col">
													<h4>Response Headers</h4>
													<pre class="avs-code-box"><code><?php echo esc_html( $row->response_headers ? json_encode( json_decode( $row->response_headers ), JSON_PRETTY_PRINT ) : 'None' ); ?></code></pre>
												</div>
											</div>

											<?php if ( $row->response_body_snippet ) : ?>
												<div style="margin-top: 15px;">
													<h4>Raw Response Body Snippet (~2000 Chars)</h4>
													<pre class="avs-code-box avs-body-snippet"><code><?php echo esc_html( $row->response_body_snippet ); ?></code></pre>
												</div>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- TAB 2: Scan Comparison / Diff View -->
	<div id="tab-diag-diff" class="avs-diag-tab-content">
		<div class="avs-card">
			<h3 class="avs-card-title"><?php esc_html_e( 'Before / After Scan Comparison Tool', 'ai-visibility-scanner' ); ?></h3>
			<p class="avs-subtitle"><?php esc_html_e( 'Compare two scan executions side-by-side to verify if an introduced issue was detected or a fix stopped reporting errors.', 'ai-visibility-scanner' ); ?></p>

			<div class="avs-diff-selectors" style="display: flex; gap: 20px; margin: 20px 0;">
				<div style="flex: 1;">
					<label class="avs-label-bold"><?php esc_html_e( 'Baseline Scan (Before Fix):', 'ai-visibility-scanner' ); ?></label>
					<select id="avs-diff-scan-1" class="avs-select-lg">
						<?php foreach ( $recent_scans as $scan ) : ?>
							<option value="<?php echo esc_attr( $scan->id ); ?>">Scan #<?php echo esc_html( $scan->id ); ?> (<?php echo esc_html( $scan->started_at ); ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="flex: 1;">
					<label class="avs-label-bold"><?php esc_html_e( 'Comparison Scan (After Fix):', 'ai-visibility-scanner' ); ?></label>
					<select id="avs-diff-scan-2" class="avs-select-lg">
						<?php foreach ( $recent_scans as $idx => $scan ) : ?>
							<option value="<?php echo esc_attr( $scan->id ); ?>" <?php selected( 0, $idx ); ?>>Scan #<?php echo esc_html( $scan->id ); ?> (<?php echo esc_html( $scan->started_at ); ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div style="display: flex; align-items: flex-end;">
					<button type="button" class="button button-primary" id="avs-compare-scans-btn">
						<span class="dashicons dashicons-randomize" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Compare Scans', 'ai-visibility-scanner' ); ?>
					</button>
				</div>
			</div>

			<div id="avs-diff-output-container" style="display: none;">
				<div id="avs-diff-summary" class="avs-alert avs-alert-info" style="margin-bottom: 20px;"></div>
				<table class="wp-list-table widefat fixed striped table-view-list avs-diff-table">
					<thead>
						<tr>
							<th>Target Page</th>
							<th>Check Slug</th>
							<th>Change Status</th>
							<th>Baseline Result</th>
							<th>Comparison Result</th>
						</tr>
					</thead>
					<tbody id="avs-diff-table-body"></tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- TAB 3: Single-URL Ad-hoc Test Tool -->
	<div id="tab-diag-adhoc" class="avs-diag-tab-content">
		<div class="avs-card">
			<h3 class="avs-card-title"><?php esc_html_e( 'Ad-Hoc Single-URL Test Tool', 'ai-visibility-scanner' ); ?></h3>
			<p class="avs-subtitle"><?php esc_html_e( 'Test a single page URL instantly against scanner checks without running a full 11-page scan sequence.', 'ai-visibility-scanner' ); ?></p>

			<form id="avs-adhoc-form" style="margin-top: 20px;">
				<div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
					<input type="url" id="avs-adhoc-url" class="regular-text" style="flex: 1; height: 38px;" placeholder="https://example.com/sample-post/" value="<?php echo esc_attr( get_site_url() ); ?>" required />
					<button type="submit" class="button button-primary" id="avs-adhoc-submit-btn" style="height: 38px;">
						<span class="dashicons dashicons-controls-play" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Run Ad-hoc Test', 'ai-visibility-scanner' ); ?>
					</button>
				</div>
			</form>

			<div id="avs-adhoc-results-container" style="display: none; margin-top: 25px;">
				<h4>Ad-hoc Check Execution Results</h4>
				<div id="avs-adhoc-output-grid" class="avs-adhoc-grid"></div>
			</div>
		</div>
	</div>
</div>
