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
if (!isset($_POST['name']) || empty(trim($_POST['name'])) || 
    !isset($_POST['description']) || empty(trim($_POST['description']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name and description are required']);
    exit();
}

// Sanitize input
$name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
$description = filter_var(trim($_POST['description']), FILTER_SANITIZE_STRING);

try {
    // Check if category name already exists
    $stmt = $mysqli->prepare("SELECT category_id FROM categories WHERE name = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("s", $name);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('A category with this name already exists');
    }
    $stmt->close();

    // Insert new category
    $stmt = $mysqli->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("ss", $name, $description);
    if (!$stmt->execute()) {
        throw new Exception('Failed to save category: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Category saved successfully',
        'category_id' => $stmt->insert_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 