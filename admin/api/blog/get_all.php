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

try {
    // Get all blog posts with author information
    $query = "
        SELECT 
            p.post_id, 
            p.title, 
            LEFT(p.content, 200) AS content_preview, 
            p.image_url,
            p.created_at, 
            p.updated_at, 
            COALESCE(u.username, 'Unknown') AS author_name,
            p.author_id,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comment_count
        FROM 
            blog_posts p
        LEFT JOIN 
            users u ON p.author_id = u.user_id
        ORDER BY 
            p.created_at DESC
    ";
    
    $result = $mysqli->query($query);
    
    if (!$result) {
        throw new Exception("Failed to fetch blog posts: " . $mysqli->error);
    }
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates for display
        $row['created_at'] = date('M d, Y H:i', strtotime($row['created_at']));
        $row['updated_at'] = date('M d, Y H:i', strtotime($row['updated_at']));
        
        // Add content preview with ellipsis if needed
        if (strlen($row['content_preview']) == 200) {
            $row['content_preview'] .= '...';
        }
        
        $posts[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'posts' => $posts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 