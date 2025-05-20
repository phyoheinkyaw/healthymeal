<?php
/**
 * Payment record synchronization API endpoint
 * Used to fix inconsistencies between payment history and verification records
 */
session_start();
require_once '../../config/connection.php';
require_once 'utils/payment_sync.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if user is admin
$isAdmin = false;
if (isset($_SESSION['role']) && $_SESSION['role'] == 1) {
    $isAdmin = true;
}

// Get order ID from request
$order_id = 0;

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
    $order_id = filter_var($_GET['order_id'], FILTER_VALIDATE_INT);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = filter_var($data['order_id'] ?? 0, FILTER_VALIDATE_INT);
}

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// For regular users, verify they own the order
if (!$isAdmin) {
    $check_stmt = $mysqli->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to access this order']);
        exit;
    }
}

try {
    // Check if there are inconsistencies
    $hasInconsistencies = hasPaymentInconsistencies($mysqli, $order_id);
    
    if (!$hasInconsistencies) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment records are already in sync.',
            'no_changes' => true
        ]);
        exit;
    }
    
    // Synchronize payment records
    $result = synchronizePaymentRecords($mysqli, $order_id);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'fixed_issues' => $result['fixed_issues'],
            'no_changes' => false
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?: 'Failed to synchronize payment records'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
} 