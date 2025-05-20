<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate required fields
$required_fields = ['ingredient_id', 'name', 'calories_per_100g', 'protein_per_100g', 'carbs_per_100g', 'fat_per_100g', 'price_per_100g'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// Sanitize and validate input
$ingredient_id = filter_var($_POST['ingredient_id'], FILTER_VALIDATE_INT);
$name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
$calories = filter_var($_POST['calories_per_100g'], FILTER_VALIDATE_FLOAT);
$protein = filter_var($_POST['protein_per_100g'], FILTER_VALIDATE_FLOAT);
$carbs = filter_var($_POST['carbs_per_100g'], FILTER_VALIDATE_FLOAT);
$fat = filter_var($_POST['fat_per_100g'], FILTER_VALIDATE_FLOAT);
$price = filter_var($_POST['price_per_100g'], FILTER_VALIDATE_FLOAT);

if (!$ingredient_id || !$calories || !$protein || !$carbs || !$fat || !$price) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input values'
    ]);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Check if ingredient exists
    $check_stmt = $mysqli->prepare("SELECT ingredient_id FROM ingredients WHERE ingredient_id = ?");
    $check_stmt->bind_param("i", $ingredient_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Ingredient not found");
    }

    $stmt = $mysqli->prepare("
        UPDATE ingredients SET
            name = ?,
            calories_per_100g = ?,
            protein_per_100g = ?,
            carbs_per_100g = ?,
            fat_per_100g = ?,
            price_per_100g = ?
        WHERE ingredient_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $mysqli->error);
    }

    // Convert to appropriate types for bind_param
    $calories = (float)$calories;
    $protein = (float)$protein;
    $carbs = (float)$carbs;
    $fat = (float)$fat;
    $price = (float)$price;

    $stmt->bind_param(
        "sdddddi",
        $name, $calories, $protein, $carbs, $fat, $price, $ingredient_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient updated successfully'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log("Error updating ingredient: " . $e->getMessage());
}