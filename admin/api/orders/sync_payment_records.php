<?php
/**
 * Admin API endpoint to synchronize payment records
 */
require_once '../../../includes/auth_check.php';
require_once '../../../api/orders/utils/payment_sync.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
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
        // Log the synchronization
        $admin_id = $_SESSION['user_id'] ?? 1;
        $log_stmt = $mysqli->prepare("
            INSERT INTO admin_activity_log (
                admin_id, activity_type, related_id, description
            ) VALUES (?, 'payment_sync', ?, ?)
        ");
        
        $description = "Synchronized payment records for Order #$order_id. " . 
                      count($result['fixed_issues']) . " issues fixed.";
        
        $log_stmt->bind_param("iis", $admin_id, $order_id, $description);
        $log_stmt->execute();
        
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