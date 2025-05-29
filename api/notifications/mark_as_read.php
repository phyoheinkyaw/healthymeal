<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = (int)$_GET['order_id'];

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Mark notifications as read for this order and user
    $stmt = $mysqli->prepare("
        UPDATE order_notifications 
        SET is_read = 1
        WHERE order_id = ? AND user_id = ? AND is_read = 0
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Notifications marked as read';
        $response['updated_count'] = $stmt->affected_rows;
    } else {
        $response['success'] = true;
        $response['message'] = 'No unread notifications found';
        $response['updated_count'] = 0;
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit; 