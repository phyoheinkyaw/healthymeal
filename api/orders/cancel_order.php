<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // Get order ID from POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = filter_var($data['order_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }

    // Check if the order belongs to the user and is in Pending status
    $stmt = $mysqli->prepare("
        SELECT o.*, os.status_name
        FROM orders o
        LEFT JOIN order_status os ON o.status_id = os.status_id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Check if order is in Pending status (status_id = 1)
    if ($order['status_id'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
        exit;
    }

    // Update order status to Cancelled (status_id = 4)
    $stmt = $mysqli->prepare("UPDATE orders SET status_id = 4 WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order', 'debug' => $stmt->error]);
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception occurred',
        'debug' => $e->getMessage()
    ]);
}
?>
