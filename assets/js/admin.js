/**
 * AI Visibility Scanner Admin JavaScript
 */
(function($) {
	'use strict';

	$(document).ready(function() {

		/* ==========================================================================
		   1. Dashboard Scan Trigger & REST API Polling
		   ========================================================================== */
		const $btn = $('#avs-start-scan-btn');
		const $progressContainer = $('#avs-progress-container');
		const $progressText = $('#avs-progress-text');
		const $progressPercent = $('#avs-progress-percent');
		const $progressBarFill = $('#avs-progress-bar-fill');
		let pollInterval = null;

		if ($btn.length) {
			$btn.on('click', function(e) {
				e.preventDefault();

				$btn.prop('disabled', true).addClass('updating-message');
				$progressContainer.slideDown();
				$progressText.text('Initializing scan queue...');
				$progressPercent.text('0%');
				$progressBarFill.css('width', '0%');

				$.ajax({
					url: avsData.restUrl + 'scans',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', avsData.nonce);
					},
					success: function(response) {
						if (response.success && response.scan_id) {
							startPolling(response.scan_id);
						} else {
							showError('Failed to start scan. Please try again.');
						}
					},
					error: function(xhr) {
						showError('REST API request failed: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText));
					}
				});
			});
		}

		function startPolling(scanId) {
			pollInterval = setInterval(function() {
				$.ajax({
					url: avsData.restUrl + 'scans/' + scanId,
					method: 'GET',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', avsData.nonce);
					},
					success: function(data) {
						const progress = data.progress || 0;
						$progressText.text('Scanning pages (' + (data.pages_scanned || 0) + ' / ' + (data.pages_total || 0) + ')...');
						$progressPercent.text(progress + '%');
						$progressBarFill.css('width', progress + '%');

						if (data.status === 'completed') {
							clearInterval(pollInterval);
							$progressText.text('Scan complete! Redirecting to report...');
							setTimeout(function() {
								window.location.href = avsData.reportUrl + '&scan_id=' + scanId;
							}, 1200);
						} else if (data.status === 'failed') {
							clearInterval(pollInterval);
							showError('Scan execution encountered an error.');
						}
					}
				});
			}, 2500);
		}

		function showError(msg) {
			if ($btn.length) {
				$btn.prop('disabled', false).removeClass('updating-message');
			}
			$progressText.text('Error: ' + msg);
			$progressBarFill.css('background', '#ef4444');
		}

		/* ==========================================================================
		   2. Report View Tabs Switching
		   ========================================================================== */
		$('.avs-tab-item').on('click', function() {
			const targetTab = $(this).attr('data-tab');

			$('.avs-tab-item').removeClass('active');
			$(this).addClass('active');

			$('.avs-tab-content').removeClass('active');
			$('#' + targetTab).addClass('active');

			// Refresh pagination if switching to All Checks tab
			if (targetTab === 'tab-all') {
				applyFiltersAndPaginate();
			}
		});

		/* ==========================================================================
		   3. Accordion Toggle
		   ========================================================================== */
		$(document).on('click', '.avs-accordion-header', function(e) {
			// Don't toggle accordion if clicking directly on a link inside title
			if ($(e.target).is('a')) return;

			const $item = $(this).closest('.avs-accordion-item');
			const $body = $item.find('.avs-accordion-body');

			$item.toggleClass('expanded');
			$body.slideToggle(200);
		});

		/* ==========================================================================
		   4. Live Search & Filter Logic
		   ========================================================================== */
		const $searchInput = $('#avs-filter-search');
		const $statusSelect = $('#avs-filter-status');
		const $catSelect = $('#avs-filter-category');
		const $resetBtn = $('#avs-filter-reset');

		let currentPage = 1;
		let perPage = 15;

		if ($searchInput.length) {
			$searchInput.on('keyup input', function() {
				currentPage = 1;
				applyFiltersAndPaginate();
			});

			$statusSelect.on('change', function() {
				currentPage = 1;
				applyFiltersAndPaginate();
			});

			$catSelect.on('change', function() {
				currentPage = 1;
				applyFiltersAndPaginate();
			});

			$resetBtn.on('click', function(e) {
				e.preventDefault();
				$searchInput.val('');
				$statusSelect.val('all');
				$catSelect.val('all');
				currentPage = 1;
				applyFiltersAndPaginate();
			});
		}

		/* ==========================================================================
		   5. Client-Side Table Pagination & Filtering
		   ========================================================================== */
		$('#avs-per-page-select').on('change', function() {
			perPage = parseInt($(this).val(), 10) || 15;
			currentPage = 1;
			applyFiltersAndPaginate();
		});

		$('#avs-prev-page').on('click', function(e) {
			e.preventDefault();
			if (currentPage > 1) {
				currentPage--;
				applyFiltersAndPaginate();
			}
		});

		$('#avs-next-page').on('click', function(e) {
			e.preventDefault();
			currentPage++;
			applyFiltersAndPaginate();
		});

		function applyFiltersAndPaginate() {
			const query = ($searchInput.val() || '').toLowerCase().trim();
			const status = $statusSelect.val() || 'all';
			const cat = $catSelect.val() || 'all';

			// Filter Tab 1 (Action Plan)
			let matchedFixesCount = 0;
			$('#avs-fixes-list .avs-fix-card').each(function() {
				const itemSearch = $(this).attr('data-search') || '';
				const itemStatus = $(this).attr('data-status') || '';
				const itemCat = $(this).attr('data-category') || '';

				const matchesQuery = !query || itemSearch.indexOf(query) !== -1;
				const matchesStatus = status === 'all' || itemStatus === status;
				const matchesCat = cat === 'all' || itemCat === cat;

				if (matchesQuery && matchesStatus && matchesCat) {
					$(this).show();
					matchedFixesCount++;
				} else {
					$(this).hide();
				}
			});

			if ($('#avs-fixes-list').length) {
				$('#avs-no-fixes-matched').toggle(matchedFixesCount === 0);
			}

			// Filter Tab 2 (Page-by-Page Audit)
			$('#avs-pages-list .avs-page-card').each(function() {
				const $pageCard = $(this);
				const pageSearch = $pageCard.attr('data-search') || '';
				let hasVisibleRows = false;

				$pageCard.find('tbody tr').each(function() {
					const itemSearch = $(this).attr('data-search') || '';
					const itemStatus = $(this).attr('data-status') || '';
					const itemCat = $(this).attr('data-category') || '';

					const matchesQuery = !query || pageSearch.indexOf(query) !== -1 || itemSearch.indexOf(query) !== -1;
					const matchesStatus = status === 'all' || itemStatus === status;
					const matchesCat = cat === 'all' || itemCat === cat;

					if (matchesQuery && matchesStatus && matchesCat) {
						$(this).show();
						hasVisibleRows = true;
					} else {
						$(this).hide();
					}
				});

				$pageCard.toggle(hasVisibleRows);
			});

			// Filter & Paginate Tab 3 (All Checks Table)
			const $allRows = $('#avs-all-results-tbody tr.avs-table-row');
			const visibleRows = [];

			$allRows.each(function() {
				const itemSearch = $(this).attr('data-search') || '';
				const itemStatus = $(this).attr('data-status') || '';
				const itemCat = $(this).attr('data-category') || '';

				const matchesQuery = !query || itemSearch.indexOf(query) !== -1;
				const matchesStatus = status === 'all' || itemStatus === status;
				const matchesCat = cat === 'all' || itemCat === cat;

				if (matchesQuery && matchesStatus && matchesCat) {
					visibleRows.push($(this));
				} else {
					$(this).hide();
				}
			});

			const totalVisible = visibleRows.length;
			const totalPages = Math.ceil(totalVisible / perPage) || 1;

			if (currentPage > totalPages) currentPage = totalPages;
			if (currentPage < 1) currentPage = 1;

			const startIdx = (currentPage - 1) * perPage;
			const endIdx = startIdx + perPage;

			// Show only rows for current page
			$.each(visibleRows, function(index, $row) {
				if (index >= startIdx && index < endIdx) {
					$row.show();
				} else {
					$row.hide();
				}
			});

			// Update Pagination Controls UI
			$('#avs-current-page-num').text(currentPage);
			$('#avs-prev-page').prop('disabled', currentPage <= 1);
			$('#avs-next-page').prop('disabled', currentPage >= totalPages || totalVisible === 0);

			const displayStart = totalVisible === 0 ? 0 : startIdx + 1;
			const displayEnd = Math.min(endIdx, totalVisible);
			$('#avs-page-info').text('Showing ' + displayStart + '-' + displayEnd + ' of ' + totalVisible + ' results');
		}

		// Initial filter run on page load
		if ($('#avs-all-results-tbody').length) {
			applyFiltersAndPaginate();
		}

		/* ==========================================================================
		   3. Scan Diagnostics & Developer Log Panel Logic
		   ========================================================================== */

		// Sub-Tab Switching
		$('.avs-diag-tab-item').on('click', function() {
			const targetTab = $(this).attr('data-tab');
			$('.avs-diag-tab-item').removeClass('active');
			$(this).addClass('active');
			$('.avs-diag-tab-content').removeClass('active');
			$('#' + targetTab).addClass('active');
		});

		// Inspect Row Detail Toggle
		$(document).on('click', '.avs-toggle-detail-btn', function() {
			const targetId = $(this).attr('data-target');
			$('#' + targetId).toggle();
		});

		// Diagnostics Log Priority & Strategy Filtering
		let activeErrorFilter = 'all';
		let activeStrategyFilter = 'all';

		$('.avs-log-filter-btn').on('click', function() {
			$('.avs-log-filter-btn').removeClass('active');
			$(this).addClass('active');
			activeErrorFilter = $(this).attr('data-filter-val');
			filterDiagnosticsTable();
		});

		$('#avs-strategy-filter').on('change', function() {
			activeStrategyFilter = $(this).val();
			filterDiagnosticsTable();
		});

		function filterDiagnosticsTable() {
			const $rows = $('#avs-diag-table-body .avs-diag-row');
			$rows.each(function() {
				const $row = $(this);
				const isIssue = $row.attr('data-is-issue') === '1';
				const strategy = $row.attr('data-strategy');
				const detailId = $row.find('.avs-toggle-detail-btn').attr('data-target');

				let show = true;

				if (activeErrorFilter === 'issues_only' && !isIssue) {
					show = false;
				}

				if (activeStrategyFilter !== 'all' && strategy !== activeStrategyFilter) {
					show = false;
				}

				if (show) {
					$row.show();
				} else {
					$row.hide();
					$('#' + detailId).hide();
				}
			});
		}

		// Standalone Connectivity Self-Test Trigger
		$('#avs-run-selftest-btn').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			$btn.prop('disabled', true).addClass('updating-message');

			const $container = $('#avs-selftest-container');
			const $grid = $('#avs-selftest-cards-grid');
			$container.slideDown();
			$grid.html('<p class="avs-text-muted">Running 4 connectivity checks (Loopback, Robots.txt, Sitemap, Cloudflare)...</p>');

			$.ajax({
				url: avsData.restUrl + 'diagnostics/selftest',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', avsData.nonce);
				},
				success: function(res) {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (res.success && res.tests) {
						$('#avs-selftest-time').text('Ran at ' + res.timestamp);
						let html = '';
						$.each(res.tests, function(key, test) {
							const statusClass = 'st-' + (test.status || 'info');
							html += '<div class="avs-st-card ' + statusClass + '">';
							html += '  <div class="avs-st-title"><span>' + test.name + '</span><span class="avs-pill avs-pill-' + (test.status === 'pass' ? 'success' : (test.status === 'fail' ? 'danger' : 'warn')) + '">' + test.status.toUpperCase() + '</span></div>';
							html += '  <div class="avs-st-summary">' + test.summary + '</div>';
							if (test.snippet) {
								html += '  <pre class="avs-code-box" style="margin-top: 8px; max-height: 80px;"><code>' + test.snippet + '</code></pre>';
							}
							html += '</div>';
						});
						$grid.html(html);
					}
				},
				error: function(xhr) {
					$btn.prop('disabled', false).removeClass('updating-message');
					$grid.html('<div class="avs-alert avs-alert-danger">Self-test request failed: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText) + '</div>');
				}
			});
		});

		// Scan Comparison / Diff Execution
		$('#avs-compare-scans-btn').on('click', function(e) {
			e.preventDefault();
			const scan1 = $('#avs-diff-scan-1').val();
			const scan2 = $('#avs-diff-scan-2').val();

			if (!scan1 || !scan2) {
				alert('Please select two valid scans to compare.');
				return;
			}

			const $outContainer = $('#avs-diff-output-container');
			const $summary = $('#avs-diff-summary');
			const $tbody = $('#avs-diff-table-body');

			$outContainer.slideDown();
			$summary.text('Comparing Scan #' + scan1 + ' vs Scan #' + scan2 + '...');
			$tbody.html('<tr><td colspan="5" class="avs-text-center">Calculating diff...</td></tr>');

			$.ajax({
				url: avsData.restUrl + 'diagnostics/compare?scan_id_1=' + scan1 + '&scan_id_2=' + scan2,
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', avsData.nonce);
				},
				success: function(res) {
					if (res.success) {
						$summary.html('<strong>Comparison Complete:</strong> Found ' + res.total_diffs + ' check result changes between Scan #' + scan1 + ' and Scan #' + scan2 + '.');
						if (res.total_diffs === 0) {
							$tbody.html('<tr><td colspan="5" class="avs-text-center">No differences found! Both scans produced identical results.</td></tr>');
							return;
						}

						let rowsHtml = '';
						$.each(res.diffs, function(idx, diff) {
							let badge = '<span class="avs-pill avs-pill-neutral">' + diff.change_type + '</span>';
							if (diff.change_type === 'resolved') {
								badge = '<span class="avs-pill avs-pill-success">FIXED / RESOLVED 🎉</span>';
							} else if (diff.change_type === 'regressed') {
								badge = '<span class="avs-pill avs-pill-danger">NEW FAILURE ⚠️</span>';
							}

							const res1 = diff.scan_1 ? diff.scan_1.result.toUpperCase() : 'N/A';
							const res2 = diff.scan_2 ? diff.scan_2.result.toUpperCase() : 'N/A';

							rowsHtml += '<tr>';
							rowsHtml += '<td><code>' + diff.page_url + '</code></td>';
							rowsHtml += '<td><code>' + diff.check_slug + '</code></td>';
							rowsHtml += '<td>' + badge + '</td>';
							rowsHtml += '<td><strong style="color: ' + (res1 === 'PASS' ? '#166534' : '#991b1b') + '">' + res1 + '</strong></td>';
							rowsHtml += '<td><strong style="color: ' + (res2 === 'PASS' ? '#166534' : '#991b1b') + '">' + res2 + '</strong></td>';
							rowsHtml += '</tr>';
						});

						$tbody.html(rowsHtml);
					}
				},
				error: function(xhr) {
					$tbody.html('<tr><td colspan="5" class="avs-text-center avs-text-danger">Failed to calculate scan comparison.</td></tr>');
				}
			});
		});

		// Single-URL Ad-hoc Test Form
		$('#avs-adhoc-form').on('submit', function(e) {
			e.preventDefault();
			const url = $('#avs-adhoc-url').val();
			const $btn = $('#avs-adhoc-submit-btn');

			$btn.prop('disabled', true).addClass('updating-message');
			const $container = $('#avs-adhoc-results-container');
			const $grid = $('#avs-adhoc-output-grid');

			$container.slideDown();
			$grid.html('<p class="avs-text-muted">Executing checks directly against target URL: ' + url + '...</p>');

			$.ajax({
				url: avsData.restUrl + 'diagnostics/adhoc',
				method: 'POST',
				data: { url: url, checks: 'all' },
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', avsData.nonce);
				},
				success: function(res) {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (res.success && res.results) {
						let html = '';
						$.each(res.results, function(idx, item) {
							const isPass = item.result === 'pass';
							html += '<div class="avs-adhoc-item">';
							html += '  <div style="display: flex; justify-content: space-between; align-items: center;">';
							html += '    <strong>' + item.title + ' (<code>' + item.slug + '</code>)</strong>';
							html += '    <span class="avs-pill avs-pill-' + (isPass ? 'success' : 'danger') + '">' + item.result.toUpperCase() + '</span>';
							html += '  </div>';
							if (item.evidence) {
								html += '  <div style="margin-top: 6px; font-size: 12px; color: #475569;"><strong>Evidence:</strong> ' + item.evidence + '</div>';
							}
							if (item.fix_hint && !isPass) {
								html += '  <div class="avs-mini-hint" style="margin-top: 4px;"><strong>Fix Hint:</strong> ' + item.fix_hint + '</div>';
							}
							html += '</div>';
						});
						$grid.html(html);
					}
				},
				error: function(xhr) {
					$btn.prop('disabled', false).removeClass('updating-message');
					$grid.html('<div class="avs-alert avs-alert-danger">Ad-hoc execution failed: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText) + '</div>');
				}
			});
		});

		// Export Log JSON Download
		$('#avs-export-json-btn').on('click', function(e) {
			e.preventDefault();
			const scanId = $(this).attr('data-scan-id');
			if (!scanId) return;

			window.open(avsData.restUrl + 'diagnostics/export?scan_id=' + scanId + '&_wpnonce=' + avsData.nonce, '_blank');
		});

	});

})(jQuery);

