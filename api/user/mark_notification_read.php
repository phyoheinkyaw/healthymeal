<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Get and validate input
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($data['notification_id']) ? filter_var($data['notification_id'], FILTER_VALIDATE_INT) : null;
$all = isset($data['all']) && $data['all'] === true;

try {
    if ($all) {
        // Mark all notifications as read
        $stmt = $mysqli->prepare("
            UPDATE order_notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $affected = $stmt->affected_rows;
        $message = "All notifications marked as read";
    } elseif ($notification_id) {
        // Mark specific notification as read
        $stmt = $mysqli->prepare("
            UPDATE order_notifications 
            SET is_read = 1 
            WHERE notification_id = ? AND user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        
        $affected = $stmt->affected_rows;
        $message = "Notification marked as read";
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing notification_id or all parameter']);
        exit;
    }
    
    // Get count of remaining unread notifications
    $count_stmt = $mysqli->prepare("
        SELECT COUNT(*) as count
        FROM order_notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $unread_count = $count_result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'unread_count' => (int)$unread_count,
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating notifications: ' . $e->getMessage()
    ]);
} 