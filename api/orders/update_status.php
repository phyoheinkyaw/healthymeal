<?php
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log the request for debugging
$log_file = '../../uploads/order_status_updates.log';
$timestamp = date('Y-m-d H:i:s');
$log_message = "[{$timestamp}] Received update request: " . json_encode($data) . "\n";
file_put_contents($log_file, $log_message, FILE_APPEND);

// Validate required fields
if (!isset($data['order_id']) || !is_numeric($data['order_id']) ||
    !isset($data['status_id']) || !is_numeric($data['status_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request data',
        'alert' => [
            'title' => 'Request Error',
            'text' => 'The request data was invalid or incomplete.',
            'icon' => 'error'
        ]
    ]);
    exit();
}

$order_id = (int)$data['order_id'];
$status_id = (int)$data['status_id'];

try {
    // Check if order exists
    $stmt = $mysqli->prepare("SELECT order_id, user_id FROM orders WHERE order_id = ?");
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
    
    $order_data = $result->fetch_assoc();
    $user_id = $order_data['user_id'];
    $stmt->close();

    // Check if status exists
    $stmt = $mysqli->prepare("SELECT status_id, status_name FROM order_status WHERE status_id = ?");
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
    
    $status_data = $result->fetch_assoc();
    $status_name = $status_data['status_name'];
    $stmt->close();

    // Start transaction
    $mysqli->begin_transaction();

    // Update order status
    $stmt = $mysqli->prepare("UPDATE orders SET status_id = ?, updated_at = NOW() WHERE order_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("ii", $status_id, $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update order status: ' . $stmt->error);
    }
    
    // If this is a refund (cancelled status), update payment status as well
    if ($status_id == 7) { // Assuming 7 is cancelled status
        $payment_stmt = $mysqli->prepare("
            UPDATE payment_history 
            SET payment_status = 3, updated_at = NOW() 
            WHERE order_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        if ($payment_stmt) {
            $payment_stmt->bind_param("i", $order_id);
            $payment_stmt->execute();
            $payment_stmt->close();
            
            // Log payment update
            file_put_contents($log_file, "[{$timestamp}] Updated payment status for order {$order_id} to refunded (3)\n", FILE_APPEND);
        }
    }
    
    // Create a notification for the user
    $notification_message = "Your order #$order_id status has been updated to: $status_name";
    
    $notif_stmt = $mysqli->prepare("
        INSERT INTO order_notifications (
            order_id, user_id, message, is_read, created_at
        ) VALUES (?, ?, ?, 0, NOW())
    ");
    
    if ($notif_stmt) {
        $notif_stmt->bind_param("iis", $order_id, $user_id, $notification_message);
        $notif_stmt->execute();
        $notif_stmt->close();
    }

    // Commit transaction
    $mysqli->commit();
    
    // Log success
    file_put_contents($log_file, "[{$timestamp}] Successfully updated order {$order_id} to status {$status_id} ({$status_name})\n", FILE_APPEND);

    echo json_encode([
        'success' => true, 
        'message' => 'Order status updated successfully',
        'status_name' => $status_name,
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    // Rollback if transaction was started
    if ($mysqli->inTransaction()) {
        $mysqli->rollback();
    }
    
    // Log error
    file_put_contents($log_file, "[{$timestamp}] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 