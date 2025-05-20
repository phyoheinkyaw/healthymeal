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

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate category ID
if (!isset($data['category_id']) || !is_numeric($data['category_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    exit();
}

$category_id = (int)$data['category_id'];

try {
    // Start transaction
    $mysqli->begin_transaction();

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

    // Check if category is being used by any meal kits
    $stmt = $mysqli->prepare("SELECT meal_kit_id FROM meal_kits WHERE category_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $category_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Cannot delete category: it is being used by one or more meal kits');
    }
    $stmt->close();

    // Delete category
    $stmt = $mysqli->prepare("DELETE FROM categories WHERE category_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $category_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete category: ' . $stmt->error);
    }

    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Category deleted successfully'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 