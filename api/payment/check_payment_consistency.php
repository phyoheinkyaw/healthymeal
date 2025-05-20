<?php
/**
 * Payment consistency check script
 * 
 * This script should be run as a cron job to check for inconsistent payment records
 * and fix them automatically.
 * 
 * Recommended cron schedule: Once per day
 * Example cron entry: 0 2 * * * php /path/to/check_payment_consistency.php
 */

// Allow execution from command line only
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key']) && $_GET['cron_key'] !== 'YOUR_SECRET_KEY') {
    header('HTTP/1.0 403 Forbidden');
    exit('This script is meant to be run as a cron job or with proper authentication.');
}

// Load dependencies
require_once '../../config/connection.php';
require_once 'utils/payment_functions.php';
require_once '../orders/utils/payment_sync.php';

// Log file setup
$log_file = __DIR__ . '/../../logs/payment_consistency_' . date('Y-m-d') . '.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    
    // Also output to console when run from CLI
    if (php_sapi_name() === 'cli') {
        echo "[$timestamp] $message" . PHP_EOL;
    }
}

log_message("Starting payment consistency check.");

// Find orders with recent activity (last 30 days)
$stmt = $mysqli->prepare("
    SELECT o.order_id
    FROM orders o
    LEFT JOIN payment_history ph ON o.order_id = ph.order_id
    WHERE 
        (o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR
         ph.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
    GROUP BY o.order_id
");

if (!$stmt) {
    log_message("Error preparing SQL statement: " . $mysqli->error);
    exit();
}

$stmt->execute();
$result = $stmt->get_result();
$fixed_count = 0;
$checked_count = 0;

while ($order = $result->fetch_assoc()) {
    $checked_count++;
    log_message("Checking payment consistency for Order #{$order['order_id']}");
    
    // Check if there are inconsistencies
    if (hasPaymentInconsistencies($mysqli, $order['order_id'])) {
        log_message("Found inconsistencies in Order #{$order['order_id']}");
        
        // Fix inconsistencies
        $sync_result = synchronizePaymentRecords($mysqli, $order['order_id']);
        
        if ($sync_result['success']) {
            $fixed_count++;
            log_message("Successfully fixed Order #{$order['order_id']}. Issues fixed: " . 
                       implode(", ", $sync_result['fixed_issues']));
        } else {
            log_message("Failed to fix Order #{$order['order_id']}: " . $sync_result['message']);
        }
    } else {
        log_message("Order #{$order['order_id']} has consistent payment records.");
    }
}

log_message("Completed payment consistency check. Checked {$checked_count} orders. Fixed {$fixed_count} orders with inconsistencies.");

// Close database connection
$mysqli->close(); 