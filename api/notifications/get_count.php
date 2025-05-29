<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'count' => 0
];

try {
    // Get count of unread notifications for the current user
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as count 
        FROM order_notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response['count'] = $row['count'];
        $response['success'] = true;
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit; 