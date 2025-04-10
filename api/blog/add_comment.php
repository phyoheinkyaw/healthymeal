<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to comment']);
    exit();
}

// Validate input
$post_id = filter_var($_POST['post_id'], FILTER_VALIDATE_INT);
$content = trim($_POST['content']);

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit();
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit();
}

// Check if post exists
$stmt = $mysqli->prepare("SELECT post_id FROM blog_posts WHERE post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Blog post not found']);
    exit();
}

// Insert comment
$stmt = $mysqli->prepare("
    INSERT INTO comments (post_id, user_id, content, created_at)
    VALUES (?, ?, ?, NOW())
");

$user_id = $_SESSION['user_id'];
$stmt->bind_param("iis", $post_id, $user_id, $content);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Comment added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
} 