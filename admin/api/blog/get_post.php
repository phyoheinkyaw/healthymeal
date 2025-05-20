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

// Validate post ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit();
}

$post_id = (int)$_GET['id'];

try {
    // Get post with author information
    $query = "
        SELECT 
            p.post_id, 
            p.title, 
            p.content, 
            p.image_url,
            p.created_at, 
            p.updated_at, 
            p.author_id,
            u.username AS author_name
        FROM 
            blog_posts p
        JOIN 
            users u ON p.author_id = u.user_id
        WHERE 
            p.post_id = ?
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $post_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    
    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit();
    }
    
    // Get comments for this post
    $comments_query = "
        SELECT 
            c.comment_id,
            c.content,
            c.created_at,
            u.username,
            u.user_id
        FROM 
            comments c
        JOIN 
            users u ON c.user_id = u.user_id
        WHERE 
            c.post_id = ?
        ORDER BY 
            c.created_at DESC
    ";
    
    $comments_stmt = $mysqli->prepare($comments_query);
    if (!$comments_stmt) {
        throw new Exception("Failed to prepare comments statement: " . $mysqli->error);
    }
    
    $comments_stmt->bind_param("i", $post_id);
    if (!$comments_stmt->execute()) {
        throw new Exception("Failed to execute comments statement: " . $comments_stmt->error);
    }
    
    $comments_result = $comments_stmt->get_result();
    $comments = [];
    
    while ($comment = $comments_result->fetch_assoc()) {
        $comment['created_at'] = date('M d, Y H:i', strtotime($comment['created_at']));
        $comments[] = $comment;
    }
    
    // Format dates for display
    $post['created_at'] = date('M d, Y H:i', strtotime($post['created_at']));
    $post['updated_at'] = date('M d, Y H:i', strtotime($post['updated_at']));
    
    // Add comments to post data
    $post['comments'] = $comments;
    
    echo json_encode([
        'success' => true,
        'post' => $post
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 