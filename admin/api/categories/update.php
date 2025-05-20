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

// Validate required fields
if (!isset($_POST['category_id']) || !is_numeric($_POST['category_id']) ||
    !isset($_POST['name']) || empty(trim($_POST['name'])) || 
    !isset($_POST['description']) || empty(trim($_POST['description']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Sanitize input
$category_id = (int)$_POST['category_id'];
$name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
$description = filter_var(trim($_POST['description']), FILTER_SANITIZE_STRING);

try {
    // Check if category exists
    $stmt = $mysqli->prepare("SELECT category_id FROM categories WHERE category_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $category_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Category not found');
    }
    $stmt->close();

    // Check if name is already used by another category
    $stmt = $mysqli->prepare("SELECT category_id FROM categories WHERE name = ? AND category_id != ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("si", $name, $category_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('A category with this name already exists');
    }
    $stmt->close();

    // Update category
    $stmt = $mysqli->prepare("UPDATE categories SET name = ?, description = ? WHERE category_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("ssi", $name, $description, $category_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update category: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Category updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 