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
if (!isset($input['post_id']) || !is_numeric($input['post_id']) ||
    !isset($input['title']) || empty(trim($input['title'])) || 
    !isset($input['content']) || empty(trim($input['content']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Post ID, title, and content are required']);
    exit();
}

// Sanitize input
$post_id = (int)$input['post_id'];
$title = trim($input['title']);
$content = trim($input['content']);
$image_url = isset($input['image_url']) ? trim($input['image_url']) : '';

// Handle file upload if present
if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    // Setup upload directory
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/hm/uploads/blog/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
    $new_filename = 'blog_' . time() . '_' . uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_path)) {
        // Set image URL to relative path
        $image_url = '/hm/uploads/blog/' . $new_filename;
    } else {
        throw new Exception('Failed to upload image file');
    }
}

try {
    // Check if post exists and is editable by this admin (admins can edit all posts)
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
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Blog post not found']);
        exit();
    }
    $check_stmt->close();
    
    // Update blog post
    $query = "UPDATE blog_posts SET title = ?, content = ?, image_url = ?, updated_at = NOW() WHERE post_id = ?";
    $stmt = $mysqli->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare update statement: ' . $mysqli->error);
    }
    
    $stmt->bind_param("sssi", $title, $content, $image_url, $post_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update blog post: ' . $stmt->error);
    }
    
    // Get updated post with author info
    $get_query = "
        SELECT 
            p.post_id, 
            p.title, 
            LEFT(p.content, 200) AS content_preview, 
            p.created_at, 
            p.updated_at, 
            u.username AS author_name,
            u.user_id AS author_id,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comment_count
        FROM 
            blog_posts p
        JOIN 
            users u ON p.author_id = u.user_id
        WHERE 
            p.post_id = ?
    ";
    
    $get_stmt = $mysqli->prepare($get_query);
    if (!$get_stmt) {
        throw new Exception('Failed to prepare get statement: ' . $mysqli->error);
    }
    
    $get_stmt->bind_param("i", $post_id);
    if (!$get_stmt->execute()) {
        throw new Exception('Failed to execute get statement: ' . $get_stmt->error);
    }
    
    $result = $get_stmt->get_result();
    $post = $result->fetch_assoc();
    
    // Format dates
    $post['created_at'] = date('M d, Y H:i', strtotime($post['created_at']));
    $post['updated_at'] = date('M d, Y H:i', strtotime($post['updated_at']));
    
    // Add ellipsis to content preview if needed
    if (strlen($post['content_preview']) == 200) {
        $post['content_preview'] .= '...';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Blog post updated successfully',
        'post' => $post
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 