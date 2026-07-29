<?php
$_GET['page']    = 'ai-visibility-scanner-report';
$_GET['scan_id'] = 2;

ob_start();
include '/var/www/html/wp-content/plugins/ai-visibility-scanner/includes/admin/views/report.php';
$html = ob_get_clean();

echo "REPORT_HTML_LENGTH: " . strlen( $html ) . "\n";

if ( strpos( $html, 'Executive Overview Card' ) !== false || strpos( $html, 'avs-tabs-container' ) !== false ) {
    echo "SUCCESS: Report HTML includes new tabbed UI structure!\n";
} else {
    echo "ERROR: Report HTML did not render expected elements.\n";
}

if ( strpos( $html, 'elementor-ai-landing-page' ) !== false ) {
    echo "SUCCESS: Elementor Landing Page checks present in report!\n";
}

if ( strpos( $html, 'gutenberg-ai-test-page' ) !== false ) {
    echo "SUCCESS: Gutenberg Blocks Page checks present in report!\n";
}
