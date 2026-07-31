( function( wp ) {
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var apiFetch = wp.apiFetch;

	// Component
	function AVSEditorPanel() {
		var postId = avsEditorData.postId;
		var [ isLoading, setIsLoading ] = useState( true );
		var [ isAnalyzing, setIsAnalyzing ] = useState( false );
		var [ reportData, setReportData ] = useState( null );
		var [ error, setError ] = useState( null );

		// Hook into Gutenberg to read post save actions / state if dirty
		var isDirty = useSelect( function( select ) {
			var coreEditor = select( 'core/editor' );
			return coreEditor ? coreEditor.isEditedPostDirty() : false;
		} );
		var isSaving = useSelect( function( select ) {
			var coreEditor = select( 'core/editor' );
			return coreEditor ? coreEditor.isSavingPost() : false;
		} );

		// Fetch report data on mount
		useEffect( function() {
			fetchReport();
		}, [] );

		function fetchReport() {
			setIsLoading( true );
			apiFetch( { path: 'avs/v1/pages/' + postId + '/report' } )
				.then( function( response ) {
					setReportData( response );
					setIsLoading( false );
				} )
				.catch( function( err ) {
					setError( err.message || 'Error fetching audit report.' );
					setIsLoading( false );
				} );
		}

		function handleAnalyze() {
			setIsAnalyzing( true );
			setError( null );

			// Trigger Gutenberg post save first if it's dirty
			var savePromise = Promise.resolve();
			if ( isDirty ) {
				savePromise = wp.data.dispatch( 'core/editor' ).savePost();
			}

			savePromise.then( function() {
				// Run analysis REST call
				apiFetch( {
					path: 'avs/v1/pages/' + postId + '/analyze',
					method: 'POST'
				} )
				.then( function( response ) {
					setReportData( {
						scanned: true,
						score: response.score,
						updated_at: response.updated_at,
						summary: response.summary,
						top_issue: response.top_issue,
						results: response.results
					} );
					setIsAnalyzing( false );
				} )
				.catch( function( err ) {
					setError( err.message || 'Error running page analysis.' );
					setIsAnalyzing( false );
				} );
			} ).catch( function( err ) {
				setError( 'Could not save post before analysis: ' + ( err.message || 'unknown error' ) );
				setIsAnalyzing( false );
			} );
		}

		if ( isLoading ) {
			return el( 'div', { className: 'avs-editor-panel' },
				el( wp.components.Spinner, {} )
			);
		}

		var hasScanned = reportData && reportData.scanned;
		var score = hasScanned ? reportData.score : 0;
		var updated_at = hasScanned ? reportData.updated_at : '';
		var results = hasScanned ? reportData.results : [];

		// Badge color class
		var scoreClass = 'not-scanned';
		var scoreText = 'Not Scanned';
		if ( hasScanned ) {
			if ( score >= 80 ) {
				scoreClass = 'good';
				scoreText = 'Excellent AI Visibility';
			} else if ( score >= 50 ) {
				scoreClass = 'warn';
				scoreText = 'Needs Improvement';
			} else {
				scoreClass = 'bad';
				scoreText = 'Critical AI Invisibility';
			}
		}

		// Human readable date
		var dateStr = 'Never';
		if ( updated_at ) {
			dateStr = updated_at;
		}

		// Helper to render check status icon
		function getStatusIcon( result ) {
			if ( 'pass' === result ) {
				return el( 'span', { className: 'avs-editor-check-status-icon pass' }, '✅' );
			} else if ( 'warn' === result ) {
				return el( 'span', { className: 'avs-editor-check-status-icon warn' }, '⚠️' );
			} else {
				return el( 'span', { className: 'avs-editor-check-status-icon fail' }, '❌' );
			}
		}

		// Nice plain-text label for slugs
		function getCheckTitle( slug ) {
			switch( slug ) {
				case 'schema_presence':
					return 'Schema Presence';
				case 'schema_validity':
					return 'Schema Validity';
				case 'heading_hierarchy':
					return 'Heading Hierarchy';
				case 'meta_description':
					return 'Meta Description';
				case 'faq_howto_opportunity':
					return 'FAQ/HowTo Opportunity';
				default:
					return slug.replace( /_/g, ' ' ).replace( /\b\w/g, function( l ) { return l.toUpperCase(); } );
			}
		}

		var permalink = wp.data.select( 'core/editor' ).getPermalink();
		var deepLinkReportUrl = avsEditorData.reportUrl + '&search=' + encodeURIComponent( permalink );

		return el( 'div', { className: 'avs-editor-panel' },
			error && el( 'div', { className: 'notice notice-error is-dismissible', style: { margin: '0 0 15px 0', padding: '10px' } },
				el( 'p', { style: { margin: 0 } }, error )
			),
			el( 'div', { className: 'avs-editor-score-wrapper' },
				el( 'div', { className: 'avs-editor-score-circle ' + scoreClass },
					el( 'span', { className: 'avs-editor-score-num' }, hasScanned ? score : '—' ),
					hasScanned && el( 'span', { className: 'avs-editor-score-label' }, '/100' )
				),
				el( 'div', { className: 'avs-editor-score-text' }, scoreText ),
				el( 'div', { className: 'avs-editor-score-meta' }, 'Last analyzed: ' + dateStr )
			),

			hasScanned && el( 'div', { className: 'avs-editor-checklist' },
				results.map( function( check ) {
					return el( 'div', { key: check.slug, className: 'avs-editor-check-row' },
						el( 'div', { className: 'avs-editor-check-header' },
							getStatusIcon( check.result ),
							el( 'span', {}, getCheckTitle( check.slug ) )
						),
						el( 'div', { className: 'avs-editor-check-evidence' }, check.evidence ),
						'pass' !== check.result && check.fix_hint && el( 'div', { className: 'avs-editor-check-hint' },
							el( 'strong', {}, 'Fix: ' ),
							check.fix_hint
						)
					);
				} )
			),

			el( 'div', { className: 'avs-editor-actions' },
				el( wp.components.Button, {
					isPrimary: true,
					isBusy: isAnalyzing || isSaving,
					disabled: isAnalyzing || isSaving,
					onClick: handleAnalyze,
					style: { width: '100%', display: 'flex', justifyContent: 'center' }
				}, isSaving ? 'Saving Draft...' : ( isAnalyzing ? 'Analyzing Content...' : 'Analyze this page now' ) ),
				hasScanned && el( 'a', {
					href: deepLinkReportUrl,
					target: '_blank',
					className: 'avs-editor-report-link'
				}, 'View full site report →' )
			)
		);
	}

	// Register Plugin Document Panel
	registerPlugin( 'avs-document-setting-panel', {
		render: function() {
			var postType = wp.data.select( 'core/editor' ).getCurrentPostType();
			if ( ! postType || ( 'post' !== postType && 'page' !== postType ) ) {
				return null;
			}

			return el( PluginDocumentSettingPanel, {
				name: 'avs-visibility-score-panel',
				title: 'AI Visibility Scanner',
				icon: 'search',
				className: 'avs-document-setting-panel'
			}, el( AVSEditorPanel, {} ) );
		}
	} );

} )( window.wp );
