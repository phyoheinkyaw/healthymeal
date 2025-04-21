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

// Validate order ID
if (!isset($data['order_id']) || !is_numeric($data['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$order_id = (int)$data['order_id'];

try {
    // Start transaction
    $mysqli->begin_transaction();

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

    // Delete from order_item_ingredients first (due to foreign key constraint)
    $stmt = $mysqli->prepare("DELETE FROM order_item_ingredients WHERE order_item_id IN (SELECT order_item_id FROM order_items WHERE order_id = ?)");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }
    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete order item ingredients: ' . $stmt->error);
    }
    $stmt->close();

    // Delete order items
    $stmt = $mysqli->prepare("DELETE FROM order_items WHERE order_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }
    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete order items: ' . $stmt->error);
    }
    $stmt->close();

    // Delete order
    $stmt = $mysqli->prepare("DELETE FROM orders WHERE order_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete order: ' . $stmt->error);
    }

    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order deleted successfully'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 