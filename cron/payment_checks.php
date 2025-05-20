<?php
/**
 * Payment system cron jobs
 * 
 * This file runs all payment-related cron jobs:
 * 1. Check for payment record inconsistencies
 * 
 * Recommended cron schedule: Once per day
 * Example cron entry: 0 2 * * * php /path/to/cron/payment_checks.php
 */

// Set execution time limit to 5 minutes
set_time_limit(300);

// Load dependencies
require_once __DIR__ . '/../config/connection.php';

// Log file setup
$log_file = __DIR__ . '/../logs/cron_payment_checks_' . date('Y-m-d') . '.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    echo "[$timestamp] $message" . PHP_EOL;
}

log_message("Starting payment system cron jobs.");

// Check for payment record inconsistencies
log_message("Running payment consistency check...");
include_once __DIR__ . '/../api/payment/check_payment_consistency.php';

log_message("All payment system cron jobs completed.");

// Close database connection
$mysqli->close(); 