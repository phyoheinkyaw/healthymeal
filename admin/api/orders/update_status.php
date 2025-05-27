<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
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
    
    // Special handling for cancelled orders (status_id 7)
    if ($status_id == 7) {
        // Log the cancellation
        $log_dir = $_SERVER['DOCUMENT_ROOT'] . '/hm/uploads/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        $log_file = $log_dir . 'order_status.log';
        $log_message = date('Y-m-d H:i:s') . " - Order #{$order_id} status updated to 7 (Cancelled)";
        file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
        
        // Get user ID for the order for notification
        $user_stmt = $mysqli->prepare("SELECT user_id FROM orders WHERE order_id = ?");
        $user_stmt->bind_param("i", $order_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        $user_id = $user_data['user_id'];
        $user_stmt->close();
        
        // Create notification for the user
        $notify_stmt = $mysqli->prepare("
            INSERT INTO order_notifications (
                order_id, user_id, message, note, is_read, created_at
            ) VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        if ($notify_stmt) {
            $message = "Your order #{$order_id} has been cancelled.";
            $note = "Order status changed to cancelled.";
            $notify_stmt->bind_param("iiss", $order_id, $user_id, $message, $note);
            $notify_stmt->execute();
            $notify_stmt->close();
            
            // Log notification creation
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Created notification for order #{$order_id}" . PHP_EOL, FILE_APPEND);
        }
        
        // Check if there's a payment for this order that needs to be refunded
        $payment_stmt = $mysqli->prepare("
            SELECT payment_id, payment_status FROM payment_history 
            WHERE order_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        if ($payment_stmt) {
            $payment_stmt->bind_param("i", $order_id);
            $payment_stmt->execute();
            $payment_result = $payment_stmt->get_result();
            
            if ($payment_result->num_rows > 0) {
                $payment_data = $payment_result->fetch_assoc();
                
                // If payment is not already refunded (status 3), update it
                if ($payment_data['payment_status'] != 3) {
                    $update_payment_stmt = $mysqli->prepare("
                        UPDATE payment_history 
                        SET payment_status = 3, updated_at = NOW() 
                        WHERE payment_id = ?
                    ");
                    
                    if ($update_payment_stmt) {
                        $update_payment_stmt->bind_param("i", $payment_data['payment_id']);
                        $update_payment_stmt->execute();
                        $update_payment_stmt->close();
                        
                        // Log payment update
                        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Updated payment #{$payment_data['payment_id']} for order #{$order_id} to status 3 (Refunded)" . PHP_EOL, FILE_APPEND);
                    }
                }
            }
            $payment_stmt->close();
        }
        
        // Also mark any payment verification records as refunded
        $verification_stmt = $mysqli->prepare("
            UPDATE payment_verifications 
            SET payment_status = 3, updated_at = NOW() 
            WHERE order_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        if ($verification_stmt) {
            $verification_stmt->bind_param("i", $order_id);
            $verification_stmt->execute();
            $rows_affected = $verification_stmt->affected_rows;
            $verification_stmt->close();
            
            if ($rows_affected > 0) {
                // Log verification update
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - Updated payment verification for order #{$order_id} to status 3 (Refunded)" . PHP_EOL, FILE_APPEND);
            }
        }
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