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
    'notifications' => []
];

try {
    // Get recent notifications for the current user
    $stmt = $mysqli->prepare("
        SELECT notif.message, notif.created_at, notif.is_read, o.order_id, os.status_name 
        FROM order_notifications notif
        JOIN orders o ON notif.order_id = o.order_id
        JOIN order_status os ON o.status_id = os.status_id
        WHERE notif.user_id = ? 
        ORDER BY notif.created_at DESC
        LIMIT 5
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Clean/sanitize the message for JSON output
        $row['message'] = htmlspecialchars($row['message']);
        $row['status_name'] = htmlspecialchars($row['status_name']);
        
        $response['notifications'][] = $row;
    }
    
    $response['success'] = true;
    $stmt->close();
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit; 