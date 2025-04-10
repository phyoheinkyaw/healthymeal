<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate category ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    exit();
}

$category_id = (int)$_GET['id'];

try {
    // Get category details
    $stmt = $mysqli->prepare("SELECT * FROM categories WHERE category_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $category_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $category = $result->fetch_assoc();

    if (!$category) {
        throw new Exception('Category not found');
    }

    echo json_encode([
        'success' => true,
        'category' => $category
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 