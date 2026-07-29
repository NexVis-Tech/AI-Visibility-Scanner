<?php
define( 'ABSPATH', '/var/www/html/' );

$orch = new \AIVisibilityScanner\Scanner\Orchestrator();
echo "Starting scan initialization...\n";
$scan_id = $orch->start_scan();

if ( is_wp_error( $scan_id ) ) {
    echo "ERROR: " . $scan_id->get_error_message() . "\n";
    exit(1);
}

echo "Scan ID created: " . $scan_id . ". Processing scan synchronous pipeline...\n";
$orch->process_scan( (int) $scan_id );
echo "SUCCESS_SCAN_ID: " . $scan_id . "\n";
