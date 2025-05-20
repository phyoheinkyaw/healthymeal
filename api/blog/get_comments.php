<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Validate post ID
$post_id = filter_var($_GET['post_id'], FILTER_VALIDATE_INT);
if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit();
}

try {
    // Fetch comments for the post
    $stmt = $mysqli->prepare("
        SELECT c.*, u.full_name as commenter_name
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE c.post_id = ?
        ORDER BY c.created_at DESC
    ");
    
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates and escape content for security
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        $row['content'] = htmlspecialchars($row['content']);
        $row['commenter_name'] = htmlspecialchars($row['commenter_name']);
        
        $comments[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching comments: ' . $e->getMessage()
    ]);
} 