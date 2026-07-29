<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIVisibilityScanner\Scoring\Score_History;

$latest_scan = Score_History::get_latest_scan();
?>
<div class="wrap avs-wrap">
	<h1 class="avs-heading"><?php esc_html_e( 'AI Visibility Scanner — Dashboard', 'ai-visibility-scanner' ); ?></h1>
	<p class="avs-subtitle"><?php esc_html_e( 'Analyze and optimize your website for AI search engines, answer engines, and LLM web crawlers.', 'ai-visibility-scanner' ); ?></p>

	<div class="avs-grid">
		<!-- Main Action Card -->
		<div class="avs-card avs-card-primary">
			<h2><?php esc_html_e( 'Run AI Audit', 'ai-visibility-scanner' ); ?></h2>
			<p><?php esc_html_e( 'Trigger a comprehensive background crawl auditing your robots.txt, sitemaps, JSON-LD schemas, and content extractability.', 'ai-visibility-scanner' ); ?></p>
			
			<div class="avs-action-box">
				<button id="avs-start-scan-btn" class="button button-primary button-hero">
					<span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Start Scan Now', 'ai-visibility-scanner' ); ?>
				</button>
			</div>

			<!-- Progress Bar Container -->
			<div id="avs-progress-container" class="avs-progress-container" style="display: none;">
				<div class="avs-progress-status-row">
					<span id="avs-progress-text"><?php esc_html_e( 'Initializing scan queue...', 'ai-visibility-scanner' ); ?></span>
					<span id="avs-progress-percent">0%</span>
				</div>
				<div class="avs-progress-bar-bg">
					<div id="avs-progress-bar-fill" class="avs-progress-bar-fill" style="width: 0%;"></div>
				</div>
			</div>
		</div>

		<!-- Latest Scan Score Summary -->
		<div class="avs-card">
			<h2><?php esc_html_e( 'Latest Audit Score', 'ai-visibility-scanner' ); ?></h2>
			<?php if ( $latest_scan && 'completed' === $latest_scan->status ) : ?>
				<div class="avs-score-display">
					<div class="avs-score-badge avs-score-<?php echo esc_attr( $latest_scan->composite_score >= 80 ? 'good' : ( $latest_scan->composite_score >= 50 ? 'warn' : 'bad' ) ); ?>">
						<?php echo esc_html( $latest_scan->composite_score ); ?><span>/100</span>
					</div>
					<div class="avs-score-details">
						<p><strong><?php esc_html_e( 'Pages Scanned:', 'ai-visibility-scanner' ); ?></strong> <?php echo esc_html( $latest_scan->pages_scanned ); ?></p>
						<p><strong><?php esc_html_e( 'Completed:', 'ai-visibility-scanner' ); ?></strong> <?php echo esc_html( $latest_scan->completed_at ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=avs-report&scan_id=' . $latest_scan->id ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View Full Audit Report →', 'ai-visibility-scanner' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<p class="avs-empty-notice"><?php esc_html_e( 'No completed scan history found. Click "Start Scan Now" to generate your first report.', 'ai-visibility-scanner' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- History Table -->
	<div class="avs-card" style="margin-top: 20px;">
		<h2><?php esc_html_e( 'Recent Audit History', 'ai-visibility-scanner' ); ?></h2>
		<?php
		$history = Score_History::get_recent_scans( 5 );
		if ( ! empty( $history ) ) :
			?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Scan ID', 'ai-visibility-scanner' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ai-visibility-scanner' ); ?></th>
						<th><?php esc_html_e( 'Pages', 'ai-visibility-scanner' ); ?></th>
						<th><?php esc_html_e( 'Score', 'ai-visibility-scanner' ); ?></th>
						<th><?php esc_html_e( 'Started', 'ai-visibility-scanner' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ai-visibility-scanner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $scan ) : ?>
						<tr>
							<td>#<?php echo esc_html( $scan->id ); ?></td>
							<td><span class="avs-status-tag avs-status-<?php echo esc_attr( $scan->status ); ?>"><?php echo esc_html( strtoupper( $scan->status ) ); ?></span></td>
							<td><?php echo esc_html( $scan->pages_scanned . ' / ' . $scan->pages_total ); ?></td>
							<td><strong><?php echo esc_html( null !== $scan->composite_score ? $scan->composite_score . '/100' : '—' ); ?></strong></td>
							<td><?php echo esc_html( $scan->started_at ); ?></td>
							<td>
								<?php if ( 'completed' === $scan->status ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=avs-report&scan_id=' . $scan->id ) ); ?>"><?php esc_html_e( 'View Report', 'ai-visibility-scanner' ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No previous scans found.', 'ai-visibility-scanner' ); ?></p>
		<?php endif; ?>
	</div>
</div>
