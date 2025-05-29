<?php
// Ensure clean output - no errors, warnings, or notices should appear before JSON
ob_start(); // Start output buffering
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Make sure we only send JSON headers after all potential error messages are captured
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'pending_orders' => [],
    'pending_payments' => [],
    'total_count' => 0,
    'debug_info' => []
];

try {
    // Track potential issues
    $response['debug_info']['started'] = true;
    
    // Include database connection file
    $db_path = realpath('../../includes/db.php');
    $response['debug_info']['db_path'] = $db_path;
    
    if (!file_exists($db_path)) {
        throw new Exception("Database file not found at: $db_path");
    }
    
    // Capture any output from the include
    ob_start();
    require_once $db_path;
    $db_output = ob_get_clean();
    
    if (!empty($db_output)) {
        $response['debug_info']['db_output'] = $db_output;
    }

    // Check if database connection exists
    if (!isset($mysqli)) {
        throw new Exception("Database connection variable not set");
    }
    
    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }
    
    $response['debug_info']['db_connected'] = true;

    // Get pending orders
    $pendingOrdersQuery = $mysqli->query("
        SELECT o.order_id, u.username, u.full_name, o.created_at, o.total_amount 
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        WHERE o.status_id = 1
        ORDER BY o.created_at DESC
        LIMIT 5
    ");

    if ($pendingOrdersQuery === false) {
        throw new Exception("Error in pending orders query: " . $mysqli->error);
    }

    while ($order = $pendingOrdersQuery->fetch_assoc()) {
        $order['created_at'] = date('M d, H:i', strtotime($order['created_at']));
        $response['pending_orders'][] = $order;
    }

    // Get pending payment verifications
    $pendingPaymentsQuery = $mysqli->query("
        SELECT pv.verification_id, o.order_id, u.username, u.full_name, pv.amount_verified, pv.created_at
        FROM payment_verifications pv
        JOIN orders o ON pv.order_id = o.order_id
        JOIN users u ON o.user_id = u.user_id
        JOIN (
            -- Get the latest verification ID for each order
            SELECT order_id, MAX(verification_id) AS latest_verification_id
            FROM payment_verifications
            GROUP BY order_id
        ) latest ON pv.order_id = latest.order_id AND pv.verification_id = latest.latest_verification_id
        WHERE (pv.payment_status = 0 OR pv.payment_status = 4) -- Only pending or partial payments
              AND pv.payment_verified = 0 -- Only unverified payments
        ORDER BY pv.created_at DESC
        LIMIT 5
    ");

    if ($pendingPaymentsQuery === false) {
        throw new Exception("Error in pending payments query: " . $mysqli->error);
    }

    while ($payment = $pendingPaymentsQuery->fetch_assoc()) {
        $payment['created_at'] = date('M d, H:i', strtotime($payment['created_at']));
        $response['pending_payments'][] = $payment;
    }

    // Get total count for badge
    $totalOrdersCountResult = $mysqli->query("SELECT COUNT(*) as count FROM orders WHERE status_id = 1");
    $totalPaymentsCountResult = $mysqli->query("
        SELECT COUNT(*) as count 
        FROM payment_verifications pv
        JOIN (
            -- Get the latest verification ID for each order
            SELECT order_id, MAX(verification_id) AS latest_verification_id
            FROM payment_verifications
            GROUP BY order_id
        ) latest ON pv.order_id = latest.order_id AND pv.verification_id = latest.latest_verification_id
        WHERE (pv.payment_status = 0 OR pv.payment_status = 4) 
              AND pv.payment_verified = 0
    ");
    
    if ($totalOrdersCountResult === false || $totalPaymentsCountResult === false) {
        throw new Exception("Error fetching count data: " . $mysqli->error);
    }
    
    $totalOrdersCount = $totalOrdersCountResult->fetch_assoc()['count'];
    $totalPaymentsCount = $totalPaymentsCountResult->fetch_assoc()['count'];
    $response['total_count'] = $totalOrdersCount + $totalPaymentsCount;
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

// Clean any remaining output before sending JSON
ob_end_clean();

// Send JSON response
echo json_encode($response);
exit; 