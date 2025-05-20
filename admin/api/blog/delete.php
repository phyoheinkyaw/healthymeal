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
if (!isset($input['post_id']) || !is_numeric($input['post_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Post ID is required']);
    exit();
}

$post_id = (int)$input['post_id'];

try {
    // Start transaction to delete blog post and related comments
    $mysqli->begin_transaction();
    
    // First check if post exists
    $check_query = "SELECT post_id FROM blog_posts WHERE post_id = ?";
    $check_stmt = $mysqli->prepare($check_query);
    
    if (!$check_stmt) {
        throw new Exception('Failed to prepare check statement: ' . $mysqli->error);
    }
    
    $check_stmt->bind_param("i", $post_id);
    
    if (!$check_stmt->execute()) {
        throw new Exception('Failed to execute check statement: ' . $check_stmt->error);
    }
    
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Blog post not found');
    }
    $check_stmt->close();
    
    // Delete all comments for this post first
    $delete_comments = "DELETE FROM comments WHERE post_id = ?";
    $stmt_comments = $mysqli->prepare($delete_comments);
    
    if (!$stmt_comments) {
        throw new Exception('Failed to prepare delete comments statement: ' . $mysqli->error);
    }
    
    $stmt_comments->bind_param("i", $post_id);
    
    if (!$stmt_comments->execute()) {
        throw new Exception('Failed to delete comments: ' . $stmt_comments->error);
    }
    
    // Now delete the blog post
    $delete_post = "DELETE FROM blog_posts WHERE post_id = ?";
    $stmt_post = $mysqli->prepare($delete_post);
    
    if (!$stmt_post) {
        throw new Exception('Failed to prepare delete post statement: ' . $mysqli->error);
    }
    
    $stmt_post->bind_param("i", $post_id);
    
    if (!$stmt_post->execute()) {
        throw new Exception('Failed to delete blog post: ' . $stmt_post->error);
    }
    
    // Commit transaction if successful
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Blog post and related comments deleted successfully',
        'post_id' => $post_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction in case of error
    $mysqli->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 