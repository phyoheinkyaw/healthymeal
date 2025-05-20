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

// Get POST data (handle both form data and JSON input)
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

// Validate required fields
if (!isset($input['comment_id']) || !is_numeric($input['comment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Comment ID is required']);
    exit();
}

$comment_id = (int)$input['comment_id'];

try {
    // Check if comment exists
    $check_query = "SELECT comment_id, post_id FROM comments WHERE comment_id = ?";
    $check_stmt = $mysqli->prepare($check_query);
    
    if (!$check_stmt) {
        throw new Exception('Failed to prepare check statement: ' . $mysqli->error);
    }
    
    $check_stmt->bind_param("i", $comment_id);
    
    if (!$check_stmt->execute()) {
        throw new Exception('Failed to execute check statement: ' . $check_stmt->error);
    }
    
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Comment not found');
    }
    
    $comment = $result->fetch_assoc();
    $post_id = $comment['post_id'];
    $check_stmt->close();
    
    // Delete the comment
    $delete_query = "DELETE FROM comments WHERE comment_id = ?";
    $stmt = $mysqli->prepare($delete_query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare delete statement: ' . $mysqli->error);
    }
    
    $stmt->bind_param("i", $comment_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete comment: ' . $stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment deleted successfully',
        'comment_id' => $comment_id,
        'post_id' => $post_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 