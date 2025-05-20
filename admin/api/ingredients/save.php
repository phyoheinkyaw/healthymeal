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
$required_fields = ['name', 'calories_per_100g', 'protein_per_100g', 'carbs_per_100g', 'fat_per_100g', 'price_per_100g'];
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
$name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
$calories = filter_var($_POST['calories_per_100g'], FILTER_VALIDATE_FLOAT);
$protein = filter_var($_POST['protein_per_100g'], FILTER_VALIDATE_FLOAT);
$carbs = filter_var($_POST['carbs_per_100g'], FILTER_VALIDATE_FLOAT);
$fat = filter_var($_POST['fat_per_100g'], FILTER_VALIDATE_FLOAT);
$price = filter_var($_POST['price_per_100g'], FILTER_VALIDATE_FLOAT);

if (!$calories || !$protein || !$carbs || !$fat || !$price) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid numerical values provided'
    ]);
    exit();
}

// Log the values for debugging
error_log("Adding ingredient - Name: $name, Calories: $calories, Protein: $protein, Carbs: $carbs, Fat: $fat, Price: $price");

// Start transaction
$mysqli->begin_transaction();

try {
    $stmt = $mysqli->prepare("
        INSERT INTO ingredients (
            name, calories_per_100g, protein_per_100g, carbs_per_100g, 
            fat_per_100g, price_per_100g
        ) VALUES (?, ?, ?, ?, ?, ?)
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
        "sddddd",
        $name, $calories, $protein, $carbs, $fat, $price
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $ingredient_id = $mysqli->insert_id;
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient created successfully',
        'ingredient_id' => $ingredient_id
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log("Error saving ingredient: " . $e->getMessage());
}