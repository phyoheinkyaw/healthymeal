<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10; // Default to 10 notifications
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    // Prepare query based on whether we want only unread notifications
    $query = "
        SELECT on.*, o.order_id 
        FROM order_notifications on
        JOIN orders o ON on.order_id = o.order_id
        WHERE on.user_id = ?
    ";
    
    if ($unread_only) {
        $query .= " AND on.is_read = 0";
    }
    
    $query .= " ORDER BY on.created_at DESC LIMIT ?";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($notification = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $notification['notification_id'],
            'order_id' => $notification['order_id'],
            'message' => $notification['message'],
            'is_read' => (bool)$notification['is_read'],
            'created_at' => $notification['created_at']
        ];
    }
    
    // Get count of unread notifications
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
        'notifications' => $notifications,
        'unread_count' => (int)$unread_count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
} 