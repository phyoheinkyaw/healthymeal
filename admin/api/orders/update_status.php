<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['order_id']) || !is_numeric($data['order_id']) ||
    !isset($data['status_id']) || !is_numeric($data['status_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

$order_id = (int)$data['order_id'];
$status_id = (int)$data['status_id'];

try {
    // Check if order exists
    $stmt = $mysqli->prepare("SELECT order_id FROM orders WHERE order_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Order not found');
    }
    $stmt->close();

    // Check if status exists
    $stmt = $mysqli->prepare("SELECT status_id FROM order_status WHERE status_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $status_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Invalid status');
    }
    $stmt->close();

    // Update order status
    $stmt = $mysqli->prepare("UPDATE orders SET status_id = ? WHERE order_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("ii", $status_id, $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update order status: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 